<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/blog_functions.php';

$slug = trim(getParam('slug', ''));
if (!$slug) { header('Location: /blog/'); exit; }

$post = getBlogPost($slug);
if (!$post) {
    http_response_code(404);
    $pageTitle = 'Post Not Found';
    include ROOT . '/includes/header.php';
    echo '<div class="container" style="padding:4rem 1rem;text-align:center"><h1>Post not found</h1><p><a href="/blog/">← Back to Blog</a></p></div>';
    include ROOT . '/includes/footer.php';
    exit;
}

$related      = getRelatedPosts($post['id'], $post['category'], 3);
$metaTitle    = $post['meta_title'] ?: ($post['title'] . ' — 50OFF Blog');
$metaDesc     = $post['meta_desc'] ?: $post['excerpt'];
$ogImage      = $post['og_image'] ?: 'https://50offsale.com/assets/img/og-blog.png';
$canonicalUrl = 'https://50offsale.com/blog/' . urlencode($post['slug']);
$pubDate      = date('c', strtotime($post['published_at']));
$modDate      = date('c', strtotime($post['updated_at'] ?? $post['published_at']));

// Render deal placeholders: <!-- DEALS:category:N --> and <!-- STORE:store:N -->
function renderDealPlaceholders(string $content): string {
    $content = preg_replace_callback(
        '/<!--\s*DEALS:([a-z]+):(\d+)\s*-->/',
        function($m) {
            $deals = getDeals(['category' => $m[1], 'limit' => (int)$m[2], 'sort' => 'discount']);
            return renderInlineDeals($deals);
        },
        $content
    );
    $content = preg_replace_callback(
        '/<!--\s*STORE:([a-z]+):(\d+)\s*-->/',
        function($m) {
            $deals = getDeals(['store' => $m[1], 'limit' => (int)$m[2], 'sort' => 'discount']);
            return renderInlineDeals($deals);
        },
        $content
    );
    return $content;
}

function renderInlineDeals(array $deals): string {
    if (empty($deals)) return '<p class="no-deals-notice">Check back soon — we\'re updating deals now.</p>';
    $html = '<div class="post-deals-grid">';
    foreach ($deals as $d) {
        $img   = $d['image_url'] ? '<img src="'.htmlspecialchars($d['image_url']).'" alt="'.htmlspecialchars($d['title']).'" loading="lazy">' : '<div class="post-deal-no-img">🏷️</div>';
        $title = mb_substr($d['title'], 0, 80) . (mb_strlen($d['title']) > 80 ? '…' : '');
        $link  = $d['affiliate_url'] ?: $d['product_url'];
        $store = ucfirst($d['store']);
        $html .= '<a href="/go/'.intval($d['id']).'" class="post-deal-card" target="_blank" rel="nofollow sponsored">';
        $html .= '<div class="post-deal-img">'.$img.'</div>';
        $html .= '<div class="post-deal-info">';
        $html .= '<span class="post-deal-badge">'.intval($d['discount_pct']).'% OFF</span>';
        $html .= '<span class="post-deal-title">'.htmlspecialchars($title).'</span>';
        $html .= '<span class="post-deal-price">$'.number_format($d['sale_price'],2).' <s style="color:#9ca3af;font-size:.8em">$'.number_format($d['original_price'],2).'</s></span>';
        $html .= '<span class="post-deal-store">'.$store.'</span>';
        $html .= '</div></a>';
    }
    $html .= '</div>';
    return $html;
}

