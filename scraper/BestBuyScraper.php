<?php
/**
 * BestBuyScraper.php — bestbuy.com clearance & deals (50%+ off)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * HOW IT WORKS:
 * ─────────────
 * Fetches Best Buy's clearance and sale category pages, then extracts product
 * data from embedded JSON (window.__PRELOADED_STATE__ or __NEXT_DATA__).
 * Falls back to HTML parsing of .sku-item product card elements.
 *
 * Best Buy aggressively rate-limits bots — we use polite delays and rotate
 * user agents. If blocked, the scraper logs 0 deals gracefully.
 *
 * AFFILIATE:
 * ──────────
 * Best Buy affiliate program is through Impact Radius (impact.com).
 * Until approved, product URLs are plain bestbuy.com links.
 * Register at: https://www.bestbuy.com/site/misc/affiliate-program/pcmcat1487694358130.c
 */
require_once __DIR__ . '/BaseScraper.php';

class BestBuyScraper extends BaseScraper
{
    protected string $store = 'bestbuy';
    private   int    $limit = 60;

    // BestBuy clearance pages — run via GitHub Actions to bypass Incapsula
    private const PAGES = [
        ['url' => 'https://www.bestbuy.com/site/misc/clearance/pcmcat128500050004.c?intl=nosplash',                 'cat' => 'Clearance'],
        ['url' => 'https://www.bestbuy.com/site/electronics/clearance-refurbished/abcat0100000.c?intl=nosplash',    'cat' => 'Electronics'],
        ['url' => 'https://www.bestbuy.com/site/computers-pcs/clearance-computers/pcmcat232000050001.c?intl=nosplash', 'cat' => 'Computers'],
        ['url' => 'https://www.bestbuy.com/site/tvs/clearance-tvs/pcmcat284700050000.c?intl=nosplash',              'cat' => 'TVs'],
    ];

    // BestBuy public deals JSON API
    private const API_URL = 'https://www.bestbuy.com/api/deals/v1/getDeals?currentPage=1&pageSize=48&type=CLEARANCE';

    public function scrape(): void
    {
        $this->say('=== Best Buy Scraper — bestbuy.com (50%+ off) ===');
        $this->say('  ℹ Best run from GitHub Actions (bypasses Incapsula)');

        // Try the JSON deals API first — no bot protection on this endpoint
        $savedTotal = $this->scrapeDealsApi();
        $this->say("  API total: {$savedTotal}");

        foreach (self::PAGES as $page) {
            if ($savedTotal >= $this->limit) break;

            $this->say("→ {$page['cat']}...");
            sleep(rand(2, 4));

            $html = $this->fetch(
                $page['url'],
                [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                    'Sec-Fetch-Dest: document',
                    'Sec-Fetch-Mode: navigate',
                    'Sec-Fetch-Site: none',
                    'Upgrade-Insecure-Requests: 1',
                ],
                'https://www.google.com/'
            );

            if (!$html) {
                $this->say("  ✗ No response (possibly blocked). Skipping.");
                continue;
            }

            $this->say("  ✓ " . number_format(strlen($html)) . " bytes");

            // Check for Incapsula / bot protection
            if (
                stripos($html, 'incapsula') !== false ||
                stripos($html, 'Access Denied') !== false ||
                stripos($html, 'robot') !== false ||
                strlen($html) < 5000
            ) {
                $this->say("  ✗ Bot protection detected. Skipping.");
                continue;
            }

            // Try embedded JSON first (faster and more reliable)
            $pageSaved = $this->parseEmbeddedJson($html);

            // Fall back to HTML product card parsing
            if ($pageSaved === 0) {
                $this->say("  No embedded JSON. Trying HTML parsing...");
                $pageSaved = $this->parseHtmlProducts($html);
            }

            $savedTotal += $pageSaved;
            $this->say("  Saved {$pageSaved} deals from this category");
        }

        $this->say("══ Done: {$savedTotal} deals saved ══");
        $this->logResult($savedTotal > 0 ? 'success' : 'warning', "bestbuy.com 50%+ (saved: {$savedTotal})");
    }

