<?php
/**
 * WalmartScraper.php
 *
 * Targets: https://www.walmart.com/shop/deals/flash-deals
 *
 * HOW WALMART'S PAGE WORKS:
 * ──────────────────────────
 * Walmart's website is built with Next.js. Every page embeds ALL product data
 * in a <script id="__NEXT_DATA__" type="application/json"> tag.
 *
 * This JSON contains the complete product grid including:
 *   - Product title
 *   - Current (sale) price
 *   - Was (original) price
 *   - Savings percentage
 *   - Product image URL (from Walmart's image CDN)
 *   - Item ID (usItemId) for building URLs
 *   - Ratings and review count
 *
 * The data path is typically:
 *   __NEXT_DATA__.props.pageProps.initialData.searchResult.itemStacks[].items[]
 *
 * We also try the moduleData path for some page types:
 *   __NEXT_DATA__.props.pageProps.initialData.contentLayout.modules[].configs.products[]
 *
 * Walmart images are on i5.walmartimages.com — they load fine with the proxy.
 *
 * FILTER: Only save deals with 50%+ off (wasPrice vs currentPrice).
 */
require_once __DIR__ . '/BaseScraper.php';

class WalmartScraper extends BaseScraper {
    protected string $store = 'walmart';

    // Walmart deals pages to scrape
    private array $dealPages = [
        'https://www.walmart.com/shop/deals/flash-deals',
        'https://www.walmart.com/browse/rollback?cat_id=0&facet=special_offers%3ARollback',
        'https://www.walmart.com/browse/electronics?facet=special_offers%3ARollback',
        'https://www.walmart.com/browse/home?facet=special_offers%3ARollback',
        'https://www.walmart.com/browse/clothing?facet=special_offers%3ARollback',
        'https://www.walmart.com/browse/toys?facet=special_offers%3ARollback',
    ];

    public function scrape(): void {
        $this->say("=== Walmart Flash Deals Scraper ===");
        $this->say("Target: walmart.com/shop/deals/flash-deals");

        foreach ($this->dealPages as $url) {
            $this->say("Fetching: " . str_replace('https://www.walmart.com', '', $url));

            $html = $this->fetchWalmartPage($url);
            if (!$html) { $this->say("  → No response"); sleep(3); continue; }

            $count = $this->parsePage($html);
            $this->say("  → $count deals found");
            sleep(rand(3, 5));
        }

        $this->logResult('success', "Walmart Flash Deals");
    }

