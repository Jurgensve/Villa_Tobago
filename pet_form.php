<?php
// pet_form.php
require_once 'admin/config/db.php';

if (!isset($_SESSION['auth_resident'])) {
    header("Location: resident_portal.php");
    exit;
}

$res = $_SESSION['auth_resident'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type = trim($_POST['type']);
    $breed = trim($_POST['breed']);
    $reg_number = trim($_POST['reg_number']);
    $notes = trim($_POST['notes']);

    if (empty($name) || empty($type)) {
        $error = "Pet Name and Type are required.";
    }
    else {
        try {
            // First ensure resident is in the residents table
            $stmt = $pdo->prepare("SELECT id FROM residents WHERE unit_id = ? AND resident_type = ? AND resident_id = ?");
            $stmt->execute([$res['unit_id'], $res['type'], $res['id']]);
            $resident_id_record = $stmt->fetchColumn();

            if (!$resident_id_record) {
                $ins = $pdo->prepare("INSERT INTO residents (unit_id, resident_type, resident_id) VALUES (?, ?, ?)");
                $ins->execute([$res['unit_id'], $res['type'], $res['id']]);
                $resident_id_record = $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare("INSERT INTO pets (unit_id, resident_id, name, type, breed, reg_number, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
            $stmt->execute([$res['unit_id'], $resident_id_record, $name, $type, $breed, $reg_number, $notes]);
            $message = "Pet Application submitted successfully. It is now Pending review by the Trustees.";
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
    <title>Pet Application</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">New Pet Application</h1>
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

        <form method="POST">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Pet Name *</label>
                    <input type="text" name="name" class="w-full border p-2 rounded" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Pet Type *</label>
                    <select name="type" class="w-full border p-2 rounded" required>
                        <option value="">-- Select --</option>
                        <option value="Dog">Dog</option>
                        <option value="Cat">Cat</option>
                        <option value="Bird">Bird</option>
                        <option value="Fish">Fish</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Breed</label>
                    <input type="text" name="breed" class="w-full border p-2 rounded">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Registration/Microchip #</label>
                    <input type="text" name="reg_number" class="w-full border p-2 rounded">
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Additional Notes / Upload Context</label>
                <textarea name="notes" class="w-full border p-2 rounded" rows="3"
                    placeholder="If required, please specify height/weight or link to pictures."></textarea>
            </div>

            <button type="submit"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-green-700 w-full font-bold">Submit Pet
                Application</button>
        </form>
    </div>
</body>

</html>