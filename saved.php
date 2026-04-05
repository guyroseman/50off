<?php
/**
 * saved.php — Personal saved deals page
 * Access via magic link: /saved.php?token=xxxx
 * No login or password required.
 */
require_once __DIR__ . '/includes/functions.php';

$token = trim($_GET['token'] ?? '');
$deals = [];
$email = '';
$error = '';

if ($token) {
    try {
        $db  = getDB();
        // Ensure tables exist (created by api/save.php on first use)
        $sub = $db->prepare("SELECT id, email FROM subscribers WHERE token = ? LIMIT 1");
        $sub->execute([$token]);
        $subscriber = $sub->fetch();

        if ($subscriber) {
            $email = $subscriber['email'];
            $stmt = $db->prepare("
                SELECT d.* FROM deals d
                INNER JOIN saved_deals sd ON sd.deal_id = d.id
                WHERE sd.subscriber_id = ? AND d.is_active = 1
                ORDER BY sd.created_at DESC
            ");
            $stmt->execute([$subscriber['id']]);
            $deals = $stmt->fetchAll();
        } else {
            $error = 'Invalid or expired link. Please save a deal again to get a new link.';
        }
    } catch (\Throwable $e) {
        $error = 'Could not load saved deals. Please try again.';
    }
} else {
    $error = 'No access token provided. Tap ♡ on any deal to save it and get your personal link.';
}

$pageTitle = $email ? "Saved Deals for " . htmlspecialchars(explode('@', $email)[0]) : 'Your Saved Deals';
include 'includes/header.php';
?>

<div class="container" style="padding-top:1.5rem;padding-bottom:3rem">

    <div style="max-width:600px;margin:0 auto 2rem">
        <h1 style="font-size:1.5rem;margin:0 0 .25rem">
            ♥ <?= $email ? 'Your Saved Deals' : 'Saved Deals' ?>
        </h1>
        <?php if ($email): ?>
        <p style="color:#6b7280;font-size:.9rem;margin:0">
            Saved for <strong><?= htmlspecialchars($email) ?></strong> ·
            <a href="/" style="color:var(--orange)">Browse more deals</a>
        </p>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
    <div class="saved-empty">
        <span class="big-icon">♡</span>
        <p><?= htmlspecialchars($error) ?></p>
        <a href="/" class="deal-btn" style="display:inline-flex;margin-top:1rem;text-decoration:none">Browse Deals</a>
    </div>

    <?php elseif (empty($deals)): ?>
    <div class="saved-empty">
        <span class="big-icon">♡</span>
        <p>No saved deals yet. <a href="/" style="color:var(--orange)">Browse deals</a> and tap ♡ to save them here.</p>
    </div>

    <?php else: ?>
    <p style="color:#6b7280;font-size:.85rem;margin:-1rem 0 1.25rem">
        <?= count($deals) ?> saved deal<?= count($deals) !== 1 ? 's' : '' ?>
        — <a href="/" style="color:var(--orange)">Find more</a>
    </p>

    <!-- Remove all expired notice -->
    <?php
    $expiredCount = 0;
    foreach ($deals as $d) { if (!(bool)$d['is_active']) $expiredCount++; }
    if ($expiredCount > 0):
    ?>
    <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:.75rem 1rem;margin-bottom:1.25rem;font-size:.88rem;color:#92400e">
        <?= $expiredCount ?> saved deal<?= $expiredCount !== 1 ? 's have' : ' has' ?> expired and may no longer be available.
    </div>
    <?php endif; ?>

    <div class="deals-grid">
        <?php foreach ($deals as $deal):
            $lazy = true;
            include __DIR__ . '/includes/deal_card.php';
        endforeach; ?>
    </div>

    <div style="text-align:center;margin-top:2.5rem">
        <p style="color:#6b7280;font-size:.85rem;margin-bottom:.75rem">
            Bookmark this page — it's your personal deals list.<br>
            New deals are scraped every 3 hours.
        </p>
        <a href="/" class="deal-btn" style="text-decoration:none;display:inline-flex">Browse All Deals →</a>
    </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
