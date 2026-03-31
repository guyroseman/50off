<?php
/**
 * AmazonScraper.php — amazon.com US deals (50–100% off)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * HOW IT WORKS:
 * ─────────────
 * Amazon's deals page embeds all product data in a mountWidget() JSON blob.
 * We fetch the page (following any event redirect like /bigspringsale), extract
 * the JSON from the slot that contains rankedPromotions, and save each deal.
 *
 * Amazon returns ~30 deals per page. Pagination via their internal API endpoint
 * (extracted from the JSON's resource.url) is attempted but may 404 if the
 * event-specific ranking context has expired — that's fine, we still get 30/run.
 *
 * AFFILIATE:
 * ──────────
 * Tag '50off-20' is appended to all product URLs.
 * Register/manage at: https://affiliate-program.amazon.com/
 */
require_once __DIR__ . '/BaseScraper.php';

class AmazonScraper extends BaseScraper
{
    protected string $store = 'amazon';
    private   string $tag   = '50off-20';
    private   int    $limit = 90;           // max deals per run

    // 50–100% off filter encoded for the discounts-widget param
    private const FILTER = '%2522%257B%255C%2522state%255C%2522%253A%257B%255C%2522rangeRefinementFilters%255C%2522%253A%257B%255C%2522percentOff%255C%2522%253A%257B%255C%2522min%255C%2522%253A50%252C%255C%2522max%255C%2522%253A100%257D%257D%257D%252C%255C%2522version%255C%2522%253A1%257D%2522';

    // Persistent cookie jar — session cookies must carry from page fetch to API fetch
    private string $cookieJar = '';

    public function scrape(): void
    {
        $this->say('=== Amazon Scraper — amazon.com US (50–100% off) ===');

        $this->cookieJar = tempnam(sys_get_temp_dir(), '50off_amz_');

        $savedTotal = 0;

        // One entry point. Amazon may redirect to an event page (e.g. /bigspringsale).
        // The redirect is transparent — we follow it and the data is in the final page.
        $entryUrl = 'https://www.amazon.com/deals/?discounts-widget=' . self::FILTER;

        $this->say("→ Fetching deals page...");
        $html = $this->fetchAmazonPage($entryUrl);
        if (!$html) {
            $this->say('✗ Failed to fetch. Aborting.');
            $this->logResult('error', 'Could not fetch Amazon deals page');
            return;
        }
        $this->say('  ✓ ' . number_format(strlen($html)) . ' bytes');

        $json = $this->extractMountWidgetJson($html);
        if (!$json) {
            $this->say('✗ No widget JSON found. Aborting.');
            $this->logResult('error', 'mountWidget JSON extraction failed');
            return;
        }

        $prefetch    = $json['prefetchedData']['entity'] ?? [];
        $promotions  = $prefetch['rankedPromotions']     ?? [];
        $nextIndex   = (int)($prefetch['nextIndex']      ?? 0);
        $resourceUrl = $json['prefetchedData']['resource']['url'] ?? $json['resource']['url'] ?? null;

        $this->say('  Initial batch: ' . count($promotions) . ' | nextIndex: ' . $nextIndex);

        $savedTotal += $this->processPromotions($promotions, $savedTotal);

        // ── Pagination ────────────────────────────────────────────────────────
        $pageSize = count($promotions);
        while ($savedTotal < $this->limit && $nextIndex > 0 && $pageSize >= 24 && $resourceUrl) {
            $this->say("→ Paginating startIndex={$nextIndex}...");
            sleep(rand(1, 2));

            $apiUrl  = $this->buildPaginationUrl($resourceUrl, $nextIndex);
            $apiBody = $this->fetchAmazonApi($apiUrl);

            if (!$apiBody) {
                $this->say('  ✗ Pagination failed. Stopping.');
                break;
            }

            $apiData    = json_decode($apiBody, true);
            $promotions = $apiData['entity']['rankedPromotions'] ?? [];
            $nextIndex  = (int)($apiData['entity']['nextIndex'] ?? 0);
            $pageSize   = count($promotions);

            if (empty($promotions)) { $this->say('  No more results.'); break; }

            $this->say('  ✓ Got ' . $pageSize . ' promotions');
            $savedTotal += $this->processPromotions($promotions, $savedTotal);
        }

        $this->say("══ Done: {$savedTotal} deals saved ══");
        $this->logResult('success', "amazon.com US 50%+ (saved: {$savedTotal})");
    }

