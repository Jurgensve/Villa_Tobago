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
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS modification_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            policy_document_path VARCHAR(255) DEFAULT NULL
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