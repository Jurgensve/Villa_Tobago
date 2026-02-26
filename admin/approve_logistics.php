<?php
// admin/approve_logistics.php — Agent review and approval for Move-In/Move-Out Logistics
$required_roles = ['admin', 'managing_agent'];
require_once 'includes/header.php';
require_once 'includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid request.");
}

$error = '';
$success = '';

// Fetch the logistics request
$stmt = $pdo->prepare(
    "SELECT ml.*, u.unit_number,
            COALESCE(t.full_name, o.full_name) AS resident_name,
            COALESCE(t.email, o.email) AS resident_email,
            COALESCE(t.phone_number, o.phone_number) AS resident_phone
     FROM move_logistics ml
     JOIN units u ON ml.unit_id = u.id
     LEFT JOIN tenants t ON ml.resident_type = 'tenant' AND ml.resident_id = t.id
     LEFT JOIN owners o ON ml.resident_type = 'owner' AND ml.resident_id = o.id
     WHERE ml.id = ?"
);
$stmt->execute([$id]);
$req = $stmt->fetch();

if (!$req) {
    die("Logistics request not found.");
}

// Fetch global max truck GWM setting
$max_gwm = 3500;
try {
    $gwm_setting = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'max_truck_gwm'")->fetchColumn();
    if (is_numeric($gwm_setting))
        $max_gwm = (int)$gwm_setting;
}
catch (Exception $e) {
}


// ----- Handle Actions -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        try {
            $pdo->beginTransaction();

            // Mark as Approved
            $pdo->prepare("UPDATE move_logistics SET status = 'Approved' WHERE id = ?")->execute([$id]);
            $req['status'] = 'Approved'; // Update local state for display

            // 1. Send confirmation to Resident
            $move_str = $req['move_type'] === 'move_in' ? 'Move-In' : 'Move-Out';
            $subject = "{$move_str} Request Approved – Villa Tobago";
            $body = "<p>Dear " . h($req['resident_name']) . ",</p>";
            $body .= "<p>Your {$move_str} request for Unit " . h($req['unit_number']) . " has been <strong>approved</strong> by the Managing Agent.</p>";
            $body .= "<p><strong>Date:</strong> " . ($req['preferred_date'] ? format_date($req['preferred_date']) : 'Not specified') . "</p>";
            $body .= "<p>Security has been notified and will expect your arrival.</p>";
            if ($req['truck_gwm'] > $max_gwm) {
                $body .= "<p style='color:red;'><strong>Important:</strong> Your truck exceeds the max allowed weight limit. It must park outside the complex.</p>";
            }
            $body .= "<p>Regards,<br>Villa Tobago Management</p>";
            send_notification_email($req['resident_email'], $subject, $body);

            // 2. Send notification to Security
            $notify_data = [
                'unit_number' => $req['unit_number'],
                'resident_name' => $req['resident_name'],
                'resident_phone' => $req['resident_phone'],
                'move_type' => $req['move_type'],
                'preferred_date' => $req['preferred_date'],
                'truck_reg' => $req['truck_reg'],
                'moving_company' => $req['moving_company']
            ];
            if (send_security_notification($pdo, $notify_data)) {
                $pdo->prepare("UPDATE move_logistics SET security_notified = 1, security_notified_at = NOW() WHERE id = ?")->execute([$id]);
                $req['security_notified'] = 1;
            }

            $pdo->commit();
            $success = "Request approved successfully! Confirmation sent to resident and security.";
        }
        catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to process approval: " . $e->getMessage();
        }
    }
    elseif ($action === 'resend_security') {
        // Force resend security email
        $notify_data = [
            'unit_number' => $req['unit_number'],
            'resident_name' => $req['resident_name'],
            'resident_phone' => $req['resident_phone'],
            'move_type' => $req['move_type'],
            'preferred_date' => $req['preferred_date'],
            'truck_reg' => $req['truck_reg'],
            'moving_company' => $req['moving_company']
        ];
        if (send_security_notification($pdo, $notify_data)) {
            $pdo->prepare("UPDATE move_logistics SET security_notified = 1, security_notified_at = NOW() WHERE id = ?")->execute([$id]);
            $req['security_notified'] = 1;
            $success = "Security notification resent successfully.";
        }
        else {
            $error = "Failed to resend security notification. Check system settings to ensure a security email is configured.";
        }
    }
    elseif ($action === 'resend_resident') {
        // Force resend resident approval email
        $move_str = $req['move_type'] === 'move_in' ? 'Move-In' : 'Move-Out';
        $subject = "{$move_str} Request Approved – Villa Tobago (Resent)";
        $body = "<p>Dear " . h($req['resident_name']) . ",</p>";
        $body .= "<p>Your {$move_str} request for Unit " . h($req['unit_number']) . " has been <strong>approved</strong> by the Managing Agent.</p>";
        $body .= "<p><strong>Date:</strong> " . ($req['preferred_date'] ? format_date($req['preferred_date']) : 'Not specified') . "</p>";
        $body .= "<p>Security has been notified and will expect your arrival.</p>";
        if ($req['truck_gwm'] > $max_gwm) {
            $body .= "<p style='color:red;'><strong>Important:</strong> Your truck exceeds the max allowed weight limit. It must park outside the complex.</p>";
        }
        $body .= "<p>Regards,<br>Villa Tobago Management</p>";
        send_notification_email($req['resident_email'], $subject, $body);
        $success = "Resident confirmation email resent successfully.";
    }
    elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM move_logistics WHERE id = ?")->execute([$id]);
        echo "<script>window.location.href = 'pending_approvals.php';</script>";
        exit;
    }
}

