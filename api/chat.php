<?php
/**
 * /api/chat.php — AI Deal Assistant powered by Claude
 *
 * POST { message: "I need running shoes under $50" }
 * Returns { reply: "...", deals: [...] }
 *
 * Flow:
 * 1. Search deals DB for keywords from user message
 * 2. Build context with matching deals
 * 3. Call Claude API with deals context + user message
 * 4. Return AI response with deal recommendations
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../includes/db.php';

// Claude API key — set this on Hostinger
define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: '');
define('CLAUDE_MODEL', 'claude-haiku-4-5-20251001'); // fast + cheap

function json_out(array $data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

// Rate limit: max 20 requests per IP per hour
function checkRateLimit(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $file = sys_get_temp_dir() . '/50off_chat_' . md5($ip);
    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : ['count' => 0, 'reset' => time() + 3600];
    if (time() > ($data['reset'] ?? 0)) $data = ['count' => 0, 'reset' => time() + 3600];
    if (($data['count'] ?? 0) >= 20) json_out(['error' => 'Rate limit reached. Try again later.'], 429);
    $data['count']++;
    file_put_contents($file, json_encode($data));
}

try {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $message = trim($body['message'] ?? '');
    if (!$message || strlen($message) > 500) json_out(['error' => 'Message required (max 500 chars)'], 400);

    checkRateLimit();

    // ── Search deals DB for context ───────────────────────────────────────────
    $db = getDB();
    $keywords = preg_split('/\s+/', strtolower($message));
    $keywords = array_filter($keywords, function($w) { return strlen($w) >= 3; });

    // Build search query
    $where = ['is_active = 1', 'discount_pct >= 50'];
    $params = [];
    if (!empty($keywords)) {
        $kw_clauses = [];
        foreach (array_values(array_slice($keywords, 0, 5)) as $i => $kw) {
            $kw_clauses[] = "(LOWER(title) LIKE :kw{$i} OR LOWER(category) LIKE :kwc{$i})";
            $params[":kw{$i}"]  = "%{$kw}%";
            $params[":kwc{$i}"] = "%{$kw}%";
        }
        $where[] = '(' . implode(' OR ', $kw_clauses) . ')';
    }

    $sql = "SELECT id, title, sale_price, original_price, discount_pct, store, category
            FROM deals WHERE " . implode(' AND ', $where) . "
            ORDER BY discount_pct DESC LIMIT 10";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $matchingDeals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no keyword matches, get top deals as fallback context
    if (empty($matchingDeals)) {
        $stmt = $db->query("SELECT id, title, sale_price, original_price, discount_pct, store, category
                           FROM deals WHERE is_active = 1 AND discount_pct >= 50
                           ORDER BY discount_pct DESC LIMIT 10");
        $matchingDeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get site stats for context
    $stats = $db->query("SELECT COUNT(*) as total, ROUND(AVG(discount_pct)) as avg_pct,
                        GROUP_CONCAT(DISTINCT store) as stores
                        FROM deals WHERE is_active = 1 AND discount_pct >= 50")->fetch();

    // ── Build deals context string ────────────────────────────────────────────
    $dealsContext = "Current deals on 50offsale.com ({$stats['total']} active deals, avg {$stats['avg_pct']}% off):\n";
    $dealsContext .= "Stores: {$stats['stores']}\n\n";
    foreach ($matchingDeals as $d) {
        $dealsContext .= "- [{$d['id']}] {$d['title']} — \${$d['sale_price']} (was \${$d['original_price']}, {$d['discount_pct']}% off) [{$d['store']}, {$d['category']}]\n";
    }

    // ── If no API key, use simple keyword-based response ──────────────────────
    if (!CLAUDE_API_KEY) {
        $reply = buildSimpleResponse($message, $matchingDeals, $stats);
        $dealCards = [];
        foreach (array_slice($matchingDeals, 0, 5) as $d) {
            $dealCards[] = ['id' => $d['id'], 'title' => $d['title'],
                'price' => '$' . number_format((float)$d['sale_price'], 2),
                'pct' => $d['discount_pct'] . '%', 'store' => $d['store']];
        }
        json_out(['reply' => $reply, 'deals' => $dealCards]);
    }

    // ── Call Claude API ───────────────────────────────────────────────────────
    $apiBody = json_encode([
        'model' => CLAUDE_MODEL,
        'max_tokens' => 300,
        'system' => "You are the 50OFF Deal Assistant — a friendly, concise shopping helper on 50offsale.com. "
                   . "You help users find deals from our database. All deals are 50%+ off from major US retailers. "
                   . "Keep responses short (2-3 sentences max). Be enthusiastic but not pushy. "
                   . "When recommending deals, mention the deal ID in brackets like [123] so the frontend can link them. "
                   . "If no matching deals exist, suggest browsing categories or checking back later.\n\n"
                   . "AVAILABLE DEALS:\n{$dealsContext}",
        'messages' => [
            ['role' => 'user', 'content' => $message],
        ],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $apiBody,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        // Fallback to simple response if API fails
        $reply = buildSimpleResponse($message, $matchingDeals, $stats);
    } else {
        $data = json_decode($response, true);
        $reply = $data['content'][0]['text'] ?? 'Sorry, I couldn\'t process that. Try browsing our deals!';
    }

    json_out([
        'reply' => $reply,
        'deals' => array_map(fn($d) => [
            'id' => $d['id'], 'title' => $d['title'],
            'price' => '$' . number_format((float)$d['sale_price'], 2),
            'pct' => $d['discount_pct'] . '%',
            'store' => $d['store'],
        ], array_slice($matchingDeals, 0, 5)),
    ]);

} catch (\Throwable $e) {
    json_out(['error' => 'Something went wrong. Try again!'], 500);
}

// ── Simple keyword-based fallback (no API key needed) ─────────────────────────
function buildSimpleResponse(string $msg, array $deals, array $stats): string {
    $msg = strtolower($msg);
    $count = count($deals);

    if (str_contains($msg, 'hello') || str_contains($msg, 'hi') || str_contains($msg, 'hey')) {
        return "Hey! I'm the 50OFF Deal Assistant. We have {$stats['total']} deals live right now, all 50%+ off. What are you looking for today?";
    }
    if ($count === 0) {
        return "I couldn't find specific deals matching that, but we have {$stats['total']} active deals across {$stats['stores']}. Try browsing by category!";
    }
    $top = $deals[0];
    if ($count === 1) {
        return "I found 1 matching deal: {$top['title']} at \${$top['sale_price']} ({$top['discount_pct']}% off from {$top['store']}). Check it out!";
    }
    return "I found {$count} deals for you! Top pick: {$top['title']} — \${$top['sale_price']} ({$top['discount_pct']}% off). See all matches below.";
}
