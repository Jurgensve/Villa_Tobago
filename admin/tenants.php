<?php
require_once 'includes/header.php';

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_tenant'])) {
        $full_name = trim($_POST['full_name']);
        $unit_id = $_POST['unit_id'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $id_number = $_POST['id_number'];

        // Handle File Upload
        $lease_path = null;
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
                $stmt = $pdo->prepare("INSERT INTO tenants (unit_id, full_name, id_number, email, phone, lease_agreement_path, start_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$unit_id, $full_name, $id_number, $email, $phone, $lease_path]);
                $message = "Tenant added successfully.";
                $action = 'list';
            }
            catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
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

<?php if ($action === 'add'): ?>
<?php $units = $pdo->query("SELECT id, unit_number FROM units ORDER BY unit_number ASC")->fetchAll(); ?>
<div class="bg-white shadow rounded-lg p-6 max-w-2xl">
    <h2 class="text-xl font-semibold mb-4">Add New Tenant</h2>
    <form method="POST" enctype="multipart/form-data">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="mb-4 md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="unit_id">Unit *</label>
                <select
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="unit_id" name="unit_id" required>
                    <option value="">-- Select Unit --</option>
                    <?php foreach ($units as $unit): ?>
                    <option value="<?= $unit['id']?>" <?=(isset($_GET['unit_id']) && $_GET['unit_id']==$unit['id'])
                        ? 'selected' : ''?>>
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
            <div class="mb-4 md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="lease_agreement">Lease Agreement
                    (PDF/Image)</label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="lease_agreement" name="lease_agreement" type="file" accept=".pdf,.jpg,.jpeg,.png">
            </div>
        </div>
        <div class="mt-6">
            <button
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                type="submit" name="create_tenant">
                Add Tenant
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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant Name
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lease</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php
    $sql = "SELECT t.*, u.unit_number 
                        FROM tenants t
                        JOIN units u ON t.unit_id = u.id
                        WHERE t.is_active = 1
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
                    <?php if ($row['lease_agreement_path']): ?>
                    <a href="<?= SITE_URL?>/<?= h($row['lease_agreement_path'])?>" target="_blank"
                        class="hover:underline">View
                        Lease</a>
                    <?php
        else: ?>
                    <span class="text-gray-400">No File</span>
                    <?php
        endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <a href="#" class="text-indigo-600 hover:text-indigo-900">Edit</a>
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