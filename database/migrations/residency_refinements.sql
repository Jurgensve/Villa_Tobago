-- database/migrations/residency_refinements.sql

-- 1. Expanded Pet Management
ALTER TABLE `pets`
ADD COLUMN `status` ENUM('Pending', 'Approved', 'Declined', 'Removed') DEFAULT 'Pending' AFTER `house_rules_accepted`,
ADD COLUMN `removal_reason` TEXT NULL AFTER `status`,
ADD COLUMN `removed_at` DATETIME NULL AFTER `removal_reason`,
ADD COLUMN `replacement_for_pet_id` INT NULL AFTER `removed_at`;

-- Update existing column definition if it was hardcoded (MySQL allows modifying ENUMs)
ALTER TABLE `pets` MODIFY COLUMN `status` ENUM('Pending', 'Approved', 'Declined', 'Removed') DEFAULT 'Pending';

-- 2. Pending Intercom Updates
ALTER TABLE `owners`
ADD COLUMN `pending_ic1_name` VARCHAR(255) NULL AFTER `intercom_contact2_phone`,
ADD COLUMN `pending_ic1_phone` VARCHAR(50) NULL AFTER `pending_ic1_name`,
ADD COLUMN `pending_ic2_name` VARCHAR(255) NULL AFTER `pending_ic1_phone`,
ADD COLUMN `pending_ic2_phone` VARCHAR(50) NULL AFTER `pending_ic2_name`,
ADD COLUMN `intercom_update_status` VARCHAR(20) NULL AFTER `pending_ic2_phone`;

ALTER TABLE `tenants`
ADD COLUMN `pending_ic1_name` VARCHAR(255) NULL AFTER `intercom_contact2_phone`,
ADD COLUMN `pending_ic1_phone` VARCHAR(50) NULL AFTER `pending_ic1_name`,
ADD COLUMN `pending_ic2_name` VARCHAR(255) NULL AFTER `pending_ic1_phone`,
ADD COLUMN `pending_ic2_phone` VARCHAR(50) NULL AFTER `pending_ic2_name`,
ADD COLUMN `intercom_update_status` VARCHAR(20) NULL AFTER `pending_ic2_phone`;
