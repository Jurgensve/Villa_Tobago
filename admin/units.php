<?php
$required_roles = ['admin', 'managing_agent'];
require_once 'includes/header.php';

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_unit'])) {
        $unit_number = trim($_POST['unit_number']);
        $owner_name = trim($_POST['full_name']);
        $owner_id_num = trim($_POST['id_number']);
        $owner_email = trim($_POST['email']);
        $owner_phone = trim($_POST['phone']);
        $has_tenant = isset($_POST['has_tenant']);

        if (!empty($unit_number) && !empty($owner_name)) {
            try {
                $pdo->beginTransaction();

                // 1. Create Unit
                $stmt = $pdo->prepare("INSERT INTO units (unit_number) VALUES (?)");
                $stmt->execute([$unit_number]);
                $unit_id = $pdo->lastInsertId();

                // 2. Determine Owner
                $owner_id = $_POST['owner_id'] ?? null;
                if (!$owner_id && !empty($owner_name)) {
                    // Create New Owner
                    $stmt = $pdo->prepare("INSERT INTO owners (full_name, id_number, email, phone) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$owner_name, $owner_id_num, $owner_email, $owner_phone]);
                    $owner_id = $pdo->lastInsertId();
                }

                // 3. Link them in history
                if ($owner_id) {
                    $stmt = $pdo->prepare("INSERT INTO ownership_history (unit_id, owner_id, start_date, is_current) VALUES (?, ?, NOW(), 1)");
                    $stmt->execute([$unit_id, $owner_id]);

                    // Send Welcome Onboarding Email if it's a NEW owner
                    if (isset($owner_email) && !empty($owner_email) && empty($_POST['owner_id'])) {
                        $subject = "Welcome to Villa Tobago – Please Complete Your Profile";
                        $portal_url = SITE_URL . "/resident_portal.php";
                        $body = "<p>Dear " . h($owner_name) . ",</p>";
                        $body .= "<p>Welcome to Villa Tobago! You have been successfully added to our system as the owner for Unit " . h($unit_number) . ".</p>";
                        $body .= "<p>To ensure we have all your correct details, vehicle registrations, and emergency contacts, please visit our Resident Portal and complete the onboarding form.</p>";
                        $body .= "<p style='margin: 20px 0;'><a href='{$portal_url}' style='background-color:#4F46E5;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;font-weight:bold;'>Go to Resident Portal</a></p>";
                        $body .= "<p>If you have any questions, please contact the managing agent.</p>";
                        $body .= "<p>Warm regards,<br>Villa Tobago Management</p>";

                        send_notification_email($owner_email, $subject, $body);
                    }
                }

                $pdo->commit();

                if ($has_tenant) {
                    header("Location: tenants.php?action=add&unit_id=" . $unit_id);
                    exit;
                }

                $message = "Unit '$unit_number' and Owner '$owner_name' added successfully.";
                $action = 'list';
            }
            catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error: " . $e->getMessage();
            }
        }
        else {
            $error = "Unit Number and Owner Name are required.";
        }
    }
    // Handle Manage Owners
    if (isset($_POST['assign_owner'])) {
        $unit_id = $_POST['unit_id'];
        $owner_id = $_POST['owner_id'] ?? null;
        $full_name = trim($_POST['full_name']);
        $replacement_type = $_POST['replacement_type'] ?? 'replace'; // 'replace' or 'add'

        try {
            $pdo->beginTransaction();

            // If no existing owner selected, create new
            if (!$owner_id && !empty($full_name)) {
                $stmt = $pdo->prepare("INSERT INTO owners (full_name, id_number, email, phone) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $full_name,
                    trim($_POST['id_number']),
                    trim($_POST['email']),
                    trim($_POST['phone'])
                ]);
                $owner_id = $pdo->lastInsertId();
            }

            if ($owner_id) {
                // If replacing, end current ownerships
                if ($replacement_type === 'replace') {
                    $stmt = $pdo->prepare("UPDATE ownership_history SET is_current = 0, end_date = NOW() WHERE unit_id = ? AND is_current = 1");
                    $stmt->execute([$unit_id]);
                }

                // Check if this owner is already linked to this unit
                $stmt = $pdo->prepare("SELECT id FROM ownership_history WHERE unit_id = ? AND owner_id = ? AND is_current = 1");
                $stmt->execute([$unit_id, $owner_id]);
                if (!$stmt->fetch()) {
                    // Link owner
                    $stmt = $pdo->prepare("INSERT INTO ownership_history (unit_id, owner_id, start_date, is_current) VALUES (?, ?, NOW(), 1)");
                    $stmt->execute([$unit_id, $owner_id]);
                    $message = "Ownership updated successfully.";

                    // Send Welcome Onboarding Email if it's a NEW owner we just created
                    $assigned_email = trim($_POST['email'] ?? '');
                    if (empty($_POST['owner_id']) && !empty($assigned_email) && !empty($full_name)) {
                        $unit_number = $pdo->query("SELECT unit_number FROM units WHERE id = " . (int)$unit_id)->fetchColumn();
                        $subject = "Welcome to Villa Tobago – Please Complete Your Profile";
                        $portal_url = SITE_URL . "/resident_portal.php";
                        $body = "<p>Dear " . h($full_name) . ",</p>";
                        $body .= "<p>Welcome to Villa Tobago! You have been successfully added to our system as the owner for Unit " . h($unit_number) . ".</p>";
                        $body .= "<p>To ensure we have all your correct details, vehicle registrations, and emergency contacts, please visit our Resident Portal and complete the onboarding form.</p>";
                        $body .= "<p style='margin: 20px 0;'><a href='{$portal_url}' style='background-color:#4F46E5;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;font-weight:bold;'>Go to Resident Portal</a></p>";
                        $body .= "<p>If you have any questions, please contact the managing agent.</p>";
                        $body .= "<p>Warm regards,<br>Villa Tobago Management</p>";

                        send_notification_email($assigned_email, $subject, $body);
                    }
                }
                else {
                    $error = "This person is already a current owner of this unit.";
                }
            }
            else {
                $error = "Please select an existing owner or enter details for a new one.";
            }

            $pdo->commit();
            if ($message) {
                header("Location: units.php?action=view&id=" . $unit_id . "&msg=ownership_updated");
                exit;
            }
        }
        catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-900">Units</h1>
    <?php if ($action === 'view' && isset($_GET['id'])): ?>
    <div class="space-x-2">
        <a href="modifications.php?action=add&unit_id=<?= $_GET['id']?>"
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-hammer mr-2"></i> Log Modification
        </a>
        <a href="units.php?action=edit&id=<?= $_GET['id']?>"
            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-edit mr-2"></i> Edit Unit
        </a>
        <a href="units.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-arrow-left mr-2"></i> Back to List
        </a>
    </div>
    <?php
