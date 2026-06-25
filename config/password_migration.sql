-- ============================================================
-- Password Migration Script
-- Run this ONCE on your production database.
-- It migrates admins from md5 to bcrypt, and users from
-- AES-256 encryption to bcrypt. After running, test login
-- for each account before removing the old password columns.
--
-- STEP 1: Add a temp column to admins for bcrypt
ALTER TABLE `admins` ADD COLUMN `password_hash` VARCHAR(255) DEFAULT NULL;

-- STEP 2: You CANNOT convert md5 hashes back to bcrypt server-side.
-- You must reset each admin password manually.
-- Use the PHP script below to generate new hashes, then UPDATE:
--
--   UPDATE admins SET password_hash = '$2y$12$...' WHERE id = 1;
--
-- STEP 3: After ALL admins are migrated and login tested:
--   ALTER TABLE `admins` DROP COLUMN `password`;
--   ALTER TABLE `admins` CHANGE `password_hash` `password` VARCHAR(255) NOT NULL;
--
-- For users table: same approach.
-- The new code handles BOTH old (AES) and new (bcrypt) passwords
-- during transition - once all users have logged in once, the
-- old AES column can be dropped.

-- ============================================================
-- Helper: generate a bcrypt hash in PHP
-- Run from command line:
--   php -r "echo password_hash('YourNewPassword', PASSWORD_BCRYPT) . PHP_EOL;"
-- ============================================================
