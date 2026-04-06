<?php
require_once __DIR__ . '/auth.php';
initSession();
$_currentUser = currentUser();
$_isLoggedIn  = (bool)$_currentUser;

$siteName    = '50OFF';
$currentStore= getParam('store');
$currentCat  = getParam('category');
$searchQ     = getParam('q');
$categories  = getCategories();
$stores      = getStores();

$discountTypes = [
    ''           => ['label' => 'All Deals',    'icon' => '✨'],
    'electronics'=> ['label' => 'Electronics',   'icon' => '📱'],
    'clothing'   => ['label' => 'Clothing',       'icon' => '👗'],
    'home'       => ['label' => 'Home',           'icon' => '🏠'],
    'kitchen'    => ['label' => 'Kitchen',        'icon' => '🍳'],
    'toys'       => ['label' => 'Toys',           'icon' => '🧸'],
    'sports'     => ['label' => 'Sports',         'icon' => '⚽'],
    'beauty'     => ['label' => 'Beauty',         'icon' => '💄'],
    'health'     => ['label' => 'Health',         'icon' => '💊'],
    'tools'      => ['label' => 'Tools',          'icon' => '🔧'],
    'pets'       => ['label' => 'Pets',           'icon' => '🐾'],
];

$isBlog = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/blog');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
    $_canonicalUrl = 'https://50offsale.com' . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $_metaDesc = isset($pageTitle) && $pageTitle !== 'Top 50%+ Off Deals Today'
        ? h($pageTitle) . ' — verified 50%+ off deals updated every 3 hours.'
        : 'Only 50%+ off deals from Amazon, Target, eBay &amp; 6pm. Verified discounts updated every 3 hours.';
    ?>
    <title><?= isset($pageTitle) ? h($pageTitle) . ' — ' : '' ?>50OFF — Don't search for products, search for discounts</title>
    <meta name="description" content="<?= $_metaDesc ?>">
    <link rel="canonical" href="<?= $_canonicalUrl ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $_canonicalUrl ?>">
    <meta property="og:title" content="<?= isset($pageTitle) ? h($pageTitle) . ' — 50OFF' : '50OFF — Only 50%+ Off Deals' ?>">
    <meta property="og:description" content="<?= $_metaDesc ?>">
    <meta property="og:site_name" content="50OFF">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= isset($pageTitle) ? h($pageTitle) : '50OFF' ?>">
    <meta name="twitter:description" content="<?= $_metaDesc ?>">
    <meta name="geo.region" content="US">
    <meta name="geo.placename" content="United States">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏷️</text></svg>">
    <!-- JSON-LD: WebSite schema for sitelinks search -->
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => '50OFF',
        'url' => 'https://50offsale.com',
        'description' => 'Only 50%+ off deals from top US retailers',
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => ['@type' => 'EntryPoint', 'urlTemplate' => 'https://50offsale.com/search.php?q={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ],
    ], JSON_UNESCAPED_SLASHES) ?></script>
    <!-- Google Analytics (GA4) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-N17QKBTL2V"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-N17QKBTL2V');</script>
</head>
<body>

<!-- ══ URGENCY TICKER ════════════════════════════════════════════════════════ -->
<div class="ticker-bar" aria-hidden="true">
    <div class="ticker-track">
        <?php
        $tickerItems = [
            '🔥 New deals added every 3 hours',
            '✅ Every deal is 50%+ off — guaranteed',
            '🛒 Amazon • Target • eBay • 6pm • Best Buy',
            '⚡ Limited-time prices — act fast',
            '💰 Save hundreds on top brands',
            '🔥 New deals added every 3 hours',
            '✅ Every deal is 50%+ off — guaranteed',
            '🛒 Amazon • Target • eBay • 6pm • Best Buy',
            '⚡ Limited-time prices — act fast',
            '💰 Save hundreds on top brands',
        ];
        foreach ($tickerItems as $item): ?>
        <span class="ticker-item"><?= $item ?></span>
        <?php endforeach; ?>
    </div>
