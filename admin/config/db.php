<?php
// Use absolute path to ensure we find the config file regardless of where this script is called from
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Temporary patch: Ensure 'status' column is VARCHAR, not ENUM, so 'Conditional Approval' saves correctly.
    try {
        $pdo->exec("ALTER TABLE pets MODIFY COLUMN status VARCHAR(50) DEFAULT 'Pending'");
        
        // Add policy_accepted column to modifications if it doesn't exist
        try {
            $pdo->exec("ALTER TABLE modifications ADD COLUMN policy_accepted TINYINT(1) DEFAULT 0");
        } catch (PDOException $ex) {
            // Ignore if column already exists
        }
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS modification_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            policy_document_path VARCHAR(255) DEFAULT NULL
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS digital_signatures (
            id INT AUTO_INCREMENT PRIMARY KEY,
            resident_type VARCHAR(50),
            resident_id INT,
            unit_id INT,
            document_type VARCHAR(255),
            record_id INT NULL,
            accepted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent TEXT
        )");
        
        // Seed default categories if none exist
        $count = $pdo->query("SELECT COUNT(*) FROM modification_categories")->fetchColumn();
        if ($count == 0) {
            $pdo->exec("INSERT INTO modification_categories (name) VALUES 
                ('Gas Installation'), 
                ('Patio Cover Modification'), 
                ('External Windows or Doors'), 
                ('Aircon Installation'), 
                ('Solar Power System'), 
                ('Other')");
        }
    } catch (PDOException $ex) {
        // Ignore if error
    }
} catch (PDOException $e) {
    // In production, log this error instead of showing it
    die("Database Connection Failed: " . $e->getMessage());
}
?>