$renderedContent = renderDealPlaceholders($post['content']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($metaTitle) ?></title>
<meta name="description" content="<?= h($metaDesc) ?>">
<link rel="canonical" href="<?= h($canonicalUrl) ?>">
<meta name="author" content="<?= h($post['author'] ?: '50OFF Team') ?>">
<meta name="robots" content="index, follow">

<!-- Geo / US targeting -->
<meta name="geo.region" content="US">
<meta name="geo.placename" content="United States">
<meta name="ICBM" content="39.5,-98.35">

<!-- Open Graph / Article -->
<meta property="og:type" content="article">
<meta property="og:url" content="<?= h($canonicalUrl) ?>">
<meta property="og:title" content="<?= h($metaTitle) ?>">
<meta property="og:description" content="<?= h($metaDesc) ?>">
<meta property="og:image" content="<?= h($ogImage) ?>">
<meta property="og:site_name" content="50OFF">
<meta property="og:locale" content="en_US">
<meta property="article:published_time" content="<?= h($pubDate) ?>">
<meta property="article:modified_time" content="<?= h($modDate) ?>">
<meta property="article:author" content="50OFF Team">
<meta property="article:section" content="<?= h(ucfirst($post['category'])) ?>">
<?php if ($post['tags']): foreach (explode(',', $post['tags']) as $tag): ?>
<meta property="article:tag" content="<?= h(trim($tag)) ?>">
<?php endforeach; endif; ?>

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($metaTitle) ?>">
<meta name="twitter:description" content="<?= h($metaDesc) ?>">
<meta name="twitter:image" content="<?= h($ogImage) ?>">

<!-- Schema.org Article -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": <?= json_encode($post['title']) ?>,
  "description": <?= json_encode($metaDesc) ?>,
  "image": <?= json_encode($ogImage) ?>,
  "datePublished": <?= json_encode($pubDate) ?>,
  "dateModified": <?= json_encode($modDate) ?>,
  "author": {"@type": "Organization", "name": "50OFF Team", "url": "https://50offsale.com"},
  "publisher": {
    "@type": "Organization",
    "name": "50OFF",
    "url": "https://50offsale.com",
    "logo": {"@type": "ImageObject", "url": "https://50offsale.com/assets/img/logo.png"}
  },
  "mainEntityOfPage": {"@type": "WebPage", "@id": <?= json_encode($canonicalUrl) ?>}
}
</script>

