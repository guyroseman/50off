<?php
/**
 * WalmartScraper.php — walmart.com clearance deals (50%+ off)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * HOW IT WORKS:
 * ─────────────
 * Walmart's search/browse pages embed ALL product data in a <script id="__NEXT_DATA__">
 * JSON blob. We parse itemStacks[].items[] from the search result.
 *
 * ⚠ BLOCKING: Walmart uses Akamai Bot Manager.
 *   - From Hostinger (datacenter): BLOCKED (HTTP 307 → bot page)
 *   - From GitHub Actions (Microsoft Azure): Often works — run via workflow
 *   - From residential: CAPTCHA
 *
 * Run this scraper via GitHub Actions (scrape.yml) not from Hostinger cron.
 *
 * URLS:
 * ─────
 * Clearance sorted by discount: /browse/clearance?facet=discount_percentage%3A%5B50_TO_100%5D&sort=discount_high
 * Search 50%+ off: /search?q=clearance&facet=discount_percentage%3A%5B50_TO_100%5D
 *
 * AFFILIATE:
 * ──────────
 * Apply at: https://affiliates.walmart.com/
 * Tag format: &affiliates=1&sourceid=imp_xxx
 */
require_once __DIR__ . '/BaseScraper.php';

class WalmartScraper extends BaseScraper
{
    protected string $store = 'walmart';
    private   int    $limit = 80;

    private const PAGES = [
        // Clearance 50%+ off sorted by highest discount
        'https://www.walmart.com/browse/clearance/clearance-special-buys/1085666_1232864?facet=discount_percentage%3A%5B50_TO_100%5D&sort=discount_high',
        // Clothing clearance
        'https://www.walmart.com/browse/clothing/clearance/5438_1045804?facet=discount_percentage%3A%5B50_TO_100%5D&sort=discount_high',
        // Electronics clearance
        'https://www.walmart.com/browse/electronics/clearance/3944_1045804?facet=discount_percentage%3A%5B50_TO_100%5D&sort=discount_high',
        // Toys clearance
        'https://www.walmart.com/browse/toys/clearance/4171_1045804?facet=discount_percentage%3A%5B50_TO_100%5D&sort=discount_high',
        // Home clearance
        'https://www.walmart.com/browse/home/clearance/4044_1045804?facet=discount_percentage%3A%5B50_TO_100%5D&sort=discount_high',
    ];

    public function scrape(): void
    {
        $this->say('=== Walmart Scraper — walmart.com (50%+ off) ===');
        $this->say('  ℹ Best run from GitHub Actions (Azure IPs bypass Akamai)');

        $savedTotal = 0;

        foreach (self::PAGES as $url) {
            if ($savedTotal >= $this->limit) break;

            $label = parse_url($url, PHP_URL_PATH);
            $this->say("→ {$label}...");
            sleep(rand(3, 6));

            $html = $this->fetchWalmart($url);
            if (!$html) {
                $this->say("  ✗ Blocked or failed. Trying next page.");
                continue;
            }

            $n = $this->parseNextData($html);
            $savedTotal += $n;
            $this->say("  Saved {$n} deals");
        }

        $status = $savedTotal > 0 ? 'success' : 'error';
        $this->say("══ Done: {$savedTotal} deals saved ══");
        $this->logResult($status, "walmart.com 50%+ (saved: {$savedTotal})");
    }

    private function fetchWalmart(string $url): string|false
    {
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
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            CURLOPT_REFERER        => 'https://www.walmart.com/',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate, br',
                'Connection: keep-alive',
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

        if ($err)         { $this->say("  cURL: $err"); return false; }
        if ($code >= 400) { $this->say("  HTTP $code"); return false; }
        if (strlen($body) < 5000) { $this->say("  Response too short — likely bot block"); return false; }

        // Akamai bot block detection
        if (stripos($body, 'AkamaiGHost') !== false ||
            stripos($body, 'access denied') !== false ||
            stripos($body, 'robot') !== false ||
            stripos($body, 'captcha') !== false) {
            $this->say("  Bot protection detected (Akamai)");
            return false;
        }

        $this->say("  ✓ " . number_format(strlen($body)) . " bytes (HTTP $code)");
        return $body;
    }