elseif ($action === 'add' || $action === 'edit'): ?>
    <a href="units.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
        <i class="fas fa-arrow-left mr-2"></i> Back to List
    </a>
    <?php
else: ?>
    <a href="units.php?action=add" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
        <i class="fas fa-plus mr-2"></i> Add Unit
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
<div class="bg-white shadow rounded-lg p-6 max-w-2xl">
    <h2 class="text-xl font-semibold mb-6">Add New Unit & Owner</h2>
    <form method="POST" class="space-y-6">
        <div class="bg-blue-50 p-4 rounded-md mb-6">
            <h3 class="text-blue-800 font-bold mb-3 border-b border-blue-200 pb-2">Step 1: Unit Details</h3>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="unit_number">
                    Unit Number *
                </label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="unit_number" name="unit_number" type="text" placeholder="e.g. Unit 42" required>
            </div>
        </div>

        <div class="bg-gray-50 p-4 rounded-md mb-6">
            <h3 class="text-gray-800 font-bold mb-3 border-b border-gray-200 pb-2">Step 2: Owner Details</h3>
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
            </div>
        </div>

        <div class="flex items-center mb-6 bg-yellow-50 p-4 rounded-md">
            <input id="has_tenant" name="has_tenant" type="checkbox"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
            <label for="has_tenant" class="ml-3 block text-sm font-bold text-gray-700">
                There is a tenant in place (Continue to Tenant Details after save)
            </label>
        </div>

        <div class="flex items-center justify-end">
            <button
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded focus:outline-none focus:shadow-outline w-full md:w-auto"
                type="submit" name="create_unit">
                Save Unit & Owner
            </button>
        </div>
    </form>
