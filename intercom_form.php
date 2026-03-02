<?php
// intercom_form.php
session_start();
require_once 'admin/config/db.php';
require_once 'admin/includes/functions.php';

if (!isset($_SESSION['auth_resident'])) {
    header("Location: resident_portal.php");
    exit;
}

$res = $_SESSION['auth_resident'];
$message = '';
$error = '';
$table = ($res['type'] === 'owner') ? 'owners' : 'tenants';

// Handle Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p_ic1_name = trim($_POST['ic1_name'] ?? '');
    $p_ic1_phone = trim($_POST['ic1_phone'] ?? '');
    $p_ic2_name = trim($_POST['ic2_name'] ?? '');
    $p_ic2_phone = trim($_POST['ic2_phone'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE {$table} SET 
            pending_ic1_name = ?, pending_ic1_phone = ?,
            pending_ic2_name = ?, pending_ic2_phone = ?,
            intercom_update_status = 'Pending'
            WHERE id = ?");
        $stmt->execute([
            $p_ic1_name ?: null, $p_ic1_phone ?: null,
            $p_ic2_name ?: null, $p_ic2_phone ?: null,
            $res['id']
        ]);
        $message = "Your intercom update request has been submitted to the Managing Agent for approval.";
    }
    catch (Exception $e) {
        $error = "Error saving request.";
    }
}

