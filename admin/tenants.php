<?php
$required_roles = ['admin', 'managing_agent'];
require_once 'includes/header.php';

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_tenant']) || isset($_POST['update_tenant'])) {
        $full_name = trim($_POST['full_name']);
        $unit_id = $_POST['unit_id'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $id_number = $_POST['id_number'];
        $tenant_id = $_POST['tenant_id'] ?? null;

        // Handle File Upload
        $lease_path = $_POST['current_lease_path'] ?? null;
        if (isset($_FILES['lease_agreement']) && $_FILES['lease_agreement']['error'] == 0) {
            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            $filename = $_FILES['lease_agreement']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                // Ensure upload directory exists
                $target_dir = UPLOAD_DIR . 'leases/';
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }

                $new_filename = uniqid() . '.' . $ext;
                $target_file = $target_dir . $new_filename;

                if (move_uploaded_file($_FILES['lease_agreement']['tmp_name'], $target_file)) {
                    $lease_path = 'uploads/leases/' . $new_filename;
                }
                else {
                    $upload_error = error_get_last();
                    $error = "Failed to move uploaded file. " . ($upload_error['message'] ?? '');
                }
            }
            else {
                $error = "Invalid file type. Only PDF and Images allowed.";
            }
        }

        if (!$error) {
            try {
                $pdo->beginTransaction();
                if ($tenant_id) {
                    $stmt = $pdo->prepare("UPDATE tenants SET unit_id = ?, full_name = ?, id_number = ?, email = ?, phone = ?, lease_agreement_path = ? WHERE id = ?");
                    $stmt->execute([$unit_id, $full_name, $id_number, $email, $phone, $lease_path, $tenant_id]);
                    $message = "Tenant updated successfully.";
                }
                else {
                    $stmt = $pdo->prepare("INSERT INTO tenants (unit_id, full_name, id_number, email, phone, lease_agreement_path, start_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$unit_id, $full_name, $id_number, $email, $phone, $lease_path]);
                    $tenant_id = $pdo->lastInsertId();
                    $message = "Tenant added successfully.";
                }

                // Update Residency for the unit
                $stmt = $pdo->prepare("SELECT resident_type, resident_id FROM residents WHERE unit_id = ?");
                $stmt->execute([$unit_id]);
                $current_r = $stmt->fetch();

                if (!$current_r || $current_r['resident_type'] !== 'tenant' || $current_r['resident_id'] != $tenant_id) {
                    $stmt = $pdo->prepare("DELETE FROM pets WHERE unit_id = ?");
                    $stmt->execute([$unit_id]);
                }

                $stmt = $pdo->prepare("INSERT INTO residents (unit_id, resident_type, resident_id) 
                                             VALUES (?, 'tenant', ?) 
                                             ON DUPLICATE KEY UPDATE resident_type = 'tenant', resident_id = VALUES(resident_id)");
                $stmt->execute([$unit_id, $tenant_id]);

                $pdo->commit();
                $action = 'list';
            }
            catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }

    if (isset($_POST['update_approval'])) {
        $tenant_id = $_POST['tenant_id'];
        $status = $_POST['status'];
        $owner_approval = isset($_POST['owner_approval']) ? 1 : 0;
        $pet_approval = isset($_POST['pet_approval']) ? 1 : 0;

        // === APPROVAL INTERLOCK ===
        // For Tenants: status can only be 'Approved' if BOTH owner_approval AND pet_approval are true.
        $interlock_warning = '';
        if ($status === 'Approved' && !($owner_approval && $pet_approval)) {
            $status = 'Pending';
            $interlock_warning = "Status reset to Pending: both Owner Approval and Pet Approval must be ticked to Approve a tenant.";
        }

        $pdo->prepare("UPDATE tenants SET status = ?, owner_approval = ?, pet_approval = ? WHERE id = ?")
            ->execute([$status, $owner_approval, $pet_approval, $tenant_id]);

        // When marked Approved, send the token-gated Move-In Logistics Form link
        if ($status === 'Approved') {
            $t = $pdo->prepare("SELECT email, full_name, move_in_sent, move_in_token FROM tenants WHERE id = ?");
            $t->execute([$tenant_id]);
            $res = $t->fetch();

            if ($res && !$res['move_in_sent']) {
                // Generate a one-time token if not already set
                $move_in_token = $res['move_in_token'] ?: bin2hex(random_bytes(32));
                $pdo->prepare("UPDATE tenants SET move_in_token = ? WHERE id = ?")->execute([$move_in_token, $tenant_id]);

                $move_in_link = SITE_URL . "/move_in_form.php?token=" . $move_in_token;
                $subject = "Congratulations – Your Application is Approved! | Move-In Form";
                $body = "<p>Dear " . h($res['full_name']) . ",</p>";
                $body .= "<p>Your resident application for <strong>Villa Tobago</strong> has been <strong>Approved</strong>!</p>";
                $body .= "<p>Please complete the Move-In Logistics Form so that the security team can be notified of your move-in date:</p>";
                $body .= "<p><a href='{$move_in_link}' style='background:#2563eb;color:white;padding:10px 20px;border-radius:6px;text-decoration:none;display:inline-block;'>Complete Move-In Form</a></p>";
                $body .= "<p style='color:#999;font-size:0.85em;'>This link is personal and single-use. Villa Tobago Management.</p>";
                send_notification_email($res['email'], $subject, $body);
                $pdo->prepare("UPDATE tenants SET move_in_sent = 1 WHERE id = ?")->execute([$tenant_id]);
            }
        }

        $message = $interlock_warning ?: 'Approval status updated.';
        header("Location: tenants.php?action=view&id=" . $tenant_id . "&msg=" . urlencode($message));
        exit;
    }
}
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-900">Tenants</h1>
    <?php if ($action !== 'add'): ?>
    <a href="tenants.php?action=add" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
        <i class="fas fa-plus mr-2"></i> Add Tenant
    </a>
    <?php
else: ?>
    <a href="tenants.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
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

<?php if ($action === 'add' || $action === 'edit'): ?>
<?php
    $units = $pdo->query("SELECT id, unit_number FROM units ORDER BY unit_number ASC")->fetchAll();
    $tenant = ['id' => '', 'unit_id' => $_GET['unit_id'] ?? '', 'full_name' => '', 'id_number' => '', 'email' => '', 'phone' => '', 'lease_agreement_path' => ''];
    if ($action === 'edit' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $fetched = $stmt->fetch();
        if ($fetched)
            $tenant = $fetched;
    }
?>
<div class="bg-white shadow rounded-lg p-6 max-w-2xl">
    <h2 class="text-xl font-semibold mb-4">
        <?= $action === 'edit' ? 'Edit Tenant' : 'Add New Tenant'?>
    </h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="tenant_id" value="<?= h($tenant['id'])?>">
        <input type="hidden" name="current_lease_path" value="<?= h($tenant['lease_agreement_path'])?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="mb-4 md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="unit_id">Unit *</label>
                <select
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="unit_id" name="unit_id" required>
                    <option value="">-- Select Unit --</option>
                    <?php foreach ($units as $unit): ?>
                    <option value="<?= $unit['id']?>" <?=($tenant['unit_id'] == $unit['id']) ? 'selected' : ''?>>
                        <?= h($unit['unit_number'])?>
                    </option>
                    <?php
    endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="full_name">Full Name *</label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="full_name" name="full_name" type="text" value="<?= h($tenant['full_name'])?>" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="id_number">ID Number</label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="id_number" name="id_number" type="text" value="<?= h($tenant['id_number'])?>">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">Email</label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="email" name="email" type="email" value="<?= h($tenant['email'])?>">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">Phone</label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="phone" name="phone" type="text" value="<?= h($tenant['phone'])?>">
            </div>
            <div class="mb-4 md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="lease_agreement">
                    Lease Agreement (PDF/Image)
                    <?php if ($tenant['lease_agreement_path']): ?>
                    <span class="text-xs font-normal text-gray-500 ml-2">(Current:
                        <?= basename($tenant['lease_agreement_path'])?>)
                    </span>
                    <?php
    endif; ?>
                </label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="lease_agreement" name="lease_agreement" type="file" accept=".pdf,.jpg,.jpeg,.png">
            </div>
        </div>
        <div class="mt-6">
            <button
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                type="submit" name="<?= $action === 'edit' ? 'update_tenant' : 'create_tenant'?>">
                <?= $action === 'edit' ? 'Update Tenant' : 'Add Tenant'?>
            </button>
        </div>
    </form>
</div>
<?php
elseif ($action === 'view' && isset($_GET['id'])): ?>
<?php
    $stmt = $pdo->prepare("SELECT t.*, u.unit_number FROM tenants t JOIN units u ON t.unit_id = u.id WHERE t.id = ?");
    $stmt->execute([$_GET['id']]);
    $tenant = $stmt->fetch();

    if (!$tenant) {
        echo "<div class='bg-red-100 p-4 rounded text-red-700'>Tenant not found.</div>";
        require_once 'includes/footer.php';
        exit;
    }
?>
<div class="max-w-4xl mx-auto">
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b flex justify-between items-center">
            <h2 class="text-xl font-bold text-gray-800">Tenant Details</h2>
            <a href="tenants.php?action=edit&id=<?= $tenant['id']?>"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded text-sm transition duration-150 ease-in-out">
                <i class="fas fa-edit mr-2"></i> Edit Tenant
            </a>
        </div>
        <div class="p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Personal Information</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm text-gray-500">Full Name</label>
                            <div class="text-lg font-bold text-gray-900">
                                <?= h($tenant['full_name'])?>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-500">ID Number</label>
                            <div class="text-gray-900">
                                <?= h($tenant['id_number'] ?: 'Not provided')?>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-500">Assigned Unit</label>
                            <div class="text-gray-900 font-semibold border-b-2 border-blue-200 inline-block">
                                <?= h($tenant['unit_number'])?>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Contact Details</h3>
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <i class="fas fa-envelope w-8 text-blue-400"></i>
                            <div>
                                <label class="block text-sm text-gray-500">Email Address</label>
                                <a href="mailto:<?= h($tenant['email'])?>" class="text-blue-600 hover:underline">
                                    <?= h($tenant['email'] ?: 'No email')?>
                                </a>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-phone w-8 text-green-400"></i>
                            <div>
                                <label class="block text-sm text-gray-500">Phone Number</label>
                                <div class="text-gray-900">
                                    <?= h($tenant['phone'] ?: 'No phone')?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Approval Status Section -->
            <div class="mt-8 pt-8 border-t border-gray-100">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Resident Application Approval
                </h3>
                <form method="POST" class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                    <input type="hidden" name="tenant_id" value="<?= h($tenant['id'])?>">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Overall Status</label>
                            <select name="status" class="w-full border rounded p-2">
                                <?php foreach (['Pending', 'Information Requested', 'Pending Updated', 'Approved', 'Declined', 'Completed'] as $st): ?>
                                <option value="<?= $st?>" <?=($tenant['status'] ?? 'Pending' )===$st ? 'selected' : ''
    ?>>
                                    <?= $st?>
                                </option>
                                <?php
    endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-center mt-6">
                            <input type="checkbox" name="owner_approval" value="1" <?=empty($tenant['owner_approval'])
                                ? '' : 'checked'?> class="mr-2 h-5 w-5 text-blue-600">
                            <label class="font-bold text-sm text-gray-700">Owner Approved</label>
                        </div>
                        <div class="flex items-center mt-6">
                            <input type="checkbox" name="pet_approval" value="1" <?=empty($tenant['pet_approval']) ? ''
                                : 'checked'?> class="mr-2 h-5 w-5 text-blue-600">
                            <label class="font-bold text-sm text-gray-700">Pet Approved</label>
                        </div>
                    </div>
                    <button type="submit" name="update_approval"
                        class="bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">Update
                        Approvals</button>
                    <?php if (!empty($tenant['move_in_sent'])): ?>
                    <span class="ml-4 text-green-600 text-sm font-bold"><i class="fas fa-check-circle"></i> Move-In Form
                        Sent</span>
                    <?php
    endif; ?>
                </form>
            </div>

            <div class="mt-8 pt-8 border-t border-gray-100">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Documentation</h3>
                <?php if ($tenant['lease_agreement_path']):
        $full_path = UPLOAD_DIR . str_replace('uploads/', '', $tenant['lease_agreement_path']);
        $exists = file_exists($full_path);
?>
                <div class="bg-gray-50 rounded-lg p-6 border border-gray-200 flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="bg-indigo-100 p-3 rounded-lg mr-4 text-indigo-600">
                            <i class="fas fa-file-contract text-2xl"></i>
                        </div>
                        <div>
                            <div class="font-bold text-gray-900">Lease Agreement</div>
                            <div class="text-xs text-gray-500">
                                <?= h(basename($tenant['lease_agreement_path']))?>
                            </div>
                        </div>
                    </div>
                    <?php if ($exists): ?>
                    <a href="<?= SITE_URL?>/<?= h($tenant['lease_agreement_path'])?>" target="_blank"
                        class="bg-white border border-gray-300 hover:border-indigo-500 hover:text-indigo-600 text-gray-700 font-bold py-2 px-6 rounded transition duration-150">
                        <i class="fas fa-external-link-alt mr-2"></i> View Document
                    </a>
                    <?php
        else: ?>
                    <span class="text-red-500 font-bold text-sm"><i class="fas fa-exclamation-triangle mr-1"></i> File
                        Missing!</span>
                    <?php
        endif; ?>
                </div>
                <?php
    else: ?>
                <div class="text-center py-8 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                    <p class="text-gray-400 italic">No lease agreement uploaded.</p>
                    <a href="tenants.php?action=edit&id=<?= $tenant['id']?>"
                        class="text-blue-600 text-sm font-bold mt-2 inline-block">Upload Lease <i
                            class="fas fa-arrow-up ml-1"></i></a>
                </div>
                <?php
    endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
elseif ($action === 'list'): ?>
<?php
else: ?>
<div class="bg-white shadow overflow-hidden sm:rounded-lg p-4">
    <table id="tenantsTable" class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left">Unit</th>
                <th class="px-6 py-3 text-left">Tenant Name</th>
                <th class="px-6 py-3 text-left">Contact</th>
                <th class="px-6 py-3 text-left">Lease</th>
                <th class="px-6 py-3 text-left">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php
    $sql = "SELECT t.*, u.unit_number 
                        FROM tenants t
                        JOIN units u ON t.unit_id = u.id
                        ORDER BY u.unit_number ASC";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()):
?>
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    <?= h($row['unit_number'])?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= h($row['full_name'])?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= h($row['email'])?><br>
                    <?= h($row['phone'])?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600">
                    <?php if ($row['lease_agreement_path']):
            $full_path = UPLOAD_DIR . str_replace('uploads/', '', $row['lease_agreement_path']);
            $exists = file_exists($full_path);
?>
                    <a href="<?= SITE_URL?>/<?= h($row['lease_agreement_path'])?>" target="_blank"
                        class="hover:underline">
                        <?= $exists ? 'View Lease' : '<span class="text-red-500">File Missing!</span>'?>
                    </a>
                    <?php
        else: ?>
                    <span class="text-gray-400">No File</span>
                    <?php
        endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-3">
                    <a href="tenants.php?action=view&id=<?= $row['id']?>"
                        class="text-green-600 hover:text-green-900">View</a>
                    <a href="tenants.php?action=edit&id=<?= $row['id']?>"
                        class="text-indigo-600 hover:text-indigo-900">Edit</a>
                </td>
            </tr>
            <?php
    endwhile; ?>
        </tbody>
    </table>
</div>

<script>
    $(document).ready(function () {
        $('#tenantsTable').DataTable({
            "pageLength": 25,
            "order": [[0, "asc"]]
        });
    });
</script>
<?php
endif; ?>

<?php require_once 'includes/footer.php'; ?>