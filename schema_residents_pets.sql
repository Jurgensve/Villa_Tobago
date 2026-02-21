-- schema_residents_pets.sql
-- 1. Residents table (Unifies occupancy: one per unit)
CREATE TABLE IF NOT EXISTS residents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL UNIQUE,
    resident_type ENUM('owner', 'tenant') NOT NULL,
    resident_id INT NOT NULL, -- PK from owners or tenants table
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
);

-- 2. Pet Settings (Policies)
CREATE TABLE IF NOT EXISTS pet_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Initial default settings
INSERT IGNORE INTO pet_settings (setting_key, setting_value) VALUES 
('max_pets_per_unit', '2'),
('allowed_pet_types', 'Dog, Cat, Bird, Fish'),
('pet_management_enabled', '1');

-- 3. Pets table
CREATE TABLE IF NOT EXISTS pets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    resident_id INT NOT NULL, -- Link to the resident record at the time of creation
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    breed VARCHAR(100),
    reg_number VARCHAR(100), -- Identification number/tag
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
);
