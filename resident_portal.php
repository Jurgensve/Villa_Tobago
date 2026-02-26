<?php
// resident_portal.php
session_start();
require_once 'admin/config/db.php';
require_once 'admin/includes/functions.php';

$error = '';

// ── LOGOUT ───────────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: resident_portal.php");
    exit;
}

// ── LOGIN ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup'])) {
    $unit_id = (int)$_POST['unit_id'];
    $id_number = trim($_POST['id_number']);

    // Try owner
    $stmtOwner = $pdo->prepare(
        "SELECT o.*, u.unit_number, u.id AS unit_id, 'owner' AS type
         FROM owners o
         JOIN ownership_history oh ON o.id = oh.owner_id
         JOIN units u ON oh.unit_id = u.id
         WHERE u.id = ? AND o.id_number = ? AND oh.is_current = 1 LIMIT 1"
    );
    $stmtOwner->execute([$unit_id, $id_number]);
    $resident_record = $stmtOwner->fetch();

    if (!$resident_record) {
        $stmtTenant = $pdo->prepare(
            "SELECT t.*, u.unit_number, u.id AS unit_id, 'tenant' AS type
             FROM tenants t
             JOIN units u ON t.unit_id = u.id
             WHERE t.unit_id = ? AND t.id_number = ? LIMIT 1"
        );
        $stmtTenant->execute([$unit_id, $id_number]);
        $resident_record = $stmtTenant->fetch();
    }

    if ($resident_record) {
        $_SESSION['auth_resident'] = $resident_record;
        header("Location: resident_portal.php");
        exit;
    }
    else {
        $error = "Validation failed. ID Number and Unit combination not found.";
    }
}

// ── STEP FORM SUBMISSIONS ─────────────────────────────────────────────────────
if (isset($_SESSION['auth_resident'])) {
    $res = $_SESSION['auth_resident'];
    $rtype = $res['type']; // 'owner' or 'tenant'
    $rid = $res['id'];
    $uid = $res['unit_id'];
    $table = ($rtype === 'owner') ? 'owners' : 'tenants';

    // ── Step A: Intercom ──────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_intercom'])) {
        $pdo->prepare(
            "UPDATE {$table} SET
                intercom_contact1_name  = ?,
                intercom_contact1_phone = ?,
                intercom_contact2_name  = ?,
                intercom_contact2_phone = ?
             WHERE id = ?"
        )->execute([
            trim($_POST['ic1_name']),
            trim($_POST['ic1_phone']),
            trim($_POST['ic2_name']),
            trim($_POST['ic2_phone']),
            $rid
        ]);
        // Refresh session
        $_SESSION['auth_resident']['intercom_contact1_name'] = trim($_POST['ic1_name']);
        $_SESSION['auth_resident']['intercom_contact1_phone'] = trim($_POST['ic1_phone']);
        $_SESSION['auth_resident']['intercom_contact2_name'] = trim($_POST['ic2_name']);
        $_SESSION['auth_resident']['intercom_contact2_phone'] = trim($_POST['ic2_phone']);
        header("Location: resident_portal.php?step=B&saved=1");
        exit;
    }

    // ── Step B: Occupancy ─────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_occupancy'])) {
        $fields = ($rtype === 'tenant')
            ? "num_occupants = ?, rental_agency_or_owner_name = ?, move_in_date = ?"
            : "num_occupants = ?, rental_agency_or_owner_name = ?";
        $vals = ($rtype === 'tenant')
            ? [(int)$_POST['num_occupants'], trim($_POST['rental_agency_or_owner_name']), trim($_POST['move_in_date']) ?: null, $rid]
            : [(int)$_POST['num_occupants'], trim($_POST['rental_agency_or_owner_name']), $rid];
        $pdo->prepare("UPDATE {$table} SET {$fields} WHERE id = ?")->execute($vals);
        header("Location: resident_portal.php?step=C&saved=1");
        exit;
    }

    // ── Step C: Vehicles ──────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle'])) {
        $reg = trim($_POST['registration']);
        $make = trim($_POST['make_model']);
        $col = trim($_POST['color']);
        if ($reg) {
            // Check max vehicles setting
            $max_v = (int)($pdo->query("SELECT setting_value FROM pet_settings WHERE setting_key = 'max_vehicles_per_unit'")->fetchColumn() ?: 2);
            $count_v_stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE unit_id = ?");
            $count_v_stmt->execute([$uid]);
            $vcount = (int)$count_v_stmt->fetchColumn();
            if ($max_v == 0 || $vcount < $max_v) {
                $pdo->prepare("INSERT INTO vehicles (unit_id, resident_type, resident_id, registration, make_model, color) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$uid, $rtype, $rid, $reg, $make, $col]);
            }
        }
        header("Location: resident_portal.php?step=C&saved=1");
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_vehicle'])) {
        $vid = (int)$_POST['vehicle_id'];
        $pdo->prepare("DELETE FROM vehicles WHERE id = ? AND unit_id = ?")->execute([$vid, $uid]);
        header("Location: resident_portal.php?step=C");
        exit;
    }

    // ── Step E: Code of Conduct ───────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_coc'])) {
        $pdo->prepare("UPDATE {$table} SET code_of_conduct_accepted = 1 WHERE id = ?")->execute([$rid]);
        // Check if all required steps are done → mark details_complete
        checkAndMarkComplete($pdo, $rtype, $rid, $uid);
        header("Location: resident_portal.php?step=done&saved=1");
        exit;
    }
}

