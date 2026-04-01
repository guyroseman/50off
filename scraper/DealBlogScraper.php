<?php
/**
 * DealBlogScraper.php — Curated deal blogs with public RSS feeds
 *
 * Sources:
 *  - BensBargains.com  — Editor-curated deals, updated 24/7, Amazon/Walmart/etc.
 *                        Title: "Product Name $X at Store"
 *                        Image: cdn.bensimages.com CDN (always accessible)
 *  - Hip2Save.com      — Popular savings blog, millions of readers
 *                        Title: "Product from $X (Reg. $Y)"
 *                        Image: <thumbnail-image> custom element
 *
 * All RSS feeds are public, no API key needed, no bot protection.
 * Updated continuously — new deals appear every 30-60 minutes.
 */
require_once __DIR__ . '/BaseScraper.php';

class DealBlogScraper extends BaseScraper
{
    protected string $store = 'other';

    private array $feeds = [
        // BensBargains — direct links to Amazon/Walmart/etc, updated 24/7
        ['url' => 'https://feeds2.feedburner.com/BensBargains',  'source' => 'bensbargains', 'type' => 'bens'],
        // Hip2Save — millions of readers, title includes "(Reg. $X)", updated constantly
        ['url' => 'https://hip2save.com/feed/',                   'source' => 'hip2save',     'type' => 'h2s'],
    ];

    public function scrape(): void
    {
        $this->say('=== DealBlog Scraper — BensBargains + Hip2Save ===');
        $totalSaved = 0;

        foreach ($this->feeds as $feed) {
            $this->say("→ {$feed['source']}: {$feed['url']}");
            $xml = $this->fetchRss($feed['url']);
            if (!$xml) {
                $this->say('  ✗ No response');
                sleep(2);
                continue;
            }

            $items  = $xml->channel->item ?? [];
            $count  = 0;
            $method = 'process_' . $feed['type'];

            foreach ($items as $item) {
                if ($this->$method($item, $feed['source'])) {
                    $count++;
                    $totalSaved++;
                }
            }

            $this->say("  ✓ {$count} deals from {$feed['source']}");
            sleep(2);
        }

        $this->say("══ Done: {$totalSaved} deals ══");
        $this->logResult($totalSaved > 0 ? 'success' : 'warning', "DealBlog (BB+H2S) saved: {$totalSaved}");
    }

