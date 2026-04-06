<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
initSession();

if (!isLoggedIn()) { header('Location: /login.php'); exit; }

$user       = currentUser();
$savedDeals = getUserSavedDeals($user['id']);
$savedCount = count($savedDeals);

$pageTitle = 'My Account';
include 'includes/header.php';
?>

<div class="container">
<div class="account-page">

    <!-- Account header -->
    <div class="account-header">
        <div class="account-avatar"><?= strtoupper(mb_substr($user['name'] ?: $user['email'], 0, 1)) ?></div>
        <div>
            <h1><?= h($user['name'] ?: 'Your Account') ?></h1>
            <p class="account-email"><?= h($user['email']) ?></p>
            <p class="account-since">Member since <?= date('M Y', strtotime($user['created_at'])) ?></p>
        </div>
    </div>

    <!-- Stats strip -->
    <div class="account-stats">
        <div class="account-stat">
            <span class="account-stat-num"><?= $savedCount ?></span>
            <span class="account-stat-label">Saved Deals</span>
        </div>
        <div class="account-stat">
            <span class="account-stat-num"><?= $savedCount > 0 ? '$' . number_format(array_sum(array_column($savedDeals, 'sale_price')) - array_sum(array_column($savedDeals, 'sale_price')) + array_sum(array_map(fn($d) => (float)$d['original_price'] - (float)$d['sale_price'], $savedDeals)), 0) : '0' ?></span>
            <span class="account-stat-label">Total Savings</span>
        </div>
    </div>

    <!-- Saved deals section -->
    <div class="account-section">
        <div class="account-section-header">
            <h2>Saved Deals (<?= $savedCount ?>)</h2>
            <?php if ($savedCount > 0): ?>
            <a href="/" class="account-browse-link">Browse more →</a>
            <?php endif; ?>
        </div>

        <?php if ($savedCount === 0): ?>
        <div class="saved-empty">
            <span class="big-icon">♡</span>
            <p>No saved deals yet.</p>
            <a href="/" class="deal-btn" style="display:inline-flex;margin-top:.75rem;text-decoration:none">Browse Deals</a>
        </div>
        <?php else: ?>
        <div class="deals-grid">
            <?php foreach ($savedDeals as $deal): $lazy = true; include __DIR__ . '/includes/deal_card.php'; endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Account settings -->
    <div class="account-section">
        <h2>Account Settings</h2>
        <div class="account-settings">
            <a href="/forgot-password.php" class="btn-secondary">Change Password</a>
            <a href="/logout.php" class="btn-secondary btn-secondary--danger">Log Out</a>
        </div>
    </div>

</div>
</div>

<?php include 'includes/footer.php'; ?>
