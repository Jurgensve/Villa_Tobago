<?php
ob_start();
$required_roles = ['admin', 'managing_agent', 'trustee'];
require_once 'includes/header.php';

$unit_id = (int)($_GET['unit_id'] ?? 0);
$message = '';
$error = '';

if (!$unit_id) {
    echo "<div class='bg-red-100 p-4 rounded text-red-700'>No unit specified.</div>";
    require_once 'includes/footer.php';
    exit;
}

// ── Load unit and pending application ─────────────────────────────────────────
$unit = $pdo->prepare("SELECT * FROM units WHERE id = ?");
$unit->execute([$unit_id]);
$unit = $unit->fetch();

$app_type = null;
$app_id = null;

if ($unit && !empty($unit['pending_app_type']) && !empty($unit['pending_app_id'])) {
    $app_type = $unit['pending_app_type'];
    $app_id = (int)$unit['pending_app_id'];
}
else {
    // Check if the active resident is pending final approval
    // Check if the active resident is pending final approval
    try {
        $active_res = $pdo->prepare("SELECT resident_type, resident_id FROM residents WHERE unit_id = ?");
        $active_res->execute([$unit_id]);
        $res_row = $active_res->fetch();
        if ($res_row) {
            $check_table = $res_row['resident_type'] === 'owner' ? 'owners' : 'tenants';
            $check_stmt = $pdo->prepare("SELECT status FROM {$check_table} WHERE id = ?");
            $check_stmt->execute([$res_row['resident_id']]);
            $status_val = $check_stmt->fetchColumn();
            if ($status_val !== 'Approved' && $status_val !== 'Completed') {
                $app_type = $res_row['resident_type'];
                $app_id = (int)$res_row['resident_id'];
            }
        }
    }
    catch (PDOException $e) {
    // Migration might not have run yet, ignore
    }
}

if (!$unit || !$app_type || !$app_id) {
    echo "<div class='bg-yellow-100 p-4 rounded text-yellow-800'>No pending resident application found for this unit.</div>
          <a href='units.php?action=view&id={$unit_id}' class='mt-4 inline-block text-blue-600 hover:text-blue-800'>&larr; Back to Unit View</a>";
    require_once 'includes/footer.php';
    exit;
}

$app_table = ($app_type === 'owner') ? 'owners' : 'tenants';

$appStmt = $pdo->prepare("SELECT * FROM {$app_table} WHERE id = ?");
$appStmt->execute([$app_id]);
$app = $appStmt->fetch();

if (!$app) {
    echo "<div class='bg-red-100 p-4 rounded text-red-700'>Application record not found.</div>
          <a href='units.php?action=view&id={$unit_id}' class='mt-4 inline-block text-blue-600 hover:text-blue-800'>&larr; Back to Unit View</a>";
    require_once 'includes/footer.php';
    exit;
}

$app_vehicles = [];
try {
    $vstmt = $pdo->prepare("SELECT * FROM vehicles WHERE resident_type = ? AND resident_id = ? AND unit_id = ? ORDER BY created_at ASC");
    $vstmt->execute([$app_type, $app_id, $unit_id]);
    $app_vehicles = $vstmt->fetchAll();
}
catch (PDOException $e) {
}

