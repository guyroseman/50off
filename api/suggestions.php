<?php
// api/suggestions.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo '[]'; exit; }

$db   = getDB();
$stmt = $db->prepare("
    SELECT id, title, sale_price, discount_pct, image_url
    FROM deals
    WHERE is_active=1 AND discount_pct>=50 AND title LIKE ?
    ORDER BY discount_pct DESC
    LIMIT 6
");
$stmt->execute(['%' . $q . '%']);
$rows = $stmt->fetchAll();

$out = array_map(fn($r) => [
    'id'    => $r['id'],
    'title' => $r['title'],
    'price' => number_format((float)$r['sale_price'], 2),
    'pct'   => $r['discount_pct'],
    'image' => $r['image_url'] ?? '',
], $rows);

echo json_encode($out);
