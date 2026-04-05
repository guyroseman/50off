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

// Build image src — same CDN whitelist as deal_card.php
$dealImgSrc = '/assets/images/placeholder.svg';
if (!empty($deal['image_url'])) {
    $imgHost = parse_url($deal['image_url'], PHP_URL_HOST) ?? '';
    $directHosts = ['i.ebayimg.com', 'thumbs.ebaystatic.com', 'm.media-amazon.com', 'i.target.com', 'target.scene7.com', 'i5.walmartimages.com', 'pisces.bbystatic.com', 'm.media-6pm.com', 'scene7.com'];
    $isDirect = false;
    foreach ($directHosts as $dh) { if (str_contains($imgHost, $dh)) { $isDirect = true; break; } }
    $dealImgSrc = $isDirect ? $deal['image_url'] : '/api/img.php?url=' . urlencode($deal['image_url']);
}

$pct         = (int)$deal['discount_pct'];
$hasCat      = !empty($deal['category']) && $deal['category'] !== 'other';
$catLabel    = $hasCat ? ucfirst($deal['category']) : '';

include 'includes/header.php';
?>

<div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="/">Home</a> &rsaquo;
        <?php if($hasCat): ?>
        <a href="/?category=<?= h($deal['category']) ?>"><?= h($catLabel) ?></a> &rsaquo;
        <?php endif; ?>
        <span><?= h(mb_substr($deal['title'], 0, 50)) ?>…</span>
    </nav>

    <div class="deal-detail-grid">
        <!-- Image -->
        <div class="deal-detail-img-wrap">
            <span class="discount-badge badge-fire deal-detail-badge">-<?= $pct ?>%</span>

            <!-- Save heart button on image -->
            <button
                class="deal-save-btn deal-save-btn--detail"
                id="detail-save-btn"
                data-id="<?= (int)$deal['id'] ?>"
                data-title="<?= h(mb_substr($deal['title'], 0, 60)) ?>"
                data-price="<?= h(number_format((float)$deal['sale_price'], 2)) ?>"
                data-pct="<?= $pct ?>"
                data-img="<?= h($dealImgSrc) ?>"
                data-link="/deal.php?id=<?= $deal['id'] ?>"
                aria-label="Save deal"
                onclick="toggleSave(this, event)"
            >♡</button>

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
                <a href="/?store=<?= h($deal['store']) ?>" class="deal-store-link" style="color:<?= storeColor($deal['store']) ?>; font-size:1.1rem">
                    <?= storeLogo($deal['store']) ?> <?= ucfirst(h($deal['store'])) ?>
                </a>
                <?php if($hasCat): ?>
                <a href="/?category=<?= h($deal['category']) ?>" class="deal-cat-link"><?= h($catLabel) ?></a>
                <?php endif; ?>
            </div>

            <h1 class="deal-detail-title"><?= h($deal['title']) ?></h1>

            <?php if(!empty($deal['rating']) && (float)$deal['rating'] >= 3.5): ?>
            <div class="deal-rating deal-rating--lg">
                <?php $r = (int)round((float)$deal['rating']); ?>
                <?= str_repeat('★', $r) ?><?= str_repeat('☆', 5 - $r) ?>
                <strong><?= $deal['rating'] ?></strong>
                <span>(<?= number_format((int)$deal['review_count']) ?> reviews)</span>
            </div>
            <?php endif; ?>

            <?php if(!empty($deal['description'])): ?>
            <p class="deal-detail-desc"><?= h($deal['description']) ?></p>
            <?php endif; ?>

            <div class="deal-detail-prices">
                <span class="sale-price sale-price--xl"><?= formatPrice((float)$deal['sale_price']) ?></span>
                <div class="price-meta">
                    <span class="orig-price orig-price--md">Was <?= formatPrice((float)$deal['original_price']) ?></span>
                    <span class="you-save you-save--md">You save <?= savings((float)$deal['original_price'], (float)$deal['sale_price']) ?> (<?= $pct ?>%)</span>
                </div>
            </div>

            <!-- Primary CTA -->
            <a
                href="/go.php?id=<?= $deal['id'] ?>"
                class="deal-btn deal-btn--xl"
                target="_blank"
                rel="noopener sponsored"
                onclick="trackDealClick(<?= (int)$deal['id'] ?>)"
            >
                <?= storeLogo($deal['store']) ?> Get This Deal on <?= ucfirst(h($deal['store'])) ?> →
            </a>

            <!-- Save + Share row -->
            <div class="deal-actions">
                <button
                    class="btn-secondary deal-detail-save-text"
                    id="detail-save-text-btn"
                    onclick="toggleSave(document.getElementById('detail-save-btn'), event)"
                >
                    <span id="detail-save-label">♡ Save Deal</span>
                </button>
                <button onclick="shareDeal()" class="btn-secondary">📤 Share</button>
                <?php if($hasCat): ?>
                <a href="/?category=<?= h($deal['category']) ?>" class="btn-secondary">More <?= h($catLabel) ?> Deals</a>
                <?php endif; ?>
            </div>

            <p class="deal-disclaimer">
                Price may change. Last verified <?= date('M j, Y', strtotime($deal['scraped_at'])) ?>.
                As an affiliate we may earn a commission.
            </p>
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
    if (navigator.share) {
        navigator.share({ title: document.title, url: location.href });
    } else {
        navigator.clipboard.writeText(location.href).then(() => {
            if (typeof showToast === 'function') showToast('Link copied!', 'success');
            else alert('Link copied!');
        });
    }
}

// Sync detail page save button state with localStorage
document.addEventListener('DOMContentLoaded', function() {
    if (typeof getSaved !== 'function') return;
    var saved = getSaved();
    var btn   = document.getElementById('detail-save-btn');
    var label = document.getElementById('detail-save-label');
    if (!btn) return;
    var id = btn.dataset.id;
    if (saved[id]) {
        btn.textContent = '♥';
        btn.classList.add('saved');
        if (label) label.textContent = '♥ Saved';
    }

    // Watch for changes (heart toggled)
    var observer = new MutationObserver(function() {
        var isSaved = btn.classList.contains('saved');
        if (label) label.textContent = isSaved ? '♥ Saved' : '♡ Save Deal';
    });
    observer.observe(btn, { attributes: true, attributeFilter: ['class'] });
});
</script>

<?php include 'includes/footer.php'; ?>