$app_pets = [];
try {
    $pstmt = $pdo->prepare("SELECT * FROM pets WHERE resident_type = ? AND resident_id = ? AND unit_id = ? ORDER BY created_at DESC");
    $pstmt->execute([$app_type, $app_id, $unit_id]);
    $app_pets = $pstmt->fetchAll();
}
catch (PDOException $e) {
}

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_taken = $_POST['action'] ?? '';

    try {
        $pdo->beginTransaction();

        if ($action_taken === 'approve') {
            // 1. Approve the pending record
            if ($app_type === 'owner') {
                $pdo->prepare("UPDATE owners SET status = 'Approved', agent_approval = 1, portal_access_granted = 1 WHERE id = ?")
                    ->execute([$app_id]);
            }
            else {
                $pdo->prepare("UPDATE tenants SET status = 'Approved', owner_approval = 1, portal_access_granted = 1, amendment_token = NULL WHERE id = ?")
                    ->execute([$app_id]);
            }

            // 2. Update the residents table — this is the single source of truth for who is the active resident
            $pdo->prepare(
                "INSERT INTO residents (unit_id, resident_type, resident_id)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE resident_type = VALUES(resident_type), resident_id = VALUES(resident_id)"
            )->execute([$unit_id, $app_type, $app_id]);

            // 3. Clear the pending app reference on the unit
            $pdo->prepare("UPDATE units SET pending_app_type = NULL, pending_app_id = NULL WHERE id = ?")
                ->execute([$unit_id]);

            // 4. Notify the applicant
            send_notification_email(
                $app['email'],
                'Your Resident Application has been Approved – Villa Tobago',
                "<p>Dear " . h($app['full_name']) . ",</p>
                 <p>We are pleased to inform you that your resident application for Unit <strong>" . h($unit['unit_number']) . "</strong> has been approved!</p>
                 <p>You can now log in to the Resident Portal to complete your profile details.</p>
                 <p style='margin:20px 0'><a href='" . SITE_URL . "/resident_portal.php' style='background:#4F46E5;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;font-weight:bold;'>Go to Resident Portal</a></p>
                 <p>Welcome to Villa Tobago!<br>Villa Tobago Management</p>"
            );

            $pdo->commit();
            header("Location: units.php?action=view&id={$unit_id}&msg=resident_approved");
            exit;

        }
        elseif ($action_taken === 'decline') {
            // 1. Set status to Declined
            $pdo->prepare("UPDATE {$app_table} SET status = 'Declined', amendment_token = NULL WHERE id = ?")
                ->execute([$app_id]);

            // 2. Clear pending ref
            $pdo->prepare("UPDATE units SET pending_app_type = NULL, pending_app_id = NULL WHERE id = ?")
                ->execute([$unit_id]);

            // 3. Notify applicant
            $reason = trim($_POST['decline_reason'] ?? '');
            send_notification_email(
                $app['email'],
                'Resident Application Update – Villa Tobago',
                "<p>Dear " . h($app['full_name']) . ",</p>
                 <p>Unfortunately, your resident application for Unit <strong>" . h($unit['unit_number']) . "</strong> could not be approved at this time.</p>"
                . ($reason ? "<p><strong>Reason:</strong> " . nl2br(h($reason)) . "</p>" : "")
                . "<p>Please contact the managing agent if you have any questions.</p>
                 <p>Regards,<br>Villa Tobago Management</p>"
            );

            $pdo->commit();
            header("Location: units.php?action=view&id={$unit_id}&msg=resident_declined");
            exit;

        }
        elseif ($action_taken === 'approve_pet') {
            $pet_id = (int)$_POST['pet_id'];
            $pdo->prepare("UPDATE pets SET status = 'Approved' WHERE id = ?")->execute([$pet_id]);

            // Auto-check if all pets are approved to satisfy the pet_approval requirement
            $unapproved = $pdo->prepare("SELECT COUNT(*) FROM pets WHERE resident_type = ? AND resident_id = ? AND status != 'Approved'");
            $unapproved->execute([$app_type, $app_id]);
            if ($unapproved->fetchColumn() == 0) {
                $pdo->prepare("UPDATE {$app_table} SET pet_approval = 1 WHERE id = ?")->execute([$app_id]);
            }
            $pdo->commit();
            header("Location: resident_application.php?unit_id={$unit_id}&msg=" . urlencode("Pet approved."));
            exit;
        }
        elseif ($action_taken === 'decline_pet') {
            $pet_id = (int)$_POST['pet_id'];
            $pdo->prepare("UPDATE pets SET status = 'Declined' WHERE id = ?")->execute([$pet_id]);
            $pdo->commit();
            header("Location: resident_application.php?unit_id={$unit_id}&err=" . urlencode("Pet declined."));
            exit;
        }
        elseif ($action_taken === 'request_info') {
            // Generate amendment token and set status
            $token = bin2hex(random_bytes(32));
            $comment = trim($_POST['info_request_comment'] ?? '');
            $pdo->prepare("UPDATE {$app_table} SET status = 'Information Requested', amendment_token = ? WHERE id = ?")
                ->execute([$token, $app_id]);

            // Build amendment link — for tenant, use the owner_approve page; for resident form, use amend_request
            $amend_link = SITE_URL . "/amend_request.php?type={$app_type}&token={$token}";

            send_notification_email(
                $app['email'],
                'Additional Information Required – Villa Tobago',
                "<p>Dear " . h($app['full_name']) . ",</p>
                 <p>We need some additional information regarding your resident application for Unit <strong>" . h($unit['unit_number']) . "</strong>.</p>"
                . ($comment ? "<blockquote style='border-left:4px solid #F59E0B;padding:8px 16px;background:#FFFBEB;margin:16px 0'>" . nl2br(h($comment)) . "</blockquote>" : "")
                . "<p style='margin:20px 0'><a href='{$amend_link}' style='background:#D97706;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;font-weight:bold;'>Update My Application</a></p>
                 <p>Regards,<br>Villa Tobago Management</p>"
            );

            $pdo->commit();
            $message = "Information request sent to " . h($app['full_name']) . ".";
        }
    }
    catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// ── Derive progress step ──────────────────────────────────────────────────────
