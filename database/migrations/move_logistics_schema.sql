-- =============================================================
-- Villa Tobago – Move Logistics Schema Migration
-- Run this once via phpMyAdmin or your hosting SQL tool.
-- =============================================================

-- 1. Create the move_logistics table
CREATE TABLE IF NOT EXISTS move_logistics (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    unit_id         INT NOT NULL,
    resident_type   ENUM('owner','tenant') NOT NULL,
    resident_id     INT NOT NULL,          -- PK from owners or tenants table
    move_type       ENUM('move_in','move_out') NOT NULL,
    preferred_date  DATE,
    truck_reg       VARCHAR(50),
    truck_gwm       INT DEFAULT NULL,      -- Gross Vehicle Mass in kg
    moving_company  VARCHAR(100),
    notes           TEXT,
    status          ENUM('Pending','Approved','Completed','Cancelled') DEFAULT 'Pending',
    -- Approval token sent to owner (for tenant move-outs) or agent-approved directly
    move_out_token          VARCHAR(64) NULL,
    owner_approval          TINYINT(1) DEFAULT NULL, -- NULL = not required yet, 0 = declined, 1 = approved
    security_notified       TINYINT(1) DEFAULT 0,
    security_notified_at    TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
);

-- 2. Add move_in_token column to tenants so the Move-In Form link is token-gated
ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS move_in_token VARCHAR(64) NULL AFTER amendment_token;

-- 3. Add system_settings table (used for security email and future global settings)
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    description   VARCHAR(255),
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4. Default system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES
    ('security_email',   'security@villatobago.co.za', 'Email address for security gate move notifications'),
    ('complex_rules_pdf','',  'Path to uploaded Body Corporate rules PDF'),
    ('logo_path',        '',  'Path to uploaded complex logo image'),
    ('footer_text',      '',  'Custom footer text for resident-facing pages'),
    ('total_units',      '0', 'Total number of units in the complex (locked once set)'),
    ('max_truck_gwm',    '3500', 'Maximum allowed weight (kg) for moving trucks entering the complex');

-- 5. Add move_in_token to owners (mirrors tenants)
ALTER TABLE owners
    ADD COLUMN IF NOT EXISTS move_in_token VARCHAR(64) NULL AFTER move_in_sent;

-- 6. Add intercom removal columns to move_logistics
ALTER TABLE move_logistics
    ADD COLUMN IF NOT EXISTS intercom_flagged    TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS intercom_cleared_at DATETIME NULL;
