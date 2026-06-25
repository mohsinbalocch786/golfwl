-- ============================================================
--  GOLF WL — Role-Based Ownership Migration
--  Run this AFTER importing victor17_golfwl_1_.sql
-- ============================================================

-- 1. USERS — add role system
ALTER TABLE `users`
  ADD COLUMN `role` ENUM('manager','user') NOT NULL DEFAULT 'user' AFTER `status`,
  ADD COLUMN `manager_id` INT NULL DEFAULT NULL AFTER `role`,
  ADD COLUMN `created_by` INT NULL DEFAULT NULL AFTER `manager_id`,
  ADD COLUMN `can_view_team` TINYINT(1) NOT NULL DEFAULT 0 AFTER `created_by`;

-- Make the existing seeded user (id=2) a manager so they can see everything
-- they create going forward, and can be the "manager_id" for their team.
UPDATE `users` SET `role`='manager', `can_view_team`=1 WHERE `id`=2;

-- 2. CONTACTS — ownership
ALTER TABLE `contacts`
  ADD COLUMN `user_id` INT NULL DEFAULT NULL AFTER `id`,
  ADD COLUMN `manager_id` INT NULL DEFAULT NULL AFTER `user_id`;

-- 3. CONTACT GROUPS — ownership
ALTER TABLE `contact_groups`
  ADD COLUMN `user_id` INT NULL DEFAULT NULL AFTER `id`,
  ADD COLUMN `manager_id` INT NULL DEFAULT NULL AFTER `user_id`;

-- 4. TEMPLATES — ownership + visibility
ALTER TABLE `templates`
  ADD COLUMN `user_id` INT NULL DEFAULT NULL AFTER `id`,
  ADD COLUMN `manager_id` INT NULL DEFAULT NULL AFTER `user_id`,
  ADD COLUMN `visibility` ENUM('private','team','global') NOT NULL DEFAULT 'global' AFTER `manager_id`;

-- 5. CAMPAIGNS — ownership
ALTER TABLE `campaigns`
  ADD COLUMN `user_id` INT NULL DEFAULT NULL AFTER `id`,
  ADD COLUMN `manager_id` INT NULL DEFAULT NULL AFTER `user_id`;

-- 6. CAMPAIGN QUEUE — ownership (carried over from campaign for fast scoping)
ALTER TABLE `campaign_queue`
  ADD COLUMN `user_id` INT NULL DEFAULT NULL AFTER `id`,
  ADD COLUMN `manager_id` INT NULL DEFAULT NULL AFTER `user_id`;

-- 7. TWILIO NUMBERS — ownership (optional but kept consistent)
ALTER TABLE `twilio_numbers`
  ADD COLUMN `user_id` INT NULL DEFAULT NULL AFTER `id`,
  ADD COLUMN `manager_id` INT NULL DEFAULT NULL AFTER `user_id`;

-- ============================================================
--  Backfill existing rows to the seeded manager (id=2)
--  so old data remains visible after the conversion.
--  Change 2 below if your "owner" user has a different id.
-- ============================================================
UPDATE `contacts`       SET `user_id`=2, `manager_id`=2 WHERE `user_id` IS NULL;
UPDATE `contact_groups` SET `user_id`=2, `manager_id`=2 WHERE `user_id` IS NULL;
UPDATE `templates`       SET `user_id`=2, `manager_id`=2, `visibility`='global' WHERE `user_id` IS NULL;
UPDATE `campaigns`       SET `user_id`=2, `manager_id`=2 WHERE `user_id` IS NULL;
UPDATE `campaign_queue`  SET `user_id`=2, `manager_id`=2 WHERE `user_id` IS NULL;
UPDATE `twilio_numbers`  SET `user_id`=2, `manager_id`=2 WHERE `user_id` IS NULL;

-- ============================================================
--  Indexes for faster ownership-scoped queries
-- ============================================================
ALTER TABLE `contacts`       ADD INDEX `idx_contacts_owner` (`user_id`, `manager_id`);
ALTER TABLE `contact_groups` ADD INDEX `idx_groups_owner` (`user_id`, `manager_id`);
ALTER TABLE `templates`      ADD INDEX `idx_templates_owner` (`user_id`, `manager_id`);
ALTER TABLE `campaigns`      ADD INDEX `idx_campaigns_owner` (`user_id`, `manager_id`);
ALTER TABLE `campaign_queue` ADD INDEX `idx_queue_owner` (`user_id`, `manager_id`);
ALTER TABLE `users`          ADD INDEX `idx_users_manager` (`manager_id`);