$owner_approved = ($app_type === 'owner') ? (($app['agent_approval'] ?? 0) == 1) : (($app['owner_approval'] ?? 0) == 1);
$has_pets_flag = ($app_type === 'tenant') ? (($app['pet_approval'] ?? 1) == 0) : false;
$pet_approved = ($app['pet_approval'] ?? 1) == 1;
$status = $app['status'] ?? 'Pending';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <a href="units.php?action=view&id=<?= $unit_id?>"
                class="text-blue-600 hover:text-blue-800 text-sm font-semibold">
                <i class="fas fa-arrow-left mr-1"></i> Back to Unit
                <?= h($unit['unit_number'])?>
            </a>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">
                Resident Application Review
                <span class="text-blue-600">— Unit
                    <?= h($unit['unit_number'])?>
                </span>
            </h1>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold
            <?= $app_type === 'tenant' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800'?>">
            <?= ucfirst($app_type)?> Application
        </span>
    </div>

    <?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-lg font-semibold">
        <i class="fas fa-check-circle mr-2"></i>
        <?= h($message)?>
    </div>
    <?php
endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded-lg">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        <?= h($error)?>
    </div>
    <?php
endif; ?>

    <!-- Progress Flow -->
    <div class="bg-white shadow rounded-xl p-6">
        <h2 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-5">Application Progress</h2>
        <?php
// Determine steps dynamically
$steps = [
    ['label' => 'Submitted', 'done' => true],
    ['label' => $app_type === 'tenant' ? 'Owner Approval' : 'Agent Verification',
        'done' => $owner_approved],
];
if ($has_pets_flag) {
    $steps[] = ['label' => 'Pet / Trustee Approval', 'done' => $pet_approved];
}
$steps[] = ['label' => 'Approved & Active', 'done' => $status === 'Approved'];
?>
        <div class="flex items-center gap-0">
            <?php foreach ($steps as $i => $step): ?>
            <?php $is_last = ($i === count($steps) - 1); ?>
            <div class="flex items-center <?=!$is_last ? 'flex-1' : ''?>">
                <div class="flex flex-col items-center">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm
                            <?= $step['done'] ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-500'?>">
                        <?= $step['done'] ? '<i class="fas fa-check"></i>' : ($i + 1)?>
                    </div>
                    <span class="text-xs mt-1 text-center font-semibold
                            <?= $step['done'] ? 'text-green-700' : 'text-gray-400'?>" style="max-width:80px">
                        <?= $step['label']?>
                    </span>
                </div>
                <?php if (!$is_last): ?>
                <div class="flex-1 h-1 mx-1 <?= $step['done'] ? 'bg-green-400' : 'bg-gray-200'?>"></div>
                <?php
    endif; ?>
            </div>
            <?php
