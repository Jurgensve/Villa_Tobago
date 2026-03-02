<?php
// move_out_form.php  — Logged-in Move-Out Request form for residents
session_start();
require_once 'admin/config/db.php';
require_once 'admin/includes/functions.php';

if (!isset($_SESSION['auth_resident'])) {
    header("Location: resident_portal.php");
    exit;
}

$res = $_SESSION['auth_resident'];
$unit_id = $res['unit_id'];
$resident_type = $res['type'];
$resident_id = $res['id'];

$message = '';
$error = '';

// ----- Handle form submission -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_moveout'])) {

    $preferred_date = $_POST['move_out_date'] ?? null;
    $truck_reg = trim($_POST['truck_reg'] ?? '');
    $truck_gwm = !empty($_POST['truck_gwm']) ? (int)$_POST['truck_gwm'] : null;
    $moving_company = trim($_POST['moving_company'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$unit_id || !$resident_id || !$resident_type) {
        $error = "Session invalid. Please log out and back in.";
    }
    else {
        try {
            $pdo->beginTransaction();

            // Generate approval token (sent to owner for tenant move-outs)
            $move_out_token = bin2hex(random_bytes(32));

            $stmt = $pdo->prepare(
                "INSERT INTO move_logistics (unit_id, resident_type, resident_id, move_type, preferred_date, truck_reg, truck_gwm, moving_company, notes, status, move_out_token)
                 VALUES (?, ?, ?, 'move_out', ?, ?, ?, ?, ?, 'Pending', ?)"
            );
            $stmt->execute([
                $unit_id, $resident_type, $resident_id,
                $preferred_date, $truck_reg, $truck_gwm, $moving_company, $notes,
                $move_out_token
            ]);
            $logistics_id = $pdo->lastInsertId();

            // If TENANT move-out → send approval request to the unit's Owner first
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
                $message = "Your move-out/in request has been submitted. The unit owner has been notified and must approve it. You will be contacted once a decision has been made.";
            }
            else {
                // OWNER move-out → Goes straight to Agent approval queue
                $message = "Your move-out/in request has been submitted to the Managing Agent for approval. You will receive an email confirmation once approved and security has been notified.";
            }

            $pdo->commit();
        }
        catch (PDOException $e) {
            $pdo->rollBack();
            $error = "A system error occurred. Please try again. (" . $e->getMessage() . ")";
        }
    }
}

// Fetch max truck weight setting
$max_gwm = 3500;
try {
    $gwm_setting = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'max_truck_gwm'")->fetchColumn();
    if (is_numeric($gwm_setting))
        $max_gwm = (int)$gwm_setting;
}
catch (Exception $e) {
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics Request – Villa Tobago</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gray-100 min-h-screen font-sans flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-white rounded-lg shadow-xl p-8">

        <div class="flex items-center gap-4 mb-6">
            <a href="resident_portal.php"
                class="text-blue-500 hover:text-blue-700 bg-blue-50 w-10 h-10 rounded-full flex items-center justify-center">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Move-Out / Move-In Request</h1>
                <p class="text-gray-500 text-sm mt-1">Unit
                    <?= h($res['unit_number'] ?? 'Unknown')?>
                </p>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-800 px-4 py-4 rounded text-center">
            <i class="fas fa-check-circle text-2xl text-green-500 mb-2 block"></i>
            <strong>
                <?= h($message)?>
            </strong>
        </div>
        <div class="text-center mt-6">
            <a href="resident_portal.php" class="text-blue-600 hover:underline text-sm font-bold">Return to
                Dashboard</a>
        </div>
        <?php
else: ?>

        <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-800 px-4 py-3 rounded mb-6 text-sm">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <?= h($error)?>
        </div>
        <?php
    endif; ?>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-800 flex gap-3 items-start">
            <i class="fas fa-info-circle text-blue-500 mt-0.5 text-lg"></i>
            <div>
                <strong>Identity Verified</strong><br>
                You are submitting this form as <em>
                    <?= h($res['full_name'])?>
                </em> (
                <?= ucfirst($resident_type)?>).
            </div>
        </div>

        <form method="POST" id="moveout-form" class="space-y-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-1" for="move_out_date">
                    Preferred Move Date <span class="text-red-500">*</span>
                </label>
                <input type="date" id="move_out_date" name="move_out_date"
                    class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none" required
                    min="<?= date('Y-m-d')?>">
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
                <label class="block text-gray-700 text-sm font-bold mb-1" for="truck_gwm">
                    Gross Vehicle Mass (GWM) of Truck in kg
                </label>
                <input type="number" id="truck_gwm" name="truck_gwm" step="1" min="0"
                    class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                    placeholder="e.g. 2000">
                <p class="text-xs text-gray-500 mt-1">The maximum permitted weight inside the complex is <strong>
                        <?= number_format($max_gwm)?>kg
                    </strong>.</p>

                <div id="gwm_warning" class="hidden mt-2 bg-red-50 border-l-4 border-red-500 p-3 rounded">
                    <p class="text-red-700 text-xs font-bold leading-tight flex items-start gap-2">
                        <i class="fas fa-exclamation-triangle mt-0.5"></i>
                        <span><strong>Warning:</strong> Your truck exceeds the max allowed weight. It must park
                            outside the complex to avoid paving damage. Any damage inside will be for the owner's
                            account.</span>
                    </p>
                </div>
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
                    placeholder="Any special requirements or notes for the move."></textarea>
            </div>
            <button type="submit" name="submit_moveout"
                class="w-full bg-orange-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-orange-700 transition shadow-md mt-4">
                <i class="fas fa-paper-plane mr-2"></i> Submit Logistics Request
            </button>
        </form>

        <?php
endif; ?>

    </div>

    <script>
        // GWM Warning Script
        document.addEventListener('DOMContentLoaded', function () {
            const gwmInput = document.getElementById('truck_gwm');
            const gwmWarning = document.getElementById('gwm_warning');
            const form = document.getElementById('moveout-form');
            const maxGwm = <?= $max_gwm?>;

            if (gwmInput) {
                gwmInput.addEventListener('input', function () {
                    const val = parseInt(this.value, 10);
                    if (!isNaN(val) && val > maxGwm) {
                        gwmWarning.classList.remove('hidden');
                    } else {
                        gwmWarning.classList.add('hidden');
                    }
                });

                if (form) {
                    form.addEventListener('submit', function (e) {
                        const val = parseInt(gwmInput.value, 10);
                        if (!isNaN(val) && val > maxGwm) {
                            e.preventDefault();
                            alert("Your truck exceeds the maximum allowed weight (" + maxGwm + "kg) and cannot enter the complex. Please update your logistics.");
                        }
                    });
                }
            }
        });
    </script>
</body>

</html>