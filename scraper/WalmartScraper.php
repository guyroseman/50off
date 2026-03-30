<?php
/**
 * WalmartScraper.php
 *
 * HOW THIS WORKS:
 * ────────────────
 * Walmart's Next.js frontend uses a BFF (Backend For Frontend) pattern.
 * When the browser makes XHR requests to search/browse URLs with
 * `Accept: application/json`, the server returns structured JSON
 * (not the full HTML page). This JSON contains product data including
 * prices, images, and discount info — without the bot-detection layer
 * that strips __NEXT_DATA__ from HTML responses.
 *
 * We use this JSON API directly, targeting:
 *   walmart.com/search?q=...&facet=special_offers%3ARollback
 *
 * Response path:
 *   data.items[].item  → product details (name, prices, images)
 * or:
 *   items[].item       → same, depending on endpoint version
 *
 * FILTER: Only save deals with 50%+ off.
 */
require_once __DIR__ . '/BaseScraper.php';

class WalmartScraper extends BaseScraper {
    protected string $store = 'walmart';

    // Walmart deals pages to scrape via HTML (with session cookies)
    private array $dealPages = [
        'https://www.walmart.com/shop/deals/flash-deals',
        'https://www.walmart.com/browse/rollback?cat_id=0&facet=special_offers%3ARollback',
        'https://www.walmart.com/browse/electronics?facet=special_offers%3ARollback',
        'https://www.walmart.com/browse/home-furniture-bedding/home-garden?facet=special_offers%3ARollback',
        'https://www.walmart.com/browse/clothing?facet=special_offers%3ARollback',
        'https://www.walmart.com/browse/toys?facet=special_offers%3ARollback',
    ];

    // Search keywords for JSON API fallback
    private array $searchQueries = [
        ['q' => 'electronics',   'facet' => 'special_offers:Rollback'],
        ['q' => 'home',          'facet' => 'special_offers:Rollback'],
        ['q' => 'clothing',      'facet' => 'special_offers:Rollback'],
        ['q' => 'toys',          'facet' => 'special_offers:Rollback'],
        ['q' => 'clearance',     'facet' => 'special_offers:Clearance'],
    ];

    // Cookie jar — shared across requests so session cookies carry over
    private string $cookieJar = '';

    public function scrape(): void {
        $this->say("=== Walmart Scraper (JSON API Method) ===");

        // Initialize session: visit homepage to collect cookies before API calls.
        // Walmart returns HTTP 412 if session cookies are missing.
        $this->cookieJar = tempnam(sys_get_temp_dir(), '50off_wmt_');
        $this->say("Initializing Walmart session...");
        $this->initWalmartSession();

        $totalCount = 0;

        // Method 1: Scrape HTML deals pages with session cookies
        // The homepage fetch sets cookies that allow accessing the deals pages.
        $this->say("Method 1: HTML deals pages...");
        foreach ($this->dealPages as $url) {
            $label = str_replace('https://www.walmart.com', '', $url);
            $this->say("Fetching: $label");

            $html = $this->fetchWalmartHtml($url);
            if (!$html) { $this->say("  → No response"); sleep(3); continue; }

            // Try to parse __NEXT_DATA__ from the HTML
            $items = $this->parseHtmlFallback($html);
            if ($items === false || empty($items)) {
                $this->say("  → No product data in HTML");
                sleep(2);
                continue;
            }

            $count = 0;
            foreach ($items as $raw) {
                if ($this->processWalmartItem($raw)) $count++;
            }
            $this->say("  → {$count} deals saved (from " . count($items) . " items)");
            $totalCount += $count;
            sleep(rand(3, 5));
        }

        // Method 2: JSON API as fallback
        $this->say("Method 2: JSON search API...");
        foreach ($this->searchQueries as $query) {
            $this->say("Querying: q={$query['q']}");

            $items = $this->fetchWalmartJson($query['q'], $query['facet']);
            if ($items === false) {
                $this->say("  → Failed to get JSON response");
                sleep(3);
                continue;
            }

            $count = 0;
            foreach ($items as $raw) {
                if ($this->processWalmartItem($raw)) $count++;
            }
            $this->say("  → {$count} deals saved (from " . count($items) . " items)");
            $totalCount += $count;
            sleep(rand(2, 4));
        }

        $this->say("Total Walmart deals saved: {$totalCount}");
        $this->logResult('success', "Walmart (saved: {$totalCount})");
    }

