<?php
/**
 * HomeDepotScraper.php — homedepot.com special buys & clearance (50%+ off)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * HOW IT WORKS:
 * ─────────────
 * Home Depot's product listing pages embed product data in a JSON blob
 * assigned to window.__APOLLO_STATE__ or in structured script tags.
 * We target their Savings Center and Special Buy pages which concentrate
 * the highest discounts.
 *
 * Home Depot also exposes a public product search/browse API at:
 *   /b/N-{navParam}?format=json  (older v1 format)
 *   /federation-gateway/graphql  (modern GraphQL)
 * We try the JSON query param first, then fall back to HTML parsing.
 *
 * Bot protection: Imperva Incapsula on some endpoints. The browse pages
 * are less protected than search. We use polite delays + browser UA.
 *
 * AFFILIATE:
 * ──────────
 * Home Depot affiliate program via Impact Radius (impact.com).
 * Register at: https://www.homedepot.com/c/SF_BizConnect_Affiliates
 */
require_once __DIR__ . '/BaseScraper.php';

class HomeDepotScraper extends BaseScraper
{
    protected string $store = 'homedepot';
    private   int    $limit = 60;

    // Browse pages with consistent heavy discounts
    // format: [ label => url ]
    private const BROWSE_PAGES = [
        'Special Buy'   => 'https://www.homedepot.com/b/Special-Values/N-5yc1vZbm7a',
        'Clearance'     => 'https://www.homedepot.com/b/Clearance/N-5yc1vZ1z175b8',
        'Savings Center'=> 'https://www.homedepot.com/c/savings_center',
    ];

    // Home Depot Search API — returns JSON directly when ?format=json
    // navParam values for high-discount categories
    private const API_ENDPOINTS = [
        'Clearance Tools'     => 'https://www.homedepot.com/b/Tools/N-5yc1vZc1xy?Nrpp=24&sortorder=desc&sortby=TOP_SELLERS&format=json',
        'Clearance Appliances'=> 'https://www.homedepot.com/b/Appliances/N-5yc1vZbpgo?Nrpp=24&sortorder=desc&sortby=TOP_SELLERS&format=json',
        'Special Buy'         => 'https://www.homedepot.com/b/Special-Values/N-5yc1vZbm7a?Nrpp=48&sortorder=desc&sortby=PRICE_HIGH_TO_LOW&format=json',
    ];

    public function scrape(): void
    {
        $this->say('=== Home Depot Scraper — homedepot.com (50%+ off) ===');
        $savedTotal = 0;

        // ── Try JSON API endpoints first ──────────────────────────────────────
        foreach (self::API_ENDPOINTS as $label => $apiUrl) {
            if ($savedTotal >= $this->limit) break;
            $this->say("→ API: {$label}...");
            sleep(rand(2, 4));

            $body = $this->fetch($apiUrl, [
                'Accept: application/json, text/plain, */*',
                'X-Requested-With: XMLHttpRequest',
            ], 'https://www.homedepot.com/');

            if (!$body) { $this->say("  ✗ No response"); continue; }
            if (strlen($body) < 1000 || stripos($body, 'incapsula') !== false) {
                $this->say("  ✗ Blocked. Skipping."); continue;
            }

            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->say("  Not JSON — trying HTML fallback");
                $savedTotal += $this->parseHtml($body, $label);
                continue;
            }

            $n = $this->processApiData($data);
            $this->say("  ✓ {$n} deals from {$label}");
            $savedTotal += $n;
        }

        // ── HTML browse pages fallback ────────────────────────────────────────
        if ($savedTotal < 20) {
            foreach (self::BROWSE_PAGES as $label => $url) {
                if ($savedTotal >= $this->limit) break;
                $this->say("→ HTML: {$label}...");
                sleep(rand(3, 6));

                $html = $this->fetch($url, [
                    'Accept: text/html,application/xhtml+xml,*/*;q=0.8',
                    'Upgrade-Insecure-Requests: 1',
                ], 'https://www.google.com/');

                if (!$html || strlen($html) < 5000 || stripos($html, 'incapsula') !== false) {
                    $this->say("  ✗ Blocked or empty."); continue;
                }

                $n = $this->parseHtml($html, $label);
                $this->say("  ✓ {$n} deals from {$label}");
                $savedTotal += $n;
            }
        }

