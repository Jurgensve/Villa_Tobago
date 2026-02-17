<?php
require_once 'includes/header.php';

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_modification'])) {
        $unit_id = $_POST['unit_id'];
        $owner_id = $_POST['owner_id']; // Usually derived from unit, but for admin we might select
        $description = trim($_POST['description']);
        $notes = trim($_POST['notes']);

        // Check if owner matches unit if we want strictness, but let's trust the input for admin
        if (empty($description)) {
            $error = "Description is required.";
        }
        else {
            try {
                $stmt = $pdo->prepare("INSERT INTO modifications (unit_id, owner_id, description, notes, request_date) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$unit_id, $owner_id, $description, $notes]);
                $message = "Modification request logged.";
                $action = 'list';
            }
            catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
    elseif (isset($_POST['update_status'])) {
        $mod_id = $_POST['mod_id'];
        $status = $_POST['status'];
        $notes = trim($_POST['notes']);

        $stmt = $pdo->prepare("UPDATE modifications SET status = ?, notes = ?, approval_date = NOW() WHERE id = ?");
        $stmt->execute([$status, $notes, $mod_id]);
        $message = "Status updated.";
    }
}
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-900">Modifications</h1>
    <?php if ($action !== 'add'): ?>
    <a href="modifications.php?action=add" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
        <i class="fas fa-plus mr-2"></i> Log Request
    </a>
    <?php
else: ?>
    <a href="modifications.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
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
<?php
    // Fetch units and their current owners
    $sql = "SELECT u.id as unit_id, u.unit_number, o.id as owner_id, o.full_name 
            FROM units u 
            JOIN ownership_history oh ON u.id = oh.unit_id AND oh.is_current = 1
            JOIN owners o ON oh.owner_id = o.id
            ORDER BY u.unit_number ASC";
    $units = $pdo->query($sql)->fetchAll();
?>
<div class="bg-white shadow rounded-lg p-6 max-w-2xl">
    <h2 class="text-xl font-semibold mb-4">Log Modification Request</h2>
    <form method="POST">
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="unit_owner_select">Unit & Owner</label>
            <select
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                name="unit_owner_json" id="unit_owner_select" onchange="updateHiddenFields()" required>
                <option value="">-- Select Unit --</option>
                <?php foreach ($units as $unit): ?>
                <option value='<?= json_encode([' unit_id'=> $unit['unit_id'], 'owner_id' => $unit['owner_id']])?>'>
                    <?= h($unit['unit_number'])?> -
                    <?= h($unit['full_name'])?>
                </option>
                <?php
    endforeach; ?>
            </select>
            <input type="hidden" name="unit_id" id="unit_id">
            <input type="hidden" name="owner_id" id="owner_id">

            <script>
                function updateHiddenFields() {
                    const select = document.getElementById('unit_owner_select');
                    const val = select.value;
                    if (val) {
                        const data = JSON.parse(val);
                        document.getElementById('unit_id').value = data.unit_id;
                        document.getElementById('owner_id').value = data.owner_id;
                    }
                }
            </script>
        </div>
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="description">Request Description</label>
            <textarea
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                id="description" name="description" rows="4" required></textarea>
        </div>
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="notes">Admin Notes</label>
            <textarea
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                id="notes" name="notes" rows="2"></textarea>
        </div>
        <div class="mt-6">
            <button
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                type="submit" name="create_modification">
                Save Request
            </button>
        </div>
    </form>
</div>

<?php
else: ?>
<div class="bg-white shadow overflow-hidden sm:rounded-lg">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php
    $sql = "SELECT m.*, u.unit_number 
                        FROM modifications m
                        JOIN units u ON m.unit_id = u.id
                        ORDER BY m.request_date DESC";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()):
        $statusColor = 'bg-gray-100 text-gray-800';
        if ($row['status'] == 'approved')
            $statusColor = 'bg-green-100 text-green-800';
        if ($row['status'] == 'rejected')
            $statusColor = 'bg-red-100 text-red-800';
        if ($row['status'] == 'completed')
            $statusColor = 'bg-blue-100 text-blue-800';
?>
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    <?= h($row['unit_number'])?>
                </td>
                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                    <?= h($row['description'])?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusColor?>">
                        <?= ucfirst($row['status'])?>
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= format_date($row['request_date'])?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button
                        onclick="openStatusModal(<?= $row['id']?>, '<?= $row['status']?>', '<?= h(addslashes($row['notes']))?>')"
                        class="text-indigo-600 hover:text-indigo-900">Update Status</button>
                </td>
            </tr>
            <?php
    endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Simple Modal for Status Update -->
<div id="statusModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Update Status</h3>
            <form method="POST" class="mt-2 text-left">
                <input type="hidden" name="mod_id" id="modal_mod_id">
                <div class="mt-2">
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="modal_status"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="mt-2">
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="notes" id="modal_notes"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm"></textarea>
                </div>
                <div class="items-center px-4 py-3">
                    <button name="update_status"
                        class="px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300">
                        Save
                    </button>
                    <button type="button" onclick="document.getElementById('statusModal').classList.add('hidden')"
                        class="mt-3 px-4 py-2 bg-gray-100 text-gray-700 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-200">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    function openStatusModal(id, status, notes) {
        document.getElementById('modal_mod_id').value = id;
        document.getElementById('modal_status').value = status;
        document.getElementById('modal_notes').value = notes;
        document.getElementById('statusModal').classList.remove('hidden');
    }
</script>
<?php
endif; ?>

<?php require_once 'includes/footer.php'; ?>