<?php
require_once 'admin/config/db.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM modifications LIKE 'tenant_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE modifications ADD COLUMN tenant_id INT DEFAULT NULL AFTER owner_id;");
        echo "Added tenant_id. \n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM modifications LIKE 'amendment_token'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE modifications ADD COLUMN amendment_token VARCHAR(64) DEFAULT NULL AFTER status;");
        echo "Added amendment_token. \n";
    }

    echo "Migration Complete.";
}
catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}