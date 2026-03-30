-- 50OFF Database Setup
-- Run this file once to initialize the database

CREATE DATABASE IF NOT EXISTS `50off_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `50off_db`;

-- Products / Deals table
CREATE TABLE IF NOT EXISTS `deals` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title`           VARCHAR(500) NOT NULL,
    `description`     TEXT,
    `original_price`  DECIMAL(10,2) NOT NULL DEFAULT 0,
    `sale_price`      DECIMAL(10,2) NOT NULL DEFAULT 0,
    `discount_pct`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `image_url`       VARCHAR(1000),
    `product_url`     VARCHAR(1000) NOT NULL,
    `affiliate_url`   VARCHAR(1000),
    `store`           ENUM('amazon','walmart','target','bestbuy','ebay','costco','homedepot','lowes','macys','kohls','newegg','samsclub','staples','adorama','bhphoto','other') NOT NULL DEFAULT 'other',
    `category`        VARCHAR(100),
    `rating`          DECIMAL(3,2) DEFAULT NULL,
    `review_count`    INT UNSIGNED DEFAULT 0,
    `is_featured`     TINYINT(1) NOT NULL DEFAULT 0,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `expires_at`      DATETIME DEFAULT NULL,
    `scraped_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_product_url`  (`product_url`(255)),
    INDEX `idx_store`        (`store`),
    INDEX `idx_discount`     (`discount_pct`),
    INDEX `idx_category`     (`category`),
    INDEX `idx_featured`     (`is_featured`),
    INDEX `idx_active`       (`is_active`),
    INDEX `idx_scraped`      (`scraped_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories table
CREATE TABLE IF NOT EXISTS `categories` (
    `id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug`  VARCHAR(100) NOT NULL UNIQUE,
    `name`  VARCHAR(100) NOT NULL,
    `icon`  VARCHAR(50) DEFAULT '🏷️',
    `sort_order` TINYINT UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Scraper log table
CREATE TABLE IF NOT EXISTS `scraper_log` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `store`      VARCHAR(50),
    `status`     ENUM('success','error','partial') DEFAULT 'success',
    `deals_found` INT UNSIGNED DEFAULT 0,
    `message`    TEXT,
    `ran_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Click tracking
CREATE TABLE IF NOT EXISTS `clicks` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `deal_id`    INT UNSIGNED NOT NULL,
    `ip_hash`    VARCHAR(64),
    `user_agent` VARCHAR(255),
    `clicked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`deal_id`) REFERENCES `deals`(`id`) ON DELETE CASCADE,
    INDEX `idx_deal` (`deal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed categories
INSERT IGNORE INTO `categories` (`slug`, `name`, `icon`, `sort_order`) VALUES
('electronics',   'Electronics',   '📱', 1),
('clothing',      'Clothing',      '👗', 2),
('home',          'Home & Garden', '🏠', 3),
('toys',          'Toys & Games',  '🧸', 4),
('sports',        'Sports',        '⚽', 5),
('beauty',        'Beauty',        '💄', 6),
('books',         'Books',         '📚', 7),
('kitchen',       'Kitchen',       '🍳', 8),
('automotive',    'Automotive',    '🚗', 9),
('health',        'Health',        '💊', 10);

-- Seed sample deals (for local dev / demo)
INSERT INTO `deals` (`title`, `description`, `original_price`, `sale_price`, `discount_pct`, `image_url`, `product_url`, `affiliate_url`, `store`, `category`, `rating`, `review_count`, `is_featured`) VALUES
('Apple AirPods Pro (2nd Gen)', 'Active Noise Cancelling, Transparency mode, USB-C', 249.00, 99.00, 60, 'https://m.media-amazon.com/images/I/61SUj2aKoEL._AC_SL1500_.jpg', 'https://amazon.com/dp/B0BDHWDR12', 'https://amazon.com/dp/B0BDHWDR12?tag=50off-20', 'amazon', 'electronics', 4.7, 82341, 1),
('Ninja Foodi 8-Qt Air Fryer', '8-in-1 multi-cooker with air fryer, pressure cooker', 199.99, 79.99, 60, 'https://m.media-amazon.com/images/I/71VruzBUMRL._AC_SL1500_.jpg', 'https://walmart.com/ip/123456', 'https://walmart.com/ip/123456?affid=50off', 'walmart', 'kitchen', 4.6, 15234, 1),
('Levi\'s 501 Original Jeans', 'Classic fit denim jeans, multiple washes available', 79.50, 34.99, 56, 'https://lsco.scene7.com/is/image/lscoco/005010114-front-pdp.jpg', 'https://target.com/p/levis-jeans/-/A-12345', 'https://target.com/p/levis-jeans/-/A-12345?afid=50off', 'target', 'clothing', 4.4, 6730, 0),
('Samsung 65" 4K QLED TV', 'Quantum HDR, Alexa built-in, 120Hz', 1299.99, 549.99, 58, 'https://image-us.samsung.com/SamsungUS/home/televisions-home-theater/tvs/qled-4k-tvs/qn65q80cafxza.jpg', 'https://bestbuy.com/site/samsung-tv/6535678', 'https://bestbuy.com/site/samsung-tv/6535678?lid=50off', 'bestbuy', 'electronics', 4.8, 3892, 1),
('Instant Pot Duo 7-in-1', '6 Qt, pressure cooker, slow cooker, rice cooker', 99.95, 44.99, 55, 'https://m.media-amazon.com/images/I/71EGuoXqBRL._AC_SL1500_.jpg', 'https://amazon.com/dp/B00FLYWNYQ', 'https://amazon.com/dp/B00FLYWNYQ?tag=50off-20', 'amazon', 'kitchen', 4.7, 129821, 0),
('Nike Air Max 270', 'Men\'s running shoes, multiple colors', 150.00, 69.99, 53, 'https://static.nike.com/a/images/t_PDP_1280_v1/air-max-270.jpg', 'https://target.com/p/nike-air-max-270/-/A-98765', 'https://target.com/p/nike-air-max-270/-/A-98765?afid=50off', 'target', 'sports', 4.5, 9876, 0),
('KitchenAid Stand Mixer 5Qt', 'Tilt-head, 59 touchpoints, 10 speeds', 449.99, 199.99, 56, 'https://kitchenaid.com/content/dam/documents/NA/usa/images/product-images.jpg', 'https://walmart.com/ip/kitchenaid-mixer/789012', 'https://walmart.com/ip/kitchenaid-mixer/789012?affid=50off', 'walmart', 'kitchen', 4.9, 45231, 1),
('Dyson V15 Detect Cordless Vacuum', 'Laser reveal dust, HEPA filter, 60 min runtime', 749.99, 349.99, 53, 'https://dyson-h.assetsadobe2.com/is/image/content/dam/dyson/images/products/hero/394172-01.png', 'https://amazon.com/dp/B09M7HLLN9', 'https://amazon.com/dp/B09M7HLLN9?tag=50off-20', 'amazon', 'home', 4.6, 12093, 0);
