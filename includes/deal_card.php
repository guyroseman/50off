<?php
// includes/deal_card.php — matches Figma ProductCard component
// Expects: $deal (array), $lazy (bool)
$lazy  = $lazy ?? true;
$link  = '/deal.php?id=' . $deal['id'];
$pct   = (int)$deal['discount_pct'];
$saved = savings((float)$deal['original_price'], (float)$deal['sale_price']);

// Image: use proxy to bypass hotlink blocking from Amazon/Walmart/Target CDNs
$imgSrc = '/assets/images/placeholder.svg';
if (!empty($deal['image_url'])) {
    $imgSrc = '/api/img.php?url=' . urlencode($deal['image_url']);
}

// Badge label (like Figma discountTypeLabels)
$badgeLabel = match(true) {
    $pct >= 70 => 'Extreme Deal',
    $pct >= 60 => 'Hot Deal',
    default    => 'Sale',
};
?>
<article class="deal-card" data-store="<?= h($deal['store']) ?>">

    <!-- Image (served through /api/img.php proxy to fix hotlink blocking) -->
    <a href="<?= h($link) ?>" class="deal-card-img-wrap" style="display:block">
        <img
            src="<?= h($imgSrc) ?>"
            alt="<?= h($deal['title']) ?>"
            class="deal-card-img"
            <?= $lazy ? 'loading="lazy"' : '' ?>
            onerror="this.src='/assets/images/placeholder.svg'"
        >

        <!-- Discount type badge — top-left, Sale Red -->
        <span class="discount-badge badge-deal"><?= $badgeLabel ?></span>

        <!-- Percent — top-right, dark pill -->
        <span class="pct-badge">-<?= $pct ?>%</span>

        <?php if($deal['is_featured']): ?>
        <span class="featured-tag">⭐ Featured</span>
        <?php endif; ?>

        <?php if(!empty($deal['expires_at'])): ?>
        <span class="deal-timer">
            <svg width="10" height="10" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm.5 5v5.25l4.5 2.67-.75 1.23L11 13V7Z"/></svg>
            Expires soon
        </span>
        <?php endif; ?>
    </a>

    <!-- Body -->
    <div class="deal-card-body">

        <!-- Store -->
        <div class="deal-store-row">
            <span class="deal-store" style="color:<?= storeColor($deal['store']) ?>">
                <?= storeLogo($deal['store']) ?> <?= ucfirst(h($deal['store'])) ?>
            </span>
            <?php if(!empty($deal['category'])): ?>
            <span class="deal-cat"><?= h(ucfirst($deal['category'])) ?></span>
            <?php endif; ?>
        </div>

        <!-- Title -->
        <h3 class="deal-title">
            <a href="<?= h($link) ?>"><?= h($deal['title']) ?></a>
        </h3>

        <!-- Rating -->
        <?php if(!empty($deal['rating'])): ?>
        <div class="deal-rating">
            <?= str_repeat('★', (int)round($deal['rating'])) ?><?= str_repeat('☆', 5-(int)round($deal['rating'])) ?>
            <span>(<?= number_format($deal['review_count']) ?>)</span>
        </div>
        <?php endif; ?>

        <!-- Price history link (matches Figma "View Price History") -->
        <a href="<?= h($link) ?>" class="price-history-link">
            <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            View Price History
        </a>

        <!-- Price row (Green sale price like Figma) -->
        <div class="deal-prices">
            <span class="sale-price">$<?= number_format((float)$deal['sale_price'], 2) ?></span>
            <span class="orig-price">$<?= number_format((float)$deal['original_price'], 2) ?></span>
            <span class="you-save">Save <?= $saved ?></span>
        </div>

        <!-- Verified badge (Verified Green from palette) -->
        <div class="verified-badge">
            <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            Verified Genuine Discount
        </div>

        <!-- CTA — Conversion Orange -->
        <a
            href="/go.php?id=<?= $deal['id'] ?>"
            class="deal-btn"
            target="_blank"
            rel="noopener sponsored"
        >
            Buy Now at a Discount
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        </a>
    </div>
</article>
