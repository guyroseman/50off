<?php
require_once __DIR__ . '/includes/functions.php';

$id   = (int)getParam('id');
$deal = $id ? getDealById($id) : null;

if (!$deal) {
    http_response_code(404);
    $pageTitle = 'Deal Not Found';
    include 'includes/header.php';
    echo '<div class="container"><div class="empty-state"><h2>404 — Deal Not Found</h2><p>This deal may have expired.</p><a href="/" class="btn-primary">Browse All Deals</a></div></div>';
    include 'includes/footer.php';
    exit;
}

$pageTitle = $deal['title'];
$related   = getRelatedDeals($deal['id'], $deal['category'] ?? '', $deal['store'], 8);

// Build image src using same proxy logic as deal_card.php
$dealImgSrc = '/assets/images/placeholder.svg';
if (!empty($deal['image_url'])) {
    $imgHost = parse_url($deal['image_url'], PHP_URL_HOST) ?? '';
    $directHosts = ['i.ebayimg.com', 'thumbs.ebaystatic.com', 'm.media-amazon.com', 'i.target.com', 'target.scene7.com'];
    $isDirect = false;
    foreach ($directHosts as $dh) { if (str_contains($imgHost, $dh)) { $isDirect = true; break; } }
    $dealImgSrc = $isDirect ? $deal['image_url'] : '/api/img.php?url=' . urlencode($deal['image_url']);
}

include 'includes/header.php';
?>

<div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="/">Home</a> &rsaquo;
        <?php if($deal['category']): ?>
        <a href="/?category=<?= h($deal['category']) ?>"><?= h(ucfirst($deal['category'])) ?></a> &rsaquo;
        <?php endif; ?>
        <span><?= h(substr($deal['title'],0,50)) ?>…</span>
    </nav>

    <div class="deal-detail-grid">
        <!-- Image -->
        <div class="deal-detail-img-wrap">
            <span class="discount-badge badge-fire deal-detail-badge">-<?= $deal['discount_pct'] ?>%</span>
            <img
                src="<?= h($dealImgSrc) ?>"
                alt="<?= h($deal['title']) ?>"
                class="deal-detail-img"
                onerror="this.src='/assets/images/placeholder.svg'"
            >
        </div>

        <!-- Info -->
        <div class="deal-detail-info">
            <div class="deal-store-row">
                <span class="deal-store" style="color:<?= storeColor($deal['store']) ?>; font-size:1.1rem">
                    <?= storeLogo($deal['store']) ?> <?= ucfirst(h($deal['store'])) ?>
                </span>
                <?php if($deal['category']): ?>
                <a href="/?category=<?= h($deal['category']) ?>" class="deal-cat"><?= h($deal['category']) ?></a>
                <?php endif; ?>
            </div>

            <h1 class="deal-detail-title"><?= h($deal['title']) ?></h1>

            <?php if(!empty($deal['rating'])): ?>
            <div class="deal-rating deal-rating--lg">
                <?= str_repeat('★', (int)round($deal['rating'])) ?><?= str_repeat('☆', 5-(int)round($deal['rating'])) ?>
                <strong><?= $deal['rating'] ?></strong>
                <span>(<?= number_format($deal['review_count']) ?> reviews)</span>
            </div>
            <?php endif; ?>

            <?php if($deal['description']): ?>
            <p class="deal-detail-desc"><?= h($deal['description']) ?></p>
            <?php endif; ?>

            <div class="deal-detail-prices">
                <span class="sale-price sale-price--xl"><?= formatPrice((float)$deal['sale_price']) ?></span>
                <div class="price-meta">
                    <span class="orig-price orig-price--md">Was <?= formatPrice((float)$deal['original_price']) ?></span>
                    <span class="you-save you-save--md">You save <?= savings((float)$deal['original_price'], (float)$deal['sale_price']) ?> (<?= $deal['discount_pct'] ?>%)</span>
                </div>
            </div>

            <a
                href="/go.php?id=<?= $deal['id'] ?>"
                class="deal-btn deal-btn--xl"
                target="_blank"
                rel="noopener sponsored"
            >
                🛒 Get This Deal on <?= ucfirst(h($deal['store'])) ?> →
            </a>

            <p class="deal-disclaimer">
                Price may change. Last verified <?= date('M j, Y', strtotime($deal['scraped_at'])) ?>.
                As an affiliate we may earn a commission.
            </p>

            <div class="deal-actions">
                <button onclick="shareDeal()" class="btn-secondary">📤 Share</button>
                <a href="/?category=<?= h($deal['category']) ?>" class="btn-secondary">More <?= h(ucfirst($deal['category'])) ?> Deals</a>
            </div>
        </div>
    </div>

    <!-- Related deals -->
    <?php if($related): ?>
    <section class="related-section">
        <h2 class="section-title">You Might Also Like</h2>
        <div class="deals-grid">
            <?php foreach($related as $deal): $lazy = true; include 'includes/deal_card.php'; endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</div>

<script>
function shareDeal() {
    if(navigator.share) {
        navigator.share({ title: document.title, url: location.href });
    } else {
        navigator.clipboard.writeText(location.href).then(() => alert('Link copied!'));
    }
}
</script>

<?php include 'includes/footer.php'; ?>
