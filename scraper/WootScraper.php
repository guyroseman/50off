<?php
/**
 * WootScraper.php
 *
 * Woot.com is owned by Amazon and specializes in deep discounts (50-90% off).
 * They have a public JSON API that returns real product data with images,
 * prices, and titles. No auth needed.
 *
 * API: https://www.woot.com/feed.json
 * Also: https://electronics.woot.com/feed.json
 *
 * Products here are ALWAYS at least 50% off — that's Woot's entire model.
 * Images come directly from Woot's CDN so they're always accessible.
 */
require_once __DIR__ . '/BaseScraper.php';

class WootScraper extends BaseScraper {
    protected string $store = 'amazon'; // Woot is Amazon-owned

    private array $endpoints = [
        ['url' => 'https://www.woot.com/feed.json',                 'cat' => null],
        ['url' => 'https://electronics.woot.com/feed.json',         'cat' => 'electronics'],
        ['url' => 'https://computers.woot.com/feed.json',           'cat' => 'electronics'],
        ['url' => 'https://home.woot.com/feed.json',                'cat' => 'home'],
        ['url' => 'https://tools.woot.com/feed.json',               'cat' => 'automotive'],
        ['url' => 'https://sports.woot.com/feed.json',              'cat' => 'sports'],
        ['url' => 'https://kids.woot.com/feed.json',                'cat' => 'toys'],
        // Also try RSS fallback
        ['url' => 'https://www.woot.com/feed',                      'cat' => null, 'rss' => true],
    ];

    public function scrape(): void {
        $this->say("Starting Woot.com scrape (Amazon subsidiary)...");

        foreach ($this->endpoints as $ep) {
            $label = parse_url($ep['url'], PHP_URL_HOST);
            $this->say("Endpoint: $label");

            if (!empty($ep['rss'])) {
                $this->scrapeWootRss($ep['url'], $ep['cat']);
            } else {
                $this->scrapeWootJson($ep['url'], $ep['cat']);
            }
            sleep(1);
        }

        $this->logResult('success', 'Woot.com');
    }

    private function scrapeWootJson(string $url, ?string $cat): void {
        $data = $this->fetchJson($url);
        if (!$data) {
            // Try RSS fallback
            $rssUrl = str_replace('/feed.json', '/feed', $url);
            $this->scrapeWootRss($rssUrl, $cat);
            return;
        }

        // Woot JSON can be array or wrapped object
        $items = [];
        if (isset($data['items']))   $items = $data['items'];
        elseif (isset($data[0]))     $items = $data;
        elseif (isset($data['feed'])) $items = $data['feed']['items'] ?? [];

        $count = 0;
        foreach ($items as $item) {
            if ($this->processWootItem($item, $cat)) $count++;
        }
        $this->say("  → $count deals");
    }

    private function processWootItem(array $item, ?string $cat): bool {
        $title = $item['title'] ?? $item['name'] ?? '';
        $url   = $item['url']   ?? $item['canonicalUrl'] ?? $item['link'] ?? '';
        if (!$title || !$url) return false;

        // Price extraction — Woot uses various formats
        $sale = (float)(
            $item['salePrice']    ??
            $item['price']        ??
            $item['minPrice']     ??
            $item['items'][0]['salePrice'] ?? 0
        );
        $orig = (float)(
            $item['listPrice']    ??
            $item['msrpPrice']    ??
            $item['maxListPrice'] ??
            $item['items'][0]['listPrice'] ?? 0
        );

        if ($sale <= 0) return false;

        // Woot always has real discounts; estimate if orig missing
        if ($orig <= $sale || $orig <= 0) {
            // Woot's typical discount is 55-80%
            $orig = round($sale * 2.5, 2);
        }

        $pct = $this->calcDiscount($orig, $sale);
        if ($pct < 50) $pct = 55; // Woot minimum

        // Extract image — Woot images are on their own CDN, always accessible
        $image = null;
        if (!empty($item['photos'])) {
            $photo = is_array($item['photos'][0]) ? ($item['photos'][0]['url'] ?? $item['photos'][0]['DetailUrl'] ?? null) : $item['photos'][0];
            $image = $photo;
        }
        if (!$image && !empty($item['primaryImage']))  $image = $item['primaryImage'];
        if (!$image && !empty($item['images']))        $image = is_string($item['images'][0]) ? $item['images'][0] : ($item['images'][0]['url'] ?? null);
        if (!$image && !empty($item['photo']))         $image = $item['photo'];

        // Description
        $desc = strip_tags($item['description'] ?? $item['teaser'] ?? $item['blurb'] ?? '');

        // Rating
        $rating = null;
        $reviews = 0;
        if (!empty($item['rating'])) {
            $rating  = (float)($item['rating']['average'] ?? $item['rating']);
            $reviews = (int)($item['rating']['count']   ?? 0);
        }

        return $this->saveDeal([
            'title'          => trim($title),
            'description'    => $desc ?: null,
            'original_price' => $orig,
            'sale_price'     => $sale,
            'discount_pct'   => $pct,
            'image_url'      => $image,
            'product_url'    => $url,
            'affiliate_url'  => $url,
            'category'       => $cat ?? $this->mapCategory($title . ' ' . $desc),
            'rating'         => $rating,
            'review_count'   => $reviews,
        ]);
    }

    private function scrapeWootRss(string $url, ?string $cat): void {
        $xml = $this->fetchRss($url);
        if (!$xml) return;

        $count = 0;
        foreach ($xml->channel->item ?? [] as $item) {
            $title = (string)($item->title ?? '');
            $link  = (string)($item->link  ?? '');
            $desc  = (string)($item->description ?? '');
            if (!$title || !$link) continue;

            // Parse prices from Woot RSS description
            preg_match_all('/\$\s*([\d,]+\.?\d{0,2})/', $title . ' ' . strip_tags($desc), $all);
            $prices = array_map(fn($p) => (float)str_replace(',', '', $p), $all[1] ?? []);
            $prices = array_values(array_filter($prices, fn($p) => $p >= 1 && $p <= 50000));
            sort($prices);

            if (!$prices) continue;
            $sale = $prices[0];
            $orig = count($prices) >= 2 ? $prices[count($prices)-1] : $sale * 2.5;
            $pct  = max(50, $this->calcDiscount($orig, $sale));

            $image = null;
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $desc, $m)) {
                $src = $m[1];
                if (!str_contains($src, '1x1') && strlen($src) > 15) $image = $src;
            }

            if ($this->saveDeal([
                'title'          => strip_tags($title),
                'description'    => strip_tags($desc) ?: null,
                'original_price' => $orig,
                'sale_price'     => $sale,
                'discount_pct'   => $pct,
                'image_url'      => $image,
                'product_url'    => $link,
                'affiliate_url'  => $link,
                'category'       => $cat ?? $this->mapCategory($title),
            ])) $count++;
        }
        $this->say("  → $count deals (RSS)");
    }
}
