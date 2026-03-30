<?php
/**
 * AmazonScraper.php — amazon.com (US) 50%+ deals scraper
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * STRATEGY:
 * ─────────
 * 1. Fetch the React deals page HTML (prefiltered to 50-100% off).
 * 2. Extract the massive JSON blob injected via `assets.mountWidget(...)`.
 * 3. Parse `prefetchedData.entity.rankedPromotions` for the first batch.
 * 4. Paginate via Amazon's internal AAPI endpoint using `nextIndex` until
 * we hit $this->limit or run out of deals.
 * 5. For each deal: extract title, image, prices, discount %, affiliate URL,
 * then upsert into the deals table via BaseScraper::saveDeal().
 *
 * NOTE: Affiliate tag must be a US Amazon Associates tag (amazon.com).
 *       Register at: https://affiliate-program.amazon.com/
 */

require_once __DIR__ . '/BaseScraper.php';

class AmazonScraper extends BaseScraper
{
    protected string $store = 'amazon';
    private   string $tag   = '50off-20';   // US Amazon Associates tag (amazon.com)
    private   int    $limit = 100;          // max products per run

    /** Deals pages pre-filtered to 50–100 % off (US store) — multiple categories for broader scope */
    private string $startUrl =
        'https://www.amazon.com/deals/' .
        '?discounts-widget=%2522%257B%255C%2522state%255C%2522%253A%257B' .
        '%255C%2522rangeRefinementFilters%255C%2522%253A%257B%255C%2522percentOff' .
        '%255C%2522%253A%257B%255C%2522min%255C%2522%253A50%252C%255C%2522max' .
        '%255C%2522%253A100%257D%257D%257D%252C%255C%2522version%255C%2522%253A1%257D%2522';

    // Additional deal pages using different sort orders to get fresh deals
    // Amazon's category paths like /deals/browse-deals/electronics aren't real URLs
    // — they all return the same main deals page. Instead, we use different sort params.
    private array $categoryUrls = [
        // Sort by newest deals (different from default "best deals" sort)
        'https://www.amazon.com/deals/?discounts-widget=%2522%257B%255C%2522state%255C%2522%253A%257B%255C%2522rangeRefinementFilters%255C%2522%253A%257B%255C%2522percentOff%255C%2522%253A%257B%255C%2522min%255C%2522%253A50%252C%255C%2522max%255C%2522%253A100%257D%257D%257D%252C%255C%2522version%255C%2522%253A1%257D%2522&deals-widget=%7B%22version%22%3A1%2C%22state%22%3A%7B%22selectedSort%22%3A%22BY_SCORE%22%7D%7D',
        // Lightning deals specifically (time-limited, different pool of products)
        'https://www.amazon.com/deals/lightning-deals?discounts-widget=%2522%257B%255C%2522state%255C%2522%253A%257B%255C%2522rangeRefinementFilters%255C%2522%253A%257B%255C%2522percentOff%255C%2522%253A%257B%255C%2522min%255C%2522%253A50%252C%255C%2522max%255C%2522%253A100%257D%257D%257D%252C%255C%2522version%255C%2522%253A1%257D%2522',
    ];

    // ── Entry point ──────────────────────────────────────────────────────────

