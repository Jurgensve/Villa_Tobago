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

/**
 * Gate access to a list of allowed roles.
 * Usage: require_role(['admin','managing_agent'])
 */
function require_role(array $allowed_roles)
{
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $allowed_roles)) {
        $_SESSION['flash_error'] = 'Access denied. You do not have permission to view that page.';
        header("Location: index.php");
        exit;
    }
}

/**
 * Gate: only 'admin' role may manage users.
 */
function require_admin()
{
    require_role(['admin']);
}

/**
 * Return a human-readable label for a role slug.
 */
function role_label(string $role): string
{
    return match ($role) {
            'admin' => 'Admin',
            'managing_agent' => 'Managing Agent',
            'trustee' => 'Trustee',
            default => ucfirst($role),
        };
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
    // Try with is_active filter first; fall back if column not yet added by migration
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
    }
    catch (PDOException $e) {
        // is_active column doesn't exist yet — query without it
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
    }
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
        $_SESSION['role'] = $user['role'] ?? 'managing_agent';
        return true;
    }
    return false;
}
?>