<?php
// owner_approve_tenant.php
session_start();
require_once 'admin/config/db.php';
require_once 'admin/includes/functions.php';

$message = '';
$error = '';
$tenant = null;
$token = $_GET['token'] ?? '';

if ($token) {
    $stmt = $pdo->prepare(
        "SELECT t.*, u.unit_number FROM tenants t
         JOIN units u ON t.unit_id = u.id
         WHERE t.amendment_token = ?"
    );
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
            // Grant owner approval + portal access so tenant can complete their profile
            $pdo->prepare(
                "UPDATE tenants SET owner_approval = 1, portal_access_granted = 1, amendment_token = NULL WHERE id = ?"
            )->execute([$tenant_id]);

            // Email the tenant with portal access instructions
            $portal_url = SITE_URL . "/resident_portal.php";
            $subject = "Great News! Your Tenancy Application Has Been Approved";
            $body = "Dear " . h($tenant['full_name']) . ",<br><br>";
            $body .= "The unit owner has <strong>approved</strong> your application to reside at Villa Tobago.<br><br>";
            $body .= "You now have access to the Resident Portal where you need to complete a few more steps before your application is finalised:<br><br>";
            $body .= "<ul style='margin:0;padding-left:20px;'>";
            $body .= "<li>Set up your intercom contact details</li>";
            $body .= "<li>Confirm the number of occupants in the unit</li>";
            $body .= "<li>Register any vehicles kept on the property</li>";
            $body .= "<li>Register any pets (if applicable)</li>";
            $body .= "<li>Accept the Villa Tobago Code of Conduct</li>";
            $body .= "</ul><br>";
            $body .= "Please visit the Resident Portal to complete these steps:<br>";
            $body .= "<a href='{$portal_url}'>{$portal_url}</a><br><br>";
            $body .= "You can log in using your <strong>Unit Number</strong> and <strong>ID Number</strong>.<br><br>";
            $body .= "Once all steps are complete, the Managing Agent will do a final review and you will receive your Move-In Form.<br><br>";
            $body .= "Kind regards,<br>Villa Tobago Management";

            send_notification_email($tenant['email'], $subject, $body);

            $message = "You have successfully approved " . h($tenant['full_name']) . " as a tenant. They have been emailed with instructions to complete their profile in the Resident Portal.";
            $tenant = null;

        }
        elseif ($action === 'decline') {
            $pdo->prepare(
                "UPDATE tenants SET owner_approval = 0, status = 'Declined', amendment_token = NULL WHERE id = ?"
            )->execute([$tenant_id]);

            $subject = "Update: Your Resident Application Status";
            $body = "Dear " . h($tenant['full_name']) . ",<br><br>";
            $body .= "We regret to inform you that the unit owner has <strong>declined</strong> your tenant application for Villa Tobago.<br><br>";
            $body .= "If you believe this is an error, please contact the Managing Agent directly.<br><br>";
            $body .= "Kind regards,<br>Villa Tobago Management";
            send_notification_email($tenant['email'], $subject, $body);

            $message = "You have declined the tenant application for " . h($tenant['full_name']) . ". The applicant has been notified by email.";
            $tenant = null;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Approval — Villa Tobago</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gradient-to-br from-blue-900 to-blue-700 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-lg w-full">
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-400 mb-4">
                <i class="fas fa-building text-blue-900 text-2xl"></i>
            </div>
            <h1 class="text-2xl font-extrabold text-white">Tenant Approval</h1>
            <p class="text-blue-200 text-sm mt-1">Villa Tobago — Unit Owner Action Required</p>
        </div>

        <div class="bg-white rounded-2xl shadow-2xl p-8">

            <?php if ($message): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-5 rounded-xl text-center mb-5">
                <i class="fas fa-check-circle text-green-500 text-3xl mb-2 block"></i>
                <p class="font-bold text-green-800">
                    <?= h($message)?>
                </p>
            </div>
            <div class="text-center">
                <a href="index.html" class="text-blue-600 underline hover:text-blue-800 text-sm">Return to Homepage</a>
            </div>

            <?php
elseif ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-5 rounded-xl text-center">
                <i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-2 block"></i>
                <p class="font-bold text-red-800">
                    <?= h($error)?>
                </p>
            </div>

            <?php
elseif ($tenant): ?>
            <p class="text-gray-600 text-sm mb-6 text-center">A tenant has applied to reside in your unit. Please review
                their details below and approve or decline the application.</p>

            <div class="bg-gray-50 rounded-xl border border-gray-200 p-5 mb-6">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Application Details</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="block text-gray-400 text-xs">Unit</span>
                        <span class="block font-bold text-gray-900">
                            <?= h($tenant['unit_number'])?>
                        </span>
                    </div>
                    <div>
                        <span class="block text-gray-400 text-xs">Applicant Name</span>
                        <span class="block font-bold text-gray-900">
                            <?= h($tenant['full_name'])?>
                        </span>
                    </div>
                    <div>
                        <span class="block text-gray-400 text-xs">ID / Passport</span>
                        <span class="block font-bold text-gray-900">
                            <?= h($tenant['id_number'])?>
                        </span>
                    </div>
                    <div>
                        <span class="block text-gray-400 text-xs">Phone</span>
                        <span class="block font-bold text-gray-900">
                            <?= h($tenant['phone'])?>
                        </span>
                    </div>
                    <div class="col-span-2">
                        <span class="block text-gray-400 text-xs">Email</span>
                        <span class="block font-bold text-gray-900">
                            <?= h($tenant['email'])?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 text-sm text-blue-700">
                <i class="fas fa-info-circle mr-2"></i>
                If you approve, the tenant will receive an email with access to the Resident Portal to complete their
                full residency profile before the Managing Agent does a final review.
            </div>

            <form method="POST" class="flex flex-col sm:flex-row gap-3">
                <input type="hidden" name="token" value="<?= h($token)?>">
                <button type="submit" name="action" value="decline"
                    class="flex-1 bg-red-50 text-red-700 hover:bg-red-100 font-bold py-3 px-4 rounded-xl border-2 border-red-200 transition"
                    onclick="return confirm('Are you sure you want to decline this tenant?')">
                    <i class="fas fa-times-circle mr-2"></i> Decline
                </button>
                <button type="submit" name="action" value="approve"
                    class="flex-1 bg-green-600 text-white hover:bg-green-700 font-bold py-3 px-4 rounded-xl shadow transition"
                    onclick="return confirm('Approve this tenant and grant them portal access?')">
                    <i class="fas fa-check-circle mr-2"></i> Approve Tenant
                </button>
            </form>
            <?php
endif; ?>
        </div>
    </div>
</body>

</html>