</div>

<!-- ══ MOBILE DRAWER OVERLAY ═════════════════════════════════════════════════ -->
<div class="drawer-overlay" id="drawer-overlay"></div>

<!-- ══ MOBILE DRAWER ═════════════════════════════════════════════════════════ -->
<aside class="mobile-drawer" id="mobile-drawer" aria-label="Navigation menu">
    <div class="drawer-header">
        <a href="/" class="logo">
            <div class="logo-icon">50</div>
            <div class="logo-text-wrap">
                <span class="logo-name">50<span class="logo-off">OFF</span></span>
                <span class="logo-tag">Find deals, not products</span>
            </div>
        </a>
        <button class="drawer-close" id="drawer-close" aria-label="Close menu">✕</button>
    </div>

    <!-- Search inside drawer -->
    <div class="drawer-section">
        <form action="/search.php" method="GET" role="search">
            <div class="drawer-search-wrap">
                <input type="search" name="q" class="drawer-search-input" placeholder="Search 50%+ deals…" value="<?= h($searchQ) ?>" autocomplete="off">
                <button type="submit" class="drawer-search-btn">Go</button>
            </div>
        </form>
    </div>

    <!-- Stores -->
    <div class="drawer-section">
        <div class="drawer-section-title">Stores</div>
        <div class="drawer-nav-links">
            <a href="/" class="drawer-nav-link <?= !$currentStore ? 'active' : '' ?>">
                ✨ All Deals
                <span class="drawer-pill-count">All</span>
            </a>
            <?php foreach($stores as $s): ?>
            <a href="/?store=<?= h($s['store']) ?>" class="drawer-nav-link <?= $currentStore === $s['store'] ? 'active' : '' ?>">
                <span><?= storeLogo($s['store']) ?> <?= ucfirst(h($s['store'])) ?></span>
                <span class="link-right">
                    <span class="drawer-pill-count"><?= $s['cnt'] ?></span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Categories -->
    <div class="drawer-section">
        <div class="drawer-section-title">Categories</div>
        <div class="drawer-cats">
            <?php foreach($discountTypes as $slug => $info):
                if ($slug === '') continue;
                $isActive = $currentCat === $slug;
            ?>
            <a href="/?category=<?= urlencode($slug) ?>" class="drawer-cat-chip <?= $isActive ? 'active' : '' ?>">
                <?= $info['icon'] ?> <?= $info['label'] ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Blog -->
    <div class="drawer-section">
        <div class="drawer-section-title">Blog & Resources</div>
        <div class="drawer-nav-links">
            <a href="/blog/" class="drawer-nav-link <?= $isBlog ? 'active' : '' ?>">📝 All Blog Posts</a>
            <a href="/blog/?cat=roundup" class="drawer-nav-link">🔥 Weekly Roundups</a>
            <a href="/blog/?cat=guide" class="drawer-nav-link">📖 Buying Guides</a>
            <a href="/blog/how-50off-works" class="drawer-nav-link">❓ How 50OFF Works</a>
        </div>
    </div>

    <!-- Account -->
    <div class="drawer-section">
        <div class="drawer-section-title">Account</div>
        <div class="drawer-nav-links">
            <?php if ($_isLoggedIn): ?>
            <a href="/account.php" class="drawer-nav-link">👤 My Account &amp; Saved Deals</a>
            <a href="/logout.php" class="drawer-nav-link">🚪 Log Out</a>
            <?php else: ?>
            <a href="/signup.php" class="drawer-nav-link" style="color:var(--orange);font-weight:600">✨ Sign Up Free</a>
            <a href="/login.php" class="drawer-nav-link">🔑 Log In</a>
            <?php endif; ?>
        </div>
    </div>
</aside>

