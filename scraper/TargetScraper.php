<?php
/**
 * TargetScraper.php
 *
 * Targets: https://www.target.com/c/top-deals/-/N-4xw74
 *
 * HOW TARGET'S PAGE WORKS:
 * ─────────────────────────
 * Target's website makes real-time API calls to their "RedSky" backend.
 * We can intercept these calls and use them directly.
 *
 * Target uses TWO data sources on their deals page:
 *
 * 1. __PRELOADED_STATE__ in <script> tags — contains initial product data
 *    Path: __PRELOADED_STATE__.plp.items.Item[]
 *
 * 2. RedSky API — Target's own public-facing product API
 *    Endpoint: https://redsky.target.com/redsky_aggregations/v1/web/plp_search_v2
 *    API Key: ff457966e64d5e877fdbad070f276d18ecec4a01 (embedded in Target's JS)
 *
 * Price structure in Target's data:
 *   item.price.reg_retail    = original price
 *   item.price.current_retail= sale price
 *   item.price.save_percent  = discount percentage
 *
 * Image URLs: https://target.scene7.com/is/image/Target/TCIN_number
 *
 * TCIN = Target's product ID (like ASIN for Amazon)
 */
require_once __DIR__ . '/BaseScraper.php';

class TargetScraper extends BaseScraper {
    protected string $store = 'target';

    // Target's public API key (extracted from their JS bundle — rotates periodically)
    private string $apiKey = '9f36aeafbe60771e321a7cc95a78140772ab3e96';

    // Store ID for pricing (1358 = Minneapolis HQ store, used for online prices)
    private int $storeId = 3991;

    // Target deal pages to scrape
    private array $dealPages = [
        'https://www.target.com/c/top-deals/-/N-4xw74',
    ];

    // Keywords for RedSky API search — "sale X" returns the most discounted items
    private array $apiCategories = [
        ['keyword' => 'sale electronics',  'cat' => 'electronics'],
        ['keyword' => 'sale clothing',     'cat' => 'clothing'],
        ['keyword' => 'sale home',         'cat' => 'home'],
        ['keyword' => 'clearance toys',    'cat' => 'toys'],
        ['keyword' => 'sale kitchen',      'cat' => 'kitchen'],
        ['keyword' => 'sale sports',       'cat' => 'sports'],
        ['keyword' => 'sale beauty',       'cat' => 'beauty'],
        ['keyword' => '50 percent off',    'cat' => 'other'],
        ['keyword' => 'half off',          'cat' => 'other'],
    ];

    public function scrape(): void {
        $this->say("=== Target Top Deals Scraper ===");
        $this->say("Target: target.com/c/top-deals/-/N-4xw74");

        // Method 1: Scrape HTML pages directly (gets __PRELOADED_STATE__ data)
        foreach ($this->dealPages as $url) {
            $this->say("Page: " . str_replace('https://www.target.com', '', $url));
            $html = $this->fetchTargetPage($url);
            if (!$html) { $this->say("  → No response"); sleep(3); continue; }

            $count = $this->parsePage($html);
            $this->say("  → $count deals from page HTML");
            sleep(rand(2, 4));
        }

        // Method 2: RedSky API (more reliable, structured JSON)
        $this->say("Querying Target RedSky API...");
        foreach ($this->apiCategories as $cat) {
            $count = $this->queryRedSkyApi($cat['keyword']);
            $this->say("  → {$count} deals for {$cat['cat']} ({$cat['keyword']})");
            sleep(rand(1, 3));
        }

        $this->logResult('success', "Target Top Deals");
    }

