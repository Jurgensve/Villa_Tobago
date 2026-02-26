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
    $pet_enabled = '1';
    $max_pets = 2;
    $allowed_types = ['Dog', 'Cat', 'Bird', 'Fish'];
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
                'id' => null, // No DB record yet
                '_default' => true, // Flag so we can show hint
            ];
        }

        // 6. Fetch Pets
        $stmt = $pdo->prepare("SELECT * FROM pets WHERE unit_id = ? ORDER BY created_at DESC");
        $stmt->execute([$id]);
        $pets = $stmt->fetchAll();

        // 7. Fetch Pet Settings
        $pet_settings = $pdo->query("SELECT setting_key, setting_value FROM pet_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $max_pets = $pet_settings['max_pets_per_unit'] ?? 2;
        $pet_enabled = $pet_settings['pet_management_enabled'] ?? '1';
        $allowed_types = array_map('trim', explode(',', $pet_settings['allowed_pet_types'] ?? 'Dog, Cat, Bird, Fish'));
    }
    catch (PDOException $e) {
        // New tables not migrated yet — skip pets/resident features
        $pet_enabled = '0';
    }

    // 8. Fetch Modifications
    $stmt = $pdo->prepare("SELECT * FROM modifications WHERE unit_id = ? ORDER BY request_date DESC");
    $stmt->execute([$id]);
    $modifications = $stmt->fetchAll();
?>
<div class="max-w-6xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Info Cards -->
        <div class="space-y-6 lg:col-span-1">
            <!-- Unit Info + Owner Card -->
            <div class="bg-white shadow rounded-lg overflow-hidden border-t-4 border-blue-500">
                <div class="px-6 py-4 bg-gray-50 border-b flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center">
                        <i class="fas fa-building mr-2 text-blue-500"></i>
                        Unit
                        <?= h($unit['unit_number'])?>
                    </h3>
                    <span class="text-xs text-gray-400">#
                        <?= $unit['id']?>
                    </span>
                </div>
                <div class="p-6">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Owner(s)</p>
                    <?php if (!empty($current_owners)): ?>
                    <div class="space-y-4">
                        <?php foreach ($current_owners as $index => $owner): ?>
                        <div class="<?= $index > 0 ? 'pt-3 border-t border-gray-100' : ''?>">
                            <div class="flex justify-between items-start">
                                <div class="font-bold text-gray-900">
                                    <?= h($owner['full_name'])?>
                                </div>
                                <a href="owners.php?action=edit&id=<?= $owner['id']?>"
                                    class="text-indigo-500 hover:text-indigo-700 text-xs ml-2">Edit</a>
                            </div>
                            <div class="mt-1 space-y-1 text-sm text-gray-500">
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
                    <div class="mt-5 pt-4 border-t border-dashed border-gray-100">
                        <a href="units.php?action=manage_owners&id=<?= $id?>"
                            class="inline-flex items-center justify-center w-full bg-indigo-50 text-indigo-700 font-bold py-2 px-4 rounded hover:bg-indigo-100 transition duration-150 text-sm">
                            <i class="fas fa-users-cog mr-2"></i> Manage Owners
                        </a>
                    </div>
                </div>
            </div>

            <!-- Resident Card (unified) -->
            <?php
    $display_res = null;
    $res_color = 'border-gray-300';
    $res_icon = 'text-gray-400';
    if ($tenant) {
        $display_res = ['name' => $tenant['full_name'], 'email' => $tenant['email'], 'phone' => $tenant['phone'],
            'label' => 'Tenant', 'label_class' => 'bg-green-100 text-green-800',
            'lease' => $tenant['lease_agreement_path'] ?? null,
            'link' => 'tenants.php?action=view&id=' . $tenant['id'], 'link_text' => 'View Tenant Details',
            'add_tenant' => false];
        $res_color = 'border-green-500';
        $res_icon = 'text-green-500';
    }
    elseif ($resident && empty($resident['_default'])) {
        $display_res = ['name' => $resident['resident_name'], 'email' => $resident['resident_email'], 'phone' => $resident['resident_phone'],
            'label' => 'Owner (Residing)', 'label_class' => 'bg-purple-100 text-purple-800',
            'add_tenant' => true];
        $res_color = 'border-purple-500';
        $res_icon = 'text-purple-500';
    }
    elseif (!empty($current_owners)) {
        $o = $current_owners[0];
        $display_res = ['name' => $o['full_name'], 'email' => $o['email'], 'phone' => $o['phone'],
            'label' => 'Owner', 'label_class' => 'bg-blue-100 text-blue-700',
            'default_hint' => true, 'add_tenant' => true];
        $res_color = 'border-blue-300';
        $res_icon = 'text-blue-400';
    }
