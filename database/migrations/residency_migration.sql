-- ============================================================
-- residency_migration.sql
-- Villa Tobago — Residency Application Workflow Overhaul
-- Run once on the production database.
-- ============================================================

-- ── OWNERS: new workflow + residency detail columns ──────────
ALTER TABLE owners
    ADD COLUMN IF NOT EXISTS portal_access_granted TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS details_complete      TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS agent_approved        TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS code_of_conduct_accepted TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS num_occupants         INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS intercom_contact1_name  VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS intercom_contact1_phone VARCHAR(30)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS intercom_contact2_name  VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS intercom_contact2_phone VARCHAR(30)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS rental_agency_or_owner_name VARCHAR(150) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'Pending',
    ADD COLUMN IF NOT EXISTS agent_approval TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS pet_approval   TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS move_in_sent   TINYINT(1) NOT NULL DEFAULT 0;

-- ── TENANTS: new workflow + residency detail columns ─────────
ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS portal_access_granted TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS details_complete      TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS agent_approved        TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS code_of_conduct_accepted TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS num_occupants         INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS intercom_contact1_name  VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS intercom_contact1_phone VARCHAR(30)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS intercom_contact2_name  VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS intercom_contact2_phone VARCHAR(30)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS rental_agency_or_owner_name VARCHAR(150) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS move_in_date          DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS status                VARCHAR(50) DEFAULT 'Pending',
    ADD COLUMN IF NOT EXISTS owner_approval        TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS pet_approval          TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS move_in_sent          TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS amendment_token       VARCHAR(255) DEFAULT NULL;

-- ── VEHICLES: new table ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS vehicles (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    unit_id       INT NOT NULL,
    resident_type ENUM('owner','tenant') NOT NULL,
    resident_id   INT NOT NULL,
    registration  VARCHAR(50) NOT NULL,
    make_model    VARCHAR(100) DEFAULT NULL,
    color         VARCHAR(50)  DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
);

-- ── PETS: new detail + compliance columns ────────────────────
ALTER TABLE pets
    ADD COLUMN IF NOT EXISTS adult_size            ENUM('Small','Medium','Large') DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS photo_path            VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS is_sterilized         TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS sterilized_proof_path VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS is_vaccinated         TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS vaccination_proof_path VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS is_microchipped       TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS wears_id_tag          TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS motivation_note       TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS house_rules_accepted  TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS status                VARCHAR(50) DEFAULT 'Pending',
    ADD COLUMN IF NOT EXISTS trustee_comments      TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS birth_date            DATE DEFAULT NULL;

-- ── PET SETTINGS: vehicle limit ──────────────────────────────
INSERT IGNORE INTO pet_settings (setting_key, setting_value)
VALUES ('max_vehicles_per_unit', '2');

-- ── SYSTEM SETTINGS table (if not yet created) ───────────────
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