    // ── BensBargains ─────────────────────────────────────────────────────────
    // Title format: "Product Name  $X.xx at Store"
    // Image: in description <img> tag, cdn.bensimages.com CDN
    private function process_bens(\SimpleXMLElement $item, string $source): bool
    {
        $title = trim((string)($item->title ?? ''));
        $link  = trim((string)($item->link  ?? ''));
        $desc  = (string)($item->description ?? '');
        if (!$title || !$link) return false;

        // Skip pure category/roundup posts without a specific price
        if (!preg_match('/\$\s*[\d,]+/', $title . $desc)) return false;

        $fullText = $title . ' ' . strip_tags($desc);

        // Sale price: "$X.xx at Store" in title
        $sale = 0.0;
        if (preg_match('/\$\s*([\d,]+\.?\d{0,2})\s+at\s+\w/i', $title, $m))
            $sale = (float)str_replace(',', '', $m[1]);

        // Original / was price from description
        $orig = 0.0;
        if (preg_match('/(?:was|reg(?:ular)?|list|original|retails?\s+for)[:\s]+\$\s*([\d,]+\.?\d{0,2})/i', $fullText, $m))
            $orig = (float)str_replace(',', '', $m[1]);

        // "for $X - $Y off" pattern: orig = sale + savings
        if ($orig <= 0) {
            if (preg_match('/\$\s*([\d,]+\.?\d{0,2})\s*[-–]\s*\$\s*([\d,]+\.?\d{0,2})\s+off/i', $fullText, $m)) {
                $listPrice = (float)str_replace(',', '', $m[1]);
                $savings   = (float)str_replace(',', '', $m[2]);
                if ($listPrice > 0 && $savings > 0) {
                    $sale = $listPrice - $savings;
                    $orig = $listPrice;
                }
            }
        }

        // Percentage
        $pct = 0;
        if (preg_match('/(\d+)\s*%\s*off/i', $fullText, $m)) $pct = (int)$m[1];

        // Fallback: all prices, lowest=sale, highest=orig
        if ($sale <= 0 || $orig <= 0) {
            preg_match_all('/\$\s*([\d,]+\.?\d{0,2})/', $fullText, $all);
            $prices = array_map(fn($p) => (float)str_replace(',', '', $p), $all[1] ?? []);
            $prices = array_values(array_filter($prices, fn($p) => $p >= 1 && $p <= 50000));
            sort($prices);
            if (count($prices) >= 2) {
                if ($sale <= 0) $sale = $prices[0];
                if ($orig <= 0) $orig = end($prices);
            } elseif (count($prices) === 1) {
                if ($sale <= 0) $sale = $prices[0];
                if ($pct >= 50 && $sale > 0) $orig = round($sale / (1 - $pct / 100), 2);
            }
        }

        if ($orig <= 0 && $pct >= 50 && $sale > 0) $orig = round($sale / (1 - $pct / 100), 2);
        if ($pct < 50 && $sale > 0 && $orig > $sale) $pct = $this->calcDiscount($orig, $sale);
        if ($pct < 50 || $sale <= 0 || $orig <= $sale) return false;

        // Image — BensBargains CDN is always accessible
        $image = $this->extractImage($desc);

        // Detect actual store from link
        $store = $this->detectStore($link . ' ' . $title . ' ' . $desc);
        $this->store = $store;

        // Clean title: remove price/store suffix
        $cleanTitle = preg_replace('/\s+\$[\d,]+\.?\d*\s+at\s+\w+[\w\s]*$/i', '', $title);
        $cleanTitle = trim(strip_tags($cleanTitle));

        return $this->saveDeal([
            'title'          => $cleanTitle ?: $title,
            'description'    => $this->cleanText($desc),
            'original_price' => $orig,
            'sale_price'     => $sale,
            'discount_pct'   => $pct,
            'image_url'      => $image,
            'product_url'    => $link,
            'affiliate_url'  => $link,
            'category'       => $this->mapCategory($title . ' ' . $desc),
        ]);
    }

