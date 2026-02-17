-- Database Schema for Villa Tobago Management System

-- 1. Users (System Administrators)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Units (The physical properties)
CREATE TABLE IF NOT EXISTS units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_number VARCHAR(20) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Owners (Profile information)
CREATE TABLE IF NOT EXISTS owners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    id_number VARCHAR(20),
    email VARCHAR(100),
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4. Ownership History (Links Owners to Units with dates)
CREATE TABLE IF NOT EXISTS ownership_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    owner_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    is_current BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (unit_id) REFERENCES units(id),
    FOREIGN KEY (owner_id) REFERENCES owners(id)
);

-- 5. Tenants (Current and Previous)
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    id_number VARCHAR(20),
    email VARCHAR(100),
    phone VARCHAR(20),
    previous_address TEXT,
    lease_agreement_path VARCHAR(255),
    start_date DATE,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id)
);

-- 6. Occupants (Family members/co-residents for Owners OR Tenants)
CREATE TABLE IF NOT EXISTS occupants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    associated_type ENUM('owner', 'tenant') NOT NULL,
    associated_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    id_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    -- Note: We can't strictly enforce FK here easily without polymorphism or separate tables, 
    -- but application logic will handle validity.
);

-- 7. Modifications (Renovation requests)
CREATE TABLE IF NOT EXISTS modifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    owner_id INT NOT NULL,
    description TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approval_date TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (unit_id) REFERENCES units(id),
    FOREIGN KEY (owner_id) REFERENCES owners(id)
);

-- Insert a default admin user (Password: admin123 - CHANGE THIS IMMEDIATELY)
-- Hash generated using password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO users (username, password_hash) VALUES ('admin', '$2y$10$YourHashedPasswordHerePleaseReplaceThisOnProduction') ON DUPLICATE KEY UPDATE id=id;
