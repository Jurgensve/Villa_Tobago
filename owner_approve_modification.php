<?php
// owner_approve_modification.php
session_start();
require_once 'admin/config/db.php';
require_once 'admin/includes/functions.php';

$token = $_GET['token'] ?? '';
$message = '';
$error = '';

if (empty($token)) {
    die("Invalid request. No token provided.");
}

// Find the modification by token
$stmt = $pdo->prepare("
    SELECT m.*, u.unit_number, t.full_name as tenant_name, o.full_name as owner_name, o.email as owner_email
    FROM modifications m
    JOIN units u ON m.unit_id = u.id
    LEFT JOIN tenants t ON m.tenant_id = t.id
    LEFT JOIN owners o ON m.owner_id = o.id
    WHERE m.amendment_token = ? AND m.status = 'Pending Owner Approval'
    LIMIT 1
");
$stmt->execute([$token]);
$mod = $stmt->fetch();

if (!$mod) {
    die("Invalid or expired token. This request may have already been processed.");
}

// Fetch attachments
$att_stmt = $pdo->prepare("SELECT * FROM modification_attachments WHERE modification_id = ?");
$att_stmt->execute([$mod['id']]);
$attachments = $att_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $pdo->prepare("UPDATE modifications SET status = 'Pending', amendment_token = NULL WHERE id = ?")->execute([$mod['id']]);
        $message = "You have approved this modification request. It has now been forwarded to the Managing Agent/Trustees for final review.";

        // Optional: Notify tenant
        if (!empty($mod['tenant_id'])) {
            $tstmt = $pdo->prepare("SELECT email FROM tenants WHERE id = ?");
            $tstmt->execute([$mod['tenant_id']]);
            $tenant_email = $tstmt->fetchColumn();

            if ($tenant_email) {
                send_notification_email($tenant_email, "Modification Request Approved by Owner",
                    "Your unit owner has approved your modification request ({$mod['category']}). It is now pending final review by the Managing Agent/Trustees."
                );
            }
        }
        $mod = false; // Hide form
    }
    elseif ($action === 'decline') {
        $pdo->prepare("UPDATE modifications SET status = 'Declined', amendment_token = NULL WHERE id = ?")->execute([$mod['id']]);
        $error = "You have declined this modification request.";

        // Notify tenant
        if (!empty($mod['tenant_id'])) {
            $tstmt = $pdo->prepare("SELECT email FROM tenants WHERE id = ?");
            $tstmt->execute([$mod['tenant_id']]);
            $tenant_email = $tstmt->fetchColumn();

            if ($tenant_email) {
                send_notification_email($tenant_email, "Modification Request Declined by Owner",
                    "Your unit owner has declined your modification request ({$mod['category']}). Please contact them directly for more details."
                );
            }
        }
        $mod = false; // Hide form
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Modification Approval</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gray-100 min-h-screen p-8 flex items-center justify-center">

    <div class="max-w-xl w-full bg-white rounded-2xl shadow-xl overflow-hidden">
        <!-- Header -->
        <div class="bg-blue-600 px-6 py-5 flex items-center gap-4">
            <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shrink-0">
                <i class="fas fa-hammer text-blue-600 text-xl"></i>
            </div>
            <div class="text-white">
                <h1 class="text-xl font-bold">Tenant Modification Request</h1>
                <p class="text-blue-100 text-sm opacity-90">Unit
                    <?= h($mod['unit_number'] ?? '')?>
                </p>
            </div>
        </div>

        <div class="p-6">
            <?php if ($message): ?>
            <div
                class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-start gap-3">
                <i class="fas fa-check-circle mt-0.5"></i>
                <div>
                    <?= h($message)?>
                </div>
            </div>
            <?php
endif; ?>

            <?php if ($error && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-start gap-3">
                <i class="fas fa-times-circle mt-0.5"></i>
                <div>
                    <?= h($error)?>
                </div>
            </div>
            <?php
endif; ?>

            <?php if ($mod): ?>
            <p class="text-gray-600 mb-6 text-sm">
                Dear
                <?= h($mod['owner_name'])?>, your tenant <strong>
                    <?= h($mod['tenant_name'])?>
                </strong> has submitted a modification request for your unit. Please review the details below.
            </p>

            <div class="bg-gray-50 border rounded-xl p-5 mb-6 space-y-4">
                <div>
                    <div class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Category</div>
                    <div class="font-semibold text-gray-800">
                        <?= h($mod['category'])?>
                    </div>
                </div>
                <div>
                    <div class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Description</div>
                    <div class="text-gray-700 whitespace-pre-wrap text-sm">
                        <?= h($mod['description'])?>
                    </div>
                </div>

                <?php if ($attachments): ?>
                <div>
                    <div class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Attachments</div>
                    <div class="space-y-2">
                        <?php foreach ($attachments as $att): ?>
                        <a href="<?= SITE_URL?>/<?= h($att['file_path'])?>" target="_blank"
                            class="flex items-center gap-2 p-2 bg-white border rounded-lg hover:border-blue-400 text-sm transition group">
                            <i class="fas fa-file-alt text-gray-400 group-hover:text-blue-500"></i>
                            <span class="flex-1 truncate group-hover:text-blue-600 text-gray-700">
                                <?= h($att['display_name'])?>
                            </span>
                            <i class="fas fa-external-link-alt text-gray-300 group-hover:text-blue-400 text-xs"></i>
                        </a>
                        <?php
        endforeach; ?>
                    </div>
                </div>
                <?php
    endif; ?>
            </div>

            <form method="POST" class="flex gap-4">
                <button type="submit" name="action" value="decline"
                    class="flex-1 bg-white border-2 border-red-200 text-red-600 hover:bg-red-50 font-bold py-3 px-4 rounded-xl transition flex items-center justify-center gap-2"
                    onclick="return confirm('Are you sure you want to DECLINE this request?');">
                    <i class="fas fa-times"></i> Decline
                </button>
                <button type="submit" name="action" value="approve"
                    class="flex-1 bg-blue-600 text-white hover:bg-blue-700 font-bold py-3 px-4 rounded-xl shadow-lg shadow-blue-200 transition flex items-center justify-center gap-2">
                    <i class="fas fa-check"></i> Approve Request
                </button>
            </form>
            <?php
endif; ?>
        </div>
    </div>

</body>

</html>