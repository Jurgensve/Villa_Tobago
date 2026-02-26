<?php
require_once 'admin/config/db.php';

try {
    $pdo->exec("ALTER TABLE move_logistics ADD COLUMN IF NOT EXISTS truck_gwm INT DEFAULT NULL AFTER truck_reg");
    $pdo->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES ('max_truck_gwm', '3500', 'Maximum allowed weight (kg) for moving trucks entering the complex')");
    echo "Success";
}
catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}