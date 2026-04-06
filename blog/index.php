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

// Category-specific metadata
$catMeta = [
    ''        => ['50OFF Deals Blog — Tips, Guides &amp; Weekly Roundups',
                  'Expert deal guides, weekly Amazon &amp; Target roundups, and shopping tips to save 50% or more. Updated weekly by the 50OFF team.'],
    'roundup' => ['Weekly Deal Roundups — 50%+ Off from Amazon &amp; Target',
                  'Weekly deal roundups with the best 50%+ off offers from Amazon, Target, eBay and 6pm. Curated deals on electronics, kitchen, clothing, and more.'],
    'guide'   => ['Shopping Guides &amp; Tips — How to Find 50%+ Off Deals',
                  'Expert shopping guides on finding 50% off deals on Amazon, Target clearance, headphones, kitchen appliances, bedding and more. Save money every time you shop.'],
];

[$pageTitle, $pageDescription] = $catMeta[$catFilter] ?? $catMeta[''];
$canonicalUrl = 'https://50offsale.com/blog/' . ($catFilter ? '?cat=' . urlencode($catFilter) : '');

$categories = ['' => 'All Posts', 'roundup' => '🔥 Roundups', 'guide' => '📖 Guides'];

// Featured post = first post on page 1 with no filter
$featuredPost = ($page === 1 && !$catFilter && !empty($posts)) ? array_shift($posts) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?> — 50OFF</title>
<meta name="description" content="<?= $pageDescription ?>">
<link rel="canonical" href="<?= h($canonicalUrl) ?>">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
<meta name="geo.region" content="US">
<meta name="geo.placename" content="United States">
<meta name="ICBM" content="39.5,-98.35">
<meta name="DC.coverage" content="USA">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= h($canonicalUrl) ?>">
<meta property="og:title" content="<?= $pageTitle ?>">
<meta property="og:description" content="<?= $pageDescription ?>">
<meta property="og:image" content="https://50offsale.com/assets/img/og-blog.png">
<meta property="og:site_name" content="50OFF">
<meta property="og:locale" content="en_US">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $pageTitle ?>">
<meta name="twitter:description" content="<?= $pageDescription ?>">
<meta name="twitter:image" content="https://50offsale.com/assets/img/og-blog.png">

<!-- Schema: Blog -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Blog",
  "name": "50OFF Deals Blog",
  "url": "https://50offsale.com/blog/",
  "description": "Expert deal guides and weekly roundups — only 50%+ off deals from top US retailers.",
  "inLanguage": "en-US",
  "audience": {"@type":"Audience","geographicArea":{"@type":"Country","name":"United States"}},
  "publisher": {
    "@type": "Organization",
    "name": "50OFF",
    "url": "https://50offsale.com",
    "logo": {"@type":"ImageObject","url":"https://50offsale.com/assets/img/logo.png"}
  }
}
</script>
<!-- Schema: BreadcrumbList -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type":"ListItem","position":1,"name":"Home","item":"https://50offsale.com/"},
    {"@type":"ListItem","position":2,"name":"Blog","item":"https://50offsale.com/blog/"}<?php if($catFilter): ?>,
    {"@type":"ListItem","position":3,"name":"<?= h(ucfirst($catFilter)) ?>","item":"https://50offsale.com/blog/?cat=<?= urlencode($catFilter) ?>"}
    <?php endif; ?>
  ]
}
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏷️</text></svg>">
<style>
.blog-hero{background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);padding:3rem 0 2.5rem;text-align:center;color:#fff}
.blog-hero h1{font-size:clamp(1.5rem,4vw,2.3rem);font-weight:800;margin:0 0 .65rem}
.blog-hero p{opacity:.75;font-size:.95rem;max-width:540px;margin:0 auto 1.25rem}
.blog-hero-stats{display:flex;gap:1.5rem;justify-content:center;flex-wrap:wrap;font-size:.78rem;opacity:.65}
.blog-hero-stat{display:flex;align-items:center;gap:.3rem}

