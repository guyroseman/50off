<?php
define('BASE_PATH', __DIR__ . '/..');
require_once BASE_PATH . '/includes/functions.php';

$q       = trim(getParam('q'));
$sort    = getParam('sort', 'discount');
$page    = max(1, (int)getParam('page', 1));
$perPage = 24;
$offset  = ($page - 1) * $perPage;

$deals   = $q ? getDeals(['search' => $q, 'sort' => $sort, 'limit' => $perPage, 'offset' => $offset]) : [];
$total   = $q ? countDeals(['search' => $q]) : 0;
$pagData = paginate($total, $perPage, $page);

$pageTitle = $q ? 'Search: ' . $q : 'Search Deals';
include BASE_PATH . '/includes/header.php';
?>
<div class="container">
    <h1 class="page-title">
        <?php if($q): ?>
            🔍 Results for "<em><?= h($q) ?></em>" — <?= number_format($total) ?> deals
        <?php else: ?>
            Search 50%+ Off Deals
        <?php endif; ?>
    </h1>

    <?php if($deals): ?>
    <div class="deals-grid">
        <?php foreach($deals as $deal): $lazy=true; include BASE_PATH . '/includes/deal_card.php'; endforeach; ?>
    </div>
    <?php elseif($q): ?>
    <div class="empty-state">
        <p>😢 No deals found for "<?= h($q) ?>". Try a broader search.</p>
        <a href="/" class="btn-primary">Browse All Deals</a>
    </div>
    <?php else: ?>
    <div class="empty-state"><p>Enter a search term above to find deals.</p></div>
    <?php endif; ?>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