function checkAndMarkComplete($pdo, $rtype, $rid, $uid)
{
    $table = $rtype === 'owner' ? 'owners' : 'tenants';
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$rid]);
    $r = $stmt->fetch();

    $intercom_done = !empty($r['intercom_contact1_name']) && !empty($r['intercom_contact1_phone']);
    $occupancy_done = !empty($r['num_occupants']);
    $coc_done = (int)$r['code_of_conduct_accepted'] === 1;

    if ($intercom_done && $occupancy_done && $coc_done) {
        $pdo->prepare("UPDATE {$table} SET details_complete = 1 WHERE id = ?")->execute([$rid]);
        // Notify agent
        try {
            $agent_email = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'security_email'")->fetchColumn();
            if ($agent_email) {
                $unit_num = $pdo->query("SELECT unit_number FROM units WHERE id = {$uid}")->fetchColumn();
                send_notification_email($agent_email,
                    "Resident Profile Complete — Unit {$unit_num}",
                    "The resident in Unit {$unit_num} (" . h($r['full_name']) . ") has completed their profile and is ready for final agent review.<br><br>
                     Please log into the Admin Portal → Pending Approvals to review and approve."
                );
            }
        }
        catch (Exception $e) {
        }
    }
}

// ── Load resident data if logged in ──────────────────────────────────────────
$res = null;
$vehicles = [];
$pets = [];
$step_data = [];
if (isset($_SESSION['auth_resident'])) {
    $res = $_SESSION['auth_resident'];
    $rtype = $res['type'];
    $rid = $res['id'];
    $uid = $res['unit_id'];
    $table = ($rtype === 'owner') ? 'owners' : 'tenants';

    // Refresh from DB
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$rid]);
    $step_data = $stmt->fetch();

    // Vehicles
    $vstmt = $pdo->prepare("SELECT * FROM vehicles WHERE unit_id = ? ORDER BY created_at ASC");
    $vstmt->execute([$uid]);
    $vehicles = $vstmt->fetchAll();

    // Pets
    $pstmt = $pdo->prepare("SELECT * FROM pets WHERE unit_id = ? ORDER BY created_at DESC");
    $pstmt->execute([$uid]);
    $pets = $pstmt->fetchAll();

    // Max vehicles
    $max_vehicles = (int)($pdo->query("SELECT setting_value FROM pet_settings WHERE setting_key = 'max_vehicles_per_unit'")->fetchColumn() ?: 2);
    $max_pets = (int)($pdo->query("SELECT setting_value FROM pet_settings WHERE setting_key = 'max_pets_per_unit'")->fetchColumn() ?: 2);

    // Rules PDF
    $rules_pdf = '';
    try {
        $rules_pdf = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'complex_rules_pdf'")->fetchColumn();
    }
    catch (Exception $e) {
    }
}

// Active step from URL
$active_step = $_GET['step'] ?? 'A';
$just_saved = isset($_GET['saved']);

