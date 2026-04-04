<?php
// includes/deal_card.php — Addicting, mobile-optimized deal card
// Expects: $deal (array), $lazy (bool)
$lazy  = $lazy ?? true;
$link  = '/deal.php?id=' . $deal['id'];
$pct   = (int)$deal['discount_pct'];
$saved = savings((float)$deal['original_price'], (float)$deal['sale_price']);

$imgSrc = '/assets/images/placeholder.svg';
if (!empty($deal['image_url'])) {
    // eBay and some CDNs don't block hotlinking — load directly for speed
    $imgHost = parse_url($deal['image_url'], PHP_URL_HOST) ?? '';
    $directHosts = ['i.ebayimg.com', 'thumbs.ebaystatic.com', 'm.media-amazon.com', 'i.target.com', 'target.scene7.com'];
    $isDirect = false;
    foreach ($directHosts as $dh) { if (str_contains($imgHost, $dh)) { $isDirect = true; break; } }
    $imgSrc = $isDirect ? $deal['image_url'] : '/api/img.php?url=' . urlencode($deal['image_url']);
}

// Badge logic: more exciting labels at higher discounts
$badgeLabel  = match(true) {
    $pct >= 80 => 'Extreme Deal',
    $pct >= 70 => 'Hot Deal',
    $pct >= 60 => 'On Sale',
    default    => 'Sale',
};
$badgeClass = match(true) {
    $pct >= 80 => 'badge-flash',
    $pct >= 70 => 'badge-trending',
    default    => 'badge-deal',
};

// Urgency timer: pseudo-random based on deal ID so it's consistent per deal
$hoursLeft = (($deal['id'] * 7 + 5) % 22) + 2; // 2–23h, deterministic per deal
$showTimer = $pct >= 60 || !empty($deal['expires_at']); // only hot deals get timers
?>
<article class="deal-card" data-store="<?= h($deal['store']) ?>" data-id="<?= (int)$deal['id'] ?>" data-cat="<?= h($deal['category'] ?? '') ?>">

    <!-- Image wrap with overlays -->
    <a href="<?= h($link) ?>" class="deal-card-img-wrap" style="display:block" aria-label="<?= h($deal['title']) ?>">
        <img
            src="<?= h($imgSrc) ?>"
            alt="<?= h($deal['title']) ?>"
            class="deal-card-img"
            <?= $lazy ? 'loading="lazy"' : '' ?>
            onerror="this.src='/assets/images/placeholder.svg'"
        >

        <!-- Discount type badge — top-right -->
        <span class="discount-badge <?= $badgeClass ?>" style="left:auto;right:10px"><?= $badgeLabel ?></span>

        <!-- Percent badge — top-left -->
        <span class="pct-badge" style="left:10px;right:auto">-<?= $pct ?>%</span>

        <!-- Save/wishlist heart button -->
        <button
            class="deal-save-btn"
            data-id="<?= (int)$deal['id'] ?>"
            data-title="<?= h(mb_substr($deal['title'],0,60)) ?>"
            data-price="<?= h(number_format((float)$deal['sale_price'],2)) ?>"
            data-pct="<?= $pct ?>"
            data-img="<?= h($imgSrc) ?>"
            data-link="<?= h($link) ?>"
            aria-label="Save deal"
            onclick="toggleSave(this, event)"
            style="bottom:auto;top:auto;bottom:8px;left:8px"
        >♡</button>

        <?php if($deal['is_featured']): ?>
        <span class="featured-tag">⭐ Featured</span>
        <?php endif; ?>

        <?php if($showTimer): ?>
        <span class="deal-timer">
            <svg width="10" height="10" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm.5 5v5.25l4.5 2.67-.75 1.23L11 13V7Z"/></svg>
            <?= $hoursLeft ?>h left
        </span>
        <?php endif; ?>
    </a>

    <!-- Body -->
    <div class="deal-card-body">

        <!-- Store + Category — both internal links -->
        <div class="deal-store-row">
            <a href="/?store=<?= h($deal['store']) ?>" class="deal-store-link" style="color:<?= storeColor($deal['store']) ?>">
                <?= storeLogo($deal['store']) ?> <?= ucfirst(h($deal['store'])) ?>
            </a>
            <?php if(!empty($deal['category'])): ?>
            <a href="/?category=<?= h($deal['category']) ?>" class="deal-cat-link">
                <?= h(ucfirst($deal['category'])) ?>
            </a>
            <?php endif; ?>
        </div>

        <!-- Title -->
        <h3 class="deal-title">
            <a href="<?= h($link) ?>"><?= h($deal['title']) ?></a>
        </h3>

        <!-- Rating -->
        <?php if(!empty($deal['rating']) && (float)$deal['rating'] >= 3.5): ?>
        <div class="deal-rating">
            <?php $r = round((float)$deal['rating']); ?>
            <?= str_repeat('★', $r) ?><?= str_repeat('☆', 5-$r) ?>
            <span>(<?= number_format((int)$deal['review_count']) ?>)</span>
        </div>
        <?php endif; ?>

        <!-- Price row -->
        <div class="deal-prices">
            <span class="sale-price">$<?= number_format((float)$deal['sale_price'], 2) ?></span>
            <span class="orig-price">$<?= number_format((float)$deal['original_price'], 2) ?></span>
            <span class="you-save">Save <?= $saved ?></span>
        </div>

        <!-- CTA -->
        <a
            href="/go.php?id=<?= $deal['id'] ?>"
            class="deal-btn"
            target="_blank"
            rel="noopener sponsored"
            onclick="trackDealClick(<?= (int)$deal['id'] ?>)"
        >
            Get This Deal
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        </a>
    </div>
</article>
