-- 50OFF Database Setup for Supabase (PostgreSQL)
-- Run this in the Supabase SQL Editor (https://supabase.com/dashboard → SQL Editor)

-- Store type (replaces MySQL ENUM)
CREATE TYPE store_type AS ENUM (
    'amazon','walmart','target','bestbuy','ebay','costco',
    'homedepot','lowes','macys','kohls','newegg','samsclub',
    'staples','adorama','bhphoto','other'
);

CREATE TYPE scraper_status AS ENUM ('success','error','partial');

-- Deals table
CREATE TABLE IF NOT EXISTS deals (
    id              SERIAL PRIMARY KEY,
    title           VARCHAR(500) NOT NULL,
    description     TEXT,
    original_price  DECIMAL(10,2) NOT NULL DEFAULT 0,
    sale_price      DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_pct    SMALLINT NOT NULL DEFAULT 0,
    image_url       TEXT,
    product_url     TEXT NOT NULL,
    affiliate_url   TEXT,
    store           store_type NOT NULL DEFAULT 'other',
    category        VARCHAR(100),
    rating          DECIMAL(3,2) DEFAULT NULL,
    review_count    INTEGER DEFAULT 0,
    is_featured     BOOLEAN NOT NULL DEFAULT FALSE,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    expires_at      TIMESTAMP DEFAULT NULL,
    scraped_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Unique index on product_url (first 255 chars equivalent)
CREATE UNIQUE INDEX IF NOT EXISTS uq_product_url ON deals (product_url);
CREATE INDEX IF NOT EXISTS idx_store ON deals (store);
CREATE INDEX IF NOT EXISTS idx_discount ON deals (discount_pct);
CREATE INDEX IF NOT EXISTS idx_category ON deals (category);
CREATE INDEX IF NOT EXISTS idx_featured ON deals (is_featured);
CREATE INDEX IF NOT EXISTS idx_active ON deals (is_active);
CREATE INDEX IF NOT EXISTS idx_scraped ON deals (scraped_at);

-- Auto-update updated_at trigger
CREATE OR REPLACE FUNCTION update_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER deals_updated_at
    BEFORE UPDATE ON deals
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id         SERIAL PRIMARY KEY,
    slug       VARCHAR(100) NOT NULL UNIQUE,
    name       VARCHAR(100) NOT NULL,
    icon       VARCHAR(50) DEFAULT '🏷️',
    sort_order SMALLINT DEFAULT 0
);

-- Scraper log table
CREATE TABLE IF NOT EXISTS scraper_log (
    id          SERIAL PRIMARY KEY,
    store       VARCHAR(50),
    status      scraper_status DEFAULT 'success',
    deals_found INTEGER DEFAULT 0,
    message     TEXT,
    ran_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Click tracking
CREATE TABLE IF NOT EXISTS clicks (
    id          SERIAL PRIMARY KEY,
    deal_id     INTEGER NOT NULL REFERENCES deals(id) ON DELETE CASCADE,
    ip_hash     VARCHAR(64),
    user_agent  VARCHAR(255),
    clicked_at  TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_click_deal ON clicks (deal_id);

-- Seed categories
INSERT INTO categories (slug, name, icon, sort_order) VALUES
('electronics',   'Electronics',   '📱', 1),
('clothing',      'Clothing',      '👗', 2),
('home',          'Home & Garden', '🏠', 3),
('toys',          'Toys & Games',  '🧸', 4),
('sports',        'Sports',        '⚽', 5),
('beauty',        'Beauty',        '💄', 6),
('books',         'Books',         '📚', 7),
('kitchen',       'Kitchen',       '🍳', 8),
('automotive',    'Automotive',    '🚗', 9),
('health',        'Health',        '💊', 10)
ON CONFLICT (slug) DO NOTHING;

-- Seed sample deals (for demo)
INSERT INTO deals (title, description, original_price, sale_price, discount_pct, image_url, product_url, affiliate_url, store, category, rating, review_count, is_featured) VALUES
('Apple AirPods Pro (2nd Gen)', 'Active Noise Cancelling, Transparency mode, USB-C', 249.00, 99.00, 60, 'https://m.media-amazon.com/images/I/61SUj2aKoEL._AC_SL1500_.jpg', 'https://amazon.com/dp/B0BDHWDR12', 'https://amazon.com/dp/B0BDHWDR12?tag=50off-20', 'amazon', 'electronics', 4.7, 82341, TRUE),
('Ninja Foodi 8-Qt Air Fryer', '8-in-1 multi-cooker with air fryer, pressure cooker', 199.99, 79.99, 60, 'https://m.media-amazon.com/images/I/71VruzBUMRL._AC_SL1500_.jpg', 'https://walmart.com/ip/123456', 'https://walmart.com/ip/123456?affid=50off', 'walmart', 'kitchen', 4.6, 15234, TRUE),
('Levi''s 501 Original Jeans', 'Classic fit denim jeans, multiple washes available', 79.50, 34.99, 56, 'https://lsco.scene7.com/is/image/lscoco/005010114-front-pdp.jpg', 'https://target.com/p/levis-jeans/-/A-12345', 'https://target.com/p/levis-jeans/-/A-12345?afid=50off', 'target', 'clothing', 4.4, 6730, FALSE),
('Samsung 65" 4K QLED TV', 'Quantum HDR, Alexa built-in, 120Hz', 1299.99, 549.99, 58, 'https://image-us.samsung.com/SamsungUS/home/televisions-home-theater/tvs/qled-4k-tvs/qn65q80cafxza.jpg', 'https://bestbuy.com/site/samsung-tv/6535678', 'https://bestbuy.com/site/samsung-tv/6535678?lid=50off', 'bestbuy', 'electronics', 4.8, 3892, TRUE),
('Instant Pot Duo 7-in-1', '6 Qt, pressure cooker, slow cooker, rice cooker', 99.95, 44.99, 55, 'https://m.media-amazon.com/images/I/71EGuoXqBRL._AC_SL1500_.jpg', 'https://amazon.com/dp/B00FLYWNYQ', 'https://amazon.com/dp/B00FLYWNYQ?tag=50off-20', 'amazon', 'kitchen', 4.7, 129821, FALSE),
('Nike Air Max 270', 'Men''s running shoes, multiple colors', 150.00, 69.99, 53, 'https://static.nike.com/a/images/t_PDP_1280_v1/air-max-270.jpg', 'https://target.com/p/nike-air-max-270/-/A-98765', 'https://target.com/p/nike-air-max-270/-/A-98765?afid=50off', 'target', 'sports', 4.5, 9876, FALSE),
('KitchenAid Stand Mixer 5Qt', 'Tilt-head, 59 touchpoints, 10 speeds', 449.99, 199.99, 56, 'https://kitchenaid.com/content/dam/documents/NA/usa/images/product-images.jpg', 'https://walmart.com/ip/kitchenaid-mixer/789012', 'https://walmart.com/ip/kitchenaid-mixer/789012?affid=50off', 'walmart', 'kitchen', 4.9, 45231, TRUE),
('Dyson V15 Detect Cordless Vacuum', 'Laser reveal dust, HEPA filter, 60 min runtime', 749.99, 349.99, 53, 'https://dyson-h.assetsadobe2.com/is/image/content/dam/dyson/images/products/hero/394172-01.png', 'https://amazon.com/dp/B09M7HLLN9', 'https://amazon.com/dp/B09M7HLLN9?tag=50off-20', 'amazon', 'home', 4.6, 12093, FALSE)
ON CONFLICT (product_url) DO NOTHING;
