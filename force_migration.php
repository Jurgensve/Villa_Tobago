<?php
// force_migration.php
// This script applies missing database columns.

require_once 'admin/config/db.php';

try {
        // 1. Apply the missing columns to the units table
        $sqlUnits = "ALTER TABLE units
            ADD COLUMN IF NOT EXISTS pending_app_type ENUM('owner','tenant') NULL,
            ADD COLUMN IF NOT EXISTS pending_app_id INT NULL";
        $pdo->exec($sqlUnits);

        // 2. Apply the missing columns to the pets table
        $sqlPets = "ALTER TABLE pets
            ADD COLUMN IF NOT EXISTS resident_type ENUM('owner','tenant') NOT NULL AFTER unit_id,
            ADD COLUMN IF NOT EXISTS adult_size VARCHAR(50) NULL AFTER reg_number,
            ADD COLUMN IF NOT EXISTS birth_date DATE NULL AFTER adult_size,
            ADD COLUMN IF NOT EXISTS is_sterilized TINYINT(1) DEFAULT 0,
            ADD COLUMN IF NOT EXISTS is_vaccinated TINYINT(1) DEFAULT 0,
            ADD COLUMN IF NOT EXISTS is_microchipped TINYINT(1) DEFAULT 0,
            ADD COLUMN IF NOT EXISTS wears_id_tag TINYINT(1) DEFAULT 0,
            ADD COLUMN IF NOT EXISTS motivation_note TEXT NULL,
            ADD COLUMN IF NOT EXISTS house_rules_accepted TINYINT(1) DEFAULT 0,
            ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'Pending',
            ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) NULL,
            ADD COLUMN IF NOT EXISTS sterilized_proof_path VARCHAR(255) NULL,
            ADD COLUMN IF NOT EXISTS vaccination_proof_path VARCHAR(255) NULL";
        $pdo->exec($sqlPets);

        echo "<h1>Migration Successful!</h1>";
        echo "<p>The missing columns have been successfully added to the <code>units</code> and <code>pets</code> tables.</p>";
}
catch (PDOException $e) {
        echo "<h1>Migration Failed</h1>";
        echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>