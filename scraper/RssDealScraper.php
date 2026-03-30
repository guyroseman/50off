<?php
/**
 * RssDealScraper.php — Aggregates deals from public deal blog RSS feeds
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * STRATEGY:
 * ─────────
 * Deal blogs (9to5Toys, TechBargains, Ben's Bargains, Hip2Save) publish
 * curated deals from ALL major retailers (Amazon, Walmart, Target, Best Buy,
 * etc.) via public RSS feeds. These feeds are designed for consumption by
 * feed readers and are NOT blocked by datacenter IPs.
 *
 * For each RSS item we:
 * 1. Extract price info from the title/description text (regex patterns)
 * 2. Detect the retailer from embedded product links
 * 3. Extract the direct product URL from the blog post HTML
 * 4. Only save deals with 50%+ discount
 *
 * This bypasses the IP-blocking issue with Walmart/Target since we're
 * fetching from deal blogs, not the retailers themselves.
 */
require_once __DIR__ . '/BaseScraper.php';

class RssDealScraper extends BaseScraper {
    protected string $store = 'other';

    private array $feeds = [
        [
            'url'   => 'https://9to5toys.com/feed/',
            'label' => '9to5Toys',
        ],
        [
            'url'   => 'https://feeds.feedburner.com/BensBargains',
            'label' => 'BensBargains',
        ],
        [
            'url'   => 'https://www.hip2save.com/feed/',
            'label' => 'Hip2Save',
        ],
    ];

    // Map of retailer domains to store names
    private array $storeMap = [
        'amazon.com'    => 'amazon',
        'amzn.to'       => 'amazon',
        'walmart.com'   => 'walmart',
        'target.com'    => 'target',
        'bestbuy.com'   => 'bestbuy',
        'costco.com'    => 'costco',
        'ebay.com'      => 'ebay',
        'homedepot.com' => 'homedepot',
        'lowes.com'     => 'lowes',
        'macys.com'     => 'macys',
        'kohls.com'     => 'kohls',
        'nordstrom.com' => 'nordstrom',
        'newegg.com'    => 'newegg',
        'bhphotovideo.com' => 'bhphoto',
    ];

    public function scrape(): void {
        $this->say("=== RSS Deal Aggregator ===");

        $totalSaved = 0;
        foreach ($this->feeds as $feed) {
            $this->say("Feed: {$feed['label']}...");
            $xml = $this->fetchRss($feed['url']);
            if (!$xml) {
                $this->say("  → No response or invalid XML");
                continue;
            }

            $items = $xml->channel->item ?? $xml->entry ?? [];
            $count = 0;
            $saved = 0;
            foreach ($items as $item) {
                $count++;
                if ($this->processRssItem($item)) $saved++;
            }
            $this->say("  → Processed {$count} items, saved {$saved} deals");
            $totalSaved += $saved;
            sleep(rand(1, 3));
        }

        $this->say("Total RSS deals saved: {$totalSaved}");
        $this->logResult('success', "RSS Aggregator (saved: {$totalSaved})");
    }

