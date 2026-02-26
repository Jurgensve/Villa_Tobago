<?php
require_once 'includes/header.php';
require_role(['admin', 'managing_agent']); // Trustees: approvals only

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, "r")) !== FALSE) {
        // Skip header row if exists
        $header = fgetcsv($handle, 1000, ",");

        $count = 0;
        $skipped = 0;
        $errors = [];
        try {
            $pdo->beginTransaction();
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Handle semi-colon delimited files if comma fails
                if (count($data) == 1 && strpos($data[0], ';') !== false) {
                    $data = str_getcsv($data[0], ';');
                }

                $unit_num = trim($data[0] ?? '');
                $name = trim($data[1] ?? '');

                if (empty($name) || empty($unit_num)) {
                    $skipped++;
                    continue;
                }

                $id_num = trim($data[2] ?? '');
                $email = trim($data[3] ?? '');
                $phone = trim($data[4] ?? '');

                // 1. Find or Create Unit
                $stmt = $pdo->prepare("SELECT id FROM units WHERE unit_number = ?");
                $stmt->execute([$unit_num]);
                $unit_id = $stmt->fetchColumn();

                if (!$unit_id) {
                    $stmt = $pdo->prepare("INSERT INTO units (unit_number) VALUES (?)");
                    $stmt->execute([$unit_num]);
                    $unit_id = $pdo->lastInsertId();
                }

                // 2. Create Owner
                $stmt = $pdo->prepare("INSERT INTO owners (full_name, id_number, email, phone) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $id_num, $email, $phone]);
                $owner_id = $pdo->lastInsertId();

                // 3. Link them
                $stmt = $pdo->prepare("UPDATE ownership_history SET is_current = 0, end_date = NOW() WHERE unit_id = ? AND is_current = 1");
                $stmt->execute([$unit_id]);
                $stmt = $pdo->prepare("INSERT INTO ownership_history (unit_id, owner_id, start_date, is_current) VALUES (?, ?, NOW(), 1)");
                $stmt->execute([$unit_id, $owner_id]);

                $count++;
            }
            $pdo->commit();
            $message = "Successfully imported $count owners.";
            if ($skipped > 0)
                $message .= " ($skipped rows skipped due to missing Unit/Name)";
            if ($count === 0 && $skipped > 0)
                $error = "No valid data found. Check your CSV format (Unit Number, Name, ID, Email, Phone).";
        }
        catch (Exception $e) {
            $pdo->rollBack();
            $error = "Import failed on row " . ($count + $skipped + 1) . ": " . $e->getMessage();
        }
        fclose($handle);
    }
    else {
        $error = "Could not open file.";
    }
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Import Owners</h1>
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

<div class="bg-white shadow rounded-lg p-6 max-w-lg">
    <p class="mb-4 text-gray-600">
        Upload a CSV file to bulk import owners and assign them to units. <br>
        <strong>Format:</strong> Unit Number, Full Name, ID Number, Email, Phone
    </p>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="csv_file">CSV File</label>
            <input
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                id="csv_file" name="csv_file" type="file" accept=".csv" required>
        </div>
        <button
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
            type="submit">
            Import Owners
        </button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>