    // ── Extract mountWidget JSON ──────────────────────────────────────────────
    private function extractMountWidgetJson(string $html): array|false
    {
        preg_match_all("/assets\.mountWidget\('([^']+)'/", $html, $slotMatches);
        $slots = $slotMatches[1] ?? [];

        if (empty($slots)) {
            $this->say('  No mountWidget calls in HTML');
            return false;
        }
        $this->say('  Slots: ' . implode(', ', $slots));

        foreach ($slots as $slot) {
            $marker = "assets.mountWidget('{$slot}',";
            $pos    = strpos($html, $marker);
            if ($pos === false) continue;

            $start   = strpos($html, '{', $pos + strlen($marker));
            if ($start === false) continue;

            $jsonStr = $this->extractBalancedJson($html, $start);
            if (!$jsonStr) continue;

            $decoded = json_decode($jsonStr, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $jsonStr);
                $decoded = json_decode($cleaned, true);
                if (json_last_error() !== JSON_ERROR_NONE) continue;
            }

            if (isset($decoded['prefetchedData']['entity']['rankedPromotions'])) {
                $this->say("  Using slot: {$slot} ✓");
                return $decoded;
            }
        }
        $this->say('  No slot with rankedPromotions found');
        return false;
    }

    private function extractBalancedJson(string $str, int $start): string|false
    {
        $len = strlen($str); $depth = 0; $inStr = false; $escape = false;
        for ($i = $start; $i < $len; $i++) {
            $c = $str[$i];
            if ($escape) { $escape = false; continue; }
            if ($c === '\\' && $inStr) { $escape = true; continue; }
            if ($c === '"') { $inStr = !$inStr; continue; }
            if ($inStr) continue;
            if ($c === '{' || $c === '[') $depth++;
            if ($c === '}' || $c === ']') { $depth--; if ($depth === 0) return substr($str, $start, $i - $start + 1); }
        }
        return false;
    }

    // ── Process promotions ────────────────────────────────────────────────────
    private function processPromotions(array $promotions, int $alreadySaved): int
    {
        $saved = 0;
        foreach ($promotions as $promo) {
            if (($alreadySaved + $saved) >= $this->limit) break;

            $product = $promo['product']['entity'] ?? null;
            if (!$product) continue;

            $asin = $product['asin'] ?? null;
            if (!$asin) continue;

            // Title
            $title = $product['title']['entity']['displayString']
                  ?? $product['title']['entity']['shortDisplayString']
                  ?? null;
            if (!$title) continue;
            $title = trim(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));

            // Pricing
            $pricing = $this->extractPricing($product['buyingOptions'] ?? []);
            if (!$pricing) continue;

            // Image — prefer MAIN hiRes, fallback to lowRes
            $imageUrl = $this->extractBestImage($product);

            // URL
            $relPath    = $product['links']['entity']['viewOnAmazon']['url'] ?? "/dp/{$asin}";
            $relPath    = preg_replace('|^https?://[^/]+|', '', $relPath);
            $productUrl = 'https://www.amazon.com' . $relPath;
            $affiliateUrl = $productUrl . (str_contains($productUrl, '?') ? '&' : '?') . 'tag=' . $this->tag;

            // Category
            $catRaw = $product['productCategory']['entity']['websiteDisplayGroup']['displayString']
                   ?? $product['productCategory']['entity']['glProductGroup']['symbol']
                   ?? '';

            if ($this->saveDeal([
                'title'          => $title,
                'original_price' => $pricing['original'],
                'sale_price'     => $pricing['sale'],
                'discount_pct'   => $pricing['pct'],
                'image_url'      => $imageUrl,
                'product_url'    => $productUrl,
                'affiliate_url'  => $affiliateUrl,
                'category'       => $this->mapCategory($catRaw ?: $title),
                'rating'         => null,
                'review_count'   => 0,
            ])) {
                $saved++;
            }
        }
        return $saved;
    }

    // ── Image extraction ──────────────────────────────────────────────────────
    private function extractBestImage(array $product): ?string
    {
        $images = $product['productImages']['entity']['images'] ?? [];
        foreach ([['MAIN','hiRes'],['MAIN','lowRes'],['PT01','hiRes'],['PT01','lowRes']] as [$variant, $res]) {
            foreach ($images as $img) {
                if (($img['variant'] ?? '') !== $variant) continue;
                $id = $img[$res]['physicalId'] ?? null;
                if ($id) return $this->buildAmazonImageUrl($id, $res === 'hiRes');
            }
        }
        foreach ($images as $img) {
            $id = $img['hiRes']['physicalId'] ?? $img['lowRes']['physicalId'] ?? null;
            if ($id) return $this->buildAmazonImageUrl($id, false);
        }
        return null;
    }

    private function buildAmazonImageUrl(string $physicalId, bool $hiRes): string
    {
        // Strip any existing size tokens
        $clean = preg_replace('/\._[A-Z0-9,_]+_$/', '', $physicalId);
        // _AC_SL500_ = Auto-Crop, Size-Limit 500px — works on all aspect ratios
        $size  = $hiRes ? '_AC_SL500_' : '_AC_SL300_';
        return "https://m.media-amazon.com/images/I/{$clean}.{$size}.jpg";
    }

    // ── Price extraction ──────────────────────────────────────────────────────
    private function extractPricing(array $buyingOptions): ?array
    {
        $best = null;
        foreach ($buyingOptions as $option) {
            $pe = $option['price']['entity'] ?? null;
            if (!$pe) continue;

            $sale     = $this->parseMoney($pe['priceToPay'] ?? null);
            $original = $this->parseMoney($pe['basisPrice'] ?? null);
            $pct      = (int)($pe['savings']['percentage']['value'] ?? 0);

            if ($original === null && $sale !== null && $pct > 0) {
                $original = round($sale / (1 - $pct / 100), 2);
            }
            if ($sale === null || $original === null || $original <= 0) continue;

            $calcPct = $this->calcDiscount($original, $sale);
            $pct = max($pct, $calcPct);
            if ($pct < 50) continue;

            if ($best === null || $pct > $best['pct']) {
                $best = ['sale' => $sale, 'original' => $original, 'pct' => $pct];
            }
        }
        return $best;
    }

    private function parseMoney(mixed $node): ?float
    {
        if (!is_array($node)) return null;
        $amount = $node['moneyValueOrRange']['value']['amount']
               ?? $node['moneyValueOrRange']['value']['convertedFrom']['amount']
               ?? $node['amount']
               ?? null;
        return $amount !== null ? (float)$amount : null;
    }

    // ── URL builder ───────────────────────────────────────────────────────────
    private function buildPaginationUrl(string $resourceUrl, int $startIndex): string
    {
        $full = 'https://www.amazon.com' . $resourceUrl;
        if (str_contains($full, 'startIndex=')) {
            return preg_replace('/startIndex=\d+/', 'startIndex=' . $startIndex, $full);
        }
        return $full . (str_contains($full, '?') ? '&' : '?') . 'startIndex=' . $startIndex;
    }

    // ── HTTP helpers ──────────────────────────────────────────────────────────
    private function fetchAmazonPage(string $url): string|false
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
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            CURLOPT_REFERER        => 'https://www.amazon.com/',
            CURLOPT_COOKIEJAR      => $this->cookieJar,
            CURLOPT_COOKIEFILE     => $this->cookieJar,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate, br',
                'sec-ch-ua: "Google Chrome";v="125", "Chromium";v="125"',
                'sec-ch-ua-mobile: ?0',
                'sec-ch-ua-platform: "Windows"',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Upgrade-Insecure-Requests: 1',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($err)       { $this->say("cURL error: $err"); return false; }
        if ($code >= 400) { $this->say("HTTP $code"); return false; }
        return $body ?: false;
    }

    // Dedicated JSON API fetch — uses CORS headers, not HTML navigation headers
    private function fetchAmazonApi(string $url): string|false
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
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
                'sec-ch-ua: "Google Chrome";v="125"',
                'sec-ch-ua-mobile: ?0',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: same-origin',
                'X-Requested-With: XMLHttpRequest',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 400) { $this->say("  API HTTP $code"); return false; }
        return $body ?: false;
    }
}
