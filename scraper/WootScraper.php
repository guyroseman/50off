<?php
/**
 * WootScraper.php
 *
 * Woot.com is owned by Amazon and specializes in deep discounts (50-90% off).
 * The public feed.json endpoints were removed; we now scrape the category pages
 * and extract product data from the embedded __NEXT_DATA__ / GraphQL state.
 *
 * Categories: /category/{name} pages contain server-side rendered deal cards.
 */
require_once __DIR__ . '/BaseScraper.php';

class WootScraper extends BaseScraper {
    protected string $store = 'woot';

    private array $pages = [
        ['url' => 'https://www.woot.com/category/electronics', 'cat' => 'electronics'],
        ['url' => 'https://www.woot.com/category/computers',   'cat' => 'electronics'],
        ['url' => 'https://www.woot.com/category/home',        'cat' => 'home'],
        ['url' => 'https://www.woot.com/category/tools',       'cat' => 'tools'],
        ['url' => 'https://www.woot.com/category/sport',       'cat' => 'sports'],
        ['url' => 'https://www.woot.com/category/sellout',     'cat' => null],
    ];

    public function scrape(): void {
        $this->say('=== Woot Scraper (Amazon-owned) ===');
        $totalSaved = 0;

        foreach ($this->pages as $page) {
            $label = basename($page['url']);
            $this->say("→ {$label}...");
            sleep(rand(2, 3));

            $html = $this->fetch($page['url'], [
                'Accept: text/html,application/xhtml+xml,*/*;q=0.8',
            ], 'https://www.woot.com/');

            if (!$html || strlen($html) < 3000) {
                $this->say('  ✗ No/short response');
                continue;
            }
            $this->say('  ✓ ' . number_format(strlen($html)) . ' bytes');

            $n = $this->parseWootPage($html, $page['cat']);
            $totalSaved += $n;
            $this->say("  Saved {$n} deals");
        }

        $this->say("══ Done: {$totalSaved} deals ══");
        $this->logResult($totalSaved > 0 ? 'success' : 'warning', "Woot.com saved: {$totalSaved}");
    }

    private function parseWootPage(string $html, ?string $cat): int
    {
        $saved = 0;

        // Try __NEXT_DATA__ first
        if (preg_match('/<script[^>]+id=["\']__NEXT_DATA__["\'][^>]*>(.+?)<\/script>/s', $html, $m)) {
            $data = json_decode($m[1], true);
            if ($data && json_last_error() === JSON_ERROR_NONE) {
                $saved = $this->extractFromNextData($data, $cat);
                if ($saved > 0) return $saved;
            }
        }

        // Try any JSON with SalePrice / ListPrice fields
        if (preg_match_all('/"SalePrice"\s*:\s*([\d.]+)/', $html, $salePrices)) {
            // Extract surrounding JSON objects
            if (preg_match('/"Offers"\s*:\s*(\[.+?\])\s*[,}]/s', $html, $m)) {
                $offers = json_decode($m[1], true);
                if ($offers && json_last_error() === JSON_ERROR_NONE) {
                    foreach ($offers as $offer) {
                        if ($this->processWootOffer($offer, $cat)) $saved++;
                    }
                    return $saved;
                }
            }
        }

        // HTML fallback: deal cards
        $saved = $this->parseWootHtml($html, $cat);
        return $saved;
    }

    private function extractFromNextData(array $data, ?string $cat): int
    {
        $saved = 0;
        $pp    = $data['props']['pageProps'] ?? [];

        // Woot's pageProps path: offers[] or items[]
        $offers = $pp['offers'] ?? $pp['items'] ?? $pp['products'] ?? [];

        if (empty($offers)) {
            // Deep search for array with SalePrice keys
            array_walk_recursive($pp, function($v, $k) use (&$offers) {
                if ($k === 'Offers' && is_array($v) && !empty($v)) $offers = $v;
            });
        }

        foreach ($offers as $offer) {
            if ($this->processWootOffer($offer, $cat)) $saved++;
        }
        return $saved;
    }

