<?php
/**
 * ZapposScraper.php — zappos.com sale items (50%+ off)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * HOW IT WORKS:
 * ─────────────
 * Zappos (owned by Amazon) uses the same frontend as 6pm — window.__INITIAL_STATE__
 * with a "products":[...] array containing string prices ("$43.99"), percentOff ("84%"),
 * and msaImageId pointing to Amazon's CDN.
 *
 * Parsing is identical to SixPmScraper — extract products[] with balanced-brace parser.
 *
 * AFFILIATE:
 * ──────────
 * Zappos affiliate program is through Amazon Associates.
 * Product URLs use direct zappos.com links.
 */
require_once __DIR__ . '/BaseScraper.php';

class ZapposScraper extends BaseScraper
{
    protected string $store = 'zappos';
    private   int    $limit = 100;

    private const PAGES = [
        'https://www.zappos.com/c/shoes?s=PercentOff/desc&pge=0',
        'https://www.zappos.com/c/shoes?s=PercentOff/desc&pge=1',
        'https://www.zappos.com/c/clothing?s=PercentOff/desc&pge=0',
        'https://www.zappos.com/c/clothing?s=PercentOff/desc&pge=1',
        'https://www.zappos.com/c/sandals?s=PercentOff/desc&pge=0',
        'https://www.zappos.com/c/sneakers?s=PercentOff/desc&pge=0',
        'https://www.zappos.com/c/boots?s=PercentOff/desc&pge=0',
        'https://www.zappos.com/c/jackets?s=PercentOff/desc&pge=0',
        'https://www.zappos.com/c/dresses?s=PercentOff/desc&pge=0',
    ];

    public function scrape(): void
    {
        $this->say('=== Zappos Scraper — zappos.com (50%+ off) ===');
        $savedTotal = 0;

        foreach (self::PAGES as $url) {
            if ($savedTotal >= $this->limit) break;

            $label = parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);
            $this->say("→ {$label}...");
            sleep(rand(2, 4));

            $html = $this->fetch($url, [
                'Accept: text/html,application/xhtml+xml,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ], 'https://www.zappos.com/');

            if (!$html || strlen($html) < 5000) {
                $this->say("  ✗ No response or blocked.");
                continue;
            }

            $this->say("  ✓ " . number_format(strlen($html)) . " bytes");
            $n = $this->parseProducts($html, 'zappos.com');
            $savedTotal += $n;
            $this->say("  Saved {$n} deals");
        }

        $this->say("══ Done: {$savedTotal} deals saved ══");
        $this->logResult($savedTotal > 0 ? 'success' : 'warning', "zappos.com 50%+ (saved: {$savedTotal})");
    }

    private function parseProducts(string $html, string $domain): int
    {
        // Find "products":[{...}] in __INITIAL_STATE__ using balanced-brace extraction
        $needle = '"products":';
        $pos = strpos($html, $needle);
        if ($pos === false) { $this->say("  No products key found"); return 0; }

        $arrStart = strpos($html, '[', $pos + strlen($needle));
        if ($arrStart === false) return 0;

        $depth = 0; $i = $arrStart; $len = strlen($html);
        while ($i < $len) {
            $c = $html[$i];
            if ($c === '[' || $c === '{') $depth++;
            elseif ($c === ']' || $c === '}') { $depth--; if ($depth === 0) break; }
            $i++;
        }

        $products = json_decode(substr($html, $arrStart, $i - $arrStart + 1), true);
        if (!$products || json_last_error() !== JSON_ERROR_NONE || !isset($products[0]['productId'])) {
            $this->say("  Could not parse products array");
            return 0;
        }

        $this->say("  Found " . count($products) . " products");
        $saved = 0;

        foreach ($products as $prod) {
            if (!is_array($prod)) continue;
            if (($prod['onSale'] ?? '') !== 'true') continue;

            $brand = $prod['brandName'] ?? '';
            $name  = $prod['productName'] ?? $prod['name'] ?? '';
            $title = trim($brand ? "{$brand} {$name}" : $name);
            if (!$title) continue;

            // Prices are strings: "$43.99", "$275.00"
            $sale     = $this->parsePrice((string)($prod['price']         ?? '0'));
            $original = $this->parsePrice((string)($prod['originalPrice'] ?? '0'));
            $pctRaw   = $prod['percentOff'] ?? '0';
            $pct      = (int)preg_replace('/[^0-9]/', '', (string)$pctRaw);

            if ($sale <= 0) continue;
            if ($original <= 0 && $pct > 0) $original = round($sale / (1 - $pct / 100), 2);
            if ($original > $sale) $pct = max($pct, $this->calcDiscount($original, $sale));
            if ($pct < 50 || $original <= 0 || $original <= $sale) continue;

            // Image via Amazon CDN
            $imageUrl = null;
            if (!empty($prod['msaImageId'])) {
                $imageUrl = 'https://m.media-amazon.com/images/I/' . $prod['msaImageId'] . '._AC_SL500_.jpg';
            }

            // Product URL
            $relUrl = $prod['productUrl'] ?? null;
            if (!$relUrl) continue;
            $productUrl = str_starts_with($relUrl, 'http') ? $relUrl : 'https://www.' . $domain . $relUrl;
            // Strip tracking params, keep clean URL
            $productUrl = preg_replace('/\?pf_rd_[^&]+(&pf_rd_[^&]+)*/', '', $productUrl);

            $rating      = isset($prod['reviewAvgRating'])  ? (float)$prod['reviewAvgRating']  : null;
            $reviewCount = (int)($prod['reviewCount'] ?? $prod['numReviews'] ?? 0);

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
            ])) $saved++;
        }

        return $saved;
    }
}
