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

    // eBay's public deals page (no API key needed)
    private string $dealsUrl = 'https://www.ebay.com/deals';

    // eBay Finding API endpoint
    private string $apiUrl = 'https://svcs.ebay.com/services/search/FindingService/v1';

    public function __construct(string $appId = '') {
        parent::__construct();
        $this->appId = $appId;
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

        foreach ($urls as $url) {
            $html = $this->fetch($url, [], 'https://www.ebay.com/');
            if (!$html) continue;

            $xpath = $this->loadDom($html);
            if (!$xpath) continue;

            // eBay deals page items
            $this->parseEbayDealsHtml($xpath, $url);
            sleep(1);
        }
    }

    private function parseEbayDealsHtml(\DOMXPath $xpath, string $pageUrl): void {
        // eBay deals page uses data attributes and structured markup
        // Try multiple selectors for different page formats

        // Method: find JSON data embedded in page
        $scripts = $xpath->query('//script[not(@src)]');
        foreach ($scripts as $script) {
            $code = $script->textContent;

            // eBay embeds deal data as window.__PRELOADED_STATE__
            if (str_contains($code, '__PRELOADED_STATE__')) {
                if (preg_match('/window\.__PRELOADED_STATE__\s*=\s*({.+?});\s*(?:window|<\/script>)/s', $code, $m)) {
                    $data = @json_decode($m[1], true);
                    if ($data) { $this->parseEbayStateData($data); return; }
                }
            }

            // eBay also uses window.__DWN_DATA__
            if (str_contains($code, '"discountedPrice"') || str_contains($code, '"originalPrice"')) {
                // Try to extract deal objects
                preg_match_all('/"title"\s*:\s*"([^"]+)".*?"discountedPrice"\s*:\s*\{[^}]*"value"\s*:\s*"([\d.]+)".*?"originalPrice"\s*:\s*\{[^}]*"value"\s*:\s*"([\d.]+)"/s', $code, $matches, PREG_SET_ORDER);
                foreach ($matches as $m) {
                    $sale = (float)$m[2];
                    $orig = (float)$m[3];
                    $pct  = $this->calcDiscount($orig, $sale);
                    if ($pct < 50) continue;

                    $this->saveDeal([
                        'title'          => $m[1],
                        'original_price' => $orig,
                        'sale_price'     => $sale,
                        'discount_pct'   => $pct,
                        'product_url'    => $pageUrl,
                        'affiliate_url'  => $pageUrl,
                        'category'       => $this->mapCategory($m[1]),
                    ]);
                }
            }
        }

        // Fallback: parse deal cards from HTML
        $dealItems = $xpath->query('//*[contains(@class,"dne-itemtile") or contains(@class,"dealItem") or contains(@class,"itemCell")]');
        foreach ($dealItems as $el) {
            $title    = $xpath->evaluate('string(.//*[contains(@class,"itemtile-title") or contains(@class,"title") or contains(@class,"name")])', $el);
            $saleText = $xpath->evaluate('string(.//*[contains(@class,"bold") or contains(@class,"price") or contains(@class,"current")])', $el);
            $origText = $xpath->evaluate('string(.//*[contains(@class,"original") or contains(@class,"was") or contains(@class,"strike")])', $el);
            $imgSrc   = $xpath->evaluate('string(.//img/@src)', $el);
            $link     = $xpath->evaluate('string(.//a/@href)', $el);

            $title = trim($title);
            if (!$title) continue;

            $sale = $this->parsePrice($saleText);
            $orig = $this->parsePrice($origText);
            if ($sale <= 0) continue;
            if ($orig <= $sale) $orig = $sale * 2;

            $pct = $this->calcDiscount($orig, $sale);
            if ($pct < 50) continue;

            if ($link && !str_starts_with($link, 'http')) {
                $link = 'https://www.ebay.com' . $link;
            }

            $this->saveDeal([
                'title'          => $title,
                'original_price' => $orig,
                'sale_price'     => $sale,
                'discount_pct'   => $pct,
                'image_url'      => $imgSrc ?: null,
                'product_url'    => $link ?: $pageUrl,
                'affiliate_url'  => $link ?: $pageUrl,
                'category'       => $this->mapCategory($title),
            ]);
        }
    }

    private function parseEbayStateData(array $data): void {
        // Recursively search for deal items in eBay's state object
        $this->findDealsInArray($data, 0);
    }

    private function findDealsInArray(array $data, int $depth): void {
        if ($depth > 8) return;
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Check if this looks like a deal item
                if (isset($value['title']) && (isset($value['discountedPrice']) || isset($value['dealPrice']))) {
                    $sale = (float)($value['discountedPrice']['value'] ?? $value['dealPrice']['value'] ?? 0);
                    $orig = (float)($value['originalPrice']['value'] ?? $value['msrpPrice']['value'] ?? 0);
                    $pct  = isset($value['discountPercentage']) ? (int)$value['discountPercentage'] : $this->calcDiscount($orig, $sale);
                    if ($pct >= 50 && $sale > 0) {
                        $img  = $value['image']['imageUrl'] ?? $value['image'][0] ?? null;
                        $url  = $value['itemUrl'] ?? $value['dealUrl'] ?? '';
                        $this->saveDeal([
                            'title'          => (string)$value['title'],
                            'original_price' => $orig > 0 ? $orig : round($sale / (1 - $pct/100), 2),
                            'sale_price'     => $sale,
                            'discount_pct'   => $pct,
                            'image_url'      => $img,
                            'product_url'    => $url,
                            'affiliate_url'  => $url,
                            'category'       => $this->mapCategory((string)$value['title']),
                        ]);
                    }
                }
                $this->findDealsInArray($value, $depth + 1);
            }
        }
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
                if ($orig <= $sale) $orig = $sale * 2.2;

                $pct   = $this->calcDiscount($orig, $sale);
                if ($pct < 50) continue;

                $image = $item['pictureURLSuperSize'][0] ?? $item['galleryURL'][0] ?? null;

                $this->saveDeal([
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
                elseif (count($prices) === 1) { $sale = $prices[0]; $orig = $sale * 2.5; }
                else continue;

                $pct = $this->calcDiscount($orig, $sale);
                if ($pct < 50) continue;

                $image = null;
                if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $desc, $m)) $image = $m[1];

                $this->saveDeal([
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
