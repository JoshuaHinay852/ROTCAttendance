-- Run this in your database first!
CREATE TABLE IF NOT EXISTS `cadet_excuses` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `cadet_id` INT NOT NULL,
    `excuse_type` VARCHAR(50) NOT NULL,
    `reason` TEXT NOT NULL,
    `start_date` DATE,
    `end_date` DATE,
    `attachment_path` VARCHAR(255),
    `status` ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    `remarks` TEXT,
    `reviewed_by` INT,
    `reviewed_at` DATETIME,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_archived` BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (`cadet_id`) REFERENCES `cadet_accounts`(`id`) ON DELETE CASCADE,
    INDEX `idx_cadet_excuses_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mp_excuses` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `mp_id` INT NOT NULL,
    `excuse_type` VARCHAR(50) NOT NULL,
    `reason` TEXT NOT NULL,
    `start_date` DATE,
    `end_date` DATE,
    `attachment_path` VARCHAR(255),
    `status` ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    `remarks` TEXT,
    `reviewed_by` INT,
    `reviewed_at` DATETIME,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_archived` BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (`mp_id`) REFERENCES `mp_accounts`(`id`) ON DELETE CASCADE,
    INDEX `idx_mp_excuses_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
