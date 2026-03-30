<?php
// go.php — affiliate redirect with click tracking
require_once __DIR__ . '/includes/functions.php';

$id   = (int)($_GET['id'] ?? 0);
$deal = $id ? getDealById($id) : null;

if (!$deal) {
    header('Location: /');
    exit;
}

// Track the click
trackClick($id);

// Redirect to affiliate URL (or product URL as fallback)
$url = !empty($deal['affiliate_url']) ? $deal['affiliate_url'] : $deal['product_url'];

header('Location: ' . $url, true, 302);
exit;
