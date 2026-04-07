<?php
/**
 * Auth helper — token-based, NO PHP sessions
 *
 * Why no sessions: PHP sessions on Hostinger shared hosting behind a reverse
 * proxy are unreliable. The session cookie's 'secure' flag and cross-request
 * file locking cause intermittent session loss. Instead we store a 64-char
 * random token in the `subscribers.token` column and in a plain HttpOnly
 * cookie `50off_sess`. Every authenticated request is verified by DB lookup.
 */
require_once __DIR__ . '/db.php';

// ── Cookie helpers ────────────────────────────────────────────────────────────
function _detectHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
}

function _setAuthCookie(string $token): void {
    setcookie('50off_sess', $token, [
        'expires'  => time() + 86400 * 30,
        'path'     => '/',
        'secure'   => _detectHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    // Also make it available in this request
    $_COOKIE['50off_sess'] = $token;
}

function _clearAuthCookie(): void {
    setcookie('50off_sess', '', ['expires' => time() - 3600, 'path' => '/']);
    unset($_COOKIE['50off_sess']);
}

// No-op — kept for backward compat with any code that calls initSession()
function initSession(): void {}

// ── Ensure DB schema ─────────────────────────────────────────────────────────
function ensureAuthTables(): void {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS subscribers (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email         VARCHAR(255) NOT NULL,
        token         CHAR(64)     NOT NULL DEFAULT '',
        password_hash VARCHAR(255) DEFAULT NULL,
        name          VARCHAR(100) DEFAULT NULL,
        reset_token   CHAR(64)     DEFAULT NULL,
        verified      TINYINT(1)   NOT NULL DEFAULT 0,
        created_at    DATETIME     NOT NULL DEFAULT NOW(),
        UNIQUE KEY uq_email (email),
        KEY idx_token (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migrations for existing tables
    foreach ([
        "ALTER TABLE subscribers ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER token",
        "ALTER TABLE subscribers ADD COLUMN name VARCHAR(100) DEFAULT NULL AFTER password_hash",
        "ALTER TABLE subscribers ADD COLUMN reset_token CHAR(64) DEFAULT NULL AFTER name",
        "ALTER TABLE subscribers ADD KEY idx_token (token)",
    ] as $sql) {
        try { $db->exec($sql); } catch (\PDOException) {}
    }

    $db->exec("CREATE TABLE IF NOT EXISTS saved_deals (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        subscriber_id INT UNSIGNED NOT NULL,
        deal_id       INT UNSIGNED NOT NULL,
        created_at    DATETIME     NOT NULL DEFAULT NOW(),
        UNIQUE KEY uq_sub_deal (subscriber_id, deal_id),
        KEY idx_subscriber (subscriber_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ── currentUser — DB lookup by cookie token ──────────────────────────────────
function currentUser(): ?array {
    static $cache = [];
    $token = $_COOKIE['50off_sess'] ?? '';
    if (!$token || strlen($token) !== 64 || !ctype_xdigit($token)) return null;
    if (array_key_exists($token, $cache)) return $cache[$token];
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT id, email, name, token, verified, created_at
             FROM subscribers WHERE token = ? LIMIT 1"
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch() ?: null;
    } catch (\Throwable) {
        $user = null;
    }
    $cache[$token] = $user;
    return $user;
}

function isLoggedIn(): bool {
    return currentUser() !== null;
}

// ── Signup ────────────────────────────────────────────────────────────────────
function signup(string $email, string $password, string $name = ''): array {
    $email = strtolower(trim($email));
    $name  = trim($name);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        return ['ok' => false, 'error' => 'Invalid email address.'];
    if (strlen($password) < 6)
        return ['ok' => false, 'error' => 'Password must be at least 6 characters.'];

    $db = getDB();
    ensureAuthTables();

    $stmt = $db->prepare("SELECT id, password_hash, token FROM subscribers WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    $token = bin2hex(random_bytes(32)); // 64-char hex

    if ($existing) {
        if ($existing['password_hash'])
            return ['ok' => false, 'error' => 'An account with this email already exists. Try logging in.'];
        // Upgrade email-only subscriber to full account
        $db->prepare("UPDATE subscribers SET password_hash = ?, name = ?, token = ? WHERE id = ?")
           ->execute([password_hash($password, PASSWORD_DEFAULT), $name, $token, $existing['id']]);
        _setAuthCookie($token);
        return ['ok' => true, 'user_id' => (int)$existing['id'], 'upgraded' => true];
    }

    $db->prepare(
        "INSERT INTO subscribers (email, token, password_hash, name, verified) VALUES (?, ?, ?, ?, 1)"
    )->execute([$email, $token, password_hash($password, PASSWORD_DEFAULT), $name]);

    _setAuthCookie($token);
    return ['ok' => true, 'user_id' => (int)$db->lastInsertId()];
}

// ── Login ─────────────────────────────────────────────────────────────────────
function login(string $email, string $password): array {
    $email = strtolower(trim($email));
    if (!$email || !$password)
        return ['ok' => false, 'error' => 'Email and password are required.'];

    $db = getDB();
    ensureAuthTables();
    $stmt = $db->prepare("SELECT id, email, name, password_hash FROM subscribers WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user)
        return ['ok' => false, 'error' => 'No account found with this email.'];
    if (!$user['password_hash'])
        return ['ok' => false, 'error' => 'Please set a password first.', 'needs_password' => true];
    if (!password_verify($password, $user['password_hash']))
        return ['ok' => false, 'error' => 'Incorrect password.'];

    // Generate fresh session token on every login
    $token = bin2hex(random_bytes(32));
    $db->prepare("UPDATE subscribers SET token = ? WHERE id = ?")->execute([$token, $user['id']]);
    _setAuthCookie($token);
    return ['ok' => true, 'user_id' => (int)$user['id'], 'name' => $user['name']];
}

// ── Logout ────────────────────────────────────────────────────────────────────
function logout(): void {
    $user = currentUser();
    if ($user) {
        // Rotate token so old cookie is permanently invalidated
        $db = getDB();
        $db->prepare("UPDATE subscribers SET token = ? WHERE id = ?")
           ->execute([bin2hex(random_bytes(32)), $user['id']]);
    }
    _clearAuthCookie();
}

// ── Password reset ────────────────────────────────────────────────────────────
function requestPasswordReset(string $email): array {
    $email = strtolower(trim($email));
    $db    = getDB();
    ensureAuthTables();
    $stmt  = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
    $stmt->execute([$email]);
    $user  = $stmt->fetch();
    if (!$user) return ['ok' => true]; // Don't reveal if email exists

    $resetToken = bin2hex(random_bytes(32));
    $db->prepare("UPDATE subscribers SET reset_token = ? WHERE id = ?")->execute([$resetToken, $user['id']]);

    $link = 'https://50offsale.com/reset-password.php?token=' . urlencode($resetToken);
    $body = "Hi!\n\nReset your 50offsale.com password:\n{$link}\n\nIf you didn't request this, ignore this email.\n\n— 50offsale.com";
    @mail($email, 'Reset your 50offsale.com password', $body, implode("\r\n", [
        'From: 50offsale.com <noreply@50offsale.com>',
        'Content-Type: text/plain; charset=UTF-8',
    ]));
    return ['ok' => true];
}

function resetPassword(string $resetToken, string $newPassword): array {
    if (strlen($newPassword) < 6)
        return ['ok' => false, 'error' => 'Password must be at least 6 characters.'];
    if (!$resetToken || strlen($resetToken) !== 64)
        return ['ok' => false, 'error' => 'Invalid reset link.'];

    $db   = getDB();
    ensureAuthTables();
    $stmt = $db->prepare("SELECT id FROM subscribers WHERE reset_token = ?");
    $stmt->execute([$resetToken]);
    $user = $stmt->fetch();
    if (!$user) return ['ok' => false, 'error' => 'Invalid or expired reset link.'];

    $newToken = bin2hex(random_bytes(32));
    $db->prepare("UPDATE subscribers SET password_hash = ?, token = ?, reset_token = NULL WHERE id = ?")
       ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $newToken, $user['id']]);

    _setAuthCookie($newToken);
    return ['ok' => true];
}

// ── Saved deals ───────────────────────────────────────────────────────────────
function getUserSavedDeals(int $userId): array {
    $db = getDB();
    ensureAuthTables();
    $stmt = $db->prepare(
        "SELECT d.*, sd.created_at AS saved_at
         FROM deals d
         INNER JOIN saved_deals sd ON sd.deal_id = d.id
         WHERE sd.subscriber_id = ?
         ORDER BY sd.created_at DESC"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getSavedDealCount(int $userId): int {
    $db   = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM saved_deals WHERE subscriber_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}
