<?php
// api/track.php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$id   = (int)($body['id'] ?? $_GET['id'] ?? 0);

if ($id) trackClick($id);

echo '{"ok":true}';
