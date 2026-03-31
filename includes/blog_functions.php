<?php
// includes/blog_functions.php — Blog DB helpers

function getBlogPosts(int $limit = 12, int $offset = 0, string $category = ''): array {
    $db    = getDB();
    $where = ['is_published = 1'];
    $params = [];
    if ($category) {
        $where[]          = 'category = :cat';
        $params[':cat']   = $category;
    }
    $sql = "SELECT id, slug, title, excerpt, category, tags, og_image, author, published_at, view_count
            FROM blog_posts
            WHERE " . implode(' AND ', $where) . "
            ORDER BY published_at DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBlogPost(string $slug): ?array {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM blog_posts WHERE slug = ? AND is_published = 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $db->prepare("UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?")
           ->execute([$row['id']]);
    }
    return $row ?: null;
}

function countBlogPosts(string $category = ''): int {
    $db    = getDB();
    $where = ['is_published = 1'];
    $params = [];
    if ($category) {
        $where[]        = 'category = :cat';
        $params[':cat'] = $category;
    }
    $stmt = $db->prepare("SELECT COUNT(*) FROM blog_posts WHERE " . implode(' AND ', $where));
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function getRelatedPosts(int $id, string $category, int $limit = 3): array {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT id, slug, title, excerpt, og_image, published_at
         FROM blog_posts
         WHERE is_published = 1 AND id != :id AND category = :cat
         ORDER BY published_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':id',    $id,       PDO::PARAM_INT);
    $stmt->bindValue(':cat',   $category);
    $stmt->bindValue(':limit', $limit,    PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
