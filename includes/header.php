<?php
$siteName    = '50OFF';
$currentStore= getParam('store');
$currentCat  = getParam('category');
$searchQ     = getParam('q');
$categories  = getCategories();
$stores      = getStores();

$discountTypes = [
    ''           => ['label' => 'All Deals',    'icon' => '✨'],
    'clearance'  => ['label' => 'Clearance',     'icon' => '🏷️'],
    'electronics'=> ['label' => 'Electronics',   'icon' => '📱'],
    'kitchen'    => ['label' => 'Kitchen',        'icon' => '🍳'],
    'clothing'   => ['label' => 'Clothing',       'icon' => '👗'],
    'home'       => ['label' => 'Home',           'icon' => '🏠'],
    'toys'       => ['label' => 'Toys',           'icon' => '🧸'],
    'sports'     => ['label' => 'Sports',         'icon' => '⚽'],
    'beauty'     => ['label' => 'Beauty',         'icon' => '💄'],
    'health'     => ['label' => 'Health',         'icon' => '💊'],
    'books'      => ['label' => 'Books',          'icon' => '📚'],
];

$isBlog = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/blog');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? h($pageTitle) . ' — ' : '' ?>50OFF — Don't search for products, search for discounts</title>
    <meta name="description" content="Only 50%+ off deals from Amazon, Target &amp; eBay. Verified discounts updated every 3 hours.">
    <meta property="og:title" content="50OFF — Only 50%+ Off Deals">
    <meta property="og:description" content="50% off or more from Amazon, Target &amp; eBay. Updated every 3 hours.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏷️</text></svg>">
</head>
<body>

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

    <!-- Saved deals -->
    <div class="drawer-section">
        <div class="drawer-section-title">Your Wishlist</div>
        <div id="drawer-saved-summary" style="font-size:.85rem;color:var(--slate);padding:.25rem .85rem">
            Loading saved deals…
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

            <!-- Saved deals button (desktop) -->
            <button class="header-saved-btn" id="header-saved-btn" onclick="openSavedPanel()" aria-label="Saved deals">
                ♡ Saved
                <span class="header-saved-count" id="header-saved-count"></span>
            </button>

            <!-- Mobile hamburger -->
            <button class="mobile-menu-btn" id="mobile-menu-btn" aria-label="Open menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- ══ CATEGORY / DISCOUNT TYPE BAR ═════════════════════════════════════════ -->
<div class="cat-bar">
    <div class="cat-bar-inner">
        <?php foreach($discountTypes as $slug => $info):
            $isActive = ($slug === '' && !$currentCat) || ($slug !== '' && $currentCat === $slug);
            $href = $slug === '' ? '/' : '/?category=' . urlencode($slug);
        ?>
        <a href="<?= $href ?>" class="cat-chip <?= $isActive ? 'active' : '' ?>">
            <?= $info['icon'] ?> <?= $info['label'] ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ══ SAVED DEALS SLIDE-PANEL ══════════════════════════════════════════════ -->
<div class="drawer-overlay" id="saved-overlay"></div>
<aside class="mobile-drawer" id="saved-panel" aria-label="Saved deals">
    <div class="drawer-header">
        <span style="font-weight:700;font-size:1rem">♡ Saved Deals <span id="saved-panel-count" style="color:var(--red)"></span></span>
        <button class="drawer-close" onclick="closeSavedPanel()">✕</button>
    </div>
    <div id="saved-panel-body" style="padding:1rem">
        <p class="saved-empty"><span class="big-icon">♡</span>No saved deals yet.<br>Tap the heart on any deal!</p>
    </div>
</aside>

<!-- ══ TOAST CONTAINER ══════════════════════════════════════════════════════ -->
<div id="toast-container" aria-live="polite"></div>

<main class="site-main">
