-- The Brighten Stars Academy – Database Schema
-- Run this once on a fresh MySQL/MariaDB database

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(150) NOT NULL,
    `email`      VARCHAR(191) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL,
    `role`       ENUM('admin','student','parent') NOT NULL DEFAULT 'student',
    `phone`      VARCHAR(20),
    `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `last_login` DATETIME,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `courses` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(150) NOT NULL,
    `code`        VARCHAR(20)  NOT NULL UNIQUE,
    `duration`    VARCHAR(100),
    `instructor`  VARCHAR(150),
    `description` TEXT,
    `fee`         DECIMAL(10,2) DEFAULT 0,
    `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `students` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`         INT UNSIGNED,
    `roll_number`     VARCHAR(50) NOT NULL UNIQUE,
    `name`            VARCHAR(150) NOT NULL,
    `father_name`     VARCHAR(150),
    `phone`           VARCHAR(20),
    `email`           VARCHAR(191),
    `cnic`            VARCHAR(20),
    `dob`             DATE,
    `gender`          ENUM('male','female','other') NOT NULL DEFAULT 'male',
    `address`         TEXT,
    `course_id`       INT UNSIGNED,
    `batch`           VARCHAR(100),
    `timing`          VARCHAR(50),
    `enrollment_date` DATE,
    `photo`           VARCHAR(255),
    `status`          ENUM('active','inactive','completed') NOT NULL DEFAULT 'active',
    `completion_date` DATE,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE SET NULL,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `parents` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED,
    `student_id` INT UNSIGNED NOT NULL,
    `name`       VARCHAR(150) NOT NULL,
    `relation`   VARCHAR(50),
    `phone`      VARCHAR(20),
    `whatsapp`   VARCHAR(20),
    `email`      VARCHAR(191),
    `cnic`       VARCHAR(20),
    `address`    TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE SET NULL,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tests` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `course_id`        INT UNSIGNED NOT NULL,
    `name`             VARCHAR(150) NOT NULL,
    `subject`          VARCHAR(100),
    `test_type`        ENUM('weekly','monthly','final') NOT NULL DEFAULT 'weekly',
    `total_marks`      DECIMAL(6,2) NOT NULL DEFAULT 100,
    `date`             DATE,
    `duration_minutes` INT,
    `description`      TEXT,
    `created_by`       INT UNSIGNED,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_id`)  REFERENCES `courses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `test_results` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `test_id`         INT UNSIGNED NOT NULL,
    `student_id`      INT UNSIGNED NOT NULL,
    `obtained_marks`  DECIMAL(6,2) NOT NULL DEFAULT 0,
    `percentage`      DECIMAL(5,2),
    `grade`           VARCHAR(5),
    `position`        INT,
    `teacher_remarks` TEXT,
    `entered_by`      INT UNSIGNED,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_test_student` (`test_id`,`student_id`),
    FOREIGN KEY (`test_id`)    REFERENCES `tests`(`id`)    ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`entered_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attendance` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `course_id`  INT UNSIGNED NOT NULL,
    `date`       DATE NOT NULL,
    `status`     ENUM('present','absent','leave') NOT NULL DEFAULT 'present',
    `marked_by`  INT UNSIGNED,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_attendance` (`student_id`,`course_id`,`date`),
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`)  REFERENCES `courses`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`marked_by`)  REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `monthly_reports` (
    `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id`           INT UNSIGNED NOT NULL,
    `course_id`            INT UNSIGNED NOT NULL,
    `month`                TINYINT NOT NULL,
    `year`                 YEAR NOT NULL,
    `total_classes`        INT DEFAULT 0,
    `present_days`         INT DEFAULT 0,
    `absent_days`          INT DEFAULT 0,
    `leave_days`           INT DEFAULT 0,
    `attendance_percentage` DECIMAL(5,2) DEFAULT 0,
    `tests_taken`          INT DEFAULT 0,
    `average_marks`        DECIMAL(5,2) DEFAULT 0,
    `overall_grade`        VARCHAR(5),
    `teacher_remarks`      TEXT,
    `generated_by`         INT UNSIGNED,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`)   REFERENCES `courses`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lectures` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `course_id`      INT UNSIGNED NOT NULL,
    `title`          VARCHAR(200) NOT NULL,
    `description`    TEXT,
    `file_name`      VARCHAR(255),
    `file_path`      VARCHAR(500),
    `file_type`      VARCHAR(20),
    `file_size`      BIGINT,
    `allow_download` TINYINT(1) NOT NULL DEFAULT 0,
    `uploaded_by`    INT UNSIGNED,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_id`)   REFERENCES `courses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `course_files` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `course_id`      INT UNSIGNED NOT NULL,
    `title`          VARCHAR(200) NOT NULL,
    `description`    TEXT,
    `file_name`      VARCHAR(255),
    `file_path`      VARCHAR(500),
    `file_type`      VARCHAR(20),
    `file_size`      BIGINT,
    `allow_download` TINYINT(1) NOT NULL DEFAULT 0,
    `uploaded_by`    INT UNSIGNED,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_id`)   REFERENCES `courses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `announcements` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title`           VARCHAR(200) NOT NULL,
    `content`         TEXT,
    `type`            ENUM('holiday','test','course','event','notice','general') NOT NULL DEFAULT 'general',
    `target_audience` ENUM('all','students','parents') NOT NULL DEFAULT 'all',
    `course_id`       INT UNSIGNED,
    `is_pinned`       TINYINT(1) NOT NULL DEFAULT 0,
    `expires_at`      DATE,
    `published_by`    INT UNSIGNED,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_id`)    REFERENCES `courses`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`published_by`) REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `whatsapp_logs` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sent_by`      INT UNSIGNED,
    `phone`        VARCHAR(20),
    `message`      TEXT,
    `message_type` VARCHAR(50),
    `status`       ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    `sent_at`      DATETIME,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`sent_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED,
    `action`      VARCHAR(100) NOT NULL,
    `description` TEXT,
    `ip_address`  VARCHAR(45),
    `user_agent`  TEXT,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_action_ip_time` (`action`, `ip_address`, `created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key`   VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `allowed_ips` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL UNIQUE,
    `label`      VARCHAR(100),
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Default admin account  (change password immediately after setup)
INSERT IGNORE INTO `users` (`name`,`email`,`password`,`role`,`status`)
VALUES ('Admin', 'admin@brightenstars.edu.pk', '$2y$12$PLACEHOLDER_CHANGE_ME', 'admin', 'active');

-- Default settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('institute_name',       'The Brighten Stars Academy'),
    ('institute_phone',      ''),
    ('institute_email',      ''),
    ('institute_address',    ''),
    ('whatsapp_api_key',     ''),
    ('whatsapp_instance_id', '');
