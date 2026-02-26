<?php

// Sanitize output for HTML
function h($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
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

// Send generic notification email
function send_notification_email($to_email, $subject, $message_body)
{
    $headers = "From: noreply@villatobago.co.za\r\n";
    $headers .= "Reply-To: admin@villatobago.co.za\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    @mail($to_email, $subject, $message_body, $headers);
    return true; // Always return true to avoid breaking the UI when mail is not configured locally
}

// Get a system setting value from the database
function get_system_setting($pdo, $key, $default = '')
{
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    }
    catch (PDOException $e) {
        return $default;
    }
}

// Send security gate notification email for a move
function send_security_notification($pdo, $move)
{
    $security_email = get_system_setting($pdo, 'security_email', '');
    if (empty($security_email))
        return false;

    $move_type_label = ($move['move_type'] === 'move_in') ? 'MOVE-IN' : 'MOVE-OUT';
    $date_label = $move['preferred_date'] ? format_date($move['preferred_date']) : 'TBC';
    $truck_label = $move['truck_reg'] ? h($move['truck_reg']) : 'Not specified';
    $company_label = $move['moving_company'] ? h($move['moving_company']) : 'Not specified';

    $gwm_row = '';
    if (!empty($move['truck_gwm'])) {
        $gwm_row = "<tr><td style='padding:8px;border:1px solid #ddd;font-weight:bold;'>Truck GWM</td><td style='padding:8px;border:1px solid #ddd;color:orange;font-weight:bold;'>" . number_format($move['truck_gwm']) . " kg</td></tr>";
    }

    $subject = "Security Notice: {$move_type_label} – Unit {$move['unit_number']}";

    $body = "<p>Dear Security Team,</p>";
    $body .= "<p>Please be advised of the following scheduled move:</p>";
    $body .= "<table style='border-collapse:collapse;width:100%;max-width:480px;'>";
    $body .= "<tr><td style='padding:8px;border:1px solid #ddd;font-weight:bold;'>Type</td><td style='padding:8px;border:1px solid #ddd;'>{$move_type_label}</td></tr>";
    $body .= "<tr><td style='padding:8px;border:1px solid #ddd;font-weight:bold;'>Unit</td><td style='padding:8px;border:1px solid #ddd;'>{$move['unit_number']}</td></tr>";
    $body .= "<tr><td style='padding:8px;border:1px solid #ddd;font-weight:bold;'>Resident</td><td style='padding:8px;border:1px solid #ddd;'>" . h($move['resident_name']) . "</td></tr>";
    $body .= "<tr><td style='padding:8px;border:1px solid #ddd;font-weight:bold;'>Date</td><td style='padding:8px;border:1px solid #ddd;'>{$date_label}</td></tr>";
    $body .= "<tr><td style='padding:8px;border:1px solid #ddd;font-weight:bold;'>Truck Reg</td><td style='padding:8px;border:1px solid #ddd;'>{$truck_label}</td></tr>";
    $body .= $gwm_row;
    $body .= "<tr><td style='padding:8px;border:1px solid #ddd;font-weight:bold;'>Moving Company</td><td style='padding:8px;border:1px solid #ddd;'>{$company_label}</td></tr>";
    $body .= "</table>";
    $body .= "<p style='color:#666;font-size:0.85em;'>This is an automated notification from Villa Tobago Management.</p>";

    return send_notification_email($security_email, $subject, $body);
}
?>