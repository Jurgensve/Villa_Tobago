<?php
// owner_approve_tenant.php
require_once 'admin/config/db.php';
require_once 'admin/includes/functions.php';

$message = '';
$error = '';
$tenant = null;
$token = $_GET['token'] ?? '';

// Ensure token exists on DB. Since we just added this logic, let's ensure the column exists
try {
    $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS amendment_token VARCHAR(255) NULL");
}
catch (PDOException $e) { /* Ignore if it already exists or db user lacks privilege just in case */
}

if ($token) {
    $stmt = $pdo->prepare("SELECT t.*, u.unit_number FROM tenants t JOIN units u ON t.unit_id = u.id WHERE t.amendment_token = ?");
    $stmt->execute([$token]);
    $tenant = $stmt->fetch();

    if (!$tenant) {
        $error = "Invalid or expired approval link.";
    }
}
else {
    $error = "No token provided.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $tenant) {
    $action = $_POST['action'];
    $tenant_id = $tenant['id'];

    try {
        if ($action === 'approve') {
            $pdo->prepare("UPDATE tenants SET owner_approval = 1, amendment_token = NULL WHERE id = ?")->execute([$tenant_id]);
            $message = "You have successfully approved " . h($tenant['full_name']) . " as a tenant.";
            $tenant = null; // Hide the form
        }
        elseif ($action === 'decline') {
            $pdo->prepare("UPDATE tenants SET owner_approval = 0, status = 'Declined', amendment_token = NULL WHERE id = ?")->execute([$tenant_id]);
            $message = "You have declined the tenant application for " . h($tenant['full_name']) . "!";
            // Send email to tenant
            $subject = "Update: Resident Application Status";
            $body = "Dear " . h($tenant['full_name']) . ",<br><br>We regret to inform you that the unit owner has declined your tenant application.";
            send_notification_email($tenant['email'], $subject, $body);
            
            $tenant = null; // Hide the form
        }
    }
    catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Owner Tenant Approval - Villa Tobago</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gray-100 min-h-screen font-sans flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-white rounded-lg shadow-xl p-8">

        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900 border-b pb-4"><i class="fas fa-home text-blue-600 mr-2"></i>
                Tenant Approval Required</h1>
        </div>

        <?php if ($message): ?>
        <div class="bg-green-100 border border-green-500 text-green-700 px-4 py-3 rounded text-center mb-6">
            <strong><i class="fas fa-check-circle mr-2"></i>
                <?= h($message)?>
            </strong>
        </div>
        <div class="text-center">
            <a href="index.html" class="text-blue-600 underline hover:text-blue-800">Return to Homepage</a>
        </div>
        <?php
elseif ($error): ?>
        <div class="bg-red-100 border border-red-500 text-red-700 px-4 py-3 rounded text-center mb-6">
            <strong><i class="fas fa-exclamation-triangle mr-2"></i>
                <?= h($error)?>
            </strong>
        </div>
        <?php
elseif ($tenant): ?>
        <p class="text-gray-600 mb-6 text-center">A new application has been submitted by a tenant applying to live in
            your unit. Please review their details below.</p>

        <div class="bg-gray-50 p-6 rounded-lg mb-8 border border-gray-200">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Application Details</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="block text-sm text-gray-500">Unit</span>
                    <span class="block font-bold text-gray-800">
                        <?= h($tenant['unit_number'])?>
                    </span>
                </div>
                <div>
                    <span class="block text-sm text-gray-500">Applicant Name</span>
                    <span class="block font-bold text-gray-800">
                        <?= h($tenant['full_name'])?>
                    </span>
                </div>
                <div>
                    <span class="block text-sm text-gray-500">ID Number / Passport</span>
                    <span class="block font-bold text-gray-800">
                        <?= h($tenant['id_number'])?>
                    </span>
                </div>
                <div>
                    <span class="block text-sm text-gray-500">Phone</span>
                    <span class="block font-bold text-gray-800">
                        <?= h($tenant['phone'])?>
                    </span>
                </div>
                <div>
                    <span class="block text-sm text-gray-500">Email</span>
                    <span class="block font-bold text-gray-800">
                        <?= h($tenant['email'])?>
                    </span>
                </div>
                <div>
                    <span class="block text-sm text-gray-500">Pets?</span>
                    <span
                        class="block font-bold <?=($tenant['pet_approval'] ?? 1) == 0 ? 'text-red-800' : 'text-gray-800'?>">
                        <?=($tenant['pet_approval'] ?? 1) == 0 ? 'Yes (Requires Trustee Mgmt)' : 'No'?>
                    </span>
                </div>
            </div>
        </div>

        <form method="POST" class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
            <input type="hidden" name="token" value="<?= h($token)?>">
            <button type="submit" name="action" value="decline"
                class="flex-1 bg-red-100 text-red-700 hover:bg-red-200 font-bold py-3 px-4 rounded border border-red-300 transition"
                onclick="return confirm('Are you sure you want to decline this tenant?');">
                <i class="fas fa-times-circle mr-2"></i> Decline
            </button>
            <button type="submit" name="action" value="approve"
                class="flex-1 bg-green-600 text-white hover:bg-green-700 font-bold py-3 px-4 rounded shadow transition"
                onclick="return confirm('Are you sure you want to approve this tenant?');">
                <i class="fas fa-check-circle mr-2"></i> Approve Tenant
            </button>
        </form>
        <?php
endif; ?>
    </div>
</body>

</html>