?>
            <div class="bg-white shadow rounded-lg overflow-hidden border-t-4 <?= $res_color?>">
                <div class="px-6 py-4 bg-gray-50 border-b">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center">
                        <i class="fas fa-home mr-2 <?= $res_icon?>"></i> Resident
                    </h3>
                </div>
                <div class="p-6">
                    <?php if ($display_res): ?>
                    <div class="flex items-center gap-2 mb-3">
                        <span
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?= $display_res['label_class']?>">
                            <?= h($display_res['name'])?>
                        </span>
                        <?php if (!empty($display_res['default_hint'])): ?>
                        <span class="text-xs text-gray-400 italic">default</span>
                        <?php
        endif; ?>
                    </div>
                    <div class="font-bold text-gray-900 text-lg mb-2">
                        <?= h($display_res['name'])?>
                    </div>
                    <div
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold <?= $display_res['label_class']?> mb-3">
                        <?= h($display_res['label'])?>
                    </div>
                    <div class="space-y-1 text-sm text-gray-600">
                        <?php if (!empty($display_res['email'])): ?>
                        <div><i class="fas fa-envelope w-5 text-gray-400"></i> <a
                                href="mailto:<?= h($display_res['email'])?>" class="hover:text-blue-600">
                                <?= h($display_res['email'])?>
                            </a></div>
                        <?php
        endif; ?>
                        <?php if (!empty($display_res['phone'])): ?>
                        <div><i class="fas fa-phone w-5 text-gray-400"></i>
                            <?= h($display_res['phone'])?>
                        </div>
                        <?php
        endif; ?>
                    </div>
                    <?php if (!empty($display_res['lease'])): ?>
                    <div class="mt-4 pt-3 border-t border-gray-100">
                        <a href="<?= SITE_URL?>/<?= h($display_res['lease'])?>" target="_blank"
                            class="inline-flex items-center text-blue-600 hover:text-blue-800 font-semibold text-sm">
                            <i class="fas fa-file-contract mr-2"></i> View Lease
                        </a>
                    </div>
                    <?php
        endif; ?>
                    <?php if (!empty($display_res['link'])): ?>
                    <div class="mt-2"><a href="<?= $display_res['link']?>"
                            class="text-indigo-600 hover:text-indigo-800 text-xs font-bold uppercase tracking-wider">
                            <?= $display_res['link_text']?> <i class="fas fa-arrow-right ml-1"></i>
                        </a></div>
                    <?php
        endif; ?>
                    <?php if (!empty($display_res['add_tenant'])): ?>
                    <div class="mt-4 pt-3 border-t border-dashed border-gray-100">
                        <a href="tenants.php?action=add&unit_id=<?= $id?>"
                            class="inline-flex items-center justify-center w-full bg-green-50 text-green-700 font-bold py-2 px-4 rounded hover:bg-green-100 transition text-sm">
                            <i class="fas fa-plus mr-2"></i> Add Tenant
                        </a>
                    </div>
                    <?php
        endif; ?>
                    <?php
    else: ?>
                    <p class="text-gray-400 italic text-sm mb-4">No resident or owner assigned.</p>
                    <a href="tenants.php?action=add&unit_id=<?= $id?>"
                        class="inline-flex items-center justify-center w-full bg-green-50 text-green-700 font-bold py-2 px-4 rounded hover:bg-green-100 transition text-sm">
                        <i class="fas fa-plus mr-2"></i> Add Tenant
                    </a>
                    <?php
    endif; ?>
                </div>
            </div>


            <!-- Pets Card -->
            <?php if ($pet_enabled): ?>
            <div class="bg-white shadow rounded-lg overflow-hidden border-t-4 border-yellow-500">
                <div class="px-6 py-4 bg-gray-50 border-b flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center">
                        <i class="fas fa-paw mr-2 text-yellow-500"></i> Pets
                    </h3>
                    <span class="text-xs text-gray-500">Max:
                        <?= $max_pets == 0 ? 'Unlimited' : $max_pets?>
                    </span>
                </div>
                <div class="p-6">
                    <?php if (!$resident): ?>
                    <p class="text-gray-400 italic text-sm">Assign a resident to this unit to register pets.</p>
                    <?php
        elseif (!empty($pets)): ?>
                    <div class="space-y-3">
                        <?php foreach ($pets as $pet): ?>
                        <div
                            class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg border border-yellow-100">
                            <div>
                                <div class="font-bold text-yellow-900 flex items-center gap-2">
                                    <?= h($pet['name'])?>
                                    <?php
                $sColor = 'bg-gray-100 text-gray-800';
                if ($pet['status'] == 'Approved')
                    $sColor = 'bg-green-100 text-green-800';
                elseif ($pet['status'] == 'Declined')
                    $sColor = 'bg-red-100 text-red-800';
                elseif ($pet['status'] == 'Information Requested')
                    $sColor = 'bg-yellow-100 text-yellow-800';
                elseif ($pet['status'] == 'Pending Updated')
                    $sColor = 'bg-purple-100 text-purple-800';
