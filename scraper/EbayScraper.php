<?php
/**
 * EbayScraper.php
 *
 * eBay has a FREE public Finding API that returns real product listings
 * with images, prices, titles and direct links.
 *
 * FREE tier: 5,000 calls/day. No credit card. Get key at:
 * https://developer.ebay.com/signin (free signup)
 *
 * We search for items with huge discounts by comparing Buy-It-Now price
 * to original retail value. Also includes eBay Deals page scraping.
 *
 * eBay images are always accessible (no hotlink blocking).
 */
require_once __DIR__ . '/BaseScraper.php';

class EbayScraper extends BaseScraper {
    protected string $store = 'ebay';
    private string $appId;
    private array  $seenUrls = [];  // dedup within a single run

    // eBay's public deals page (no API key needed)
    private string $dealsUrl = 'https://www.ebay.com/deals';

    // eBay Finding API endpoint
    private string $apiUrl = 'https://svcs.ebay.com/services/search/FindingService/v1';

    public function __construct(string $appId = '') {
        parent::__construct();
        $this->appId = $appId;
    }

    // Dedup wrapper — eBay's recursive state traversal can yield same URL twice
    private function saveEbayDeal(array $d): bool {
        $url = $d['product_url'] ?? '';
        if (!$url || isset($this->seenUrls[$url])) return false;
        $this->seenUrls[$url] = true;

        // Upgrade eBay image URLs to larger size (s-l225 → s-l500)
        if (!empty($d['image_url'])) {
            $d['image_url'] = preg_replace('/s-l\d+\./', 's-l500.', $d['image_url']);
        }

        // Skip deals without real product images
        $img = $d['image_url'] ?? '';
        if (!$img || str_contains($img, 's_1x2') || str_contains($img, '1x1') ||
            str_contains($img, 'pixel') || str_contains($img, 'spacer') ||
            !str_contains($img, 'ebayimg.com')) {
            return false;
        }

        return $this->saveDeal($d);
    }

    public function scrape(): void {
        $this->say("Starting eBay scrape...");

        // Method 1: eBay Deals page (no API key)
        $this->scrapeDealsPage();

        // Method 2: eBay Finding API (if key provided)
        if ($this->appId) {
            $this->scrapeViaApi();
        }

        // Method 3: eBay RSS feeds for categories
        $this->scrapeEbayRss();

        $this->logResult('success', 'eBay');
    }

    // ── eBay Deals page ───────────────────────────────────────────────────────
    private function scrapeDealsPage(): void {
        $this->say("Scraping eBay Deals page...");

        $urls = [
            'https://www.ebay.com/deals',
            'https://www.ebay.com/deals/electronics',
            'https://www.ebay.com/deals/home-garden',
            'https://www.ebay.com/deals/fashion',
            'https://www.ebay.com/deals/sporting-goods',
            'https://www.ebay.com/deals/toys-hobbies',
        ];

        // Force US locale for USD prices
        $headers = [
            'Accept-Language: en-US,en;q=0.9',
            'Cookie: dp1=bl/US/;',
        ];

        foreach ($urls as $url) {
            $html = $this->fetch($url, $headers, 'https://www.ebay.com/');
            if (!$html) continue;

            $this->say('  ✓ ' . number_format(strlen($html)) . ' bytes');

            $xpath = $this->loadDom($html);
            if (!$xpath) continue;

            $n = $this->parseEbayDealsHtml($xpath, $url);
            $this->say("  → {$n} deals from " . basename(parse_url($url, PHP_URL_PATH) ?: 'deals'));
            sleep(1);
        }
    }

    private function parseEbayDealsHtml(\DOMXPath $xpath, string $pageUrl): int {
        $saved = 0;

        // eBay uses dne-itemtile class for deal cards (skip "show more" tiles)
        $tiles = $xpath->query(
            '//*[contains(@class,"dne-itemtile") and not(contains(@class,"dne-show-more"))]'
        );

        if (!$tiles || $tiles->length === 0) {
            $this->say('  No deal tiles found');
            return 0;
        }

        foreach ($tiles as $tile) {
            // Title: dne-itemtile-title class
            $title = trim($xpath->evaluate(
                'string(.//*[contains(@class,"dne-itemtile-title")])', $tile
            ));
            if (!$title) continue;

            // Sale price: dne-itemtile-price class
            $saleText = $xpath->evaluate(
                'string(.//*[contains(@class,"dne-itemtile-price") and not(contains(@class,"original"))])', $tile
            );
            // Original price: dne-itemtile-original-price class
            $origText = $xpath->evaluate(
                'string(.//*[contains(@class,"dne-itemtile-original-price")])', $tile
            );

            $sale = $this->parsePrice($saleText);
            $orig = $this->parsePrice($origText);
            if ($sale <= 0) continue;

            // eBay includes "XX% off" in the original price text — extract it
            $pct = 0;
            if (preg_match('/(\d+)\s*%\s*off/i', $origText, $pctMatch)) {
                $pct = (int)$pctMatch[1];
            }
            if ($pct === 0 && $orig > $sale) {
                $pct = $this->calcDiscount($orig, $sale);
            }
            // Reconstruct original if we have pct but not orig
            if ($orig <= $sale && $pct > 0 && $sale > 0) {
                $orig = round($sale / (1 - $pct / 100), 2);
            }
            if ($pct < 50 || $orig <= $sale) continue;

            // Image: real product image from img tag
            $imgSrc = $xpath->evaluate('string(.//img/@src)', $tile);
            if (!$imgSrc || str_contains($imgSrc, '1x1') || str_contains($imgSrc, 'pixel')) {
                $imgSrc = $xpath->evaluate('string(.//img/@data-src)', $tile);
            }

            // Link
            $link = $xpath->evaluate('string(.//a/@href)', $tile);
            if ($link && !str_starts_with($link, 'http')) {
                $link = 'https://www.ebay.com' . $link;
            }
            if (!$link) continue;

            if ($this->saveEbayDeal([
                'title'          => $title,
                'original_price' => $orig,
                'sale_price'     => $sale,
                'discount_pct'   => $pct,
                'image_url'      => $imgSrc ?: null,
                'product_url'    => $link,
                'affiliate_url'  => $link,
                'category'       => $this->mapCategory($title),
            ])) $saved++;
        }
        return $saved;
    }

