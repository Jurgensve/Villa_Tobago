<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

$username = 'admin';
$new_password = 'admin123';
$hash = password_hash($new_password, PASSWORD_DEFAULT);

echo "<h1>Resetting Admin Password...</h1>";

try {
    // First, check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        echo "<p>User '$username' found. Updating password...</p>";
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $user['id']]);
        echo "<p>Update successful!</p>";
    }
    else {
        echo "<p>User '$username' NOT found. Creating new user...</p>";
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$username, $hash]);
        echo "<p>User created successfully!</p>";
    }

    echo "<h3>New Credentials:</h3>";
    echo "<ul><li>Username: <strong>admin</strong></li><li>Password: <strong>admin123</strong></li></ul>";
    echo "<p>Please <strong>DELETE this file</strong> (/admin/reset_password.php) immediately for security after logging in.</p>";
    echo '<a href="login.php" style="padding:10px 20px; background:blue; color:white; text-decoration:none; border-radius:5px;">Go to Login Page</a>';

}
catch (PDOException $e) {
    echo "<h2 style='color:red'>Database Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>This means your configuration in <strong>admin/config/config.php</strong> is likely still incorrect.</p>";
}
?>