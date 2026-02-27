-- ============================================================
-- migrate_resident_app.sql
-- Villa Tobago — Resident Application Workflow
-- Adds pending application tracking to the units table.
-- Safe to re-run (ALTER IGNORE pattern).
-- ============================================================

ALTER TABLE units
    ADD COLUMN IF NOT EXISTS pending_app_type ENUM('owner','tenant') NULL,
    ADD COLUMN IF NOT EXISTS pending_app_id   INT NULL;
