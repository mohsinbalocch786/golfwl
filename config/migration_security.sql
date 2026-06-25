-- ============================================================
--  Security & Compliance Migration
--  Run ONCE on your production database
-- ============================================================

-- 1. Do-Not-Contact / Suppression list
--    Populated automatically when: unsubscribe webhooks fire,
--    STOP SMS received, or admin manually adds entries
CREATE TABLE IF NOT EXISTS `do_not_contact` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `email`      VARCHAR(150) DEFAULT NULL,
    `phone`      VARCHAR(30)  DEFAULT NULL,
    `reason`     ENUM('unsubscribed','bounce','stop_sms','spam','manual') DEFAULT 'manual',
    `source`     VARCHAR(100) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_email` (`email`),
    KEY `idx_dnc_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Audit log - who did what and when
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT DEFAULT NULL,
    `user_type`  ENUM('admin','user') DEFAULT 'user',
    `action`     VARCHAR(100) DEFAULT NULL,
    `module`     VARCHAR(50)  DEFAULT NULL,
    `record_id`  INT DEFAULT NULL,
    `detail`     TEXT,
    `ip_address` VARCHAR(45)  DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    KEY `idx_audit_user`    (`user_id`),
    KEY `idx_audit_module`  (`module`),
    KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Cron heartbeat - so you can monitor whether cron is running
CREATE TABLE IF NOT EXISTS `cron_heartbeat` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `job_name`   VARCHAR(100) NOT NULL,
    `last_run`   DATETIME DEFAULT NULL,
    `status`     VARCHAR(20) DEFAULT 'ok',
    `message`    TEXT,
    UNIQUE KEY `uk_job` (`job_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed job records
INSERT IGNORE INTO `cron_heartbeat` (job_name, last_run, status) VALUES
('send_campaigns', NULL, 'never_run'),
('workflow_engine', NULL, 'never_run');

-- 4. Add unique email constraint to contacts (prevent duplicates per user)
-- Note: per-user uniqueness (email + user_id combination)
ALTER TABLE `contacts` ADD UNIQUE KEY `uk_contact_email_user` (`email`, `user_id`);

