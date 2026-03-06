<?php
$required_roles = ['admin', 'managing_agent'];
require_once 'includes/functions.php';
require_once 'config/db.php';
session_start();

if (!isset($_SESSION['auth_admin']) || !in_array($_SESSION['auth_admin']['role'], $required_roles)) {
    die("Unauthorized Access");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['pet_id'])) {
    $pet_id = (int) $_POST['pet_id'];
    $action = $_POST['action'];
    $reason = trim($_POST['reason'] ?? '');

    // Get the pet and resident info to redirect back to the correct unit
    $stmt = $pdo->prepare("SELECT unit_id, name FROM pets WHERE id = ?");
    $stmt->execute([$pet_id]);
    $pet = $stmt->fetch();

    if (!$pet) {
        die("Pet not found");
    }

    $unit_id = $pet['unit_id'];

    if ($action === 'approve') {
        $pdo->prepare("UPDATE pets SET status = 'Approved' WHERE id = ?")->execute([$pet_id]);
        $msg = urlencode("Pet " . $pet['name'] . " approved successfully.");
    } elseif ($action === 'decline' && !empty($reason)) {
        $pdo->prepare("UPDATE pets SET status = 'Declined', trustee_comments = ? WHERE id = ?")->execute([$reason, $pet_id]);
        $msg = urlencode("Pet " . $pet['name'] . " declined.");
    } elseif ($action === 'request_info' && !empty($reason)) {
        $pdo->prepare("UPDATE pets SET status = 'Info Required', trustee_comments = ? WHERE id = ?")->execute([$reason, $pet_id]);
        $msg = urlencode("Requested more info for " . $pet['name'] . ".");
    } else {
        $msg = urlencode("Invalid action or missing reason.");
    }

    header("Location: units.php?action=view&id=" . $unit_id . "&msg=" . $msg);
    exit;
} else {
    header("Location: units.php");
    exit;
}
?>