<!-- ══ HEADER ═══════════════════════════════════════════════════════════════ -->
<header class="site-header">
    <div class="container">
        <div class="header-inner">

            <!-- Logo -->
            <a href="/" class="logo">
                <div class="logo-icon">50</div>
                <div class="logo-text-wrap">
                    <span class="logo-name">50<span class="logo-off">OFF</span></span>
                    <span class="logo-tag">Find deals, not products</span>
                </div>
            </a>

            <!-- Search -->
            <form class="search-form" action="/search.php" method="GET" role="search">
                <div class="search-wrap">
                    <svg class="search-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input
                        type="search"
                        name="q"
                        class="search-input"
                        placeholder="Search 50%+ deals…"
                        value="<?= h($searchQ) ?>"
                        autocomplete="off"
                    >
                    <button type="submit" class="search-btn">Search</button>
                </div>
            </form>

            <!-- Store nav (desktop only) -->
            <nav class="store-nav" aria-label="Stores">
                <a href="/" class="store-pill <?= !$currentStore ? 'active' : '' ?>">All</a>
                <?php foreach($stores as $s): ?>
                <a href="/?store=<?= h($s['store']) ?>" class="store-pill <?= $currentStore === $s['store'] ? 'active' : '' ?>">
                    <?= storeLogo($s['store']) ?> <?= ucfirst(h($s['store'])) ?>
                    <span class="pill-count"><?= $s['cnt'] ?></span>
                </a>
                <?php endforeach; ?>
                <span class="store-nav-divider" aria-hidden="true"></span>
                <a href="/blog/" class="store-pill store-pill--blog <?= $isBlog ? 'active' : '' ?>">✍️ Blog</a>
            </nav>

            <!-- Auth / account button (desktop) -->
            <?php if ($_isLoggedIn): ?>
            <a href="/account.php" class="header-saved-btn" aria-label="My account">
                👤 <?= h($_currentUser['name'] ?: 'Account') ?>
            </a>
            <?php else: ?>
            <a href="/login.php" class="header-saved-btn" aria-label="Log in">
                Log In
            </a>
            <?php endif; ?>

            <!-- Mobile hamburger -->
            <button class="mobile-menu-btn" id="mobile-menu-btn" aria-label="Open menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- ══ CATEGORY PILL STRIP (all pages) ═════════════════════════════════════ -->
<nav class="cat-pill-bar" aria-label="Browse by category">
    <div class="container">
        <div class="cat-pill-strip">
            <a href="/" class="cat-pill <?= (!$currentStore && !$currentCat) ? 'active' : '' ?>">All</a>
            <?php
            $catPills = [
                'electronics' => ['Electronics', '📱'],
                'clothing'    => ['Clothing',    '👗'],
                'home'        => ['Home',        '🏠'],
                'kitchen'     => ['Kitchen',     '🍳'],
                'toys'        => ['Toys',        '🧸'],
                'sports'      => ['Sports',      '⚽'],
                'beauty'      => ['Beauty',      '💄'],
                'health'      => ['Health',      '💊'],
                'tools'       => ['Tools',       '🔧'],
                'pets'        => ['Pets',        '🐾'],
            ];
            foreach ($catPills as $slug => [$label, $icon]):
            ?>
            <a href="/?category=<?= urlencode($slug) ?>" class="cat-pill <?= ($currentCat === $slug) ? 'active' : '' ?>">
                <?= $icon ?> <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>

<!-- ══ TOAST CONTAINER ══════════════════════════════════════════════════════ -->
<div id="toast-container" aria-live="polite"></div>

<!-- Auth state for JS -->
<script>
window.__isLoggedIn = <?= $_isLoggedIn ? 'true' : 'false' ?>;
window.__savedIds = <?php
    if ($_isLoggedIn) {
        $db = getDB();
        $stmt = $db->prepare("SELECT deal_id FROM saved_deals WHERE subscriber_id = ?");
        $stmt->execute([$_currentUser['id']]);
        echo json_encode(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    } else {
        echo '[]';
    }
?>;
</script>

<main class="site-main">
