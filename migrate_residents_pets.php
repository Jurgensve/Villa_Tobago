<?php
require_once 'admin/includes/functions.php';
// Database connection is normally in header.php, let's include enough to get PDO
require_once 'admin/includes/db.php';

try {
    $sql = file_get_contents('schema_residents_pets.sql');
    $pdo->exec($sql);
    echo "<h1>Migration Successful!</h1>";
    echo "<p>Residents, Pets, and Pet Settings tables have been created.</p>";
}
catch (PDOException $e) {
    echo "<h1>Migration Failed</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
<a href="admin/index.php">Back to Dashboard</a>