        $this->say("══ Done: {$savedTotal} deals saved ══");
        $this->logResult($savedTotal > 0 ? 'success' : 'warning', "homedepot.com 50%+ (saved: {$savedTotal})");
    }

    // ── Process JSON API response ─────────────────────────────────────────────
    private function processApiData(array $data): int
    {
        // Multiple possible JSON structures Home Depot has used over time
        $items = $data['searchModel']['products']
              ?? $data['products']
              ?? $data['productSearchModel']['products']
              ?? $data['items']
              ?? [];

        if (empty($items)) {
            // Try nested path
            foreach (['searchModel', 'productSearchModel', 'data'] as $k) {
                $sub = $data[$k] ?? null;
                if ($sub && isset($sub['products'])) { $items = $sub['products']; break; }
            }
        }

        if (empty($items)) { $this->say("  No products in API response"); return 0; }
        $this->say("  Found " . count($items) . " products in API JSON");

        $saved = 0;
        foreach ($items as $item) {
            $result = $this->processApiItem($item);
            if ($result) $saved++;
        }
        return $saved;
    }

    private function processApiItem(array $item): bool
    {
        // Title
        $title = $item['description'] ?? $item['productLabel'] ?? $item['name'] ?? null;
        if (!$title) return false;
        $title = trim(html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8'));

        // Pricing — Home Depot has several price node structures
        $pricing  = $item['pricing'] ?? $item['price'] ?? [];
        $sale     = (float)($pricing['value']          ?? $pricing['specialPrice']  ?? $pricing['salePrice']    ?? $item['price'] ?? 0);
        $original = (float)($pricing['original']       ?? $pricing['wasPrice']      ?? $pricing['regularPrice'] ?? $pricing['listPrice'] ?? 0);
        $pct      = (int)  ($pricing['percentageOff']  ?? $pricing['savingsPercent']?? 0);

        if ($sale <= 0) return false;
        if ($original <= 0 && $pct > 0) $original = round($sale / (1 - $pct / 100), 2);
        if ($original > 0 && $sale < $original) $pct = max($pct, $this->calcDiscount($original, $sale));
        if ($pct < 50 || $original <= 0 || $original <= $sale) return false;

        // Image
        $imageUrl = $item['media']['images'][0]['url']
                 ?? $item['image'] ?? $item['imageUrl'] ?? null;
        if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
            $imageUrl = 'https://images.thdstatic.com/' . ltrim($imageUrl, '/');
        }

        // URL
        $sku        = $item['itemId'] ?? $item['id'] ?? $item['productId'] ?? '';
        $urlKey     = $item['identifiers']['productLabel'] ?? $item['brandName'] ?? '';
        $slug       = $urlKey ? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $urlKey)) : 'product';
        $productUrl = $sku
            ? "https://www.homedepot.com/p/{$slug}/{$sku}"
            : ($item['canonicalUrl'] ?? null);
        if (!$productUrl) return false;
        if (!str_starts_with($productUrl, 'http')) $productUrl = 'https://www.homedepot.com' . $productUrl;

        // Rating
        $rating      = isset($item['reviews']['ratingsReviews']['averageRating'])
                       ? (float)$item['reviews']['ratingsReviews']['averageRating'] : null;
        $reviewCount = (int)($item['reviews']['ratingsReviews']['totalReviews'] ?? 0);

        return $this->saveDeal([
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
        ]);
    }

    // ── HTML parsing: product pods from browse pages ──────────────────────────
    private function parseHtml(string $html, string $pageLabel): int
    {
        $saved = 0;

        // First: try Apollo State / Next.js embedded JSON
        if (preg_match('/window\.__APOLLO_STATE__\s*=\s*(\{.+?\});\s*(?:<\/script>|window\.)/s', $html, $m)) {
            $apollo = json_decode($m[1], true);
            if ($apollo && json_last_error() === JSON_ERROR_NONE) {
                $saved += $this->processApolloState($apollo);
                if ($saved > 0) return $saved;
            }
        }

        // Second: __NEXT_DATA__
        if (preg_match('/<script[^>]+id=["\']__NEXT_DATA__["\'][^>]*>(.+?)<\/script>/s', $html, $m)) {
            $next = json_decode($m[1], true);
            if ($next && json_last_error() === JSON_ERROR_NONE) {
                $products = $next['props']['pageProps']['searchModel']['products']
                         ?? $next['props']['pageProps']['products']
                         ?? [];
                if (!empty($products)) {
                    foreach ($products as $p) {
                        if ($this->processApiItem($p)) $saved++;
                    }
                    if ($saved > 0) return $saved;
                }
            }
        }

        // Third: DOM parsing of product pods
        $xpath = $this->loadDom($html);
        if (!$xpath) return 0;

        // Home Depot product pods have class "product-pod" or data-pod-id attributes
        $pods = $xpath->query(
            '//div[contains(@class,"product-pod") or contains(@class,"pod-plp") or @data-sku-number]'
        );
        if (!$pods || $pods->length === 0) {
            $this->say("  No product pod elements in HTML");
            return 0;
        }
        $this->say("  Found {$pods->length} product pods");

        foreach ($pods as $pod) {
            if (!($pod instanceof \DOMElement)) continue;

            // Title
            $titleNode = $xpath->query(
                './/span[contains(@class,"product-description")] | .//h2[contains(@class,"product-header")] | .//a[@title]',
                $pod
            )->item(0);
            $title = '';
            if ($titleNode instanceof \DOMElement && $titleNode->hasAttribute('title')) {
                $title = $titleNode->getAttribute('title');
            } elseif ($titleNode) {
                $title = trim($titleNode->textContent);
            }
            if (!$title) continue;

            // Was price
            $wasPriceNode = $xpath->query(
                './/*[contains(@class,"was-price") or contains(@class,"original-price") or contains(text(),"Was")]',
                $pod
            )->item(0);
            $original = $wasPriceNode ? $this->parsePrice($wasPriceNode->textContent) : 0.0;
            if ($original <= 0) continue;

            // Sale price
            $salePriceNode = $xpath->query(
                './/*[contains(@class,"price-format") and not(contains(@class,"was")) and not(contains(@class,"range"))]//span | .//*[@data-automation="sale-price"]',
                $pod
            )->item(0);
            $sale = $salePriceNode ? $this->parsePrice($salePriceNode->textContent) : 0.0;
            if ($sale <= 0 || $sale >= $original) continue;

            $pct = $this->calcDiscount($original, $sale);
            if ($pct < 50) continue;

            $linkNode   = $xpath->query('.//a[@href]', $pod)->item(0);
            $href       = ($linkNode instanceof \DOMElement) ? $linkNode->getAttribute('href') : '';
            if (!$href) continue;
            $productUrl = str_starts_with($href, 'http') ? $href : 'https://www.homedepot.com' . $href;

            $imgNode  = $xpath->query('.//img[@src]', $pod)->item(0);
            $imageUrl = ($imgNode instanceof \DOMElement) ? $imgNode->getAttribute('src') : null;

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

    private function processApolloState(array $state): int
    {
        $saved = 0;
        foreach ($state as $key => $node) {
            if (!str_starts_with($key, 'Product:') && !str_starts_with($key, 'SearchProduct:')) continue;
            if (!is_array($node)) continue;
            if ($this->processApiItem($node)) $saved++;
        }
        return $saved;
    }
}
