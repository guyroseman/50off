-- 50OFF: Subscriber email + saved deals system
-- Run once on Hostinger via phpMyAdmin or SSH mysql client

CREATE TABLE IF NOT EXISTS subscribers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(255) NOT NULL,
    token      CHAR(64)     NOT NULL,
    verified   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT NOW(),
    UNIQUE KEY uq_email (email),
    UNIQUE KEY uq_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS saved_deals (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT UNSIGNED NOT NULL,
    deal_id       INT UNSIGNED NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT NOW(),
    UNIQUE KEY uq_sub_deal (subscriber_id, deal_id),
    KEY idx_subscriber (subscriber_id),
    CONSTRAINT fk_sd_subscriber FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    CONSTRAINT fk_sd_deal       FOREIGN KEY (deal_id)       REFERENCES deals(id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
