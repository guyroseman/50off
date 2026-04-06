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
$allPosts     = getBlogPosts(8, 0); // For internal links
$metaTitle    = $post['meta_title'] ?: ($post['title'] . ' — 50OFF Blog');
$metaDesc     = $post['meta_desc'] ?: $post['excerpt'];
$ogImage      = $post['og_image'] ?: 'https://50offsale.com/assets/img/og-blog.png';
// Make og:image absolute
if ($ogImage && strpos($ogImage, 'http') !== 0) {
    $ogImage = 'https://50offsale.com' . $ogImage;
}
$canonicalUrl = 'https://50offsale.com/blog/' . urlencode($post['slug']);
$pubDate      = date('c', strtotime($post['published_at']));
$modDate      = date('c', strtotime($post['updated_at'] ?? $post['published_at']));

// ── Content helpers ──────────────────────────────────────────────────────────

function renderDealPlaceholders(string $content): string {
    $content = preg_replace_callback('/<!--\s*DEALS:([a-z]+):(\d+)\s*-->/', function($m) {
        $deals = getDeals(['category' => $m[1], 'limit' => (int)$m[2], 'sort' => 'discount']);
        return renderInlineDeals($deals, $m[1]);
    }, $content);
    $content = preg_replace_callback('/<!--\s*STORE:([a-z]+):(\d+)\s*-->/', function($m) {
        $deals = getDeals(['store' => $m[1], 'limit' => (int)$m[2], 'sort' => 'discount']);
        return renderInlineDeals($deals, '', $m[1]);
    }, $content);
    return $content;
}

function renderInlineDeals(array $deals, string $category = '', string $store = ''): string {
    if (empty($deals)) return '<p class="no-deals-notice">No deals found right now — <a href="/">check all deals →</a></p>';
    $browseUrl  = $category ? '/?category=' . urlencode($category) : ($store ? '/?store=' . urlencode($store) : '/');
    $browseLabel = $category ? ucfirst($category) : ($store ? ucfirst($store) : 'all');
    $html = '<div class="post-deals-grid">';
    foreach ($deals as $d) {
        $img   = $d['image_url']
            ? '<img src="'.htmlspecialchars($d['image_url']).'" alt="'.htmlspecialchars($d['title']).'" loading="lazy">'
            : '<div class="post-deal-no-img">🏷️</div>';
        $title = mb_substr($d['title'], 0, 70) . (mb_strlen($d['title']) > 70 ? '…' : '');
        $html .= '<a href="/go.php?id='.intval($d['id']).'" class="post-deal-card" target="_blank" rel="nofollow sponsored">';
        $html .= '<div class="post-deal-img">'.$img.'</div>';
        $html .= '<div class="post-deal-info">';
        $html .= '<span class="post-deal-badge">'.intval($d['discount_pct']).'% OFF</span>';
        $html .= '<span class="post-deal-title">'.htmlspecialchars($title).'</span>';
        $html .= '<span class="post-deal-price">$'.number_format((float)$d['sale_price'],2).' <s style="color:#9ca3af;font-size:.8em">$'.number_format((float)$d['original_price'],2).'</s></span>';
        $html .= '<span class="post-deal-store">'.ucfirst(htmlspecialchars($d['store'])).'</span>';
        $html .= '</div></a>';
    }
    $html .= '</div>';
    $html .= '<p style="text-align:center;margin:.5rem 0 1.5rem"><a href="'.htmlspecialchars($browseUrl).'" class="browse-all-link">Browse all '.$browseLabel.' deals at 50%+ off →</a></p>';
    return $html;
}

// Generate TOC from H2 tags
function generateTOC(string $content): array {
    preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $content, $matches);
    $toc = [];
    foreach ($matches[1] as $h) {
        $text   = strip_tags($h);
        $anchor = 'h-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($text));
        $toc[]  = ['text' => $text, 'anchor' => $anchor];
    }
    return $toc;
}

