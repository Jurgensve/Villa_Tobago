<?php
require_once 'includes/header.php';

$message = '';
$action = $_GET['action'] ?? 'list';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_unit'])) {
        $unit_number = trim($_POST['unit_number']);
        if (!empty($unit_number)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO units (unit_number) VALUES (?)");
                $stmt->execute([$unit_number]);
                $message = "Unit '$unit_number' added successfully.";
            }
            catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
            }
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

<?php if ($action === 'add'): ?>
<div class="bg-white shadow rounded-lg p-6 max-w-lg">
    <h2 class="text-xl font-semibold mb-4">Add New Unit</h2>
    <form method="POST">
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="unit_number">
                Unit Number
            </label>
            <input
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                id="unit_number" name="unit_number" type="text" placeholder="e.g. Unit 42" required>
        </div>
        <button
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
            type="submit" name="create_unit">
            Save Unit
        </button>
    </form>
</div>
<?php
else: ?>
<div class="bg-white shadow overflow-hidden sm:rounded-lg">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    ID
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Unit Number
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Current Owner
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Current Tenant
                </th>
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
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= h($row['id'])?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    <?= h($row['unit_number'])?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= $row['owner_name'] ? h($row['owner_name']) : '<span class="text-red-400">No Owner</span>'?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= $row['tenant_name'] ? h($row['tenant_name']) : '<span class="text-gray-400">-</span>'?>
                </td>
            </tr>
            <?php
    endwhile; ?>
        </tbody>
    </table>
</div>
<?php
endif; ?>

<?php require_once 'includes/footer.php'; ?>