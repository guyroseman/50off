-- ============================================================
-- 50OFF — Database Erase & Reset Script
-- ============================================================
-- Wipes ALL scraped data, logs, and click history, then
-- re-seeds the category table so the site stays functional.
--
-- RUN WITH:
--   mysql -u YOUR_USER -p 50off_db < reset_database.sql
--
-- FULL COMMAND (copy-paste ready):
--   mysql -u root -p 50off_db < ~/Documents/Other/Projects/50off/50off/reset_database.sql
--
-- ⚠  This is IRREVERSIBLE. All deals, clicks and logs will be gone.
-- ============================================================

USE `50off_db`;

-- Disable FK checks so tables can be truncated in any order
SET FOREIGN_KEY_CHECKS = 0;

-- ── Erase all data ────────────────────────────────────────────────────────────
TRUNCATE TABLE `clicks`;
TRUNCATE TABLE `scraper_log`;
TRUNCATE TABLE `deals`;
TRUNCATE TABLE `categories`;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Re-seed default categories ────────────────────────────────────────────────
INSERT INTO `categories` (`slug`, `name`, `icon`, `sort_order`) VALUES
('electronics',   'Electronics',   '📱',  1),
('clothing',      'Clothing',      '👗',  2),
('home',          'Home & Garden', '🏠',  3),
('toys',          'Toys & Games',  '🧸',  4),
('sports',        'Sports',        '⚽',  5),
('beauty',        'Beauty',        '💄',  6),
('books',         'Books',         '📚',  7),
('kitchen',       'Kitchen',       '🍳',  8),
('automotive',    'Automotive',    '🚗',  9),
('health',        'Health',        '💊', 10),
('other',         'Other',         '🏷️', 11);

-- ── Confirm row counts after reset ───────────────────────────────────────────
SELECT 'clicks'      AS `table`, COUNT(*) AS `rows` FROM `clicks`
UNION ALL
SELECT 'scraper_log',              COUNT(*)           FROM `scraper_log`
UNION ALL
SELECT 'deals',                    COUNT(*)           FROM `deals`
UNION ALL
SELECT 'categories',               COUNT(*)           FROM `categories`;

-- Done — run the scraper next:
--   php ~/Documents/Other/Projects/50off/50off/scraper/run.php
