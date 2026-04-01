<?php
/**
 * CostcoScraper.php — costco.com online deals (50%+ off)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * HOW IT WORKS:
 * ─────────────
 * Fetches Costco's online deals and clearance pages, then parses product
 * tiles from the server-rendered HTML. Each product tile contains the item
 * number, title, current price, and original/was price for discount calc.
 *
 * Costco does not require login to view online deals, making HTML scraping
 * relatively reliable. Products without both prices are skipped.
 *
 * AFFILIATE:
 * ──────────
 * Costco does not have a public affiliate program. Product URLs link directly
 * to costco.com product pages.
 */
require_once __DIR__ . '/BaseScraper.php';

class CostcoScraper extends BaseScraper
{
    protected string $store = 'costco';
    private   int    $limit = 50;

    private const PAGES = [
        'https://www.costco.com/online-deals.html',
        'https://www.costco.com/clearance.html',
        'https://www.costco.com/electronics.html',
        'https://www.costco.com/home-goods-sale.html',
    ];

    public function scrape(): void
    {
        $this->say('=== Costco Scraper — costco.com (50%+ off) ===');
        $savedTotal = 0;

        foreach (self::PAGES as $url) {
            if ($savedTotal >= $this->limit) break;

            $slug = basename(parse_url($url, PHP_URL_PATH), '.html');
            $this->say("→ {$slug}...");
            sleep(rand(2, 4));

            $html = $this->fetch(
                $url,
                [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                    'Sec-Fetch-Dest: document',
                    'Sec-Fetch-Mode: navigate',
                    'Upgrade-Insecure-Requests: 1',
                ],
                'https://www.google.com/'
            );

            if (!$html) {
                $this->say("  ✗ No response. Skipping.");
                continue;
            }

            $this->say("  ✓ " . number_format(strlen($html)) . " bytes");

            if (strlen($html) < 5000 || stripos($html, 'Access Denied') !== false) {
                $this->say("  ✗ Blocked or empty. Skipping.");
                continue;
            }

            // Try embedded JSON first
            $pageSaved = $this->parseEmbeddedJson($html);

            // Fall back to HTML product tile parsing
            if ($pageSaved === 0) {
                $pageSaved = $this->parseHtmlTiles($html);
            }

            $savedTotal += $pageSaved;
            $this->say("  Saved {$pageSaved} deals from this page");
        }

        $this->say("══ Done: {$savedTotal} deals saved ══");
        $this->logResult($savedTotal > 0 ? 'success' : 'warning', "costco.com 50%+ (saved: {$savedTotal})");
    }

    // ── Try Costco embedded JSON (they sometimes embed product data) ──────────
    private function parseEmbeddedJson(string $html): int
    {
        // Costco pages may embed product arrays in various script vars
        $patterns = [
            '/window\.__INITIAL_STATE__\s*=\s*(\{.+?\});?\s*(?:<\/script>|window\.)/s',
            '/window\._sharedData\s*=\s*(\{.+?\});?\s*(?:<\/script>|window\.)/s',
            '/"products"\s*:\s*(\[.+?\])\s*(?:,|\})/s',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $html, $m)) continue;
            $data = json_decode($m[1], true);
            if (!$data || json_last_error() !== JSON_ERROR_NONE) continue;

            $products = is_array($data) ? $data
                     : ($data['productList']['products'] ?? $data['products'] ?? []);
            if (!empty($products)) {
                $this->say("  Found " . count($products) . " products in embedded JSON");
                return $this->processJsonProducts($products);
            }
        }
        return 0;
    }

    private function processJsonProducts(array $products): int
    {
        $saved = 0;
        foreach ($products as $prod) {
            $title    = $prod['name'] ?? $prod['description'] ?? null;
            if (!$title) continue;
            $title    = trim(html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8'));

            $sale     = (float)($prod['salePrice'] ?? $prod['currentPrice'] ?? $prod['price'] ?? 0);
            $original = (float)($prod['regularPrice'] ?? $prod['listPrice'] ?? $prod['originalPrice'] ?? 0);
            if ($sale <= 0 || $original <= 0 || $original <= $sale) continue;

            $pct = $this->calcDiscount($original, $sale);
            if ($pct < 50) continue;

            $itemNum    = $prod['itemNumber'] ?? $prod['id'] ?? $prod['productId'] ?? '';
            $imageUrl   = $prod['image'] ?? $prod['thumbnailImage'] ?? null;
            $productUrl = $itemNum
                ? "https://www.costco.com/p.{$itemNum}.html"
                : ($prod['url'] ?? null);
            if (!$productUrl) continue;

            if (!str_starts_with($productUrl, 'http')) {
                $productUrl = 'https://www.costco.com' . $productUrl;
            }

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

    // ── HTML tile parsing ─────────────────────────────────────────────────────
    private function parseHtmlTiles(string $html): int
    {
        $saved = 0;
        $xpath = $this->loadDom($html);
        if (!$xpath) return 0;

        // Costco product tiles: <div class="product-tile-set"> or automation attrs
        $tiles = $xpath->query(
            '//div[contains(@class,"product") and @automation-id] | ' .
            '//div[contains(@class,"product-tile")] | ' .
            '//div[@data-page-type="productList"]//div[contains(@class,"thumbnail")]'
        );

        if (!$tiles || $tiles->length === 0) {
            $this->say("  No product tile elements found in HTML");
            return 0;
        }

        $this->say("  Found {$tiles->length} product tile elements");

        foreach ($tiles as $tile) {
            if (!($tile instanceof \DOMElement)) continue;

            // Title
            $titleNode = $xpath->query(
                './/span[@automation-id="productDescriptionTruncated"] | ' .
                './/span[contains(@class,"description")] | ' .
                './/a[contains(@class,"product") and @title]',
                $tile
            )->item(0);
            $title = '';
            if ($titleNode instanceof \DOMElement && $titleNode->hasAttribute('title')) {
                $title = $titleNode->getAttribute('title');
            } elseif ($titleNode) {
                $title = trim($titleNode->textContent);
            }
            if (!$title) continue;
            $title = trim(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));

            // Sale price
            $salePriceNode = $xpath->query(
                './/*[@automation-id="sale-price"] | ' .
                './/*[contains(@class,"price") and not(contains(@class,"was"))]//span[contains(text(),"$")]',
                $tile
            )->item(0);
            $sale = $salePriceNode ? $this->parsePrice($salePriceNode->textContent) : 0.0;
            if ($sale <= 0) continue;

            // Was price
            $wasPriceNode = $xpath->query(
                './/*[@automation-id="was-price"] | .//*[contains(@class,"was-price")] | .//*[contains(text(),"Was $")]',
                $tile
            )->item(0);
            $original = $wasPriceNode ? $this->parsePrice($wasPriceNode->textContent) : 0.0;
            if ($original <= 0 || $original <= $sale) continue;

            $pct = $this->calcDiscount($original, $sale);
            if ($pct < 50) continue;

            // Product URL
            $linkNode   = $xpath->query('.//a[@href]', $tile)->item(0);
            $href       = ($linkNode instanceof \DOMElement) ? $linkNode->getAttribute('href') : '';
            if (!$href) continue;
            $productUrl = str_starts_with($href, 'http') ? $href : 'https://www.costco.com' . $href;

            // Image
            $imgNode  = $xpath->query('.//img[@src]', $tile)->item(0);
            $imageUrl = ($imgNode instanceof \DOMElement) ? $imgNode->getAttribute('src') : null;
            if ($imageUrl && !str_starts_with($imageUrl, 'http')) $imageUrl = null;

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
