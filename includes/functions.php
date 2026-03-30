<?php
require_once __DIR__ . '/db.php';

// ─── Deals ────────────────────────────────────────────────────────────────────

function getDeals(array $opts = []): array {
    $db = getDB();
    $where  = ['d.is_active = TRUE', 'd.discount_pct >= 50'];
    $params = [];

    if (!empty($opts['store'])) {
        $where[] = 'd.store = :store';
        $params[':store'] = $opts['store'];
    }
    if (!empty($opts['category'])) {
        $where[] = 'd.category = :cat';
        $params[':cat'] = $opts['category'];
    }
    if (!empty($opts['search'])) {
        $where[] = '(d.title ILIKE :q OR d.description ILIKE :q2)';
        $params[':q']  = '%' . $opts['search'] . '%';
        $params[':q2'] = '%' . $opts['search'] . '%';
    }
    if (!empty($opts['featured'])) {
        $where[] = 'd.is_featured = TRUE';
    }

    $orderMap = [
        'discount' => 'd.discount_pct DESC, d.scraped_at DESC',
        'newest'   => 'd.scraped_at DESC',
        'price'    => 'd.sale_price ASC',
    ];
    $order = $orderMap[$opts['sort'] ?? 'discount'] ?? 'd.discount_pct DESC';

    $limit  = (int)($opts['limit']  ?? 24);
    $offset = (int)($opts['offset'] ?? 0);

    $sql = "SELECT d.*
            FROM deals d
            WHERE " . implode(' AND ', $where) . "
            ORDER BY $order
            LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDealById(int $id): ?array {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM deals WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function countDeals(array $opts = []): int {
    $db     = getDB();
    $where  = ['is_active = TRUE', 'discount_pct >= 50'];
    $params = [];
    if (!empty($opts['store']))    { $where[] = 'store = :store';    $params[':store'] = $opts['store']; }
    if (!empty($opts['category'])) { $where[] = 'category = :cat';   $params[':cat']   = $opts['category']; }
    if (!empty($opts['search']))   { $where[] = 'title ILIKE :q';    $params[':q']     = '%'.$opts['search'].'%'; }
    $stmt = $db->prepare("SELECT COUNT(*) FROM deals WHERE " . implode(' AND ', $where));
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function getStores(): array {
    $db   = getDB();
    $stmt = $db->query("
        SELECT store, COUNT(*) as cnt
        FROM deals WHERE is_active=TRUE AND discount_pct>=50
        GROUP BY store ORDER BY cnt DESC LIMIT 8
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCategories(): array {
    $db   = getDB();
    $stmt = $db->query("
        SELECT c.id, c.slug, c.name, c.icon, c.sort_order, COUNT(d.id) as deal_count
        FROM categories c
        LEFT JOIN deals d ON d.category = c.slug AND d.is_active=TRUE AND d.discount_pct>=50
        GROUP BY c.id, c.slug, c.name, c.icon, c.sort_order ORDER BY c.sort_order
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ─── Click Tracking ──────────────────────────────────────────────────────────

function trackClick(int $dealId): void {
    try {
        $db   = getDB();
        $stmt = $db->prepare("INSERT INTO clicks (deal_id, ip_hash, user_agent) VALUES (?,?,?)");
        $ip   = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
        $ua   = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $stmt->execute([$dealId, $ip, $ua]);
    } catch (Exception $e) {}
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatPrice(float $p): string { return '$' . number_format($p, 2); }

function savings(float $orig, float $sale): string { return formatPrice($orig - $sale); }

function storeLogo(string $store): string {
    return [
        'amazon'    => '🛒',
        'walmart'   => '🔵',
        'target'    => '🎯',
        'bestbuy'   => '🟡',
        'ebay'      => '🔴',
        'costco'    => '⭕',
        'homedepot' => '🟠',
        'lowes'     => '🔷',
        'macys'     => '⭐',
        'kohls'     => '💙',
        'newegg'    => '🖥️',
        'samsclub'  => '🔶',
        'staples'   => '📎',
        'adorama'   => '📷',
        'bhphoto'   => '📸',
        'other'     => '🏷️',
    ][$store] ?? '🏷️';
}

function storeColor(string $store): string {
    return [
        'amazon'    => '#FF9900',
        'walmart'   => '#0071CE',
        'target'    => '#CC0000',
        'bestbuy'   => '#003B64',
        'ebay'      => '#E53238',
        'costco'    => '#005DAA',
        'homedepot' => '#F96302',
        'lowes'     => '#004990',
        'macys'     => '#B11116',
        'kohls'     => '#315CA4',
        'newegg'    => '#F37021',
        'samsclub'  => '#007DC6',
        'staples'   => '#CC0000',
        'adorama'   => '#0060A9',
        'bhphoto'   => '#004E98',
        'other'     => '#6B7280',
    ][$store] ?? '#6B7280';
}

function paginate(int $total, int $perPage, int $current): array {
    return [
        'total'    => $total,
        'per_page' => $perPage,
        'current'  => $current,
        'pages'    => (int)ceil($total / max(1, $perPage)),
    ];
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function redirect(string $url): never {
    header("Location: $url"); exit;
}

function getParam(string $key, mixed $default = ''): mixed {
    $val = $_GET[$key] ?? $default;
    return is_string($val) ? trim($val) : $val;
}

function updateParam(string $key, mixed $val): string {
    $params       = $_GET;
    $params[$key] = $val;
    unset($params['page']);
    return '/?' . http_build_query($params);
}
