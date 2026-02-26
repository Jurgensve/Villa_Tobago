<?php
// modification_form.php
session_start();
require_once 'admin/config/db.php';
require_once 'admin/includes/functions.php';

if (!isset($_SESSION['auth_resident'])) {
    header("Location: resident_portal.php");
    exit;
}

$res = $_SESSION['auth_resident'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    // owner_id from auth_resident is different for tenants. 
    // If tenant, how to get owner_id? Modification is bound to owner_id.
    // For now, let's fetch the current owner for this unit.
    $stmtOwnerId = $pdo->prepare("SELECT owner_id FROM ownership_history WHERE unit_id = ? AND is_current = 1 LIMIT 1");
    $stmtOwnerId->execute([$res['unit_id']]);
    $owner_id = $stmtOwnerId->fetchColumn() ?: 0;

    if (empty($category) || empty($description)) {
        $error = "Category and Description are required.";
    }
    else {
        try {
            $stmt = $pdo->prepare("INSERT INTO modifications (unit_id, owner_id, category, description, status, request_date) VALUES (?, ?, ?, ?, 'Pending', NOW())");
            $stmt->execute([$res['unit_id'], $owner_id, $category, $description]);
            $mod_id = $pdo->lastInsertId();

            // Handle Multiple Attachments
            if (isset($_FILES['attachments'])) {
                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['attachments']['error'][$key] == 0) {
                        $filename = $_FILES['attachments']['name'][$key];
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        $display_name = trim($_POST['attachment_names'][$key] ?? 'Attachment ' . ($key + 1));

                        $new_filename = uniqid() . '_' . $key . '.' . $ext;
                        $target_dir = UPLOAD_DIR . 'modifications/';
                        if (!file_exists($target_dir))
                            mkdir($target_dir, 0755, true);

                        $target_path = $target_dir . $new_filename;
                        if (move_uploaded_file($tmp_name, $target_path)) {
                            $relative_path = 'uploads/modifications/' . $new_filename;
                            $att_stmt = $pdo->prepare("INSERT INTO modification_attachments (modification_id, file_path, display_name) VALUES (?, ?, ?)");
                            $att_stmt->execute([$mod_id, $relative_path, $display_name]);
                        }
                    }
                }
            }
            $message = "Modification request submitted successfully. It is now Pending review by Trustees.";
        }
        catch (Exception $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Modification Request</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">New Unit Modification</h1>
            <a href="resident_portal.php" class="text-blue-600 hover:text-blue-800">Back to Portal</a>
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

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Category *</label>
                <select name="category" class="w-full border p-2 rounded" required>
                    <option value="">-- Select Category --</option>
                    <option value="Gas Installation">Gas Installation</option>
                    <option value="Patio Cover Modification">Patio Cover Modification</option>
                    <option value="External Windows or Doors">External Windows or Doors</option>
                    <option value="Aircon Installation">Aircon Installation</option>
                    <option value="Solar Power System">Solar Power System</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Detailed Description *</label>
                <textarea name="description" class="w-full border p-2 rounded" rows="5"
                    placeholder="Specify brands, contractors, sizes, colors, etc." required></textarea>
            </div>

            <div class="bg-gray-50 border p-4 rounded mb-6">
                <h3 class="font-bold border-b pb-2 mb-2 flex justify-between">
                    Attachments
                    <button type="button" onclick="addAttachmentRow()" class="text-blue-600 text-sm hover:underline"><i
                            class="fas fa-plus"></i> Add file</button>
                </h3>
                <div id="attachments-container" class="space-y-4">
                    <div class="attachment-row border p-2 bg-white rounded flex gap-4">
                        <div class="flex-1">
                            <input type="text" name="attachment_names[]" class="w-full border px-2 text-sm"
                                placeholder="e.g. Quote / Plan">
                        </div>
                        <div class="flex-1">
                            <input type="file" name="attachments[]" class="w-full text-sm">
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit"
                class="bg-blue-600 text-white px-4 py-2 rounded font-bold w-full hover:bg-blue-700">Submit Modification
                Request</button>
        </form>
    </div>

    <script>
        function addAttachmentRow() {
            const container = document.getElementById('attachments-container');
            const clone = container.querySelector('.attachment-row').cloneNode(true);
            clone.querySelector('input[type="text"]').value = '';
            clone.querySelector('input[type="file"]').value = '';
            container.appendChild(clone);
        }
    </script>
</body>

</html>