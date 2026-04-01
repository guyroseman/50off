<?php
/**
 * POST /api/ingest.php — Receive scraped deals via JSON and save to DB.
 *
 * Used by GitHub Actions to submit deals scraped from IPs that aren't blocked
 * (e.g. Amazon blocks Hostinger shared-hosting IPs).
 *
 * Expects:
 *   - Authorization: Bearer <INGEST_SECRET>
 *   - JSON body: array of deal objects
 *
 * Returns JSON: { "ok": true, "saved": N, "skipped": N, "errors": N }
 */

define('INGEST_SECRET', 'sk_50off_ingest_2024_x9k2m');

header('Content-Type: application/json');

// ── Auth ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$token = '';
if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
    $token = trim($m[1]);
}

$expectedToken = getenv('INGEST_TOKEN') ?: INGEST_SECRET;

if (!hash_equals($expectedToken, $token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Parse body ───────────────────────────────────────────────────────────────
$raw = file_get_contents('php://input');
$deals = json_decode($raw, true);

if (!is_array($deals) || empty($deals)) {
    http_response_code(400);
    echo json_encode(['error' => 'Expected non-empty JSON array of deals']);
    exit;
}

// Support both bare array and {"deals": [...]} wrapper
if (isset($deals['deals']) && is_array($deals['deals'])) {
    $deals = $deals['deals'];
}

// ── DB ───────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

$sql = "
    INSERT INTO deals
        (title, description, original_price, sale_price, discount_pct,
         image_url, product_url, affiliate_url, store, category,
         rating, review_count, is_featured, is_active, scraped_at)
    VALUES
        (:title, :desc, :orig, :sale, :pct, :img, :url, :aff, :store, :cat, :rating, :reviews, 0, 1, NOW())
    ON DUPLICATE KEY UPDATE
        title          = VALUES(title),
        sale_price     = VALUES(sale_price),
        original_price = VALUES(original_price),
        discount_pct   = VALUES(discount_pct),
        image_url      = COALESCE(VALUES(image_url), image_url),
        description    = COALESCE(VALUES(description), description),
        scraped_at     = NOW(),
        is_active      = 1
";
$stmt = $db->prepare($sql);

$saved = 0;
$skipped = 0;
$errors = 0;

foreach ($deals as $d) {
    // ── Validate required fields ─────────────────────────────────────────
    if (empty($d['title']) || empty($d['product_url'])) {
        $skipped++;
        continue;
    }

    $pct = (int)($d['discount_pct'] ?? 0);
    if ($pct < 50) {
        $skipped++;
        continue;
    }

    $orig = (float)($d['original_price'] ?? 0);
    $sale = (float)($d['sale_price'] ?? 0);
    if ($sale <= 0 || $orig <= $sale) {
        $skipped++;
        continue;
    }

    $params = [
        ':title'   => substr(trim(strip_tags($d['title'])), 0, 500),
        ':desc'    => isset($d['description']) ? substr(strip_tags($d['description']), 0, 1000) : null,
        ':orig'    => $orig,
        ':sale'    => $sale,
        ':pct'     => $pct,
        ':img'     => $d['image_url'] ?? null,
        ':url'     => substr($d['product_url'], 0, 1000),
        ':aff'     => substr($d['affiliate_url'] ?? $d['product_url'], 0, 1000),
        ':store'   => $d['store'] ?? 'other',
        ':cat'     => $d['category'] ?? null,
        ':rating'  => isset($d['rating']) ? round((float)$d['rating'], 1) : null,
        ':reviews' => (int)($d['review_count'] ?? 0),
    ];

    try {
        $stmt->execute($params);
        $saved++;
    } catch (\PDOException $e) {
        // Retry once on connection lost
        if (str_contains($e->getMessage(), 'gone away') || str_contains($e->getMessage(), '2006')) {
            $db = getDB(true);
            $stmt = $db->prepare($sql);
            try {
                $stmt->execute($params);
                $saved++;
            } catch (\PDOException) {
                $errors++;
            }
        } else {
            $errors++;
        }
    }
}

echo json_encode([
    'ok'      => true,
    'saved'   => $saved,
    'skipped' => $skipped,
    'errors'  => $errors,
]);