    // ── Fetch with Walmart-specific headers ───────────────────────────────────
    private function fetchWalmartPage(string $url): string|false {
        return $this->fetch($url, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'sec-ch-ua: "Chromium";v="124", "Google Chrome";v="124"',
            'sec-ch-ua-mobile: ?0',
            'sec-fetch-dest: document',
            'sec-fetch-mode: navigate',
            'sec-fetch-site: none',
        ], 'https://www.walmart.com/');
    }

    // ── Parse the page ────────────────────────────────────────────────────────
    private function parsePage(string $html): int {
        $count = 0;

        // ── PRIMARY METHOD: Extract __NEXT_DATA__ JSON ────────────────────
        // Walmart embeds ALL product data here. The script tag may have extra
        // attributes (e.g. nonce="...") so we locate it positionally rather
        // than with a strict regex.
        $scriptPos = strpos($html, 'script id="__NEXT_DATA__"');
        if ($scriptPos !== false) {
            $jsonStart = strpos($html, '>', $scriptPos) + 1;
            $jsonEnd   = strpos($html, '</script>', $jsonStart);
            $nextData  = @json_decode(substr($html, $jsonStart, $jsonEnd - $jsonStart), true);
            if ($nextData) {
                $count += $this->processNextData($nextData);
                if ($count > 0) return $count;
            }
        }

        // ── FALLBACK: Parse HTML directly ─────────────────────────────────
        $count += $this->parseHtmlFallback($html);
        return $count;
    }

    // ── Process __NEXT_DATA__ JSON ────────────────────────────────────────────
    private function processNextData(array $data): int {
        $count = 0;

        // Walmart uses several possible paths for product data
        $productPaths = [
            // Standard search/browse result
            ['props','pageProps','initialData','searchResult','itemStacks'],
            // Deals/flash deals page
            ['props','pageProps','initialData','contentLayout','modules'],
            // Alternative path
            ['props','pageProps','initialData','searchResult','searchResult','itemStacks'],
            // Category page
            ['props','pageProps','initialData','categoryContent','modules'],
        ];

        $allItems = [];

        foreach ($productPaths as $path) {
            $node = $data;
            foreach ($path as $key) {
                $node = $node[$key] ?? null;
                if (!$node) break;
            }
            if (!$node) continue;

            // itemStacks is an array of stacks, each with items
            if (isset($node[0]['items'])) {
                foreach ($node as $stack) {
                    $allItems = array_merge($allItems, $stack['items'] ?? []);
                }
                break;
            }
            // modules path — each module may have configs.products
            elseif (isset($node[0]['type'])) {
                foreach ($node as $module) {
                    $products = $module['configs']['products']         ??
                                $module['configs']['items']            ??
                                $module['moduleData']['products']      ??
                                $module['moduleData']['items']         ?? [];
                    $allItems = array_merge($allItems, $products);
                }
                break;
            }
        }

        foreach ($allItems as $item) {
            if ($this->processWalmartItem($item)) $count++;
        }
        return $count;
    }

    // ── Process a single Walmart product item ─────────────────────────────────
    private function processWalmartItem(array $item): bool {
        // Title
        $title = $item['name'] ?? $item['title'] ?? '';
        if (!$title || strlen($title) < 3) return false;

        // Item ID for URL
        $itemId = $item['usItemId'] ?? $item['itemId'] ?? $item['id'] ?? null;
        if (!$itemId) return false;

        // ── Price extraction ────────────────────────────────────────────────
        // Walmart's current __NEXT_DATA__ structure (verified 2026):
        //   item['price']          → numeric sale price  (e.g. 193.99)
        //   priceInfo['wasPrice']  → string orig price   (e.g. "$399.99")
        //   priceInfo['savingsAmt']→ numeric dollar savings (e.g. 206)
        $priceInfo = $item['priceInfo'] ?? [];

        $sale = (float)($item['price'] ?? $priceInfo['minPrice'] ?? 0);

        // wasPrice is a formatted string like "$399.99" — strip non-numeric chars
        $origRaw = $priceInfo['wasPrice'] ?? '';
        $orig    = $origRaw ? $this->parsePrice((string)$origRaw) : 0.0;

        // Savings amount lets us derive orig if wasPrice is missing
        $savingsAmt = (float)($priceInfo['savingsAmt'] ?? 0);
        if ($orig <= 0 && $sale > 0 && $savingsAmt > 0) {
            $orig = round($sale + $savingsAmt, 2);
        }

        $pct = 0;

        if ($sale <= 0) return false;

        // Derive orig from pct if missing
        if ($orig <= $sale && $pct >= 50 && $sale > 0) {
            $orig = round($sale / (1 - $pct / 100), 2);
        }

        // Calc pct if we have both prices
        if ($pct < 50 && $orig > $sale && $sale > 0) {
            $pct = $this->calcDiscount($orig, $sale);
        }

        // Only keep 50%+ deals
        if ($pct < 50 || $orig <= $sale) return false;

        // ── Image ──────────────────────────────────────────────────────────
        // Walmart images: https://i5.walmartimages.com/asr/UUID.jpg
        $image = null;
        $imgInfo = $item['imageInfo'] ?? $item['image'] ?? null;
        if (is_array($imgInfo)) {
            $image = $imgInfo['thumbnailUrl'] ??
                     $imgInfo['url']          ??
                     ($imgInfo['allImages'][0]['url'] ?? null);
        } elseif (is_string($imgInfo)) {
            $image = $imgInfo;
        }

        // ── Rating ─────────────────────────────────────────────────────────
        $ratingInfo  = $item['ratingsReviews'] ?? $item['rating'] ?? [];
        $rating      = is_array($ratingInfo) ? (float)($ratingInfo['averageStarRating'] ?? $ratingInfo['rating'] ?? 0) : (float)$ratingInfo;
        $reviewCount = is_array($ratingInfo) ? (int)($ratingInfo['numberOfReviews'] ?? 0) : 0;

        // ── Build clean product URL ─────────────────────────────────────────
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', substr($title, 0, 50)));
        $slug = trim($slug, '-');
        $productUrl  = "https://www.walmart.com/ip/{$slug}/{$itemId}";
        $affiliateUrl = $productUrl; // Add your Walmart affiliate params here

        return $this->saveDeal([
            'title'          => trim($title),
            'description'    => isset($item['shortDescription']) ? strip_tags($item['shortDescription']) : null,
            'original_price' => $orig,
            'sale_price'     => $sale,
            'discount_pct'   => $pct,
            'image_url'      => $image,
            'product_url'    => $productUrl,
            'affiliate_url'  => $affiliateUrl,
            'category'       => $this->mapCategory($title . ' ' . ($item['category']['path'] ?? '')),
            'rating'         => $rating > 0 ? $rating : null,
            'review_count'   => $reviewCount,
        ]);
    }

    // ── HTML fallback parser ──────────────────────────────────────────────────
    private function parseHtmlFallback(string $html): int {
        $count = 0;
        $xpath = $this->loadDom($html);
        if (!$xpath) return 0;

        // Walmart product tiles in search results
        $tiles = $xpath->query('//*[@data-item-id or contains(@class,"search-result-gridview-item") or contains(@class,"Grid-col")]');

        foreach ($tiles as $tile) {
            /** @var \DOMElement $tile */
            $itemId = $tile->getAttribute('data-item-id');
            if (!$itemId) {
                $links = $xpath->query('.//a[contains(@href,"/ip/")]', $tile);
                if ($links->length > 0) {
                    /** @var \DOMElement $link */
                    $link = $links->item(0);
                    preg_match('|/ip/[^/]+/(\d+)|', $link->getAttribute('href'), $m);
                    $itemId = $m[1] ?? null;
                }
            }
            if (!$itemId) continue;

            $title = trim($xpath->evaluate('string(.//*[contains(@class,"product-title") or contains(@class,"f6") or contains(@class,"f7")])', $tile));
            if (!$title) continue;

            $saleText = $xpath->evaluate('string(.//*[contains(@class,"price-characteristic") or contains(@class,"w_iUH")])', $tile);
            $origText = $xpath->evaluate('string(.//*[contains(@class,"price-old") or contains(@class,"was-price") or contains(@class,"line-through")])', $tile);
            $pctText  = $xpath->evaluate('string(.//*[contains(@class,"flag-reduced") or contains(@class,"percent-off") or contains(text(),"%") and contains(text(),"off")])', $tile);

            $sale = $this->parsePrice($saleText);
            $orig = $this->parsePrice($origText);
            $pct  = 0;
            if (preg_match('/(\d+)\s*%/', $pctText, $pm)) $pct = (int)$pm[1];

            if ($sale <= 0) continue;
            if ($pct < 50 && $orig > $sale) $pct = $this->calcDiscount($orig, $sale);
            if ($pct < 50) continue;
            if ($orig <= $sale) $orig = round($sale / (1 - $pct/100), 2);

            $imgSrc = $xpath->evaluate('string(.//img/@src)', $tile);

            $this->saveDeal([
                'title'          => $title,
                'original_price' => $orig,
                'sale_price'     => $sale,
                'discount_pct'   => $pct,
                'image_url'      => $imgSrc ?: null,
                'product_url'    => "https://www.walmart.com/ip/{$itemId}",
                'affiliate_url'  => "https://www.walmart.com/ip/{$itemId}",
                'category'       => $this->mapCategory($title),
            ]);
            $count++;
        }
        return $count;
    }
}
