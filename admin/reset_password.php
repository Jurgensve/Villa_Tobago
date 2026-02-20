<?php
require_once 'config/db.php';

$username = 'admin';
$new_password = 'admin123';
$hash = password_hash($new_password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
    $stmt->execute([$hash, $username]);

    if ($stmt->rowCount() > 0) {
        echo "<h1>Success!</h1><p>Password for user 'admin' has been reset to 'admin123'.</p>";
        echo "<p>Please <strong>DELETE this file</strong> (/admin/reset_password.php) immediately for security.</p>";
        echo '<a href="login.php">Go to Login</a>';
    }
    else {
        // Maybe the user doesn't exist? Try to insert
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = ?");
        $stmt->execute([$username, $hash, $hash]);
        echo "<h1>Success!</h1><p>User 'admin' created/updated with password 'admin123'.</p>";
        echo "<p>Please <strong>DELETE this file</strong> (/admin/reset_password.php) immediately for security.</p>";
        echo '<a href="login.php">Go to Login</a>';
    }
}
catch (PDOException $e) {
    echo "<h1>Error</h1><p>" . $e->getMessage() . "</p>";
}
?>