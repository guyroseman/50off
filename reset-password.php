<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
initSession();

$token = trim($_GET['token'] ?? '');
$error = '';
$done  = false;

if (!$token) {
    header('Location: /forgot-password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    if ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } else {
        $result = resetPassword($token, $pass);
        if ($result['ok']) {
            $done = true;
        } else {
            $error = $result['error'];
        }
    }
}

$pageTitle = 'Set New Password';
include 'includes/header.php';
?>

<div class="container">
<div class="auth-page">
    <div class="auth-card">
        <?php if ($done): ?>
        <div class="auth-header">
            <h1>Password updated!</h1>
            <p>You're now logged in. Your new password is active.</p>
        </div>
        <a href="/account.php" class="auth-btn">Go to My Account</a>
        <?php else: ?>
        <div class="auth-header">
            <h1>Set new password</h1>
            <p>Choose a new password for your account</p>
        </div>

        <?php if ($error): ?>
        <div class="auth-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="auth-field">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" required autocomplete="new-password" placeholder="At least 6 characters" minlength="6" autofocus>
            </div>
            <div class="auth-field">
                <label for="password2">Confirm Password</label>
                <input type="password" id="password2" name="password2" required autocomplete="new-password" placeholder="Repeat password" minlength="6">
            </div>
            <button type="submit" class="auth-btn">Update Password</button>
        </form>
        <?php endif; ?>
    </div>
</div>
</div>

<?php include 'includes/footer.php'; ?>
