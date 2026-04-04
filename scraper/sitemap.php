<?php
/**
 * scraper/sitemap.php — Generate sitemap.xml
 * Run via cron: php /path/to/scraper/sitemap.php
 * Or via web: add to Hostinger cron: php scraper/sitemap.php
 */

require_once __DIR__ . '/../includes/db.php';

$baseUrl    = 'https://50offsale.com';
$outputFile = __DIR__ . '/../sitemap.xml';

$db   = getDB();
$stmt = $db->query("SELECT id, scraped_at FROM deals WHERE is_active=1 ORDER BY scraped_at DESC LIMIT 5000");
$deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$static = [
    ['loc' => '/',            'priority' => '1.0', 'changefreq' => 'hourly'],
    ['loc' => '/about.php',   'priority' => '0.5', 'changefreq' => 'monthly'],
    ['loc' => '/privacy.php', 'priority' => '0.3', 'changefreq' => 'yearly'],
    ['loc' => '/terms.php',   'priority' => '0.3', 'changefreq' => 'yearly'],
    ['loc' => '/search.php',  'priority' => '0.5', 'changefreq' => 'daily'],
];

$categories = ['electronics','clothing','home','kitchen','toys','sports','beauty','health','tools','pets','gaming','office','baby','automotive'];
foreach ($categories as $cat) {
    $static[] = ['loc' => '/?category=' . $cat, 'priority' => '0.7', 'changefreq' => 'hourly'];
}

$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($static as $page) {
    $xml .= "  <url>\n";
    $xml .= "    <loc>" . htmlspecialchars($baseUrl . $page['loc'], ENT_XML1) . "</loc>\n";
    $xml .= "    <changefreq>{$page['changefreq']}</changefreq>\n";
    $xml .= "    <priority>{$page['priority']}</priority>\n";
    $xml .= "  </url>\n";
}

foreach ($deals as $d) {
    $lastmod = date('Y-m-d', strtotime($d['scraped_at']));
    $xml .= "  <url>\n";
    $xml .= "    <loc>" . htmlspecialchars($baseUrl . '/deal.php?id=' . $d['id'], ENT_XML1) . "</loc>\n";
    $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
    $xml .= "    <changefreq>daily</changefreq>\n";
    $xml .= "    <priority>0.6</priority>\n";
    $xml .= "  </url>\n";
}

$xml .= '</urlset>' . "\n";

file_put_contents($outputFile, $xml);
$count = count($deals) + count($static);
echo "Sitemap written: {$outputFile} ({$count} URLs)\n";
