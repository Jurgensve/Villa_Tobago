<?php
$host = 'localhost';
$db = 'villatobagoco_coredb';
$user = 'villatobagoco_gemini';
$pass = 'mamqox-norsyc-mufhI5';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "SUCCESS: Credentials work perfectly from localhost.";
}
catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
?>