</div>
<?php
elseif ($action === 'view' && isset($_GET['id'])): ?>
<?php
    $id = $_GET['id'];

    // 1. Fetch Unit Details
    $sql = "SELECT * FROM units WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $unit = $stmt->fetch();

    if (!$unit) {
        echo "<div class='bg-red-100 p-4 rounded text-red-700'>Unit not found.</div>";
        require_once 'includes/footer.php';
        exit;
    }

    // 2. Fetch Current Owners
    $sql = "SELECT o.* FROM owners o JOIN ownership_history oh ON o.id = oh.owner_id WHERE oh.unit_id = ? AND oh.is_current = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $current_owners = $stmt->fetchAll();

    // 3. Fetch Active Tenant
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE unit_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$id]);
    $tenant = $stmt->fetch();

    // 4. Fetch Current Resident (graceful if migration not yet run)
    $resident = null;
    $pets = [];
    $vehicles = [];
    $pet_enabled = '1';
    $max_pets = 2;
    $allowed_types = ['Dog', 'Cat', 'Bird', 'Fish'];
    $resident_detail = null; // full row from owners or tenants
    try {
        $stmt = $pdo->prepare("SELECT r.*,
            CASE WHEN r.resident_type = 'owner' THEN o.full_name ELSE t.full_name END AS resident_name,
            CASE WHEN r.resident_type = 'owner' THEN o.email ELSE t.email END AS resident_email,
            CASE WHEN r.resident_type = 'owner' THEN o.phone ELSE t.phone END AS resident_phone
            FROM residents r
            LEFT JOIN owners o ON r.resident_type = 'owner' AND r.resident_id = o.id
            LEFT JOIN tenants t ON r.resident_type = 'tenant' AND r.resident_id = t.id
            WHERE r.unit_id = ?");
        $stmt->execute([$id]);
        $resident = $stmt->fetch();

        // 5. Default residency: if no explicit resident, owners are occupants (when no tenant)
        if (!$resident && !$tenant && !empty($current_owners)) {
            $first_owner = $current_owners[0];
            $resident = [
                'resident_type' => 'owner',
                'resident_id' => $first_owner['id'],
                'resident_name' => $first_owner['full_name'],
                'resident_email' => $first_owner['email'],
                'resident_phone' => $first_owner['phone'],
                'id' => null,
                '_default' => true,
            ];
        }

        // Fetch full detail row from owners or tenants table
        if ($resident && !empty($resident['resident_id'])) {
            $detail_table = $resident['resident_type'] === 'owner' ? 'owners' : 'tenants';
            $dstmt = $pdo->prepare("SELECT * FROM {$detail_table} WHERE id = ?");
            $dstmt->execute([$resident['resident_id']]);
            $resident_detail = $dstmt->fetch();
        }

        // 6. Fetch Pets
        $stmt = $pdo->prepare("SELECT * FROM pets WHERE unit_id = ? ORDER BY created_at DESC");
        $stmt->execute([$id]);
        $pets = $stmt->fetchAll();

        // 7. Fetch Vehicles
        $vstmt = $pdo->prepare("SELECT * FROM vehicles WHERE unit_id = ? ORDER BY created_at ASC");
        $vstmt->execute([$id]);
        $vehicles = $vstmt->fetchAll();

        // 8. Fetch Pet Settings
        $pet_settings = $pdo->query("SELECT setting_key, setting_value FROM pet_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $max_pets = $pet_settings['max_pets_per_unit'] ?? 2;
        $pet_enabled = $pet_settings['pet_management_enabled'] ?? '1';
        $allowed_types = array_map('trim', explode(',', $pet_settings['allowed_pet_types'] ?? 'Dog, Cat, Bird, Fish'));
    }
    catch (PDOException $e) {
        $pet_enabled = '0';
    }

    // 9. Fetch Modifications
    $stmt = $pdo->prepare("SELECT * FROM modifications WHERE unit_id = ? ORDER BY request_date DESC");
    $stmt->execute([$id]);
    $modifications = $stmt->fetchAll();
    // Rules PDF for CoC link
    $rules_pdf = '';
    try {
        $rules_pdf = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'complex_rules_pdf'")->fetchColumn();
    }
    catch (Exception $e) {
    }
?>
<div class="max-w-6xl mx-auto space-y-6">

    <!-- ── Row 1: Unit Info + Owner ───────────────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Unit & Owner Card -->
        <div class="bg-white shadow rounded-xl overflow-hidden border-t-4 border-blue-500">
            <div class="px-5 py-4 bg-gray-50 border-b flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-building text-blue-500"></i> Unit
                    <?= h($unit['unit_number'])?>
                </h3>
                <span class="text-xs text-gray-400">#
                    <?= $unit['id']?>
                </span>
            </div>
            <div class="p-5">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Owner(s)</p>
                <?php if (!empty($current_owners)): ?>
                <div class="space-y-3">
                    <?php foreach ($current_owners as $idx => $owner): ?>
                    <div class="<?= $idx > 0 ? 'pt-3 border-t border-gray-100' : ''?>">
                        <div class="flex justify-between items-start">
                            <div class="font-bold text-gray-900">
                                <?= h($owner['full_name'])?>
                            </div>
                            <a href="owners.php?action=edit&id=<?= $owner['id']?>"
                                class="text-indigo-500 hover:text-indigo-700 text-xs">Edit</a>
                        </div>
                        <div class="mt-1 space-y-0.5 text-sm text-gray-500">
                            <?php if ($owner['id_number']): ?>
                            <div><i class="fas fa-id-card w-5 text-gray-300"></i>
                                <?= h($owner['id_number'])?>
                            </div>
                            <?php
            endif; ?>
                            <?php if ($owner['email']): ?>
                            <div><i class="fas fa-envelope w-5 text-gray-300"></i> <a
                                    href="mailto:<?= h($owner['email'])?>" class="hover:text-indigo-600">
                                    <?= h($owner['email'])?>
                                </a></div>
                            <?php
            endif; ?>
                            <?php if ($owner['phone']): ?>
                            <div><i class="fas fa-phone w-5 text-gray-300"></i>
                                <?= h($owner['phone'])?>
                            </div>
                            <?php
            endif; ?>
                        </div>
                    </div>
                    <?php
        endforeach; ?>
                </div>
                <?php
    else: ?>
                <p class="text-red-400 italic text-sm">No owner assigned.</p>
                <?php
    endif; ?>
                <div class="mt-4 pt-4 border-t border-dashed border-gray-100">
                    <a href="units.php?action=manage_owners&id=<?= $id?>"
                        class="inline-flex items-center justify-center w-full bg-indigo-50 text-indigo-700 font-bold py-2 px-4 rounded-lg hover:bg-indigo-100 transition text-sm">
                        <i class="fas fa-users-cog mr-2"></i> Manage Owners
                    </a>
                </div>
            </div>
        </div>

        <!-- Resident Summary Card -->
        <?php
    $rtype_label = 'No Resident';
    $rtype_class = 'bg-gray-100 text-gray-600';
    $rborder = 'border-gray-300';
    if ($tenant) {
        $rtype_label = 'Tenant';
        $rtype_class = 'bg-green-100 text-green-800';
        $rborder = 'border-green-500';
    }
    elseif ($resident && empty($resident['_default'])) {
        $rtype_label = 'Owner (Residing)';
        $rtype_class = 'bg-purple-100 text-purple-800';
        $rborder = 'border-purple-500';
    }
    elseif (!empty($current_owners)) {
        $rtype_label = 'Owner';
        $rtype_class = 'bg-blue-100 text-blue-700';
        $rborder = 'border-blue-300';
    }
    $r_name = $resident['resident_name'] ?? ($current_owners[0]['full_name'] ?? '—');
    $r_email = $resident['resident_email'] ?? ($current_owners[0]['email'] ?? '—');
    $r_phone = $resident['resident_phone'] ?? ($current_owners[0]['phone'] ?? '—');
?>
        <div class="bg-white shadow rounded-xl overflow-hidden border-t-4 <?= $rborder?> lg:col-span-2">
            <div class="px-5 py-4 bg-gray-50 border-b flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-home text-gray-500"></i> Resident
                </h3>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?= $rtype_class?>">
                    <?= $rtype_label?>
                </span>
            </div>
            <div class="p-5">
                <?php if ($resident || !empty($current_owners)): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-xs text-gray-400 font-bold uppercase">Name</span>
                        <div class="font-bold text-gray-900 mt-0.5">
                            <?= h($r_name)?>
                        </div>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 font-bold uppercase">Email</span>
                        <div class="text-gray-700 mt-0.5">
                            <?= $r_email ? '<a href="mailto:' . h($r_email) . '" class="hover:text-blue-600">' . h($r_email) . '</a>' : '—'?>
                        </div>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 font-bold uppercase">Phone</span>
                        <div class="text-gray-700 mt-0.5">
                            <?= h($r_phone) ?: '—'?>
                        </div>
                    </div>
                    <?php if ($resident_detail): ?>
                    <?php if (!empty($resident_detail['id_number'])): ?>
                    <div>
                        <span class="text-xs text-gray-400 font-bold uppercase">RSA ID / Passport</span>
                        <div class="text-gray-700 mt-0.5">
                            <?= h($resident_detail['id_number'])?>
                        </div>
                    </div>
                    <?php
            endif; ?>
                    <?php if (!empty($resident_detail['num_occupants'])): ?>
                    <div>
                        <span class="text-xs text-gray-400 font-bold uppercase">Occupants</span>
                        <div class="text-gray-700 mt-0.5">
                            <?= h($resident_detail['num_occupants'])?> person(s)
                        </div>
                    </div>
                    <?php
            endif; ?>
                    <?php if (!empty($resident_detail['rental_agency_or_owner_name'])): ?>
                    <div>
                        <span class="text-xs text-gray-400 font-bold uppercase">Owner / Agency</span>
                        <div class="text-gray-700 mt-0.5">
                            <?= h($resident_detail['rental_agency_or_owner_name'])?>
                        </div>
                    </div>
                    <?php
            endif; ?>
                    <?php if (!empty($resident_detail['move_in_date'])): ?>
                    <div>
                        <span class="text-xs text-gray-400 font-bold uppercase">Move-in Date</span>
                        <div class="text-gray-700 mt-0.5">
                            <?= format_date($resident_detail['move_in_date'])?>
                        </div>
                    </div>
                    <?php
            endif; ?>
                    <?php
        endif; ?>
                </div>

                <!-- Application Status Badges -->
                <?php if ($resident_detail): ?>
                <div class="mt-4 flex flex-wrap gap-2">
                    <?php
            if (!function_exists('res_badge')) {
                function res_badge($val, $label)
                {
                    $cls = $val ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500';
                    $icon = $val ? 'fa-check' : 'fa-clock';
                    echo "<span class='inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold {$cls}'><i class='fas {$icon}'></i>{$label}</span>";
                }
            }
            $oa = ($resident['resident_type'] ?? '') === 'tenant' ? ($resident_detail['owner_approval'] ?? 0) : ($resident_detail['agent_approval'] ?? 0);
            res_badge($oa, ($resident['resident_type'] ?? '') === 'tenant' ? 'Owner Approved' : 'Agent Verified');
            res_badge($resident_detail['portal_access_granted'] ?? 0, 'Portal Access');
            res_badge($resident_detail['details_complete'] ?? 0, 'Profile Complete');
            res_badge($resident_detail['agent_approved'] ?? 0, 'Final Approved');
            res_badge($resident_detail['code_of_conduct_accepted'] ?? 0, 'CoC Accepted');
            res_badge($resident_detail['move_in_sent'] ?? 0, 'Move-in Sent');
?>
                </div>
                <?php
        endif; ?>

                <?php if ($tenant && !empty($tenant['lease_agreement_path'])): ?>
                <div class="mt-3">
                    <a href="<?= SITE_URL?>/<?= h($tenant['lease_agreement_path'])?>" target="_blank"
                        class="inline-flex items-center text-blue-600 hover:text-blue-800 font-semibold text-sm">
                        <i class="fas fa-file-contract mr-2"></i> View Lease Agreement
                    </a>
                </div>
                <?php
        endif; ?>

                <?php
    else: ?>
                <p class="text-gray-400 italic text-sm mb-3">No resident assigned yet.</p>
                <?php
    endif; ?>

                <div class="mt-4 pt-3 border-t border-dashed border-gray-100 flex gap-2">
                    <a href="tenants.php?action=add&unit_id=<?= $id?>"
                        class="inline-flex items-center bg-green-50 text-green-700 font-bold py-1.5 px-4 rounded-lg hover:bg-green-100 text-sm">
                        <i class="fas fa-plus mr-1"></i> Add Tenant
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Row 2: Intercom + Vehicles + Pets ──────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Intercom Card -->
        <div class="bg-white shadow rounded-xl overflow-hidden border-t-4 border-blue-400">
            <div class="px-5 py-4 bg-gray-50 border-b">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2"><i
                        class="fas fa-phone text-blue-400"></i> Intercom Access</h3>
            </div>
            <div class="p-5 space-y-4">
                <?php if ($resident_detail && (!empty($resident_detail['intercom_contact1_name']) || !empty($resident_detail['intercom_contact2_name']))): ?>
                <?php
        $contacts = [
            ['name' => $resident_detail['intercom_contact1_name'] ?? '', 'phone' => $resident_detail['intercom_contact1_phone'] ?? ''],
            ['name' => $resident_detail['intercom_contact2_name'] ?? '', 'phone' => $resident_detail['intercom_contact2_phone'] ?? ''],
        ];
        foreach ($contacts as $ci => $c):
            if (empty($c['name']))
                continue;
?>
                <div class="p-3 bg-blue-50 rounded-lg">
                    <div class="text-xs text-blue-500 font-bold mb-1">Contact
                        <?= $ci + 1?>
                    </div>
                    <div class="font-bold text-gray-900 text-sm">
                        <?= h($c['name'])?>
                    </div>
                    <div class="text-gray-600 text-sm">
                        <?= h($c['phone'])?>
                    </div>
                </div>
                <?php
        endforeach; ?>
                <?php
    else: ?>
                <p class="text-gray-400 italic text-sm">Not yet provided.</p>
                <?php
    endif; ?>
            </div>
        </div>

        <!-- Vehicles Card -->
        <div class="bg-white shadow rounded-xl overflow-hidden border-t-4 border-indigo-400">
            <div class="px-5 py-4 bg-gray-50 border-b flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2"><i
                        class="fas fa-car text-indigo-400"></i> Vehicles</h3>
                <span class="text-xs text-gray-500">
                    <?= count($vehicles)?> registered
                </span>
            </div>
            <div class="p-5">
                <?php if (!empty($vehicles)): ?>
                <div class="space-y-3">
                    <?php foreach ($vehicles as $v): ?>
                    <div class="p-3 bg-indigo-50 rounded-lg">
                        <div class="font-bold text-indigo-900 text-sm">
                            <?= h($v['registration'])?>
                        </div>
                        <div class="text-indigo-700 text-xs">
                            <?= h($v['make_model'])?> &middot;
                            <?= h($v['color'])?>
                        </div>
                    </div>
                    <?php
        endforeach; ?>
                </div>
                <?php
    else: ?>
                <p class="text-gray-400 italic text-sm">No vehicles registered.</p>
                <?php
    endif; ?>
            </div>
        </div>

        <!-- Pets Card -->
        <?php if ($pet_enabled): ?>
        <div class="bg-white shadow rounded-xl overflow-hidden border-t-4 border-yellow-400">
            <div class="px-5 py-4 bg-gray-50 border-b flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2"><i
                        class="fas fa-paw text-yellow-500"></i> Pets</h3>
                <span class="text-xs text-gray-500">Max:
                    <?= $max_pets == 0 ? 'Unlimited' : $max_pets?>
                </span>
            </div>
            <div class="p-5">
                <?php if (!empty($pets)): ?>
                <div class="space-y-3">
                    <?php foreach ($pets as $pet):
                $sc = 'bg-gray-100 text-gray-600';
                if ($pet['status'] == 'Approved')
                    $sc = 'bg-green-100 text-green-800';
                elseif ($pet['status'] == 'Declined')
                    $sc = 'bg-red-100 text-red-800';
?>
                    <div class="p-3 bg-yellow-50 rounded-lg flex items-start justify-between">
                        <div>
                            <div class="font-bold text-yellow-900 text-sm flex items-center gap-1">
                                <?= h($pet['name'])?>
                                <span class="text-xs <?= $sc?> px-1.5 py-0.5 rounded-full">
                                    <?= h($pet['status'] ?? 'Pending')?>
                                </span>
                            </div>
                            <div class="text-xs text-yellow-700">
                                <?= h($pet['type'])?>
                                <?= $pet['breed'] ? ' · ' . h($pet['breed']) : ''?>
                                <?=!empty($pet['adult_size']) ? ' · ' . h($pet['adult_size']) : ''?>
                            </div>
                            <?php if (!empty($pet['is_sterilized']) || !empty($pet['is_vaccinated'])): ?>
                            <div class="flex gap-1 mt-1">
                                <?php if ($pet['is_sterilized']): ?><span
                                    class="text-xs bg-blue-100 text-blue-700 px-1.5 rounded">✓ Sterilized</span>
                                <?php
                    endif; ?>
                                <?php if ($pet['is_vaccinated']): ?><span
                                    class="text-xs bg-blue-100 text-blue-700 px-1.5 rounded">✓ Vaccinated</span>
                                <?php
                    endif; ?>
                            </div>
                            <?php
                endif; ?>
                        </div>
                        <a href="units.php?action=delete_pet&pet_id=<?= $pet['id']?>&unit_id=<?= $id?>"
                            class="text-red-300 hover:text-red-500 text-xs ml-2"
                            onclick="return confirm('Remove pet?')"><i class="fas fa-times"></i></a>
                    </div>
                    <?php
            endforeach; ?>
                </div>
                <?php
        else: ?>
                <p class="text-gray-400 italic text-sm mb-3">No pets registered.</p>
                <?php
        endif; ?>
                <?php if ($resident && ($max_pets == 0 || count($pets) < $max_pets)): ?>
                <div class="mt-3">
                    <a href="units.php?action=add_pet&id=<?= $id?>"
                        class="inline-flex items-center bg-yellow-50 text-yellow-700 font-bold py-1.5 px-4 rounded-lg hover:bg-yellow-100 text-sm w-full justify-center">
                        <i class="fas fa-plus mr-1"></i> Register Pet
                    </a>
                </div>
                <?php
        endif; ?>
            </div>
        </div>
        <?php
    endif; ?>
    </div>

    <!-- ── Row 3: Modifications (compact) ─────────────────────────────────── -->
    <div class="bg-white shadow rounded-xl overflow-hidden">
        <div class="px-5 py-4 bg-gray-50 border-b flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2"><i
                    class="fas fa-hammer text-gray-400"></i> Modification History</h3>
            <span class="bg-gray-200 text-gray-700 px-3 py-0.5 rounded-full text-xs font-bold">
                <?= count($modifications)?> Total
            </span>
        </div>
        <div class="p-5">
            <?php if (empty($modifications)): ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-tools text-3xl mb-2 block text-gray-300"></i>
                No modifications logged for this unit.
            </div>
            <?php
    else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-xs font-bold text-gray-400 uppercase border-b">
                            <th class="pb-2 text-left">Category</th>
                            <th class="pb-2 text-left">Status</th>
                            <th class="pb-2 text-left">Date</th>
                            <th class="pb-2 text-left">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($modifications as $mod):
            $mc = 'bg-gray-100 text-gray-600';
            if (in_array($mod['status'], ['Approved', 'approved']))
                $mc = 'bg-green-100 text-green-800';
            elseif (in_array($mod['status'], ['Declined', 'rejected']))
                $mc = 'bg-red-100 text-red-800';
            elseif ($mod['status'] === 'Completed')
                $mc = 'bg-blue-100 text-blue-800';
?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 pr-3 font-bold text-gray-800">
                                <?= h($mod['category'] ?? '—')?>
                            </td>
                            <td class="py-2 pr-3"><span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $mc?>">
                                    <?= ucfirst(h($mod['status']))?>
                                </span></td>
                            <td class="py-2 pr-3 text-gray-500 whitespace-nowrap">
                                <?= format_date($mod['request_date'])?>
                            </td>
                            <td class="py-2 text-gray-600 truncate max-w-xs">
                                <?= h(mb_strimwidth($mod['description'], 0, 80, '…'))?>
                            </td>
                        </tr>
                        <?php
        endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
    endif; ?>
        </div>
    </div>

</div><!-- /max-w-6xl -->
<?php
elseif ($action === 'delete_pet' && isset($_GET['pet_id']) && isset($_GET['unit_id'])):
    $pet_id = (int)$_GET['pet_id'];
    $unit_id = (int)$_GET['unit_id'];
    $stmt = $pdo->prepare("DELETE FROM pets WHERE id = ? AND unit_id = ?");
    $stmt->execute([$pet_id, $unit_id]);
    header("Location: units.php?action=view&id=" . $unit_id . "&msg=pet_removed");
    exit;
elseif ($action === 'add_pet' && isset($_GET['id'])):
    $unit_id = (int)$_GET['id'];

    // Fetch unit
    $stmt = $pdo->prepare("SELECT * FROM units WHERE id = ?");
    $stmt->execute([$unit_id]);
    $unit = $stmt->fetch();
    if (!$unit) {
        header("Location: units.php");
        exit;
    }

    // Fetch resident
    $stmt = $pdo->prepare("SELECT r.*, 
        CASE WHEN r.resident_type = 'owner' THEN o.full_name ELSE t.full_name END AS resident_name
        FROM residents r
        LEFT JOIN owners o ON r.resident_type = 'owner' AND r.resident_id = o.id
        LEFT JOIN tenants t ON r.resident_type = 'tenant' AND r.resident_id = t.id
        WHERE r.unit_id = ?");
    $stmt->execute([$unit_id]);
    $resident = $stmt->fetch();

    if (!$resident) {
        header("Location: units.php?action=view&id=" . $unit_id);
        exit;
    }

    // Fetch pet settings
    $pet_settings = $pdo->query("SELECT setting_key, setting_value FROM pet_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $max_pets = (int)($pet_settings['max_pets_per_unit'] ?? 2);
    $allowed_types = array_map('trim', explode(',', $pet_settings['allowed_pet_types'] ?? 'Dog, Cat, Bird, Fish'));

    // Check current count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pets WHERE unit_id = ?");
    $stmt->execute([$unit_id]);
    $pet_count = $stmt->fetchColumn();

    if ($max_pets > 0 && $pet_count >= $max_pets) {
        header("Location: units.php?action=view&id=" . $unit_id . "&error=max_pets");
        exit;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pet'])) {
        $name = trim($_POST['pet_name']);
        $type = trim($_POST['pet_type']);
        $breed = trim($_POST['breed'] ?? '');
        $reg = trim($_POST['reg_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($name && $type) {
            $stmt = $pdo->prepare("INSERT INTO pets (unit_id, resident_id, name, type, breed, reg_number, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$unit_id, $resident['id'], $name, $type, $breed, $reg, $notes]);
            header("Location: units.php?action=view&id=" . $unit_id . "&msg=pet_added");
            exit;
        }
        else {
            $error = "Pet name and type are required.";
        }
    }
?>
<div class="max-w-2xl mx-auto">
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h2 class="text-xl font-bold text-gray-800">
                <i class="fas fa-paw mr-2 text-yellow-500"></i>
                Register Pet for
                <?= h($unit['unit_number'])?>
            </h2>
            <p class="text-sm text-gray-500 mt-1">Resident: <strong>
                    <?= h($resident['resident_name'])?>
                </strong></p>
        </div>
        <div class="p-6">
            <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= h($error)?>
            </div>
            <?php
    endif; ?>
            <form method="POST" class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Pet Name *</label>
                        <input type="text" name="pet_name" required
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                            placeholder="e.g. Buddy">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Type *</label>
                        <select name="pet_type" required
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline">
                            <option value="">-- Select Type --</option>
                            <?php foreach ($allowed_types as $t): ?>
                            <option value="<?= h($t)?>">
                                <?= h($t)?>
                            </option>
                            <?php
    endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Breed</label>
                        <input type="text" name="breed"
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none"
                            placeholder="e.g. Labrador">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Registration / Tag
                            Number</label>
                        <input type="text" name="reg_number"
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none"
                            placeholder="e.g. T-1234">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Notes</label>
                        <textarea name="notes" rows="3"
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none"
                            placeholder="Any additional notes about this pet..."></textarea>
                    </div>
                </div>
                <div class="flex gap-4 pt-4">
                    <button type="submit" name="save_pet"
                        class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-6 rounded shadow transition duration-150">
                        <i class="fas fa-paw mr-2"></i> Register Pet
                    </button>
                    <a href="units.php?action=view&id=<?= $unit_id?>"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-6 rounded transition duration-150">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
elseif ($action === 'manage_owners' && isset($_GET['id'])): ?>
<?php
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM units WHERE id = ?");
    $stmt->execute([$id]);
    $unit = $stmt->fetch();

    if (!$unit) {
        echo "<div class='bg-red-100 p-4 rounded text-red-700'>Unit not found.</div>";
        require_once 'includes/footer.php';
        exit;
    }

    // Fetch current owners
    $stmt = $pdo->prepare("SELECT o.* FROM owners o JOIN ownership_history oh ON o.id = oh.owner_id WHERE oh.unit_id = ? AND oh.is_current = 1");
    $stmt->execute([$id]);
    $current_owners = $stmt->fetchAll();

    // Fetch all owners for dropdown
    $all_owners = $pdo->query("SELECT id, full_name, email FROM owners WHERE is_active = 1 ORDER BY full_name ASC")->fetchAll();
?>
<div class="max-w-4xl mx-auto">
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h2 class="text-xl font-bold text-gray-800">Manage Ownership:
                <?= h($unit['unit_number'])?>
            </h2>
        </div>
        <div class="p-8">
            <!-- Current Owners List -->
            <div class="mb-10">
                <h3 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-4">Current Owners</h3>
                <?php if ($current_owners): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($current_owners as $owner): ?>
                    <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg border border-blue-100">
                        <div>
                            <div class="font-bold text-blue-900">
                                <?= h($owner['full_name'])?>
                            </div>
                            <div class="text-xs text-blue-600">
                                <?= h($owner['email'])?>
                            </div>
                        </div>
                        <i class="fas fa-check-circle text-blue-500"></i>
                    </div>
                    <?php
        endforeach; ?>
                </div>
                <?php
    else: ?>
                <p class="text-gray-400 italic">No owners currently assigned.</p>
                <?php
    endif; ?>
            </div>

            <form method="POST" class="space-y-8">
                <input type="hidden" name="unit_id" value="<?= $id?>">

                <div>
                    <h3 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-4">Add or Assign
                        Owner
                    </h3>
                    <div class="bg-gray-50 border rounded-lg p-6">
                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Select Existing
                                Owner</label>
                            <select name="owner_id"
                                class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                                id="owner_select" onchange="toggleNewOwnerForm()">
                                <option value="">-- Create New Owner --</option>
                                <?php foreach ($all_owners as $o): ?>
                                <option value="<?= $o['id']?>">
                                    <?= h($o['full_name'])?> (
                                    <?= h($o['email'])?>)
                                </option>
                                <?php
    endforeach; ?>
                            </select>
                        </div>

                        <div id="new_owner_form" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <p class="text-xs font-bold text-gray-500 mb-2 border-b pb-1">NEW OWNER DETAILS
                                </p>
                            </div>
                            <div>
                                <label class="block text-gray-700 text-xs font-bold mb-1">Full Name *</label>
                                <input type="text" name="full_name" id="new_name"
                                    class="shadow border rounded w-full py-2 px-3 text-gray-700 text-sm">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-xs font-bold mb-1">ID Number</label>
                                <input type="text" name="id_number"
                                    class="shadow border rounded w-full py-2 px-3 text-gray-700 text-sm">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-xs font-bold mb-1">Email</label>
                                <input type="email" name="email"
                                    class="shadow border rounded w-full py-2 px-3 text-gray-700 text-sm">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-xs font-bold mb-1">Phone</label>
                                <input type="text" name="phone"
                                    class="shadow border rounded w-full py-2 px-3 text-gray-700 text-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($current_owners): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                    <h3 class="text-yellow-800 font-bold mb-3 flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Replacement Confirmation
                    </h3>
                    <p class="text-sm text-yellow-700 mb-4">This unit already has owner(s) assigned. How would
                        you
                        like
                        to proceed?</p>
                    <div class="space-y-3">
                        <label
                            class="flex items-center p-3 bg-white border rounded cursor-pointer hover:border-yellow-400">
                            <input type="radio" name="replacement_type" value="replace" checked
                                class="h-4 w-4 text-yellow-600">
                            <div class="ml-3">
                                <span class="block font-bold text-gray-900">Replace existing owners</span>
                                <span class="block text-xs text-gray-500">They will be marked as "Previous
                                    Owners"</span>
                            </div>
                        </label>
                        <label
                            class="flex items-center p-3 bg-white border rounded cursor-pointer hover:border-yellow-400">
                            <input type="radio" name="replacement_type" value="add" class="h-4 w-4 text-yellow-600">
                            <div class="ml-3">
                                <span class="block font-bold text-gray-900">Add as Co-owner</span>
                                <span class="block text-xs text-gray-500">Unit will be owned by multiple people
                                    simultaneously</span>
                            </div>
                        </label>
                    </div>
                </div>
                <?php
    endif; ?>

                <div class="flex items-center justify-between pt-6 border-t">
                    <a href="units.php?action=view&id=<?= $id?>"
                        class="text-gray-500 hover:text-gray-700 font-bold">Cancel</a>
                    <button type="submit" name="assign_owner"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded shadow-lg transition duration-150">
                        Update Unit Ownership
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleNewOwn             {
        const select = document.getElementById('owner            );
        const form = document.ge('new_owner_form');
                            = document.getElementById                           if (select && form && nameIn                       if (select.value === "") fo = "1";
                form                    ents = "auto";
                name                     true;
    } else {
                              ty        pa = "0.4";
        form.style.pointerEvents = "none";
        nameInput.required = false;
    }
        }         cument.addEventListener('DOMCont            d', toggleNewOwnerForm);
</script>
<?php
else: ?>
<div class="bg-white shadow overflow-hidden sm:rounded-lg p-4">
    <table id="unitsTable" class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left">Unit Number</th>
                <th class="px-6 py-3 text-left">Current Owner</th>
                <th class="px-6 py-3 text-left">Current Resident</th>
                <th class="px-6 py-3 text-left">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php
    // Query to get units with current owners and tenant
    $sql = "SELECT u.*, 
                    GROUP_CONCAT(o.full_name SEPARATOR ', ') as owner_names, 
                    t.full_name as tenant_name,
                    t.id as tenant_id 
                    FROM units u
                    LEFT JOIN ownership_history oh ON u.id = oh.unit_id AND oh.is_current = 1
                    LEFT JOIN owners o ON oh.owner_id = o.id
                    LEFT JOIN tenants t ON u.id = t.unit_id
                    GROUP BY u.id
                    ORDER BY u.unit_number ASC";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()):
?>
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    <?= h($row['unit_number'])?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= $row['owner_names'] ? h($row['owner_names']) : '<span class="text-red-400">No Owner</span>'?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?php if ($row['tenant_id']): ?>
                    <a href="tenants.php?action=view&id=<?= $row['tenant_id']?>"
                        class="text-blue-600 hover:text-blue-900 underline underline-offset-2">
                        <?= h($row['tenant_name'])?>
                    </a>
                    <?php
        else: ?>
                    <span class="text-gray-400">-</span>
                    <?php
        endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-3">
                    <a href="units.php?action=view&id=<?= $row['id']?>"
                        class="text-green-600 hover:text-green-900">View</a>
                    <a href="units.php?action=edit&id=<?= $row['id']?>"
                        class="text-indigo-600 hover:text-indigo-900">Edit</a>
                </td>
            </tr>
            <?php
    endwhile; ?>
        </tbody>
    </table>
</div>

<script>
    $(document).reataTable({
        "pageLength": 25,
        "order": [[0, "asc"]]
    });
    });
</script>
<?php
endif; ?>

<?php require_once 'includes/footer.php'; ?>