    public function scrape(): void
    {
        $this->say('=== Amazon Scraper (React/JSON Method) — amazon.com US ===');
        $this->say("Target:  {$this->startUrl}");
        $this->say("Limit:   {$this->limit} products\n");

        // ── Step 1: Fetch initial HTML page ──────────────────────────────────
        $this->say('→ Fetching deals page...');
        $html = $this->fetchAmazonPage($this->startUrl);

        if (!$html) {
            $this->say('✗ Failed to fetch deals page. Aborting.');
            $this->logResult('error', 'Could not fetch amazon.ca deals page');
            return;
        }
        $this->say('  ✓ Page fetched (' . number_format(strlen($html)) . ' bytes)');

        // ── Step 2: Extract embedded JSON from the mountWidget call ──────────
        $json = $this->extractMountWidgetJson($html);

        if (!$json) {
            $this->say('✗ Could not extract widget JSON from page. Aborting.');
            $this->logResult('error', 'JSON extraction failed');
            return;
        }
        $this->say('  ✓ Widget JSON extracted');

        // ── Step 3: Parse initial promotions batch ────────────────────────────
        $prefetch  = $json['prefetchedData']['entity'] ?? [];
        $promotions = $prefetch['rankedPromotions'] ?? [];
        $nextIndex  = $prefetch['nextIndex']         ?? 0;
        $pageSize   = 30; // Amazon returns 30 per page by default

        // Extract the actual resource URL from the widget JSON — Amazon embeds
        // the full pagination endpoint with correct filters/ranking context.
        $resourceUrl = $json['prefetchedData']['resource']['url']
                    ?? $json['resource']['url']
                    ?? null;

        $this->say('  Initial batch: ' . count($promotions) . ' promotions');
        $this->say('  nextIndex:     ' . $nextIndex);
        $this->say('  resourceUrl:   ' . ($resourceUrl ? 'found' : 'MISSING') . "\n");

        $savedCount = $this->processPromotions($promotions, 0);
        $totalSeen  = count($promotions);

        // ── Step 4: Paginate until limit or exhausted ─────────────────────────
        while ($savedCount < $this->limit && $nextIndex > 0 && count($promotions) >= $pageSize && $resourceUrl) {
            $this->say("→ Fetching page at startIndex={$nextIndex}...");
            sleep(rand(1, 2)); // polite delay

            // Reuse the resource URL from the JSON, only swapping startIndex
            $apiUrl  = $this->buildPaginationUrl($resourceUrl, $nextIndex);
            $apiResp = $this->fetchAmazonApi($apiUrl); // dedicated JSON fetch (no HTML headers)

            if (!$apiResp) {
                $this->say('  ✗ API request failed. Stopping pagination.');
                break;
            }

            $apiData    = json_decode($apiResp, true);
            $promotions = $apiData['entity']['rankedPromotions'] ?? [];
            $nextIndex  = $apiData['entity']['nextIndex']         ?? 0;

            if (empty($promotions)) {
                $this->say('  No more promotions. Done.');
                break;
            }

            $this->say('  ✓ Got ' . count($promotions) . ' promotions');
            $savedCount += $this->processPromotions($promotions, $savedCount);
            $totalSeen  += count($promotions);
        }

        $this->say('');
        $this->say("══ Main page done: seen={$totalSeen}, saved/updated={$savedCount} ══");

        // ── Step 5: Scrape additional category deal pages ─────────────────────
        // Each category page gives ~30 unique deals beyond the main page.
        foreach ($this->categoryUrls as $catUrl) {
            if ($savedCount >= $this->limit) break;

            $catLabel = basename(parse_url($catUrl, PHP_URL_PATH));
            $this->say("\n→ Category page: {$catLabel}...");
            sleep(rand(2, 4));

            $html = $this->fetchAmazonPage($catUrl);
            if (!$html) { $this->say("  ✗ Failed to fetch"); continue; }

            $json = $this->extractMountWidgetJson($html);
            if (!$json) { $this->say("  ✗ No widget JSON"); continue; }

            $promotions = $json['prefetchedData']['entity']['rankedPromotions'] ?? [];
            $this->say("  Got " . count($promotions) . " promotions");

            $batch = $this->processPromotions($promotions, $savedCount);
            $savedCount += $batch;
            $totalSeen  += count($promotions);
            $this->say("  Saved {$batch} from {$catLabel}");
        }

        $this->say("\n══ Final: seen={$totalSeen}, saved/updated={$savedCount} ══");
        $this->logResult('success', "amazon.com US 50%+ deals (saved: {$savedCount})");
    }

    // ── Core extraction ──────────────────────────────────────────────────────

    /**
     * Extract the JSON object passed to assets.mountWidget(...) from the page HTML.
     * Amazon injects: P.when('DiscountsWidgetsHorizonteAssets').execute(function(assets){
     * assets.mountWidget('slot-14', { ...GIANT JSON... })
     * });
     *
     * We grab everything between the first `{` after `assets.mountWidget('slot-14',`
     * and the matching closing `}`.
     */
    private function extractMountWidgetJson(string $html): array|false
    {
        // Find ALL mountWidget calls and pick the one with rankedPromotions
        preg_match_all("/assets\.mountWidget\('([^']+)',/", $html, $slotMatches);
        $slots = $slotMatches[1] ?? [];

        if (empty($slots)) {
            $this->say('  ✗ No mountWidget calls found in HTML');
            return false;
        }

        $this->say('  Found slots: ' . implode(', ', $slots));

        foreach ($slots as $slot) {
            $marker = "assets.mountWidget('{$slot}',";
            $pos    = strpos($html, $marker);
            if ($pos === false) continue;

            $start = strpos($html, '{', $pos + strlen($marker));
            if ($start === false) continue;

            $jsonStr = $this->extractBalancedJson($html, $start);
            if (!$jsonStr) continue;

            $decoded = json_decode($jsonStr, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $jsonStr);
                $decoded = json_decode($cleaned, true);
                if (json_last_error() !== JSON_ERROR_NONE) continue;
            }

            // Check if this slot has rankedPromotions
            $promotions = $decoded['prefetchedData']['entity']['rankedPromotions'] ?? null;
            if ($promotions !== null) {
                $this->say("  Using slot: {$slot} ✓ (has rankedPromotions)");
                return $decoded;
            }
        }

