#!/usr/bin/env php
<?php
/**
 * reset.php — Clean all deals and re-run scrapers from scratch
 *
 * USAGE:
 *   php scraper/reset.php          # wipe + run all working scrapers
 *   php scraper/reset.php clean    # wipe only (no scraping)
 */

define('ROOT', dirname(__DIR__));
require_once ROOT . '/includes/db.php';

$db = getDB();

echo "\n══════════════════════════════════════════════════════════\n";
echo " 50OFF Reset — " . date('Y-m-d H:i:s') . "\n";
echo "══════════════════════════════════════════════════════════\n\n";

// ── 1. Migrate store column if still ENUM ────────────────────────────────
echo "→ Migrating store column to VARCHAR...\n";
try {
    $db->exec("ALTER TABLE deals MODIFY COLUMN store VARCHAR(50) NOT NULL DEFAULT 'other'");
    echo "  ✓ Store column migrated\n";
} catch (\PDOException $e) {
    echo "  (already VARCHAR or skipped)\n";
}

// ── 2. Count existing deals ──────────────────────────────────────────────
$before = (int)$db->query("SELECT COUNT(*) FROM deals")->fetchColumn();
echo "\n→ Current deals in DB: {$before}\n";

// ── 3. Wipe all deals ───────────────────────────────────────────────────
echo "→ Deleting all deals...\n";
$db->exec("DELETE FROM deals");
$db->exec("DELETE FROM scraper_log");
echo "  ✓ Deleted {$before} deals + scraper logs\n";

// ── 4. Reset auto-increment ─────────────────────────────────────────────
$db->exec("ALTER TABLE deals AUTO_INCREMENT = 1");
echo "  ✓ Auto-increment reset\n";

$mode = $argv[1] ?? 'run';
if ($mode === 'clean') {
    echo "\n Done (clean only). Run `php scraper/run.php` to scrape.\n\n";
    exit(0);
}

// ── 5. Run scrapers ──────────────────────────────────────────────────────
echo "\n→ Running scrapers...\n\n";

require_once __DIR__ . '/BaseScraper.php';
require_once __DIR__ . '/AmazonScraper.php';
require_once __DIR__ . '/TargetScraper.php';
require_once __DIR__ . '/EbayScraper.php';

$scrapers = [
    'amazon'    => fn() => new AmazonScraper(),
    'target'    => fn() => new TargetScraper(),
    'ebay'      => fn() => new EbayScraper(),
];

foreach ($scrapers as $name => $factory) {
    echo str_repeat('─', 60) . "\n ▶  " . strtoupper($name) . "\n" . str_repeat('─', 60) . "\n";
    try {
        $factory()->scrape();
    } catch (\Throwable $e) {
        echo "  ⚠  ERROR: {$e->getMessage()}\n";
    }
    echo "\n";
}

// ── 6. Summary ───────────────────────────────────────────────────────────
$after = (int)$db->query("SELECT COUNT(*) FROM deals WHERE is_active=1")->fetchColumn();
$rows  = $db->query("
    SELECT store, COUNT(*) as deals, ROUND(AVG(discount_pct)) as avg_pct, MAX(discount_pct) as max_pct
    FROM deals WHERE is_active=1 AND discount_pct>=50
    GROUP BY store ORDER BY deals DESC
")->fetchAll(\PDO::FETCH_ASSOC);

$line = str_repeat('═', 60);
echo "\n{$line}\n RESULTS\n{$line}\n";
printf(" %-14s  %6s  %8s  %8s\n", 'STORE', 'DEALS', 'AVG OFF', 'MAX OFF');
echo str_repeat('─', 48) . "\n";
foreach ($rows as $r) {
    printf(" %-14s  %6d  %7d%%  %7d%%\n", $r['store'], $r['deals'], $r['avg_pct'], $r['max_pct']);
}
echo str_repeat('─', 48) . "\n";
printf(" %-14s  %6d\n", 'TOTAL', $after);
echo "\n ✅ Done: " . date('Y-m-d H:i:s') . "\n{$line}\n\n";
