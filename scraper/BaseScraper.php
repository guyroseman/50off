<?php
/**
 * BaseScraper.php — Foundation for all 50OFF scrapers.
 * Uses cURL for reliability. All scrapers extend this class.
 */
require_once __DIR__ . '/../includes/db.php';

abstract class BaseScraper {
    protected PDO    $db;
    protected string $store      = 'other';
    protected int    $dealsFound = 0;
    protected bool   $verbose    = true;

    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
    ];

    public function __construct() { $this->db = getDB(); }

    abstract public function scrape(): void;

    // ── cURL fetch with automatic retry on 429/503 ────────────────────────────
    protected function fetch(string $url, array $extraHeaders = [], string $referer = '', int $retries = 3): string|false {
        $ua = $this->userAgents[array_rand($this->userAgents)];

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_ENCODING       => '',           // auto-decompress gzip/deflate
                CURLOPT_USERAGENT      => $ua,
                CURLOPT_REFERER        => $referer ?: 'https://www.google.com/',
                CURLOPT_HTTPHEADER     => array_merge([
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                    'Cache-Control: no-cache',
                    'Sec-Fetch-Dest: document',
                    'Sec-Fetch-Mode: navigate',
                    'Sec-Fetch-Site: none',
                    'Upgrade-Insecure-Requests: 1',
                ], $extraHeaders),
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            if ($err) {
                $this->say("cURL error (attempt $attempt/$retries): $err");
                if ($attempt < $retries) { sleep(rand(3, 6)); continue; }
                return false;
            }

            // Rate-limited (429) or temporary unavailable (503) — back off and retry
            if ($code === 429 || $code === 503) {
                $wait = $attempt * rand(8, 15);
                $this->say("HTTP $code — rate limited. Waiting {$wait}s before retry $attempt/$retries...");
                sleep($wait);
                // Rotate user agent on retry
                $ua = $this->userAgents[array_rand($this->userAgents)];
                continue;
            }

            if ($code >= 400) {
                $this->say("HTTP $code: $url");
                return false;
            }

            return $body ?: false;
        }

        return false;
    }

    protected function fetchJson(string $url, array $headers = []): ?array {
        $h    = array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers);
        $body = $this->fetch($url, $h);
        if (!$body) return null;
        $d = @json_decode($body, true);
        return is_array($d) ? $d : null;
    }

    protected function fetchRss(string $url): ?\SimpleXMLElement {
        $body = $this->fetch($url, ['Accept: application/rss+xml, application/xml, text/xml']);
        if (!$body) return null;
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($body);
        libxml_clear_errors();
        return $xml ?: null;
    }

    // ── Save deal to DB ────────────────────────────────────────────────────────
    protected function saveDeal(array $d): bool {
        if (empty($d['title']) || empty($d['product_url'])) return false;

        $orig = (float)($d['original_price'] ?? 0);
        $sale = (float)($d['sale_price']     ?? 0);
        $pct  = (int)($d['discount_pct']     ?? 0);

        if ($pct === 0 && $orig > 0 && $sale > 0 && $sale < $orig) {
            $pct = $this->calcDiscount($orig, $sale);
        }
        if ($orig <= 0 && $pct > 0 && $sale > 0) {
            $orig = round($sale / (1 - $pct / 100), 2);
        }
        if ($pct < 50 || $sale <= 0 || $orig <= $sale) return false;

        $params = [
            ':title'   => substr(trim(strip_tags($d['title'])), 0, 500),
            ':desc'    => isset($d['description']) ? substr(strip_tags($d['description']), 0, 1000) : null,
            ':orig'    => $orig,
            ':sale'    => $sale,
            ':pct'     => $pct,
            ':img'     => $d['image_url'] ?? null,
            ':url'     => substr($d['product_url'], 0, 1000),
            ':aff'     => substr($d['affiliate_url'] ?? $d['product_url'], 0, 1000),
            ':store'   => $this->store,
            ':cat'     => $d['category'] ?? null,
            ':rating'  => isset($d['rating']) ? round((float)$d['rating'], 1) : null,
            ':reviews' => (int)($d['review_count'] ?? 0),
        ];

        $sql = "
            INSERT INTO deals
                (title, description, original_price, sale_price, discount_pct,
                 image_url, product_url, affiliate_url, store, category,
                 rating, review_count, is_featured, is_active, scraped_at)
            VALUES
                (:title,:desc,:orig,:sale,:pct,:img,:url,:aff,:store,:cat,:rating,:reviews,0,1,NOW())
            ON DUPLICATE KEY UPDATE
                title          = VALUES(title),
                sale_price     = VALUES(sale_price),
                original_price = VALUES(original_price),
                discount_pct   = VALUES(discount_pct),
                image_url      = COALESCE(VALUES(image_url), image_url),
                description    = COALESCE(VALUES(description), description),
                scraped_at     = NOW(),
                is_active      = 1
        ";

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $this->dealsFound++;
                $this->say("✓ Saved: " . substr($d['title'], 0, 60) . " (-{$pct}%)");
                return true;
            } catch (\PDOException $e) {
                $msg = $e->getMessage();
                // MySQL "gone away" (2006) or "lost connection" (2013) — reconnect and retry
                if ($attempt === 1 && (str_contains($msg, 'gone away') || str_contains($msg, '2006') || str_contains($msg, '2013') || str_contains($msg, 'lost connection'))) {
                    $this->say("  DB reconnecting...");
                    $this->db = getDB(true);
                    continue; // retry
                }
                $this->say("DB error: " . $msg);
                return false;
            }
        }
        return false;
    }

    protected function calcDiscount(float $orig, float $sale): int {
        if ($orig <= 0 || $sale >= $orig) return 0;
        return (int)round((($orig - $sale) / $orig) * 100);
    }

    protected function say(string $msg): void {
        if ($this->verbose) echo "    $msg\n";
    }

    protected function logResult(string $status = 'success', string $msg = ''): void {
        try {
            $this->db->prepare("INSERT INTO scraper_log (store,status,deals_found,message) VALUES (?,?,?,?)")
                     ->execute([$this->store, $status, $this->dealsFound, $msg]);
        } catch (\Exception) {}
        $icon = $status === 'success' ? '✅' : '⚠️';
        echo "  $icon [{$this->store}] {$this->dealsFound} deals saved. $msg\n";
    }

    protected function loadDom(string $html): ?\DOMXPath {
        if (!$html) return null;
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        return new \DOMXPath($dom);
    }

    protected function parsePrice(string $text): float {
        $text = preg_replace('/[^\d.,]/', ' ', $text);
        preg_match('/([\d,]+\.?\d{0,2})/', trim($text), $m);
        return isset($m[1]) ? (float)str_replace(',', '', $m[1]) : 0.0;
    }

    protected function mapCategory(string $raw): string {
        $raw = strtolower($raw);
        $map = [
            'electronic'=>'electronics','computer'=>'electronics','laptop'=>'electronics',
            'phone'=>'electronics','tablet'=>'electronics','tv '=>'electronics',
            'camera'=>'electronics','headphone'=>'electronics','audio'=>'electronics',
            'speaker'=>'electronics','smart watch'=>'electronics','gaming'=>'electronics',
            'kitchen'=>'kitchen','appliance'=>'kitchen','cookware'=>'kitchen',
            'blender'=>'kitchen','instant pot'=>'kitchen','air fryer'=>'kitchen',
            'clothing'=>'clothing','apparel'=>'clothing','fashion'=>'clothing',
            'shoe'=>'clothing','shirt'=>'clothing','jeans'=>'clothing','dress'=>'clothing',
            'toy'=>'toys','lego'=>'toys','game'=>'toys','doll'=>'toys',
            'sport'=>'sports','outdoor'=>'sports','fitness'=>'sports','gym'=>'sports',
            'health'=>'health','vitamin'=>'health','supplement'=>'health',
            'beauty'=>'beauty','skincare'=>'beauty','makeup'=>'beauty',
            'home'=>'home','garden'=>'home','furniture'=>'home','bedding'=>'home',
            'vacuum'=>'home','mattress'=>'home',
            'book'=>'books','novel'=>'books',
            'auto'=>'automotive','car'=>'automotive','tool'=>'automotive',
        ];
        foreach ($map as $k => $v) { if (str_contains($raw, $k)) return $v; }
        return 'other';
    }
}