endforeach; ?>
        </div>
    </div>

    <!-- Applicant Details -->
    <div class="bg-white shadow rounded-xl overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-user text-gray-500"></i> Applicant Details
            </h2>
        </div>
        <div class="p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                <div>
                    <dt class="text-xs font-bold text-gray-400 uppercase tracking-widest">Full Name</dt>
                    <dd class="font-bold text-gray-900 mt-0.5">
                        <?= h($app['full_name'])?>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-bold text-gray-400 uppercase tracking-widest">RSA ID / Passport</dt>
                    <dd class="text-gray-700 mt-0.5">
                        <?= h($app['id_number'] ?? '—')?>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-bold text-gray-400 uppercase tracking-widest">Email</dt>
                    <dd class="text-gray-700 mt-0.5">
                        <a href="mailto:<?= h($app['email'])?>" class="text-blue-600 hover:underline">
                            <?= h($app['email'])?>
                        </a>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-bold text-gray-400 uppercase tracking-widest">Phone</dt>
                    <dd class="text-gray-700 mt-0.5">
                        <?= h($app['phone'] ?? '—')?>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-bold text-gray-400 uppercase tracking-widest">Application Type</dt>
                    <dd class="mt-0.5">
                        <span
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold
                            <?= $app_type === 'tenant' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800'?>">
                            <?= ucfirst($app_type)?>
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-bold text-gray-400 uppercase tracking-widest">Current Status</dt>
                    <dd class="mt-0.5">
                        <?php
$sc = match (true) {
        str_contains($status, 'Approved') => 'bg-green-100 text-green-800',
        str_contains($status, 'Declined') => 'bg-red-100 text-red-800',
        str_contains($status, 'Information') => 'bg-yellow-100 text-yellow-800',
        default => 'bg-gray-100 text-gray-600',
    };
?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?= $sc?>">
                            <?= h($status)?>
                        </span>
                    </dd>
                </div>
                <?php if ($app_type === 'tenant'): ?>
                <div>
                    <dt class="text-xs font-bold text-gray-400 uppercase tracking-widest">Owner Approval</dt>
                    <dd class="mt-0.5">
                        <?= $owner_approved
        ? '<span class="text-green-700 font-semibold"><i class="fas fa-check-circle mr-1"></i>Approved</span>'
        : '<span class="text-amber-600 font-semibold"><i class="fas fa-clock mr-1"></i>Pending</span>'?>
                    </dd>
                </div>
                <?php
endif; ?>
                <?php if ($has_pets_flag): ?>
                <div>
                    <dt class="text-xs font-bold text-gray-400 uppercase tracking-widest">Pet / Trustee Approval</dt>
                    <dd class="mt-0.5">
                        <?= $pet_approved
        ? '<span class="text-green-700 font-semibold"><i class="fas fa-check-circle mr-1"></i>Approved</span>'
        : '<span class="text-amber-600 font-semibold"><i class="fas fa-clock mr-1"></i>Pending</span>'?>
                    </dd>
                </div>
                <?php
