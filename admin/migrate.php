<?php
require_once 'config/db.php';

echo "<h1>Database Migration</h1>";

try {
    // 1. Create modification_attachments table
    $sql = "CREATE TABLE IF NOT EXISTS modification_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        modification_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (modification_id) REFERENCES modifications(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "<p style='color:green'>Table 'modification_attachments' ready.</p>";

    // 2. Add category column to modifications
    // Check if column exists first to avoid error
    $check = $pdo->query("SHOW COLUMNS FROM modifications LIKE 'category'");
    if (!$check->fetch()) {
        $pdo->exec("ALTER TABLE modifications ADD COLUMN category VARCHAR(50) AFTER owner_id");
        echo "<p style='color:green'>Column 'category' added to 'modifications'.</p>";
    }
    else {
        echo "<p>Column 'category' already exists.</p>";
    }

    echo "<h3>Migration Complete!</h3>";
    echo "<p>Please <strong>DELETE this file</strong> (/admin/migrate.php) now.</p>";
    echo '<a href="index.php">Go to Dashboard</a>';

}
catch (PDOException $e) {
    echo "<h2 style='color:red'>Migration Failed</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>