// Add anchor IDs to H2 tags
function addAnchorIds(string $content): string {
    return preg_replace_callback('/<h2([^>]*)>(.*?)<\/h2>/is', function($m) {
        $text   = strip_tags($m[2]);
        $anchor = 'h-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($text));
        return '<h2 id="' . htmlspecialchars($anchor) . '"' . $m[1] . '>' . $m[2] . '</h2>';
    }, $content);
}

// Extract FAQ pairs from H3+P patterns
function extractFAQs(string $content): array {
    preg_match_all('/<h3[^>]*>(.*?)<\/h3>\s*<p>(.*?)<\/p>/is', $content, $m, PREG_SET_ORDER);
    $faqs = [];
    foreach ($m as $item) {
        $q = strip_tags($item[1]);
        $a = strip_tags($item[2]);
        if (strlen($q) < 120 && strlen($a) > 30 && strlen($a) < 600) {
            $faqs[] = ['q' => $q, 'a' => $a];
        }
    }
    return array_slice($faqs, 0, 8);
}

// Estimate reading time
function readingTime(string $content): int {
    return max(1, (int)round(str_word_count(strip_tags($content)) / 220));
}

$contentWithAnchors = addAnchorIds($post['content']);
$renderedContent    = renderDealPlaceholders($contentWithAnchors);
$toc                = generateTOC($post['content']);
$faqs               = extractFAQs($post['content']);
$readMins           = readingTime($post['content']);

// Build FAQ schema
$faqSchema = null;
if (!empty($faqs)) {
    $faqSchema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_map(fn($f) => [
            '@type'          => 'Question',
            'name'           => $f['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
        ], $faqs),
    ];
}

// Category → internal link map for mid-post CTAs
$catLinks = [
    'electronics' => ['📱 Electronics', '/?category=electronics'],
    'kitchen'     => ['🍳 Kitchen',      '/?category=kitchen'],
    'clothing'    => ['👗 Clothing',      '/?category=clothing'],
    'home'        => ['🏠 Home',          '/?category=home'],
    'toys'        => ['🧸 Toys',          '/?category=toys'],
    'sports'      => ['⚽ Sports',        '/?category=sports'],
    'beauty'      => ['💄 Beauty',        '/?category=beauty'],
    'health'      => ['💊 Health',        '/?category=health'],
];

// Other posts for internal cross-linking (exclude current)
$crossLinks = array_filter($allPosts, fn($p) => $p['slug'] !== $post['slug']);
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
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="news_keywords" content="<?= h($post['tags'] ?? '') ?>">

<!-- Geo / US targeting -->
<meta name="geo.region" content="US">
<meta name="geo.placename" content="United States">
<meta name="ICBM" content="39.5,-98.35">
<meta name="DC.coverage" content="USA">
<meta name="geo.country" content="US">

<!-- Open Graph / Article -->
<meta property="og:type" content="article">
<meta property="og:url" content="<?= h($canonicalUrl) ?>">
<meta property="og:title" content="<?= h($metaTitle) ?>">
<meta property="og:description" content="<?= h($metaDesc) ?>">
<meta property="og:image" content="<?= h($ogImage) ?>">
<meta property="og:image:width" content="1365">
<meta property="og:image:height" content="1024">
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
<meta name="twitter:site" content="@50offsale">
<meta name="twitter:title" content="<?= h($metaTitle) ?>">
<meta name="twitter:description" content="<?= h($metaDesc) ?>">
<meta name="twitter:image" content="<?= h($ogImage) ?>">

