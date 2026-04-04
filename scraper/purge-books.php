#!/usr/bin/env php
<?php
/**
 * purge-books.php — Remove book deals from the database
 *
 * USAGE: php scraper/purge-books.php
 */

require_once dirname(__DIR__) . '/includes/db.php';

$db = getDB();

// Count before
$before = (int)$db->query("
    SELECT COUNT(*) FROM deals
    WHERE category = 'books'
       OR title LIKE '%paperback%'
       OR title LIKE '%hardcover%'
       OR title LIKE '%kindle%'
       OR title LIKE '%audiobook%'
       OR title LIKE '%novel%'
       OR title LIKE '%isbn%'
       OR title LIKE '%edition)%'
")->fetchColumn();

echo "Books found: {$before}\n";

if ($before > 0) {
    $db->exec("
        DELETE FROM deals
        WHERE category = 'books'
           OR title LIKE '%paperback%'
           OR title LIKE '%hardcover%'
           OR title LIKE '%kindle%'
           OR title LIKE '%audiobook%'
           OR title LIKE '%novel%'
           OR title LIKE '%isbn%'
           OR title LIKE '%edition)%'
    ");
    echo "Deleted {$before} book deals.\n";
} else {
    echo "No books to purge.\n";
}

$total = (int)$db->query("SELECT COUNT(*) FROM deals WHERE is_active=1")->fetchColumn();
echo "Remaining active deals: {$total}\n";
