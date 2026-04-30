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

// ── SEO metadata per category/store ─────────────────────────────────────────
$_categoryMeta = [
    'electronics' => [
        'title' => 'Electronics Deals 50-90% Off — TVs, Laptops & Headphones',
        'desc'  => 'Verified electronics deals at 50% off or more. We scan Amazon, Best Buy & Target electronics clearance every 3 hours — TVs, laptops, headphones, cameras & phones.',
        'h1'    => 'Electronics Clearance — 50–90% Off',
        'intro' => 'Every electronics deal here is verified at 50% off or better, pulled from Amazon electronics sales, Best Buy clearance, and Target tech deals every 3 hours. Browse cheap laptops, discounted 4K TVs, budget headphones, cameras on sale, and smartphones at half price or more. Only real markdowns — no inflated "original" prices.',
    ],
    'clothing' => [
        'title' => 'Clothing Deals 50-90% Off — Designer Fashion Clearance',
        'desc'  => 'Shop clothing and apparel at 50% off or more from 6pm, Amazon & Target. End-of-season markdowns on women\'s, men\'s & kids\' fashion — verified every 3 hours.',
        'h1'    => 'Clothing & Fashion Deals — 50–90% Off',
        'intro' => 'All clothing deals here are at least 50% off the original retail price, sourced from 6pm, Amazon, and Target. Browse women\'s clothing on clearance, discounted men\'s fashion, and kids\' clothes at half price. Our scrapers update apparel deals every 3 hours, pulling end-of-season markdowns and flash sales before they sell out.',
    ],
    'home' => [
        'title' => 'Home Décor Deals 50-90% Off — Furniture, Bedding & Storage',
        'desc'  => 'Shop home and décor deals at 50% off or more. Furniture, bedding, décor & storage clearance from Amazon, Target & Walmart — updated every 3 hours.',
        'h1'    => 'Home & Décor Deals — 50–90% Off',
        'intro' => 'Find the best home deals at 50-90% off from Amazon, Target, and Walmart. From discounted furniture and cheap bedding sets to home décor on clearance and storage solutions at half price — every listing is a verified markdown. We pull fresh home clearance deals every 3 hours so the savings are always up to date.',
    ],
    'kitchen' => [
        'title' => 'Kitchen Deals 50-90% Off — Cookware, Appliances & Gadgets',
        'desc'  => 'Find kitchen and appliance deals at 50% off or more. Cookware sets, air fryers, small appliances & gadgets on clearance from Amazon, Target & Walmart.',
        'h1'    => 'Kitchen & Appliance Deals — 50–90% Off',
        'intro' => 'Shop kitchen deals at 50-90% off from Amazon, Target, and Walmart. We track cookware sets on clearance, discounted air fryers, cheap Instant Pots, small appliances on sale, and kitchen gadgets at half price — all verified at 50% off or more. Every deal is automatically pulled and updated every 3 hours.',
    ],
    'toys' => [
        'title' => 'Toy Deals 50-90% Off — LEGO, Board Games & Action Figures',
        'desc'  => 'Shop toy deals at 50% off or more. LEGO sets, board games, dolls & action figures on clearance from Amazon, Target & Walmart — updated every 3 hours.',
        'h1'    => 'Toy & Game Deals — 50–90% Off',
        'intro' => 'All toy deals here are 50% off or more, verified from Amazon, Target, and Walmart. Find discounted LEGO sets, board games on clearance, cheap action figures, and kids\' toys at half price. We scan toy clearance sections every 3 hours — perfect for spotting deals before birthdays, holidays, or just because.',
    ],
    'sports' => [
        'title' => 'Sports & Outdoors Deals 50-90% Off — Gear & Equipment Sale',
        'desc'  => 'Find sports and outdoor deals at 50% off or more. Exercise equipment, hiking gear, bikes & activewear on clearance — verified and updated every 3 hours.',
        'h1'    => 'Sports & Outdoors Deals — 50–90% Off',
        'intro' => 'Shop sports and outdoor deals at 50-90% off from Amazon, Target, and Walmart. From discounted exercise equipment and cheap camping gear to activewear on clearance and sporting goods at half price — all verified at 50% off or more. We track sports clearance every 3 hours, so whether you need a cheap yoga mat or discounted hiking boots, they\'re here.',
    ],
    'beauty' => [
        'title' => 'Beauty Deals 50-90% Off — Skincare, Makeup & Hair Tools Sale',
        'desc'  => 'Shop beauty deals at 50% off or more. Skincare, makeup, hair tools & fragrance on clearance from Amazon, Target & 6pm — verified and updated every 3 hours.',
        'h1'    => 'Beauty & Skincare Deals — 50–90% Off',
        'intro' => 'Find beauty deals at 50-90% off from Amazon, Target, and 6pm. We track skincare on clearance, cheap makeup palettes, discounted hair tools, and fragrance at half price — all verified at 50% off minimum. Whether you\'re looking for a cheap curling iron, discounted moisturizer, or perfume on sale, every deal here meets our strict threshold.',
    ],
    'health' => [
        'title' => 'Health & Wellness Deals 50-90% Off — Vitamins & Fitness Gear',
        'desc'  => 'Find health and wellness deals at 50% off or more. Vitamins, supplements, fitness equipment & wellness products on clearance — updated every 3 hours.',
        'h1'    => 'Health & Wellness Deals — 50–90% Off',
        'intro' => 'Shop health and wellness deals at 50-90% off from Amazon and Target. Browse vitamins and supplements on clearance, discounted fitness equipment, and wellness products at half price — all automatically verified at 50% off or better. We update health deals every 3 hours, from cheap protein powder to discounted blood pressure monitors.',
    ],
    'tools' => [
        'title' => 'Tools & Hardware Deals 50-90% Off — Power Tools Clearance',
        'desc'  => 'Find tools and hardware deals at 50% off or more. Power tools, hand tools & hardware on clearance from Amazon & Walmart — verified and updated every 3 hours.',
        'h1'    => 'Tools & Hardware Deals — 50–90% Off',
        'intro' => 'Find tools and hardware deals at 50-90% off from Amazon and Walmart. Browse discounted power tools, cheap hand tools, and hardware on clearance — all verified at minimum 50% off. Whether you need a cheap cordless drill, discounted circular saw, or hand tools at half price, we track tool clearance sales every 3 hours.',
    ],
    'pets' => [
        'title' => 'Pet Supply Deals 50-90% Off — Dog, Cat & Small Animal',
        'desc'  => 'Find pet supply deals at 50% off or more. Pet food, toys, beds & accessories for dogs, cats & more from Amazon & Target — verified and updated every 3 hours.',
        'h1'    => 'Pet Supply Deals — 50–90% Off',
        'intro' => 'Shop pet supply deals at 50-90% off from Amazon and Target. We track cheap dog food, cat supplies on clearance, discounted pet beds, and pet accessories at half price — all verified at 50% off or more. Whether you need a cheap dog crate, discounted cat toys, or pet food on sale, we update pet deals every 3 hours.',
    ],
];

