#!/usr/bin/env php
<?php
/**
 * 50OFF — Scraper Runner
 * ══════════════════════════════════════════════════════════════════════════
 *
 *  SOURCES:
 *  ┌─────────────────────────────────────────────────────────────────────┐
 *  │ Amazon     (amazon.com)    — deals widget + category seeds          │
 *  │ Target     (target.com)    — RedSky API, 6 categories               │
 *  │ Walmart    (walmart.com)   — ⚠ blocked by Akamai, stub only         │
 *  │ Best Buy   (bestbuy.com)   — clearance/sale pages, embedded JSON    │
 *  │ Costco     (costco.com)    — online deals & clearance               │
 *  │ Home Depot (homedepot.com) — Special Buy JSON API + HTML fallback   │
 *  │ 6pm        (6pm.com)       — sale pages, __NEXT_DATA__ JSON         │
 *  │ DealBlogs  (bens+hip2save)  — BensBargains + Hip2Save live RSS       │
 *  │ DealNews   (dealnews.com)  — 9 editor-curated RSS feeds             │
 *  │ eBay       (ebay.com)      — deals page + RSS feeds                 │
 *  └─────────────────────────────────────────────────────────────────────┘
 *
 *  USAGE:
 *    php scraper/run.php              # run all scrapers
 *    php scraper/run.php all          # same
 *    php scraper/run.php amazon       # Amazon only
 *    php scraper/run.php target       # Target only
 *    php scraper/run.php bestbuy      # Best Buy only
 *    php scraper/run.php costco       # Costco only
 *    php scraper/run.php homedepot    # Home Depot only
 *    php scraper/run.php 6pm          # 6pm only
 *    php scraper/run.php slickdeals   # SlickDeals RSS only
 *    php scraper/run.php woot         # Woot.com only
 *    php scraper/run.php dealnews     # DealNews RSS only
 *    php scraper/run.php ebay         # eBay only
 *    php scraper/run.php aggregators  # SlickDeals + Woot + DealNews + eBay
 *    php scraper/run.php retail       # Amazon + Target + BestBuy + Costco + HD + 6pm
 *    php scraper/run.php seed         # seed data only (no network)
 *
 *  CRON (every 3 hours — recommended):
 *    0 *\/3 * * * /usr/bin/php /path/to/50off/scraper/run.php retail >> /tmp/50off.log 2>&1
 */

define('ROOT', dirname(__DIR__));

$jsonMode = (getenv('SCRAPER_OUTPUT') === 'json');

if (!$jsonMode) {
    require_once ROOT . '/includes/db.php';
}

require_once __DIR__ . '/BaseScraper.php';
require_once __DIR__ . '/SeedScraper.php';
require_once __DIR__ . '/AmazonScraper.php';
require_once __DIR__ . '/WalmartScraper.php';
require_once __DIR__ . '/TargetScraper.php';
require_once __DIR__ . '/BestBuyScraper.php';
require_once __DIR__ . '/CostcoScraper.php';
require_once __DIR__ . '/HomeDepotScraper.php';
require_once __DIR__ . '/SixPmScraper.php';
require_once __DIR__ . '/SlickDealsScraper.php';
require_once __DIR__ . '/EbayScraper.php';
require_once __DIR__ . '/WootScraper.php';
require_once __DIR__ . '/DealNewsScraper.php';
require_once __DIR__ . '/DealBlogScraper.php';

if ($jsonMode) {
    BaseScraper::enableJsonMode();
}

$line = str_repeat('═', 60);

if (!$jsonMode) {
    echo "\n$line\n 50OFF Scraper — " . date('Y-m-d H:i:s') . "\n$line\n\n";
}

// ── Which scrapers to run ────────────────────────────────────────────────────
$requested = $argv[1] ?? 'all';
$run = match($requested) {
    'seed'        => ['seed'],
    'amazon'      => ['amazon'],
    'walmart'     => ['walmart'],
    'target'      => ['target'],
    'bestbuy'     => ['bestbuy'],
    'costco'      => ['costco'],
    'homedepot'   => ['homedepot'],
    '6pm'         => ['6pm'],
    'slickdeals'  => ['slickdeals'],
    'ebay'        => ['ebay'],
    'woot'        => ['woot'],
    'dealnews'    => ['dealnews'],
    'dealblogs'   => ['dealblogs'],
    'working'     => ['amazon', 'target', 'dealblogs', 'ebay'],
    'aggregators' => ['dealblogs', 'woot', 'dealnews', 'ebay'],
    'retail'      => ['amazon', 'target', 'bestbuy', 'costco', 'homedepot', '6pm'],
    default       => ['amazon', 'target', 'dealblogs', 'ebay'],
};

