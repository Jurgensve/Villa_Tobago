<?php
// Simple script to test database connection
// Upload to your server and visit: yoursite.com/test_db.php

// Include the db connection (adjust path as needed if you move this file)
require_once 'admin/config/db.php';

if ($pdo) {
    echo "<h1>Database Connection Successful!</h1>";
    echo "<p>Connected to database: " . DB_NAME . "</p>";
}
else {
    echo "<h1>Connection Failed</h1>";
}
?>