<!-- Schema: Article -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": <?= json_encode($post['title']) ?>,
  "description": <?= json_encode($metaDesc) ?>,
  "image": {"@type":"ImageObject","url":<?= json_encode($ogImage) ?>,"width":1365,"height":1024},
  "datePublished": <?= json_encode($pubDate) ?>,
  "dateModified": <?= json_encode($modDate) ?>,
  "author": {"@type":"Organization","name":"50OFF Team","url":"https://50offsale.com"},
  "publisher": {
    "@type": "Organization", "name": "50OFF", "url": "https://50offsale.com",
    "logo": {"@type":"ImageObject","url":"https://50offsale.com/assets/img/logo.png","width":192,"height":192}
  },
  "mainEntityOfPage": {"@type":"WebPage","@id":<?= json_encode($canonicalUrl) ?>},
  "keywords": <?= json_encode($post['tags'] ?? '') ?>,
  "articleSection": <?= json_encode(ucfirst($post['category'])) ?>,
  "wordCount": <?= str_word_count(strip_tags($post['content'])) ?>,
  "timeRequired": "PT<?= $readMins ?>M",
  "inLanguage": "en-US",
  "isAccessibleForFree": true,
  "audience": {"@type":"Audience","geographicArea":{"@type":"Country","name":"United States"}}
}
</script>

<!-- Schema: BreadcrumbList -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type":"ListItem","position":1,"name":"Home","item":"https://50offsale.com/"},
    {"@type":"ListItem","position":2,"name":"Blog","item":"https://50offsale.com/blog/"},
    {"@type":"ListItem","position":3,"name":"<?= h(ucfirst($post['category'])) ?>","item":"https://50offsale.com/blog/?cat=<?= urlencode($post['category']) ?>"},
    {"@type":"ListItem","position":4,"name":<?= json_encode($post['title']) ?>,"item":<?= json_encode($canonicalUrl) ?>}
  ]
}
</script>

<?php if ($faqSchema): ?>
<!-- Schema: FAQPage -->
<script type="application/ld+json"><?= json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏷️</text></svg>">
<style>
/* ── Reading progress bar ─────────────────────────────────────────── */
#read-progress{position:fixed;top:0;left:0;height:3px;background:linear-gradient(90deg,#ef4444,#f97316);z-index:9999;transition:width .1s linear;width:0}

/* ── Post layout ──────────────────────────────────────────────────── */
.post-layout{max-width:1100px;margin:0 auto;padding:2rem 1rem 4rem;display:grid;grid-template-columns:1fr 280px;gap:2.5rem;align-items:start}
@media(max-width:860px){.post-layout{grid-template-columns:1fr}}

.post-main{min-width:0}
.post-sidebar{position:sticky;top:80px}
@media(max-width:860px){.post-sidebar{display:none}}