    // ── Hip2Save ─────────────────────────────────────────────────────────────
    // Title format: "Product from $X (Reg. $Y)" or "Product Just $X Shipped"
    // Image: <thumbnail-image> custom element
    private function process_h2s(\SimpleXMLElement $item, string $source): bool
    {
        $title = trim((string)($item->title ?? ''));
        $link  = trim((string)($item->link  ?? ''));
        $desc  = (string)($item->description ?? '');
        if (!$title || !$link) return false;

        // Skip gift card, freebie, and coupon posts — not real product deals
        $lc = strtolower($title);
        if (str_contains($lc, 'gift card') || str_contains($lc, 'free shipping only')
            || str_contains($lc, 'coupon') || str_contains($lc, 'printable')) return false;

        $fullText = $title . ' ' . strip_tags($desc);

        // "(Reg. $Y)" pattern — most reliable for Hip2Save
        $orig = 0.0;
        if (preg_match('/\((?:Reg|Orig|Was|Retail)\.?\s*\$\s*([\d,]+\.?\d{0,2})\)/i', $fullText, $m))
            $orig = (float)str_replace(',', '', $m[1]);
        if ($orig <= 0 && preg_match('/(?:reg(?:ular)?|orig(?:inally)?|was|retail)[:\s]+\$\s*([\d,]+\.?\d{0,2})/i', $fullText, $m))
            $orig = (float)str_replace(',', '', $m[1]);

        // Sale price: "from $X", "just $X", "for $X", "as low as $X"
        $sale = 0.0;
        if (preg_match('/(?:from|just|for|only|as low as)\s+\$\s*([\d,]+\.?\d{0,2})/i', $title, $m))
            $sale = (float)str_replace(',', '', $m[1]);
        if ($sale <= 0 && preg_match('/\$\s*([\d,]+\.?\d{0,2})\s+(?:shipped|each|per)/i', $fullText, $m))
            $sale = (float)str_replace(',', '', $m[1]);

        // Percent off
        $pct = 0;
        if (preg_match('/(\d+)\s*%\s*off/i', $fullText, $m)) $pct = (int)$m[1];

        // Fallback: collect all prices
        if ($sale <= 0 || $orig <= 0) {
            preg_match_all('/\$\s*([\d,]+\.?\d{0,2})/', $fullText, $all);
            $prices = array_map(fn($p) => (float)str_replace(',', '', $p), $all[1] ?? []);
            $prices = array_values(array_filter($prices, fn($p) => $p >= 1 && $p <= 50000));
            sort($prices);
            if (count($prices) >= 2) {
                if ($sale <= 0) $sale = $prices[0];
                if ($orig <= 0) $orig = end($prices);
            } elseif (count($prices) === 1 && $pct >= 50) {
                if ($sale <= 0) $sale = $prices[0];
                if ($orig <= 0) $orig = round($sale / (1 - $pct / 100), 2);
            }
        }

        if ($orig <= 0 && $pct >= 50 && $sale > 0) $orig = round($sale / (1 - $pct / 100), 2);
        if ($pct < 50 && $sale > 0 && $orig > $sale) $pct = $this->calcDiscount($orig, $sale);
        if ($pct < 50 || $sale <= 0 || $orig <= $sale) return false;

        // Hip2Save has <thumbnail-image> custom tag
        $nsItems = $item->children('', false);
        $image   = null;
        if (isset($nsItems->{'thumbnail-image'})) {
            $image = trim((string)$nsItems->{'thumbnail-image'});
        }
        if (!$image) $image = $this->extractImage($desc);

        $store = $this->detectStore($link . ' ' . $title . ' ' . $desc);
        $this->store = $store;

        // Clean title: remove "(Reg. $X)" and trailing store
        $cleanTitle = preg_replace('/\s*\((?:Reg|Orig|Was|Retail)\.?\s*\$[\d,.]+\)/i', '', $title);
        $cleanTitle = preg_replace('/\s+(?:at|on)\s+\w[\w\s]+$/i', '', $cleanTitle);
        $cleanTitle = trim(strip_tags($cleanTitle));

        return $this->saveDeal([
            'title'          => $cleanTitle ?: $title,
            'description'    => $this->cleanText($desc),
            'original_price' => $orig,
            'sale_price'     => $sale,
            'discount_pct'   => $pct,
            'image_url'      => $image,
            'product_url'    => $link,
            'affiliate_url'  => $link,
            'category'       => $this->mapCategory($title . ' ' . $desc),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private function extractImage(string $html): ?string
    {
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
            foreach ($m[1] as $src) {
                $src = htmlspecialchars_decode($src);
                if (str_contains($src, '1x1'))   continue;
                if (str_contains($src, 'pixel'))  continue;
                if (str_contains($src, 'track'))  continue;
                if (str_contains($src, 'spacer')) continue;
                if (strlen($src) < 15)            continue;
                if (str_starts_with($src, '//')) $src = 'https:' . $src;
                return $src;
            }
        }
        return null;
    }

    private function detectStore(string $text): string
    {
        $t   = strtolower($text);
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
            'bhphoto'   => ['bhphotovideo.com'],
            'staples'   => ['staples.com'],
            'samsclub'  => ['samsclub.com'],
        ];
        foreach ($map as $s => $ps)
            foreach ($ps as $p)
                if (str_contains($t, $p)) return $s;
        return 'other';
    }

    private function cleanText(string $html): ?string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        return trim(preg_replace('/\s+/', ' ', $text)) ?: null;
    }
}