// Fetch Current and Pending Data
$stmt = $pdo->prepare("SELECT 
    intercom_contact1_name, intercom_contact1_phone,
    intercom_contact2_name, intercom_contact2_phone,
    pending_ic1_name, pending_ic1_phone,
    pending_ic2_name, pending_ic2_phone,
    intercom_update_status
    FROM {$table} WHERE id = ?");
$stmt->execute([$res['id']]);
$record = $stmt->fetch();

$is_pending = ($record['intercom_update_status'] === 'Pending');

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intercom Access — Villa Tobago</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gradient-to-br from-slate-100 to-purple-50 min-h-screen font-sans">
    <div class="max-w-3xl mx-auto py-10 px-4">

        <!-- Header -->
        <div class="text-center mb-8">
            <div
                class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-purple-500 mb-3 text-white text-2xl">
                <i class="fas fa-phone-alt"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-900">Intercom Access Request</h1>
            <p class="text-gray-500 mt-1">Manage numbers linked to the main gate for Unit
                <?= h($res['unit_number'])?>
            </p>
        </div>

        <?php if ($message): ?>
        <div
            class="bg-green-50 border-l-4 border-green-500 p-4 rounded-xl mb-6 shadow-sm flex flex-col md:flex-row items-start md:items-center gap-4 justify-between">
            <div class="flex items-center gap-3">
                <i class="fas fa-check-circle text-green-500 text-xl"></i>
                <p class="font-bold text-green-800">
                    <?= h($message)?>
                </p>
            </div>
            <a href="resident_portal.php"
                class="text-sm bg-green-200 hover:bg-green-300 text-green-800 font-bold py-2 px-4 rounded-lg transition whitespace-nowrap">Return
                to Dashboard</a>
        </div>
        <?php
endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Current Active Numbers -->
            <div class="bg-white rounded-2xl shadow p-6 border-t-4 border-blue-400">
                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-broadcast-tower text-blue-500"></i> Active Main Gate Numbers
                </h2>
                <div class="space-y-4">
                    <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1 block">Contact
                            1</span>
                        <?php if ($record['intercom_contact1_phone']): ?>
                        <p class="font-bold text-gray-900"><i class="fas fa-user-circle text-gray-400 mr-1"></i>
                            <?= h($record['intercom_contact1_name'])?>
                        </p>
                        <p class="text-blue-600 font-medium"><i class="fas fa-phone-alt text-gray-400 mr-1 text-sm"></i>
                            <?= h($record['intercom_contact1_phone'])?>
                        </p>
                        <?php
else: ?>
                        <p class="text-gray-400 italic text-sm">Not configured</p>
                        <?php
endif; ?>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1 block">Contact
                            2</span>
                        <?php if ($record['intercom_contact2_phone']): ?>
                        <p class="font-bold text-gray-900"><i class="fas fa-user-circle text-gray-400 mr-1"></i>
                            <?= h($record['intercom_contact2_name'])?>
                        </p>
                        <p class="text-blue-600 font-medium"><i class="fas fa-phone-alt text-gray-400 mr-1 text-sm"></i>
                            <?= h($record['intercom_contact2_phone'])?>
                        </p>
                        <?php
else: ?>
                        <p class="text-gray-400 italic text-sm">Not configured</p>
                        <?php
endif; ?>
                    </div>
                </div>
            </div>

            <!-- Update Request Form -->
            <div class="bg-white rounded-2xl shadow p-6 border-t-4 border-purple-500 relative overflow-hidden">
                <?php if ($is_pending): ?>
                <!-- Pending Overlay -->
                <div
                    class="absolute inset-0 bg-white/90 backdrop-blur-sm z-10 flex flex-col items-center justify-center p-6 text-center">
                    <div
                        class="w-16 h-16 bg-yellow-100 text-yellow-600 rounded-full flex items-center justify-center text-3xl mb-3 shadow-inner">
                        <i class="fas fa-hourglass-half animate-pulse"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Update Pending</h3>
                    <p class="text-sm text-gray-600 mb-4">You have already submitted an update request. The Managing
                        Agent will review it shortly. The gate system normally takes 24 hours to sync updates.</p>

                    <div class="w-full text-left bg-gray-50 p-3 rounded-xl border border-gray-200">
                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Requested Changes:
                        </h4>
                        <?php if ($record['pending_ic1_phone']): ?>
                        <p class="text-sm"><strong>1:</strong>
                            <?= h($record['pending_ic1_name'])?> (
                            <?= h($record['pending_ic1_phone'])?>)
                        </p>
                        <?php
    endif; ?>
                        <?php if ($record['pending_ic2_phone']): ?>
                        <p class="text-sm"><strong>2:</strong>
                            <?= h($record['pending_ic2_name'])?> (
                            <?= h($record['pending_ic2_phone'])?>)
                        </p>
                        <?php
    endif; ?>
                    </div>
                </div>
                <?php
endif; ?>

                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-edit text-purple-500"></i> Request New Numbers
                </h2>
                <p class="text-xs text-gray-500 mb-4 leading-relaxed">Fill out this form to request a change to your
                    main gate intercom numbers. Only valid local mobile or landline numbers are supported.</p>

                <form method="POST" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="block text-gray-700 text-xs font-bold mb-1 uppercase">Contact 1 Name</label>
                            <input type="text" name="ic1_name" value="<?= h($record['intercom_contact1_name'])?>"
                                class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-purple-400 outline-none transition"
                                required>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-gray-700 text-xs font-bold mb-1 uppercase">Contact 1 Phone
                                #</label>
                            <input type="tel" name="ic1_phone" value="<?= h($record['intercom_contact1_phone'])?>"
                                class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-purple-400 outline-none transition"
                                required>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    <div class="flex items-center gap-2 mb-2">
                        <input type="checkbox" id="enable_ic2" class="rounded text-purple-600 border-gray-300"
                            onchange="toggleIC2()">
                        <label for="enable_ic2" class="text-sm font-bold text-gray-700 cursor-pointer">Register a 2nd
                            Contact (Optional)</label>
                    </div>

                    <div id="ic2_group"
                        class="grid grid-cols-2 gap-3 <?= $record['intercom_contact2_phone'] ? '' : 'hidden'?>">
                        <div class="col-span-2">
                            <label class="block text-gray-700 text-xs font-bold mb-1 uppercase">Contact 2 Name</label>
                            <input type="text" name="ic2_name" value="<?= h($record['intercom_contact2_name'])?>"
                                class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-purple-400 outline-none transition"
                                id="ic2_name_input">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-gray-700 text-xs font-bold mb-1 uppercase">Contact 2 Phone
                                #</label>
                            <input type="tel" name="ic2_phone" value="<?= h($record['intercom_contact2_phone'])?>"
                                class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-purple-400 outline-none transition"
                                id="ic2_phone_input">
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 mt-4 rounded-xl shadow transition">Submit
                        Request</button>
                </form>
            </div>
        </div>

        <div class="text-center">
            <a href="resident_portal.php"
                class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-700 font-bold text-sm bg-white border border-gray-200 py-2 px-5 rounded-lg shadow-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <script>
        function toggleIC2() {
            const group = document.getElementById('ic2_group');
            const chk = document.getElementById('enable_ic2');
            const name = document.getElementById('ic2_name_input');
            const val = document.getElementById('ic2_phone_input');

            if (chk.checked) {
                group.classList.remove('hidden');
            } else {
                group.classList.add('hidden');
                name.value = '';
                val.value = '';
            }
        }

        // Auto-check if values exist
        if (document.getElementById('ic2_phone_input').value.trim() !== '') {
            document.getElementById('enable_ic2').checked = true;
            document.getElementById('ic2_group').classList.remove('hidden');
        }
    </script>
</body>

</html>