// ── DB setup (DB mode only) ───────────────────────────────────────────────────
if (!$jsonMode) {
    $db = getDB();

    try { $db->exec("ALTER TABLE deals ADD UNIQUE KEY uq_product_url (product_url(255))"); }
    catch (\PDOException) {}

    $exp = $db->prepare("UPDATE deals SET is_active=0
        WHERE scraped_at < NOW() - INTERVAL 3 DAY AND is_featured=0");
    $exp->execute();
    echo "✓ Expired: {$exp->rowCount()} old deals deactivated\n\n";
}

// ── Scraper map ───────────────────────────────────────────────────────────────
$scrapers = [
    'seed'       => fn() => new SeedScraper(),
    'amazon'     => fn() => new AmazonScraper(),
    'walmart'    => fn() => new WalmartScraper(),
    'target'     => fn() => new TargetScraper(),
    'bestbuy'    => fn() => new BestBuyScraper(),
    'costco'     => fn() => new CostcoScraper(),
    'homedepot'  => fn() => new HomeDepotScraper(),
    '6pm'        => fn() => new SixPmScraper(),
    'slickdeals' => fn() => new SlickDealsScraper(),
    'ebay'       => fn() => new EbayScraper(),
    'woot'       => fn() => new WootScraper(),
    'dealnews'   => fn() => new DealNewsScraper(),
    'dealblogs'  => fn() => new DealBlogScraper(),
];

if (!$jsonMode) {
    $before = (int)$db->query("SELECT COUNT(*) FROM deals WHERE is_active=1")->fetchColumn();
}

foreach ($run as $name) {
    if (!isset($scrapers[$name])) {
        echo "  ⚠ Unknown scraper: {$name}\n";
        continue;
    }
    if (!$jsonMode) {
        echo str_repeat('─', 60) . "\n ▶  " . strtoupper($name) . "\n" . str_repeat('─', 60) . "\n";
    }
    try {
        $scrapers[$name]()->scrape();
    } catch (\Throwable $e) {
        echo "  ⚠  ERROR: {$e->getMessage()}\n";
        echo "     {$e->getFile()}:{$e->getLine()}\n";
    }
    if (!$jsonMode) echo "\n";
}

// ── JSON mode output ─────────────────────────────────────────────────────────
if ($jsonMode) {
    echo json_encode(BaseScraper::getJsonDeals());
    exit(0);
}

// ── Summary ───────────────────────────────────────────────────────────────────
try { $db->query("SELECT 1"); } catch (\PDOException) { $db = getDB(); }

$after = (int)$db->query("SELECT COUNT(*) FROM deals WHERE is_active=1")->fetchColumn();
$rows  = $db->query("
    SELECT store,
           COUNT(*) as deals,
           ROUND(AVG(discount_pct)) as avg_pct,
           MIN(sale_price) as lowest,
           MAX(discount_pct) as max_pct
    FROM deals WHERE is_active=1 AND discount_pct>=50
    GROUP BY store ORDER BY deals DESC
")->fetchAll(\PDO::FETCH_ASSOC);

echo "$line\n RESULTS\n$line\n";
printf(" %-14s  %6s  %8s  %8s  %10s\n", 'STORE', 'DEALS', 'AVG OFF', 'MAX OFF', 'LOWEST $');
echo str_repeat('─', 56) . "\n";
foreach ($rows as $r) {
    printf(" %-14s  %6d  %7d%%  %7d%%  %9s\n",
        $r['store'], $r['deals'], $r['avg_pct'], $r['max_pct'],
        '$'.number_format($r['lowest'], 2));
}
echo str_repeat('─', 56) . "\n";
printf(" %-14s  %6d  (+%d new)\n", 'TOTAL', $after, max(0, $after - $before));
echo "\n Done: " . date('Y-m-d H:i:s') . "\n$line\n\n";
