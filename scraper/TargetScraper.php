<?php
/**
 * TargetScraper.php — target.com deals (50%+ off) via RedSky API
 * ════════════════════════════════════════════════════════════════
 *
 * HOW IT WORKS:
 * ─────────────
 * Target's RedSky API (plp_search_v2) returns structured product data including
 * current price, regular price, and save_percent for each item.
 * We browse by category with sortBy=DiscountHigh to surface 50%+ deals first.
 *
 * The API requires a `page` param (category path) and a valid `key` extracted
 * from the Target website. Works from Hostinger when both are provided.
 *
 * AFFILIATE:
 * ──────────
 * Target's affiliate program is through Impact (formerly Commission Junction).
 * Until affiliate approval, product URLs are plain target.com links.
 * Register at: https://partners.target.com/
 */
require_once __DIR__ . '/BaseScraper.php';

class TargetScraper extends BaseScraper
{
    protected string $store = 'target';
    private   int    $limit = 90;       // max deals per run

    private const API_KEY = '9f36aeafbe60771e321a7cc95a78140772ab3e96';
    private const API_BASE = 'https://redsky.target.com/redsky_aggregations/v1/web/plp_search_v2';

    // Category IDs + their page paths (required by API)
    // format: [ categoryId => [label, pagePath] ]
    private const CATEGORIES = [
        '5q0ga' => ['Clearance',       '/c/clearance/-/N-5q0ga'],
        '5xtm1' => ['Food & Beverage', '/c/food-beverages/-/N-5xtm1'],
        '5xtgz' => ['Toys',            '/c/toys/-/N-5xtgz'],
        '5xtk2' => ['Kitchen',         '/c/kitchen-dining/-/N-5xtk2'],
        '5xtg6' => ['Electronics',     '/c/electronics/-/N-5xtg6'],
        '5xtk9' => ['Furniture',       '/c/furniture/-/N-5xtk9'],
    ];

    public function scrape(): void
    {
        $this->say('=== Target Scraper — target.com (50%+ off) ===');

        $savedTotal = 0;

        foreach (self::CATEGORIES as $catId => [$catLabel, $catPage]) {
            if ($savedTotal >= $this->limit) break;

            $this->say("→ Category: {$catLabel} ({$catId})");
            $offset = 0;
            $pageSize = 24;

            do {
                $url = $this->buildApiUrl($catId, $catPage, $offset);
                $body = $this->fetch($url, $this->apiHeaders($catPage), 'https://www.target.com/');

                if (!$body) {
                    $this->say("  ✗ No response. Skipping category.");
                    break;
                }

                $data = json_decode($body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->say("  ✗ JSON decode failed.");
                    break;
                }

                $products = $data['data']['search']['products'] ?? [];
                $totalResults = (int)($data['data']['search']['search_response']['typed_metadata']['total_results'] ?? 0);

                if (empty($products)) {
                    $this->say("  No products returned.");
                    break;
                }

                $this->say("  ✓ Got " . count($products) . " products (offset={$offset}, total={$totalResults})");

                $batchSaved = $this->processProducts($products, $savedTotal);
                $savedTotal += $batchSaved;

                $offset += $pageSize;

                // Stop paginating if: no discount deals left, or reached limit, or got all results
                if ($batchSaved === 0 || $savedTotal >= $this->limit || $offset >= $totalResults) break;

                sleep(rand(1, 2));

            } while (count($products) >= $pageSize);

            $this->say("  Subtotal: {$savedTotal} deals saved so far");

            if ($savedTotal < $this->limit) {
                sleep(rand(1, 2));
            }
        }

        $this->say("══ Done: {$savedTotal} deals saved ══");
        $this->logResult('success', "target.com 50%+ (saved: {$savedTotal})");
    }

