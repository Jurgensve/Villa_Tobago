<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

// Check if user is already logged in (for login page)
function require_logout()
{
    if (isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }
}

// Gate: only super_admin may proceed; others get a flash + redirect
function require_super_admin()
{
    require_login();
    if (($_SESSION['role'] ?? '') !== 'super_admin') {
        $_SESSION['flash_error'] = 'Access denied. Super-admin privileges required.';
        header("Location: index.php");
        exit;
    }
}

// Fetch the full user row for the currently logged-in user
function current_user($pdo)
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    catch (PDOException $e) {
        return null;
    }
}

// Verify login and populate session
function verify_login($username, $password, $pdo)
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
        $_SESSION['role'] = $user['role'] ?? 'admin';
        return true;
    }
    return false;
}
?>