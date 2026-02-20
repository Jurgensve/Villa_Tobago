<?php
require_once 'includes/header.php';

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_modification'])) {
        $unit_id = $_POST['unit_id'];
        $owner_id = $_POST['owner_id'];
        $category = $_POST['category'];
        $description = trim($_POST['description']);
        $notes = trim($_POST['notes']);
        $status = 'approved'; // Admin-added mods are auto-approved

        // Ensure 'category' column exists (Fallback if migration hasn't run)
        $check = $pdo->query("SHOW COLUMNS FROM modifications LIKE 'category'");
        if (!$check->fetch()) {
            $pdo->exec("ALTER TABLE modifications ADD COLUMN category VARCHAR(50) AFTER owner_id");
        }

        if (empty($description)) {
            $error = "Description is required.";
        }
        else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO modifications (unit_id, owner_id, category, description, notes, status, request_date, approval_date) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$unit_id, $owner_id, $category, $description, $notes, $status]);
                $mod_id = $pdo->lastInsertId();

                // Handle Multiple Attachments
                if (isset($_FILES['attachments'])) {
                    $attachment_names = $_POST['attachment_names'] ?? [];
                    foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['attachments']['error'][$key] == 0) {
                            $filename = $_FILES['attachments']['name'][$key];
                            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            $display_name = trim($attachment_names[$key] ?? 'Attachment ' . ($key + 1));

                            $new_filename = uniqid() . '_' . $key . '.' . $ext;
                            $target_dir = UPLOAD_DIR . 'modifications/';
                            if (!file_exists($target_dir))
                                mkdir($target_dir, 0755, true);

                            $target_path = $target_dir . $new_filename;
                            if (move_uploaded_file($tmp_name, $target_path)) {
                                $relative_path = 'uploads/modifications/' . $new_filename;
                                $stmt = $pdo->prepare("INSERT INTO modification_attachments (modification_id, file_path, display_name) VALUES (?, ?, ?)");
                                $stmt->execute([$mod_id, $relative_path, $display_name]);
                            }
                        }
                    }
                }

                $pdo->commit();
                $message = "Modification logged and approved successfully.";
                $action = 'list';
            }
            catch (PDOException $e) {
                $pdo->rollBack();
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

<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
    <?= h($error)?>
</div>
<?php
endif; ?>

<?php if ($action === 'add'): ?>
<?php
    $sql = "SELECT u.id as unit_id, u.unit_number, o.id as owner_id, o.full_name 
            FROM units u 
            JOIN ownership_history oh ON u.id = oh.unit_id AND oh.is_current = 1
            JOIN owners o ON oh.owner_id = o.id
            ORDER BY u.unit_number ASC";
    $units = $pdo->query($sql)->fetchAll();

    $categories = [
        "Gas Installation",
        "Patio Cover Modification",
        "External Windows or Doors",
        "Aircon Installation",
        "Fence Installation / Upgrade",
        "Security Gates or Burglar Bars",
        "Satellite Dish or Antenna",
        "Patio Built-in Fireplace",
        "Solar Power System",
        "Water Backup System"
    ];
?>
<div class="bg-white shadow rounded-lg p-6 max-w-4xl">
    <h2 class="text-xl font-semibold mb-6">Log Modification (Auto-Approved)</h2>
    <form method="POST" enctype="multipart/form-data">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="unit_owner_select">Unit & Owner</label>
                <select
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    name="unit_owner_json" id="unit_owner_select" onchange="updateHiddenFields()" required>
                    <option value="">-- Select Unit --</option>
                    <?php foreach ($units as $unit): ?>
                    <option value='<?= json_encode([' unit_id' => $unit['unit_id'], 'owner_id' => $unit['owner_id']])
    ?>'>
                        <?= h($unit['unit_number'])?> -
                        <?= h($unit['full_name'])?>
                    </option>
                    <?php
    endforeach; ?>
                </select>
                <input type="hidden" name="unit_id" id="unit_id">
                <input type="hidden" name="owner_id" id="owner_id">
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="category">Category</label>
                <select
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    name="category" id="category" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= h($cat)?>">
                        <?= h($cat)?>
                    </option>
                    <?php
    endforeach; ?>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="description">Detailed Description</label>
            <textarea
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                id="description" name="description" rows="3" required
                placeholder="Describe the modification..."></textarea>
        </div>

        <div class="bg-gray-50 p-4 rounded-md mb-6">
            <h3 class="font-bold text-gray-800 mb-4 flex justify-between items-center">
                Attachments
                <button type="button" onclick="addAttachmentRow()"
                    class="text-sm bg-blue-100 text-blue-700 px-3 py-1 rounded hover:bg-blue-200">
                    <i class="fas fa-plus mr-1"></i> Add More
                </button>
            </h3>
            <div id="attachments_container" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end attachment-row">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Attachment Name (e.g.
                            Invoice)</label>
                        <input type="text" name="attachment_names[]" class="w-full text-sm border rounded px-2 py-1"
                            placeholder="Name this document">
                    </div>
                    <div>
                        <input type="file" name="attachments[]" class="w-full text-sm">
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="notes">Internal Admin Notes</label>
            <textarea
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                id="notes" name="notes" rows="2"></textarea>
        </div>

        <div class="mt-8">
            <button
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded focus:outline-none focus:shadow-outline w-full md:w-auto"
                type="submit" name="create_modification">
                Save & Approve Modification
            </button>
        </div>
    </form>

    <script>
        function updateHiddenField              const select = document.getElementById('unit_owner_select')            nst val = select.value;
              l) {
                       = JSON.parse(val);
            ementById('unit_id').value = data.unit_id;
            tById('owner_id').value = data.owner_id;
        }
                un        ach                    const container = entById('attachments_container');
        const row = document.c            v');
        row.className = 'grid grid            cols-2 gap-4 items-end attachment-row border-t pt-4';
        row.innerHTML = `
                                              <label class="block text-xs font-semibold text-gray-600 mb-1">Attachment Name</label>
                        <input type="text" name="attachment_names[]" class="w-full text-sm border rounded px-2 py-1" placeholder="Name this document">
                    </div>
                    <div class="flex gap-2">
                        <input type="file" name="attachments[]" class="w-full text-sm">
                        <button type="button" onclick="this.closest('.attachment-row').remove()" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
                    </div>
                `;
        container.appendChild(row);
        }
    </script>
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
                <td class="px-6 py-4 text-sm text-gray-500">
                    <div class="font-bold text-gray-700">
                        <?= h($row['category'])?>
                    </div>
                    <div class="truncate max-w-xs">
                        <?= h($row['description'])?>
                    </div>

                    <?php
        // Fetch attachments
        $atts = $pdo->prepare("SELECT * FROM modification_attachments WHERE modification_id = ?");
        $atts->execute([$row['id']]);
        if ($attachments = $atts->fetchAll()): ?>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <?php foreach ($attachments as $att): ?>
                        <a href="<?= SITE_URL?>/<?= h($att['file_path'])?>" target="_blank"
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 hover:bg-blue-200"
                            title="<?= h($att['display_name'])?>">
                            <i class="fas fa-paperclip mr-1"></i>
                            <?= h($att['display_name'])?>
                        </a>
                        <?php
            endforeach; ?>
                    </div>
                    <?php
        endif; ?>
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
    function openStatusModaatus, notes) {
        document.getElementById('modal_mod_id').value = id;
        document.getElementById('modal_status').value = status;
        document.getElementById('modal_notes').value = notes;
        document.getElementById('statusModal').classList.remove('hidden');
    }
</script>
<?php
endif; ?>

<?php require_once 'includes/footer.php'; ?>