    private function parseNextData(string $html): int
    {
        if (!preg_match('/<script[^>]+id=["\']__NEXT_DATA__["\'][^>]*>(.+?)<\/script>/s', $html, $m)) {
            $this->say("  No __NEXT_DATA__ found");
            return 0;
        }

        $data = json_decode($m[1], true);
        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            $this->say("  __NEXT_DATA__ JSON parse failed");
            return 0;
        }

        // Walmart structure: props.pageProps.initialData.searchResult.itemStacks[].items[]
        $initialData = $data['props']['pageProps']['initialData'] ?? [];
        $searchResult = $initialData['searchResult'] ?? $initialData['contentLayout'] ?? [];

        // Collect all items from itemStacks
        $allItems = [];
        $itemStacks = $searchResult['itemStacks'] ?? [];
        foreach ($itemStacks as $stack) {
            foreach ($stack['items'] ?? [] as $item) {
                $allItems[] = $item;
            }
        }

        // Also try direct items array
        if (empty($allItems)) {
            $allItems = $searchResult['items'] ?? [];
        }

        if (empty($allItems)) {
            // Deep scan for any array with usItemId
            $this->deepScan($data, $allItems);
        }

        if (empty($allItems)) {
            $this->say("  No items found in __NEXT_DATA__");
            return 0;
        }

        $this->say("  Found " . count($allItems) . " items");
        return $this->processItems($allItems);
    }

    private function deepScan(array $data, array &$found): void
    {
        foreach ($data as $key => $val) {
            if ($key === 'items' && is_array($val) && !empty($val) && isset($val[0]['usItemId'])) {
                $found = array_merge($found, $val);
                return;
            }
            if (is_array($val)) {
                $this->deepScan($val, $found);
                if (!empty($found)) return;
            }
        }
    }

    private function processItems(array $items): int
    {
        $saved = 0;
        foreach ($items as $item) {
            if (!is_array($item)) continue;

            // Skip sponsored/ad items
            if (!empty($item['isSponsoredFlag']) || !empty($item['isAd'])) continue;

            $title = $item['name'] ?? $item['description'] ?? null;
            if (!$title || strlen($title) < 4) continue;
            $title = trim(html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8'));

            // Pricing — Walmart structure has priceInfo object
            $priceInfo = $item['priceInfo'] ?? [];
            $sale     = (float)($priceInfo['currentPrice']['price']     ?? $item['price']          ?? 0);
            $original = (float)($priceInfo['wasOrUnitPrice']['price']   ?? $item['wasPrice']       ?? $item['listPrice'] ?? 0);
            $pct      = (int)($priceInfo['savingsPercent']              ?? $item['savingsPercent'] ?? 0);

            if ($sale <= 0) continue;
            if ($original <= 0 && $pct > 0) $original = round($sale / (1 - $pct / 100), 2);
            if ($original > $sale) $pct = max($pct, $this->calcDiscount($original, $sale));
            if ($pct < 50 || $original <= 0 || $original <= $sale) continue;

            // Image
            $imageUrl = $item['imageInfo']['thumbnailUrl']
                     ?? $item['image']
                     ?? $item['imageUrl']
                     ?? null;
            if ($imageUrl) {
                // Upgrade Walmart image to larger size
                $imageUrl = preg_replace('/\?.*/', '?odnHeight=500&odnWidth=500&odnBg=FFFFFF', $imageUrl);
            }

            // URL
            $canonicalUrl = $item['canonicalUrl'] ?? null;
            $itemId       = $item['usItemId'] ?? $item['itemId'] ?? null;
            if ($canonicalUrl) {
                $productUrl = str_starts_with($canonicalUrl, 'http')
                    ? $canonicalUrl
                    : 'https://www.walmart.com' . $canonicalUrl;
            } elseif ($itemId) {
                $productUrl = "https://www.walmart.com/ip/{$itemId}";
            } else {
                continue;
            }

            // Rating
            $rating      = isset($item['rating']['averageRating'])    ? (float)$item['rating']['averageRating']    : null;
            $reviewCount = (int)($item['rating']['numberOfRatings']   ?? $item['reviewCount'] ?? 0);

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
}
