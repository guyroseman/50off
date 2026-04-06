<?php
/**
 * Auth helper — session-based user management for 50OFF
 *
 * Uses the existing `subscribers` table with added password_hash column.
 * Sessions are PHP native with secure cookie settings.
 */
require_once __DIR__ . '/db.php';

// ── Start session with secure settings ───────────────────────────────────────
function initSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    // Detect HTTPS — works behind reverse proxies (Hostinger shared hosting)
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
              || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
    session_set_cookie_params([
        'lifetime' => 86400 * 30, // 30 days
        'path'     => '/',
        'secure'   => $isSecure,
        'httponly'  => true,
        'samesite'  => 'Lax',
    ]);
    session_start();
}

// ── Ensure DB schema ─────────────────────────────────────────────────────────
function ensureAuthTables(): void {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS subscribers (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email      VARCHAR(255) NOT NULL,
        token      CHAR(64)     NOT NULL,
        password_hash VARCHAR(255) DEFAULT NULL,
        name       VARCHAR(100) DEFAULT NULL,
        verified   TINYINT(1)   NOT NULL DEFAULT 0,
        created_at DATETIME     NOT NULL DEFAULT NOW(),
        UNIQUE KEY uq_email (email),
        UNIQUE KEY uq_token (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Add password_hash column if missing (migration)
    try { $db->exec("ALTER TABLE subscribers ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER token"); } catch (\PDOException) {}
    try { $db->exec("ALTER TABLE subscribers ADD COLUMN name VARCHAR(100) DEFAULT NULL AFTER password_hash"); } catch (\PDOException) {}

    $db->exec("CREATE TABLE IF NOT EXISTS saved_deals (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        subscriber_id INT UNSIGNED NOT NULL,
        deal_id       INT UNSIGNED NOT NULL,
        created_at    DATETIME     NOT NULL DEFAULT NOW(),
        UNIQUE KEY uq_sub_deal (subscriber_id, deal_id),
        KEY idx_subscriber (subscriber_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ── Get current logged-in user (or null) ─────────────────────────────────────
function currentUser(): ?array {
    initSession();
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT id, email, name, token, verified, created_at FROM subscribers WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function isLoggedIn(): bool {
    initSession();
    return !empty($_SESSION['user_id']);
}

// ── Signup ────────────────────────────────────────────────────────────────────
function signup(string $email, string $password, string $name = ''): array {
    $email = strtolower(trim($email));
    $name  = trim($name);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Invalid email address.'];
    if (strlen($password) < 6) return ['ok' => false, 'error' => 'Password must be at least 6 characters.'];

    $db = getDB();
    ensureAuthTables();

    // Check if email exists
    $stmt = $db->prepare("SELECT id, password_hash FROM subscribers WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['password_hash']) {
            return ['ok' => false, 'error' => 'An account with this email already exists. Try logging in.'];
        }
        // Existing subscriber without password (from save-deal flow) — upgrade to full account
        $db->prepare("UPDATE subscribers SET password_hash = ?, name = ? WHERE id = ?")
           ->execute([password_hash($password, PASSWORD_DEFAULT), $name, $existing['id']]);
        initSession();
        $_SESSION['user_id'] = (int)$existing['id'];
        return ['ok' => true, 'user_id' => (int)$existing['id'], 'upgraded' => true];
    }

    $token = bin2hex(random_bytes(32));
    $db->prepare("INSERT INTO subscribers (email, token, password_hash, name, verified) VALUES (?, ?, ?, ?, 1)")
       ->execute([$email, $token, password_hash($password, PASSWORD_DEFAULT), $name]);
    $userId = (int)$db->lastInsertId();

    initSession();
    $_SESSION['user_id'] = $userId;
    return ['ok' => true, 'user_id' => $userId];
}

// ── Login ─────────────────────────────────────────────────────────────────────
function login(string $email, string $password): array {
    $email = strtolower(trim($email));
    if (!$email || !$password) return ['ok' => false, 'error' => 'Email and password are required.'];

    $db = getDB();
    ensureAuthTables();
    $stmt = $db->prepare("SELECT id, email, name, password_hash FROM subscribers WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) return ['ok' => false, 'error' => 'No account found with this email.'];
    if (!$user['password_hash']) return ['ok' => false, 'error' => 'This account was created via deal save. Please set a password first.', 'needs_password' => true];
    if (!password_verify($password, $user['password_hash'])) return ['ok' => false, 'error' => 'Incorrect password.'];

    initSession();
    $_SESSION['user_id'] = (int)$user['id'];
    return ['ok' => true, 'user_id' => (int)$user['id'], 'name' => $user['name']];
}

// ── Logout ────────────────────────────────────────────────────────────────────
function logout(): void {
    initSession();
    $_SESSION = [];
    session_destroy();
}

// ── Password reset request ────────────────────────────────────────────────────
function requestPasswordReset(string $email): array {
    $email = strtolower(trim($email));
    $db = getDB();
    $stmt = $db->prepare("SELECT id, token FROM subscribers WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) return ['ok' => true]; // Don't reveal if email exists

    // Regenerate token for reset
    $token = bin2hex(random_bytes(32));
    $db->prepare("UPDATE subscribers SET token = ? WHERE id = ?")->execute([$token, $user['id']]);

    $link = 'https://50offsale.com/reset-password.php?token=' . urlencode($token);
    $body = "Hi!\n\nYou requested a password reset for 50offsale.com.\n\nClick here to set a new password:\n{$link}\n\n"
          . "If you didn't request this, ignore this email.\n\n— 50offsale.com";
    @mail($email, 'Reset your 50offsale.com password', $body, implode("\r\n", [
        'From: 50offsale.com <noreply@50offsale.com>',
        'Content-Type: text/plain; charset=UTF-8',
    ]));
    return ['ok' => true];
}

// ── Reset password with token ─────────────────────────────────────────────────
function resetPassword(string $token, string $newPassword): array {
    if (strlen($newPassword) < 6) return ['ok' => false, 'error' => 'Password must be at least 6 characters.'];

    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM subscribers WHERE token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) return ['ok' => false, 'error' => 'Invalid or expired reset link.'];

    $newToken = bin2hex(random_bytes(32));
    $db->prepare("UPDATE subscribers SET password_hash = ?, token = ? WHERE id = ?")
       ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $newToken, $user['id']]);

    initSession();
    $_SESSION['user_id'] = (int)$user['id'];
    return ['ok' => true];
}

// ── Get saved deals for a user ────────────────────────────────────────────────
function getUserSavedDeals(int $userId): array {
    $db = getDB();
    ensureAuthTables();
    $stmt = $db->prepare("
        SELECT d.*, sd.created_at AS saved_at FROM deals d
        INNER JOIN saved_deals sd ON sd.deal_id = d.id
        WHERE sd.subscriber_id = ?
        ORDER BY sd.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getSavedDealCount(int $userId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM saved_deals WHERE subscriber_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}
