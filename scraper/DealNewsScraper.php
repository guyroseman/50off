<?php
/**
 * DealNewsScraper.php
 *
 * DealNews.com is a professional deal-curation site.
 * Editors manually verify every deal — much higher accuracy than auto-scrapers.
 * They have public RSS feeds covering all major US retailers.
 *
 * Key feeds:
 *  - Top Deals 50% off: https://www.dealnews.com/rss/d3.xml
 *  - Electronics:       https://www.dealnews.com/rss/Electronics.xml
 *  - etc.
 */
require_once __DIR__ . '/BaseScraper.php';

class DealNewsScraper extends BaseScraper {
    protected string $store = 'other';

    private array $feeds = [
        // d3 = "Deals worth sharing" = typically 50%+ off
        ['url' => 'https://www.dealnews.com/rss/d3.xml',                     'cat' => null],
        ['url' => 'https://www.dealnews.com/rss/Electronics.xml',            'cat' => 'electronics'],
        ['url' => 'https://www.dealnews.com/rss/Computers.xml',              'cat' => 'electronics'],
        ['url' => 'https://www.dealnews.com/rss/Home-Garden.xml',            'cat' => 'home'],
        ['url' => 'https://www.dealnews.com/rss/Clothing-Accessories.xml',   'cat' => 'clothing'],
        ['url' => 'https://www.dealnews.com/rss/Toys-Games.xml',             'cat' => 'toys'],
        ['url' => 'https://www.dealnews.com/rss/Sports-Fitness.xml',         'cat' => 'sports'],
        ['url' => 'https://www.dealnews.com/rss/Health-Beauty.xml',          'cat' => 'health'],
        ['url' => 'https://www.dealnews.com/rss/Kitchen.xml',                'cat' => 'kitchen'],
    ];

    public function scrape(): void {
        $this->say("Starting DealNews scrape...");

        foreach ($this->feeds as $feed) {
            $label = basename($feed['url'], '.xml');
            $this->say("Feed: $label");

            $xml = $this->fetchRss($feed['url']);
            if (!$xml) { $this->say("  → No response"); sleep(2); continue; }

            $count = 0;
            foreach ($xml->channel->item ?? [] as $item) {
                if ($this->processItem($item, $feed['cat'])) $count++;
            }
            $this->say("  → {$count} deals");
            sleep(1);
        }

        $this->logResult('success', 'DealNews RSS feeds');
    }