    private function buildApiUrl(string $categoryId, string $categoryPage, int $offset): string
    {
        return self::API_BASE . '?' . http_build_query([
            'channel'    => 'WEB',
            'count'      => 24,
            'default_purchasability_filter' => 'true',
            'include_sponsored' => 'false',
            'category'   => $categoryId,
            'page'       => $categoryPage,
            'offset'     => $offset,
            'sortBy'     => 'DiscountHigh',
            'key'        => self::API_KEY,
            'pricing_store_id' => '3991',
            'visitor_id' => '019D428271750200BEF0EA343724646B',
        ]);
    }

    private function apiHeaders(string $categoryPage): array
    {
        return [
            'Accept: application/json',
            'Accept-Language: en-US,en;q=0.9',
            'Origin: https://www.target.com',
            'Referer: https://www.target.com' . $categoryPage,
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-site',
        ];
    }

    private function processProducts(array $products, int $alreadySaved): int
    {
        $saved = 0;

        foreach ($products as $prod) {
            if (($alreadySaved + $saved) >= $this->limit) break;

            // Title
            $title = $prod['item']['product_description']['title'] ?? null;
            if (!$title) continue;
            $title = trim(html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8'));

            // TCIN (Target product ID)
            $tcin = $prod['tcin'] ?? null;
            if (!$tcin) continue;

            // Pricing
            $pricing = $this->extractPricing($prod['price'] ?? []);
            if (!$pricing) continue;

            // Image — comes as full URL directly from API
            $imageUrl = $prod['item']['enrichment']['images']['primary_image_url'] ?? null;
            if ($imageUrl) {
                $imageUrl = $this->upgradeImageSize($imageUrl);
            }

            // URL
            $urlSlug = $this->buildUrlSlug($title);
            $productUrl = "https://www.target.com/p/{$urlSlug}/-/A-{$tcin}";

            // Category
            $catRaw = $prod['item']['product_classification']['product_type_name'] ?? '';

            if ($this->saveDeal([
                'title'          => $title,
                'original_price' => $pricing['original'],
                'sale_price'     => $pricing['sale'],
                'discount_pct'   => $pricing['pct'],
                'image_url'      => $imageUrl,
                'product_url'    => $productUrl,
                'affiliate_url'  => $productUrl,
                'category'       => $this->mapCategory($catRaw ?: $title),
                'rating'         => isset($prod['ratings_and_reviews']['statistics']['rating']['average'])
                                     ? (float)$prod['ratings_and_reviews']['statistics']['rating']['average']
                                     : null,
                'review_count'   => (int)($prod['ratings_and_reviews']['statistics']['total_review_count'] ?? 0),
            ])) {
                $saved++;
            }
        }

        return $saved;
    }

    private function extractPricing(array $price): ?array
    {
        $sale     = (float)($price['current_retail'] ?? $price['formatted_current_price_value'] ?? 0);
        $original = (float)($price['reg_retail'] ?? $price['formatted_comparison_price_value'] ?? 0);
        $pct      = (int)($price['save_percent'] ?? 0);

        if ($sale <= 0) return null;

        // Reconstruct original if missing
        if ($original <= 0 && $pct > 0) {
            $original = round($sale / (1 - $pct / 100), 2);
        }

        // Recalculate pct if we have both prices
        if ($original > 0 && $sale < $original) {
            $calcPct = $this->calcDiscount($original, $sale);
            $pct = max($pct, $calcPct);
        }

        if ($pct < 50 || $original <= 0 || $original <= $sale) return null;

        return ['sale' => $sale, 'original' => $original, 'pct' => $pct];
    }

    private function upgradeImageSize(string $url): string
    {
        // Target Scene7 URLs support wid/hei params — request 500px
        if (str_contains($url, 'scene7.com')) {
            $base = strtok($url, '?');
            return $base . '?wid=500&hei=500&fmt=webp&qlt=80';
        }
        return $url;
    }

    private function buildUrlSlug(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/\s+/', '-', trim($slug));
        $slug = preg_replace('/-+/', '-', $slug);
        return substr($slug, 0, 100);
    }
}