$_storeMeta = [
    'amazon' => [
        'title' => 'Amazon Deals 50-90% Off Today | Lightning Deals & Clearance',
        'desc'  => 'Browse the best Amazon deals at 50% off or more. Lightning deals, warehouse clearance & Amazon sale items — verified and updated every 3 hours.',
        'h1'    => 'Amazon Deals — 50–90% Off Today',
        'intro' => 'We scan Amazon\'s deal pages, warehouse clearance, and lightning deals every 3 hours to surface only products marked 50% off or more. Every Amazon deal here is a verified markdown — no inflated "original prices." Browse cheap Amazon electronics, clothing on clearance, Amazon kitchen deals, toys on sale, and more across every category at genuine half-price or better.',
    ],
    'target' => [
        'title' => 'Target Deals 50-90% Off | Target Clearance & Circle Sales',
        'desc'  => 'Shop Target deals at 50% off or more. Target clearance, Circle deals & weekly sales — automatically verified and updated every 3 hours.',
        'h1'    => 'Target Deals — 50–90% Off',
        'intro' => 'All Target deals here are verified at 50% off or more, pulled from Target\'s clearance racks, Circle member deals, and weekly sales. We scan Target every 3 hours for the deepest markdowns — cheap Target clothing, discounted home goods, Target toy clearance, electronics on sale, and more. Skip the app and find the best Target deals all in one place.',
    ],
    'ebay' => [
        'title' => 'eBay Deals 50-90% Off | eBay Clearance & Daily Deals',
        'desc'  => 'Find eBay deals at 50% off or more. eBay daily deals, clearance listings & fixed-price markdowns with verified deep discounts — updated every 3 hours.',
        'h1'    => 'eBay Deals — 50–90% Off',
        'intro' => 'Browse eBay deals verified at 50% off or more — sourced from eBay\'s daily deals, clearance listings, and fixed-price markdowns. We track eBay every 3 hours to surface cheap electronics, discounted brand-name clothing, and refurbished goods at half price. Every deal is compared against the original retail price to ensure at least 50% off.',
    ],
    '6pm' => [
        'title' => '6pm Deals 50-90% Off | Designer Shoes & Clothing Clearance',
        'desc'  => 'Shop 6pm deals at 50% off or more. Designer shoes, boots, sneakers & clothing clearance from top brands — verified and updated every 3 hours.',
        'h1'    => '6pm Fashion Deals — 50–90% Off',
        'intro' => 'Find the best 6pm deals at 50-90% off — designer shoes on clearance, brand-name clothing at half price, cheap boots, and discounted sneakers from top fashion brands. 6pm is Zappos\'s discount outlet, and we track every markdown at 50% off or more, updated every 3 hours. Find cheap UGGs, discounted Nike, Adidas clearance, and more.',
    ],
    'bestbuy' => [
        'title' => 'Best Buy Deals 50-90% Off | Electronics & Appliance Clearance',
        'desc'  => 'Shop Best Buy deals at 50% off or more. Open-box electronics, clearance TVs, laptops & appliances — verified and updated every 3 hours.',
        'h1'    => 'Best Buy Deals — 50–90% Off',
        'intro' => 'Browse Best Buy deals verified at 50% off or more — open-box electronics, clearance TVs, discounted laptops, and appliance markdowns. We scan Best Buy\'s clearance section and deal pages every 3 hours, surfacing cheap TVs, discounted headphones, laptop clearance, gaming deals, and more at genuine half-price or better.',
    ],
];

