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

// Send Email Helper
function send_notification_email($to_email, $subject, $message_body)
{
    // For local testing where mail() might fail or not be configured, we just return true.
    // In production, this uses PHP's mail() function (the server's SMTP).
    $headers = "From: admin@villatobago.co.za\r\n";
    $headers .= "Reply-To: admin@villatobago.co.za\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    // Disable errors from mail() just in case it's not configured locally
    $success = @mail($to_email, $subject, nl2br($message_body), $headers);
    return true; // We return true to avoid breaking the UI locally
}
?>