endif; ?>
            </dl>
        </div>
    </div>

    <!-- Intercom Contacts -->
    <div class="bg-white shadow rounded-xl overflow-hidden mt-6">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-phone text-gray-500"></i> Intercom Contacts
            </h2>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                <p class="text-xs font-bold text-blue-900 uppercase mb-1">Contact 1</p>
                <p class="font-bold text-gray-900">
                    <?= h($app['intercom_contact1_name'] ?: 'Not provided')?>
                </p>
                <p class="text-gray-600 text-sm">
                    <?= h($app['intercom_contact1_phone'] ?: '—')?>
                </p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <p class="text-xs font-bold text-gray-500 uppercase mb-1">Contact 2</p>
                <p class="font-bold text-gray-900">
                    <?= h($app['intercom_contact2_name'] ?: 'Not provided')?>
                </p>
                <p class="text-gray-600 text-sm">
                    <?= h($app['intercom_contact2_phone'] ?: '—')?>
                </p>
            </div>
        </div>
    </div>

    <!-- Vehicles -->
    <div class="bg-white shadow rounded-xl overflow-hidden mt-6">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-car text-gray-500"></i> Vehicles
            </h2>
        </div>
        <div class="p-6">
            <?php if (empty($app_vehicles)): ?>
            <p class="text-gray-500 italic">No vehicles registered.</p>
            <?php
else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($app_vehicles as $v): ?>
                <div class="flex items-center gap-4 bg-gray-50 border border-gray-200 p-4 rounded-lg">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                        <i class="fas fa-car-side"></i>
                    </div>
                    <div>
                        <p class="font-bold text-gray-900">
                            <?= h($v['registration'])?>
                        </p>
                        <p class="text-sm text-gray-500">
                            <?= h($v['make_model'] ?: 'Unknown Make')?> •
                            <?= h($v['color'] ?: 'Unknown Color')?>
                        </p>
                    </div>
                </div>
                <?php
    endforeach; ?>
            </div>
            <?php
endif; ?>
        </div>
    </div>

    <!-- Pets -->
    <div class="bg-white shadow rounded-xl overflow-hidden mt-6 mb-6">
        <div class="px-6 py-4 bg-gray-50 border-b mb-6 border-b">
            <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-paw text-gray-500"></i> Pets
            </h2>
        </div>
        <div class="px-6 pb-6">
            <?php if (empty($app_pets)): ?>
            <p class="text-gray-500 italic">No pets registered.</p>
            <?php
else: ?>
            <div class="space-y-4">
                <?php foreach ($app_pets as $p): ?>
                <div
                    class="flex flex-col md:flex-row items-center justify-between gap-4 bg-gray-50 border border-gray-200 p-4 rounded-lg">
                    <div class="flex items-center gap-4">
                        <div
                            class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 text-xl">
                            <i
                                class="fas fa-<?= strtolower($p['type']) === 'cat' ? 'cat' : (strtolower($p['type']) === 'bird' ? 'dove' : (strtolower($p['type']) === 'fish' ? 'fish' : 'dog'))?>"></i>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900">
                                <?= h($p['name'])?> <span
                                    class="bg-gray-200 text-gray-700 text-xs px-2 py-0.5 rounded-full ml-1">
                                    <?= h($p['type'])?>
                                </span>
                            </p>
                            <p class="text-sm text-gray-500">
                                <?= h($p['breed'] ?: 'Mixed')?>
                                <?= $p['adult_size'] ? ' • ' . h($p['adult_size']) . ' Size' : ''?>
                                <?= $p['status'] === 'Approved' ? '<span class="text-green-600 font-bold ml-2"><i class="fas fa-check-circle mr-1"></i>Approved</span>' : '<span class="text-amber-600 font-bold ml-2"><i class="fas fa-clock mr-1"></i>Pending</span>'?>
                            </p>
                        </div>
                    </div>
                    <?php if ($p['status'] !== 'Approved' && $p['status'] !== 'Declined'): ?>
                    <div class="flex gap-2">
                        <form method="POST" class="inline" onsubmit="return confirm('Approve this pet?');">
                            <input type="hidden" name="action" value="approve_pet">
                            <input type="hidden" name="pet_id" value="<?= $p['id']?>">
                            <button type="submit"
                                class="text-sm bg-green-100 hover:bg-green-200 text-green-800 font-bold py-1.5 px-3 rounded shadow-sm">
                                Approve Pet
                            </button>
                        </form>
                        <form method="POST" class="inline" onsubmit="return confirm('Decline this pet?');">
                            <input type="hidden" name="action" value="decline_pet">
                            <input type="hidden" name="pet_id" value="<?= $p['id']?>">
                            <button type="submit"
                                class="text-sm bg-red-100 hover:bg-red-200 text-red-800 font-bold py-1.5 px-3 rounded shadow-sm">
                                Decline
                            </button>
                        </form>
                    </div>
                    <?php
        endif; ?>
                </div>
                <?php
    endforeach; ?>
            </div>
            <?php