// ── UI ───────────────────────────────────────────────────────────────────────────
$m_type = $req['move_type'] === 'move_in' ? 'Move-In' : 'Move-Out';
$m_color = $req['move_type'] === 'move_in' ? 'green' : 'orange';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <a href="pending_approvals.php" class="text-blue-600 hover:underline text-sm mb-2 inline-block">
            <i class="fas fa-arrow-left mr-1"></i> Back to Pending Approvals
        </a>
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
            <span class="bg-<?= $m_color?>-100 text-<?= $m_color?>-600 p-2 rounded-lg inline-flex">
                <i class="fas fa-<?= $req['move_type'] === 'move_in' ? 'sign-in-alt' : 'sign-out-alt'?>"></i>
            </span>
            Review
            <?= $m_type?> Request
        </h1>
        <p class="text-gray-500 mt-1">Review logistics and truck details for Unit
            <?= h($req['unit_number'])?>
        </p>
    </div>

    <div class="flex gap-2">
        <form method="POST" onsubmit="return confirm('Delete this request entirely? This cannot be undone.');">
            <input type="hidden" name="action" value="delete">
            <button type="submit"
                class="bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 font-bold py-2 px-4 rounded shadow-sm">
                <i class="fas fa-trash-alt mr-1"></i> Delete
            </button>
        </form>
    </div>
</div>

<?php if ($success): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center shadow-sm">
    <i class="fas fa-check-circle mr-2 text-xl"></i>
    <span class="font-medium">
        <?= h($success)?>
    </span>
</div>
<?php
endif; ?>

<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center shadow-sm">
    <i class="fas fa-exclamation-triangle mr-2 text-xl"></i>
    <span class="font-medium">
        <?= h($error)?>
    </span>
