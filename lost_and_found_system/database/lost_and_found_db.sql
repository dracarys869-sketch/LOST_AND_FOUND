-- ============================================================
--  School Lost & Found Management System
--  Database: lost_and_found_db
--  Engine:   MySQL 5.7+ / MariaDB 10.3+
--  Encoding: utf8mb4
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `lost_and_found_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `lost_and_found_db`;

-- ─────────────────────────────────────────────────────────────
--  TABLE: users
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(100)    NOT NULL,
    `email`           VARCHAR(150)    NOT NULL,
    `password`        VARCHAR(255)    NOT NULL,
    `role`            ENUM('admin','student','security') NOT NULL DEFAULT 'student',
    `student_id`      VARCHAR(50)     NULL DEFAULT NULL,
    `phone`           VARCHAR(20)     NULL DEFAULT NULL,
    `profile_photo`   VARCHAR(255)    NULL DEFAULT NULL,
    `status`          ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    `remember_token`  VARCHAR(100)    NULL DEFAULT NULL,
    `last_login`      DATETIME        NULL DEFAULT NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`),
    KEY `idx_role`   (`role`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  TABLE: categories
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `description` TEXT         NULL DEFAULT NULL,
    `icon`        VARCHAR(50)  NOT NULL DEFAULT 'bi-tag',
    `color`       VARCHAR(20)  NOT NULL DEFAULT '#065F46',
    `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_name` (`name`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  TABLE: locations
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `locations` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `building`    VARCHAR(100) NULL DEFAULT NULL,
    `floor`       VARCHAR(50)  NULL DEFAULT NULL,
    `description` TEXT         NULL DEFAULT NULL,
    `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  TABLE: lost_items
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lost_items` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_number` VARCHAR(20)  NOT NULL,
    `user_id`       INT UNSIGNED NOT NULL,
    `category_id`   INT UNSIGNED NOT NULL,
    `location_id`   INT UNSIGNED NOT NULL,
    `item_name`     VARCHAR(150) NOT NULL,
    `brand`         VARCHAR(100) NULL DEFAULT NULL,
    `color`         VARCHAR(50)  NULL DEFAULT NULL,
    `description`   TEXT         NULL DEFAULT NULL,
    `date_lost`     DATE         NOT NULL,
    `time_lost`     TIME         NULL DEFAULT NULL,
    `status`        ENUM('pending','active','matched','claimed','closed') NOT NULL DEFAULT 'pending',
    `admin_notes`   TEXT         NULL DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_report_number` (`report_number`),
    KEY `idx_user_id`   (`user_id`),
    KEY `idx_status`    (`status`),
    KEY `idx_date_lost` (`date_lost`),
    FULLTEXT KEY `ft_lost_search` (`item_name`,`brand`,`color`,`description`),
    CONSTRAINT `fk_lost_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)      ON DELETE CASCADE,
    CONSTRAINT `fk_lost_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_lost_location` FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  TABLE: found_items
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `found_items` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_number`    VARCHAR(20)  NOT NULL,
    `user_id`          INT UNSIGNED NOT NULL,
    `category_id`      INT UNSIGNED NOT NULL,
    `location_id`      INT UNSIGNED NOT NULL,
    `item_name`        VARCHAR(150) NOT NULL,
    `brand`            VARCHAR(100) NULL DEFAULT NULL,
    `color`            VARCHAR(50)  NULL DEFAULT NULL,
    `description`      TEXT         NULL DEFAULT NULL,
    `date_found`       DATE         NOT NULL,
    `time_found`       TIME         NULL DEFAULT NULL,
    `storage_location` VARCHAR(200) NULL DEFAULT NULL,
    `qr_code_path`     VARCHAR(255) NULL DEFAULT NULL,
    `status`           ENUM('pending','active','matched','claimed','released') NOT NULL DEFAULT 'pending',
    `security_notes`   TEXT         NULL DEFAULT NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_report_number` (`report_number`),
    KEY `idx_user_id`    (`user_id`),
    KEY `idx_status`     (`status`),
    KEY `idx_date_found` (`date_found`),
    FULLTEXT KEY `ft_found_search` (`item_name`,`brand`,`color`,`description`),
    CONSTRAINT `fk_found_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)      ON DELETE CASCADE,
    CONSTRAINT `fk_found_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_found_location` FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  TABLE: item_photos
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `item_photos` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_type`  ENUM('lost','found') NOT NULL,
    `item_id`    INT UNSIGNED NOT NULL,
    `photo_path` VARCHAR(255) NOT NULL,
    `is_primary` TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_item` (`item_type`,`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  TABLE: matches
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `matches` (
    `id`               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `lost_item_id`     INT UNSIGNED   NOT NULL,
    `found_item_id`    INT UNSIGNED   NOT NULL,
    `similarity_score` DECIMAL(5,2)   NOT NULL DEFAULT '0.00',
    `match_reasons`    JSON           NULL DEFAULT NULL,
    `status`           ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
    `matched_by`       ENUM('system','admin') NOT NULL DEFAULT 'system',
    `reviewed_by`      INT UNSIGNED   NULL DEFAULT NULL,
    `created_at`       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lost_item`  (`lost_item_id`),
    KEY `idx_found_item` (`found_item_id`),
    KEY `idx_status`     (`status`),
    CONSTRAINT `fk_match_lost`     FOREIGN KEY (`lost_item_id`)  REFERENCES `lost_items`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_match_found`    FOREIGN KEY (`found_item_id`) REFERENCES `found_items`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_match_reviewer` FOREIGN KEY (`reviewed_by`)   REFERENCES `users`(`id`)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  TABLE: claims
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `claims` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lost_item_id`     INT UNSIGNED NULL DEFAULT NULL,
    `found_item_id`    INT UNSIGNED NOT NULL,
    `match_id`         INT UNSIGNED NULL DEFAULT NULL,
    `claimant_id`      INT UNSIGNED NOT NULL,
    `proof_description` TEXT        NULL DEFAULT NULL,
    `proof_photo`      VARCHAR(255) NULL DEFAULT NULL,
    `status`           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `reviewed_by`      INT UNSIGNED NULL DEFAULT NULL,
    `review_notes`     TEXT         NULL DEFAULT NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_claimant`   (`claimant_id`),
    KEY `idx_status`     (`status`),
    KEY `idx_found_item` (`found_item_id`),
    CONSTRAINT `fk_claim_lost`     FOREIGN KEY (`lost_item_id`)  REFERENCES `lost_items`(`id`)  ON DELETE SET NULL,
    CONSTRAINT `fk_claim_found`    FOREIGN KEY (`found_item_id`) REFERENCES `found_items`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_claim_match`    FOREIGN KEY (`match_id`)      REFERENCES `matches`(`id`)     ON DELETE SET NULL,
    CONSTRAINT `fk_claim_claimant` FOREIGN KEY (`claimant_id`)   REFERENCES `users`(`id`)       ON DELETE CASCADE,
    CONSTRAINT `fk_claim_reviewer` FOREIGN KEY (`reviewed_by`)   REFERENCES `users`(`id`)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  TABLE: releases
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `releases` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `claim_id`      INT UNSIGNED NOT NULL,
    `found_item_id` INT UNSIGNED NOT NULL,
    `released_by`   INT UNSIGNED NOT NULL,
    `released_to`   INT UNSIGNED NOT NULL,
    `release_date`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes`         TEXT         NULL DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_release_date` (`release_date`),
    KEY `idx_found_item`   (`found_item_id`),
    CONSTRAINT `fk_release_claim`       FOREIGN KEY (`claim_id`)      REFERENCES `claims`(`id`)      ON DELETE RESTRICT,
    CONSTRAINT `fk_release_found`       FOREIGN KEY (`found_item_id`) REFERENCES `found_items`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_release_released_by` FOREIGN KEY (`released_by`)   REFERENCES `users`(`id`)       ON DELETE RESTRICT,
    CONSTRAINT `fk_release_released_to` FOREIGN KEY (`released_to`)   REFERENCES `users`(`id`)       ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  TABLE: notifications
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `title`        VARCHAR(200) NOT NULL,
    `message`      TEXT         NOT NULL,
    `type`         ENUM('info','success','warning','match','claim') NOT NULL DEFAULT 'info',
    `is_read`      TINYINT(1)   NOT NULL DEFAULT 0,
    `related_id`   INT UNSIGNED NULL DEFAULT NULL,
    `related_type` VARCHAR(50)  NULL DEFAULT NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_is_read` (`is_read`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  TABLE: audit_logs
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NULL DEFAULT NULL,
    `action`     VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(100) NULL DEFAULT NULL,
    `record_id`  INT UNSIGNED NULL DEFAULT NULL,
    `old_values` JSON         NULL DEFAULT NULL,
    `new_values` JSON         NULL DEFAULT NULL,
    `ip_address` VARCHAR(45)  NULL DEFAULT NULL,
    `user_agent` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id`    (`user_id`),
    KEY `idx_action`     (`action`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  TABLE: settings
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100) NOT NULL,
    `setting_value` TEXT         NULL DEFAULT NULL,
    `setting_type`  ENUM('text','number','boolean','json') NOT NULL DEFAULT 'text',
    `description`   VARCHAR(255) NULL DEFAULT NULL,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ═════════════════════════════════════════════════════════════
--  SEED DATA
-- ═════════════════════════════════════════════════════════════

-- Settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('school_name',          'Greenfield Academy',                 'text',    'School / Institution name'),
('school_address',       '123 Campus Drive, Greenfield City',  'text',    'School address'),
('school_email',         'info@greenfield.edu',                'text',    'School contact email'),
('school_phone',         '+63 912 345 6789',                   'text',    'School contact phone'),
('match_threshold',      '75',                                 'number',  'Minimum similarity % to flag as a possible match'),
('session_timeout',      '30',                                 'number',  'Session timeout in minutes'),
('max_upload_size',      '5',                                  'number',  'Maximum photo upload size in MB'),
('items_per_page',       '10',                                 'number',  'Pagination: items per page'),
('allow_registration',   '1',                                  'boolean', 'Allow public student self-registration'),
('maintenance_mode',     '0',                                  'boolean', 'Put site in maintenance mode');

-- Categories
INSERT INTO `categories` (`name`, `description`, `icon`, `color`) VALUES
('Electronics',      'Mobile phones, laptops, tablets, earphones, chargers, etc.',   'bi-phone',         '#065F46'),
('Clothing',         'Uniforms, jackets, PE attire, caps, and accessories.',          'bi-person-badge',  '#059669'),
('Bags & Backpacks', 'School bags, backpacks, pouches, and sling bags.',              'bi-bag',           '#10B981'),
('Books & Stationery','Textbooks, notebooks, pens, calculators, and art materials.',  'bi-book',          '#34D399'),
('IDs & Cards',      'School IDs, library cards, ATM cards, access cards.',          'bi-card-text',     '#6EE7B7'),
('Accessories',      'Glasses, watches, jewelry, hair accessories, keychains.',       'bi-gem',           '#A7F3D0'),
('Sports Equipment', 'Rackets, balls, sports gear, gym equipment.',                  'bi-activity',      '#D1FAE5'),
('Keys',             'House keys, padlocks, car keys, locker keys.',                  'bi-key',           '#ECFDF5'),
('Wallet & Purse',   'Wallets, coin purses, money clips, cardholders.',               'bi-wallet2',       '#047857'),
('Others',           'Items that do not fit in other categories.',                    'bi-three-dots',    '#044030');

-- Locations
INSERT INTO `locations` (`name`, `building`, `floor`, `description`) VALUES
('Main Library',          'Library Building',  'Ground Floor', 'Main reading area and circulation desk'),
('Cafeteria',             'Main Building',     'Ground Floor', 'School cafeteria and dining area'),
('Science Laboratory',    'Science Building',  '2nd Floor',    'Physics, Chemistry, Biology labs'),
('Computer Laboratory',   'Tech Building',     '3rd Floor',    'PC labs 1-4'),
('Gymnasium',             'Gym Building',      'Ground Floor', 'Main gym and indoor courts'),
('Admin Office',          'Main Building',     '1st Floor',    'Principal and admin offices'),
('Guidance Office',       'Main Building',     '1st Floor',    'Guidance and counseling office'),
('Classroom Block A',     'Main Building',     '2nd Floor',    'Sections A-F'),
('Classroom Block B',     'Main Building',     '3rd Floor',    'Sections G-L'),
('Classroom Block C',     'Annex Building',    '2nd Floor',    'Sections M-R'),
('PE Area',               'Outdoor Area',      'Ground Floor', 'Track oval, basketball courts, grounds'),
('Parking Lot',           'Outdoor Area',      'Ground Floor', 'Student and faculty parking area'),
('School Gate / Entrance','Main Building',     'Ground Floor', 'Main school entrance and guardhouse'),
('Restrooms Block A',     'Main Building',     '2nd Floor',    'Male and female restrooms near block A'),
('Covered Court',         'Outdoor Area',      'Ground Floor', 'Covered basketball and assembly court');

-- Users  (passwords are bcrypt hashes of: admin@skul123, Student@123, Student@123, Security@123)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `student_id`, `phone`, `status`) VALUES
('System Administrator', 'admin@school.edu.ph',    '$2y$12$z1KE8/h6totDYc4x2G0vdOY7ssohkRZBwsfGnP6gKDqZ1GGcWhVxy', 'admin',    NULL,        '+63 912 000 0001', 'active'),
('Juan dela Cruz',       'student@school.edu',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student',  '2024-0001', '+63 912 000 0002', 'active'),
('Maria Santos',         'maria@school.edu',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student',  '2024-0002', '+63 912 000 0003', 'active'),
('Pedro Reyes',          'pedro@school.edu',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student',  '2024-0003', '+63 912 000 0004', 'active'),
('Officer Ben Cruz',     'security@school.edu', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'security', NULL,        '+63 912 000 0005', 'active'),
('Officer Ana Reyes',    'ana@school.edu',      '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'security', NULL,        '+63 912 000 0006', 'active');

-- NOTE: All sample users use password: password
-- The hash above is for "password" (Laravel default test hash — replace with proper hashes after setup)
-- To generate correct hashes, run: php -r "echo password_hash('Admin@123', PASSWORD_BCRYPT, ['cost'=>12]);"

-- Lost Items
INSERT INTO `lost_items` (`report_number`, `user_id`, `category_id`, `location_id`, `item_name`, `brand`, `color`, `description`, `date_lost`, `time_lost`, `status`) VALUES
('LI-2024-0001', 2, 1, 4, 'Wireless Earphones',     'JBL',    'Black',  'JBL Tune 230NC TWS earphones in a black charging case with red accents. Left earbud has a small scratch.',  '2024-11-01', '10:30:00', 'active'),
('LI-2024-0002', 2, 2, 8, 'PE Jacket',              'Nike',   'Blue',   'Navy blue Nike PE jacket, size Medium, with white stripes. Name written inside: J. DELA CRUZ - III-A.',       '2024-11-03', '14:00:00', 'active'),
('LI-2024-0003', 3, 5, 2, 'School ID',              NULL,     NULL,     'Greenfield Academy student ID for Maria Santos, Student No. 2024-0002, Section III-B.',                     '2024-11-05', '12:15:00', 'matched'),
('LI-2024-0004', 4, 9, 5, 'Brown Leather Wallet',   'Fossil', 'Brown',  'Brown Fossil leather bifold wallet. Contains school ID, cash around PHP 500, and a blue ATM card.',          '2024-11-07', '16:30:00', 'active'),
('LI-2024-0005', 3, 1, 3, 'Scientific Calculator',  'Casio',  'Black',  'Casio FX-991ES PLUS scientific calculator. Black with silver accents. Has sticker on the back: MS2024.',   '2024-11-08', '09:00:00', 'active');

-- Found Items
INSERT INTO `found_items` (`report_number`, `user_id`, `category_id`, `location_id`, `item_name`, `brand`, `color`, `description`, `date_found`, `time_found`, `storage_location`, `status`) VALUES
('FI-2024-0001', 5, 1, 4, 'Black Earphones (Case)', 'JBL',    'Black',  'Found black TWS earphone case near computer lab chairs. Has small red accents. One earbud inside.', '2024-11-02', '09:00:00', 'Security Office — Cabinet A', 'matched'),
('FI-2024-0002', 5, 5, 2, 'Student ID Card',        NULL,     NULL,     'Found a student ID on cafeteria table. Name: Maria Santos. Has lanyard attached.',                '2024-11-05', '13:00:00', 'Security Office — Drawer 1',  'matched'),
('FI-2024-0003', 6, 2, 8, 'Blue Sports Jacket',     'Nike',   'Blue',   'Blue Nike jacket left on chair in classroom block A hallway. Size M.',                           '2024-11-04', '15:30:00', 'Security Office — Cabinet A', 'active'),
('FI-2024-0004', 5, 3, 5, 'Red Drawstring Bag',     NULL,     'Red',    'Red drawstring bag found near gymnasium entrance. Contains some PE clothes and a water bottle.',  '2024-11-06', '17:00:00', 'Security Office — Shelf 1',   'active'),
('FI-2024-0005', 6, 1, 3, 'Scientific Calculator',  'Casio',  'Black',  'Casio scientific calculator found in science lab. Has sticker: MS2024.',                        '2024-11-08', '11:00:00', 'Security Office — Drawer 1',  'active');

-- Matches
INSERT INTO `matches` (`lost_item_id`, `found_item_id`, `similarity_score`, `match_reasons`, `status`, `matched_by`) VALUES
(1, 1, 92.50, '{"category":25,"item_name":18,"color":15,"brand":15,"description":12,"location":5,"date":2.5}', 'confirmed', 'system'),
(3, 2, 95.00, '{"category":25,"item_name":20,"color":0,"brand":0,"description":15,"location":5,"date":5}',    'confirmed', 'system');

-- Claims
INSERT INTO `claims` (`lost_item_id`, `found_item_id`, `match_id`, `claimant_id`, `proof_description`, `status`, `reviewed_by`) VALUES
(3, 2, 2, 3, 'This is my school ID. I lost it during lunch on November 5. My student number is 2024-0002 and my section is III-B which are visible on the ID.', 'approved', 5);

-- Releases
INSERT INTO `releases` (`claim_id`, `found_item_id`, `released_by`, `released_to`, `release_date`, `notes`) VALUES
(1, 2, 5, 3, '2024-11-07 10:30:00', 'Item released to claimant after identity verification. Student presented original school ID from another pocket.');

-- Notifications
INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `is_read`, `related_id`, `related_type`) VALUES
(2, 'Report Submitted Successfully',    'Your lost item report LI-2024-0001 for "Wireless Earphones" has been submitted and is now active.',                          'success', 1, NULL, NULL),
(2, 'Possible Match Found!',            'A possible match (92.5% similarity) has been found for your lost Wireless Earphones. Please review the match details.',      'match',   0, 1, 'match'),
(3, 'Your Item Has Been Matched',       'A match was found for your lost School ID (LI-2024-0003). Score: 95%. Please review and submit a claim if this is yours.',   'match',   1, 2, 'match'),
(3, 'Claim Approved!',                  'Your claim for the School ID has been approved. Please proceed to the Security Office to claim your item.',                  'success', 1, 1, 'claim'),
(5, 'New Found Item Report',            'A new found item "Black Earphones (Case)" has been logged. Matching engine found a possible match (92.5%).',                 'info',    1, 1, 'match'),
(1, 'New Match Detected',               'System matched Lost Item LI-2024-0001 with Found Item FI-2024-0001. Similarity score: 92.5%.',                              'match',   0, 1, 'match');

-- Audit Logs
INSERT INTO `audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `ip_address`) VALUES
(1, 'LOGIN',               'users',       1, '127.0.0.1'),
(2, 'LOGIN',               'users',       2, '127.0.0.1'),
(2, 'CREATE_LOST_ITEM',    'lost_items',  1, '127.0.0.1'),
(5, 'CREATE_FOUND_ITEM',   'found_items', 1, '127.0.0.1'),
(1, 'SYSTEM_MATCH_CREATE', 'matches',     1, '127.0.0.1'),
(5, 'APPROVE_CLAIM',       'claims',      1, '127.0.0.1'),
(5, 'RELEASE_ITEM',        'releases',    1, '127.0.0.1');
