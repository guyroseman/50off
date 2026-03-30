<?php
$siteName    = '50OFF';
$currentStore= getParam('store');
$currentCat  = getParam('category');
$searchQ     = getParam('q');
$categories  = getCategories();
$stores      = getStores();

// Figma discount type filters (matching DiscountFilter component)
$discountTypes = [
    ''           => ['label' => 'All Deals',     'icon' => '✨'],
    'clearance'  => ['label' => 'Clearance',      'icon' => '🏷️'],
    'electronics'=> ['label' => 'Electronics',    'icon' => '📱'],
    'kitchen'    => ['label' => 'Kitchen',         'icon' => '🍳'],
    'clothing'   => ['label' => 'Clothing',        'icon' => '👗'],
    'home'       => ['label' => 'Home',            'icon' => '🏠'],
    'toys'       => ['label' => 'Toys',            'icon' => '🧸'],
    'sports'     => ['label' => 'Sports',          'icon' => '⚽'],
    'beauty'     => ['label' => 'Beauty',          'icon' => '💄'],
    'health'     => ['label' => 'Health',          'icon' => '💊'],
    'books'      => ['label' => 'Books',           'icon' => '📚'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? h($pageTitle) . ' — ' : '' ?>50OFF — Don't search for products, search for discounts</title>
    <meta name="description" content="Only 50%+ off deals from Amazon, Walmart & Target. Verified discounts updated every 2 hours.">
    <meta property="og:title" content="50OFF — Only 50%+ Off Deals">
    <meta property="og:description" content="Find only the best deals: 50% off or more from top US retailers.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏷️</text></svg>">
</head>
<body>

<!-- ══ HEADER ═══════════════════════════════════════════════════════════════ -->
<header class="site-header">
    <div class="container">
        <div class="header-inner">

            <!-- Logo (matches Figma: gradient square + brand name) -->
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

            <!-- Store nav -->
            <nav class="store-nav" aria-label="Stores">
                <a href="/" class="store-pill <?= !$currentStore ? 'active' : '' ?>">All</a>
                <?php foreach($stores as $s): ?>
                <a href="/?store=<?= h($s['store']) ?>" class="store-pill <?= $currentStore === $s['store'] ? 'active' : '' ?>">
                    <?= storeLogo($s['store']) ?> <?= ucfirst(h($s['store'])) ?>
                    <span class="pill-count"><?= $s['cnt'] ?></span>
                </a>
                <?php endforeach; ?>
            </nav>

            <button class="mobile-menu-btn" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- ══ CATEGORY / DISCOUNT TYPE BAR (matches Figma DiscountFilter) ══════════ -->
<div class="cat-bar">
    <div class="cat-bar-inner">
        <?php foreach($discountTypes as $slug => $info):
            $isActive = ($slug === '' && !$currentCat && !$currentStore) ||
                        ($slug !== '' && $currentCat === $slug);
            $href = $slug === '' ? '/' : '/?category=' . urlencode($slug);
        ?>
        <a href="<?= $href ?>" class="cat-chip <?= $isActive ? 'active' : '' ?>">
            <?= $info['icon'] ?> <?= $info['label'] ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<main class="site-main">
