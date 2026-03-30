<?php
/**
 * SlickDealsScraper.php
 *
 * SlickDeals.net is the #1 deal community in the US.
 * They publish FREE public RSS feeds of community-vetted deals
 * from Amazon, Walmart, Target, Best Buy, Costco, etc.
 *
 * No API key needed. Completely public and legal to consume.
 * Updated constantly by the community.
 *
 * Feeds:
 *   - Frontpage: https://slickdeals.net/newsearch.php?mode=frontpage&output=rss
 *   - Search:    https://slickdeals.net/newsearch.php?q=50%25+off&output=rss
 */
require_once __DIR__ . '/BaseScraper.php';

class SlickDealsScraper extends BaseScraper {
    protected string $store = 'other'; // set per deal

    private array $feeds = [
        [
            'url' => 'https://slickdeals.net/newsearch.php?mode=frontpage&searcharea=deals&q=&r=1&output=rss',
            'label' => 'frontpage',
        ],
        [
            'url' => 'https://slickdeals.net/newsearch.php?mode=popular&searcharea=deals&q=&r=1&output=rss',
            'label' => 'popular',
        ],
        [
            'url' => 'https://slickdeals.net/newsearch.php?mode=search&searcharea=deals&q=50%25+off&r=1&output=rss',
            'label' => '50pct-off',
        ],
        [
            'url' => 'https://slickdeals.net/newsearch.php?mode=search&searcharea=deals&q=clearance&r=1&output=rss',
            'label' => 'clearance',
        ],
        [
            'url' => 'https://slickdeals.net/newsearch.php?mode=search&searcharea=deals&q=amazon+50%25&r=1&output=rss',
            'label' => 'amazon-50',
        ],
        [
            'url' => 'https://slickdeals.net/newsearch.php?mode=search&searcharea=deals&q=walmart+clearance&r=1&output=rss',
            'label' => 'walmart-clearance',
        ],
        [
            'url' => 'https://slickdeals.net/newsearch.php?mode=search&searcharea=deals&q=best+buy+sale&r=1&output=rss',
            'label' => 'bestbuy-sale',
        ],
    ];

    public function scrape(): void {
        $this->say("Starting SlickDeals scrape...");

        foreach ($this->feeds as $feed) {
            $this->say("Feed: {$feed['label']}");
            $xml = $this->fetchRss($feed['url']);

            if (!$xml) {
                $this->say("No response from {$feed['label']}");
                sleep(2);
                continue;
            }

            $items = $xml->channel->item ?? [];
            $count = 0;

            foreach ($items as $item) {
                if ($this->processItem($item)) $count++;
            }

            $this->say("  → {$count} deals from {$feed['label']}");
            sleep(2); // polite delay between feeds
        }

        $this->logResult('success', 'SlickDeals RSS feeds');
    }

    private function processItem(\SimpleXMLElement $item): bool {
        $title = trim((string)($item->title ?? ''));
        $link  = trim((string)($item->link  ?? ''));
        $desc  = (string)($item->description ?? '');

        if (!$title || !$link) return false;

        $fullText = $title . ' ' . strip_tags($desc);

        // ── Detect discount percentage ──────────────────────────────────────
        $pct = 0;
        if (preg_match('/(\d+)\s*%\s*off/i', $fullText, $m))   $pct = (int)$m[1];
        if (!$pct && preg_match('/save\s+(\d+)%/i', $fullText, $m)) $pct = (int)$m[1];

        // ── Parse prices ────────────────────────────────────────────────────
        $sale = $orig = 0.0;

        // SlickDeals format: "Sale Price: $X.xx (Was $Y.yy)" in description
        if (preg_match('/(?:sale|deal|now|for)[:\s]+\$\s*([\d,]+\.?\d{0,2})/i', $fullText, $m))
            $sale = (float)str_replace(',', '', $m[1]);

        if (preg_match('/(?:was|reg|list|original|retail)[:\s]+\$\s*([\d,]+\.?\d{0,2})/i', $fullText, $m))
            $orig = (float)str_replace(',', '', $m[1]);

        // Fallback: grab all prices and take lowest=sale, highest=orig
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

        // Derive orig from pct + sale if still missing
        if ($orig <= 0 && $pct >= 50 && $sale > 0)
            $orig = round($sale / (1 - $pct / 100), 2);

        // Calc pct if we have both prices
        if ($pct < 50 && $sale > 0 && $orig > $sale)
            $pct = $this->calcDiscount($orig, $sale);

        if ($pct < 50 || $sale <= 0 || $orig <= $sale) return false;

        // ── Extract image from description HTML ─────────────────────────────
        $image = $this->extractImage($desc);

        // ── Detect store from URL and text ──────────────────────────────────
        $store = $this->detectStore($title . ' ' . $link . ' ' . $desc);

        // ── Map category ────────────────────────────────────────────────────
        $category = $this->mapCategory($title . ' ' . $desc);

        // ── Clean title (remove store prefix) ───────────────────────────────
        $cleanTitle = preg_replace(
            '/^(?:Amazon|Walmart|Target|Best Buy|Costco|eBay|Home Depot)[:\s\-–]+/i',
            '', $title
        );
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
            'category'       => $category,
        ]);
    }

    private function extractImage(string $html): ?string {
        // Try to find a meaningful product image
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                $src = htmlspecialchars_decode($src);
                // Skip tiny/tracking images
                if (str_contains($src, '1x1'))      continue;
                if (str_contains($src, 'pixel'))     continue;
                if (str_contains($src, 'track'))     continue;
                if (str_contains($src, 'spacer'))    continue;
                if (strlen($src) < 15)               continue;
                return $src;
            }
        }
        return null;
    }

    private function detectStore(string $text): string {
        $t = strtolower($text);
        $map = [
            'amazon'    => ['amazon.com', 'amzn.to', 'amzn.com'],
            'walmart'   => ['walmart.com'],
            'target'    => ['target.com'],
            'bestbuy'   => ['bestbuy.com', 'best buy'],
            'costco'    => ['costco.com'],
            'ebay'      => ['ebay.com'],
            'homedepot' => ['homedepot.com', 'home depot'],
            'lowes'     => ['lowes.com'],
            'macys'     => ['macys.com'],
            'kohls'     => ['kohls.com'],
            'newegg'    => ['newegg.com'],
            'adorama'   => ['adorama.com'],
            'bhphoto'   => ['bhphotovideo.com', 'b&h photo'],
            'staples'   => ['staples.com'],
            'samsclub'  => ['samsclub.com'],
        ];
        foreach ($map as $store => $patterns)
            foreach ($patterns as $p)
                if (str_contains($t, $p)) return $store;
        return 'other';
    }

    private function cleanDesc(string $html): ?string {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        return $text ?: null;
    }
}
