<?php
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

                // 2. Create Owner
                $stmt = $pdo->prepare("INSERT INTO owners (full_name, id_number, email, phone) VALUES (?, ?, ?, ?)");
                $stmt->execute([$owner_name, $owner_id_num, $owner_email, $owner_phone]);
                $owner_id = $pdo->lastInsertId();

                // 3. Link them in history
                $stmt = $pdo->prepare("INSERT INTO ownership_history (unit_id, owner_id, start_date, is_current) VALUES (?, ?, NOW(), 1)");
                $stmt->execute([$unit_id, $owner_id]);

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
}
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-900">Units</h1>
    <?php if ($action !== 'add'): ?>
    <a href="units.php?action=add" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
        <i class="fas fa-plus mr-2"></i> Add Unit
    </a>
    <?php
else: ?>
    <a href="units.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
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

    // 1. Fetch Unit & Current Owner
    $sql = "SELECT u.*, o.full_name as owner_name, o.email as owner_email, o.phone as owner_phone, o.id_number as owner_id_num
            FROM units u
            LEFT JOIN ownership_history oh ON u.id = oh.unit_id AND oh.is_current = 1
            LEFT JOIN owners o ON oh.owner_id = o.id
            WHERE u.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $unit = $stmt->fetch();

    if (!$unit) {
        echo "<div class='bg-red-100 p-4 rounded text-red-700'>Unit not found.</div>";
        require_once 'includes/footer.php';
        exit;
    }

    // 2. Fetch Active Tenant
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE unit_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$id]);
    $tenant = $stmt->fetch();

    // 3. Fetch Modifications
    $stmt = $pdo->prepare("SELECT * FROM modifications WHERE unit_id = ? ORDER BY request_date DESC");
    $stmt->execute([$id]);
    $modifications = $stmt->fetchAll();
