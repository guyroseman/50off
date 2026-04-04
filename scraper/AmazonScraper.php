<?php
/**
 * AmazonScraper.php — amazon.com US deals (50–100% off)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * HOW IT WORKS:
 * ─────────────
 * Amazon's deals page embeds all product data in a mountWidget() JSON blob
 * (widget type: discount-asin-grid). We fetch the page, follow any event
 * redirect (e.g. /events/bigspringsale), extract the JSON from slot-5, and
 * save the productSearchResponse.products array (~30 deals per run).
 *
 * Pagination: hidden slots load via browser JS/AJAX only — not server-rendered.
 * We reliably get 30 deals per page fetch.
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

    // 50–100% off filter encoded for the discounts-widget param
    private const FILTER = '%2522%257B%255C%2522state%255C%2522%253A%257B%255C%2522rangeRefinementFilters%255C%2522%253A%257B%255C%2522percentOff%255C%2522%253A%257B%255C%2522min%255C%2522%253A50%252C%255C%2522max%255C%2522%253A100%257D%257D%257D%252C%255C%2522version%255C%2522%253A1%257D%2522';

    // Persistent cookie jar — session cookies must carry from page fetch to API fetch
    private string $cookieJar = '';

    public function scrape(): void
    {
        $this->say('=== Amazon Scraper — amazon.com US (50–100% off) ===');

        $this->cookieJar = tempnam(sys_get_temp_dir(), '50off_amz_');

        // ── Source 1: Deals page (widget JSON) ───────────────────────────────
        $entryUrl = 'https://www.amazon.com/deals/?discounts-widget=' . self::FILTER;

        $this->say("→ [1/2] Deals page...");
        $html = $this->fetchAmazonPage($entryUrl);
        $savedTotal = 0;

        if ($html) {
            $this->say('  ✓ ' . number_format(strlen($html)) . ' bytes');
            $data = $this->extractWidgetData($html);
            if ($data) {
                $products  = $data['products'];
                $nextIndex = $data['nextIndex'];
                $widgetId  = $data['widgetId'];
                $pageId    = $data['pageId'];
                $csrfToken = $data['csrfToken'];

                $this->say('  Initial batch: ' . count($products) . ' | nextIndex: ' . $nextIndex);
                $savedTotal = $this->processProducts($products);

                // Pagination via /deals/widget-api
                $pageSize = count($products);
                while ($savedTotal < 90 && $nextIndex > 0 && $pageSize >= 24 && $widgetId && $pageId) {
                    $this->say("  Paginating startIndex={$nextIndex}...");
                    sleep(rand(1, 2));
                    $apiBody = $this->fetchPaginationApi($widgetId, $pageId, $nextIndex, $csrfToken, $html);
                    if (!$apiBody) { $this->say('  ✗ Pagination failed. Stopping.'); break; }
                    $apiData   = json_decode($apiBody, true);
                    $products  = $apiData['productSearchResponse']['products'] ?? [];
                    $nextIndex = (int)($apiData['productSearchResponse']['nextIndex'] ?? 0);
                    $pageSize  = count($products);
                    if (empty($products)) { $this->say('  No more results.'); break; }
                    $this->say('  ✓ Got ' . $pageSize . ' products');
                    $savedTotal += $this->processProducts($products);
                }
            } else {
                $this->say('  ✗ No widget JSON found in deals page');
            }
        } else {
            $this->say('  ✗ Failed to fetch deals page');
        }

        $this->say("  Deals page total: {$savedTotal}");

        // ── Source 2: Search results (50%+ refinement, sorted by discount) ───
        sleep(rand(2, 4));
        $searchSaved = $this->scrapeSearchPages();
        $savedTotal += $searchSaved;

        $this->say("══ Done: {$savedTotal} deals saved ══");
        $this->logResult('success', "amazon.com US 50%+ (saved: {$savedTotal})");
    }

    // ── Search category seeds: different dept refinements for variety ─────────
    // Each is [url, label] — p_8:50- = 50%+ off filter, combined with dept node
    private const SEARCH_SEEDS = [
        // Generic 50%+ off sorted by discount (broad)
        ['https://www.amazon.com/s?rh=p_8%3A50-&s=discount-rank',              'All 50%+'],
        // Electronics
        ['https://www.amazon.com/s?rh=n%3A172282%2Cp_8%3A50-&s=discount-rank', 'Electronics'],
        // Clothing
        ['https://www.amazon.com/s?rh=n%3A7141123011%2Cp_8%3A50-&s=discount-rank', 'Clothing'],
        // Home & Kitchen
        ['https://www.amazon.com/s?rh=n%3A1055398%2Cp_8%3A50-&s=discount-rank', 'Home & Kitchen'],
        // Toys
        ['https://www.amazon.com/s?rh=n%3A165793011%2Cp_8%3A50-&s=discount-rank', 'Toys'],
        // Sports & Outdoors
        ['https://www.amazon.com/s?rh=n%3A3375251%2Cp_8%3A50-&s=discount-rank', 'Sports'],
        // Beauty
        ['https://www.amazon.com/s?rh=n%3A3760911%2Cp_8%3A50-&s=discount-rank', 'Beauty'],
        // Health
        ['https://www.amazon.com/s?rh=n%3A3760901%2Cp_8%3A50-&s=discount-rank', 'Health'],
        // Kitchen
        ['https://www.amazon.com/s?rh=n%3A284507%2Cp_8%3A50-&s=discount-rank', 'Kitchen'],
        // Tools
        ['https://www.amazon.com/s?rh=n%3A228013%2Cp_8%3A50-&s=discount-rank', 'Tools'],
        // Pet Supplies
        ['https://www.amazon.com/s?rh=n%3A2619533011%2Cp_8%3A50-&s=discount-rank', 'Pets'],
        // Video Games
        ['https://www.amazon.com/s?rh=n%3A468642%2Cp_8%3A50-&s=discount-rank', 'Video Games'],
        // Office Products
        ['https://www.amazon.com/s?rh=n%3A1064954%2Cp_8%3A50-&s=discount-rank', 'Office'],
        // Baby
        ['https://www.amazon.com/s?rh=n%3A165796011%2Cp_8%3A50-&s=discount-rank', 'Baby'],
        // Automotive
        ['https://www.amazon.com/s?rh=n%3A15684181%2Cp_8%3A50-&s=discount-rank', 'Automotive'],
    ];

    // ── Scrape search result pages across multiple categories ─────────────────
    private function scrapeSearchPages(): int
    {
        $this->say("→ [2/2] Search results — " . count(self::SEARCH_SEEDS) . " category seeds...");
        $saved = 0;
        $consecutiveBlocks = 0;

        foreach (self::SEARCH_SEEDS as [$baseUrl, $label]) {
            if ($saved >= 200) break;
            if ($consecutiveBlocks >= 2) {
                $this->say("  Too many blocks, stopping early.");
                break;
            }

            $this->say("  Category: {$label}...");
            sleep(rand(3, 6));

            // Fetch 2 pages per category seed for variety
            for ($page = 1; $page <= 2; $page++) {
                $url = $baseUrl . ($page > 1 ? '&page=' . $page : '');
                $html = $this->fetchAmazonPage($url);

                if (!$html) {
                    $this->say("    ✗ Failed [{$label} p{$page}]");
                    $consecutiveBlocks++;
                    break;
                }

                if (stripos($html, 'robot check') !== false || stripos($html, 'captcha') !== false) {
                    $this->say("    ✗ Bot check on [{$label} p{$page}]");
                    $consecutiveBlocks++;
                    break;
                }
                $consecutiveBlocks = 0;

                // Try __NEXT_DATA__ first (richer, faster)
                $pageSaved = $this->parseSearchNextData($html);

                // Fall back to HTML DOM parsing
                if ($pageSaved === 0) {
                    $pageSaved = $this->parseSearchResults($html);
                }

                $this->say("    ✓ [{$label} p{$page}]: {$pageSaved} deals");
                $saved += $pageSaved;

                if ($pageSaved === 0 || $saved >= 200) break;
                if ($page < 2) sleep(rand(2, 4));
            }
        }

        $this->say("  Search total: {$saved}");
        return $saved;
    }

    // ── Extract products from Amazon's __NEXT_DATA__ / embedded JSON ──────────
    private function parseSearchNextData(string $html): int
    {
        // Amazon search pages embed product data in a data-asin-metadata attribute
        // and also in a JSON blob assigned to a script tag
        $saved = 0;

        // Method 1: data-asin-metadata JSON on each product tile
        // This is richer — has BSR, review count, price history
        if (preg_match_all('/data-asin-metadata="([^"]+)"/i', $html, $m)) {
            foreach ($m[1] as $encoded) {
                $json = html_entity_decode($encoded, ENT_QUOTES, 'UTF-8');
                $meta = json_decode($json, true);
                if (!$meta || json_last_error() !== JSON_ERROR_NONE) continue;
                // This metadata doesn't always have pricing — skip, fall through to DOM parser
            }
        }

        // Method 2: Extract from P13n (personalisation) JSON blobs which have pricing
        if (preg_match_all('/"asin"\s*:\s*"([A-Z0-9]{10})"[^}]{0,500}"averageRating"\s*:\s*([\d.]+)[^}]{0,200}"numRatings"\s*:\s*(\d+)/s', $html, $m, PREG_SET_ORDER)) {
            // We have ASIN + ratings but not pricing in this path — skip
        }

        // Method 3: Look for the raw product data array Amazon sometimes embeds
        if (preg_match('/data-cel-widget="search_result_\d+"\s[^>]*data-asin="([^"]+)"[^>]*>/i', $html, $m)) {
            // This confirms the page has server-rendered results — let DOM parser handle it
            return 0;
        }

        return $saved;
    }

    private function parseSearchResults(string $html): int
    {
        $saved  = 0;
        $xpath  = $this->loadDom($html);
        if (!$xpath) return 0;

        // Each search result is a div with data-component-type="s-search-result"
        $results = $xpath->query('//div[@data-component-type="s-search-result" and @data-asin and string-length(@data-asin)>0]');
        if (!$results || $results->length === 0) {
            $this->say("  No s-search-result elements found");
            return 0;
        }

        foreach ($results as $result) {
            if (!($result instanceof \DOMElement)) continue;
            $asin = $result->getAttribute('data-asin');
            if (!$asin || $asin === '') continue;

            // ── Title ───────────────────────────────────────────────────────
            $title = '';
            $titleSpans = $xpath->query('.//h2//span[not(@class) or not(contains(@class,"visually"))]', $result);
            foreach ($titleSpans as $ts) {
                $t = trim($ts->textContent);
                if (strlen($t) > strlen($title)) $title = $t;
            }
            if (!$title || strlen($title) < 5) continue;
            $title = trim(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));

            // ── Sale price (first non-struck price) ─────────────────────────
            $salePriceNode = $xpath->query(
                './/span[contains(@class,"a-price") and not(contains(@class,"a-text-price")) and not(@data-a-strike="true")]//span[@class="a-offscreen"]',
                $result
            )->item(0);
            $sale = $salePriceNode ? $this->parsePrice($salePriceNode->textContent) : 0.0;
            if ($sale <= 0) continue;

            // ── Original / was price (struck through) ───────────────────────
            $origNode = $xpath->query(
                './/span[contains(@class,"a-text-price") or @data-a-strike="true"]//span[@class="a-offscreen"]',
                $result
            )->item(0);
            $original = $origNode ? $this->parsePrice($origNode->textContent) : 0.0;

            // ── Discount % badge ─────────────────────────────────────────────
            $pct = 0;
            $badgeNodes = $xpath->query('.//*[contains(text(),"%") and contains(text(),"off")]', $result);
            foreach ($badgeNodes as $bn) {
                if (preg_match('/(\d+)%\s*off/i', $bn->textContent, $m)) {
                    $pct = max($pct, (int)$m[1]);
                }
            }

            // Reconstruct missing original from pct, or compute pct from both prices
            if ($original <= 0 && $pct >= 50 && $sale > 0) {
                $original = round($sale / (1 - $pct / 100), 2);
            }
            if ($original > 0 && $sale < $original) {
                $pct = max($pct, $this->calcDiscount($original, $sale));
            }

            if ($pct < 50 || $original <= 0 || $original <= $sale) continue;

            // ── Image ────────────────────────────────────────────────────────
            $imageUrl = null;
            $imgNode  = $xpath->query('.//img[contains(@class,"s-image")]', $result)->item(0);
            if ($imgNode instanceof \DOMElement) {
                $src = $imgNode->getAttribute('src');
                if (str_contains($src, 'media-amazon.com') || str_contains($src, 'm.media-amazon')) {
                    // Upgrade to 500px auto-crop
                    $imageUrl = preg_replace('/\._[A-Z0-9,_]+_\.(jpe?g|png|webp)/i', '._AC_SL500_.$1', $src);
                    if (!str_contains($imageUrl, '_AC_SL500_')) {
                        // URL without size token — append directly
                        $imageUrl = preg_replace('/\.(jpe?g|png|webp)$/i', '._AC_SL500_.$1', $src);
                    }
                }
            }

            // ── Rating ───────────────────────────────────────────────────────
            $rating      = null;
            $ratingNode  = $xpath->query('.//span[contains(@class,"a-icon-alt")]', $result)->item(0);
            if ($ratingNode && preg_match('/^([\d.]+)\s+out/i', $ratingNode->textContent, $m)) {
                $rating = (float)$m[1];
            }

            // ── Review count ─────────────────────────────────────────────────
            $reviewCount  = 0;
            $reviewNode   = $xpath->query('.//span[@class="a-size-base s-underline-text"]', $result)->item(0);
            if (!$reviewNode) {
                $reviewNode = $xpath->query('.//a[contains(@class,"s-link-style")]//span[contains(@class,"a-size-base")]', $result)->item(0);
            }
            if ($reviewNode) {
                $reviewCount = (int)str_replace([',', '.', ' '], '', $reviewNode->textContent);
            }

            // ── URLs ─────────────────────────────────────────────────────────
            $productUrl   = "https://www.amazon.com/dp/{$asin}";
            $affiliateUrl = "https://www.amazon.com/dp/{$asin}?tag={$this->tag}";

            if ($this->saveDeal([
                'title'          => $title,
                'original_price' => $original,
                'sale_price'     => $sale,
                'discount_pct'   => $pct,
                'image_url'      => $imageUrl,
                'product_url'    => $productUrl,
                'affiliate_url'  => $affiliateUrl,
                'category'       => $this->mapCategory($title),
                'rating'         => $rating,
                'review_count'   => $reviewCount,
            ])) {
                $saved++;
            }
        }

        return $saved;
    }

    // ── Extract widget JSON from page HTML ────────────────────────────────────
    private function extractWidgetData(string $html): array|false
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

            // New format: productSearchResponse.products
            $psr = $decoded['productSearchResponse'] ?? null;
            if ($psr && !empty($psr['products'])) {
                $this->say("  Using slot: {$slot} (discount-asin-grid) ✓");
                return [
                    'products'  => $psr['products'],
                    'nextIndex' => (int)($psr['nextIndex'] ?? 0),
                    'widgetId'  => $decoded['widgetId'] ?? null,
                    'pageId'    => $decoded['renderingContext']['pageId'] ?? null,
                    'csrfToken' => $decoded['csrfToken'] ?? null,
                ];
            }

            // Legacy format: prefetchedData.entity.rankedPromotions (fallback)
            if (isset($decoded['prefetchedData']['entity']['rankedPromotions'])) {
                $this->say("  Using slot: {$slot} (legacy rankedPromotions) ✓");
                $entity = $decoded['prefetchedData']['entity'];
                return [
                    'products'  => $entity['rankedPromotions'],
                    'nextIndex' => (int)($entity['nextIndex'] ?? 0),
                    'widgetId'  => null,
                    'pageId'    => null,
                    'csrfToken' => null,
                    'legacy'    => true,
                ];
            }
        }
        $this->say('  No slot with product data found');
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

    // ── Process products (new format) ─────────────────────────────────────────
    private function processProducts(array $products): int
    {
        $saved = 0;
        foreach ($products as $prod) {
            // Detect format
            if (isset($prod['product'])) {
                // Legacy format wraps in product.entity
                $result = $this->processLegacyPromotion($prod);
            } else {
                $result = $this->processNewProduct($prod);
            }
            if ($result) $saved++;
        }
        return $saved;
    }

    private function processNewProduct(array $prod): bool
    {
        $asin = $prod['asin'] ?? null;
        if (!$asin) return false;

        $title = $prod['title'] ?? null;
        if (!$title) return false;
        $title = trim(html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8'));

        // Pricing
        $sale     = (float)($prod['price']['priceToPay']['price'] ?? 0);
        $original = (float)($prod['price']['basisPrice']['price'] ?? 0);

        if ($sale <= 0) return false;

        // Try to get pct from dealBadge text (e.g. "51% off")
        $pct = 0;
        $badgeText = $prod['dealBadge']['label']['content']['fragments'][0]['text'] ?? '';
        if (preg_match('/(\d+)%/', $badgeText, $m)) {
            $pct = (int)$m[1];
        }

        if ($original <= 0 && $pct > 0) {
            $original = round($sale / (1 - $pct / 100), 2);
        }
        if ($original > 0 && $sale < $original) {
            $calcPct = $this->calcDiscount($original, $sale);
            $pct = max($pct, $calcPct);
        }

        if ($pct < 50 || $original <= 0) return false;

        // Image
        $physicalId = $prod['image']['hiRes']['physicalId']
                   ?? $prod['image']['physicalId']
                   ?? $prod['image']['lowRes']['physicalId']
                   ?? null;
        $imageUrl = $physicalId ? $this->buildAmazonImageUrl($physicalId) : null;

        // URL
        $relPath    = $prod['link'] ?? "/dp/{$asin}";
        $relPath    = preg_replace('|^https?://[^/]+|', '', $relPath);
        $productUrl   = 'https://www.amazon.com' . $relPath;
        $affiliateUrl = $productUrl . (str_contains($productUrl, '?') ? '&' : '?') . 'tag=' . $this->tag;

        // Category
        $catRaw = $prod['productCategory']['displayName'] ?? '';

        return $this->saveDeal([
            'title'          => $title,
            'original_price' => $original,
            'sale_price'     => $sale,
            'discount_pct'   => $pct,
            'image_url'      => $imageUrl,
            'product_url'    => $productUrl,
            'affiliate_url'  => $affiliateUrl,
            'category'       => $this->mapCategory($catRaw ?: $title),
            'rating'         => null,
            'review_count'   => 0,
        ]);
    }

    private function processLegacyPromotion(array $promo): bool
    {
        $product = $promo['product']['entity'] ?? null;
        if (!$product) return false;

        $asin = $product['asin'] ?? null;
        if (!$asin) return false;

        $title = $product['title']['entity']['displayString']
              ?? $product['title']['entity']['shortDisplayString']
              ?? null;
        if (!$title) return false;
        $title = trim(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));

        $pricing = $this->extractLegacyPricing($product['buyingOptions'] ?? []);
        if (!$pricing) return false;

        $imageUrl = $this->extractLegacyBestImage($product);

        $relPath    = $product['links']['entity']['viewOnAmazon']['url'] ?? "/dp/{$asin}";
        $relPath    = preg_replace('|^https?://[^/]+|', '', $relPath);
        $productUrl = 'https://www.amazon.com' . $relPath;
        $affiliateUrl = $productUrl . (str_contains($productUrl, '?') ? '&' : '?') . 'tag=' . $this->tag;

        $catRaw = $product['productCategory']['entity']['websiteDisplayGroup']['displayString']
               ?? $product['productCategory']['entity']['glProductGroup']['symbol']
               ?? '';

        return $this->saveDeal([
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
        ]);
    }

    // ── Image ─────────────────────────────────────────────────────────────────
    private function buildAmazonImageUrl(string $physicalId): string
    {
        // Strip any existing size tokens, apply auto-crop 500px
        $clean = preg_replace('/\._[A-Z0-9,_]+_$/', '', $physicalId);
        return "https://m.media-amazon.com/images/I/{$clean}._AC_SL500_.jpg";
    }

    private function extractLegacyBestImage(array $product): ?string
    {
        $images = $product['productImages']['entity']['images'] ?? [];
        foreach ([['MAIN','hiRes'],['MAIN','lowRes'],['PT01','hiRes'],['PT01','lowRes']] as [$variant, $res]) {
            foreach ($images as $img) {
                if (($img['variant'] ?? '') !== $variant) continue;
                $id = $img[$res]['physicalId'] ?? null;
                if ($id) return $this->buildAmazonImageUrl($id);
            }
        }
        foreach ($images as $img) {
            $id = $img['hiRes']['physicalId'] ?? $img['lowRes']['physicalId'] ?? null;
            if ($id) return $this->buildAmazonImageUrl($id);
        }
        return null;
    }

    // ── Legacy price extraction ───────────────────────────────────────────────
    private function extractLegacyPricing(array $buyingOptions): ?array
    {
        $best = null;
        foreach ($buyingOptions as $option) {
            $pe = $option['price']['entity'] ?? null;
            if (!$pe) continue;

            $sale     = $this->parseLegacyMoney($pe['priceToPay'] ?? null);
            $original = $this->parseLegacyMoney($pe['basisPrice'] ?? null);
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

    private function parseLegacyMoney(mixed $node): ?float
    {
        if (!is_array($node)) return null;
        $amount = $node['moneyValueOrRange']['value']['amount']
               ?? $node['moneyValueOrRange']['value']['convertedFrom']['amount']
               ?? $node['amount']
               ?? null;
        return $amount !== null ? (float)$amount : null;
    }

    // ── Pagination API ────────────────────────────────────────────────────────
    private function fetchPaginationApi(
        string $widgetId, string $pageId, int $startIndex,
        ?string $csrfToken, string $originalHtml
    ): string|false {
        // Amazon's widget API for loading more deals
        $url = 'https://www.amazon.com/deals/widget-api/getProducts'
             . '?widgetId=' . urlencode($widgetId)
             . '&pageId=' . urlencode($pageId)
             . '&startIndex=' . $startIndex
             . '&discounts-widget=' . self::FILTER;

        $headers = [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9',
            'sec-ch-ua: "Google Chrome";v="125"',
            'sec-ch-ua-mobile: ?0',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-origin',
            'X-Requested-With: XMLHttpRequest',
        ];
        if ($csrfToken) {
            $headers[] = 'anti-csrftoken-a2z: ' . $csrfToken;
        }

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
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);
        if ($code >= 400) { $this->say("  API HTTP $code"); return false; }
        return $body ?: false;
    }

    // ── HTML page fetch ───────────────────────────────────────────────────────
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
        unset($ch);
        if ($err)         { $this->say("cURL error: $err"); return false; }
        if ($code >= 400) { $this->say("HTTP $code"); return false; }
        return $body ?: false;
    }
}