    // ── eBay Finding API ──────────────────────────────────────────────────────
    private function scrapeViaApi(): void {
        $categories = [
            '293'  => 'electronics',   // Consumer Electronics
            '58058'=> 'electronics',   // Computers/Tablets
            '11450'=> 'clothing',      // Clothing/Shoes
            '11700'=> 'home',          // Home & Garden
            '220'  => 'toys',          // Toys & Hobbies
            '888'  => 'sports',        // Sporting Goods
        ];

        foreach ($categories as $catId => $catName) {
            $params = http_build_query([
                'OPERATION-NAME'               => 'findItemsAdvanced',
                'SERVICE-VERSION'              => '1.13.0',
                'SECURITY-APPNAME'             => $this->appId,
                'RESPONSE-DATA-FORMAT'         => 'JSON',
                'REST-PAYLOAD'                 => '',
                'categoryId'                   => $catId,
                'paginationInput.entriesPerPage' => '50',
                'itemFilter(0).name'           => 'ListingType',
                'itemFilter(0).value'          => 'FixedPrice',
                'itemFilter(1).name'           => 'MinDiscountPercentage',
                'itemFilter(1).value'          => '50',
                'sortOrder'                    => 'BestMatch',
                'outputSelector(0)'            => 'PictureURLSuperSize',
            ]);

            $data = $this->fetchJson($this->apiUrl . '?' . $params);
            if (!$data) continue;

            $items = $data['findItemsAdvancedResponse'][0]['searchResult'][0]['item'] ?? [];
            foreach ($items as $item) {
                $title = $item['title'][0] ?? '';
                $url   = $item['viewItemURL'][0] ?? '';
                if (!$title || !$url) continue;

                $sale = (float)($item['sellingStatus'][0]['currentPrice'][0]['__value__'] ?? 0);
                $orig = (float)($item['listingInfo'][0]['listingType'][0] === 'FixedPrice'
                    ? ($item['sellingStatus'][0]['convertedCurrentPrice'][0]['__value__'] ?? 0)
                    : 0);

                if ($sale <= 0) continue;
                if ($orig <= $sale) continue;

                $pct   = $this->calcDiscount($orig, $sale);
                if ($pct < 50) continue;

                $image = $item['pictureURLSuperSize'][0] ?? $item['galleryURL'][0] ?? null;

                $this->saveEbayDeal([
                    'title'          => $title,
                    'original_price' => $orig,
                    'sale_price'     => $sale,
                    'discount_pct'   => $pct,
                    'image_url'      => $image,
                    'product_url'    => $url,
                    'affiliate_url'  => $url,
                    'category'       => $catName,
                ]);
            }
            sleep(1);
        }
    }

    // ── eBay RSS (category feeds) ─────────────────────────────────────────────
    private function scrapeEbayRss(): void {
        // eBay publishes RSS feeds for saved searches
        $feeds = [
            'https://www.ebay.com/sch/i.html?_nkw=clearance+electronics&LH_BIN=1&_sop=16&_rss=1',
            'https://www.ebay.com/sch/i.html?_nkw=50+percent+off&LH_BIN=1&LH_Sale=1&_sop=16&_rss=1',
        ];

        foreach ($feeds as $url) {
            $xml = $this->fetchRss($url);
            if (!$xml) continue;

            foreach ($xml->channel->item ?? [] as $item) {
                $title    = (string)($item->title ?? '');
                $link     = (string)($item->link  ?? '');
                $desc     = (string)($item->description ?? '');
                if (!$title || !$link) continue;

                $sale = $orig = 0.0;
                preg_match_all('/\$\s*([\d,]+\.?\d{0,2})/', strip_tags($desc) . $title, $all);
                $prices = array_map(fn($p) => (float)str_replace(',', '', $p), $all[1] ?? []);
                $prices = array_values(array_filter($prices, fn($p) => $p >= 1 && $p <= 50000));
                sort($prices);
                if (count($prices) >= 2) { $sale = $prices[0]; $orig = end($prices); }
                else continue; // skip items with only 1 price (can't verify discount)

                $pct = $this->calcDiscount($orig, $sale);
                if ($pct < 50) continue;

                $image = null;
                if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $desc, $m)) $image = $m[1];

                $this->saveEbayDeal([
                    'title'          => strip_tags($title),
                    'original_price' => $orig,
                    'sale_price'     => $sale,
                    'discount_pct'   => $pct,
                    'image_url'      => $image,
                    'product_url'    => $link,
                    'affiliate_url'  => $link,
                    'category'       => $this->mapCategory($title),
                ]);
            }
            sleep(1);
        }
    }
}
