<?php
/**
 * SixPmScraper.php — 6pm.com sale & clearance (50%+ off)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * HOW IT WORKS:
 * ─────────────
 * 6pm.com (owned by Zappos/Amazon) is a fashion off-price retailer. Their
 * product listing pages are Next.js apps that embed ALL product data in
 * window.__INITIAL_STATE__ — a large JSON blob on every page that contains
 * complete pricing: originalPrice, price (sale), percentOff, brand, name,
 * images, productId.
 *
 * URLs: /c/sale?pge=0&s=SalePrice/desc filters sale items, sorted by price
 * descending. The percentOff filter is: /c/sale?pge=0&s=PercentOff/desc
 *
 * 6pm also has a JSON API: https://www.6pm.com/api/products/search?...
 * but it requires session cookies. The embedded JSON is more reliable.
 *
 * AFFILIATE:
 * ──────────
 * 6pm.com affiliate program is through Commission Junction (CJ Affiliate).
 * Product URLs use direct 6pm.com links until affiliate approval.
 * Register at: https://www.cj.com/advertiser-details/6pm-com
 */
require_once __DIR__ . '/BaseScraper.php';

class SixPmScraper extends BaseScraper
{
    protected string $store = '6pm';
    private   int    $limit = 60;

    // Sale pages sorted by highest % off first
    private const PAGES = [
        'https://www.6pm.com/c/sale?s=PercentOff/desc&pge=0',
        'https://www.6pm.com/c/sale?s=PercentOff/desc&pge=1',
        'https://www.6pm.com/c/shoes?s=PercentOff/desc&pge=0',
        'https://www.6pm.com/c/clothing?s=PercentOff/desc&pge=0',
        'https://www.6pm.com/c/bags-handbags?s=PercentOff/desc&pge=0',
    ];

    public function scrape(): void
    {
        $this->say('=== 6pm Scraper — 6pm.com (50%+ off) ===');
        $savedTotal = 0;

        foreach (self::PAGES as $url) {
            if ($savedTotal >= $this->limit) break;

            $pageLabel = parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);
            $this->say("→ {$pageLabel}...");
            sleep(rand(2, 4));

            $html = $this->fetch($url, [
                'Accept: text/html,application/xhtml+xml,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Upgrade-Insecure-Requests: 1',
            ], 'https://www.6pm.com/');

            if (!$html) {
                $this->say("  ✗ No response. Skipping.");
                continue;
            }
            if (strlen($html) < 5000) {
                $this->say("  ✗ Response too short — likely blocked.");
                continue;
            }

            $this->say("  ✓ " . number_format(strlen($html)) . " bytes");

            // Try embedded __INITIAL_STATE__ JSON
            $n = $this->parseInitialState($html);

            // Fall back to __NEXT_DATA__
            if ($n === 0) $n = $this->parseNextData($html);

            // Fall back to HTML product tiles
            if ($n === 0) $n = $this->parseHtmlTiles($html);

            $savedTotal += $n;
            $this->say("  Saved {$n} deals from this page");
        }

