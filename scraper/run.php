#!/usr/bin/env php
<?php
/**
 * 50OFF — Scraper Runner
 * ══════════════════════════════════════════════════════════════════════════
 *
 *  SOURCES:
 *  ┌─────────────────────────────────────────────────────────────────────┐
 *  │ Amazon  (amazon.com US) — deals filtered to 50–100% off            │
 *  │ Target  (target.com)    — top deals + clearance, 50%+ off          │
 *  │                           ⚠ Target needs GitHub Actions IP         │
 *  │ Walmart (walmart.com)   — ⚠ blocked by Akamai, stub only           │
 *  └─────────────────────────────────────────────────────────────────────┘
 *
 *  USAGE:
 *    php scraper/run.php           # run all scrapers (amazon + target + walmart)
 *    php scraper/run.php all       # same
 *    php scraper/run.php amazon    # Amazon only
 *    php scraper/run.php target    # Target only
 *    php scraper/run.php walmart   # Walmart only (stub — logs block message)
 *    php scraper/run.php seed      # seed data only (no network)
 *
 *  GITHUB ACTIONS MODE (SCRAPER_OUTPUT=json):
 *    SCRAPER_OUTPUT=json php scraper/run.php target
 *    Outputs JSON to stdout, no DB connection needed. Used by GitHub Actions
 *    to run Target scraper and POST results to /api/submit-deals.php.
 *
 *  CRON (every 4 hours — Amazon only, runs on Hostinger):
 *    0 *\/4 * * * /usr/bin/php /path/to/50off/scraper/run.php amazon >> /tmp/50off.log 2>&1
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
    'seed'    => ['seed'],
    'amazon'  => ['amazon'],
    'walmart' => ['walmart'],
    'target'  => ['target'],
    default   => ['amazon', 'target', 'walmart'],   // 'all' or no arg
};

// ── DB setup (DB mode only) ───────────────────────────────────────────────────
if (!$jsonMode) {
    $db = getDB();

    // Ensure unique key exists (required for upsert ON DUPLICATE KEY UPDATE)
    try { $db->exec("ALTER TABLE deals ADD UNIQUE KEY uq_product_url (product_url(255))"); }
    catch (\PDOException) {} // already exists

    // Expire old deals
    $exp = $db->prepare("UPDATE deals SET is_active=0
        WHERE scraped_at < NOW() - INTERVAL 3 DAY AND is_featured=0");
    $exp->execute();
    echo "✓ Expired: {$exp->rowCount()} old deals deactivated\n\n";
}

// ── Run scrapers ─────────────────────────────────────────────────────────────
$scrapers = [
    'seed'    => fn() => new SeedScraper(),
    'amazon'  => fn() => new AmazonScraper(),
    'walmart' => fn() => new WalmartScraper(),
    'target'  => fn() => new TargetScraper(),
];

if (!$jsonMode) {
    $before = (int)$db->query("SELECT COUNT(*) FROM deals WHERE is_active=1")->fetchColumn();
}

foreach ($run as $name) {
    if (!$jsonMode) {
        $label = strtoupper($name);
        echo str_repeat('─', 60) . "\n";
        echo " ▶  $label\n";
        echo str_repeat('─', 60) . "\n";
    }
    try {
        $scrapers[$name]()->scrape();
    } catch (\Throwable $e) {
        echo "  ⚠  ERROR: {$e->getMessage()}\n";
        echo "     {$e->getFile()}:{$e->getLine()}\n";
    }
    if (!$jsonMode) echo "\n";
}

// ── JSON mode: output deals as JSON and exit ─────────────────────────────────
if ($jsonMode) {
    $deals = BaseScraper::getJsonDeals();
    echo json_encode($deals);
    exit(0);
}

// ── DB mode: print summary ────────────────────────────────────────────────────
// Reconnect in case MySQL dropped the connection during long scraping
try { $db->query("SELECT 1"); } catch (\PDOException) { $db = getDB(); }

$after = (int)$db->query("SELECT COUNT(*) FROM deals WHERE is_active=1")->fetchColumn();
$rows  = $db->query("
    SELECT store,
           COUNT(*) as deals,
           ROUND(AVG(discount_pct)) as avg_pct,
           MIN(sale_price) as lowest
    FROM deals WHERE is_active=1 AND discount_pct>=50
    GROUP BY store ORDER BY deals DESC
")->fetchAll(\PDO::FETCH_ASSOC);

echo "$line\n RESULTS\n$line\n";
printf(" %-14s  %6s  %10s  %10s\n", 'STORE', 'DEALS', 'AVG DISC', 'LOWEST $');
echo str_repeat('─', 50) . "\n";
foreach ($rows as $r) {
    printf(" %-14s  %6d  %9d%%  %9s\n",
        $r['store'], $r['deals'], $r['avg_pct'],
        '$'.number_format($r['lowest'], 2));
}
echo str_repeat('─', 50) . "\n";
printf(" %-14s  %6d  (+%d new)\n", 'TOTAL', $after, max(0, $after - $before));
echo "\n Done: " . date('Y-m-d H:i:s') . "\n$line\n\n";