.post-breadcrumb{font-size:.78rem;color:#9ca3af;margin-bottom:1.5rem;display:flex;gap:.3rem;flex-wrap:wrap;align-items:center}
.post-breadcrumb a{color:#6b7280;text-decoration:none}
.post-breadcrumb a:hover{color:#ef4444}

.post-header{margin-bottom:1.75rem}
.post-cat-badge{display:inline-block;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#ef4444;background:#fef2f2;padding:.22rem .6rem;border-radius:99px;margin-bottom:.6rem}
.post-header h1{font-size:clamp(1.4rem,3.5vw,2.1rem);font-weight:800;color:#111827;line-height:1.22;margin:0 0 1rem}
.post-meta{display:flex;align-items:center;gap:.75rem;font-size:.78rem;color:#9ca3af;flex-wrap:wrap}
.post-meta-divider{color:#d1d5db}
.post-meta-author{font-weight:600;color:#374151}
.post-read-time{background:#f3f4f6;padding:.2rem .55rem;border-radius:99px;font-size:.72rem;font-weight:600;color:#6b7280}

.post-hero-img{width:100%;border-radius:12px;margin-bottom:2rem;aspect-ratio:16/9;object-fit:cover}

/* ── TOC ──────────────────────────────────────────────────────────── */
.post-toc{background:#f9fafb;border:1.5px solid #f3f4f6;border-radius:10px;padding:1.1rem 1.25rem;margin-bottom:2rem}
.post-toc h4{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin:0 0 .6rem}
.post-toc ol{margin:0;padding-left:1.2rem}
.post-toc li{margin-bottom:.3rem}
.post-toc a{font-size:.82rem;color:#374151;text-decoration:none;line-height:1.4}
.post-toc a:hover{color:#ef4444}

/* ── Article body ─────────────────────────────────────────────────── */
.post-body{font-size:1rem;line-height:1.8;color:#374151}
.post-body h2{font-size:1.3rem;font-weight:800;color:#111827;margin:2.25rem 0 .75rem;padding-top:.5rem;border-top:2.5px solid #f3f4f6}
.post-body h3{font-size:1.05rem;font-weight:700;color:#111827;margin:1.5rem 0 .5rem}
.post-body p{margin:0 0 1.1rem}
.post-body ul,.post-body ol{margin:0 0 1.1rem;padding-left:1.5rem}
.post-body li{margin-bottom:.45rem}
.post-body a{color:#ef4444;text-decoration:underline;text-decoration-color:#fca5a5}
.post-body a:hover{text-decoration-color:#ef4444}
.post-body strong{color:#111827}
.post-body blockquote{border-left:4px solid #ef4444;padding:.75rem 1.25rem;margin:1.5rem 0;background:#fef2f2;border-radius:0 8px 8px 0;font-style:italic;color:#374151}
.post-body img{max-width:100%;border-radius:8px;margin:1rem 0}

/* ── Inline deal cards ────────────────────────────────────────────── */
.post-deals-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;margin:1rem 0 .5rem}
.post-deal-card{display:flex;flex-direction:column;border:1.5px solid #f3f4f6;border-radius:10px;overflow:hidden;background:#fff;text-decoration:none;color:inherit;transition:box-shadow .15s,transform .15s}
.post-deal-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.1);transform:translateY(-2px)}
.post-deal-img{width:100%;height:120px;overflow:hidden;background:#f9fafb;display:flex;align-items:center;justify-content:center}
.post-deal-img img{width:100%;height:100%;object-fit:contain;padding:.4rem}
.post-deal-no-img{font-size:2.5rem}
.post-deal-info{padding:.65rem;display:flex;flex-direction:column;gap:.25rem}
.post-deal-badge{font-size:.67rem;font-weight:800;background:#ef4444;color:#fff;padding:.18rem .45rem;border-radius:4px;align-self:flex-start}
.post-deal-title{font-size:.78rem;font-weight:600;color:#111827;line-height:1.35}
.post-deal-price{font-size:.88rem;font-weight:700;color:#ef4444}
.post-deal-store{font-size:.68rem;color:#9ca3af}
.no-deals-notice{color:#9ca3af;font-size:.9rem;padding:1rem;background:#f9fafb;border-radius:8px;text-align:center}
.browse-all-link{display:inline-flex;align-items:center;gap:.35rem;font-size:.82rem;font-weight:700;color:#ef4444;text-decoration:none;border:1.5px solid #fca5a5;padding:.35rem .85rem;border-radius:99px;transition:all .15s}
.browse-all-link:hover{background:#ef4444;color:#fff;border-color:#ef4444}

/* ── Mid-article CTA ─────────────────────────────────────────────── */
.mid-cta{background:linear-gradient(135deg,#1a1a2e,#0f3460);border-radius:12px;padding:1.5rem;text-align:center;color:#fff;margin:2rem 0}
.mid-cta h3{font-size:1.05rem;font-weight:700;margin:0 0 .4rem}
.mid-cta p{opacity:.75;font-size:.85rem;margin:0 0 .85rem}
.mid-cta a{display:inline-block;background:#ef4444;color:#fff;padding:.5rem 1.2rem;border-radius:8px;font-weight:700;font-size:.85rem;text-decoration:none}
.mid-cta a:hover{background:#dc2626}

/* ── Category quick links ────────────────────────────────────────── */
.cat-link-grid{display:flex;flex-wrap:wrap;gap:.5rem;margin:1rem 0 1.5rem}
.cat-link-pill{display:inline-flex;align-items:center;gap:.3rem;padding:.35rem .85rem;border-radius:99px;border:1.5px solid #e5e7eb;font-size:.8rem;font-weight:600;text-decoration:none;color:#374151;background:#fff;transition:all .15s}
.cat-link-pill:hover{background:#ef4444;border-color:#ef4444;color:#fff}

/* ── Sidebar ─────────────────────────────────────────────────────── */
.sidebar-box{background:#fff;border:1.5px solid #f3f4f6;border-radius:12px;padding:1.25rem;margin-bottom:1.25rem}
.sidebar-box h4{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin:0 0 .85rem}
.sidebar-post-link{display:flex;flex-direction:column;gap:.15rem;text-decoration:none;color:inherit;padding:.5rem 0;border-bottom:1px solid #f3f4f6}
.sidebar-post-link:last-child{border-bottom:none}
.sidebar-post-title{font-size:.82rem;font-weight:600;color:#111827;line-height:1.35}
.sidebar-post-title:hover{color:#ef4444}
.sidebar-post-date{font-size:.7rem;color:#9ca3af}
.sidebar-cat-link{display:flex;align-items:center;justify-content:space-between;padding:.4rem 0;text-decoration:none;font-size:.82rem;color:#374151;border-bottom:1px solid #f3f4f6}
.sidebar-cat-link:last-child{border-bottom:none}
.sidebar-cat-link:hover{color:#ef4444}
.sidebar-signup-box{background:linear-gradient(135deg,#ef4444,#f97316);border-radius:12px;padding:1.25rem;text-align:center;color:#fff}
.sidebar-signup-box h4{color:#fff;margin:0 0 .4rem;font-size:.9rem}
.sidebar-signup-box p{font-size:.78rem;opacity:.9;margin:0 0 .85rem}
.sidebar-signup-box a{display:inline-block;background:#fff;color:#ef4444;padding:.45rem 1.1rem;border-radius:8px;font-weight:700;font-size:.82rem;text-decoration:none}

/* ── Related posts ───────────────────────────────────────────────── */
.related-posts{margin-top:3rem;padding-top:2rem;border-top:2px solid #f3f4f6}
.related-posts h2{font-size:1rem;font-weight:700;margin-bottom:1.1rem;color:#111827}
.related-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem}
.related-card{border:1.5px solid #f3f4f6;border-radius:10px;overflow:hidden;text-decoration:none;color:inherit;background:#fff;transition:box-shadow .15s,transform .15s}
.related-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.1);transform:translateY(-2px)}
.related-card-img{width:100%;height:90px;object-fit:cover}
.related-card-placeholder{width:100%;height:90px;background:linear-gradient(135deg,#667eea,#f093fb);display:flex;align-items:center;justify-content:center;font-size:2rem}
.related-card-body{padding:.65rem}
.related-card-cat{font-size:.65rem;font-weight:700;text-transform:uppercase;color:#ef4444;margin-bottom:.25rem;display:block}
.related-card-title{font-size:.82rem;font-weight:600;color:#111827;line-height:1.35}

/* ── Post footer ─────────────────────────────────────────────────── */
.post-affiliate-note{font-size:.74rem;color:#9ca3af;background:#f9fafb;padding:.75rem 1rem;border-radius:8px;margin-top:2rem;line-height:1.55;border:1px solid #f3f4f6}
.post-share{display:flex;align-items:center;gap:.65rem;margin:1.5rem 0;flex-wrap:wrap}
.post-share span{font-size:.82rem;font-weight:600;color:#6b7280}
.share-btn{padding:.35rem .85rem;border-radius:99px;border:1.5px solid #e5e7eb;font-size:.78rem;font-weight:600;text-decoration:none;color:#374151;background:#fff;cursor:pointer;transition:all .15s}
.share-btn:hover{background:#111827;color:#fff;border-color:#111827}
</style>
</head>
<body>
<div id="read-progress"></div>

<?php require_once ROOT . '/includes/header.php'; ?>

<main class="site-main">
<div class="post-layout">

    <!-- ── Main content ────────────────────────────────────────────── -->
    <article class="post-main">

        <nav class="post-breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span>›</span>
            <a href="/blog/">Blog</a>
            <span>›</span>
            <a href="/blog/?cat=<?= urlencode($post['category']) ?>"><?= h(ucfirst($post['category'])) ?></a>
            <span>›</span>
            <span><?= h(mb_substr($post['title'], 0, 55)) ?>…</span>
        </nav>

        <header class="post-header">
            <span class="post-cat-badge"><?= h(ucfirst($post['category'])) ?></span>
            <h1><?= h($post['title']) ?></h1>
            <div class="post-meta">
                <span>By <span class="post-meta-author"><?= h($post['author'] ?: '50OFF Team') ?></span></span>
                <span class="post-meta-divider">·</span>
                <span>📅 <?= date('F j, Y', strtotime($post['published_at'])) ?></span>
                <span class="post-meta-divider">·</span>
                <span class="post-read-time">⏱ <?= $readMins ?> min read</span>
                <?php if ($post['view_count'] > 20): ?>
                <span class="post-meta-divider">·</span>
                <span>👁 <?= number_format($post['view_count']) ?> views</span>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($post['og_image']): ?>
        <img
            src="<?= h(strpos($post['og_image'], 'http') === 0 ? $post['og_image'] : $post['og_image']) ?>"
            alt="<?= h($post['title']) ?>"
            class="post-hero-img"
            loading="eager"
        >
        <?php endif; ?>

        <!-- Table of Contents -->
        <?php if (count($toc) >= 3): ?>
        <nav class="post-toc" aria-label="Table of contents">
            <h4>In This Guide</h4>
            <ol>
                <?php foreach ($toc as $item): ?>
                <li><a href="#<?= h($item['anchor']) ?>"><?= h($item['text']) ?></a></li>
                <?php endforeach; ?>
            </ol>
        </nav>
        <?php endif; ?>

        <div class="post-body">
            <?= $renderedContent ?>
        </div>

        <!-- Browse categories quick links -->
        <div style="margin:1.5rem 0">
            <p style="font-size:.85rem;font-weight:600;color:#6b7280;margin-bottom:.65rem">Browse all deals by category:</p>
            <div class="cat-link-grid">
                <?php foreach ($catLinks as $slug => [$label, $url]): ?>
                <a href="<?= h($url) ?>" class="cat-link-pill"><?= $label ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Final CTA -->
        <div class="mid-cta">
            <h3>🏷️ See Every Deal at 50% Off Right Now</h3>
            <p>Updated every 3 hours — from Amazon, Target, eBay, 6pm &amp; Best Buy.</p>
            <a href="/">Browse All Deals →</a>
        </div>

        <!-- Share row -->
        <div class="post-share">
            <span>Share:</span>
            <button class="share-btn" onclick="copyShareLink()">🔗 Copy Link</button>
            <a href="https://twitter.com/intent/tweet?text=<?= urlencode($post['title']) ?>&url=<?= urlencode($canonicalUrl) ?>" class="share-btn" target="_blank" rel="noopener">𝕏 Twitter</a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($canonicalUrl) ?>" class="share-btn" target="_blank" rel="noopener">Facebook</a>
            <a href="https://pinterest.com/pin/create/button/?url=<?= urlencode($canonicalUrl) ?>&description=<?= urlencode($post['title']) ?>&media=<?= urlencode($ogImage) ?>" class="share-btn" target="_blank" rel="noopener">Pinterest</a>
        </div>

        <p class="post-affiliate-note">
            <strong>Affiliate Disclosure:</strong> 50OFF participates in the Amazon Associates Program and other affiliate programs.
            When you click our links and make a purchase, we may earn a small commission at no extra cost to you.
            All deals are verified at time of posting — prices may change.
            Last updated: <?= date('F j, Y', strtotime($post['updated_at'] ?? $post['published_at'])) ?>.
        </p>

        <!-- Related posts -->
        <?php if ($related): ?>
        <aside class="related-posts">
            <h2>More Guides You'll Like</h2>
            <div class="related-grid">
                <?php foreach ($related as $r): ?>
                <a href="/blog/<?= h($r['slug']) ?>" class="related-card">
                    <?php if ($r['og_image']): ?>
                        <img src="<?= h($r['og_image']) ?>" alt="<?= h($r['title']) ?>" class="related-card-img" loading="lazy">
                    <?php else: ?>
                        <div class="related-card-placeholder">📖</div>
                    <?php endif; ?>
                    <div class="related-card-body">
                        <span class="related-card-cat"><?= h(ucfirst($r['category'])) ?></span>
                        <span class="related-card-title"><?= h($r['title']) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </aside>
        <?php endif; ?>

    </article>

    <!-- ── Sidebar ──────────────────────────────────────────────────── -->
    <aside class="post-sidebar" aria-label="Sidebar">

        <!-- Save deals CTA -->
        <div class="sidebar-signup-box" style="margin-bottom:1.25rem">
            <h4>♥ Save the Best Deals</h4>
            <p>Create a free account to save deals and browse your wishlist anytime.</p>
            <a href="/signup.php">Sign Up Free →</a>
        </div>

        <!-- More posts -->
        <?php if (!empty($crossLinks)): ?>
        <div class="sidebar-box">
            <h4>More Guides</h4>
            <?php foreach (array_slice($crossLinks, 0, 6) as $p): ?>
            <a href="/blog/<?= h($p['slug']) ?>" class="sidebar-post-link">
                <span class="sidebar-post-title"><?= h($p['title']) ?></span>
                <span class="sidebar-post-date"><?= date('M j', strtotime($p['published_at'])) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Browse by category -->
        <div class="sidebar-box">
            <h4>Browse by Category</h4>
            <?php foreach ($catLinks as $slug => [$label, $url]): ?>
            <a href="<?= h($url) ?>" class="sidebar-cat-link">
                <span><?= $label ?></span>
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Browse by store -->
        <div class="sidebar-box">
            <h4>Browse by Store</h4>
            <a href="/?store=amazon" class="sidebar-cat-link"><span>🛒 Amazon</span><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a>
            <a href="/?store=target" class="sidebar-cat-link"><span>🎯 Target</span><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a>
            <a href="/?store=ebay" class="sidebar-cat-link"><span>🔴 eBay</span><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a>
            <a href="/?store=6pm" class="sidebar-cat-link"><span>👠 6pm</span><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a>
            <a href="/?store=bestbuy" class="sidebar-cat-link"><span>🟡 Best Buy</span><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a>
        </div>

    </aside>

</div>
</main>

<script>
// Reading progress bar
(function() {
    var bar = document.getElementById('read-progress');
    if (!bar) return;
    window.addEventListener('scroll', function() {
        var doc = document.documentElement;
        var pct = doc.scrollTop / (doc.scrollHeight - doc.clientHeight) * 100;
        bar.style.width = Math.min(100, pct) + '%';
    }, { passive: true });
})();

function copyShareLink() {
    navigator.clipboard.writeText(location.href).then(function() {
        if (typeof showToast === 'function') showToast('Link copied!', 'success');
        else alert('Link copied!');
    });
}

function shareDeal() {
    if (navigator.share) {
        navigator.share({ title: document.title, url: location.href });
    } else {
        copyShareLink();
    }
}
</script>

<?php require_once ROOT . '/includes/footer.php'; ?>
</body>
</html>