?>
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $sColor?>">
                                        <?= h($pet['status'] ?? 'Pending')?>
                                    </span>
                                </div>
                                <div class="text-xs text-yellow-700">
                                    <?= h($pet['type'])?>
                                    <?= $pet['breed'] ? ' · ' . h($pet['breed']) : ''?>
                                </div>
                                <?php if ($pet['reg_number']): ?>
                                <div class="text-xs text-gray-500">Reg:
                                    <?= h($pet['reg_number'])?>
                                </div>
                                <?php
                endif; ?>
                                <?php if (!empty($pet['trustee_comments'])): ?>
                                <div class="mt-2 text-xs bg-white p-2 text-blue-800 rounded border border-blue-100">
                                    <strong>Trustee Comment:</strong>
                                    <?= h($pet['trustee_comments'])?>
                                </div>
                                <?php
                endif; ?>
                                <?php
                $logStmt = $pdo->prepare("SELECT * FROM amendment_logs WHERE related_type = 'pet' AND related_id = ? ORDER BY created_at ASC");
                $logStmt->execute([$pet['id']]);
                $logs = $logStmt->fetchAll();
                if (!empty($logs)): ?>
                                <div class="mt-2 text-xs bg-gray-50 p-2 rounded border border-gray-200">
                                    <div class="font-semibold text-gray-700 mb-1 border-b border-gray-200 pb-1">
                                        Amendment History:</div>
                                    <ul class="space-y-1">
                                        <?php foreach ($logs as $log): ?>
                                        <li><span class="text-gray-500">[
                                                <?= format_date($log['created_at'])?>]
                                            </span>
                                            <strong>
                                                <?= ucfirst($log['action_type'])?>:
                                            </strong>
                                            <?= h($log['comments'])?>
                                        </li>
                                        <?php
                    endforeach; ?>
                                    </ul>
                                    <?php
                endif; ?>
                                </div>
                                <a href="units.php?action=delete_pet&pet_id=<?= $pet['id']?>&unit_id=<?= $id?>"
                                    class="text-red-400 hover:text-red-600 text-xs"
                                    onclick="return confirm('Remove this pet record?')">
                                    <i class="fas fa-times-circle"></i>
                                </a>
                            </div>
                            <?php
            endforeach; ?>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100">
                            <?php
        else: ?>
                            <p class="text-gray-400 italic text-sm mb-4">No pets registered.</p>
                            <div>
                                <?php
        endif; ?>

                                <?php if ($resident && ($max_pets == 0 || count($pets) < $max_pets)): ?>
                                <a href="units.php?action=add_pet&id=<?= $id?>"
                                    class="inline-flex items-center bg-yellow-50 text-yellow-700 font-bold py-2 px-4 rounded text-sm hover:bg-yellow-100 w-full justify-center">
                                    <i class="fas fa-plus mr-2"></i> Register Pet
                                </a>
                                <?php
        elseif ($resident && $max_pets > 0 && count($pets) >= $max_pets): ?>
                                <p class="text-xs text-red-500 italic text-center">Maximum pets reached (
                                    <?= $max_pets?>).
                                </p>
                                <?php
        endif; ?>
                                <?php if (!empty($pets) || $resident): ?>
                            </div>
                            <?php
        endif; ?>
                        </div>
                    </div>
                    <?php
    endif; ?>

                </div>

                <!-- Right Column: Modifications History -->
                <div class="lg:col-span-2">
                    <div class="bg-white shadow rounded-lg overflow-hidden h-full">
                        <div class="px-6 py-4 bg-gray-50 border-b flex justify-between items-center">
                            <h3 class="text-xl font-bold text-gray-800">Modification History</h3>
                            <span class="bg-gray-200 text-gray-700 px-3 py-1 rounded-full text-xs font-bold">
                                <?= count($modifications)?> Total
                            </span>
                        </div>
                        <div class="p-6">
                            <?php if (empty($modifications)): ?>
                            <div class="text-center py-12">
                                <div class="text-gray-300 text-5xl mb-4"><i class="fas fa-tools"></i></div>
                                <p class="text-gray-500">No modifications logged for this unit yet.</p>
                            </div>
                            <?php
    else: ?>
                            <div class="flow-root">
                                <ul class="-mb-8">
                                    <?php foreach ($modifications as $index => $mod):
            if ($mod['status'] == 'Approved' || $mod['status'] == 'approved')
                $statusColor = 'bg-green-100 text-green-800';
            elseif ($mod['status'] == 'Declined' || $mod['status'] == 'rejected')
                $statusColor = 'bg-red-100 text-red-800';
            elseif ($mod['status'] == 'Completed' || $mod['status'] == 'completed')
                $statusColor = 'bg-blue-100 text-blue-800';
            elseif ($mod['status'] == 'Information Requested')
                $statusColor = 'bg-yellow-100 text-yellow-800';
            elseif ($mod['status'] == 'Pending Updated')
                $statusColor = 'bg-purple-100 text-purple-800';
