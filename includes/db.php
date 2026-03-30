<?php
// Database Configuration — reads from environment variable (Supabase PostgreSQL)
// Set DATABASE_URL in Vercel env vars, e.g.:
// postgresql://postgres:[PASSWORD]@db.xxx.supabase.co:5432/postgres

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $url = getenv('DATABASE_URL');
        if (!$url) {
            die(json_encode(['error' => 'DATABASE_URL environment variable not set']));
        }
        $params = parse_url($url);
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $params['host'],
            $params['port'] ?? 5432,
            ltrim($params['path'] ?? '/postgres', '/')
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, $params['user'], $params['pass'] ?? '', $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}
