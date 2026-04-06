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
    // 6pm URL format: /c/{category} with query param s=PercentOff/desc
    // Note: /c/sale returns 404 — use category-specific pages with sale flag
    private const PAGES = [
        'https://www.6pm.com/c/shoes?s=PercentOff/desc&pge=0',
        'https://www.6pm.com/c/shoes?s=PercentOff/desc&pge=1',
        'https://www.6pm.com/c/clothing?s=PercentOff/desc&pge=0',
        'https://www.6pm.com/c/clothing?s=PercentOff/desc&pge=1',
        'https://www.6pm.com/c/handbags?s=PercentOff/desc&pge=0',
        'https://www.6pm.com/c/sandals?s=PercentOff/desc&pge=0',
        'https://www.6pm.com/c/sneakers?s=PercentOff/desc&pge=0',
        'https://www.6pm.com/c/boots?s=PercentOff/desc&pge=0',
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

            // Try __NEXT_DATA__ first (confirmed server-rendered on 6pm)
            $n = $this->parseNextData($html);

            // Fall back to __INITIAL_STATE__ / __APP_STATE__
            if ($n === 0) $n = $this->parseInitialState($html);

            // Fall back to any embedded JSON product arrays
            if ($n === 0) $n = $this->parseEmbeddedJson($html);

            // Fall back to HTML product tiles
            if ($n === 0) $n = $this->parseHtmlTiles($html);

            $savedTotal += $n;
            $this->say("  Saved {$n} deals from this page");
        }

        $this->say("══ Done: {$savedTotal} deals saved ══");
        $this->logResult($savedTotal > 0 ? 'success' : 'warning', "6pm.com 50%+ (saved: {$savedTotal})");
    }

    // ── Primary: extract "products":[...] array from __INITIAL_STATE__ ───────
    // 6pm embeds products as: ..."products":[{"brandName":"...","price":"$xx","originalPrice":"$xx","percentOff":"xx%",...}]
    private function parseInitialState(string $html): int
    {
        // Find the products array using balanced-brace extraction (state JSON too large to parse whole)
        $needle = '"products":';
        $pos = strpos($html, $needle);
        if ($pos === false) { $this->say("  No products key in page"); return 0; }

        $arrStart = strpos($html, '[', $pos + strlen($needle));
        if ($arrStart === false) return 0;

        // Balance brackets to extract the full array
        $depth = 0; $i = $arrStart; $len = strlen($html);
        while ($i < $len) {
            $c = $html[$i];
            if ($c === '[' || $c === '{') $depth++;
            elseif ($c === ']' || $c === '}') { $depth--; if ($depth === 0) break; }
            $i++;
        }
        $json = substr($html, $arrStart, $i - $arrStart + 1);
        $products = json_decode($json, true);

        if (!$products || json_last_error() !== JSON_ERROR_NONE || !isset($products[0]['productId'])) {
            $this->say("  Could not extract products array");
            return 0;
        }

        $this->say("  Found " . count($products) . " products in __INITIAL_STATE__");
        return $this->processProducts($products);
    }

    // ── Fallback: __NEXT_DATA__ ───────────────────────────────────────────────
    // NOTE: 6pm __NEXT_DATA__ confirmed path: props.pageProps.initialData.products[]
    // Prices are in CENTS (integer) — must divide by 100
    private function parseNextData(string $html): int
    {
        if (!preg_match('/<script[^>]+id=["\']__NEXT_DATA__["\'][^>]*>(.+?)<\/script>/s', $html, $m)) {
            return 0;
        }
        $data = json_decode($m[1], true);
        if (!$data || json_last_error() !== JSON_ERROR_NONE) return 0;

        $pp = $data['props']['pageProps'] ?? [];

        $products = $pp['initialData']['products']          // confirmed primary
                 ?? $pp['initialData']['results']
                 ?? $pp['products']
                 ?? $pp['searchResult']['list']
                 ?? $pp['results']
                 ?? $pp['initialState']['products']['list']
                 ?? [];

        // Deep search: if still empty, look for any array with productId keys
        if (empty($products)) {
            array_walk_recursive($pp, function($val, $key) use (&$products) {
                if ($key === 'products' && is_array($val) && !empty($val) && isset($val[0]['productId'])) {
                    $products = $val;
                }
            });
        }

        if (empty($products)) {
            $this->say("  __NEXT_DATA__ present but no products found");
            return 0;
        }
        $this->say("  Found " . count($products) . " products in __NEXT_DATA__");

        // Flag that prices need cents→dollars conversion
        return $this->processProducts($products, true);
    }

    // ── Scan for any JSON product array in page scripts ───────────────────────
    private function parseEmbeddedJson(string $html): int
    {
        // Look for patterns like "productId":"xxx","productName":"..."
        // and extract the surrounding JSON object
        if (!preg_match_all('/"productId"\s*:\s*"[^"]{5,}"/', $html, $hits)) return 0;
        $this->say("  Found " . count($hits[0]) . " productId hits — trying JSON extraction");

        // Try to extract a JSON array containing these products
        foreach ([
            '/"products"\s*:\s*(\[.+?\])\s*[,}]/s',
            '/"results"\s*:\s*(\[.+?\])\s*[,}]/s',
            '/"items"\s*:\s*(\[.+?\])\s*[,}]/s',
        ] as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $arr = json_decode($m[1], true);
                if ($arr && json_last_error() === JSON_ERROR_NONE && count($arr) > 0) {
                    $this->say("  Extracted " . count($arr) . " items from embedded JSON array");
                    // Try both raw and cents mode
                    $saved = $this->processProducts($arr, false);
                    if ($saved === 0) $saved = $this->processProducts($arr, true);
                    return $saved;
                }
            }
        }
        return 0;
    }

    // ── Process product list (either source) ──────────────────────────────────
    // $centsMode: true when prices are integers in cents (from __NEXT_DATA__)
    private array $seenProductIds = [];

    private function processProducts(array $products, bool $centsMode = false): int
    {
        $saved = 0;
        foreach ($products as $prod) {
            if (!is_array($prod)) continue;

            // Deduplicate: skip if we already processed this productId
            // (same shoe in 10 colors/sizes = 10 entries with same productId)
            $pid = $prod['productId'] ?? $prod['styleId'] ?? null;
            if ($pid && isset($this->seenProductIds[$pid])) continue;
            if ($pid) $this->seenProductIds[$pid] = true;

            // Title: brand + name
            // __NEXT_DATA__ confirmed fields: productName, brand (object with name)
            $brand = $prod['brandName']
                  ?? (is_array($prod['brand'] ?? null) ? ($prod['brand']['name'] ?? '') : ($prod['brand'] ?? ''))
                  ?? '';
            $name  = $prod['productName'] ?? $prod['name'] ?? $prod['styleName'] ?? '';
            $title = trim($brand ? "{$brand} {$name}" : $name);
            if (!$title) continue;

            // Pricing — two formats:
            // 1. String prices: price="$43.99", originalPrice="$275.00", percentOff="84%"  (__INITIAL_STATE__)
            // 2. Integer cents: price=4399, msrp=27500  (__NEXT_DATA__)
            $saleRaw     = $prod['price']           ?? $prod['salePrice']       ?? $prod['minPrice']  ?? 0;
            $originalRaw = $prod['msrp']            ?? $prod['originalPrice']   ?? $prod['retailPrice']?? $prod['maxPrice']  ?? 0;
            $pctRaw      = $prod['percentOff']      ?? $prod['discountPercent'] ?? 0;

            // Strip $ and % from string formats
            $sale     = is_string($saleRaw)     ? $this->parsePrice((string)$saleRaw)     : (float)$saleRaw;
            $original = is_string($originalRaw) ? $this->parsePrice((string)$originalRaw) : (float)$originalRaw;
            $pct      = is_string($pctRaw)       ? (int)preg_replace('/[^0-9]/', '', $pctRaw) : (int)$pctRaw;

            // If still looks like cents (integer > 1000 with no decimal used), convert
            if ($centsMode && $sale > 100 && fmod($sale, 1.0) === 0.0) {
                $sale     /= 100.0;
                $original /= 100.0;
            }

            if ($sale <= 0) continue;
            if ($original <= 0 && $pct > 0) $original = round($sale / (1 - $pct / 100), 2);
            if ($original > 0 && $sale < $original) $pct = max($pct, $this->calcDiscount($original, $sale));
            if ($pct < 50 || $original <= 0 || $original <= $sale) continue;

            // Image — __INITIAL_STATE__ uses msaImageId (Amazon CDN image ID)
            //         __NEXT_DATA__ uses defaultImageUrl
            $imageUrl = $prod['defaultImageUrl'] ?? $prod['imageUrl'] ?? null;
            if (!$imageUrl && !empty($prod['msaImageId'])) {
                $imageUrl = 'https://m.media-amazon.com/images/I/' . $prod['msaImageId'] . '._AC_SL500_.jpg';
            }
            if (!$imageUrl) {
                $images = $prod['thumbnails'] ?? $prod['images'] ?? $prod['imageUrls'] ?? [];
                if (!empty($images)) {
                    $first = is_array($images[0]) ? ($images[0]['url'] ?? $images[0]['src'] ?? '') : $images[0];
                    if ($first) $imageUrl = $first;
                }
            }
            if ($imageUrl && str_starts_with($imageUrl, '//')) $imageUrl = 'https:' . $imageUrl;

            // URL — confirmed __NEXT_DATA__ field: defaultProductUrl
            $productId  = $prod['productId'] ?? $prod['styleId'] ?? '';
            $productUrl = $prod['defaultProductUrl']
                       ?? $prod['productUrl']
                       ?? null;
            // Fix protocol-relative URLs (//www.6pm.com/...) and relative paths (/p/...)
            if ($productUrl && str_starts_with($productUrl, '//')) {
                $productUrl = 'https:' . $productUrl;
            } elseif ($productUrl && !str_starts_with($productUrl, 'http')) {
                $productUrl = 'https://www.6pm.com' . $productUrl;
            }
            if (!$productUrl && $productId) {
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim("{$brand} {$name}")));
                $productUrl = "https://www.6pm.com/p/{$slug}/product/{$productId}";
            }
            if (!$productUrl) continue;
            // Strip /color/NNN suffix so all color variants dedup to one canonical URL
            $productUrl = preg_replace('#/color/\d+#', '', $productUrl);
            // Strip all tracking query params
            $productUrl = preg_replace('/\?.*$/', '', $productUrl);

            // Rating
            $rating      = isset($prod['reviewAvgRating'])  ? (float)$prod['reviewAvgRating']  : null;
            $reviewCount = (int)($prod['reviewCount'] ?? $prod['numReviews'] ?? 0);

            $productType = $prod['productTypeFacet'] ?? $prod['productType'] ?? '';
            if ($this->saveDeal([
                'title'          => $title,
                'original_price' => $original,
                'sale_price'     => $sale,
                'discount_pct'   => $pct,
                'image_url'      => $imageUrl,
                'product_url'    => $productUrl,
                'affiliate_url'  => $productUrl,
                'category'       => $this->mapFashionCategory($productType, $title),
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
