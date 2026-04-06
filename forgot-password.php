<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
initSession();

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        requestPasswordReset($email);
        $sent = true;
    }
}

$pageTitle = 'Reset Password';
include 'includes/header.php';
?>

<div class="container">
<div class="auth-page">
    <div class="auth-card">
        <?php if ($sent): ?>
        <div class="auth-header">
            <h1>Check your email</h1>
            <p>If an account exists with that email, we've sent a password reset link. Check your inbox (and spam folder).</p>
        </div>
        <a href="/login.php" class="auth-btn">Back to Login</a>
        <?php else: ?>
        <div class="auth-header">
            <h1>Reset your password</h1>
            <p>Enter your email and we'll send you a reset link</p>
        </div>

        <?php if ($error): ?>
        <div class="auth-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="auth-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email" autofocus placeholder="you@example.com">
            </div>
            <button type="submit" class="auth-btn">Send Reset Link</button>
        </form>

        <div class="auth-links">
            <a href="/login.php">Back to login</a>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<?php include 'includes/footer.php'; ?>