?>
<div class="max-w-6xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- left Column: Info Cards -->
        <div class="space-y-6 lg:col-span-1">
            <!-- Unit Card -->
            <div class="bg-white shadow rounded-lg overflow-hidden border-t-4 border-blue-500">
                <div class="px-6 py-4 bg-gray-50 border-b">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center">
                        <i class="fas fa-building mr-2 text-blue-500"></i> Unit Info
                    </h3>
                </div>
                <div class="p-6">
                    <div class="text-3xl font-bold text-gray-900 mb-1">
                        <?= h($unit['unit_number'])?>
                    </div>
                    <div class="text-sm text-gray-500">ID: #
                        <?= $unit['id']?>
                    </div>
                </div>
            </div>

            <!-- Owner Card -->
            <div class="bg-white shadow rounded-lg overflow-hidden border-t-4 border-indigo-500">
                <div class="px-6 py-4 bg-gray-50 border-b">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center">
                        <i class="fas fa-user-tie mr-2 text-indigo-500"></i> Current Owner
                    </h3>
                </div>
                <div class="p-6">
                    <?php if ($unit['owner_name']): ?>
                    <div class="font-bold text-gray-900 text-lg mb-4">
                        <?= h($unit['owner_name'])?>
                    </div>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-id-card w-6 text-indigo-400"></i>
                            <span>
                                <?= h($unit['owner_id_num'] ?: 'No ID on file')?>
                            </span>
                        </div>
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-envelope w-6 text-indigo-400"></i>
                            <a href="mailto:<?= h($unit['owner_email'])?>" class="hover:text-indigo-600">
                                <?= h($unit['owner_email'] ?: 'No email')?>
                            </a>
                        </div>
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-phone w-6 text-indigo-400"></i>
                            <span>
                                <?= h($unit['owner_phone'] ?: 'No phone')?>
                            </span>
                        </div>
                    </div>
                    <?php
    else: ?>
                    <p class="text-red-500 italic">No current owner assigned.</p>
                    <?php
    endif; ?>
                </div>
            </div>

            <!-- Tenant Card -->
            <div class="bg-white shadow rounded-lg overflow-hidden border-t-4 border-green-500">
                <div class="px-6 py-4 bg-gray-50 border-b flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center">
                        <i class="fas fa-user mr-2 text-green-500"></i> Resident Tenant
                    </h3>
                </div>
                <div class="p-6">
                    <?php if ($tenant): ?>
                    <div class="font-bold text-gray-900 text-lg mb-4">
                        <?= h($tenant['full_name'])?>
                    </div>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-envelope w-6 text-green-400"></i>
                            <a href="mailto:<?= h($tenant['email'])?>" class="hover:text-green-600">
                                <?= h($tenant['email'] ?: 'No email')?>
                            </a>
                        </div>
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-phone w-6 text-green-400"></i>
                            <span>
                                <?= h($tenant['phone'] ?: 'No phone')?>
                            </span>
                        </div>
                        <?php if ($tenant['lease_agreement_path']): ?>
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <a href="<?= SITE_URL?>/<?= h($tenant['lease_agreement_path'])?>" target="_blank"
                                class="inline-flex items-center text-blue-600 hover:text-blue-800 font-semibold">
                                <i class="fas fa-file-contract mr-2"></i> View Lease Agreement
                            </a>
                        </div>
                        <?php
        endif; ?>
                        <div class="mt-4">
                            <a href="tenants.php?action=edit&id=<?= $tenant['id']?>"
                                class="text-indigo-600 hover:text-indigo-800 text-xs font-bold uppercase tracking-wider">
                                Edit Tenant Details <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                    <?php
    else: ?>
                    <div class="text-center py-4">
                        <p class="text-gray-400 italic mb-4">No active tenant.</p>
                        <a href="tenants.php?action=add&unit_id=<?= $id?>"
                            class="bg-blue-50 text-blue-700 font-bold py-2 px-4 rounded text-sm hover:bg-blue-100">
                            <i class="fas fa-plus mr-1"></i> Add Tenant
                        </a>
                    </div>
                    <?php
    endif; ?>
                </div>
            </div>
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
            $statusColor = 'bg-gray-100 text-gray-800';
            if ($mod['status'] == 'approved')
                $statusColor = 'bg-green-100 text-green-800';
            if ($mod['status'] == 'rejected')
                $statusColor = 'bg-red-100 text-red-800';
            if ($mod['status'] == 'completed')
                $statusColor = 'bg-blue-100 text-blue-800';
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

                                                <?php if ($mod['notes']): ?>
                                                <div
                                                    class="mt-3 text-xs bg-yellow-50 p-2 rounded border border-yellow-100 italic text-yellow-800">
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
else: ?>
<div class="bg-white shadow overflow-hidden sm:rounded-lg p-4">
    <table id="unitsTable" class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left">Unit Number</th>
                <th class="px-6 py-3 text-left">Current Owner</th>
                <th class="px-6 py-3 text-left">Current Tenant</th>
                <th class="px-6 py-3 text-left">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php
    // Query to get units with current owner and tenant
    $sql = "SELECT u.*, 
                    o.full_name as owner_name, 
                    t.full_name as tenant_name,
                    t.id as tenant_id 
                    FROM units u
                    LEFT JOIN ownership_history oh ON u.id = oh.unit_id AND oh.is_current = 1
                    LEFT JOIN owners o ON oh.owner_id = o.id
                    LEFT JOIN tenants t ON u.id = t.unit_id AND t.is_active = 1
                    ORDER BY u.unit_number ASC";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()):
?>
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    <?= h($row['unit_number'])?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= $row['owner_name'] ? h($row['owner_name']) : '<span class="text-red-400">No Owner</span>'?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?php if ($row['tenant_id']): ?>
                    <a href="tenants.php?action=edit&id=<?= $row['tenant_id']?>"
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
    $(document).ready(function () {
        $('#unitsTable').DataTable({
            "pageLength": 25,
            "order": [[0, "asc"]]
        });
    });
</script>
<?php
endif; ?>

<?php require_once 'includes/footer.php'; ?>