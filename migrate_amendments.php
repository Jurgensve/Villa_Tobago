<?php
require_once 'admin/includes/functions.php';
require_once 'admin/config/db.php';

try {
    $sql = file_get_contents('schema_amendments.sql');
    $pdo->exec($sql);
    echo "Migration Successful!\n";
}
catch (PDOException $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
}
?>