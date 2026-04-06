<?php
/**
 * /api/auth.php — Authentication API endpoint
 *
 * POST actions: signup, login, logout, forgot, reset
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

function json_out(array $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $body['action'] ?? $_GET['action'] ?? '';

match($action) {
    'signup' => (function() use ($body) {
        $result = signup(
            $body['email'] ?? '',
            $body['password'] ?? '',
            $body['name'] ?? ''
        );
        json_out($result, $result['ok'] ? 200 : 400);
    })(),

    'login' => (function() use ($body) {
        $result = login($body['email'] ?? '', $body['password'] ?? '');
        json_out($result, $result['ok'] ? 200 : 401);
    })(),

    'logout' => (function() {
        logout();
        json_out(['ok' => true]);
    })(),

    'forgot' => (function() use ($body) {
        $result = requestPasswordReset($body['email'] ?? '');
        json_out($result);
    })(),

    'reset' => (function() use ($body) {
        $result = resetPassword($body['token'] ?? '', $body['password'] ?? '');
        json_out($result, $result['ok'] ? 200 : 400);
    })(),

    'me' => (function() {
        $user = currentUser();
        if (!$user) json_out(['ok' => false, 'error' => 'Not logged in'], 401);
        json_out(['ok' => true, 'user' => $user]);
    })(),

    'save' => (function() use ($body) {
        $user = currentUser();
        if (!$user) json_out(['ok' => false, 'error' => 'Login required'], 401);
        $dealId = (int)($body['deal_id'] ?? 0);
        if ($dealId <= 0) json_out(['ok' => false, 'error' => 'Invalid deal'], 400);
        $db = getDB();
        ensureAuthTables();
        try {
            $db->prepare("INSERT IGNORE INTO saved_deals (subscriber_id, deal_id) VALUES (?, ?)")
               ->execute([$user['id'], $dealId]);
        } catch (\PDOException $e) {
            json_out(['ok' => false, 'error' => 'Could not save: ' . $e->getMessage()], 500);
        }
        $stmt = $db->prepare("SELECT COUNT(*) FROM saved_deals WHERE subscriber_id = ?");
        $stmt->execute([$user['id']]);
        json_out(['ok' => true, 'saved_count' => (int)$stmt->fetchColumn()]);
    })(),

    'unsave' => (function() use ($body) {
        $user = currentUser();
        if (!$user) json_out(['ok' => false, 'error' => 'Login required'], 401);
        $dealId = (int)($body['deal_id'] ?? 0);
        $db = getDB();
        $db->prepare("DELETE FROM saved_deals WHERE subscriber_id = ? AND deal_id = ?")
           ->execute([$user['id'], $dealId]);
        json_out(['ok' => true]);
    })(),

    'saved_ids' => (function() {
        $user = currentUser();
        if (!$user) json_out(['ok' => true, 'ids' => []]);
        $db = getDB();
        $stmt = $db->prepare("SELECT deal_id FROM saved_deals WHERE subscriber_id = ?");
        $stmt->execute([$user['id']]);
        json_out(['ok' => true, 'ids' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
    })(),

    default => json_out(['error' => 'Unknown action'], 400),
};
