<?php
require_once __DIR__ . '/includes/functions.php';

$store    = getParam('store');
$category = getParam('category');
$sort     = getParam('sort', 'discount');
$page     = max(1, (int)getParam('page', 1));
$perPage  = 24;
$offset   = ($page - 1) * $perPage;

$opts = ['store' => $store, 'category' => $category, 'sort' => $sort, 'limit' => $perPage, 'offset' => $offset];
$deals   = getDeals($opts);
$total   = countDeals($opts);
$pagData = paginate($total, $perPage, $page);

// ── Lightweight JSON mode for infinite scroll ────────────────────────────────
if (!empty($_GET['_json'])) {
    header('Content-Type: text/html; charset=utf-8');
    foreach ($deals as $deal) { $lazy = true; include __DIR__ . '/includes/deal_card.php'; }
    exit;
}

// Hot deals = featured or highest discount, expiring soon concept
$hotDeals = getDeals(['featured' => true, 'limit' => 8, 'sort' => 'discount']);
if (count($hotDeals) < 4) $hotDeals = getDeals(['sort' => 'discount', 'limit' => 8]);

$pageTitle = 'Top 50%+ Off Deals Today';
if ($store)    $pageTitle = ucfirst($store) . ' Deals 50%+ Off';
if ($category) $pageTitle = ucfirst($category) . ' Deals 50%+ Off';

include 'includes/header.php';
?>

<div class="container">

<?php if (!$store && !$category && $page === 1): ?>
<!-- ══ HERO — matches Figma SearchBar component ════════════════════════════ -->
<section class="hero-banner">
    <h1 class="hero-headline">Don't search for the product,</h1>
    <span class="hero-headline-accent">search for the discount.</span>
    <p class="hero-sub">What do you want to buy today?</p>

    <!-- Big search bar (hero version) -->
    <div class="hero-search">
        <form action="/search.php" method="GET" role="search">
            <div class="search-wrap">
                <svg class="search-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input
                    type="search"
                    name="q"
                    class="search-input"
                    placeholder='Try "running shoes on clearance" or "50% off laptops"'
                    autocomplete="off"
                >
                <button type="submit" class="search-btn">Search</button>
            </div>
        </form>
    </div>

    <!-- Popular tags (matches Figma quick suggestions) -->
    <div class="hero-tags">
        <span class="hero-tags-label">Popular:</span>
        <a href="/search.php?q=electronics" class="hero-tag">Electronics BOGO</a>
        <a href="/search.php?q=clearance" class="hero-tag">50% off clearance</a>
        <a href="/search.php?q=kitchen" class="hero-tag">Kitchen deals</a>
        <a href="/search.php?q=toys" class="hero-tag">Toy sales</a>
    </div>
</section>

<!-- Stats strip -->
<div class="hero-stats-strip">
    <div class="hero-stat">
        <span class="hero-stat-num"><?= number_format($total) ?>+</span>
        <span class="hero-stat-label">Active deals</span>
    </div>
    <div class="hero-stat">
        <span class="hero-stat-num">50%+</span>
        <span class="hero-stat-label">Minimum discount</span>
    </div>
    <div class="hero-stat">
        <span class="hero-stat-num">3</span>
        <span class="hero-stat-label">Major retailers</span>
    </div>
    <div class="hero-stat">
        <span class="hero-stat-num">2h</span>
        <span class="hero-stat-label">Update frequency</span>
    </div>
</div>

<!-- ══ HOT DEALS CAROUSEL — matches Figma HotDealsCarousel ════════════════ -->
<?php if ($hotDeals): ?>
<section class="hot-deals-section">
    <div class="section-header">
        <div>
            <h2 class="section-title">🔥 Hot Real-Time Discounts</h2>
            <p class="section-subtitle">Limited time offers — grab them before they expire!</p>
        </div>
        <div class="section-nav">
            <button class="nav-btn" id="hot-prev" aria-label="Previous">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
            </button>
            <button class="nav-btn" id="hot-next" aria-label="Next">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
            </button>
        </div>
    </div>
    <div class="hot-deals-scroll" id="hot-deals-scroll">
        <?php foreach($hotDeals as $deal): $lazy = false; include 'includes/deal_card.php'; endforeach; ?>
    </div>
    <p class="swipe-hint">← Swipe for more →</p>
