<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Admin Diagnostic Check</h1>";

echo "<h2>1. Environment</h2>";
echo "Current Directory: " . __DIR__ . "<br>";
echo "PHP Version: " . phpversion() . "<br>";

echo "<h2>2. Config File Check</h2>";
$config_path = __DIR__ . '/config/config.php';
if (file_exists($config_path)) {
    echo "<span style='color:green'>config.php FOUND.</span><br>";
    include $config_path;
    echo "DB_HOST defined: " . (defined('DB_HOST') ? 'Yes' : 'No') . "<br>";
    echo "DB_USER defined: " . (defined('DB_USER') ? 'Yes' : 'No') . "<br>";
}
else {
    echo "<span style='color:red'>config.php NOT FOUND at $config_path</span><br>";
    echo "Did you rename config.sample.php?<br>";
}

echo "<h2>3. Database Connection Check</h2>";
if (defined('DB_HOST')) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<span style='color:green'>Database Connection SUCCESSFUL.</span><br>";
    }
    catch (PDOException $e) {
        echo "<span style='color:red'>Database Connection FAILED: " . $e->getMessage() . "</span><br>";
    }
}
else {
    echo "Skipping connection check because config is missing.<br>";
}

echo "<h2>4. Session Check</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    $_SESSION['test'] = 'working';
    echo "Session started successfully.<br>";
}
else {
    echo "Session already active.<br>";
}

echo "<hr>End of Diagnostics.";
?>