    // ── Initialize Walmart session by visiting homepage ───────────────────────
    // Walmart's 412 "Precondition Failed" is triggered when session cookies are
    // absent. A real browser always visits the site first, so we do the same.
    private function initWalmartSession(): void {
        $ch = curl_init('https://www.walmart.com/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            CURLOPT_COOKIEJAR      => $this->cookieJar,
            CURLOPT_COOKIEFILE     => $this->cookieJar,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate, br',
                'sec-ch-ua: "Google Chrome";v="125"',
                'sec-ch-ua-mobile: ?0',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Upgrade-Insecure-Requests: 1',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $this->say("  Homepage: HTTP $code (" . number_format(strlen((string)$body)) . " bytes)");
        sleep(2); // brief pause after homepage — mimic real user behaviour
    }

    // ── Fetch Walmart HTML page with session cookies ──────────────────────────
    // Uses cookies set by initWalmartSession() to appear as a returning browser user.
    private function fetchWalmartHtml(string $url): string|false {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            CURLOPT_COOKIEJAR      => $this->cookieJar,
            CURLOPT_COOKIEFILE     => $this->cookieJar,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate, br',
                'Referer: https://www.walmart.com/',
                'sec-ch-ua: "Google Chrome";v="125", "Chromium";v="125"',
                'sec-ch-ua-mobile: ?0',
                'sec-ch-ua-platform: "Windows"',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: same-origin',
                'Upgrade-Insecure-Requests: 1',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err)       { $this->say("  cURL: $err"); return false; }
        if ($code >= 400) { $this->say("  HTTP $code"); return false; }
        return $body ?: false;
    }