    private function processItem(\SimpleXMLElement $item, ?string $feedCat): bool {
        $title    = trim((string)($item->title       ?? ''));
        $link     = trim((string)($item->link        ?? ''));
        $desc     = (string)($item->description      ?? '');

        if (!$title || !$link) return false;

        $fullText = $title . ' ' . strip_tags($desc);

        // ── Parse prices ────────────────────────────────────────────────────
        // DealNews format: "Brand Product, $X.XX at StoreName"
        // or "X% off Brand Product at Store"
        $sale = $orig = 0.0;
        $pct  = 0;

        // Percentage
        if (preg_match('/(\d+)\s*%\s*off/i', $fullText, $m))     $pct = (int)$m[1];

        // Sale price: "$X.xx at Store" (most common DealNews format)
        if (preg_match('/\$\s*([\d,]+\.?\d{0,2})\s+at\s+/i', $title, $m))
            $sale = (float)str_replace(',', '', $m[1]);

        // "Was $X" / "Reg. $X"
        if (preg_match('/(?:was|reg(?:ular)?|retails?\s+for|list\s+price)[:\s]+\$\s*([\d,]+\.?\d{0,2})/i', $fullText, $m))
            $orig = (float)str_replace(',', '', $m[1]);

        // "Save $X" → orig = sale + savings
        if ($orig <= 0 && $sale > 0) {
            if (preg_match('/save\s+\$\s*([\d,]+\.?\d{0,2})/i', $fullText, $m)) {
                $savings = (float)str_replace(',', '', $m[1]);
                if ($savings > 0) $orig = $sale + $savings;
            }
        }

        // Fallback: collect all price mentions
        if ($sale <= 0 || $orig <= 0) {
            preg_match_all('/\$\s*([\d,]+\.?\d{0,2})/', $fullText, $all);
            $prices = array_map(fn($p) => (float)str_replace(',', '', $p), $all[1] ?? []);
            $prices = array_values(array_filter($prices, fn($p) => $p >= 1 && $p <= 50000));
            sort($prices);
            if (count($prices) >= 2) {
                if ($sale <= 0) $sale = $prices[0];
                if ($orig <= 0) $orig = $prices[count($prices) - 1];
            } elseif (count($prices) === 1 && $pct >= 50) {
                $sale = $prices[0];
                $orig = round($sale / (1 - $pct / 100), 2);
            }
        }

        // Reconstruct orig from pct if still missing
        if ($orig <= 0 && $pct >= 50 && $sale > 0)
            $orig = round($sale / (1 - $pct / 100), 2);

        // Calc pct
        if ($pct < 50 && $sale > 0 && $orig > $sale)
            $pct = $this->calcDiscount($orig, $sale);

        if ($pct < 50 || $sale <= 0 || $orig <= $sale) return false;

        // ── Extract image ────────────────────────────────────────────────────
        $image = $this->extractDealNewsImage($desc);

        // ── Detect store ─────────────────────────────────────────────────────
        $store = $this->detectStore($title . ' ' . $link . ' ' . $desc);

        // ── Clean title: "Brand Product, $X.xx at Store" → "Brand Product" ──
        $cleanTitle = preg_replace('/,?\s+\$[\d,]+\.?\d*\s+at\s+\w+.*$/i', '', $title);
        $cleanTitle = preg_replace('/\s*[—–]\s*.*$/u', '', $cleanTitle);
        $cleanTitle = trim(strip_tags($cleanTitle));

        $this->store = $store;
        return $this->saveDeal([
            'title'          => $cleanTitle ?: $title,
            'description'    => $this->cleanDesc($desc),
            'original_price' => $orig,
            'sale_price'     => $sale,
            'discount_pct'   => $pct,
            'image_url'      => $image,
            'product_url'    => $link,
            'affiliate_url'  => $link,
            'category'       => $feedCat ?? $this->mapCategory($title),
        ]);
    }

    private function extractDealNewsImage(string $html): ?string {
        // DealNews typically has a product image in the description
        if (preg_match('/<img[^>]+src=["\']([^"\']{20,})["\'][^>]*>/i', $html, $m)) {
            $src = htmlspecialchars_decode($m[1]);
            if (!str_contains($src, 'pixel') && !str_contains($src, '1x1')) {
                return $src;
            }
        }
        return null;
    }

    private function detectStore(string $text): string {
        $t = strtolower($text);
        $map = [
            'amazon'    => ['amazon.com', 'amzn.to'],
            'walmart'   => ['walmart.com', ' walmart'],
            'target'    => ['target.com', ' target'],
            'bestbuy'   => ['bestbuy.com', 'best buy'],
            'costco'    => ['costco.com', ' costco'],
            'ebay'      => ['ebay.com'],
            'homedepot' => ['homedepot.com', 'home depot'],
            'lowes'     => ['lowes.com'],
            'macys'     => ['macys.com'],
            'kohls'     => ['kohls.com'],
            'newegg'    => ['newegg.com'],
            'bhphoto'   => ['bhphotovideo.com'],
            'adorama'   => ['adorama.com'],
            'samsclub'  => ['samsclub.com'],
            'staples'   => ['staples.com'],
        ];
        foreach ($map as $s => $ps)
            foreach ($ps as $p)
                if (str_contains($t, $p)) return $s;
        return 'other';
    }

    private function cleanDesc(string $html): ?string {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        return trim(preg_replace('/\s+/', ' ', $text)) ?: null;
    }
}
