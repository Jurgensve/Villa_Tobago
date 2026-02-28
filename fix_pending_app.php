<?php
require_once 'admin/config/db.php';

try {
    $pdo->beginTransaction();

    // 1. Restore the pending application pointer on Unit 4 to point back to the Owner application (ID 5)
    $pdo->exec("UPDATE units SET pending_app_type = 'owner', pending_app_id = 5 WHERE id = 4");

    // 2. Delete the accidental empty Tenant application (ID 10) to avoid any more confusion
    $pdo->exec("DELETE FROM tenants WHERE id = 10 AND unit_id = 4");

    $pdo->commit();
    echo "<h1>Data Restored Successfully</h1>";
    echo "<p>Your original Owner application with vehicles and pets is now successfully linked to Unit 4 again.</p>";
}
catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h1>Data Restore Failed</h1>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>