// Set keyword-rich page title and meta description
$pageTitle = 'Top 50%+ Off Deals Today — Amazon, Target & eBay';
if ($store && isset($_storeMeta[$store])) {
    $pageTitle      = $_storeMeta[$store]['title'];
    $_pageMetaDesc  = $_storeMeta[$store]['desc'];
} elseif ($store) {
    $pageTitle = ucfirst($store) . ' Deals 50-90% Off | 50OFF';
} elseif ($category && isset($_categoryMeta[$category])) {
    $pageTitle      = $_categoryMeta[$category]['title'];
    $_pageMetaDesc  = $_categoryMeta[$category]['desc'];
} elseif ($category) {
    $pageTitle = ucfirst($category) . ' Deals 50-90% Off | 50OFF';
}

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

    <?php
    // Editorial intro for category/store pages — unique content for SEO
    if ($category && isset($_categoryMeta[$category]) && $page === 1):
        $cm = $_categoryMeta[$category];
    ?>
    <div class="editorial-intro">
        <h1 class="editorial-intro-h1"><?= h($cm['h1']) ?></h1>
        <p class="editorial-intro-body"><?= h($cm['intro']) ?></p>
        <div class="editorial-intro-links">
            Browse by store:
            <a href="/?category=<?= h($category) ?>&amp;store=amazon">Amazon</a>
            <a href="/?category=<?= h($category) ?>&amp;store=target">Target</a>
            <a href="/?category=<?= h($category) ?>&amp;store=ebay">eBay</a>
            <a href="/?category=<?= h($category) ?>&amp;store=6pm">6pm</a>
        </div>
    </div>
    <?php elseif ($store && isset($_storeMeta[$store]) && $page === 1):
        $sm = $_storeMeta[$store];
    ?>
    <div class="editorial-intro">
        <h1 class="editorial-intro-h1"><?= h($sm['h1']) ?></h1>
        <p class="editorial-intro-body"><?= h($sm['intro']) ?></p>
        <div class="editorial-intro-links">
            Browse by category:
            <a href="/?store=<?= h($store) ?>&amp;category=electronics">Electronics</a>
            <a href="/?store=<?= h($store) ?>&amp;category=clothing">Clothing</a>
            <a href="/?store=<?= h($store) ?>&amp;category=home">Home</a>
            <a href="/?store=<?= h($store) ?>&amp;category=kitchen">Kitchen</a>
            <a href="/?store=<?= h($store) ?>&amp;category=toys">Toys</a>
        </div>
    </div>
    <?php endif; ?>

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
