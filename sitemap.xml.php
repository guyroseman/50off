<?php
define('ROOT', __DIR__);
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/blog_functions.php';

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';

$base = 'https://50offsale.com';
$today = date('Y-m-d');

$staticPages = [
    ['url' => '/',                    'priority' => '1.0', 'freq' => 'hourly',  'mod' => $today],
    ['url' => '/blog/',               'priority' => '0.8', 'freq' => 'daily',   'mod' => $today],
    ['url' => '/search.php',          'priority' => '0.5', 'freq' => 'weekly',  'mod' => $today],
    ['url' => '/?store=amazon',       'priority' => '0.8', 'freq' => 'hourly',  'mod' => $today],
    ['url' => '/?store=target',       'priority' => '0.8', 'freq' => 'hourly',  'mod' => $today],
    ['url' => '/?category=electronics','priority'=> '0.7', 'freq' => 'daily',   'mod' => $today],
    ['url' => '/?category=kitchen',   'priority' => '0.7', 'freq' => 'daily',   'mod' => $today],
    ['url' => '/?category=clothing',  'priority' => '0.7', 'freq' => 'daily',   'mod' => $today],
    ['url' => '/?category=home',      'priority' => '0.7', 'freq' => 'daily',   'mod' => $today],
    ['url' => '/?category=toys',      'priority' => '0.6', 'freq' => 'daily',   'mod' => $today],
    ['url' => '/?category=beauty',    'priority' => '0.6', 'freq' => 'daily',   'mod' => $today],
    ['url' => '/?category=sports',    'priority' => '0.6', 'freq' => 'daily',   'mod' => $today],
    ['url' => '/?category=health',    'priority' => '0.6', 'freq' => 'daily',   'mod' => $today],
];

$blogPosts = getBlogPosts(100, 0);
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

<?php foreach ($blogPosts as $post): ?>
  <url>
    <loc><?= $base ?>/blog/<?= htmlspecialchars($post['slug']) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($post['published_at'])) ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>
<?php endforeach; ?>

</urlset>
