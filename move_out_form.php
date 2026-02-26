<?php
// move_out_form.php  — ID-gated Move-Out Request form for residents
require_once 'admin/config/db.php';
require_once 'admin/includes/functions.php';

$message = '';
$error = '';

// ----- AJAX: ID Validation endpoint -----
// Called by JS to verify Unit + ID before unlocking the form
if (isset($_GET['ajax_verify'])) {
    header('Content-Type: application/json');
    $unit_id = (int)($_POST['unit_id'] ?? 0);
    $id_number = trim($_POST['id_number'] ?? '');

    if (!$unit_id || !$id_number) {
        echo json_encode(['valid' => false, 'message' => 'Please select a unit and enter your ID number.']);
        exit;
    }

    // Check tenants
    $stmt = $pdo->prepare("SELECT id, full_name, 'tenant' AS resident_type FROM tenants
                           WHERE unit_id = ? AND id_number = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$unit_id, $id_number]);
    $resident = $stmt->fetch();

    // Check owners
    if (!$resident) {
        $stmt = $pdo->prepare("SELECT o.id, o.full_name, 'owner' AS resident_type
                               FROM owners o
                               JOIN ownership_history oh ON o.id = oh.owner_id AND oh.is_current = 1
                               WHERE oh.unit_id = ? AND o.id_number = ? AND o.is_active = 1 LIMIT 1");
        $stmt->execute([$unit_id, $id_number]);
        $resident = $stmt->fetch();
    }

    if ($resident) {
        echo json_encode(['valid' => true, 'name' => $resident['full_name'], 'resident_type' => $resident['resident_type'], 'resident_id' => $resident['id']]);
    }
    else {
        echo json_encode(['valid' => false, 'message' => 'We could not verify your identity for this unit. Please check your ID number or contact the Managing Agent.']);
    }
    exit;
}