.blog-cat-tabs{display:flex;gap:.5rem;justify-content:center;margin:2rem 0 1.5rem;flex-wrap:wrap}
.blog-cat-tab{padding:.4rem 1rem;border-radius:99px;border:1.5px solid #e5e7eb;font-size:.83rem;font-weight:600;text-decoration:none;color:#374151;background:#fff;transition:all .15s}
.blog-cat-tab:hover,.blog-cat-tab.active{background:#ef4444;border-color:#ef4444;color:#fff}

/* Featured post */
.blog-featured{display:grid;grid-template-columns:1fr 1fr;gap:2rem;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);border:1.5px solid #f3f4f6;margin-bottom:2.5rem}
@media(max-width:700px){.blog-featured{grid-template-columns:1fr}}
.blog-featured-img{width:100%;height:100%;min-height:240px;object-fit:cover}
.blog-featured-placeholder{width:100%;min-height:240px;background:linear-gradient(135deg,#667eea,#f093fb);display:flex;align-items:center;justify-content:center;font-size:4rem}
.blog-featured-body{padding:2rem;display:flex;flex-direction:column;justify-content:center}
.blog-featured-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#ef4444;margin-bottom:.5rem}
.blog-featured-title{font-size:1.3rem;font-weight:800;color:#111827;line-height:1.25;margin:0 0 .75rem;text-decoration:none;display:block}
.blog-featured-title:hover{color:#ef4444}
.blog-featured-excerpt{font-size:.88rem;color:#6b7280;line-height:1.6;margin-bottom:1rem;flex:1}
.blog-featured-meta{font-size:.75rem;color:#9ca3af;display:flex;gap:.65rem;flex-wrap:wrap}
.blog-featured-cta{display:inline-flex;align-items:center;gap:.35rem;padding:.4rem 1rem;background:#ef4444;color:#fff;border-radius:8px;font-size:.82rem;font-weight:700;text-decoration:none;margin-top:.85rem;align-self:flex-start}
.blog-featured-cta:hover{background:#dc2626}

.blog-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.5rem;padding-bottom:2.5rem}
.blog-card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1.5px solid #f3f4f6;display:flex;flex-direction:column;transition:transform .15s,box-shadow .15s}
.blog-card:hover{transform:translateY(-3px);box-shadow:0 6px 20px rgba(0,0,0,.11)}
.blog-card-img{width:100%;height:170px;object-fit:cover}
.blog-card-img-placeholder{width:100%;height:170px;display:flex;align-items:center;justify-content:center;font-size:3.5rem}
.blog-card-img-placeholder.cat-roundup{background:linear-gradient(135deg,#ff6b6b,#ffd93d)}
.blog-card-img-placeholder.cat-guide{background:linear-gradient(135deg,#667eea,#764ba2)}
.blog-card-body{padding:1.2rem;flex:1;display:flex;flex-direction:column}
.blog-card-cat{font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#ef4444;margin-bottom:.35rem;display:flex;align-items:center;justify-content:space-between}
.blog-card-read-time{font-size:.67rem;color:#9ca3af;font-weight:400;text-transform:none;letter-spacing:0}
.blog-card-title{font-size:.97rem;font-weight:700;color:#111827;line-height:1.38;margin:0 0 .5rem;text-decoration:none;display:block}
.blog-card-title:hover{color:#ef4444}
.blog-card-excerpt{font-size:.83rem;color:#6b7280;line-height:1.55;flex:1}
.blog-card-footer{display:flex;align-items:center;justify-content:space-between;margin-top:.85rem;padding-top:.65rem;border-top:1px solid #f3f4f6}
.blog-card-date{font-size:.72rem;color:#9ca3af}
.blog-read-more{font-size:.78rem;font-weight:700;color:#ef4444;text-decoration:none;display:flex;align-items:center;gap:.2rem}
.blog-read-more:hover{text-decoration:underline}

.blog-breadcrumb{font-size:.78rem;color:#9ca3af;margin:1.25rem 0}
.blog-breadcrumb a{color:#6b7280;text-decoration:none}
.blog-breadcrumb a:hover{color:#ef4444}
.blog-pagination{display:flex;gap:.5rem;justify-content:center;padding:1.5rem 0 2rem}
.blog-page-btn{padding:.4rem .85rem;border-radius:6px;border:1.5px solid #e5e7eb;font-size:.83rem;font-weight:600;text-decoration:none;color:#374151;background:#fff}
.blog-page-btn.active,.blog-page-btn:hover{background:#ef4444;border-color:#ef4444;color:#fff}
.blog-empty{text-align:center;padding:3rem;color:#9ca3af;font-size:1rem}

/* Bottom internal links */
.blog-internal-links{background:#f9fafb;border-radius:12px;padding:1.75rem;margin:2rem 0;border:1.5px solid #f3f4f6}
.blog-internal-links h3{font-size:.85rem;font-weight:700;color:#374151;margin:0 0 1rem;text-transform:uppercase;letter-spacing:.04em}
.bil-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.65rem}
.bil-link{display:flex;align-items:center;gap:.4rem;padding:.45rem .75rem;background:#fff;border:1.5px solid #e5e7eb;border-radius:8px;font-size:.8rem;font-weight:600;text-decoration:none;color:#374151;transition:all .15s}
.bil-link:hover{background:#ef4444;border-color:#ef4444;color:#fff}
</style>
</head>
<body>
<?php require_once ROOT . '/includes/header.php'; ?>

<div class="blog-hero">
    <div class="container">
        <h1><?= $catFilter === 'roundup' ? '🔥 Weekly Deal Roundups' : ($catFilter === 'guide' ? '📖 Shopping Guides' : '📝 50OFF Deals Blog') ?></h1>
        <p><?= $catFilter === 'roundup'
            ? 'Weekly curated lists of the best deals at 50% off or more — from Amazon, Target, eBay, and 6pm.'
            : ($catFilter === 'guide'
                ? 'In-depth shopping guides to help you find 50%+ off deals and never overpay.'
                : 'Weekly roundups, buying guides &amp; tips to help you save 50% or more every time you shop.') ?></p>
        <div class="blog-hero-stats">
            <span class="blog-hero-stat">✅ <?= $totalPosts ?> articles</span>
            <span class="blog-hero-stat">🔄 Updated weekly</span>
            <span class="blog-hero-stat">🇺🇸 US deals only</span>
        </div>
    </div>
</div>

<main class="site-main">
<div class="container">

<nav class="blog-breadcrumb" aria-label="Breadcrumb">
    <a href="/">Home</a> ›
    <a href="/blog/">Blog</a>
    <?= $catFilter ? ' › <strong>' . h(ucfirst($catFilter)) . '</strong>' : '' ?>
</nav>

<div class="blog-cat-tabs">
<?php foreach ($categories as $slug => $label): ?>
<a href="/blog/<?= $slug ? '?cat='.urlencode($slug) : '' ?>" class="blog-cat-tab <?= $catFilter===$slug?'active':'' ?>"><?= h($label) ?></a>
<?php endforeach; ?>
</div>

<!-- Featured post (first post, no filter, page 1) -->
<?php if ($featuredPost): ?>
<div class="blog-featured">
    <?php if ($featuredPost['og_image']): ?>
    <img src="<?= h($featuredPost['og_image']) ?>" alt="<?= h($featuredPost['title']) ?>" class="blog-featured-img" loading="eager">
    <?php else: ?>
    <div class="blog-featured-placeholder">🔥</div>
    <?php endif; ?>
    <div class="blog-featured-body">
        <span class="blog-featured-label">⭐ Featured Post · <?= h(ucfirst($featuredPost['category'])) ?></span>
        <a href="/blog/<?= h($featuredPost['slug']) ?>" class="blog-featured-title"><?= h($featuredPost['title']) ?></a>
        <p class="blog-featured-excerpt"><?= h(mb_substr($featuredPost['excerpt']??'', 0, 180)) ?>…</p>
        <div class="blog-featured-meta">
            <span>📅 <?= date('F j, Y', strtotime($featuredPost['published_at'])) ?></span>
            <span>👁 <?= number_format($featuredPost['view_count']) ?> views</span>
        </div>
        <a href="/blog/<?= h($featuredPost['slug']) ?>" class="blog-featured-cta">Read Guide → </a>
    </div>
</div>
<?php endif; ?>

<?php if (empty($posts) && !$featuredPost): ?>
<div class="blog-empty">No posts yet — check back soon!</div>
<?php elseif (!empty($posts)): ?>
<div class="blog-grid">
<?php foreach ($posts as $post):
    $emoji    = $post['category']==='roundup' ? '🔥' : '📖';
    $words    = str_word_count(strip_tags($post['excerpt']??''));
    $readMins = max(3, (int)round($words * 10 / 220)); // Rough estimate from excerpt
?>
<article class="blog-card" itemscope itemtype="https://schema.org/Article">
    <meta itemprop="datePublished" content="<?= date('c', strtotime($post['published_at'])) ?>">
    <meta itemprop="author" content="50OFF Team">
    <?php if ($post['og_image']): ?>
    <img src="<?= h($post['og_image']) ?>" alt="<?= h($post['title']) ?>" class="blog-card-img" loading="lazy" itemprop="image">
    <?php else: ?>
    <div class="blog-card-img-placeholder cat-<?= h($post['category']) ?>"><?= $emoji ?></div>
    <?php endif; ?>
    <div class="blog-card-body">
        <div class="blog-card-cat">
            <span><?= h(ucfirst($post['category'])) ?></span>
            <span class="blog-card-read-time">⏱ ~<?= $readMins ?>+ min</span>
        </div>
        <a href="/blog/<?= h($post['slug']) ?>" class="blog-card-title" itemprop="name headline"><?= h($post['title']) ?></a>
        <p class="blog-card-excerpt"><?= h(mb_substr($post['excerpt']??'',0,125)) ?>…</p>
        <div class="blog-card-footer">
            <span class="blog-card-date"><?= date('M j, Y',strtotime($post['published_at'])) ?></span>
            <a href="/blog/<?= h($post['slug']) ?>" class="blog-read-more">Read more →</a>
        </div>
    </div>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($totalPages > 1): ?>
<nav class="blog-pagination" aria-label="Pagination">
<?php if ($page>1): ?><a href="/blog/?<?= http_build_query(['cat'=>$catFilter,'page'=>$page-1]) ?>" class="blog-page-btn">← Prev</a><?php endif; ?>
<?php for($i=1;$i<=$totalPages;$i++): ?><a href="/blog/?<?= http_build_query(['cat'=>$catFilter,'page'=>$i]) ?>" class="blog-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
<?php if ($page<$totalPages): ?><a href="/blog/?<?= http_build_query(['cat'=>$catFilter,'page'=>$page+1]) ?>" class="blog-page-btn">Next →</a><?php endif; ?>
</nav>
<?php endif; ?>

<!-- Internal links — deal categories + cross-links -->
<div class="blog-internal-links">
    <h3>Browse Deals by Category</h3>
    <div class="bil-grid">
        <a href="/?category=electronics" class="bil-link">📱 Electronics Deals</a>
        <a href="/?category=kitchen" class="bil-link">🍳 Kitchen Deals</a>
        <a href="/?category=clothing" class="bil-link">👗 Clothing Deals</a>
        <a href="/?category=home" class="bil-link">🏠 Home Deals</a>
        <a href="/?category=toys" class="bil-link">🧸 Toy Deals</a>
        <a href="/?category=sports" class="bil-link">⚽ Sports Deals</a>
        <a href="/?category=beauty" class="bil-link">💄 Beauty Deals</a>
        <a href="/?category=health" class="bil-link">💊 Health Deals</a>
        <a href="/?store=amazon" class="bil-link">🛒 Amazon Deals</a>
        <a href="/?store=target" class="bil-link">🎯 Target Deals</a>
        <a href="/?store=ebay" class="bil-link">🔴 eBay Deals</a>
        <a href="/?store=6pm" class="bil-link">👠 6pm Deals</a>
    </div>
</div>

</div>
</main>

<?php require_once ROOT . '/includes/footer.php'; ?>
</body>
</html>