    // ── Fetch with Target-specific headers ────────────────────────────────────
    private function fetchTargetPage(string $url): string|false {
        return $this->fetch($url, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'sec-ch-ua: "Chromium";v="124", "Google Chrome";v="124"',
            'sec-fetch-dest: document',
            'sec-fetch-mode: navigate',
            'sec-fetch-site: none',
            'Cache-Control: no-cache',
        ], 'https://www.target.com/');
    }

    // ── Parse Target HTML page ─────────────────────────────────────────────────
    private function parsePage(string $html): int {
        $count = 0;

        // ── PRIMARY: Extract __TGT_DATA__ (current Target format, 2026+) ─
        if (preg_match('/__TGT_DATA__.*?value:\s*deepFreeze\(JSON\.parse\("(.*?)"\)\)/s', $html, $m)) {
            $json  = stripcslashes($m[1]);
            $state = @json_decode($json, true);
            if ($state) {
                $count += $this->processPreloadedState($state);
                if ($count > 0) return $count;
            }
        }

        // ── FALLBACK: Legacy __PRELOADED_STATE__ ─────────────────────────
        if (preg_match('/window\.__PRELOADED_STATE__\s*=\s*({[\s\S]+?})\s*;?\s*<\/script>/m', $html, $m)) {
            $state = @json_decode($m[1], true);
            if ($state) {
                $count += $this->processPreloadedState($state);
                if ($count > 0) return $count;
            }
        }

        return $count;
    }

    // ── Process Target __PRELOADED_STATE__ ────────────────────────────────────
    private function processPreloadedState(array $state): int {
        $count = 0;

        // Target's state paths for product listings
        $paths = [
            ['plp', 'items', 'Item'],
            ['search', 'products'],
            ['categoryPage', 'products'],
            ['shelf', 'products'],
        ];

        foreach ($paths as $path) {
            $node = $state;
            foreach ($path as $key) {
                $node = $node[$key] ?? null;
                if (!$node) break;
            }
            if (!$node || !is_array($node)) continue;

            $found = 0;
            foreach ($node as $item) {
                if ($this->processTargetProduct($item)) { $count++; $found++; }
            }
            if ($found > 0) break;
        }
        return $count;
    }

    // ── Process a single Target product ──────────────────────────────────────
    private function processTargetProduct(array $item): bool {
        // TCIN = Target's product ID
        $tcin = $item['tcin'] ?? $item['item']['tcin'] ?? null;
        if (!$tcin) return false;

        // Title
        $title = $item['item']['product_description']['title'] ??
                 $item['product_description']['title']         ??
                 $item['name']                                 ??
                 $item['title']                                ?? '';
        if (!$title || strlen($title) < 3) return false;

        // ── Price extraction ────────────────────────────────────────────────
        $priceInfo  = $item['price']      ?? $item['item']['price'] ?? [];
        $orig = (float)(
            $priceInfo['reg_retail']    ??
            $priceInfo['list_price']    ??
            $priceInfo['original']      ??
            0
        );
        $sale = (float)(
            $priceInfo['current_retail']??
            $priceInfo['sale_price']    ??
            $priceInfo['formatted_current_price'] ??
            $priceInfo['current']       ??
            0
        );
        $pct = (int)(
            $priceInfo['save_percent']  ??
            $priceInfo['percent_off']   ??
            0
        );

        if ($sale <= 0) return false;

        // Derive orig from pct
        if ($orig <= $sale && $pct >= 50 && $sale > 0) {
            $orig = round($sale / (1 - $pct / 100), 2);
        }
        // Calc pct
        if ($pct < 50 && $orig > $sale && $sale > 0) {
            $pct = $this->calcDiscount($orig, $sale);
        }

        if ($pct < 50 || $orig <= $sale) return false;

        // ── Image ──────────────────────────────────────────────────────────
        // Target images: https://target.scene7.com/is/image/Target/GUEST_TCIN
        $image = $item['item']['enrichment']['images']['primary_image_url']    ??
                 $item['enrichment']['images']['primary_image_url']            ??
                 $item['item']['primary_image_url']                            ??
                 $item['primary_image_url']                                    ??
                 // Construct from TCIN if not available
                 "https://target.scene7.com/is/image/Target/GUEST_{$tcin}?wid=500&hei=500";

        // ── Rating ─────────────────────────────────────────────────────────
        $ratingStats = $item['ratings_and_reviews']['statistics']   ??
                       $item['item']['ratings_and_reviews']         ?? [];
        $rating      = (float)($ratingStats['overall_average_rating'] ?? $ratingStats['average'] ?? 0);
        $reviews     = (int)($ratingStats['total_review_count']       ?? $ratingStats['count']   ?? 0);

        // ── Category ───────────────────────────────────────────────────────
        $catRaw = $item['item']['merchandise_type_name']                       ??
                  $item['item']['dept_name']                                   ?? '';
        if (!$catRaw && isset($item['category']) && is_string($item['category'])) {
            $catRaw = $item['category'];
        }

        // ── URL ────────────────────────────────────────────────────────────
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', substr($title, 0, 60)));
        $slug = trim($slug, '-');
        $productUrl  = "https://www.target.com/p/{$slug}/-/A-{$tcin}";
        $affiliateUrl= "https://www.target.com/p/{$slug}/-/A-{$tcin}?afid=50off";

        return $this->saveDeal([
            'title'          => trim($title),
            'description'    => $this->extractTargetDesc($item),
            'original_price' => $orig,
            'sale_price'     => $sale,
            'discount_pct'   => $pct,
            'image_url'      => $image,
            'product_url'    => $productUrl,
            'affiliate_url'  => $affiliateUrl,
            'category'       => $this->mapCategory($title . ' ' . $catRaw),
            'rating'         => $rating > 0 ? $rating : null,
            'review_count'   => $reviews,
        ]);
    }

    // ── RedSky API ────────────────────────────────────────────────────────────
    // Target's own public API — requires API-specific headers (not HTML Sec-Fetch-*)
    private function queryRedSkyApi(string $keyword): int {
        $params = http_build_query([
            'channel'                       => 'WEB',
            'count'                         => 24,
            'default_purchasability_filter' => 'true',
            'include_sponsored'             => 'false',
            'keyword'                       => $keyword,
            'offset'                        => 0,
            'page'                          => '/s/' . urlencode($keyword),
            'platform'                      => 'desktop',
            'pricing_store_id'              => $this->storeId,
            'store_id'                      => $this->storeId,
            'visitor_id'                    => 'anonymous',
            'key'                           => $this->apiKey,
        ]);

        $url = "https://redsky.target.com/redsky_aggregations/v1/web/plp_search_v2?{$params}";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Origin: https://www.target.com',
                'Referer: https://www.target.com/',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code !== 200 || !$body) {
            $this->say("RedSky HTTP $code for keyword: $keyword");
            return 0;
        }

        $data = @json_decode($body, true);
        if (!$data) return 0;

        $products = $data['data']['search']['products']                 ??
                    $data['data']['search']['product_summaries']       ??
                    $data['data']['plp_search']['product_summaries']   ?? [];

        $count = 0;
        foreach ($products as $product) {
            if ($this->processTargetProduct($product)) $count++;
        }
        return $count;
    }

    // ── Description helper ────────────────────────────────────────────────────
    private function extractTargetDesc(array $item): ?string {
        $bullets = $item['item']['product_description']['bullet_description'] ??
                   $item['product_description']['bullet_description']         ?? [];
        if ($bullets) {
            $text = implode(' ', array_slice($bullets, 0, 2));
            return substr(strip_tags($text), 0, 500) ?: null;
        }
        $desc = $item['item']['product_description']['soft_bullets']['bullets'][0] ??
                $item['description'] ?? null;
        return $desc ? substr(strip_tags($desc), 0, 500) : null;
    }
}
