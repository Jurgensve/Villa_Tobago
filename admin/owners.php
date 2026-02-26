<?php
$required_roles = ['admin', 'managing_agent'];
require_once 'includes/header.php';

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';

// Handle Form Submission handled at the top
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_owner'])) {
        $full_name = trim($_POST['full_name']);
        $id_number = trim($_POST['id_number']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $unit_id = $_POST['unit_id'] ?? null;

        if (empty($full_name)) {
            $error = "Full Name is required.";
        }
        else {
            try {
                $pdo->beginTransaction();

                // 1. Create Owner
                $stmt = $pdo->prepare("INSERT INTO owners (full_name, id_number, email, phone) VALUES (?, ?, ?, ?)");
                $stmt->execute([$full_name, $id_number, $email, $phone]);
                $owner_id = $pdo->lastInsertId();

                // 2. Assign to Unit (if selected)
                if ($unit_id) {
                    $is_resident = isset($_POST['is_resident']);

                    // Handle Ownership Replacement (from previous logic)
                    $stmt = $pdo->prepare("UPDATE ownership_history SET is_current = 0, end_date = NOW() WHERE unit_id = ? AND is_current = 1");
                    $stmt->execute([$unit_id]);

                    $stmt = $pdo->prepare("INSERT INTO ownership_history (unit_id, owner_id, start_date, is_current) VALUES (?, ?, NOW(), 1)");
                    $stmt->execute([$unit_id, $owner_id]);

                    // 3. Handle Residency Update
                    if ($is_resident) {
                        // Per plan: Clear pets if resident changes
                        // Check if current resident is different
                        $stmt = $pdo->prepare("SELECT resident_type, resident_id FROM residents WHERE unit_id = ?");
                        $stmt->execute([$unit_id]);
                        $current_r = $stmt->fetch();

                        if (!$current_r || $current_r['resident_type'] !== 'owner' || $current_r['resident_id'] != $owner_id) {
                            $stmt = $pdo->prepare("DELETE FROM pets WHERE unit_id = ?");
                            $stmt->execute([$unit_id]);
                        }

                        $stmt = $pdo->prepare("INSERT INTO residents (unit_id, resident_type, resident_id) 
                                             VALUES (?, 'owner', ?) 
                                             ON DUPLICATE KEY UPDATE resident_type = 'owner', resident_id = VALUES(resident_id)");
                        $stmt->execute([$unit_id, $owner_id]);
                    }
                }

                // 4. Send Welcome Onboarding Email
                if (!empty($email)) {
                    $subject = "Welcome to Villa Tobago – Please Complete Your Profile";
                    $portal_url = SITE_URL . "/resident_portal.php";
                    $body = "<p>Dear " . h($full_name) . ",</p>";
                    $body .= "<p>Welcome to Villa Tobago! You have been successfully added to our system as an owner" . ($unit_id ? " for Unit " . h($pdo->query("SELECT unit_number FROM units WHERE id = " . (int)$unit_id)->fetchColumn()) : "") . ".</p>";
                    $body .= "<p>To ensure we have all your correct details, vehicle registrations, and emergency contacts, please visit our Resident Portal and complete the onboarding form.</p>";
                    $body .= "<p style='margin: 20px 0;'><a href='{$portal_url}' style='background-color:#4F46E5;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;font-weight:bold;'>Go to Resident Portal</a></p>";
                    $body .= "<p>If you have any questions, please contact the managing agent.</p>";
                    $body .= "<p>Warm regards,<br>Villa Tobago Management</p>";

                    send_notification_email($email, $subject, $body);
                }

                $pdo->commit();
                $message = "Owner created successfully.";
                $action = 'list'; // Go back to list
            }
            catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-900">Owners</h1>
    <?php if ($action !== 'add'): ?>
    <a href="owners.php?action=add" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
        <i class="fas fa-plus mr-2"></i> Add Owner
    </a>
    <?php
else: ?>
    <a href="owners.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
        <i class="fas fa-arrow-left mr-2"></i> Back to List
    </a>
    <?php
endif; ?>
</div>

<?php if ($message): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
    <?= h($message)?>
</div>
<?php
endif; ?>

<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
    <?= h($error)?>
</div>
<?php
endif; ?>

<?php if ($action === 'add'): ?>
<?php
    // Fetch units for dropdown
    $units = $pdo->query("SELECT id, unit_number FROM units ORDER BY unit_number ASC")->fetchAll();
?>
<div class="bg-white shadow rounded-lg p-6 max-w-2xl">
    <h2 class="text-xl font-semibold mb-4">Add New Owner</h2>
    <form method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="full_name">Full Name *</label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="full_name" name="full_name" type="text" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="id_number">ID Number</label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="id_number" name="id_number" type="text">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">Email</label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="email" name="email" type="email">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">Phone</label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="phone" name="phone" type="text">
            </div>
            <div class="mb-4 col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="unit_id">Assign to Unit
                    (Optional)</label>
                <select
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="unit_id" name="unit_id">
                    <option value="">-- Select Unit --</option>
                    <?php foreach ($units as $unit): ?>
                    <option value="<?= $unit['id']?>">
                        <?= h($unit['unit_number'])?>
                    </option>
                    <?php
    endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Note: Assigning a unit will automatically remove the previous
                    owner from that unit.</p>
            </div>
            <div class="mb-4 col-span-2">
                <label
                    class="flex items-center space-x-3 cursor-pointer p-3 bg-blue-50 rounded-lg border border-blue-100">
                    <input type="checkbox" name="is_resident" value="1"
                        class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <span class="text-sm font-bold text-blue-800">Owner Resides in Unit (Mark as Current
                        Occupant)</span>
                </label>
                <p class="text-xs text-blue-600 mt-1 ml-8 italic">Checking this will set this owner as the primary
                    resident and clear previous occupant's pets.</p>
            </div>
        </div>
        <div class="mt-6">
            <button
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                type="submit" name="create_owner">
                Create Owner
            </button>
        </div>
    </form>
</div>

<?php
elseif ($action === 'edit'):
    $id = $_GET['id'] ?? null;
    $owner = null;
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM owners WHERE id = ?");
        $stmt->execute([$id]);
        $owner = $stmt->fetch();
    }

    if (!$owner) {
        echo "<div class='bg-red-100 text-red-700 p-4 rounded'>Owner not found.</div>";
    }
    else {
        // Handle Edit Submission
        if (isset($_POST['update_owner'])) {
            $full_name = trim($_POST['full_name']);
            $id_number = trim($_POST['id_number']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $status = $_POST['status'];
            $agent_approval = isset($_POST['agent_approval']) ? 1 : 0;
            $pet_approval = isset($_POST['pet_approval']) ? 1 : 0;
            $portal_access_granted = isset($_POST['portal_access_granted']) ? 1 : 0;

            // === APPROVAL INTERLOCK ===
            // For Owners: status can only be 'Approved' if BOTH agent_approval AND pet_approval are true.
            $interlock_warning = '';
            if ($status === 'Approved' && !($agent_approval && $pet_approval)) {
                $status = 'Pending';
                $interlock_warning = "Status was reset to 'Pending' because both Agent Approval and Pet Approval must be checked before an owner can be Approved.";
            }

            $stmt = $pdo->prepare("UPDATE owners SET full_name=?, id_number=?, email=?, phone=?, status=?, agent_approval=?, pet_approval=?, portal_access_granted=? WHERE id=?");
            $stmt->execute([$full_name, $id_number, $email, $phone, $status, $agent_approval, $pet_approval, $portal_access_granted, $id]);

            // When marked Approved, send the token-gated Move-In Logistics Form link (once only)
            if ($status === 'Approved') {
                $o = $pdo->prepare("SELECT email, full_name, move_in_sent, move_in_token FROM owners WHERE id = ?");
                $o->execute([$id]);
                $res = $o->fetch();

                if ($res && !$res['move_in_sent']) {
                    $move_in_token = $res['move_in_token'] ?: bin2hex(random_bytes(32));
                    $pdo->prepare("UPDATE owners SET move_in_token = ? WHERE id = ?")->execute([$move_in_token, $id]);

                    $move_in_link = SITE_URL . "/move_in_form.php?token=" . $move_in_token;
                    $subject = "Congratulations – Your Owner Application is Approved! | Move-In Form";
                    $body = "<p>Dear " . h($res['full_name']) . ",</p>";
                    $body .= "<p>Your owner resident application for <strong>Villa Tobago</strong> has been <strong>Approved</strong>!</p>";
                    $body .= "<p>Please complete the Move-In Logistics Form so that the security team can be notified of your move-in date:</p>";
                    $body .= "<p><a href='{$move_in_link}' style='background:#2563eb;color:white;padding:10px 20px;border-radius:6px;text-decoration:none;display:inline-block;'>Complete Move-In Form</a></p>";
                    $body .= "<p style='color:#999;font-size:0.85em;'>This link is personal and single-use. Villa Tobago Management.</p>";
                    send_notification_email($res['email'], $subject, $body);
                    $pdo->prepare("UPDATE owners SET move_in_sent = 1 WHERE id = ?")->execute([$id]);
                }
            }

            $redirect_msg = $interlock_warning ?: 'Owner updated successfully.';
            echo "<script>window.location.href = 'owners.php?action=list&msg=" . urlencode($redirect_msg) . "';</script>";

        }
?>
<div class="bg-white shadow rounded-lg p-6 max-w-2xl">
    <h2 class="text-xl font-semibold mb-4">Edit Owner</h2>
    <form method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="full_name">Full Name *</label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="full_name" name="full_name" type="text" value="<?= h($owner['full_name'])?>" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="id_number">ID Number</label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="id_number" name="id_number" type="text" value="<?= h($owner['id_number'])?>">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">Email</label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="email" name="email" type="email" value="<?= h($owner['email'])?>">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">Phone</label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="phone" name="phone" type="text" value="<?= h($owner['phone'])?>">
            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-100">
            <h3 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-4">Resident Application Approval
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4 bg-gray-50 p-6 rounded-lg border border-gray-200">
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Overall Status</label>
                    <select name="status" class="w-full border rounded p-2">
                        <?php foreach (['Pending', 'Information Requested', 'Pending Updated', 'Approved', 'Declined', 'Completed'] as $st): ?>
                        <option value="<?= $st?>" <?=($owner['status'] ?? 'Pending') === $st ? 'selected' : '' ?>>
                            <?= $st?>
                        </option>
                        <?php
        endforeach; ?>
                    </select>
                </div>
                <div class="flex items-center mt-2">
                    <input type="checkbox" name="portal_access_granted" value="1"
                        <?= empty($owner['portal_access_granted']) ? '' : 'checked' ?> class="mr-2 h-5 w-5
                    text-indigo-600">
                    <label class="font-bold text-sm text-gray-700">1. Portal Access Granted</label>
                </div>
                <div class="text-xs text-gray-500 mt-2 flex items-center">
                    (Allows owner to complete profile)
                </div>
                <div class="flex items-center mt-2">
                    <input type="checkbox" name="agent_approval" value="1" <?= empty($owner['agent_approval']) ? ''
            : 'checked' ?> class="mr-2 h-5 w-5 text-blue-600">
                    <label class="font-bold text-sm text-gray-700">2. Agent Final Approval</label>
                </div>
                <div class="flex items-center mt-2">
                    <input type="checkbox" name="pet_approval" value="1" <?= empty($owner['pet_approval']) ? ''
            : 'checked' ?> class="mr-2 h-5 w-5 text-blue-600">
                    <label class="font-bold text-sm text-gray-700">3. Pet Approved</label>
                </div>
            </div>
            <?php if (!empty($owner['move_in_sent'])): ?>
            <div class="mb-4 text-green-600 text-sm font-bold"><i class="fas fa-check-circle"></i> Move-In Form Sent
            </div>
            <?php
        endif; ?>
        </div>
        <div class="mt-6">
            <button
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                type="submit" name="update_owner">
                Update Owner
            </button>
        </div>
    </form>
</div>
<?php
    }?>

<?php
else: ?>

<div class="bg-white shadow overflow-hidden sm:rounded-lg p-4">
    <table id="ownersTable" class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left">Name</th>
                <th class="px-6 py-3 text-left">Unit</th>
                <th class="px-6 py-3 text-left">Contact</th>
                <th class="px-6 py-3 text-left">ID Number</th>
                <th class="px-6 py-3 text-left">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php
    $sql = "SELECT o.*, 
                        GROUP_CONCAT(u.unit_number SEPARATOR ', ') as unit_numbers
                        FROM owners o
                        LEFT JOIN ownership_history oh ON o.id = oh.owner_id AND oh.is_current = 1
                        LEFT JOIN units u ON oh.unit_id = u.id
                        WHERE o.is_active = 1
                        GROUP BY o.id
                        ORDER BY o.full_name ASC";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()):
?>
            <tr>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">
                        <?= h($row['full_name'])?>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <?php if ($row['unit_numbers']): ?>
                    <span
                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                        <?= h($row['unit_numbers'])?>
                    </span>
                    <?php
        else: ?>
                    <span class="text-sm text-gray-400">N/A</span>
                    <?php
        endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div>
                        <?= h($row['email'])?>
                    </div>
                    <div>
                        <?= h($row['phone'])?>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= h($row['id_number'])?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <a href="owners.php?action=edit&id=<?= $row['id']?>"
                        class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                </td>
            </tr>
            <?php
    endwhile; ?>
        </tbody>
    </table>
</div>

<script>
    $(document).ready(function () {
        $('#ownersTable').DataTable({
            "pageLength": 25,
            "order": [[0, "asc"]]
        });
    });
</script>
<?php
endif; ?>

<?php require_once 'includes/footer.php'; ?>