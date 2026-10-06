-- =====================================================================
--  Laboratory Exercise No. 6 - Product Management System
--  Database: MySQL (Aiven MySQL)           Charset: utf8mb4
--
--  Run this ONLY if you do not want to use the LavaLust migrations.
--  (Preferred way:  php lava migration run  - it creates the same tables.)
--
--  In Aiven, the database already exists (default name: defaultdb), so
--  there is no CREATE DATABASE here. Select it, then run this whole file.
-- =====================================================================

-- 1) Migration tracking table (used by the LavaLust Migration library)
CREATE TABLE IF NOT EXISTS `migrations` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `migration`  INT NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration_unique` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) users  (authentication)
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(100) NOT NULL,
  `email`      VARCHAR(255) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('admin','moderator','user') NOT NULL DEFAULT 'user',
  `is_active`  TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username_unique` (`username`),
  KEY `role_idx` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3) refresh_tokens  (JWT refresh-token storage)
CREATE TABLE IF NOT EXISTS `refresh_tokens` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `token`      TEXT NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `jti`        TEXT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4) products  (REQUIRED TABLE of the laboratory exercise)
CREATE TABLE IF NOT EXISTS `products` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `product_name` VARCHAR(100) NOT NULL,
  `description`  TEXT DEFAULT NULL,
  `price`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity`     INT NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5) Tell LavaLust these migrations are already applied
--    (so a later "php lava migration run" will not try to create them again)
INSERT IGNORE INTO `migrations` (`migration`) VALUES (0), (1), (2), (3);

-- 6) OPTIONAL demo login (you can also just use the Register form of the app)
--    email: admin@example.com    password: Admin@12345
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `role`)
VALUES ('admin', 'admin@example.com', '$2y$10$w4oAM68ZDWN/w/5JwSh0KejMgsrbI27CAPCKRMuTPfJdSbNhJZVAi', 'admin');

-- 7) OPTIONAL sample products
INSERT INTO `products` (`product_name`, `description`, `price`, `quantity`) VALUES
  ('Wireless Mouse',      '2.4GHz optical wireless mouse',        499.00, 25),
  ('Mechanical Keyboard', 'Blue-switch mechanical keyboard',     1899.50, 12),
  ('USB-C Hub',           '6-in-1 USB-C multiport adapter',      1250.00,  8);
