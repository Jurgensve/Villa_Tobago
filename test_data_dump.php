<?php
require_once 'admin/config/db.php';

try {
    $res = $pdo->query("SELECT * FROM owners ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $res_id = $res['id'];
    $res_type = 'owner';
    echo "Last Owner: " . json_encode($res) . "\n";

    $res2 = $pdo->query("SELECT * FROM tenants ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    echo "Last Tenant: " . json_encode($res2) . "\n";

    $vehicles = $pdo->query("SELECT * FROM vehicles ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "Recent Vehicles: " . json_encode($vehicles) . "\n";

    $pets = $pdo->query("SELECT * FROM pets ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "Recent Pets: " . json_encode($pets) . "\n";
}
catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>