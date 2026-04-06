<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
initSession();

// Already logged in? Go to account
if (isLoggedIn()) { header('Location: /account.php'); exit; }

$error = '';
$email = '';

// Handle form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email  = trim($_POST['email'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $result = login($email, $pass);
    if ($result['ok']) {
        $redirect = $_GET['redirect'] ?? '/account.php';
        header('Location: ' . $redirect);
        exit;
    }
    $error = $result['error'];
}

$pageTitle = 'Log In';
include 'includes/header.php';
?>

<div class="container">
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Welcome back</h1>
            <p>Log in to access your saved deals</p>
        </div>

        <?php if ($error): ?>
        <div class="auth-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="auth-form">
            <div class="auth-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= h($email) ?>" required autocomplete="email" autofocus placeholder="you@example.com">
            </div>
            <div class="auth-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Your password" minlength="6">
            </div>
            <button type="submit" class="auth-btn">Log In</button>
        </form>

        <div class="auth-links">
            <a href="/forgot-password.php">Forgot password?</a>
        </div>

        <div class="auth-divider"><span>New here?</span></div>

        <a href="/signup.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="auth-btn auth-btn--outline">Create Free Account</a>
    </div>
</div>
</div>

<?php include 'includes/footer.php'; ?>
