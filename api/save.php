<?php
/**
 * /api/save.php — Save a deal for a subscriber (no password needed)
 *
 * POST  { email, deal_id }
 *   → Creates/finds subscriber by email
 *   → Saves the deal to their list
 *   → Returns { ok, token, saved_count, is_new }
 *   → Sends magic-link email on first signup (or resend if requested)
 *
 * GET   ?token=xxx&deal_id=NNN&action=remove
 *   → Removes a saved deal (from email link)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../includes/db.php';

// ── Ensure tables exist ───────────────────────────────────────────────────────
function ensureTables(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS subscribers (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email      VARCHAR(255) NOT NULL,
        token      CHAR(64)     NOT NULL,
        verified   TINYINT(1)   NOT NULL DEFAULT 0,
        created_at DATETIME     NOT NULL DEFAULT NOW(),
        UNIQUE KEY uq_email (email),
        UNIQUE KEY uq_token (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS saved_deals (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        subscriber_id INT UNSIGNED NOT NULL,
        deal_id       INT UNSIGNED NOT NULL,
        created_at    DATETIME     NOT NULL DEFAULT NOW(),
        UNIQUE KEY uq_sub_deal (subscriber_id, deal_id),
        KEY idx_subscriber (subscriber_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function json_out(array $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function sendMagicLink(string $email, string $token): void {
    $link    = 'https://50offsale.com/saved.php?token=' . urlencode($token);
    $subject = 'Your saved deals on 50offsale.com';
    $body    = "Hi!\n\nYou saved a deal on 50offsale.com. View all your saved deals here:\n\n{$link}\n\n"
             . "Bookmark that link — it's your personal deals list. No password needed!\n\n"
             . "— The 50offsale.com team\n\nTo stop receiving these emails, reply with 'unsubscribe'.";
    $headers = implode("\r\n", [
        'From: 50offsale.com <noreply@50offsale.com>',
        'Reply-To: noreply@50offsale.com',
        'X-Mailer: PHP/' . PHP_VERSION,
        'Content-Type: text/plain; charset=UTF-8',
    ]);
    @mail($email, $subject, $body, $headers);
}

try {
    $db = getDB();
    ensureTables($db);

    // ── GET: remove a saved deal via token link ───────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $token   = trim($_GET['token']   ?? '');
        $dealId  = (int)($_GET['deal_id'] ?? 0);
        $action  = trim($_GET['action']  ?? '');

        if (!$token) json_out(['error' => 'Missing token'], 400);

        $sub = $db->prepare("SELECT id FROM subscribers WHERE token = ? LIMIT 1");
        $sub->execute([$token]);
        $subscriber = $sub->fetch();
        if (!$subscriber) json_out(['error' => 'Invalid token'], 404);

        if ($action === 'remove' && $dealId > 0) {
            $db->prepare("DELETE FROM saved_deals WHERE subscriber_id = ? AND deal_id = ?")
               ->execute([$subscriber['id'], $dealId]);
            json_out(['ok' => true, 'removed' => $dealId]);
        }

        // Return saved deal IDs
        $ids = $db->prepare("SELECT deal_id FROM saved_deals WHERE subscriber_id = ? ORDER BY created_at DESC");
        $ids->execute([$subscriber['id']]);
        json_out(['ok' => true, 'saved' => $ids->fetchAll(PDO::FETCH_COLUMN)]);
    }

    // ── POST: save a deal ─────────────────────────────────────────────────────
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = strtolower(trim($body['email'] ?? $_POST['email'] ?? ''));
    $dealId = (int)($body['deal_id'] ?? $_POST['deal_id'] ?? 0);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['error' => 'Invalid email'], 400);
    if ($dealId <= 0) json_out(['error' => 'Invalid deal_id'], 400);

    // Verify deal exists
    $dealCheck = $db->prepare("SELECT id FROM deals WHERE id = ? AND is_active = 1 LIMIT 1");
    $dealCheck->execute([$dealId]);
    if (!$dealCheck->fetch()) json_out(['error' => 'Deal not found'], 404);

    // Find or create subscriber
    $stmt = $db->prepare("SELECT id, token, verified FROM subscribers WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $subscriber = $stmt->fetch();
    $isNew = false;

    if (!$subscriber) {
        $isNew = true;
        $token = bin2hex(random_bytes(32));
        $db->prepare("INSERT INTO subscribers (email, token, verified) VALUES (?, ?, 0)")
           ->execute([$email, $token]);
        $subscriber = ['id' => (int)$db->lastInsertId(), 'token' => $token, 'verified' => 0];
        sendMagicLink($email, $token);
    }

    // Save the deal (ignore duplicate)
    try {
        $db->prepare("INSERT IGNORE INTO saved_deals (subscriber_id, deal_id) VALUES (?, ?)")
           ->execute([$subscriber['id'], $dealId]);
    } catch (\PDOException) {}

    // Count saved deals
    $count = (int)$db->prepare("SELECT COUNT(*) FROM saved_deals WHERE subscriber_id = ?")
                     ->execute([$subscriber['id']]) ?: 0;
    $countStmt = $db->prepare("SELECT COUNT(*) FROM saved_deals WHERE subscriber_id = ?");
    $countStmt->execute([$subscriber['id']]);
    $count = (int)$countStmt->fetchColumn();

    json_out([
        'ok'          => true,
        'token'       => $subscriber['token'],
        'saved_count' => $count,
        'is_new'      => $isNew,
        'message'     => $isNew
            ? 'Deal saved! Check your email for your personal deals link.'
            : 'Deal saved to your list.',
    ]);

} catch (\Throwable $e) {
    json_out(['error' => 'Server error: ' . $e->getMessage()], 500);
}
