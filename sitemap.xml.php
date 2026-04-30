<?php
define('ROOT', __DIR__);
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/blog_functions.php';

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';

$base  = 'https://50offsale.com';
$today = date('Y-m-d');

$stores     = ['amazon', 'target', 'ebay', '6pm', 'bestbuy'];
$categories = ['electronics', 'clothing', 'home', 'kitchen', 'toys', 'sports', 'beauty', 'health', 'tools', 'pets'];

$staticPages = [
    ['url' => '/',           'priority' => '1.0', 'freq' => 'hourly', 'mod' => $today],
    ['url' => '/blog/',      'priority' => '0.8', 'freq' => 'daily',  'mod' => $today],
    ['url' => '/search.php', 'priority' => '0.5', 'freq' => 'weekly', 'mod' => $today],
    ['url' => '/about',      'priority' => '0.4', 'freq' => 'monthly','mod' => $today],
];

// Store pages
foreach ($stores as $s) {
    $staticPages[] = ['url' => '/?store=' . $s, 'priority' => '0.8', 'freq' => 'hourly', 'mod' => $today];
}

// Category pages
foreach ($categories as $c) {
    $staticPages[] = ['url' => '/?category=' . $c, 'priority' => '0.7', 'freq' => 'daily', 'mod' => $today];
}

// Store × Category cross pages (high-value long-tail)
$topCrosses = [
    ['store' => 'amazon',  'cat' => 'electronics'],
    ['store' => 'amazon',  'cat' => 'kitchen'],
    ['store' => 'amazon',  'cat' => 'clothing'],
    ['store' => 'amazon',  'cat' => 'home'],
    ['store' => 'amazon',  'cat' => 'toys'],
    ['store' => 'target',  'cat' => 'clothing'],
    ['store' => 'target',  'cat' => 'home'],
    ['store' => 'target',  'cat' => 'toys'],
    ['store' => 'target',  'cat' => 'beauty'],
    ['store' => 'ebay',    'cat' => 'electronics'],
    ['store' => '6pm',     'cat' => 'clothing'],
    ['store' => 'bestbuy', 'cat' => 'electronics'],
];
foreach ($topCrosses as $cross) {
    $staticPages[] = ['url' => '/?store=' . $cross['store'] . '&category=' . $cross['cat'], 'priority' => '0.6', 'freq' => 'daily', 'mod' => $today];
}

$blogPosts = getBlogPosts(200, 0);
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

<?php foreach ($staticPages as $p): ?>
  <url>
    <loc><?= $base . htmlspecialchars($p['url']) ?></loc>
    <lastmod><?= $p['mod'] ?></lastmod>
    <changefreq><?= $p['freq'] ?></changefreq>
    <priority><?= $p['priority'] ?></priority>
  </url>
<?php endforeach; ?>

<?php foreach ($blogPosts as $post):
    $postMod = !empty($post['updated_at']) ? date('Y-m-d', strtotime($post['updated_at'])) : date('Y-m-d', strtotime($post['published_at']));
?>
  <url>
    <loc><?= $base ?>/blog/<?= htmlspecialchars($post['slug']) ?></loc>
    <lastmod><?= $postMod ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>
<?php endforeach; ?>

</urlset>
