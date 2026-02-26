<?php
// move_in_form.php  — Token-gated Move-In Logistics form
// Residents reach this page via a link emailed when their application is Approved.
require_once 'admin/config/db.php';
require_once 'admin/includes/functions.php';

$message = '';
$error = '';
$move_data = null;

// ----- Resolve who is accessing this form via token -----
$token = trim($_GET['token'] ?? '');

if (!$token) {
    $error = "Access denied. This page requires a valid invitation link from your approval email. Please contact the Managing Agent.";
}
else {
    // Try tenant first
    $stmt = $pdo->prepare("SELECT t.id, t.full_name, t.email, t.unit_id, u.unit_number, 'tenant' AS resident_type, t.id AS resident_id
                           FROM tenants t JOIN units u ON t.unit_id = u.id
                           WHERE t.move_in_token = ? AND t.status = 'Approved'
                           LIMIT 1");
    $stmt->execute([$token]);
    $move_data = $stmt->fetch();

    // Then try owner
    if (!$move_data) {
        $stmt = $pdo->prepare("SELECT o.id, o.full_name, o.email, oh.unit_id, u.unit_number, 'owner' AS resident_type, o.id AS resident_id
                               FROM owners o
                               JOIN ownership_history oh ON o.id = oh.owner_id AND oh.is_current = 1
                               JOIN units u ON oh.unit_id = u.id
                               WHERE o.move_in_token = ? AND o.status = 'Approved'
                               LIMIT 1");
        $stmt->execute([$token]);
        $move_data = $stmt->fetch();
    }

    if (!$move_data) {
        $error = "This link is invalid, has already been used, or your application is not yet approved. Please contact the Managing Agent.";
    }
}

// ----- Handle form submission -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $move_data && !$error) {
    $preferred_date = $_POST['move_in_date'] ?? null;
    $truck_reg = trim($_POST['truck_reg'] ?? '');
    $truck_gwm = !empty($_POST['truck_gwm']) ? (int)$_POST['truck_gwm'] : null;
    $moving_company = trim($_POST['moving_company'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    try {
        $pdo->beginTransaction();

        // Insert into move_logistics as Pending (Agent must approve first)
        $stmt = $pdo->prepare(
            "INSERT INTO move_logistics (unit_id, resident_type, resident_id, move_type, preferred_date, truck_reg, truck_gwm, moving_company, notes, status)
             VALUES (?, ?, ?, 'move_in', ?, ?, ?, ?, ?, 'Pending')"
        );
        $stmt->execute([
            $move_data['unit_id'],
            $move_data['resident_type'],
            $move_data['resident_id'],
            $preferred_date,
            $truck_reg,
            $truck_gwm,
            $moving_company,
            $notes
        ]);
        $logistics_id = $pdo->lastInsertId();

        // Clear the token to prevent re-use
        if ($move_data['resident_type'] === 'tenant') {
            $pdo->prepare("UPDATE tenants SET move_in_token = NULL, move_in_sent = 1 WHERE id = ?")->execute([$move_data['resident_id']]);
        }
        else {
            $pdo->prepare("UPDATE owners SET move_in_token = NULL WHERE id = ?")->execute([$move_data['resident_id']]);
        }

        $pdo->commit();
        $message = "Thank you, {$move_data['full_name']}! Your move-in details have been submitted. These details are now pending final review by the Managing Agent. You will receive an email confirmation once approved and security has been notified.";
        $move_data = null; // hide the form

    }
    catch (PDOException $e) {
        $pdo->rollBack();
        $error = "A system error occurred. Please try again or contact the Managing Agent. (" . $e->getMessage() . ")";
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
    <title>Move-In Logistics – Villa Tobago</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gray-100 min-h-screen font-sans flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-white rounded-lg shadow-xl p-8">

        <div class="text-center mb-6">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-4">
                <i class="fas fa-truck-moving text-2xl text-blue-600"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Move-In Logistics</h1>
            <p class="text-gray-500 text-sm mt-1">Villa Tobago Residential Estate</p>
        </div>

        <?php if ($message): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-800 px-6 py-5 rounded shadow text-center mb-4">
            <i class="fas fa-clock text-3xl text-green-500 mb-3 block"></i>
            <strong>
                <?= h($message)?>
            </strong>
        </div>
        <div class="text-center mt-4">
            <a href="index.html" class="text-blue-600 font-bold hover:underline text-sm"><i
                    class="fas fa-home mr-1"></i> Return to Homepage</a>
        </div>

        <?php
elseif ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-800 px-4 py-4 rounded mb-4">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <strong>
                <?= h($error)?>
            </strong>
        </div>
        <div class="text-center mt-4">
            <a href="index.html" class="text-blue-600 hover:underline text-sm">Return to Homepage</a>
        </div>

        <?php
elseif ($move_data): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <p class="text-blue-900 font-bold">Welcome,
                <?= h($move_data['full_name'])?>
            </p>
            <p class="text-blue-700 text-sm">Unit: <strong>
                    <?= h($move_data['unit_number'])?>
                </strong></p>
            <p class="text-blue-600 text-sm mt-2">Please complete the details below so we can coordinate your move-in
                and notify the security team.</p>
        </div>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-1" for="move_in_date">
                    Preferred Move-In Date <span class="text-red-500">*</span>
                </label>
                <input type="date" id="move_in_date" name="move_in_date"
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
                <p class="text-xs text-gray-400 mt-1">This will be shared with the security team at the gate.</p>
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
                        <span><strong>Warning:</strong> Your truck exceeds the max allowed weight. It must park outside
                            the complex to avoid paving damage. Any damage inside will be for the owner's
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
                <label class="block text-gray-700 text-sm font-bold mb-1" for="notes">
                    Special Requests / Notes
                </label>
                <textarea id="notes" name="notes" rows="3"
                    class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                    placeholder="e.g. Extended gate access required, large furniture items, etc."></textarea>
            </div>
            <button type="submit"
                class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-blue-700 transition shadow-md">
                <i class="fas fa-paper-plane mr-2"></i> Submit Move-In Request
            </button>
        </form>
        <?php
endif; ?>
    </div>
</body>

</html>     const maxGwm = <?= $max_gwm ?>;

            if(gwmInput) {
                gwmInput.addEventListener('input', function() {
                    const val = parseInt(this.value, 10);
                    if (!isNaN(val) && val > maxGwm) {
                        gwmWarning.classList.remove('hidden');
                    } else {
                        gwmWarning.classList.add('hidden');
                    }
                });
            }
        });
    </script>
</body>

</html>