-- The Brighten Stars Academy – Safe Database Update
-- MySQL 5.7 / MariaDB compatible
-- Run each statement one by one in phpMyAdmin Query tab
-- If it says "Duplicate column" just skip that line and run the next

ALTER TABLE students ADD COLUMN timing VARCHAR(20) NULL DEFAULT NULL;
ALTER TABLE students ADD COLUMN photo VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE students ADD COLUMN completion_date DATE NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active';

-- Update existing users to active
UPDATE users SET status = 'active';
