<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
initSession();

if (isLoggedIn()) { header('Location: /account.php'); exit; }

$error = '';
$email = '';
$name  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email  = trim($_POST['email'] ?? '');
    $name   = trim($_POST['name'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $pass2  = $_POST['password2'] ?? '';

    if ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } else {
        $result = signup($email, $pass, $name);
        if ($result['ok']) {
            $redirect = $_GET['redirect'] ?? '/account.php';
            header('Location: ' . $redirect);
            exit;
        }
        $error = $result['error'];
    }
}

$pageTitle = 'Sign Up';
include 'includes/header.php';
?>

<div class="container">
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Create your free account</h1>
            <p>Save deals, get price drop alerts, and never miss a discount</p>
        </div>

        <?php if ($error): ?>
        <div class="auth-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/signup.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="auth-form">
            <div class="auth-field">
                <label for="name">Name <span class="auth-optional">(optional)</span></label>
                <input type="text" id="name" name="name" value="<?= h($name) ?>" autocomplete="name" placeholder="Your name">
            </div>
            <div class="auth-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= h($email) ?>" required autocomplete="email" placeholder="you@example.com">
            </div>
            <div class="auth-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="new-password" placeholder="At least 6 characters" minlength="6">
            </div>
            <div class="auth-field">
                <label for="password2">Confirm Password</label>
                <input type="password" id="password2" name="password2" required autocomplete="new-password" placeholder="Repeat password" minlength="6">
            </div>
            <button type="submit" class="auth-btn">Create Account</button>
        </form>

        <p class="auth-fine">By signing up you agree to our <a href="/terms.php">Terms</a> and <a href="/privacy.php">Privacy Policy</a>.</p>

        <div class="auth-divider"><span>Already have an account?</span></div>

        <a href="/login.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="auth-btn auth-btn--outline">Log In</a>
    </div>
</div>
</div>

<?php include 'includes/footer.php'; ?>