</div>
<?php
endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Left Col: Details -->
    <div class="md:col-span-2 space-y-6">

        <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-100">
            <div class="px-6 py-4 bg-gray-50 border-b flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-800"><i class="fas fa-clipboard-list mr-2 text-blue-500"></i>
                    Request Details</h2>
                <span
                    class="px-3 py-1 rounded-full text-xs font-bold 
                    <?= $req['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'?>">
                    Status:
                    <?= h($req['status'])?>
                </span>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase mb-1">Unit Number</p>
                        <p class="text-lg font-bold text-gray-900">
                            <?= h($req['unit_number'])?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase mb-1">Preferred Date</p>
                        <p class="text-lg text-gray-900 font-medium">
                            <?= $req['preferred_date'] ? format_date($req['preferred_date']) : '<span class="italic text-gray-400">Not specified</span>'?>
                        </p>
                    </div>
                </div>

                <hr class="my-5 border-gray-100">

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase mb-1">Resident Details</p>
                        <p class="font-bold text-gray-900">
                            <?= h($req['resident_name'])?> <span
                                class="bg-gray-100 text-gray-500 text-xs px-2 py-0.5 rounded uppercase ml-1">
                                <?= h($req['resident_type'])?>
                            </span>
                        </p>
                        <p class="text-sm text-gray-600 mt-1"><i class="fas fa-envelope mr-1 w-4"></i>
                            <?= h($req['resident_email'])?>
                        </p>
                        <p class="text-sm text-gray-600"><i class="fas fa-phone mr-1 w-4"></i>
                            <?= h($req['resident_phone'])?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase mb-1">Moving Company</p>
                        <p class="text-gray-900 font-medium">
                            <?= h($req['moving_company'] ?: 'None specified')?>
                        </p>
                    </div>
                </div>

                <hr class="my-5 border-gray-100">

                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase mb-1">Applicant Notes</p>
                    <div class="bg-gray-50 p-4 rounded-lg text-sm text-gray-700 italic border border-gray-100">
                        <?= nl2br(h($req['notes'] ?: 'No notes provided by the applicant.'))?>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-100">
            <div class="px-6 py-4 bg-gray-50 border-b">
                <h2 class="text-lg font-bold text-gray-800"><i class="fas fa-truck mr-2 text-indigo-500"></i> Truck &
                    Vehicle Rules</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase mb-1">Truck Registration</p>
                        <p class="text-lg font-bold text-gray-900">
                            <?= h($req['truck_reg'] ?: 'N/A')?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase mb-1">Truck Gross Vehicle Mass (GWM)</p>
                        <?php if ($req['truck_gwm']): ?>
                        <p
                            class="text-lg font-bold <?= $req['truck_gwm'] > $max_gwm ? 'text-red-600' : 'text-green-600'?>">
                            <?= number_format($req['truck_gwm'])?> kg
                        </p>
                        <?php if ($req['truck_gwm'] > $max_gwm): ?>
                        <p class="text-xs text-red-600 font-bold mt-1 bg-red-50 p-2 rounded inline-block">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Exceeds
                            <?= number_format($max_gwm)?>kg Limit. Must park outside.
                        </p>
                        <?php
    endif; ?>
                        <?php
else: ?>
                        <p class="text-gray-400 italic">Not provided</p>
                        <?php
endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Right Col: Actions -->
    <div class="space-y-6">

        <?php if ($req['status'] === 'Pending'): ?>
        <div class="bg-white shadow rounded-lg p-6 border-t-4 border-blue-500">
            <h3 class="font-bold text-gray-900 text-lg mb-2">Agent Approval Required</h3>
            <p class="text-sm text-gray-600 mb-6">Review the details and approve this request to notify the resident and
                security.</p>

            <form method="POST">
                <input type="hidden" name="action" value="approve">
                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition flex items-center justify-center text-lg">
                    <i class="fas fa-check-circle mr-2"></i> Approve Request
                </button>
            </form>
        </div>
        <?php
else: ?>
        <div class="bg-white shadow rounded-lg p-6 border-t-4 border-green-500">
            <h3 class="font-bold text-gray-900 text-lg mb-2 flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-2"></i> Approved
            </h3>
            <p class="text-sm text-gray-600">This request has been approved.</p>
        </div>
        <?php
endif; ?>

        <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-100">
            <div class="bg-gray-50 px-5 py-3 border-b border-gray-100">
                <h3 class="font-bold text-gray-700 text-sm"><i class="fas fa-envelope mr-1 text-gray-400"></i>
                    Communication Tools</h3>
            </div>
            <div class="p-5 space-y-4">

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-bold text-gray-700">Resident Email</span>
                        <?php if ($req['status'] === 'Approved'): ?>
                        <span
                            class="text-[10px] bg-green-100 text-green-800 px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">Sent</span>
                        <?php
else: ?>
                        <span
                            class="text-[10px] bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">Pending</span>
                        <?php
endif; ?>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="resend_resident">
                        <button type="submit" <?=$req['status'] !=='Approved' ? 'disabled' : ''?> class="w-full
                            bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50
                            disabled:cursor-not-allowed font-medium py-2 px-4 rounded text-sm transition">
                            <i class="fas fa-paper-plane mr-1 text-blue-500"></i> Resend Confirmation
                        </button>
                    </form>
                </div>

                <hr class="border-gray-100">

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-bold text-gray-700">Security Gate</span>
                        <?php if ($req['security_notified']): ?>
                        <span
                            class="text-[10px] bg-green-100 text-green-800 px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">Notified</span>
                        <?php
else: ?>
                        <span
                            class="text-[10px] bg-red-100 text-red-800 px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">Not
                            Sent</span>
                        <?php
endif; ?>
                    </div>
                    <?php if ($req['security_notified_at']): ?>
                    <p class="text-xs text-gray-400 mb-2">Last sent:
                        <?= format_date($req['security_notified_at'])?>
                    </p>
                    <?php
endif; ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="resend_security">
                        <button type="submit"
                            class="w-full bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium py-2 px-4 rounded text-sm transition">
                            <i class="fas fa-paper-plane mr-1 text-green-500"></i> Resend to Security
                        </button>
                    </form>
                </div>

            </div>
        </div>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>