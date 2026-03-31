<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/blog_functions.php';

$perPage   = 9;
$page      = max(1, (int) getParam('page', 1));
$catFilter = getParam('cat', '');
$offset    = ($page - 1) * $perPage;

$posts      = getBlogPosts($perPage, $offset, $catFilter);
$totalPosts = countBlogPosts($catFilter);
$totalPages = (int) ceil($totalPosts / $perPage);

$pageTitle       = 'Deals Blog — Tips, Guides & Weekly Roundups';
$pageDescription = 'Expert deal guides, weekly Amazon & Target roundups, and shopping tips to help you save 50% or more. Updated weekly by the 50OFF team.';
$canonicalUrl    = 'https://50offsale.com/blog/';
$categories      = ['' => 'All Posts', 'roundup' => 'Roundups', 'guide' => 'Guides'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?> — 50OFF</title>
<meta name="description" content="<?= h($pageDescription) ?>">
<link rel="canonical" href="<?= h($canonicalUrl) ?>">
<meta name="geo.region" content="US">
<meta name="geo.placename" content="United States">
<meta name="ICBM" content="39.5,-98.35">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= h($canonicalUrl) ?>">
<meta property="og:title" content="<?= h($pageTitle) ?>">
<meta property="og:description" content="<?= h($pageDescription) ?>">
<meta property="og:image" content="https://50offsale.com/assets/img/og-blog.png">
<meta property="og:site_name" content="50OFF">
<meta property="og:locale" content="en_US">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($pageTitle) ?>">
<meta name="twitter:description" content="<?= h($pageDescription) ?>">
<meta name="twitter:image" content="https://50offsale.com/assets/img/og-blog.png">
<script type="application/ld+json">{"@context":"https://schema.org","@type":"Blog","name":"50OFF Deals Blog","url":"https://50offsale.com/blog/","description":"Expert deal guides and weekly roundups from 50OFF.","publisher":{"@type":"Organization","name":"50OFF","url":"https://50offsale.com"}}</script>
<script type="application/ld+json">{"@context":"https://schema.org","@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"name":"Home","item":"https://50offsale.com/"},{"@type":"ListItem","position":2,"name":"Blog","item":"https://50offsale.com/blog/"}]}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏷️</text></svg>">
<style>
.blog-hero{background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);padding:3rem 0 2.5rem;text-align:center;color:#fff}
.blog-hero h1{font-size:clamp(1.6rem,4vw,2.4rem);font-weight:800;margin:0 0 .75rem}
.blog-hero p{opacity:.75;font-size:1rem;max-width:520px;margin:0 auto}
.blog-cat-tabs{display:flex;gap:.5rem;justify-content:center;margin:2rem 0 1.5rem;flex-wrap:wrap}
.blog-cat-tab{padding:.45rem 1.1rem;border-radius:99px;border:1.5px solid #e5e7eb;font-size:.85rem;font-weight:600;text-decoration:none;color:#374151;background:#fff;transition:all .15s}
.blog-cat-tab:hover,.blog-cat-tab.active{background:#ef4444;border-color:#ef4444;color:#fff}
.blog-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.5rem;padding-bottom:2.5rem}
.blog-card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);border:1px solid #f3f4f6;display:flex;flex-direction:column;transition:transform .15s,box-shadow .15s}
.blog-card:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.12)}
.blog-card-img{width:100%;height:160px;object-fit:cover}
.blog-card-img-placeholder{width:100%;height:160px;background:linear-gradient(135deg,#667eea 0%,#f093fb 100%);display:flex;align-items:center;justify-content:center;font-size:3rem}
.blog-card-body{padding:1.25rem;flex:1;display:flex;flex-direction:column}
.blog-card-cat{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#ef4444;margin-bottom:.4rem}
.blog-card-title{font-size:1rem;font-weight:700;color:#111827;line-height:1.4;margin:0 0 .6rem;text-decoration:none;display:block}
.blog-card-title:hover{color:#ef4444}
.blog-card-excerpt{font-size:.875rem;color:#6b7280;line-height:1.55;flex:1}
.blog-card-footer{display:flex;align-items:center;justify-content:space-between;margin-top:1rem;padding-top:.75rem;border-top:1px solid #f3f4f6}
.blog-card-date{font-size:.75rem;color:#9ca3af}
.blog-read-more{font-size:.8rem;font-weight:600;color:#ef4444;text-decoration:none}
.blog-read-more:hover{text-decoration:underline}
.blog-breadcrumb{font-size:.8rem;color:#9ca3af;margin:1.5rem 0}
.blog-breadcrumb a{color:#6b7280;text-decoration:none}
.blog-breadcrumb a:hover{color:#ef4444}
.blog-pagination{display:flex;gap:.5rem;justify-content:center;padding:1.5rem 0 2rem}
.blog-page-btn{padding:.4rem .85rem;border-radius:6px;border:1.5px solid #e5e7eb;font-size:.85rem;font-weight:600;text-decoration:none;color:#374151;background:#fff}
.blog-page-btn.active,.blog-page-btn:hover{background:#ef4444;border-color:#ef4444;color:#fff}
.blog-empty{text-align:center;padding:3rem;color:#9ca3af;font-size:1rem}
</style>
</head>
<body>
<header class="site-header">
<div class="container"><div class="header-inner">
<a href="/" class="logo"><div class="logo-icon">50</div><div class="logo-text-wrap"><span class="logo-name">50<span class="logo-off">OFF</span></span><span class="logo-tag">Find deals, not products</span></div></a>
<form class="search-form" action="/search.php" method="GET" role="search"><div class="search-wrap"><svg class="search-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><input type="search" name="q" class="search-input" placeholder="Search 50%+ deals…" autocomplete="off"><button type="submit" class="search-btn">Search</button></div></form>
<nav class="store-nav" aria-label="Navigation"><a href="/" class="store-pill">All Deals</a><a href="/?store=amazon" class="store-pill">🛒 Amazon</a><a href="/?store=target" class="store-pill">🎯 Target</a><a href="/blog/" class="store-pill active">📝 Blog</a></nav>
</div></div>
</header>
<div class="blog-hero"><div class="container"><h1>📝 Deals Blog</h1><p>Weekly roundups, buying guides &amp; tips to help you save 50% or more every time you shop.</p></div></div>
<main class="site-main"><div class="container">
<nav class="blog-breadcrumb" aria-label="Breadcrumb"><a href="/">Home</a> › <strong>Blog</strong><?= $catFilter ? ' › ' . h(ucfirst($catFilter)) : '' ?></nav>
<div class="blog-cat-tabs">
<?php foreach ($categories as $slug => $label): ?>
<a href="/blog/<?= $slug ? '?cat='.urlencode($slug) : '' ?>" class="blog-cat-tab <?= $catFilter===$slug?'active':'' ?>"><?= h($label) ?></a>
<?php endforeach; ?>
</div>
<?php if (empty($posts)): ?>
<div class="blog-empty">No posts yet — check back soon!</div>
<?php else: ?>
<div class="blog-grid">
<?php foreach ($posts as $post): $emoji = $post['category']==='roundup'?'🔥':'📖'; ?>
<article class="blog-card">
<?php if ($post['og_image']): ?><img src="<?= h($post['og_image']) ?>" alt="<?= h($post['title']) ?>" class="blog-card-img" loading="lazy">
<?php else: ?><div class="blog-card-img-placeholder"><?= $emoji ?></div><?php endif; ?>
<div class="blog-card-body">
<span class="blog-card-cat"><?= h(ucfirst($post['category'])) ?></span>
<a href="/blog/<?= h($post['slug']) ?>" class="blog-card-title"><?= h($post['title']) ?></a>
<p class="blog-card-excerpt"><?= h(mb_substr($post['excerpt']??'',0,130)) ?>…</p>
<div class="blog-card-footer"><span class="blog-card-date"><?= date('M j, Y',strtotime($post['published_at'])) ?></span><a href="/blog/<?= h($post['slug']) ?>" class="blog-read-more">Read more →</a></div>
</div>
</article>
<?php endforeach; ?>
</div>
<?php if ($totalPages > 1): ?>
<nav class="blog-pagination" aria-label="Pagination">
<?php if ($page>1): ?><a href="/blog/?<?= http_build_query(['cat'=>$catFilter,'page'=>$page-1]) ?>" class="blog-page-btn">← Prev</a><?php endif; ?>
<?php for($i=1;$i<=$totalPages;$i++): ?><a href="/blog/?<?= http_build_query(['cat'=>$catFilter,'page'=>$i]) ?>" class="blog-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
<?php if ($page<$totalPages): ?><a href="/blog/?<?= http_build_query(['cat'=>$catFilter,'page'=>$page+1]) ?>" class="blog-page-btn">Next →</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
</div></main>
<?php require_once ROOT . '/includes/footer.php'; ?>
</body>
</html>
