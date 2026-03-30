<?php
// admin/index.php — Simple admin dashboard
session_start();

// Basic password protection
$adminPass = 'admin123'; // CHANGE THIS before going live!

if ($_POST['password'] ?? '' === $adminPass) {
    $_SESSION['admin'] = true;
}
if (($_GET['logout'] ?? '') === '1') {
    session_destroy();
    header('Location: /admin/');
    exit;
}
if (!($_SESSION['admin'] ?? false)) {
    ?>
    <!DOCTYPE html>
    <html><head><title>Admin — 50OFF</title>
    <style>body{font-family:system-ui;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#0f0f0f;color:#fff;margin:0}
    .box{background:#1a1a1a;padding:2rem;border-radius:12px;width:300px}
    input,button{width:100%;padding:.75rem;margin:.5rem 0;border-radius:8px;border:1px solid #333;box-sizing:border-box}
    button{background:#ff4500;color:#fff;border:none;cursor:pointer;font-weight:700}</style>
    </head><body>
    <div class="box">
        <h2>🔐 Admin Login</h2>
        <form method="POST">
            <input type="password" name="password" placeholder="Password" autofocus>
            <button type="submit">Login</button>
        </form>
    </div>
    </body></html>
    <?php
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
$db = getDB();

// Handle actions
if ($_POST['action'] ?? '' === 'delete_deal') {
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare("UPDATE deals SET is_active=0 WHERE id=?")->execute([$id]);
    header('Location: /admin/');
    exit;
}
if ($_POST['action'] ?? '' === 'feature_deal') {
    $id  = (int)($_POST['id'] ?? 0);
    $val = (int)($_POST['val'] ?? 0);
    $db->prepare("UPDATE deals SET is_featured=? WHERE id=?")->execute([$val, $id]);
    header('Location: /admin/');
    exit;
}
if ($_POST['action'] ?? '' === 'run_scraper') {
    $scraperPath = __DIR__ . '/../scraper/run.php';
    if (function_exists('shell_exec')) {
        $output = shell_exec('php ' . escapeshellarg($scraperPath) . ' 2>&1');
    } elseif (function_exists('exec')) {
        exec('php ' . escapeshellarg($scraperPath) . ' 2>&1', $lines);
        $output = implode("\n", $lines);
    } else {
        // Fallback: run scraper inline
        ob_start();
        include $scraperPath;
        $output = ob_get_clean();
    }
}

// Stats
$totalDeals    = $db->query("SELECT COUNT(*) FROM deals WHERE is_active=1")->fetchColumn();
$totalClicks   = $db->query("SELECT COUNT(*) FROM clicks WHERE clicked_at > NOW() - INTERVAL 7 DAY")->fetchColumn();
$topDeals      = $db->query("SELECT d.*, COUNT(c.id) as clicks FROM deals d LEFT JOIN clicks c ON c.deal_id=d.id GROUP BY d.id ORDER BY clicks DESC LIMIT 10")->fetchAll();
$recentDeals   = $db->query("SELECT * FROM deals WHERE is_active=1 ORDER BY scraped_at DESC LIMIT 20")->fetchAll();
$scraperLog    = $db->query("SELECT * FROM scraper_log ORDER BY ran_at DESC LIMIT 10")->fetchAll();
$storeStats    = $db->query("SELECT store, COUNT(*) as cnt FROM deals WHERE is_active=1 GROUP BY store")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — 50OFF</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, sans-serif; background: #0f0f0f; color: #e0e0e0; }
.sidebar { width: 220px; background: #1a1a1a; min-height: 100vh; position: fixed; padding: 1.5rem 1rem; }
.main { margin-left: 220px; padding: 2rem; }
.logo { font-size: 1.5rem; font-weight: 900; color: #ff4500; margin-bottom: 2rem; }
nav a { display: block; padding: .6rem .75rem; border-radius: 8px; color: #aaa; text-decoration: none; margin-bottom: .25rem; }
nav a:hover, nav a.active { background: #ff4500; color: #fff; }
.stats-row { display: grid; grid-template-columns: repeat(auto-fit,minmax(150px,1fr)); gap: 1rem; margin-bottom: 2rem; }
.stat-card { background: #1a1a1a; border-radius: 10px; padding: 1.25rem; }
.stat-card .num { font-size: 2rem; font-weight: 900; color: #ff4500; }
.stat-card .label { font-size:.85rem; color:#777; }
table { width: 100%; border-collapse: collapse; background: #1a1a1a; border-radius: 10px; overflow: hidden; }
th { background:#222; padding:.75rem 1rem; text-align:left; font-size:.8rem; color:#777; text-transform:uppercase; }
td { padding:.6rem 1rem; border-top: 1px solid #222; vertical-align:middle; font-size:.9rem; }
td img { width:40px; height:40px; object-fit:cover; border-radius:6px; }
.badge { display:inline-block; padding:.25rem .6rem; border-radius:20px; font-size:.75rem; font-weight:700; }
.badge-fire { background:#ff4500; color:#fff; }
.badge-store { background:#333; color:#aaa; }
.btn-sm { padding:.3rem .7rem; border-radius:6px; border:none; cursor:pointer; font-size:.8rem; }
.btn-danger { background:#c0392b; color:#fff; }
.btn-star { background:#f39c12; color:#fff; }
.btn-run { background:#27ae60; color:#fff; padding:.75rem 1.5rem; border-radius:8px; border:none; cursor:pointer; font-weight:700; font-size:1rem; }
h2 { margin: 1.5rem 0 1rem; font-size:1.2rem; }
.scraper-output { background:#111; border:1px solid #333; padding:1rem; border-radius:8px; font-family:monospace; font-size:.8rem; white-space:pre-wrap; max-height:300px; overflow-y:auto; }
.log-ok { color:#27ae60; }
.log-err { color:#c0392b; }
</style>
</head>
<body>
<div class="sidebar">
    <div class="logo">🏷️ 50OFF</div>
    <nav>
        <a href="/admin/" class="active">📊 Dashboard</a>
        <a href="/?_admin=1" target="_blank">🌐 View Site</a>
        <a href="/admin/?logout=1">🚪 Logout</a>
    </nav>
</div>

<div class="main">
    <h1 style="font-size:1.8rem;margin-bottom:1.5rem">Dashboard</h1>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card"><div class="num"><?= number_format($totalDeals) ?></div><div class="label">Active Deals</div></div>
        <div class="stat-card"><div class="num"><?= number_format($totalClicks) ?></div><div class="label">Clicks (7d)</div></div>
        <?php foreach($storeStats as $s): ?>
        <div class="stat-card"><div class="num"><?= $s['cnt'] ?></div><div class="label"><?= ucfirst($s['store']) ?></div></div>
        <?php endforeach; ?>
    </div>

    <!-- Run Scraper -->
    <h2>⚙️ Scraper Control</h2>
    <form method="POST">
        <input type="hidden" name="action" value="run_scraper">
        <button type="submit" class="btn-run">▶️ Run Scraper Now</button>
    </form>
    <?php if(isset($output)): ?>
    <div class="scraper-output" style="margin-top:1rem"><?= h($output) ?></div>
    <?php endif; ?>

    <!-- Scraper Log -->
    <h2>📋 Scraper Log</h2>
    <table>
        <tr><th>Store</th><th>Status</th><th>Deals</th><th>Message</th><th>Time</th></tr>
        <?php foreach($scraperLog as $l): ?>
        <tr>
            <td><?= h($l['store']) ?></td>
            <td><span class="badge <?= $l['status']==='success'?'log-ok':'log-err' ?>"><?= $l['status'] ?></span></td>
            <td><?= $l['deals_found'] ?></td>
            <td><?= h(substr($l['message'],0,80)) ?></td>
            <td style="color:#666;font-size:.8rem"><?= $l['ran_at'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <!-- Top Deals by Clicks -->
    <h2>🔥 Top Deals by Clicks</h2>
    <table>
        <tr><th>Img</th><th>Title</th><th>Store</th><th>Discount</th><th>Clicks</th><th>Actions</th></tr>
        <?php foreach($topDeals as $d): ?>
        <tr>
            <td><img src="<?= h($d['image_url']??'') ?>" onerror="this.style.display='none'"></td>
            <td><a href="/deal.php?id=<?= $d['id'] ?>" style="color:#ff4500"><?= h(substr($d['title'],0,60)) ?>…</a></td>
            <td><span class="badge badge-store"><?= ucfirst(h($d['store'])) ?></span></td>
            <td><span class="badge badge-fire">-<?= $d['discount_pct'] ?>%</span></td>
            <td><?= $d['clicks'] ?></td>
            <td>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="feature_deal">
                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                    <input type="hidden" name="val" value="<?= $d['is_featured']?0:1 ?>">
                    <button class="btn-sm btn-star"><?= $d['is_featured']?'★ Unfeature':'☆ Feature' ?></button>
                </form>
                <form method="POST" style="display:inline" onsubmit="return confirm('Hide this deal?')">
                    <input type="hidden" name="action" value="delete_deal">
                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                    <button class="btn-sm btn-danger">✕ Hide</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
<?php function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } ?>