// Compute step completion flags
$step_A_done = !empty($step_data['intercom_contact1_name']) && !empty($step_data['intercom_contact1_phone']);
$step_B_done = !empty($step_data['num_occupants']);
$step_C_done = !empty($vehicles);
$step_D_done = !empty($pets);
$step_E_done = !empty($step_data['code_of_conduct_accepted']);
$all_done = $step_A_done && $step_B_done && $step_E_done; // C & D optional but shown
$portal_access = !empty($step_data['portal_access_granted']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Portal — Villa Tobago</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gray-100 min-h-screen font-sans">

    <?php if (!$res): ?>
    <!-- ══════════════════ LOGIN SCREEN ══════════════════ -->
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-900 to-blue-700 p-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-400 mb-4">
                    <i class="fas fa-building text-blue-900 text-2xl"></i>
                </div>
                <h1 class="text-3xl font-extrabold text-white">Resident Portal</h1>
                <p class="text-blue-200 mt-2">Sign in with your unit number and ID</p>
            </div>
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-3 rounded mb-5 text-sm">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?= h($error)?>
                </div>
                <?php
    endif; ?>
                <form method="POST">
                    <div class="mb-5">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Unit Number</label>
                        <select name="unit_id"
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 focus:border-blue-500 outline-none transition"
                            required>
                            <option value="">— Select Unit —</option>
                            <?php
    $units = $pdo->query("SELECT id, unit_number FROM units ORDER BY CAST(unit_number AS UNSIGNED) ASC, unit_number ASC")->fetchAll();
    foreach ($units as $u)
        echo "<option value='{$u['id']}'>{$u['unit_number']}</option>";
?>
                        </select>
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2">ID Number / Passport</label>
                        <input type="text" name="id_number"
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 focus:border-blue-500 outline-none transition"
                            required placeholder="Enter your ID / Passport number">
                    </div>
                    <button type="submit" name="lookup"
                        class="w-full bg-blue-700 text-white font-bold py-3 rounded-xl hover:bg-blue-800 transition shadow-lg">
                        <i class="fas fa-sign-in-alt mr-2"></i> Access Resident Portal
                    </button>
                </form>
            </div>
            <div class="text-center mt-6">
                <a href="resident_form.php" class="text-blue-200 hover:text-white text-sm font-semibold">
                    <i class="fas fa-user-plus mr-1"></i> New resident? Submit an application
                </a>
            </div>
        </div>
    </div>

    <?php
else: ?>
    <!-- ══════════════════ PORTAL DASHBOARD ══════════════════ -->
    <div class="max-w-5xl mx-auto py-8 px-4">

        <!-- Top Bar -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900">
                    <i class="fas fa-building text-blue-600 mr-2"></i>
                    Resident Portal — Unit
                    <?= h($res['unit_number'])?>
                </h1>
                <p class="text-gray-500 text-sm mt-0.5">Welcome,
                    <?= h($res['full_name'])?> &middot; <span class="capitalize">
                        <?= h($rtype)?>
                    </span>
                </p>
            </div>
            <a href="?action=logout" class="text-red-500 hover:text-red-700 font-bold text-sm flex items-center gap-1">
                <i class="fas fa-sign-out-alt"></i> Sign Out
            </a>
        </div>

        <?php if ($just_saved): ?>
        <div
            class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-5 text-sm flex items-center gap-2">
            <i class="fas fa-check-circle"></i> Changes saved successfully.
        </div>
        <?php
    endif; ?>

        <!-- Application Status Timeline -->
        <div class="bg-white rounded-2xl shadow p-6 mb-6 overflow-x-auto">
            <h2 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-4">Application Status</h2>
            <?php
    $owner_approved = (int)($step_data['owner_approval'] ?? $step_data['agent_approval'] ?? 0);
    $agent_final = (int)($step_data['agent_approved'] ?? 0);
    $move_in_sent = (int)($step_data['move_in_sent'] ?? 0);

    if (!function_exists('status_dot')) {
        function status_dot($done, $label, $sub = '')
        {
            $color = $done ? 'bg-green-500' : 'bg-gray-200';
            $text = $done ? 'text-green-700' : 'text-gray-400';
            $icon = $done ? 'fa-check' : 'fa-circle';
            echo "<div class='flex flex-col items-center text-center min-w-[80px]'>";
            echo "<div class='w-9 h-9 rounded-full {$color} flex items-center justify-center mb-1'><i class='fas {$icon} text-white text-sm'></i></div>";
            echo "<span class='text-xs font-bold {$text}'>{$label}</span>";
            if ($sub)
                echo "<span class='text-xs text-gray-400'>{$sub}</span>";
            echo "</div>";
        }
    }
    if (!function_exists('status_line')) {
        function status_line($done)
        {
            $color = $done ? 'bg-green-400' : 'bg-gray-200';
            echo "<div class='flex-1 h-1 {$color} mt-4 mx-1'></div>";
        }
    }
?>
            <div class="flex items-start">
                <?php status_dot(true, 'Applied'); ?>
                <?php status_line($portal_access); ?>
                <?php status_dot($portal_access, $rtype === 'tenant' ? 'Owner Approved' : 'Verified', 'Portal access granted'); ?>
                <?php status_line($all_done); ?>
                <?php status_dot($all_done, 'Profile Complete', 'All steps done'); ?>
                <?php status_line($agent_final); ?>
                <?php status_dot($agent_final, 'Agent Approved', 'Final review done'); ?>
                <?php status_line($move_in_sent); ?>
                <?php status_dot($move_in_sent, 'Move-in Form', 'Email sent'); ?>
            </div>
        </div>

        <?php if (!$portal_access): ?>
        <!-- Not yet approved -->
        <div class="bg-yellow-50 border-2 border-yellow-200 rounded-2xl p-8 text-center mb-6">
            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-clock text-yellow-500 text-2xl"></i>
            </div>
            <h2 class="text-xl font-bold text-yellow-900 mb-2">Awaiting Approval</h2>
            <p class="text-yellow-700 max-w-md mx-auto">
                <?= $rtype === 'tenant'
            ? 'Your application is pending approval from the unit owner. You will receive an email once access has been granted, and can then complete your profile here.'
            : 'Your application has been submitted and is pending review by the Managing Agent. You will receive an email once portal access has been granted.'?>
            </p>
        </div>

        <?php
    else: ?>
        <!-- Steps Progress Strip -->
        <div class="grid grid-cols-5 gap-2 mb-6">
            <?php
        $steps = [
            'A' => ['label' => 'Intercom', 'icon' => 'fa-phone', 'done' => $step_A_done],
            'B' => ['label' => 'Occupancy', 'icon' => 'fa-users', 'done' => $step_B_done],
            'C' => ['label' => 'Vehicles', 'icon' => 'fa-car', 'done' => $step_C_done, 'optional' => true],
            'D' => ['label' => 'Pets', 'icon' => 'fa-paw', 'done' => $step_D_done, 'optional' => true],
            'E' => ['label' => 'Rules', 'icon' => 'fa-file-alt', 'done' => $step_E_done],
        ];
        foreach ($steps as $key => $s):
            $is_active = $active_step === $key;
            $bg = $s['done'] ? 'bg-green-500 text-white' : ($is_active ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 border-2 border-gray-200');
            $ring = $is_active ? 'ring-2 ring-offset-2 ring-blue-400' : '';
?>
            <a href="?step=<?= $key?>"
                class="flex flex-col items-center py-3 px-1 rounded-xl <?= $bg?> <?= $ring?> hover:opacity-90 transition text-center shadow-sm">
                <i class="fas <?= $s['icon']?> text-lg mb-1"></i>
                <span class="text-xs font-bold leading-tight">
                    <?= $s['label']?>
                </span>
                <?php if (!empty($s['optional'])): ?><span class="text-xs opacity-70">(optional)</span>
                <?php
            endif; ?>
                <?php if ($s['done']): ?><i class="fas fa-check-circle text-xs mt-0.5"></i>
                <?php
            endif; ?>
            </a>
            <?php
        endforeach; ?>
        </div>

        <!-- ── STEP A: Intercom ─────────────────────────────────────────────── -->
        <?php if ($active_step === 'A'): ?>
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-1 flex items-center gap-2"><i
                    class="fas fa-phone text-blue-500"></i> Intercom Access Details</h2>
            <p class="text-gray-500 text-sm mb-5">These names and numbers will be used to set up your intercom system
                access. Please provide up to two people who should receive intercom calls.</p>
            <form method="POST" class="space-y-5">
                <div class="p-4 bg-blue-50 rounded-xl border border-blue-100">
                    <h3 class="font-bold text-blue-900 text-sm mb-3">Contact Person 1 <span
                            class="text-red-500">*</span></h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 text-xs font-bold mb-1">Full Name</label>
                            <input type="text" name="ic1_name" required
                                value="<?= h($step_data['intercom_contact1_name'] ?? '')?>"
                                class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 focus:border-blue-400 outline-none text-sm">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-xs font-bold mb-1">Phone Number for Intercom</label>
                            <input type="text" name="ic1_phone" required
                                value="<?= h($step_data['intercom_contact1_phone'] ?? '')?>"
                                class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 focus:border-blue-400 outline-none text-sm">
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                    <h3 class="font-bold text-gray-700 text-sm mb-3">Contact Person 2 <span
                            class="text-gray-400 font-normal">(optional)</span></h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 text-xs font-bold mb-1">Full Name</label>
                            <input type="text" name="ic2_name"
                                value="<?= h($step_data['intercom_contact2_name'] ?? '')?>"
                                class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 focus:border-gray-300 outline-none text-sm">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-xs font-bold mb-1">Phone Number for Intercom</label>
                            <input type="text" name="ic2_phone"
                                value="<?= h($step_data['intercom_contact2_phone'] ?? '')?>"
                                class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 focus:border-gray-300 outline-none text-sm">
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="submit" name="save_intercom"
                        class="bg-blue-600 text-white font-bold py-2.5 px-8 rounded-xl hover:bg-blue-700 transition shadow">
                        Save & Continue <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- ── STEP B: Occupancy ────────────────────────────────────────────── -->
        <?php
        elseif ($active_step === 'B'): ?>
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-1 flex items-center gap-2"><i
                    class="fas fa-users text-blue-500"></i> Unit Occupancy Details</h2>
            <p class="text-gray-500 text-sm mb-5">Tell us how many people will be living in the unit and provide the
                name of the owner or rental agency.</p>
            <form method="POST" class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Total Number of Residents in Unit
                            <span class="text-red-500">*</span></label>
                        <input type="number" name="num_occupants" min="1" max="20" required
                            value="<?= h($step_data['num_occupants'] ?? '')?>"
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-blue-400 outline-none">
                        <p class="text-xs text-gray-400 mt-1">Include all people living in the unit (adults and
                            children).</p>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Name of Owner or Rental Agency <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="rental_agency_or_owner_name" required
                            value="<?= h($step_data['rental_agency_or_owner_name'] ?? '')?>"
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-blue-400 outline-none"
                            placeholder="e.g. John Smith or ABC Properties">
                    </div>
                    <?php if ($rtype === 'tenant'): ?>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Move-in Date</label>
                        <input type="date" name="move_in_date" value="<?= h($step_data['move_in_date'] ?? '')?>"
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-blue-400 outline-none">
                    </div>
                    <?php
            endif; ?>
                </div>
                <div class="flex justify-between gap-3">
                    <a href="?step=A"
                        class="bg-gray-100 text-gray-600 font-bold py-2.5 px-6 rounded-xl hover:bg-gray-200 transition">
                        <i class="fas fa-arrow-left mr-1"></i> Back
                    </a>
                    <button type="submit" name="save_occupancy"
                        class="bg-blue-600 text-white font-bold py-2.5 px-8 rounded-xl hover:bg-blue-700 transition shadow">
                        Save & Continue <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- ── STEP C: Vehicles ─────────────────────────────────────────────── -->
        <?php
        elseif ($active_step === 'C'): ?>
        <div class="bg-white rounded-2xl shadow p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2"><i
                            class="fas fa-car text-blue-500"></i> Vehicle Registration</h2>
                    <p class="text-gray-500 text-sm mt-0.5">Register vehicles kept on the property permanently.
                        Maximum: <strong>
                            <?= $max_vehicles == 0 ? 'Unlimited' : $max_vehicles?>
                        </strong> vehicle(s).</p>
                </div>
                <span class="bg-gray-100 text-gray-600 text-xs font-bold px-3 py-1 rounded-full">Optional</span>
            </div>

            <!-- Registered Vehicles -->
            <?php if ($vehicles): ?>
            <div class="space-y-3 mb-5">
                <?php foreach ($vehicles as $v): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100">
                    <div>
                        <div class="font-bold text-gray-800">
                            <?= h($v['registration'])?>
                        </div>
                        <div class="text-sm text-gray-500">
                            <?= h($v['make_model'])?> &middot;
                            <?= h($v['color'])?>
                        </div>
                    </div>
                    <form method="POST" onsubmit="return confirm('Remove this vehicle?')">
                        <input type="hidden" name="vehicle_id" value="<?= $v['id']?>">
                        <button type="submit" name="delete_vehicle"
                            class="text-red-400 hover:text-red-600 text-sm font-bold">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
                <?php
                endforeach; ?>
            </div>
            <?php
            endif; ?>

            <!-- Add Vehicle Form -->
            <?php if ($max_vehicles == 0 || count($vehicles) < $max_vehicles): ?>
            <form method="POST" class="bg-blue-50 rounded-xl p-4 border border-blue-100">
                <h3 class="font-bold text-blue-900 text-sm mb-3"><i class="fas fa-plus mr-1"></i> Add a Vehicle</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div><label class="block text-xs font-bold text-gray-600 mb-1">Registration <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="registration" required placeholder="e.g. CA 123 456"
                            class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-400 outline-none">
                    </div>
                    <div><label class="block text-xs font-bold text-gray-600 mb-1">Make & Model</label>
                        <input type="text" name="make_model" placeholder="e.g. Toyota Hilux"
                            class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-400 outline-none">
                    </div>
                    <div><label class="block text-xs font-bold text-gray-600 mb-1">Color</label>
                        <input type="text" name="color" placeholder="e.g. Silver"
                            class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-400 outline-none">
                    </div>
                </div>
                <div class="mt-3 flex justify-end">
                    <button type="submit" name="add_vehicle"
                        class="bg-blue-600 text-white text-sm font-bold py-2 px-5 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-plus mr-1"></i> Add Vehicle
                    </button>
                </div>
            </form>
            <?php
            else: ?>
            <p class="text-red-500 text-sm italic">Maximum vehicle limit reached (
                <?= $max_vehicles?>).
            </p>
            <?php
            endif; ?>

            <div class="flex justify-between gap-3 mt-5">
                <a href="?step=B"
                    class="bg-gray-100 text-gray-600 font-bold py-2.5 px-6 rounded-xl hover:bg-gray-200 transition">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
                <a href="?step=D"
                    class="bg-blue-600 text-white font-bold py-2.5 px-8 rounded-xl hover:bg-blue-700 transition shadow">
                    Continue <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>

        <!-- ── STEP D: Pets ─────────────────────────────────────────────────── -->
        <?php
        elseif ($active_step === 'D'): ?>
        <div class="bg-white rounded-2xl shadow p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2"><i
                            class="fas fa-paw text-yellow-500"></i> Pet Registration</h2>
                    <p class="text-gray-500 text-sm mt-0.5">Register any pets you keep at the unit. Each pet requires
                        Trustee approval.</p>
                </div>
                <span class="bg-gray-100 text-gray-600 text-xs font-bold px-3 py-1 rounded-full">Optional</span>
            </div>

            <?php if ($pets): ?>
            <div class="space-y-3 mb-5">
                <?php foreach ($pets as $p):
                    $sColor = 'bg-gray-100 text-gray-600';
                    if ($p['status'] == 'Approved')
                        $sColor = 'bg-green-100 text-green-700';
                    elseif ($p['status'] == 'Declined')
                        $sColor = 'bg-red-100 text-red-700';
?>
                <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-xl border border-yellow-100">
                    <div>
                        <div class="font-bold text-gray-800">
                            <?= h($p['name'])?> <span class="text-xs <?= $sColor?> px-2 py-0.5 rounded-full ml-1">
                                <?= h($p['status'])?>
                            </span>
                        </div>
                        <div class="text-sm text-gray-500">
                            <?= h($p['type'])?>
                            <?= $p['breed'] ? ' · ' . h($p['breed']) : ''?>
                            <?= $p['adult_size'] ? ' · ' . h($p['adult_size']) : ''?>
                        </div>
                    </div>
                </div>
                <?php
                endforeach; ?>
            </div>
            <?php
            endif; ?>

            <?php if ($max_pets == 0 || count($pets) < $max_pets): ?>
            <a href="pet_form.php"
                class="inline-flex items-center gap-2 bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2.5 px-6 rounded-xl transition shadow">
                <i class="fas fa-plus"></i> Register a Pet
            </a>
            <?php
            else: ?>
            <p class="text-red-500 text-sm italic">Maximum pet limit reached (
                <?= $max_pets?>).
            </p>
            <?php
            endif; ?>

            <div class="flex justify-between gap-3 mt-5">
                <a href="?step=C"
                    class="bg-gray-100 text-gray-600 font-bold py-2.5 px-6 rounded-xl hover:bg-gray-200 transition">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
                <a href="?step=E"
                    class="bg-blue-600 text-white font-bold py-2.5 px-8 rounded-xl hover:bg-blue-700 transition shadow">
                    Continue <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>

        <!-- ── STEP E: Code of Conduct ──────────────────────────────────────── -->
        <?php
        elseif ($active_step === 'E' || $active_step === 'done'): ?>
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-1 flex items-center gap-2"><i
                    class="fas fa-file-contract text-green-500"></i> Code of Conduct</h2>
            <p class="text-gray-500 text-sm mb-5">Please read and accept the Villa Tobago Code of Conduct to complete
                your application.</p>

            <?php if ($step_E_done || $active_step === 'done'): ?>
            <div class="bg-green-50 border-2 border-green-400 rounded-xl p-6 text-center mb-6">
                <i class="fas fa-check-circle text-green-500 text-4xl mb-3 block"></i>
                <p class="font-bold text-green-800 text-lg">Code of Conduct Accepted</p>
                <p class="text-green-700 text-sm mt-1">Your profile is complete. The Managing Agent has been notified
                    and will perform a final review.</p>
            </div>
            <?php
            else: ?>
            <?php if ($rules_pdf): ?>
            <div class="mb-4">
                <a href="<?= SITE_URL?>/<?= h($rules_pdf)?>" target="_blank"
                    class="inline-flex items-center gap-2 bg-gray-50 border-2 border-gray-200 hover:border-blue-400 text-blue-600 font-bold py-2 px-5 rounded-lg transition text-sm">
                    <i class="fas fa-file-pdf text-red-500"></i> Download / View House Rules (PDF)
                </a>
            </div>
            <?php
                endif; ?>
            <form method="POST">
                <label
                    class="flex items-start gap-3 cursor-pointer p-4 bg-green-50 rounded-xl border-2 border-green-100 hover:border-green-400 transition mb-5">
                    <input type="checkbox" name="code_of_conduct" value="1" required
                        class="h-5 w-5 mt-0.5 text-green-600 rounded border-gray-300">
                    <div>
                        <span class="font-bold text-green-900 block">I have read and agree to the Villa Tobago Rules and
                            Code of Conduct</span>
                        <span class="text-green-700 text-sm mt-1 block">I understand the rules of the Body Corporate and
                            agree to abide by them during my residency at Villa Tobago.</span>
                    </div>
                </label>
                <div class="flex justify-between gap-3">
                    <a href="?step=D"
                        class="bg-gray-100 text-gray-600 font-bold py-2.5 px-6 rounded-xl hover:bg-gray-200 transition">
                        <i class="fas fa-arrow-left mr-1"></i> Back
                    </a>
                    <button type="submit" name="accept_coc"
                        class="bg-green-600 text-white font-bold py-2.5 px-8 rounded-xl hover:bg-green-700 transition shadow">
                        <i class="fas fa-check mr-2"></i> Accept & Submit Profile
                    </button>
                </div>
            </form>
            <?php
            endif; ?>
        </div>
        <?php
        endif; ?>

        <?php
    endif; // end portal_access ?>

</div><!-- /max-w-5xl -->
<?php
endif; // end logged in ?>
</body>
</html>