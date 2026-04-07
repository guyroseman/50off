<?php
/**
 * /api/chat.php — 50OFF Deal Assistant
 *
 * POST { message: "..." }  →  { reply: "...", deals: [...] }
 *
 * Works fully without a Claude API key using a smart rule-based engine.
 * If CLAUDE_API_KEY env var is set, upgrades to Claude for free-form answers.
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../includes/db.php';

define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: '');
define('CLAUDE_MODEL',   'claude-haiku-4-5-20251001');

function json_out(array $data, int $code = 200): void {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Rate limit: 30 requests / IP / hour
function checkRateLimit(): void {
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $file = sys_get_temp_dir() . '/50off_chat_' . md5($ip);
    $data = file_exists($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];
    if (time() > ($data['reset'] ?? 0)) $data = ['count' => 0, 'reset' => time() + 3600];
    if (($data['count'] ?? 0) >= 30) json_out(['error' => 'Too many messages. Try again in an hour!'], 429);
    $data['count']++;
    file_put_contents($file, json_encode($data));
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatDeal(array $d): string {
    $save = round((float)$d['original_price'] - (float)$d['sale_price']);
    return "{$d['title']} — \${$d['sale_price']} ({$d['discount_pct']}% off, save \${$save}) at " . ucfirst($d['store']);
}

function dealCards(array $deals, int $limit = 5): array {
    $out = [];
    foreach (array_slice($deals, 0, $limit) as $d) {
        $out[] = [
            'id'    => $d['id'],
            'title' => $d['title'],
            'price' => '$' . number_format((float)$d['sale_price'], 2),
            'pct'   => $d['discount_pct'] . '%',
            'store' => $d['store'],
        ];
    }
    return $out;
}

// ── Main intent-based engine ─────────────────────────────────────────────────

function buildSmartResponse(string $raw, array $deals, array $stats, PDO $db): string {
    $msg   = strtolower(trim($raw));
    $count = count($deals);
    $total = (int)($stats['total'] ?? 0);
    $avg   = (int)($stats['avg_pct'] ?? 65);

    // ── Greetings ────────────────────────────────────────────────────────────
    if (preg_match('/^(hi|hey|hello|sup|yo|hiya|good\s*(morning|afternoon|evening))[!. ]?$/i', $msg)) {
        $greetings = [
            "Hey! 👋 I'm your 50OFF deal assistant. We've got {$total} live deals — all 50%+ off. What are you shopping for?",
            "Hi there! 🏷️ {$total} deals available right now, averaging {$avg}% off. Tell me what you need!",
            "Hello! I'm here to find you the best deals. We're talking half price or better on {$total} items. What can I help you find?",
        ];
        return $greetings[array_rand($greetings)];
    }

    // ── How are you / thanks ─────────────────────────────────────────────────
    if (preg_match('/how are you|how\'?s it going|what\'?s up/i', $msg)) {
        return "Doing great — just hunting deals! 🔥 We've got {$total} active deals right now. What are you looking for?";
    }
    if (preg_match('/thanks|thank you|thx|cheers/i', $msg)) {
        return "You're welcome! Happy saving 💰 Anything else I can help you find?";
    }

    // ── What is 50OFF / how does it work ────────────────────────────────────
    if (preg_match('/what is|how does|how do you|what do you|tell me about|explain|about 50off/i', $msg)) {
        return "50OFF only shows deals at 50% off or more — from Amazon, Target, eBay, 6pm and Best Buy. Our scrapers run every 3 hours so prices are always fresh. Right now we have {$total} live deals. What category interests you?";
    }

    // ── Which stores ─────────────────────────────────────────────────────────
    if (preg_match('/which stores?|what stores?|where from|retailers?/i', $msg)) {
        return "We track 🛒 Amazon, 🎯 Target, 🔴 eBay, 👠 6pm and 🟡 Best Buy. Want me to find deals from a specific store?";
    }

    // ── Store-specific ───────────────────────────────────────────────────────
    $storeMap = [
        'amazon'  => 'amazon',
        'target'  => 'target',
        'ebay'    => 'ebay',
        '6pm'     => '6pm',
        'bestbuy' => 'bestbuy',
        'best buy'=> 'bestbuy',
    ];
    foreach ($storeMap as $kw => $storeSlug) {
        if (str_contains($msg, $kw)) {
            $storeDeals = fetchDeals($db, store: $storeSlug, limit: 5);
            $sc = count($storeDeals);
            if ($sc === 0) return "No " . ucfirst($storeSlug) . " deals at 50%+ off right now — check back soon! Meanwhile, we have {$total} deals from other stores.";
            $top = $storeDeals[0];
            return "Found {$sc} deals from " . ucfirst($storeSlug) . "! Top pick: " . formatDeal($top) . ". See all " . ucfirst($storeSlug) . " deals at 50off.sale.com/?store={$storeSlug}";
        }
    }

    // ── Price intent: under $X ───────────────────────────────────────────────
    if (preg_match('/under\s*\$?(\d+)|less\s*than\s*\$?(\d+)|cheaper\s*than\s*\$?(\d+)|max\s*\$?(\d+)/i', $msg, $pm)) {
        $maxPrice = (float)array_filter($pm)[array_key_first(array_filter($pm, fn($v,$k) => $k > 0 && $v, ARRAY_FILTER_USE_BOTH))];
        if ($maxPrice > 0) {
            $priceDeals = fetchDeals($db, maxPrice: $maxPrice, limit: 5);
            $pc = count($priceDeals);
            if ($pc === 0) return "Nothing under \${$maxPrice} at 50%+ off right now — try raising the budget a bit, or browse our cheapest deals!";
            $top = $priceDeals[0];
            return "Found {$pc} deals under \${$maxPrice}! Best pick: " . formatDeal($top) . ".";
        }
    }

    // ── Category intents ─────────────────────────────────────────────────────
    $catMap = [
        'shoe|boot|sneaker|sandal|heel|loafer|running shoe|trainer' => ['clothing', 'shoes'],
        'cloth|shirt|dress|jacket|coat|pant|jean|top|outfit|wear|apparel|fashion' => ['clothing', 'clothing'],
        'electronic|laptop|computer|tablet|ipad|phone|gadget|tech' => ['electronics', 'electronics'],
        'headphone|earbud|speaker|audio|bluetooth|airpod|noise cancel' => ['electronics', 'headphones'],
        'tv|television|monitor|screen' => ['electronics', 'TVs'],
        'kitchen|cook|air fryer|blender|pot|pan|cookware|appliance|coffee maker|instant pot' => ['kitchen', 'kitchen appliances'],
        'toy|game|lego|kids|children|baby|puzzle' => ['toys', 'toys'],
        'sport|fitness|gym|yoga|running|bicycle|bike|outdoor|camping|hiking' => ['sports', 'sports & fitness'],
        'beauty|skincare|makeup|cosmetic|perfume|hair|serum|moisturizer' => ['beauty', 'beauty products'],
        'health|supplement|vitamin|medicine|wellness' => ['health', 'health products'],
        'home|furniture|decor|rug|curtain|pillow|bedding|sheet|mattress|sofa|couch|chair|lamp' => ['home', 'home & furniture'],
        'tool|drill|power tool|hardware|garden|lawn' => ['tools', 'tools'],
        'pet|dog|cat|animal' => ['pets', 'pet supplies'],
        'book|novel|reading' => ['', 'books'],
        'bag|backpack|luggage|wallet|purse|handbag' => ['clothing', 'bags & accessories'],
        'watch|jewel|jewelry|necklace|ring|bracelet' => ['clothing', 'accessories'],
    ];

    foreach ($catMap as $pattern => $catInfo) {
        [$dbCat, $label] = $catInfo;
        if (preg_match('/(' . $pattern . ')/i', $msg)) {
            // Also check if store was mentioned
            $catDeals = fetchDeals($db, category: $dbCat, keywords: extractKeywords($msg), limit: 10);
            $cc = count($catDeals);
            if ($cc === 0) {
                return "No {$label} deals at 50%+ off right now, but check back — we update every 3 hours! Meanwhile browse all deals at 50offsale.com.";
            }
            $top  = $catDeals[0];
            $top2 = $catDeals[1] ?? null;
            $reply = "Found {$cc} {$label} deals at 50%+ off! 🎉 Top pick: " . formatDeal($top) . ".";
            if ($top2) $reply .= " Also check out: " . formatDeal($top2) . ".";
            return $reply;
        }
    }

    // ── "Best deals" / "top deals" / "what's on sale" ───────────────────────
    if (preg_match('/best|top|hottest|biggest|most|what.?s (on sale|new|hot)|trending|popular|deal of the day/i', $msg)) {
        $topDeals = fetchDeals($db, limit: 5, sort: 'discount');
        if (empty($topDeals)) return "Check back in a bit — deals refresh every 3 hours!";
        $top = $topDeals[0];
        $reply = "🔥 Hottest deal right now: " . formatDeal($top) . ".";
        if (count($topDeals) > 1) $reply .= " Plus " . (count($topDeals) - 1) . " more below!";
        return $reply;
    }

    // ── "Cheapest" / "lowest price" ─────────────────────────────────────────
    if (preg_match('/cheapest|lowest|budget|affordable|inexpensive|cheap/i', $msg)) {
        $cheapDeals = fetchDeals($db, limit: 5, sort: 'price_asc');
        if (empty($cheapDeals)) return "Try browsing by category — we've got {$total} deals live right now!";
        $top = $cheapDeals[0];
        return "💰 Lowest priced deals at 50%+ off: starting at \${$top['sale_price']}! Top pick: " . formatDeal($top) . ".";
    }

    // ── Save / account ───────────────────────────────────────────────────────
    if (preg_match('/save|wishlist|favourite|favorite|bookmark|my deals|my account/i', $msg)) {
        return "Click the ♡ heart on any deal card to save it! You'll need a free account — sign up at 50offsale.com/signup.php. Saved deals appear in your account page anytime.";
    }

    // ── How often updated ────────────────────────────────────────────────────
    if (preg_match('/how often|when.*(update|refresh|new)|how fresh|latest/i', $msg)) {
        return "Our scrapers run every 3 hours automatically — so deals you see are always current. We've added deals within the last 3 hours right now!";
    }

    // ── Affiliate / how you make money ──────────────────────────────────────
    if (preg_match('/affiliate|commission|how do you make|profit|earn/i', $msg)) {
        return "Good question! When you click a deal and buy, we earn a small affiliate commission from the retailer — at no extra cost to you. It keeps the site free and the scrapers running!";
    }

    // ── Keyword-based fallback with real DB results ──────────────────────────
    if ($count > 0) {
        $top = $deals[0];
        $reply = "Found {$count} deal" . ($count > 1 ? 's' : '') . " matching that! ";
        $reply .= "Best match: " . formatDeal($top) . ".";
        if ($count > 1) $reply .= " " . ($count - 1) . " more below 👇";
        return $reply;
    }

    // ── Complete no-match fallback ───────────────────────────────────────────
    $suggestions = ['electronics', 'clothing', 'kitchen', 'home', 'beauty', 'sports', 'toys'];
    $pick = $suggestions[array_rand($suggestions)];
    return "I couldn't find an exact match for that, but we have {$total} deals live right now! Try asking about a category like \"{$pick}\" or a store like \"Amazon\" — or just browse 50offsale.com!";
}

// ── DB fetchers ───────────────────────────────────────────────────────────────

function extractKeywords(string $msg): array {
    $stopwords = ['the','and','for','are','but','not','you','all','any','can','her','was','one','our','out','day','get','has','him','his','how','its','let','may','now','use','way','who','did','will','with','have','this','that','from','they','been','were','what','when','your','more','also','into','just','like','over','such','than','then','them','these','want','some','need','find','show','best','deal','deals','sale'];
    $words = preg_split('/\W+/', strtolower($msg));
    return array_values(array_filter($words, fn($w) => strlen($w) >= 3 && !in_array($w, $stopwords)));
}

function fetchDeals(PDO $db, string $category = '', string $store = '', array $keywords = [], float $maxPrice = 0, int $limit = 10, string $sort = 'discount'): array {
    $where  = ['is_active = 1', 'discount_pct >= 50'];
    $params = [];

    if ($category) {
        $where[]            = 'category = :cat';
        $params[':cat']     = $category;
    }
    if ($store) {
        $where[]            = 'store = :store';
        $params[':store']   = $store;
    }
    if ($maxPrice > 0) {
        $where[]            = 'sale_price <= :maxp';
        $params[':maxp']    = $maxPrice;
    }
    if (!empty($keywords)) {
        $kw_clauses = [];
        foreach (array_slice($keywords, 0, 4) as $i => $kw) {
            $kw_clauses[] = "(LOWER(title) LIKE :kw{$i} OR LOWER(category) LIKE :kwc{$i})";
            $params[":kw{$i}"]  = "%{$kw}%";
            $params[":kwc{$i}"] = "%{$kw}%";
        }
        $where[] = '(' . implode(' OR ', $kw_clauses) . ')';
    }

    $orderBy = match($sort) {
        'price_asc' => 'sale_price ASC',
        'newest'    => 'scraped_at DESC',
        default     => 'discount_pct DESC',
    };

    $sql  = "SELECT id, title, sale_price, original_price, discount_pct, store, category
             FROM deals WHERE " . implode(' AND ', $where) . "
             ORDER BY {$orderBy} LIMIT :lim";
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Main ──────────────────────────────────────────────────────────────────────
try {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $message = trim($body['message'] ?? '');
    if (!$message || strlen($message) > 500) json_out(['error' => 'Message required (max 500 chars)'], 400);

    checkRateLimit();

    $db = getDB();

    // Site-wide stats
    $stats = $db->query(
        "SELECT COUNT(*) as total, ROUND(AVG(discount_pct)) as avg_pct,
         GROUP_CONCAT(DISTINCT store ORDER BY store) as stores
         FROM deals WHERE is_active = 1 AND discount_pct >= 50"
    )->fetch(PDO::FETCH_ASSOC);

    // Keyword search for matching deals (used by both engines)
    $keywords     = extractKeywords($message);
    $matchingDeals = !empty($keywords) ? fetchDeals($db, keywords: $keywords, limit: 10) : [];

    // If keyword search empty, get top deals as context
    if (empty($matchingDeals)) {
        $matchingDeals = fetchDeals($db, limit: 10, sort: 'discount');
    }

    // ── No API key → smart rule-based engine (always used in production for now)
    if (!CLAUDE_API_KEY) {
        $reply = buildSmartResponse($message, $matchingDeals, $stats, $db);
        json_out(['reply' => $reply, 'deals' => dealCards($matchingDeals)]);
    }

    // ── Claude API ────────────────────────────────────────────────────────────
    $dealsCtx = "Live deals ({$stats['total']} total, avg {$stats['avg_pct']}% off, stores: {$stats['stores']}):\n";
    foreach ($matchingDeals as $d) {
        $dealsCtx .= "- [{$d['id']}] {$d['title']} \${$d['sale_price']} ({$d['discount_pct']}% off) [{$d['store']}]\n";
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'model'      => CLAUDE_MODEL,
            'max_tokens' => 280,
            'system'     => "You are the 50OFF Deal Assistant on 50offsale.com — friendly, concise, helpful. "
                          . "Only 50%+ off deals from Amazon, Target, eBay, 6pm, Best Buy. "
                          . "Keep answers to 2-3 sentences. When recommending a deal mention its ID like [123]. "
                          . "If nothing matches, suggest a category or store.\n\n{$dealsCtx}",
            'messages'   => [['role' => 'user', 'content' => $message]],
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $reply = ($httpCode === 200 && $resp)
        ? (json_decode($resp, true)['content'][0]['text'] ?? buildSmartResponse($message, $matchingDeals, $stats, $db))
        : buildSmartResponse($message, $matchingDeals, $stats, $db);

    json_out(['reply' => $reply, 'deals' => dealCards($matchingDeals)]);

} catch (\Throwable $e) {
    json_out(['error' => 'Something went wrong — try again!'], 500);
}