    // ── BestBuy public deals JSON API (no bot protection) ────────────────────
    private function scrapeDealsApi(): int
    {
        $this->say("→ Deals API...");
        $body = $this->fetchJson(self::API_URL, [
            'Accept: application/json',
            'Referer: https://www.bestbuy.com/',
            'Origin: https://www.bestbuy.com',
        ]);
        if (!$body || empty($body['deals'])) {
            $this->say("  ✗ API returned no deals");
            return 0;
        }
        $this->say("  Found " . count($body['deals']) . " deals from API");
        $saved = 0;
        foreach ($body['deals'] as $deal) {
            $title    = $deal['name'] ?? $deal['title'] ?? null;
            if (!$title) continue;
            $sale     = (float)($deal['salePrice']     ?? $deal['price']        ?? 0);
            $original = (float)($deal['regularPrice']  ?? $deal['originalPrice'] ?? 0);
            $pct      = (int)($deal['percentSavings']  ?? 0);
            if ($sale <= 0 || $original <= 0 || $original <= $sale) continue;
            $pct = max($pct, $this->calcDiscount($original, $sale));
            if ($pct < 50) continue;
            $sku        = $deal['sku'] ?? $deal['skuId'] ?? '';
            $productUrl = isset($deal['url'])
                ? (str_starts_with($deal['url'], 'http') ? $deal['url'] : 'https://www.bestbuy.com' . $deal['url'])
                : "https://www.bestbuy.com/site/{$sku}.p";
            if ($this->saveDeal([
                'title'          => trim(html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8')),
                'original_price' => $original,
                'sale_price'     => $sale,
                'discount_pct'   => $pct,
                'image_url'      => $deal['thumbnailImage'] ?? $deal['image'] ?? null,
                'product_url'    => $productUrl,
                'affiliate_url'  => $productUrl,
                'category'       => $this->mapCategory($title),
                'rating'         => isset($deal['customerReviewAverage']) ? (float)$deal['customerReviewAverage'] : null,
                'review_count'   => (int)($deal['customerReviewCount'] ?? 0),
            ])) $saved++;
        }
        return $saved;
    }

    // ── Try to extract products from embedded page JSON ───────────────────────
    private function parseEmbeddedJson(string $html): int
    {
        $data = null;

        // Try window.__PRELOADED_STATE__
        if (preg_match('/window\.__PRELOADED_STATE__\s*=\s*(\{.+?\});?\s*(?:window\.|<\/script>)/s', $html, $m)) {
            $data = json_decode($m[1], true);
        }

        // Try Next.js __NEXT_DATA__
        if (!$data && preg_match('/<script[^>]+id=["\']__NEXT_DATA__["\'][^>]*>(.+?)<\/script>/s', $html, $m)) {
            $data = json_decode($m[1], true);
        }

        if (!$data || json_last_error() !== JSON_ERROR_NONE) return 0;

        // Navigate various possible JSON structures
        $products = $data['shop']['productList']['products']
                 ?? $data['productList']['products']
                 ?? $data['props']['pageProps']['productList']['products']
                 ?? $data['props']['pageProps']['products']
                 ?? [];

        if (empty($products)) return 0;

        $this->say("  Found " . count($products) . " products in embedded JSON");
        return $this->processJsonProducts($products);
    }

    private function processJsonProducts(array $products): int
    {
        $saved = 0;
        foreach ($products as $prod) {
            $title        = $prod['name'] ?? $prod['longDescription'] ?? null;
            if (!$title) continue;
            $title = trim(html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8'));

            $sale         = (float)($prod['salePrice']       ?? $prod['regularPrice'] ?? 0);
            $original     = (float)($prod['regularPrice']    ?? 0);
            $pct          = (int)($prod['percentSavings']    ?? 0);

            if ($sale <= 0 || $original <= 0) continue;
            if ($original <= $sale) continue;

            $calcPct = $this->calcDiscount($original, $sale);
            $pct     = max($pct, $calcPct);
            if ($pct < 50) continue;

            $sku      = $prod['sku']    ?? $prod['skuId'] ?? '';
            $imageUrl = $prod['image']  ?? $prod['thumbnailImage'] ?? null;
            $relUrl   = $prod['url']    ?? "/site/{$sku}.p";
            $productUrl  = str_starts_with($relUrl, 'http') ? $relUrl : 'https://www.bestbuy.com' . $relUrl;
            $affiliateUrl = $productUrl;

            if ($this->saveDeal([
                'title'          => $title,
                'original_price' => $original,
                'sale_price'     => $sale,
                'discount_pct'   => $pct,
                'image_url'      => $imageUrl,
                'product_url'    => $productUrl,
                'affiliate_url'  => $affiliateUrl,
                'category'       => $this->mapCategory($title),
                'rating'         => isset($prod['customerReviewAverage']) ? (float)$prod['customerReviewAverage'] : null,
                'review_count'   => (int)($prod['customerReviewCount'] ?? 0),
            ])) {
                $saved++;
            }
        }
        return $saved;
    }

    // ── HTML product card fallback ────────────────────────────────────────────
    private function parseHtmlProducts(string $html): int
    {
        $saved = 0;
        $xpath = $this->loadDom($html);
        if (!$xpath) return 0;

        // Best Buy product cards: <li class="sku-item"> or <div class="list-item">
        $items = $xpath->query(
            '//li[contains(@class,"sku-item")] | //div[contains(@class,"list-item") and @data-sku-id]'
        );
        if (!$items || $items->length === 0) return 0;

        $this->say("  Found {$items->length} product card elements in HTML");

        foreach ($items as $item) {
            if (!($item instanceof \DOMElement)) continue;

            // Title
            $titleNode = $xpath->query('.//h4[contains(@class,"sku-header")]//a | .//h4//a', $item)->item(0);
            $title = $titleNode ? trim($titleNode->textContent) : '';
            if (!$title) continue;

            // Sale price
            $salePriceNode = $xpath->query(
                './/*[contains(@class,"priceView-customer-price")]//span[@aria-hidden="true"] | .//*[@class="sr-only" and contains(text(),"Your price")]',
                $item
            )->item(0);
            $sale = $salePriceNode ? $this->parsePrice($salePriceNode->textContent) : 0.0;
            if ($sale <= 0) {
                // Try any visible price
                $anyPrice = $xpath->query('.//*[contains(@class,"priceView")]//span[contains(text(),"$")]', $item)->item(0);
                $sale = $anyPrice ? $this->parsePrice($anyPrice->textContent) : 0.0;
            }
            if ($sale <= 0) continue;

            // Was/regular price
            $wasPriceNode = $xpath->query(
                './/*[contains(@class,"price-was") or contains(@class,"pricing-price__regular")]//span | .//*[@data-testid="medium-strike-through-price"]',
                $item
            )->item(0);
            $original = $wasPriceNode ? $this->parsePrice($wasPriceNode->textContent) : 0.0;
            if ($original <= 0 || $original <= $sale) continue;

            $pct = $this->calcDiscount($original, $sale);
            if ($pct < 50) continue;

            // Image
            $imgNode  = $xpath->query('.//img[contains(@class,"product-image")]', $item)->item(0);
            $imageUrl = ($imgNode instanceof \DOMElement) ? $imgNode->getAttribute('src') : null;

            // URL
            $linkNode    = $xpath->query('.//a[@href and contains(@href,"/site/")]', $item)->item(0);
            $relUrl      = ($linkNode instanceof \DOMElement) ? $linkNode->getAttribute('href') : '';
            $productUrl  = $relUrl ? 'https://www.bestbuy.com' . $relUrl : null;
            if (!$productUrl) continue;

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