        $this->say("══ Done: {$savedTotal} deals saved ══");
        $this->logResult($savedTotal > 0 ? 'success' : 'warning', "6pm.com 50%+ (saved: {$savedTotal})");
    }

    // ── Primary: window.__INITIAL_STATE__ or window.__APP_STATE__ ───────────
    private function parseInitialState(string $html): int
    {
        $state = null;
        foreach ([
            '/window\.__INITIAL_STATE__\s*=\s*(\{.+?\});\s*(?:window\.|<\/script>)/s',
            '/window\.__APP_STATE__\s*=\s*(\{.+?\});\s*(?:window\.|<\/script>)/s',
        ] as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $decoded = json_decode($m[1], true);
                if ($decoded && json_last_error() === JSON_ERROR_NONE) { $state = $decoded; break; }
            }
        }
        if (!$state) return 0;

        // 6pm state structure: state.products.list[] or state.search.results[]
        $products = $state['products']['list']
                 ?? $state['search']['results']
                 ?? $state['products']['searchResult']['list']
                 ?? [];

        if (empty($products) && isset($state['products'])) {
            foreach ($state['products'] as $val) {
                if (is_array($val) && isset($val[0]['productId'])) { $products = $val; break; }
            }
        }

        if (empty($products)) {
            $this->say("  No products in __INITIAL_STATE__");
            return 0;
        }

        $this->say("  Found " . count($products) . " products in __INITIAL_STATE__");
        return $this->processProducts($products);
    }

    // ── Fallback: __NEXT_DATA__ ───────────────────────────────────────────────
    // NOTE: 6pm __NEXT_DATA__ path confirmed: props.pageProps.initialData.products[]
    // Prices are in CENTS (integer) — must divide by 100
    private function parseNextData(string $html): int
    {
        if (!preg_match('/<script[^>]+id=["\']__NEXT_DATA__["\'][^>]*>(.+?)<\/script>/s', $html, $m)) {
            return 0;
        }
        $data = json_decode($m[1], true);
        if (!$data || json_last_error() !== JSON_ERROR_NONE) return 0;

        // Confirmed path: props.pageProps.initialData.products[]
        $products = $data['props']['pageProps']['initialData']['products']
                 ?? $data['props']['pageProps']['products']
                 ?? $data['props']['pageProps']['searchResult']['list']
                 ?? $data['props']['pageProps']['initialState']['products']['list']
                 ?? [];

        if (empty($products)) return 0;
        $this->say("  Found " . count($products) . " products in __NEXT_DATA__");

        // Flag that prices need cents→dollars conversion
        return $this->processProducts($products, true);
    }

    // ── Process product list (either source) ──────────────────────────────────
    // $centsMode: true when prices are integers in cents (from __NEXT_DATA__)
    private function processProducts(array $products, bool $centsMode = false): int
    {
        $saved = 0;
        foreach ($products as $prod) {
            if (!is_array($prod)) continue;

            // Title: brand + name
            // __NEXT_DATA__ confirmed fields: productName, brand (object with name)
            $brand = $prod['brandName']
                  ?? (is_array($prod['brand'] ?? null) ? ($prod['brand']['name'] ?? '') : ($prod['brand'] ?? ''))
                  ?? '';
            $name  = $prod['productName'] ?? $prod['name'] ?? $prod['styleName'] ?? '';
            $title = trim($brand ? "{$brand} {$name}" : $name);
            if (!$title) continue;

            // Pricing — confirmed __NEXT_DATA__ fields: msrp (cents), price (cents), percentOff
            $saleRaw     = $prod['price']           ?? $prod['salePrice']       ?? $prod['minPrice']  ?? 0;
            $originalRaw = $prod['msrp']            ?? $prod['originalPrice']   ?? $prod['retailPrice']?? $prod['maxPrice']  ?? 0;
            $pct         = (int)($prod['percentOff'] ?? $prod['discountPercent'] ?? 0);

            // Convert cents → dollars if needed
            $divisor  = $centsMode ? 100.0 : 1.0;
            $sale     = (float)$saleRaw     / $divisor;
            $original = (float)$originalRaw / $divisor;

            if ($sale <= 0) continue;
            if ($original <= 0 && $pct > 0) $original = round($sale / (1 - $pct / 100), 2);
            if ($original > 0 && $sale < $original) $pct = max($pct, $this->calcDiscount($original, $sale));
            if ($pct < 50 || $original <= 0 || $original <= $sale) continue;

            // Image — confirmed __NEXT_DATA__ field: defaultImageUrl
            $imageUrl = $prod['defaultImageUrl']
                     ?? $prod['imageUrl']
                     ?? null;
            if (!$imageUrl) {
                $images = $prod['thumbnails'] ?? $prod['images'] ?? $prod['imageUrls'] ?? [];
                if (!empty($images)) {
                    $first    = is_array($images[0]) ? ($images[0]['url'] ?? $images[0]['src'] ?? '') : $images[0];
                    if ($first) $imageUrl = $first;
                }
            }
            if ($imageUrl) {
                if (str_starts_with($imageUrl, '//')) $imageUrl = 'https:' . $imageUrl;
                // Upgrade Scene7 size token to 500px
                $imageUrl = preg_replace('/\bsr=\d+\b/', 'sr=50', $imageUrl);
            }

            // URL — confirmed __NEXT_DATA__ field: defaultProductUrl
            $productId  = $prod['productId'] ?? $prod['styleId'] ?? '';
            $productUrl = $prod['defaultProductUrl']
                       ?? $prod['productUrl']
                       ?? null;
            if ($productUrl && !str_starts_with($productUrl, 'http')) {
                $productUrl = 'https://www.6pm.com' . $productUrl;
            }
            if (!$productUrl && $productId) {
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim("{$brand} {$name}")));
                $productUrl = "https://www.6pm.com/product/{$productId}/{$slug}";
            }
            if (!$productUrl) continue;

            // Rating
            $rating      = isset($prod['reviewAvgRating'])  ? (float)$prod['reviewAvgRating']  : null;
            $reviewCount = (int)($prod['reviewCount'] ?? $prod['numReviews'] ?? 0);

            if ($this->saveDeal([
                'title'          => $title,
                'original_price' => $original,
                'sale_price'     => $sale,
                'discount_pct'   => $pct,
                'image_url'      => $imageUrl,
                'product_url'    => $productUrl,
                'affiliate_url'  => $productUrl,
                'category'       => $this->mapCategory($title),
                'rating'         => $rating,
                'review_count'   => $reviewCount,
            ])) {
                $saved++;
            }
        }
        return $saved;
    }

    // ── HTML product tile fallback ────────────────────────────────────────────
    private function parseHtmlTiles(string $html): int
    {
        $saved = 0;
        $xpath = $this->loadDom($html);
        if (!$xpath) return 0;

        // 6pm product tiles: article[data-product-id] or div.product-result
        $tiles = $xpath->query(
            '//article[@data-product-id] | //div[contains(@class,"product-result")] | //div[@data-style-id]'
        );
        if (!$tiles || $tiles->length === 0) {
            $this->say("  No product tile elements found in HTML");
            return 0;
        }
        $this->say("  Found {$tiles->length} HTML product tiles");

        foreach ($tiles as $tile) {
            if (!($tile instanceof \DOMElement)) continue;

            // Title
            $titleNode = $xpath->query(
                './/span[contains(@class,"product-name")] | .//p[contains(@class,"productName")] | .//a[@title]',
                $tile
            )->item(0);
            $title = '';
            if ($titleNode instanceof \DOMElement && $titleNode->hasAttribute('title')) {
                $title = $titleNode->getAttribute('title');
            } elseif ($titleNode) {
                $title = trim($titleNode->textContent);
            }
            if (!$title) continue;

            // Sale price
            $salePriceNode = $xpath->query(
                './/*[contains(@class,"sale") and contains(text(),"$")] | .//*[contains(@class,"currentPrice")]',
                $tile
            )->item(0);
            $sale = $salePriceNode ? $this->parsePrice($salePriceNode->textContent) : 0.0;
            if ($sale <= 0) continue;

            // Original price
            $origNode = $xpath->query(
                './/*[contains(@class,"original") or contains(@class,"retail") or contains(@class,"msrp")]',
                $tile
            )->item(0);
            $original = $origNode ? $this->parsePrice($origNode->textContent) : 0.0;
            if ($original <= 0 || $original <= $sale) continue;

            $pct = $this->calcDiscount($original, $sale);
            if ($pct < 50) continue;

            $linkNode   = $xpath->query('.//a[@href]', $tile)->item(0);
            $href       = ($linkNode instanceof \DOMElement) ? $linkNode->getAttribute('href') : '';
            if (!$href) continue;
            $productUrl = str_starts_with($href, 'http') ? $href : 'https://www.6pm.com' . $href;

            $imgNode  = $xpath->query('.//img[@src]', $tile)->item(0);
            $imageUrl = ($imgNode instanceof \DOMElement) ? $imgNode->getAttribute('src') : null;
            if ($imageUrl && str_starts_with($imageUrl, '//')) $imageUrl = 'https:' . $imageUrl;

            if ($this->saveDeal([
                'title'          => $title,
                'original_price' => $original,
                'sale_price'     => $sale,
                'discount_pct'   => $pct,
                'image_url'      => $imageUrl,
                'product_url'    => $productUrl,
                'affiliate_url'  => $productUrl,
                'category'       => $this->mapCategory($title),
                'rating'         => null,
                'review_count'   => 0,
            ])) {
                $saved++;
            }
        }
        return $saved;
    }
}
