-- =============================================================
-- Villa Tobago – User Management Schema Migration
-- Adds profile fields and role/status to the users table.
-- Safe to re-run (uses IF NOT EXISTS / ALTER IGNORE pattern).
-- =============================================================

-- 1. Add profile + role columns to users
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS full_name  VARCHAR(100) NULL          AFTER username,
    ADD COLUMN IF NOT EXISTS email      VARCHAR(150) NULL          AFTER full_name,
    ADD COLUMN IF NOT EXISTS phone      VARCHAR(30)  NULL          AFTER email,
    ADD COLUMN IF NOT EXISTS role       ENUM('super_admin','admin') NOT NULL DEFAULT 'admin' AFTER phone,
    ADD COLUMN IF NOT EXISTS is_active  TINYINT(1)  NOT NULL DEFAULT 1 AFTER role,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER is_active;

-- 2. Promote the earliest user to super_admin (the original setup user)
UPDATE users SET role = 'super_admin' WHERE id = (SELECT min_id FROM (SELECT MIN(id) AS min_id FROM users) AS t);