// ----- Handle form submission -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_moveout'])) {
    $unit_id = (int)($_POST['unit_id'] ?? 0);
    $resident_type = $_POST['resident_type'] ?? '';
    $resident_id = (int)($_POST['resident_id'] ?? 0);
    $preferred_date = $_POST['move_out_date'] ?? null;
    $truck_reg = trim($_POST['truck_reg'] ?? '');
    $moving_company = trim($_POST['moving_company'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Double-check: re-verify the ID before saving (server-side guard)
    $id_number = trim($_POST['id_number'] ?? '');
    $verified = false;

    if ($resident_type === 'tenant') {
        $chk = $pdo->prepare("SELECT id FROM tenants WHERE id = ? AND unit_id = ? AND id_number = ? AND is_active = 1");
        $chk->execute([$resident_id, $unit_id, $id_number]);
        $verified = (bool)$chk->fetch();
    }
    elseif ($resident_type === 'owner') {
        $chk = $pdo->prepare("SELECT o.id FROM owners o JOIN ownership_history oh ON o.id = oh.owner_id AND oh.is_current = 1
                              WHERE o.id = ? AND oh.unit_id = ? AND o.id_number = ? AND o.is_active = 1");
        $chk->execute([$resident_id, $unit_id, $id_number]);
        $verified = (bool)$chk->fetch();
    }

    if (!$verified) {
        $error = "Identity verification failed. Please reload the page and try again.";
    }
    elseif (!$unit_id || !$resident_id || !$resident_type) {
        $error = "Missing required information. Please complete all fields.";
    }
    else {
        try {
            $pdo->beginTransaction();

            // Generate approval token (sent to owner for tenant move-outs)
            $move_out_token = bin2hex(random_bytes(32));

            $stmt = $pdo->prepare(
                "INSERT INTO move_logistics (unit_id, resident_type, resident_id, move_type, preferred_date, truck_reg, moving_company, notes, status, move_out_token)
                 VALUES (?, ?, ?, 'move_out', ?, ?, ?, ?, 'Pending', ?)"
            );
            $stmt->execute([
                $unit_id, $resident_type, $resident_id,
                $preferred_date, $truck_reg, $moving_company, $notes,
                $move_out_token
            ]);
            $logistics_id = $pdo->lastInsertId();

            // If TENANT move-out → send approval request to the unit's Owner
            if ($resident_type === 'tenant') {
                $ownerStmt = $pdo->prepare("SELECT o.full_name, o.email FROM owners o
                                            JOIN ownership_history oh ON o.id = oh.owner_id AND oh.is_current = 1
                                            WHERE oh.unit_id = ? LIMIT 1");
                $ownerStmt->execute([$unit_id]);
                $owner = $ownerStmt->fetch();

                if ($owner && !empty($owner['email'])) {
                    $approval_link = SITE_URL . "/approve_moveout.php?token=" . $move_out_token;
                    $subject = "Action Required: Tenant Move-Out Approval – Villa Tobago";
                    $body = "<p>Dear " . h($owner['full_name']) . ",</p>";
                    $body .= "<p>A tenant in your unit has submitted a move-out request. Please review and approve or decline it.</p>";
                    $body .= "<p><strong>Preferred Move-Out Date:</strong> " . ($preferred_date ? format_date($preferred_date) : 'Not specified') . "</p>";
                    $body .= "<p><a href='{$approval_link}' style='background:#2563eb;color:white;padding:10px 20px;border-radius:6px;text-decoration:none;display:inline-block;'>Review Move-Out Request</a></p>";
                    $body .= "<p style='color:#999;font-size:0.85em;'>Villa Tobago Management</p>";
                    send_notification_email($owner['email'], $subject, $body);
                }
                $message = "Your move-out request has been submitted. The unit owner has been notified and must approve it. You will be contacted once a decision has been made.";
            }
            else {
                // OWNER move-out → no owner step needed, goes straight to Agent approval queue
                $message = "Your move-out request has been submitted to the Managing Agent for approval. You will be notified once it has been processed.";
            }

            $pdo->commit();
        }
        catch (PDOException $e) {
            $pdo->rollBack();
            $error = "A system error occurred. Please try again. (" . $e->getMessage() . ")";
        }
    }
}

// Load all unit numbers for the dropdown
$units = $pdo->query("SELECT id, unit_number FROM units ORDER BY unit_number ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Move-Out Request – Villa Tobago</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gray-100 min-h-screen font-sans flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-white rounded-lg shadow-xl p-8">

        <div class="text-center mb-6">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-orange-100 mb-4">
                <i class="fas fa-sign-out-alt text-2xl text-orange-600"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Move-Out Request</h1>
            <p class="text-gray-500 text-sm mt-1">Villa Tobago Residential Estate</p>
        </div>

        <?php if ($message): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-800 px-4 py-4 rounded text-center">
            <i class="fas fa-check-circle text-2xl text-green-500 mb-2 block"></i>
            <strong>
                <?= h($message)?>
            </strong>
        </div>
        <div class="text-center mt-6">
            <a href="index.html" class="text-blue-600 hover:underline text-sm">Return to Homepage</a>
        </div>
        <?php
else: ?>

        <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-800 px-4 py-3 rounded mb-4 text-sm">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <?= h($error)?>
        </div>
        <?php
    endif; ?>

        <!-- Step 1: ID Verification -->
        <div id="step-verify" class="mb-6">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <p class="text-yellow-800 font-bold text-sm"><i class="fas fa-shield-alt mr-1"></i> Identity
                    Verification Required</p>
                <p class="text-yellow-700 text-xs mt-1">Select your unit and enter your ID number to unlock the form.
                    This information must match our records.</p>
            </div>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Unit Number <span
                            class="text-red-500">*</span></label>
                    <select id="verify_unit_id"
                        class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="">-- Select Your Unit --</option>
                        <?php foreach ($units as $u): ?>
                        <option value="<?= $u['id']?>">
                            <?= h($u['unit_number'])?>
                        </option>
                        <?php
    endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Your ID / Passport Number <span
                            class="text-red-500">*</span></label>
                    <input type="text" id="verify_id_number"
                        class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                        placeholder="Enter your ID number">
                </div>
            </div>
            <div id="verify-error" class="hidden mt-3 text-red-600 text-sm bg-red-50 p-3 rounded"></div>
            <button id="btn-verify"
                class="mt-4 w-full bg-gray-800 text-white font-bold py-3 rounded-lg hover:bg-gray-900 transition flex items-center justify-center">
                <i class="fas fa-lock-open mr-2"></i>
                <span id="btn-verify-text">Verify My Identity</span>
            </button>
        </div>

        <!-- Step 2: Move-Out Form (hidden until verified) -->
        <div id="step-form" class="hidden">
            <div id="verified-banner"
                class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 mb-5 flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3 text-lg"></i>
                <div>
                    <p class="text-green-800 font-bold text-sm">Identity Verified</p>
                    <p id="verified-name" class="text-green-700 text-xs"></p>
                </div>
            </div>

            <form method="POST" id="moveout-form" class="space-y-4">
                <!-- Hidden verified fields populated by JS -->
                <input type="hidden" name="unit_id" id="form_unit_id">
                <input type="hidden" name="id_number" id="form_id_number">
                <input type="hidden" name="resident_type" id="form_resident_type">
                <input type="hidden" name="resident_id" id="form_resident_id">

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1" for="move_out_date">
                        Preferred Move-Out Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="move_out_date" name="move_out_date"
                        class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                        required min="<?= date('Y-m-d')?>">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1" for="truck_reg">
                        Truck / Vehicle Registration Number
                    </label>
                    <input type="text" id="truck_reg" name="truck_reg"
                        class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                        placeholder="e.g. CA 123-456">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1" for="moving_company">
                        Moving Company Name <span class="text-gray-400 font-normal">(if applicable)</span>
                    </label>
                    <input type="text" id="moving_company" name="moving_company"
                        class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                        placeholder="e.g. Master Movers">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3"
                        class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                        placeholder="Any special requirements or notes for the move-out."></textarea>
                </div>
                <button type="submit" name="submit_moveout"
                    class="w-full bg-orange-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-orange-700 transition shadow-md">
                    <i class="fas fa-paper-plane mr-2"></i> Submit Move-Out Request
                </button>
            </form>
        </div>

        <?php
endif; ?>

        <div class="text-center mt-6 pt-4 border-t border-gray-100">
            <a href="index.html" class="text-gray-400 hover:text-gray-600 text-xs">Villa Tobago · Return to Homepage</a>
        </div>
    </div>

    <script>
        document.getElementById('btn-verify').addEventListener('click', function () {
            const unitId = document.getElementById('verify_unit_id').value;
            const idNumber = document.getElementById('verify_id_number').value.trim();
            const btnText = document.getElementById('btn-verify-text');
            const errDiv = document.getElementById('verify-error');

            if (!unitId || !idNumber) {
                errDiv.textContent = 'Please select a unit and enter your ID number.';
                errDiv.classList.remove('hidden');
                return;
            }

            btnText.textContent = 'Verifying…';
            document.getElementById('btn-verify').disabled = true;
            errDiv.classList.add('hidden');

            const formData = new FormData();
            formData.append('unit_id', unitId);
            formData.append('id_number', idNumber);

            fetch('move_out_form.php?ajax_verify=1', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.valid) {
                        // Populate hidden fields
                        document.getElementById('form_unit_id').value = unitId;
                        document.getElementById('form_id_number').value = idNumber;
                        document.getElementById('form_resident_type').value = data.resident_type;
                        document.getElementById('form_resident_id').value = data.resident_id;
                        document.getElementById('verified-name').textContent = 'Welcome, ' + data.name + ' (' + data.resident_type + ')';

                        // Show form, hide verify step
                        document.getElementById('step-verify').classList.add('hidden');
                        document.getElementById('step-form').classList.remove('hidden');
                    } else {
                        errDiv.textContent = data.message;
                        errDiv.classList.remove('hidden');
                        btnText.textContent = 'Verify My Identity';
                        document.getElementById('btn-verify').disabled = false;
                    }
                })
                .catch(() => {
                    errDiv.textContent = 'A network error occurred. Please try again.';
                    errDiv.classList.remove('hidden');
                    btnText.textContent = 'Verify My Identity';
                    document.getElementById('btn-verify').disabled = false;
                });
        });
    </script>
</body>

</html>