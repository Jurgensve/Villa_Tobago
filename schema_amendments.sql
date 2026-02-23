-- schema_amendments.sql

-- 1. Add roles to users
ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('admin', 'managing_agent', 'trustee', 'security') DEFAULT 'managing_agent' AFTER username;

-- 2. Add statuses and comments to modifications
ALTER TABLE modifications 
    MODIFY COLUMN status ENUM('Pending', 'Approved', 'Declined', 'Information Requested', 'Pending Updated', 'Completed') DEFAULT 'Pending',
    ADD COLUMN IF NOT EXISTS trustee_comments TEXT AFTER status,
    ADD COLUMN IF NOT EXISTS amendment_token VARCHAR(64) NULL AFTER trustee_comments;

-- 3. Add statuses and comments to pets
ALTER TABLE pets 
    ADD COLUMN IF NOT EXISTS status ENUM('Pending', 'Approved', 'Declined', 'Information Requested', 'Pending Updated') DEFAULT 'Pending' AFTER notes,
    ADD COLUMN IF NOT EXISTS trustee_comments TEXT AFTER status,
    ADD COLUMN IF NOT EXISTS amendment_token VARCHAR(64) NULL AFTER trustee_comments;

-- 4. Add approval columns to tenants
ALTER TABLE tenants 
    ADD COLUMN IF NOT EXISTS status ENUM('Pending', 'Approved', 'Declined', 'Information Requested', 'Pending Updated') DEFAULT 'Pending' AFTER lease_agreement_path,
    ADD COLUMN IF NOT EXISTS owner_approval BOOLEAN DEFAULT FALSE AFTER status,
    ADD COLUMN IF NOT EXISTS pet_approval BOOLEAN DEFAULT FALSE AFTER owner_approval,
    ADD COLUMN IF NOT EXISTS move_in_sent BOOLEAN DEFAULT FALSE AFTER pet_approval,
    ADD COLUMN IF NOT EXISTS amendment_token VARCHAR(64) NULL AFTER move_in_sent;

-- 5. Add approval columns to owners (for residency)
ALTER TABLE owners 
    ADD COLUMN IF NOT EXISTS status ENUM('Pending', 'Approved', 'Declined', 'Information Requested', 'Pending Updated') DEFAULT 'Pending' AFTER is_active,
    ADD COLUMN IF NOT EXISTS agent_approval BOOLEAN DEFAULT FALSE AFTER status,
    ADD COLUMN IF NOT EXISTS pet_approval BOOLEAN DEFAULT FALSE AFTER agent_approval,
    ADD COLUMN IF NOT EXISTS move_in_sent BOOLEAN DEFAULT FALSE AFTER pet_approval,
    ADD COLUMN IF NOT EXISTS amendment_token VARCHAR(64) NULL AFTER move_in_sent;

-- 6. Amendment History Logger Table
CREATE TABLE IF NOT EXISTS amendment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    related_type ENUM('pet', 'modification', 'tenant', 'owner') NOT NULL,
    related_id INT NOT NULL,
    user_id INT NULL, -- System/Trustee/Agent making the log
    action_type ENUM('status_change', 'comment', 'resubmit') NOT NULL,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
