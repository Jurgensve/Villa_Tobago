<?php
// resident_portal.php
session_start();
require_once 'admin/config/db.php';
require_once 'admin/includes/functions.php';

$message = '';
$error = '';
$resident_type = '';
$resident_record = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['lookup'])) {
        $unit_id = $_POST['unit_id'];
        $id_number = trim($_POST['id_number']);

        // Lookup owner
        $stmtOwner = $pdo->prepare("SELECT o.*, u.unit_number, 'owner' as type 
                                    FROM owners o 
                                    JOIN ownership_history oh ON o.id = oh.owner_id 
                                    JOIN units u ON oh.unit_id = u.id 
                                    WHERE u.id = ? AND o.id_number = ? AND oh.is_current = 1");
        $stmtOwner->execute([$unit_id, $id_number]);
        $resident_record = $stmtOwner->fetch();

        if (!$resident_record) {
            // Lookup tenant
            $stmtTenant = $pdo->prepare("SELECT t.*, u.unit_number, 'tenant' as type 
                                        FROM tenants t 
                                        JOIN units u ON t.unit_id = u.id 
                                        WHERE t.unit_id = ? AND t.id_number = ?");
            $stmtTenant->execute([$unit_id, $id_number]);
            $resident_record = $stmtTenant->fetch();
        }

        if ($resident_record) {
            $resident_type = $resident_record['type'];
            $_SESSION['auth_resident'] = $resident_record;
            header("Location: resident_portal.php?action=dashboard");
            exit;
        }
        else {
            $error = "Validation failed. ID Number and Unit combination not found.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Resident Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen font-sans">
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <?php if (!isset($_SESSION['auth_resident'])): ?>
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">Resident Authentication</h2>
            <?php if ($error): ?>
            <p class="text-red-500 text-sm mb-4 text-center">
                <?= h($error)?>
            </p>
            <?php
    endif; ?>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Select Unit</label>
                    <select name="unit_id" class="w-full border rounded px-3 py-2" required>
                        <option value="">-- Select Unit --</option>
                        <?php
    $units = $pdo->query("SELECT id, unit_number FROM units ORDER BY CAST(unit_number AS UNSIGNED) ASC, unit_number ASC")->fetchAll();
    foreach ($units as $u) {
        echo "<option value='{$u['id']}'>{$u['unit_number']}</option>";
    }
?>
                    </select>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">ID Number / Passport</label>
                    <input type="text" name="id_number" class="w-full border rounded px-3 py-2" required>
                </div>
                <button type="submit" name="lookup"
                    class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">Validate &
                    Access Portal</button>
                <p class="mt-4 text-xs text-center text-gray-500">Only verified residents may access the pet and
                    modification application forms.</p>
            </form>
        </div>
        <?php
else:
    $res = $_SESSION['auth_resident'];
?>
        <div class="bg-white rounded-lg shadow-md p-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Welcome,
                    <?= h($res['full_name'])?> (Unit
                    <?= h($res['unit_number'])?>)
                </h2>
                <a href="logout_resident.php" class="text-red-600 hover:text-red-800 font-bold">Sign Out</a>
            </div>

            <h3 class="text-xl font-semibold mb-4 text-blue-800">Available Services</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Modifications Card -->
                <div class="border rounded-lg p-6 bg-gray-50">
                    <h4 class="font-bold text-lg mb-2"><i class="fas fa-hammer text-gray-600"></i> Modification Request
                    </h4>
                    <p class="text-gray-600 text-sm mb-4">Request approval to perform renovations or modifications to
                        your unit.</p>
                    <a href="modification_form.php"
                        class="inline-block bg-blue-600 text-white px-4 py-2 rounded font-bold hover:bg-blue-700">New
                        Request</a>
                </div>

                <!-- Pet Application Card -->
                <div class="border rounded-lg p-6 bg-gray-50">
                    <h4 class="font-bold text-lg mb-2"><i class="fas fa-paw text-gray-600"></i> Pet Application</h4>
                    <p class="text-gray-600 text-sm mb-4">Register your pets to process your Pet Approval dependencies.
                    </p>
                    <a href="pet_form.php"
                        class="inline-block bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700">New
                        Application</a>
                </div>
            </div>

            <div class="mt-8">
                <h3 class="font-bold text-lg mb-2">Your Approval Status</h3>
                <?php
    $approvalText = "Pending";
    $move_in_sent = false;
    if ($res['type'] === 'tenant') {
        $tInfo = $pdo->prepare("SELECT status, owner_approval, pet_approval, move_in_sent FROM tenants WHERE id=?");
        $tInfo->execute([$res['id']]);
        $t = $tInfo->fetch();
        echo "<ul class='list-disc pl-5 mb-4'>";
        echo "<li>Owner Approval: " . ($t['owner_approval'] ? "<span class='text-green-600'>Approved</span>" : "<span class='text-red-600'>Pending</span>") . "</li>";
        echo "<li>Pet Approval: " . ($t['pet_approval'] ? "<span class='text-green-600'>Approved</span>" : "<span class='text-red-600'>Pending/NA</span>") . "</li>";
        echo "<li>Overall Status: <strong>{$t['status']}</strong></li>";
        echo "</ul>";
        $move_in_sent = $t['move_in_sent'];
    }
    else {
        $oInfo = $pdo->prepare("SELECT status, agent_approval, pet_approval, move_in_sent FROM owners WHERE id=?");
        $oInfo->execute([$res['id']]);
        $o = $oInfo->fetch();
        echo "<ul class='list-disc pl-5 mb-4'>";
        echo "<li>Managing Agent Approval: " . ($o['agent_approval'] ? "<span class='text-green-600'>Approved</span>" : "<span class='text-red-600'>Pending</span>") . "</li>";
        echo "<li>Pet Approval: " . ($o['pet_approval'] ? "<span class='text-green-600'>Approved</span>" : "<span class='text-red-600'>Pending/NA</span>") . "</li>";
        echo "<li>Overall Status: <strong>{$o['status']}</strong></li>";
        echo "</ul>";
        $move_in_sent = $o['move_in_sent'];
    }
?>

                <?php if ($move_in_sent): ?>
                <div class="bg-green-100 p-4 border rounded border-green-400">
                    <strong>Success:</strong> Your Resident Application has been fully approved!
                    <a href="#" class="text-blue-600 underline">Proceed to Move-In Logistics Form</a>
                </div>
                <?php
    endif; ?>
            </div>
        </div>
        <?php
endif; ?>
    </div>
</body>

</html>