    // ── Process a single RSS item ─────────────────────────────────────────────
    private function processRssItem(\SimpleXMLElement $item): bool {
        $title = trim((string)($item->title ?? ''));
        $link  = trim((string)($item->link ?? ''));
        $descHtml = (string)($item->description ?? $item->children('content', true)->encoded ?? '');
        $descText = trim(strip_tags(html_entity_decode($descHtml, ENT_QUOTES, 'UTF-8')));

        if (!$title) return false;

        $combined = $title . ' ' . $descText;

        // ── Extract prices ──────────────────────────────────────────────────
        $prices = $this->extractPrices($combined);
        if (!$prices || $prices['pct'] < 50) return false;

        // ── Find the actual product URL from description HTML ───────────────
        $productUrl = $this->extractProductUrl($descHtml, $link);
        if (!$productUrl) return false;

        // ── Detect retailer ─────────────────────────────────────────────────
        $detectedStore = $this->detectStore($productUrl, $combined);
        $this->store = $detectedStore;

        // ── Extract image from description HTML ─────────────────────────────
        $image = null;
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $descHtml, $imgM)) {
            $img = $imgM[1];
            // Skip tracking pixels and tiny images
            if (!str_contains($img, 'pixel') && !str_contains($img, '1x1') && !str_contains($img, 'gravatar')) {
                $image = $img;
            }
        }

        return $this->saveDeal([
            'title'          => substr($title, 0, 500),
            'description'    => substr($descText, 0, 500) ?: null,
            'original_price' => $prices['orig'],
            'sale_price'     => $prices['sale'],
            'discount_pct'   => $prices['pct'],
            'image_url'      => $image,
            'product_url'    => substr($productUrl, 0, 1000),
            'affiliate_url'  => substr($productUrl, 0, 1000),
            'category'       => $this->mapCategory($title),
            'rating'         => null,
            'review_count'   => 0,
        ]);
    }

    // ── Price extraction from deal text ───────────────────────────────────────
    // Parses patterns like:
    //   "$169.99 (Reg. $349)"   → sale=169.99, orig=349
    //   "50% off"               → pct=50
    //   "$200 off (was $400)"   → sale=200, orig=400
    //   "now $25, normally $60" → sale=25, orig=60
    private function extractPrices(string $text): ?array {
        $sale = 0.0;
        $orig = 0.0;
        $pct  = 0;
        $savings = 0.0;

        // Find all dollar amounts in the text
        preg_match_all('/\$(\d{1,6}(?:,\d{3})*(?:\.\d{2})?)/', $text, $allPrices);
        $dollarAmounts = array_map(fn($p) => (float)str_replace(',', '', $p), $allPrices[1] ?? []);

        // Look for explicit discount percentage
        if (preg_match('/(\d{1,3})%\s*(?:off|discount|savings)/i', $text, $pm)) {
            $pct = (int)$pm[1];
        }

        // Look for explicit original/regular price
        if (preg_match('/(?:Reg\.?|was|normally|list|orig\.?|retail|MSRP)\s*\$(\d{1,6}(?:,\d{3})*(?:\.\d{2})?)/i', $text, $origM)) {
            $orig = (float)str_replace(',', '', $origM[1]);
        }

        // Look for savings amount
        if (preg_match('/(?:save|savings?|off)\s*\$(\d{1,6}(?:,\d{3})*(?:\.\d{2})?)/i', $text, $savM)) {
            $savings = (float)str_replace(',', '', $savM[1]);
        }

        // Look for "now $X" or leading "$X" as sale price
        if (preg_match('/(?:now|only|just|price:?|for)\s*\$(\d{1,6}(?:,\d{3})*(?:\.\d{2})?)/i', $text, $saleM)) {
            $sale = (float)str_replace(',', '', $saleM[1]);
        }

        // If no explicit sale price, use the first (usually lowest) dollar amount
        if ($sale <= 0 && count($dollarAmounts) >= 1) {
            // If we have 2+ prices, the lower one is usually the sale price
            if (count($dollarAmounts) >= 2) {
                sort($dollarAmounts);
                $sale = $dollarAmounts[0];
                if ($orig <= 0) $orig = end($dollarAmounts);
            } else {
                $sale = $dollarAmounts[0];
            }
        }

        // Derive original from savings
        if ($orig <= 0 && $sale > 0 && $savings > 0) {
            $orig = $sale + $savings;
        }

        // Derive original from percentage
        if ($orig <= 0 && $sale > 0 && $pct >= 50) {
            $orig = round($sale / (1 - $pct / 100), 2);
        }

        // Calculate percentage if not found
        if ($pct < 50 && $orig > 0 && $sale > 0 && $sale < $orig) {
            $pct = (int)round(($orig - $sale) / $orig * 100);
        }

        // Validate
        if ($sale <= 0 || $pct < 50) return null;
        if ($orig <= $sale) return null;

        return ['sale' => $sale, 'orig' => $orig, 'pct' => $pct];
    }

    // ── Extract the product URL from blog post description HTML ───────────────
    // Blog posts link to the retailer's product page within their description.
    private function extractProductUrl(string $html, string $fallbackLink): ?string {
        // Find all links in the description
        preg_match_all('/href=["\']([^"\']+)["\']/', $html, $linkMatches);
        $links = $linkMatches[1] ?? [];

        // Prioritize links that point to known retailers
        foreach ($links as $href) {
            foreach ($this->storeMap as $domain => $_) {
                if (str_contains($href, $domain)) {
                    return $href;
                }
            }
        }

        // Fall back to the blog post URL itself
        return $fallbackLink ?: null;
    }

    // ── Detect the retailer from URL and text ────────────────────────────────
    private function detectStore(string $url, string $text): string {
        // Check URL domain
        foreach ($this->storeMap as $domain => $store) {
            if (str_contains($url, $domain)) return $store;
        }

        // Check text for store mentions
        $textLower = strtolower($text);
        $textPatterns = [
            'amazon'     => 'amazon',
            'walmart'    => 'walmart',
            'target'     => 'target',
            'best buy'   => 'bestbuy',
            'costco'     => 'costco',
            'home depot' => 'homedepot',
            'lowe\'s'    => 'lowes',
            'macy\'s'    => 'macys',
            'kohl\'s'    => 'kohls',
            'newegg'     => 'newegg',
            'b&h'        => 'bhphoto',
        ];
        foreach ($textPatterns as $pattern => $store) {
            if (str_contains($textLower, $pattern)) return $store;
        }

        return 'other';
    }
}