<!-- BreadcrumbList -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type":"ListItem","position":1,"name":"Home","item":"https://50offsale.com/"},
    {"@type":"ListItem","position":2,"name":"Blog","item":"https://50offsale.com/blog/"},
    {"@type":"ListItem","position":3,"name":<?= json_encode($post['title']) ?>,"item":<?= json_encode($canonicalUrl) ?>}
  ]
}
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏷️</text></svg>">
<style>
.post-wrap{max-width:780px;margin:0 auto;padding:2rem 1rem 3rem}
.post-breadcrumb{font-size:.8rem;color:#9ca3af;margin-bottom:1.5rem}
.post-breadcrumb a{color:#6b7280;text-decoration:none}
.post-breadcrumb a:hover{color:#ef4444}
.post-header{margin-bottom:2rem}
.post-cat-badge{display:inline-block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#ef4444;background:#fef2f2;padding:.25rem .65rem;border-radius:99px;margin-bottom:.75rem}
.post-header h1{font-size:clamp(1.5rem,4vw,2.2rem);font-weight:800;color:#111827;line-height:1.25;margin:0 0 1rem}
.post-meta{display:flex;align-items:center;gap:1rem;font-size:.8rem;color:#9ca3af;flex-wrap:wrap}
.post-meta-author{font-weight:600;color:#374151}
.post-hero-img{width:100%;border-radius:12px;margin-bottom:2rem;max-height:400px;object-fit:cover}
.post-body{font-size:1rem;line-height:1.75;color:#374151}
.post-body h2{font-size:1.35rem;font-weight:700;color:#111827;margin:2rem 0 .75rem;padding-top:.5rem;border-top:2px solid #f3f4f6}
.post-body h3{font-size:1.1rem;font-weight:700;color:#111827;margin:1.5rem 0 .5rem}
.post-body p{margin:0 0 1.1rem}
.post-body ul,.post-body ol{margin:0 0 1.1rem;padding-left:1.5rem}
.post-body li{margin-bottom:.4rem}
.post-body a{color:#ef4444;text-decoration:underline}
.post-body strong{color:#111827}
.post-body blockquote{border-left:4px solid #ef4444;padding:.75rem 1.25rem;margin:1.5rem 0;background:#fef2f2;border-radius:0 8px 8px 0;font-style:italic;color:#374151}
.post-deals-section{margin:2rem 0}
.post-deals-section h2{font-size:1.2rem;font-weight:700;color:#111827;margin-bottom:1rem}
.post-deals-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem;margin:1rem 0 1.5rem}
.post-deal-card{display:flex;flex-direction:column;border:1.5px solid #f3f4f6;border-radius:10px;overflow:hidden;background:#fff;text-decoration:none;color:inherit;transition:box-shadow .15s,transform .15s}
.post-deal-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.1);transform:translateY(-1px)}
.post-deal-img{width:100%;height:130px;overflow:hidden;background:#f9fafb;display:flex;align-items:center;justify-content:center}
.post-deal-img img{width:100%;height:100%;object-fit:contain;padding:.5rem}
.post-deal-no-img{font-size:2.5rem}
.post-deal-info{padding:.75rem;display:flex;flex-direction:column;gap:.3rem}
.post-deal-badge{font-size:.7rem;font-weight:800;background:#ef4444;color:#fff;padding:.2rem .5rem;border-radius:4px;align-self:flex-start}
.post-deal-title{font-size:.8rem;font-weight:600;color:#111827;line-height:1.35}
.post-deal-price{font-size:.9rem;font-weight:700;color:#ef4444}
.post-deal-store{font-size:.7rem;color:#9ca3af}
.no-deals-notice{color:#9ca3af;font-size:.9rem;padding:1rem;background:#f9fafb;border-radius:8px;text-align:center}
.post-cta-box{background:linear-gradient(135deg,#1a1a2e,#0f3460);border-radius:12px;padding:1.75rem;text-align:center;color:#fff;margin:2.5rem 0}
.post-cta-box h3{font-size:1.2rem;font-weight:700;margin:0 0 .5rem}
.post-cta-box p{opacity:.75;font-size:.9rem;margin:0 0 1rem}
.post-cta-box a{display:inline-block;background:#ef4444;color:#fff;padding:.6rem 1.4rem;border-radius:8px;font-weight:700;font-size:.9rem;text-decoration:none}
.post-cta-box a:hover{background:#dc2626}
.related-posts{margin-top:3rem;padding-top:2rem;border-top:2px solid #f3f4f6}
.related-posts h2{font-size:1.1rem;font-weight:700;margin-bottom:1.25rem;color:#111827}
.related-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem}
.related-card{border:1.5px solid #f3f4f6;border-radius:10px;overflow:hidden;text-decoration:none;color:inherit;background:#fff;transition:box-shadow .15s}
.related-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.1)}
.related-card-placeholder{width:100%;height:100px;background:linear-gradient(135deg,#667eea,#f093fb);display:flex;align-items:center;justify-content:center;font-size:2rem}
.related-card-body{padding:.75rem}
.related-card-title{font-size:.85rem;font-weight:600;color:#111827;line-height:1.35}
.post-affiliate-note{font-size:.75rem;color:#9ca3af;background:#f9fafb;padding:.75rem 1rem;border-radius:8px;margin-top:2rem;line-height:1.5}
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

<main class="site-main">
<div class="post-wrap">
    <nav class="post-breadcrumb" aria-label="Breadcrumb">
        <a href="/">Home</a> › <a href="/blog/">Blog</a> › <?= h($post['title']) ?>
    </nav>

    <header class="post-header">
        <span class="post-cat-badge"><?= h(ucfirst($post['category'])) ?></span>
        <h1><?= h($post['title']) ?></h1>
        <div class="post-meta">
            <span>By <span class="post-meta-author"><?= h($post['author'] ?: '50OFF Team') ?></span></span>
            <span>📅 <?= date('F j, Y', strtotime($post['published_at'])) ?></span>
            <?php if ($post['view_count'] > 10): ?>
            <span>👁 <?= number_format($post['view_count']) ?> views</span>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($post['og_image']): ?>
    <img src="<?= h($post['og_image']) ?>" alt="<?= h($post['title']) ?>" class="post-hero-img">
    <?php endif; ?>

    <article class="post-body">
        <?= $renderedContent ?>
    </article>

    <!-- CTA box -->
    <div class="post-cta-box">
        <h3>🏷️ See All 50%+ Off Deals Right Now</h3>
        <p>We automatically update deals from Amazon &amp; Target every few hours.</p>
        <a href="/">Browse All Deals →</a>
    </div>

    <p class="post-affiliate-note">
        <strong>Affiliate Disclosure:</strong> 50OFF is a participant in the Amazon Services LLC Associates Program and other affiliate programs. When you click our links and make a purchase, we may earn a small commission at no extra cost to you. All deals are verified at time of posting — prices may change.
    </p>

    <?php if ($related): ?>
    <aside class="related-posts">
        <h2>More Deal Guides</h2>
        <div class="related-grid">
            <?php foreach ($related as $r): ?>
            <a href="/blog/<?= h($r['slug']) ?>" class="related-card">
                <?php if ($r['og_image']): ?>
                    <img src="<?= h($r['og_image']) ?>" alt="<?= h($r['title']) ?>" style="width:100%;height:100px;object-fit:cover" loading="lazy">
                <?php else: ?>
                    <div class="related-card-placeholder">📖</div>
                <?php endif; ?>
                <div class="related-card-body">
                    <span class="related-card-title"><?= h($r['title']) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </aside>
    <?php endif; ?>
</div>
</main>

<?php require_once ROOT . '/includes/footer.php'; ?>
</body>
</html>
