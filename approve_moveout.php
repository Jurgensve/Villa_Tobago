<?php
// approve_moveout.php  — One-click Owner approval page for tenant move-out requests
require_once 'admin/config/db.php';
require_once 'admin/includes/functions.php';

$message = '';
$error = '';
$move = null;
$token = trim($_GET['token'] ?? '');

if (!$token) {
    $error = "No token provided. This link is invalid.";
}
else {
    $stmt = $pdo->prepare(
        "SELECT ml.*, u.unit_number,
                CASE WHEN ml.resident_type = 'tenant' THEN t.full_name ELSE o.full_name END AS resident_name,
                CASE WHEN ml.resident_type = 'tenant' THEN t.email ELSE o.email END AS resident_email
         FROM move_logistics ml
         JOIN units u ON ml.unit_id = u.id
         LEFT JOIN tenants t ON ml.resident_type = 'tenant' AND ml.resident_id = t.id
         LEFT JOIN owners  o ON ml.resident_type = 'owner'  AND ml.resident_id = o.id
         WHERE ml.move_out_token = ? AND ml.move_type = 'move_out' AND ml.status = 'Pending'
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $move = $stmt->fetch();

    if (!$move) {
        $error = "This approval link is invalid, has already been used, or the request has been processed.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $move && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'approve') {
            $pdo->prepare("UPDATE move_logistics SET owner_approval = 1, status = 'Approved', move_out_token = NULL WHERE id = ?")
                ->execute([$move['id']]);

            // Notify the resident that their move-out has been approved
            if (!empty($move['resident_email'])) {
                $subject = "Move-Out Approved – Villa Tobago";
                $body = "<p>Dear " . h($move['resident_name']) . ",</p>";
                $body .= "<p>Your move-out request for Unit <strong>" . h($move['unit_number']) . "</strong> has been approved by the unit owner.</p>";
                $body .= "<p>The Managing Agent has been notified and will be in contact regarding next steps.</p>";
                $body .= "<p style='color:#999;font-size:0.85em;'>Villa Tobago Management</p>";
                send_notification_email($move['resident_email'], $subject, $body);
            }

            $message = "Move-out approved successfully for Unit " . h($move['unit_number']) . ". The resident has been notified.";
            $move = null;

        }
        elseif ($action === 'decline') {
            $pdo->prepare("UPDATE move_logistics SET owner_approval = 0, status = 'Cancelled', move_out_token = NULL WHERE id = ?")
                ->execute([$move['id']]);

            if (!empty($move['resident_email'])) {
                $subject = "Move-Out Request Update – Villa Tobago";
                $body = "<p>Dear " . h($move['resident_name']) . ",</p>";
                $body .= "<p>Your move-out request for Unit <strong>" . h($move['unit_number']) . "</strong> has been declined by the unit owner.</p>";
                $body .= "<p>Please contact the Managing Agent if you have questions.</p>";
                $body .= "<p style='color:#999;font-size:0.85em;'>Villa Tobago Management</p>";
                send_notification_email($move['resident_email'], $subject, $body);
            }

            $message = "Move-out request has been declined. The resident has been notified.";
            $move = null;
        }
    }
    catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Move-Out Approval – Villa Tobago</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gray-100 min-h-screen font-sans flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-white rounded-lg shadow-xl p-8">

        <div class="text-center mb-6">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-orange-100 mb-4">
                <i class="fas fa-sign-out-alt text-2xl text-orange-600"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Tenant Move-Out Approval</h1>
            <p class="text-gray-500 text-sm mt-1">Villa Tobago Residential Estate</p>
        </div>

        <?php if ($message): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-800 px-4 py-4 rounded text-center mb-4">
            <i class="fas fa-check-circle text-2xl text-green-500 mb-2 block"></i>
            <strong>
                <?= h($message)?>
            </strong>
        </div>
        <div class="text-center mt-4">
            <a href="index.html" class="text-blue-600 hover:underline text-sm">Return to Homepage</a>
        </div>

        <?php
elseif ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-800 px-4 py-4 rounded mb-4">
            <i class="fas fa-exclamation-triangle mr-2"></i><strong>
                <?= h($error)?>
            </strong>
        </div>
        <div class="text-center mt-4">
            <a href="index.html" class="text-blue-600 hover:underline text-sm">Return to Homepage</a>
        </div>

        <?php
elseif ($move): ?>
        <p class="text-gray-600 mb-6 text-center text-sm">Your tenant has submitted a move-out request. Please review
            the details and approve or decline.</p>

        <div class="bg-gray-50 border border-gray-200 rounded-lg p-5 mb-6">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Move-Out Request Details</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="block text-gray-500">Unit</span>
                    <span class="block font-bold text-gray-800">
                        <?= h($move['unit_number'])?>
                    </span>
                </div>
                <div>
                    <span class="block text-gray-500">Resident</span>
                    <span class="block font-bold text-gray-800">
                        <?= h($move['resident_name'])?>
                    </span>
                </div>
                <div>
                    <span class="block text-gray-500">Move-Out Date</span>
                    <span class="block font-bold text-gray-800">
                        <?= $move['preferred_date'] ? format_date($move['preferred_date']) : 'Not specified'?>
                    </span>
                </div>
                <div>
                    <span class="block text-gray-500">Truck Reg</span>
                    <span class="block font-bold text-gray-800">
                        <?= $move['truck_reg'] ? h($move['truck_reg']) : 'Not specified'?>
                    </span>
                </div>
                <div>
                    <span class="block text-gray-500">Moving Company</span>
                    <span class="block font-bold text-gray-800">
                        <?= $move['moving_company'] ? h($move['moving_company']) : 'Not specified'?>
                    </span>
                </div>
                <div>
                    <span class="block text-gray-500">Submitted</span>
                    <span class="block font-bold text-gray-800">
                        <?= format_date($move['created_at'])?>
                    </span>
                </div>
                <?php if ($move['notes']): ?>
                <div class="col-span-2">
                    <span class="block text-gray-500">Notes</span>
                    <span class="block text-gray-700 italic">
                        <?= h($move['notes'])?>
                    </span>
                </div>
                <?php
    endif; ?>
            </div>
        </div>

        <form method="POST" class="flex flex-col sm:flex-row gap-4">
            <button type="submit" name="action" value="decline"
                class="flex-1 bg-red-100 text-red-700 hover:bg-red-200 font-bold py-3 px-4 rounded-lg border border-red-300 transition"
                onclick="return confirm('Are you sure you want to decline this move-out request?');">
                <i class="fas fa-times-circle mr-2"></i> Decline
            </button>
            <button type="submit" name="action" value="approve"
                class="flex-1 bg-green-600 text-white hover:bg-green-700 font-bold py-3 px-4 rounded-lg shadow transition"
                onclick="return confirm('Approve this move-out request?');">
                <i class="fas fa-check-circle mr-2"></i> Approve Move-Out
            </button>
        </form>
        <?php
endif; ?>
    </div>
</body>

</html>