</section>

<!-- ══ STORE QUICK-LINKS ════════════════════════════════════════════════════ -->
<div class="store-link-strip" aria-label="Browse by store">
    <?php foreach($stores as $s): ?>
    <a href="/?store=<?= h($s['store']) ?>" class="store-link-card">
        <?= storeLogo($s['store']) ?>
        <?= ucfirst(h($s['store'])) ?> Deals
        <span class="cnt">(<?= $s['cnt'] ?>)</span>
    </a>
    <?php endforeach; ?>
</div>

<?php endif; ?>
<?php endif; ?>

<!-- ══ ALL DEALS ════════════════════════════════════════════════════════════ -->
<section class="all-deals-section">
    <div class="deals-header">
        <div>
            <h2 class="section-title" style="font-size:1.35rem">
                <?php if($store): ?>
                    <?= storeLogo($store) ?> <?= ucfirst(h($store)) ?> Deals
                <?php elseif($category): ?>
                    <?= h(ucfirst($category)) ?> Deals
                <?php else: ?>
                    All Deals
                <?php endif; ?>
                <span class="deal-count-badge"><?= number_format($total) ?></span>
            </h2>
            <p class="deals-count"><?= number_format($total) ?> products found</p>
        </div>

        <div class="sort-bar">
            <label for="sort-select">Sort by:</label>
            <select id="sort-select" onchange="location.href=updateParam('sort',this.value)">
                <option value="discount" <?= $sort==='discount'?'selected':'' ?>>Highest Discount</option>
                <option value="newest"   <?= $sort==='newest'  ?'selected':'' ?>>Newest First</option>
                <option value="price"    <?= $sort==='price'   ?'selected':'' ?>>Price: Low to High</option>
            </select>
        </div>
    </div>

    <?php if ($deals): ?>
    <div class="deals-grid" id="deals-grid">
        <?php foreach($deals as $deal): $lazy = true; include 'includes/deal_card.php'; endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <p style="font-size:2rem;margin-bottom:1rem">🔍</p>
        <p style="font-weight:700;font-size:1.1rem;color:var(--ink)">No products found matching your criteria.</p>
        <p>Try adjusting your filters or search query.</p>
        <a href="/" class="btn-primary" style="margin-top:1.5rem;display:inline-flex">Browse All Deals</a>
    </div>
    <?php endif; ?>

    <!-- Scroll loader for infinite scroll (JS replaces pagination) -->
    <div class="scroll-loader" id="scroll-loader">
        <div class="scroll-spinner"></div> Loading more deals…
    </div>
    <div id="scroll-sentinel"></div>

    <!-- Pagination (hidden when infinite scroll JS loads) -->
    <?php if($pagData['pages'] > 1): ?>
    <nav class="pagination" aria-label="Pagination">
        <?php if($page > 1): ?>
        <a href="<?= h(updatePageParam($page-1)) ?>" class="page-btn">← Prev</a>
        <?php endif; ?>
        <?php for($i = max(1,$page-2); $i <= min($pagData['pages'],$page+2); $i++): ?>
        <a href="<?= h(updatePageParam($i)) ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if($page < $pagData['pages']): ?>
        <a href="<?= h(updatePageParam($page+1)) ?>" class="page-btn">Next →</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</section>

</div><!-- /.container -->

<?php
// Carousel scroll handlers live in main.js — do NOT redeclare hotScroll here
// (a const redeclaration would throw a SyntaxError that aborts main.js entirely)
function updatePageParam(int $p): string {
    $params = $_GET;
    $params['page'] = $p;
    return '/?' . http_build_query($params);
}
include 'includes/footer.php';
?>
