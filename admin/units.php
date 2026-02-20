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
                    t.full_name as tenant_name 
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
                    <?= $row['tenant_name'] ? h($row['tenant_name']) : '<span class="text-gray-400">-</span>'?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <a href="units.php?action=edit&id=<?= $row['id']?>" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
$(document).ready(function() {
    $('#unitsTable').DataTable({
        "pageLength": 25,
        "order": [[0, "asc"]]
    });
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>