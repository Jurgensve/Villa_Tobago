-- ============================================================
-- migrate_resident_data.sql
-- Villa Tobago — Fix Data Inheritance for Pets & Vehicles
-- Run once on production to ensure pets and vehicles link 
-- directly to the owner/tenant, not the unit's active resident slot.
-- ============================================================

-- 1. Add resident_type to pets table (matching vehicles table)
ALTER TABLE pets
    ADD COLUMN IF NOT EXISTS resident_type ENUM('owner','tenant') NULL AFTER unit_id;

-- 2. Update existing pet records to point to the actual owner/tenant ID, 
--    rather than the `residents.id` primary key.
--    NOTE: we only do this where 'resident_type' is currently NULL to make it idempotent.
UPDATE pets p
JOIN residents r ON p.resident_id = r.id
SET p.resident_type = r.resident_type,
    p.resident_id = r.resident_id
WHERE p.resident_type IS NULL;

-- 3. In case any vehicles were mistakenly saved with resident_id = residents.id
--    (The PHP code recently did not do this for vehicles, but just in case)
UPDATE vehicles v
JOIN residents r ON v.resident_id = r.id
SET v.resident_id = r.resident_id
WHERE v.resident_id = r.id AND (
    (v.resident_type = 'owner' AND r.resident_type = 'owner') OR
    (v.resident_type = 'tenant' AND r.resident_type = 'tenant')
);