endif; ?>
        </div>
    </div>
    <?php if ($status !== 'Approved' && $status !== 'Declined'): ?>
    <div class="bg-white shadow rounded-xl overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h2 class="text-lg font-bold text-gray-800"><i class="fas fa-gavel mr-2 text-gray-500"></i>Actions</h2>
        </div>
        <div class="p-6 space-y-6">

            <!-- Approve -->
            <div class="flex items-start justify-between gap-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div>
                    <p class="font-bold text-green-900">Approve Application</p>
                    <p class="text-green-700 text-sm mt-1">
                        This will set the applicant as the active resident of Unit
                        <?= h($unit['unit_number'])?>,
                        grant portal access, and send them an approval email.
                    </p>
                </div>
                <form method="POST"
                    onsubmit="return confirm('Approve this application and set them as the active resident?')">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit"
                        class="whitespace-nowrap bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-5 rounded-lg shadow transition">
                        <i class="fas fa-check mr-1"></i> Approve
                    </button>
                </form>
            </div>

            <!-- Request Info -->
            <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p class="font-bold text-yellow-900 mb-2"><i class="fas fa-comment-dots mr-1"></i> Request Additional
                    Information</p>
                <form method="POST">
                    <input type="hidden" name="action" value="request_info">
                    <textarea name="info_request_comment" rows="3"
                        placeholder="Describe what additional information is required..."
                        class="w-full border border-yellow-300 rounded-lg px-4 py-2 mt-1 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400 bg-white"></textarea>
                    <button type="submit"
                        class="mt-2 bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-5 rounded-lg shadow transition text-sm">
                        <i class="fas fa-paper-plane mr-1"></i> Send Request
                    </button>
                </form>
            </div>

            <!-- Decline -->
            <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                <p class="font-bold text-red-900 mb-2"><i class="fas fa-times-circle mr-1"></i> Decline Application</p>
                <form method="POST"
                    onsubmit="return confirm('Are you sure you want to decline this application? This cannot be undone.')">
                    <input type="hidden" name="action" value="decline">
                    <textarea name="decline_reason" rows="2"
                        placeholder="Optional: reason for declining (included in email to applicant)..."
                        class="w-full border border-red-300 rounded-lg px-4 py-2 mt-1 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-white"></textarea>
                    <button type="submit"
                        class="mt-2 bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-5 rounded-lg shadow transition text-sm">
                        <i class="fas fa-ban mr-1"></i> Decline Application
                    </button>
                </form>
            </div>

        </div>
    </div>
    <?php
elseif ($status === 'Approved'): ?>
    <div class="bg-green-100 border border-green-400 text-green-800 px-6 py-4 rounded-xl font-semibold text-center">
        <i class="fas fa-check-circle text-2xl mb-1 block"></i>
        This application has been approved. The resident is now active.
    </div>
    <?php
else: ?>
    <div class="bg-red-100 border border-red-400 text-red-800 px-6 py-4 rounded-xl font-semibold text-center">
        <i class="fas fa-ban text-2xl mb-1 block"></i>
        This application has been declined.
    </div>
    <?php
endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>