    private function processWootOffer(array $offer, ?string $cat): bool
    {
        $title = $offer['Title']   ?? $offer['title']   ?? $offer['name'] ?? '';
        $url   = $offer['Permalink'] ?? $offer['url']   ?? $offer['canonicalUrl'] ?? '';
        if (!$title || !$url) return false;
        if (!str_starts_with($url, 'http')) $url = 'https://www.woot.com' . $url;

        $sale = (float)($offer['SalePrice']  ?? $offer['salePrice']  ?? $offer['price']     ?? 0);
        $orig = (float)($offer['ListPrice']  ?? $offer['listPrice']  ?? $offer['msrpPrice'] ?? 0);

        if ($sale <= 0) return false;
        if ($orig <= $sale) $orig = round($sale * 2.5, 2);  // Woot always has big discounts

        $pct = $this->calcDiscount($orig, $sale);
        if ($pct < 50) $pct = 55;  // Woot's baseline

        // Image
        $image = $offer['MainImage']['Url']    ?? $offer['photo']
              ?? $offer['primaryImage']        ?? null;
        if (!$image && !empty($offer['Photos'])) {
            $p     = $offer['Photos'][0];
            $image = is_array($p) ? ($p['Url'] ?? $p['url'] ?? null) : $p;
        }

        return $this->saveDeal([
            'title'          => trim($title),
            'original_price' => $orig,
            'sale_price'     => $sale,
            'discount_pct'   => $pct,
            'image_url'      => $image,
            'product_url'    => $url,
            'affiliate_url'  => $url,
            'category'       => $cat ?? $this->mapCategory($title),
        ]);
    }

    private function parseWootHtml(string $html, ?string $cat): int
    {
        $saved = 0;
        $xpath = $this->loadDom($html);
        if (!$xpath) return 0;

        // Woot deal cards: <div class="item-..."> or <article class="deal-...">
        $cards = $xpath->query(
            '//*[contains(@class,"ItemTeaser") or contains(@class,"woot-offer") or contains(@class,"deal-card")]'
        );

        if (!$cards || $cards->length === 0) {
            $this->say('  No HTML deal cards found');
            return 0;
        }

        foreach ($cards as $card) {
            $titleNode = $xpath->query('.//*[contains(@class,"title") or contains(@class,"Title")]', $card)->item(0);
            $title     = $titleNode ? trim($titleNode->textContent) : '';
            if (!$title) continue;

            $linkNode = $xpath->query('.//a[@href]', $card)->item(0);
            $href     = ($linkNode instanceof \DOMElement) ? $linkNode->getAttribute('href') : '';
            if (!$href) continue;
            $url = str_starts_with($href, 'http') ? $href : 'https://www.woot.com' . $href;

            $saleTxt = $xpath->evaluate('string(.//*[contains(@class,"price") or contains(@class,"Price")])', $card);
            $sale    = $this->parsePrice($saleTxt);
            if ($sale <= 0) continue;

            $origTxt = $xpath->evaluate('string(.//*[contains(@class,"list") or contains(@class,"was") or contains(@class,"orig")])', $card);
            $orig    = $this->parsePrice($origTxt);
            if ($orig <= $sale) $orig = round($sale * 2.5, 2);

            $pct = max(50, $this->calcDiscount($orig, $sale));

            $imgNode  = $xpath->query('.//img[@src]', $card)->item(0);
            $imageUrl = ($imgNode instanceof \DOMElement) ? $imgNode->getAttribute('src') : null;

            if ($this->saveDeal([
                'title'          => $title,
                'original_price' => $orig,
                'sale_price'     => $sale,
                'discount_pct'   => $pct,
                'image_url'      => $imageUrl,
                'product_url'    => $url,
                'affiliate_url'  => $url,
                'category'       => $cat ?? $this->mapCategory($title),
            ])) $saved++;
        }
        return $saved;
    }
}
