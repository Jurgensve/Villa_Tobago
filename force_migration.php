<?php
// force_migration.php
// This script applies the missing database columns to the units table directly.

require_once 'admin/config/db.php';

try {
    // Apply the missing columns to the units table
    $sql = "ALTER TABLE units
            ADD COLUMN IF NOT EXISTS pending_app_type ENUM('owner','tenant') NULL,
            ADD COLUMN IF NOT EXISTS pending_app_id INT NULL";

    $pdo->exec($sql);

    echo "<h1>Migration Successful!</h1>";
    echo "<p>The missing columns (<code>pending_app_type</code> and <code>pending_app_id</code>) have been successfully added to the <code>units</code> table.</p>";
}
catch (PDOException $e) {
    echo "<h1>Migration Failed</h1>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>