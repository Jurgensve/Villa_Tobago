<?php
session_start();
require_once 'admin/includes/functions.php';
require_once 'admin/config/db.php';

$token = $_GET['token'] ?? '';
$type = $_GET['type'] ?? '';

if (empty($token) || empty($type)) {
    die("Invalid request link.");
}

$table = ($type === 'pet') ? 'pets' : 'modifications';
$stmt = $pdo->prepare("SELECT * FROM $table WHERE amendment_token = ? LIMIT 1");
$stmt->execute([$token]);
$record = $stmt->fetch();

if (!$record) {
    die("Invalid or expired link.");
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resubmit'])) {
        try {
            if ($type === 'modification') {
                $description = trim($_POST['description']);
                // Update description and status
                $upd = $pdo->prepare("UPDATE modifications SET description = ?, status = 'Pending Updated', amendment_token = NULL WHERE id = ?");
                $upd->execute([$description, $record['id']]);

                // Fetch trustee emails to notify
                $admins = $pdo->query("SELECT email FROM owners WHERE id IN (SELECT owner_id FROM ownership_history WHERE is_current=1) LIMIT 1")->fetch(); // Just using a dummy admin email for now
                send_notification_email('admin@villatobago.co.za', "Modification Resubmitted", "Unit modification {$record['id']} has been resubmitted and is Pending Updated.");

            }
            else if ($type === 'pet') {
                $notes = trim($_POST['notes']);
                $upd = $pdo->prepare("UPDATE pets SET notes = ?, status = 'Pending Updated', amendment_token = NULL WHERE id = ?");
                $upd->execute([$notes, $record['id']]);

                send_notification_email('admin@villatobago.co.za', "Pet Request Resubmitted", "Pet request {$record['id']} has been resubmitted and is Pending Updated.");
            }

            // Log to amendment_logs
            $log = $pdo->prepare("INSERT INTO amendment_logs (related_type, related_id, action_type, comments) VALUES (?, ?, 'resubmit', 'Record amended by user')");
            $log->execute([$type, $record['id']]);

            $message = "Your request has been successfully updated and resubmitted.";
            $record = null; // Hide form
        }
        catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Amend Request</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow">
        <h1 class="text-2xl font-bold mb-4">Amend Your Request</h1>

        <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= h($message)?>
        </div>
        <?php
elseif ($record): ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
            <h3 class="font-bold text-yellow-800">Trustee Comments</h3>
            <p class="text-yellow-700 mt-1">
                <?= nl2br(h($record['trustee_comments']))?>
            </p>
        </div>

        <form method="POST">
            <?php if ($type === 'modification'): ?>
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Description / Details</label>
                <textarea name="description" class="w-full border p-2 rounded" rows="5"
                    required><?= h($record['description'])?></textarea>
                <p class="text-sm text-gray-500 mt-1">Please update your request details based on the trustee comments.
                </p>
            </div>
            <?php
    elseif ($type === 'pet'): ?>
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Pet Notes / Details</label>
                <textarea name="notes" class="w-full border p-2 rounded" rows="5"
                    required><?= h($record['notes'])?></textarea>
                <p class="text-sm text-gray-500 mt-1">Please update your pet details or notes based on the trustee
                    comments.</p>
            </div>
            <?php
    endif; ?>

            <button type="submit" name="resubmit"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Resubmit Request</button>
        </form>
        <?php
endif; ?>
    </div>
</body>

</html>