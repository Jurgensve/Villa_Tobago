<?php
require_once 'config/config.php';

function check_path($path)
{
    echo "Path: $path<br>";
    echo "Exists: " . (file_exists($path) ? 'YES' : 'NO') . "<br>";
    echo "Readable: " . (is_readable($path) ? 'YES' : 'NO') . "<br>";
    echo "Writable: " . (is_writable($path) ? 'YES' : 'NO') . "<br>";
    if (is_dir($path)) {
        echo "Type: Directory<br>";
        echo "Contents: <pre>";
        print_r(scandir($path));
        echo "</pre>";
    }
    echo "------------------<br>";
}

echo "<h1>Diagnostic Tools</h1>";
echo "SITE_URL: " . SITE_URL . "<br>";
echo "UPLOAD_DIR: " . UPLOAD_DIR . "<br>";
echo "PHP version: " . phpversion() . "<br>";
echo "------------------<br>";

echo "<h3>Checking UPLOAD_DIR</h3>";
check_path(UPLOAD_DIR);

echo "<h3>Checking Leases Subdir</h3>";
check_path(UPLOAD_DIR . 'leases/');

echo "<h3>Checking Modifications Subdir</h3>";
check_path(UPLOAD_DIR . 'modifications/');

echo "<h3>Checking for .htaccess in root</h3>";
check_path(__DIR__ . '/../.htaccess');
?>