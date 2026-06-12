-- The Brighten Stars Academy – Database Updates
-- Run this in phpMyAdmin after the initial import

-- Add class timing to students
ALTER TABLE students
  ADD COLUMN IF NOT EXISTS timing VARCHAR(20) NULL AFTER batch,
  ADD COLUMN IF NOT EXISTS photo VARCHAR(255) NULL AFTER timing,
  ADD COLUMN IF NOT EXISTS completion_date DATE NULL AFTER enrollment_date;

-- Add status column to users if missing
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER role;

-- Update existing users to active
UPDATE users SET status='active' WHERE status IS NULL OR status='';