        $this->say('  ✗ No slot found with rankedPromotions data');
        return false;
    }

    /**
     * Extract a balanced JSON object/array starting at $start in $str.
     * Returns the raw JSON string, or false on failure.
     */
    private function extractBalancedJson(string $str, int $start): string|false
    {
        $len    = strlen($str);
        $depth  = 0;
        $inStr  = false;
        $escape = false;

        for ($i = $start; $i < $len; $i++) {
            $c = $str[$i];

            if ($escape) {
                $escape = false;
                continue;
            }
            if ($c === '\\' && $inStr) {
                $escape = true;
                continue;
            }
            if ($c === '"') {
                $inStr = !$inStr;
                continue;
            }
            if ($inStr) continue;

            if ($c === '{' || $c === '[') $depth++;
            if ($c === '}' || $c === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($str, $start, $i - $start + 1);
                }
            }
        }
        return false;
    }

    // ── Process promotions ───────────────────────────────────────────────────

    /**
     * Process an array of Amazon `rankedPromotions` objects.
     * Extracts all relevant fields and calls $this->saveDeal().
     *
     * @param  array $promotions  Array of promotion objects from the widget JSON
     * @param  int   $offset      How many we've already saved (for limit check)
     * @return int   Number of deals saved/updated in this batch
     */
    private function processPromotions(array $promotions, int $offset): int
    {
        $saved = 0;

        foreach ($promotions as $promo) {
            if (($offset + $saved) >= $this->limit) break;

            $product = $promo['product']['entity'] ?? null;
            if (!$product) continue;

            // ── ASIN ─────────────────────────────────────────────────────────
            $asin = $product['asin'] ?? null;
            if (!$asin) continue;

            // ── Title ────────────────────────────────────────────────────────
            $title = $product['title']['entity']['displayString']
                  ?? $product['title']['entity']['shortDisplayString']
                  ?? null;
            if (!$title) continue;
            $title = trim(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));

            // ── Images ───────────────────────────────────────────────────────
            $imageUrl = $this->extractBestImage($product);

            // ── Pricing ──────────────────────────────────────────────────────
            // Amazon embeds multiple buying options; pick the first non-empty one
            $pricing = $this->extractPricing($product['buyingOptions'] ?? []);
            if (!$pricing) continue;   // skip deals with no parseable price

            // ── Affiliate URL ────────────────────────────────────────────────
            $relPath    = $product['links']['entity']['viewOnAmazon']['url']
                       ?? "/dp/{$asin}";
            // Normalize to amazon.com regardless of what the JSON returns
            $relPath    = preg_replace('|^https?://[^/]+|', '', $relPath);
            $productUrl = 'https://www.amazon.com' . $relPath;
            $affiliateUrl = $productUrl . (str_contains($productUrl, '?') ? '&' : '?')
                          . 'tag=' . $this->tag;

            // ── Category ─────────────────────────────────────────────────────
            $categoryRaw = $product['productCategory']['entity']['websiteDisplayGroup']['displayString']
                        ?? $product['productCategory']['entity']['glProductGroup']['symbol']
                        ?? 'Deals';
            $category = method_exists($this, 'mapCategory') ? $this->mapCategory($categoryRaw ?: $title) : 'Deals';

            // ── Brand ────────────────────────────────────────────────────────
            $brand = $product['brandLogo']['entity']['logo']['entity']['altText']
                  ?? null;

            // ── Deal badge / deal type ───────────────────────────────────────
            $dealType = $this->extractDealType($product['buyingOptions'] ?? []);

            // ── Save ─────────────────────────────────────────────────────────
            $deal = [
                'title'          => trim($title),
                'original_price' => $pricing['original'],
                'sale_price'     => $pricing['sale'],
                'discount_pct'   => $pricing['pct'],
                'image_url'      => $imageUrl,
                'product_url'    => $productUrl,
                'affiliate_url'  => $affiliateUrl,
                'category'       => $category,
                'rating'         => null,
                'review_count'   => 0
            ];

            $wasSaved = $this->saveDeal($deal);
            
            if ($wasSaved) {
                $saved++;
                $pct = $pricing['pct'];
                $sale = number_format($pricing['sale'], 2);
                $this->say("  + Saved: [{$pct}% OFF] \${$sale} — " . mb_substr($title, 0, 50) . "...");
            }
        }

        return $saved;
    }

    // ── Image extraction ─────────────────────────────────────────────────────

    /**
     * Extract the best available image URL from a product entity.
     * Prefers hiRes MAIN variant, falls back through lowRes and PT01.
     */
    private function extractBestImage(array $product): ?string
    {
        $images = $product['productImages']['entity']['images'] ?? [];

        // Priority order: MAIN hiRes → MAIN lowRes → PT01 hiRes → PT01 lowRes
        $priority = [
            ['variant' => 'MAIN', 'res' => 'hiRes'],
            ['variant' => 'MAIN', 'res' => 'lowRes'],
            ['variant' => 'PT01', 'res' => 'hiRes'],
            ['variant' => 'PT01', 'res' => 'lowRes'],
        ];

        foreach ($priority as $pref) {
            foreach ($images as $img) {
                if (($img['variant'] ?? '') !== $pref['variant']) continue;
                $physicalId = $img[$pref['res']]['physicalId'] ?? null;
                if ($physicalId) {
                    return $this->buildImageUrl($physicalId, $pref['res'] === 'hiRes');
                }
            }
        }

        // Last resort: first image with any physicalId
        foreach ($images as $img) {
            $physicalId = $img['hiRes']['physicalId']
                       ?? $img['lowRes']['physicalId']
                       ?? null;
            if ($physicalId) return $this->buildImageUrl($physicalId, false);
        }

        return null;
    }

    /**
     * Convert an Amazon physicalId (e.g. "71nTmnfjuYL") into a CDN URL.
     * Uses _AC_SL500_ (Auto Crop, Size Limit 500) to prevent CDN 404 errors.
     */
    private function buildImageUrl(string $physicalId, bool $hiRes): string
    {
        // Strip trailing ._XxxYxx_ size tokens the JSON might already contain
        $clean = preg_replace('/\._[A-Z0-9,_]+_$/', '', $physicalId);
        
        // _AC_SL500_ is universally accepted by Amazon's CDN. 
        // _SX500_ is strict and will 404 on certain aspect ratios.
        $size = $hiRes ? '_AC_SL500_' : '_AC_SL300_';
        
        return "https://m.media-amazon.com/images/I/{$clean}.{$size}.jpg";
    }

    // ── Price extraction ─────────────────────────────────────────────────────

    /**
     * Walk buyingOptions to find the best-value pricing tuple.
     * Returns ['sale'=>float, 'original'=>float, 'pct'=>int, 'currency'=>string]
     * or null if no parseable price exists.
     */
    private function extractPricing(array $buyingOptions): ?array
    {
        $best = null;

        foreach ($buyingOptions as $option) {
            $priceEntity = $option['price']['entity'] ?? null;
            if (!$priceEntity) continue;

            $sale     = $this->parseMoney($priceEntity['priceToPay']  ?? null);
            $original = $this->parseMoney($priceEntity['basisPrice']  ?? null);
            $pct      = (int)($priceEntity['savings']['percentage']['value'] ?? 0);
            $currency = $priceEntity['priceToPay']['moneyValueOrRange']['value']['currencyCode']
                     ?? 'CAD';

            // Use original price from savings if basisPrice is missing
            if ($original === null && $sale !== null && $pct > 0) {
                $original = round($sale / (1 - $pct / 100), 2);
            }
            
            if ($sale === null || $original === null || $original <= 0) continue;
            
            // Re-verify percentage in case it wasn't provided or was wrong
            $calcPct = $this->calcDiscount($original, $sale);
            $pct = max($pct, $calcPct);

            // Skip if discount < 50 %
            if ($pct < 50) continue;

            // Prefer highest discount
            if ($best === null || $pct > $best['pct']) {
                $best = [
                    'sale'     => $sale,
                    'original' => $original,
                    'pct'      => $pct,
                    'currency' => $currency,
                ];
            }
        }

        return $best;
    }

    /**
     * Parse a money node → float, or null.
     * Handles both moneyValueOrRange.value.amount and direct amount fields.
     */
    private function parseMoney(mixed $node): ?float
    {
        if (!is_array($node)) return null;

        $amount = $node['moneyValueOrRange']['value']['amount']
               ?? $node['moneyValueOrRange']['value']['convertedFrom']['amount']
               ?? $node['amount']
               ?? null;

        return $amount !== null ? (float)$amount : null;
    }

    // ── Deal type extraction ─────────────────────────────────────────────────

    /**
     * Extract a human-readable deal badge string from buying options.
     * Examples: "Limited-time deal", "With Prime", "Lightning Deal"
     */
    private function extractDealType(array $buyingOptions): ?string
    {
        foreach ($buyingOptions as $option) {
            $badge = $option['dealBadge']['entity'] ?? null;
            if (!$badge) continue;

            // "content" → fragments → text (the badge label, e.g. "77% off")
            // "messaging" → content → fragments → text (the deal type string)
            $msg = $badge['messaging']['content']['fragments'][0]['text']
                ?? $badge['label']['content']['fragments'][0]['text']
                ?? null;

            if ($msg) return trim($msg);
        }
        return null;
    }

    // ── API URL builder ──────────────────────────────────────────────────────

    /**
     * Build the pagination URL by reusing the resource URL extracted from the
     * widget JSON (which contains the correct filters, ranking context, and
     * pinning config for the current page). Only the startIndex is swapped.
     */
    private function buildPaginationUrl(string $resourceUrl, int $startIndex): string
    {
        // resourceUrl is a relative path like /api/marketplaces/ATVPDKIKX0DER/promotions?...
        $full = 'https://www.amazon.com' . $resourceUrl;

        // Replace startIndex=N with the new offset; add it if not present
        if (str_contains($full, 'startIndex=')) {
            $full = preg_replace('/startIndex=\d+/', 'startIndex=' . $startIndex, $full);
        } else {
            $full .= (str_contains($full, '?') ? '&' : '?') . 'startIndex=' . $startIndex;
        }

        return $full;
    }

    /**
     * Fetch Amazon's internal JSON API endpoint.
     * Uses minimal CORS-style headers instead of the full HTML browser headers,
     * which is what the API expects (HTML headers cause 404 on pagination endpoints).
     */
    private function fetchAmazonApi(string $url): string|false
    {
        if (!$this->cookieJar) {
            $this->cookieJar = tempnam(sys_get_temp_dir(), '50off_amz_');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            CURLOPT_REFERER        => 'https://www.amazon.com/deals/',
            CURLOPT_COOKIEJAR      => $this->cookieJar,
            CURLOPT_COOKIEFILE     => $this->cookieJar,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json, text/plain, */*',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate, br',
                'X-Requested-With: XMLHttpRequest',
                'sec-ch-ua: "Google Chrome";v="125", "Chromium";v="125", "Not-A.Brand";v="99"',
                'sec-ch-ua-mobile: ?0',
                'sec-ch-ua-platform: "Windows"',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: same-origin',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err)       { $this->say("  API cURL error: $err"); return false; }
        if ($code >= 400) { $this->say("  API HTTP $code"); return false; }
        return $body ?: false;
    }

    // ── HTTP helper ──────────────────────────────────────────────────────────

    /** Cookie jar path — shared across requests so API calls inherit the session */
    private string $cookieJar = '';

    /**
     * Fetch a URL with Amazon-appropriate headers and a persistent cookie jar.
     * The initial page load sets session cookies that the pagination API requires.
     */
    private function fetchAmazonPage(string $url, array $extraHeaders = []): string|false
    {
        if (!$this->cookieJar) {
            $this->cookieJar = tempnam(sys_get_temp_dir(), '50off_amz_');
        }

        $baseHeaders = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,'
                . 'image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate, br',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'sec-ch-ua: "Google Chrome";v="125", "Chromium";v="125", "Not-A.Brand";v="99"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: same-origin',
            'Upgrade-Insecure-Requests: 1',
        ];

        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_REFERER        => 'https://www.amazon.com/',
            CURLOPT_COOKIEJAR      => $this->cookieJar,
            CURLOPT_COOKIEFILE     => $this->cookieJar,
            CURLOPT_HTTPHEADER     => array_merge($baseHeaders, $extraHeaders),
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        if ($err)       { $this->say("cURL error: $err"); return false; }
        if ($code >= 400) { $this->say("HTTP $code: $url"); return false; }
        return $body ?: false;
    }
}