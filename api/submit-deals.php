<?php
/**
 * api/submit-deals.php — Secure endpoint for receiving deals from external scrapers
 *
 * Used by GitHub Actions to push Walmart/Target deals scraped from unblocked IPs.
 * Authenticated via a shared secret in the Authorization header.
 */
header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

// Auth check
$secret = 'sk_50off_ghactions_2026_xK9mQ';  // Change this to a strong secret
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($auth !== 'Bearer ' . $secret) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
$db = getDB();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['deals']) || !is_array($input['deals'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload. Expected: {"deals": [...]}']);
    exit;
}

$saved = 0;
$errors = 0;

foreach ($input['deals'] as $d) {
    $title = substr(trim(strip_tags($d['title'] ?? '')), 0, 500);
    $url   = substr($d['product_url'] ?? '', 0, 1000);
    if (!$title || !$url) { $errors++; continue; }

    $orig = (float)($d['original_price'] ?? 0);
    $sale = (float)($d['sale_price'] ?? 0);
    $pct  = (int)($d['discount_pct'] ?? 0);

    if ($pct < 50 || $sale <= 0 || $orig <= $sale) { $errors++; continue; }

    try {
        $stmt = $db->prepare("
            INSERT INTO deals
                (title, description, original_price, sale_price, discount_pct,
                 image_url, product_url, affiliate_url, store, category,
                 rating, review_count, is_featured, is_active, scraped_at)
            VALUES
                (:title,:desc,:orig,:sale,:pct,:img,:url,:aff,:store,:cat,:rating,:reviews,0,1,NOW())
            ON DUPLICATE KEY UPDATE
                title=VALUES(title), sale_price=VALUES(sale_price),
                original_price=VALUES(original_price), discount_pct=VALUES(discount_pct),
                image_url=COALESCE(VALUES(image_url),image_url),
                scraped_at=NOW(), is_active=1
        ");
        $stmt->execute([
            ':title'   => $title,
            ':desc'    => isset($d['description']) ? substr(strip_tags($d['description']), 0, 1000) : null,
            ':orig'    => $orig,
            ':sale'    => $sale,
            ':pct'     => $pct,
            ':img'     => $d['image_url'] ?? null,
            ':url'     => $url,
            ':aff'     => substr($d['affiliate_url'] ?? $url, 0, 1000),
            ':store'   => $d['store'] ?? 'other',
            ':cat'     => $d['category'] ?? null,
            ':rating'  => isset($d['rating']) ? round((float)$d['rating'], 1) : null,
            ':reviews' => (int)($d['review_count'] ?? 0),
        ]);
        $saved++;
    } catch (\PDOException $e) {
        $errors++;
    }
}

echo json_encode(['saved' => $saved, 'errors' => $errors, 'total' => count($input['deals'])]);
