-- JamSoft — MySQL schema
-- Import: mysql -u root -p jamsoft < database/schema.mysql.sql
-- (or simply open /install.php in the browser)

CREATE DATABASE IF NOT EXISTS `jamsoft` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `jamsoft`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(190) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Key/value settings, bilingual (fa / en) — one row per key per lang; lang='' = language independent
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(100) NOT NULL,
  `lang` VARCHAR(5) NOT NULL DEFAULT '',
  `value` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings` (`key`, `lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `icon` VARCHAR(50) NOT NULL DEFAULT 'code',
  `title_fa` VARCHAR(190) NOT NULL,
  `title_en` VARCHAR(190) NOT NULL,
  `desc_fa` TEXT NULL,
  `desc_en` TEXT NULL,
  `tags_fa` VARCHAR(255) NULL,
  `tags_en` VARCHAR(255) NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(190) NOT NULL UNIQUE,
  `title_fa` VARCHAR(190) NOT NULL,
  `title_en` VARCHAR(190) NOT NULL,
  `category_fa` VARCHAR(100) NULL,
  `category_en` VARCHAR(100) NULL,
  `summary_fa` VARCHAR(500) NULL,
  `summary_en` VARCHAR(500) NULL,
  `body_fa` LONGTEXT NULL,
  `body_en` LONGTEXT NULL,
  `stack` VARCHAR(255) NULL,
  `client` VARCHAR(190) NULL,
  `year` VARCHAR(10) NULL,
  `url` VARCHAR(255) NULL,
  `image` VARCHAR(255) NULL,
  `color` VARCHAR(20) NOT NULL DEFAULT '#e8ff47',
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `sort` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `skills` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `group_fa` VARCHAR(100) NULL,
  `group_en` VARCHAR(100) NULL,
  `level` TINYINT UNSIGNED NOT NULL DEFAULT 80,
  `sort` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_fa` VARCHAR(190) NOT NULL,
  `name_en` VARCHAR(190) NOT NULL,
  `role_fa` VARCHAR(190) NULL,
  `role_en` VARCHAR(190) NULL,
  `text_fa` TEXT NULL,
  `text_en` TEXT NULL,
  `avatar` VARCHAR(255) NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(190) NOT NULL UNIQUE,
  `title_fa` VARCHAR(190) NOT NULL,
  `title_en` VARCHAR(190) NOT NULL,
  `excerpt_fa` VARCHAR(500) NULL,
  `excerpt_en` VARCHAR(500) NULL,
  `body_fa` LONGTEXT NULL,
  `body_en` LONGTEXT NULL,
  `image` VARCHAR(255) NULL,
  `published` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(190) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(50) NULL,
  `subject` VARCHAR(190) NULL,
  `budget` VARCHAR(50) NULL,
  `message` TEXT NOT NULL,
  `ip` VARCHAR(45) NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