?>
                                    <li>
                                        <div class="relative pb-8">
                                            <?php if ($index !== count($modifications) - 1): ?>
                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"
                                                aria-hidden="true"></span>
                                            <?php
            endif; ?>
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    <span
                                                        class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center ring-8 ring-white">
                                                        <i class="fas fa-hammer text-white text-xs"></i>
                                                    </span>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="bg-gray-50 rounded-lg p-4 ml-2">
                                                        <div class="flex justify-between items-start mb-2">
                                                            <div>
                                                                <h4 class="text-sm font-bold text-gray-900">
                                                                    <?= h($mod['category'])?>
                                                                </h4>
                                                                <p class="text-xs text-gray-500">
                                                                    <?= format_date($mod['request_date'])?>
                                                                </p>
                                                            </div>
                                                            <span
                                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusColor?>">
                                                                <?= ucfirst($mod['status'])?>
                                                            </span>
                                                        </div>
                                                        <p class="text-sm text-gray-600 mb-3 line-clamp-2">
                                                            <?= h($mod['description'])?>
                                                        </p>

                                                        <?php
            // Fetch attachments for this mod
            $atts = $pdo->prepare("SELECT * FROM modification_attachments WHERE modification_id = ?");
            $atts->execute([$mod['id']]);
            if ($attachments = $atts->fetchAll()): ?>
                                                        <div class="mt-2 flex flex-wrap gap-2">
                                                            <?php foreach ($attachments as $att): ?>
                                                            <a href="<?= SITE_URL?>/<?= h($att['file_path'])?>"
                                                                target="_blank"
                                                                class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-white border border-gray-200 text-blue-600 hover:border-blue-300">
                                                                <i class="fas fa-paperclip mr-1"></i>
                                                                <?= h($att['display_name'])?>
                                                            </a>
                                                            <?php
                endforeach; ?>
                                                        </div>
                                                        <?php
            endif; ?>

                                                        <?php if (!empty($mod['trustee_comments'])): ?>
                                                        <div
                                                            class="mt-3 text-sm bg-blue-50 p-2 rounded border border-blue-100 text-blue-800 mb-2">
                                                            <strong>Current Trustee Comments:</strong>
                                                            <?= h($mod['trustee_comments'])?>
                                                        </div>
                                                        <?php
            endif; ?>

                                                        <?php
            $logStmt = $pdo->prepare("SELECT * FROM amendment_logs WHERE related_type = 'modification' AND related_id = ? ORDER BY created_at ASC");
            $logStmt->execute([$mod['id']]);
            $logs = $logStmt->fetchAll();
            if (!empty($logs)): ?>
                                                        <div
                                                            class="mt-2 text-xs bg-gray-50 p-2 rounded border border-gray-200">
                                                            <div
                                                                class="font-semibold text-gray-700 mb-1 border-b border-gray-200 pb-1">
                                                                Amendment History:</div>
                                                            <ul class="space-y-1">
                                                                <?php foreach ($logs as $log): ?>
                                                                <li><span class="text-gray-500">[
                                                                        <?= format_date($log['created_at'])?>]
                                                                    </span>
                                                                    <strong>
                                                                        <?= ucfirst($log['action_type'])?>:
                                                                    </strong>
                                                                    <?= h($log['comments'])?>
                                                                </li>
                                                                <?php
                endforeach; ?>
                                                            </ul>
                                                        </div>
                                                        <?php
            endif; ?>
                                                        <?php if ($mod['notes']): ?>
                                                        <div
                                                            class="mt-2 text-xs bg-yellow-50 p-2 rounded border border-yellow-100 italic text-yellow-800">
                                                            <strong>Admin Note:</strong>
                                                            <?= h($mod['notes'])?>
                                                        </div>
                                                        <?php
            endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <?php
        endforeach; ?>
                                </ul>
                            </div>
                            <?php
    endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
                            <div
                                class="flex items-center justify-between p-3 bg-blue-50 rounded-lg border border-blue-100">
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
                                    <input type="radio" name="replacement_type" value="add"
                                        class="h-4 w-4 text-yellow-600">
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