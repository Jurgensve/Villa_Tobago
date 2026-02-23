<?php
session_start();
unset($_SESSION['auth_resident']);
header("Location: resident_portal.php");
exit;
?>