    // ── Fetch Walmart's internal JSON search API ──────────────────────────────
    // When called with Accept: application/json, Walmart's Next.js server returns
    // a JSON payload instead of an HTML page. This bypasses the __NEXT_DATA__
    // stripping that affects HTML-based scraping from datacenter IPs.
    private function fetchWalmartJson(string $keyword, string $facet = ''): array|false {
        $params = [
            'q'         => $keyword,
            'sortBy'    => 'Best_Seller',
            'numItems'  => 40,
            'page'      => 1,
        ];
        if ($facet) $params['facet'] = $facet;

        $url = 'https://www.walmart.com/search?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            CURLOPT_COOKIEJAR      => $this->cookieJar,
            CURLOPT_COOKIEFILE     => $this->cookieJar,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json, text/plain, */*',
                'Accept-Language: en-US,en;q=0.5',
                'Accept-Encoding: gzip, deflate, br',
                'Referer: https://www.walmart.com/',
                'X-Requested-With: XMLHttpRequest',
                'x-o-platform: rweb',
                'x-o-bu: WALMART-US',
                'x-o-gm-source: desktop',
                'wm_mp: true',
                'sec-ch-ua: "Chromium";v="124", "Google Chrome";v="124"',
                'sec-ch-ua-mobile: ?0',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: same-origin',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err)       { $this->say("  cURL: $err"); return false; }
        if ($code !== 200) { $this->say("  HTTP $code"); return false; }
        if (!$body)     { return false; }

        // If the response starts with HTML (<), we got the bot-challenge page
        $trimmed = ltrim($body);
        if (str_starts_with($trimmed, '<')) {
            $this->say("  → Got HTML (not JSON) — trying __NEXT_DATA__ parse");
            return $this->parseHtmlFallback($body);
        }

        $data = @json_decode($body, true);
        if (!$data) { $this->say("  → JSON decode failed"); return false; }

        // Extract items from various possible paths
        return $this->extractItemsFromJson($data);
    }

    // ── Extract items array from Walmart's JSON response ──────────────────────
    private function extractItemsFromJson(array $data): array {
        // Try multiple known paths in Walmart's JSON response
        $candidates = [
            $data['items']                                                          ?? [],
            $data['data']['items']                                                  ?? [],
            $data['searchResult']['itemStacks'][0]['items']                         ?? [],
            $data['props']['pageProps']['initialData']['searchResult']['itemStacks'][0]['items'] ?? [],
        ];

        // Also handle itemStacks array format
        if (!empty($data['searchResult']['itemStacks'])) {
            foreach ($data['searchResult']['itemStacks'] as $stack) {
                $candidates[] = $stack['items'] ?? [];
            }
        }
        if (!empty($data['data']['searchResult']['itemStacks'])) {
            foreach ($data['data']['searchResult']['itemStacks'] as $stack) {
                $candidates[] = $stack['items'] ?? [];
            }
        }

        foreach ($candidates as $list) {
            if (!empty($list) && is_array($list)) {
                return $list;
            }
        }
        return [];
    }

    // ── HTML fallback: parse __NEXT_DATA__ if we get HTML back ───────────────
    private function parseHtmlFallback(string $html): array|false {
        $pos = strpos($html, 'script id="__NEXT_DATA__"');
        if ($pos === false) return false;

        $jsonStart = strpos($html, '>', $pos) + 1;
        $jsonEnd   = strpos($html, '</script>', $jsonStart);
        $nextData  = @json_decode(substr($html, $jsonStart, $jsonEnd - $jsonStart), true);
        if (!$nextData) return false;

        $items = $this->extractItemsFromJson($nextData);
        return $items ?: false;
    }

    // ── Process a single Walmart product item ─────────────────────────────────
    private function processWalmartItem(array $raw): bool {
        // Walmart JSON API wraps items in an 'item' key
        $item = $raw['item'] ?? $raw;

        // Title
        $title = $item['name'] ?? $item['title'] ?? $raw['name'] ?? '';
        if (!$title || strlen($title) < 3) return false;

        // Item ID for URL
        $itemId = $item['usItemId'] ?? $item['itemId'] ?? $item['id']
               ?? $raw['usItemId'] ?? $raw['itemId']  ?? null;
        if (!$itemId) return false;

        // ── Price extraction ────────────────────────────────────────────────
        $priceInfo = $item['priceInfo'] ?? $raw['priceInfo'] ?? [];

        $sale    = (float)($item['price']           ?? $priceInfo['minPrice']   ?? $raw['price'] ?? 0);
        $origRaw = $priceInfo['wasPrice']            ?? $priceInfo['listPrice']  ?? '';
        $orig    = $origRaw ? $this->parsePrice((string)$origRaw) : 0.0;

        $savingsAmt = (float)($priceInfo['savingsAmt'] ?? $priceInfo['savings'] ?? 0);
        if ($orig <= 0 && $sale > 0 && $savingsAmt > 0) {
            $orig = round($sale + $savingsAmt, 2);
        }

        // Savings percentage from API
        $pct = (int)($priceInfo['savingsPercent'] ?? $priceInfo['percentOff'] ?? 0);

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
        $image    = null;
        $imgInfo  = $item['imageInfo'] ?? $item['image'] ?? $raw['imageInfo'] ?? null;
        if (is_array($imgInfo)) {
            $image = $imgInfo['thumbnailUrl'] ?? $imgInfo['url']
                  ?? ($imgInfo['allImages'][0]['url'] ?? null);
        } elseif (is_string($imgInfo)) {
            $image = $imgInfo;
        }

        // ── Rating ─────────────────────────────────────────────────────────
        $ratingInfo  = $item['ratingsReviews'] ?? $item['rating'] ?? [];
        $rating      = is_array($ratingInfo)
            ? (float)($ratingInfo['averageStarRating'] ?? $ratingInfo['rating'] ?? 0)
            : (float)$ratingInfo;
        $reviewCount = is_array($ratingInfo) ? (int)($ratingInfo['numberOfReviews'] ?? 0) : 0;

        // ── URL ────────────────────────────────────────────────────────────
        $slug       = strtolower(preg_replace('/[^a-z0-9]+/i', '-', substr($title, 0, 50)));
        $slug       = trim($slug, '-');
        $productUrl = "https://www.walmart.com/ip/{$slug}/{$itemId}";

        return $this->saveDeal([
            'title'          => trim($title),
            'description'    => isset($item['shortDescription'])
                                    ? strip_tags($item['shortDescription']) : null,
            'original_price' => $orig,
            'sale_price'     => $sale,
            'discount_pct'   => $pct,
            'image_url'      => $image,
            'product_url'    => $productUrl,
            'affiliate_url'  => $productUrl,
            'category'       => $this->mapCategory($title),
            'rating'         => $rating > 0 ? $rating : null,
            'review_count'   => $reviewCount,
        ]);
    }
}
