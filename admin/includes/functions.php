<?php

// Sanitize output for HTML
function h($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Format date
function format_date($date)
{
    if (!$date)
        return '-';
    return date('d M Y', strtotime($date));
}

// Format datetime
function format_datetime($date)
{
    if (!$date)
        return '-';
    return date('d M Y H:i', strtotime($date));
}

// Generate CSRF token
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verify_csrf_token($token)
{
    if (empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("CSRF Token Verification Failed");
    }
}
?>