<?php
$required_roles = ['admin', 'managing_agent'];
require_once 'includes/header.php';

$message = '';
$error = '';
$debug_log = [];

// Helper to safely get CSV value
function get_csv_val($row, $index)
{
    return isset($row[$index]) ? trim($row[$index]) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_template'])) {
    $headers = [
        "Unit Number", "Main Owner Full Name", "Main Owner ID Number", "Main Owner Email", "Main Owner Contact Number",
        "Second Owner Full Name", "Second Owner ID Number", "Second Owner Email", "Second Owner Contact Number",
        "Resident Full Name", "Resident ID Number", "Resident Email", "Resident Contact Number",
        "Intercom 1 Name", "Intercom 1 Phone", "Intercom 2 Name", "Intercom 2 Phone",
        "Vehicle 1 Reg", "Vehicle 1 Brand/Model", "Vehicle 1 Color",
        "Vehicle 2 Reg", "Vehicle 2 Brand/Model", "Vehicle 2 Color",
        "Pet 1 Name", "Pet 1 Breed", "Pet 1 Size", "Pet 1 Tag",
        "Pet 2 Name", "Pet 2 Breed", "Pet 2 Size", "Pet 2 Tag"
    ];

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="villa_tobago_takeon_template.csv"');
    $fp = fopen('php://output', 'w');
    fputcsv($fp, $headers);
    fclose($fp);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, "r")) !== FALSE) {
        // Skip header row
        $header = fgetcsv($handle, 10000, ",");

        $count = 0;
        $skipped = 0;
        $row_number = 1; // 1 was header

        try {
            while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                $row_number++;

                // Handle semi-colon delimited files if comma fails
                if (count($data) == 1 && strpos($data[0], ';') !== false) {
                    $data = str_getcsv($data[0], ';');
                }

                $unit_num = get_csv_val($data, 0);
                $main_owner_name = get_csv_val($data, 1);

                if (empty($unit_num) || empty($main_owner_name)) {
                    $skipped++;
                    $debug_log[] = "Row $row_number: Skipped - Missing Unit Number or Main Owner Name.";
                    continue;
                }

                $pdo->beginTransaction();

                try {
                    // 1. Find or Create Unit
                    $stmt = $pdo->prepare("SELECT id FROM units WHERE unit_number = ?");
                    $stmt->execute([$unit_num]);
                    $unit_id = $stmt->fetchColumn();

                    if (!$unit_id) {
                        $stmt = $pdo->prepare("INSERT INTO units (unit_number) VALUES (?)");
                        $stmt->execute([$unit_num]);
                        $unit_id = $pdo->lastInsertId();
                    }

                    // Clear existing current ownerships to cleanly import
                    $stmt = $pdo->prepare("UPDATE ownership_history SET is_current = 0, end_date = NOW() WHERE unit_id = ? AND is_current = 1");
                    $stmt->execute([$unit_id]);

                    // 2. Main Owner
                    $stmt = $pdo->prepare("INSERT INTO owners (full_name, id_number, email, phone, portal_access_granted, details_complete, agent_approved, code_of_conduct_accepted, status, agent_approval, move_in_sent) VALUES (?, ?, ?, ?, 1, 1, 1, 1, 'Approved', 1, 1)");
                    $stmt->execute([
                        $main_owner_name, get_csv_val($data, 2), get_csv_val($data, 3), get_csv_val($data, 4)
                    ]);
                    $main_owner_id = $pdo->lastInsertId();

                    $stmt = $pdo->prepare("INSERT INTO ownership_history (unit_id, owner_id, start_date, is_current) VALUES (?, ?, NOW(), 1)");
                    $stmt->execute([$unit_id, $main_owner_id]);

                    $primary_resident_type = 'owner';
                    $primary_resident_id = $main_owner_id;

                    // 3. Second Owner (Optional)
                    $second_owner_name = get_csv_val($data, 5);
                    if (!empty($second_owner_name)) {
                        $stmt = $pdo->prepare("INSERT INTO owners (full_name, id_number, email, phone, portal_access_granted, details_complete, agent_approved, code_of_conduct_accepted, status, agent_approval, move_in_sent) VALUES (?, ?, ?, ?, 1, 1, 1, 1, 'Approved', 1, 1)");
                        $stmt->execute([
                            $second_owner_name, get_csv_val($data, 6), get_csv_val($data, 7), get_csv_val($data, 8)
                        ]);
                        $second_owner_id = $pdo->lastInsertId();

                        $stmt = $pdo->prepare("INSERT INTO ownership_history (unit_id, owner_id, start_date, is_current) VALUES (?, ?, NOW(), 1)");
                        $stmt->execute([$unit_id, $second_owner_id]);

                        // Link second owner as occupant to main owner
                        $stmt = $pdo->prepare("INSERT INTO occupants (associated_type, associated_id, full_name, id_number) VALUES ('owner', ?, ?, ?)");
                        $stmt->execute([$main_owner_id, $second_owner_name, get_csv_val($data, 6)]);
                    }

                    // 4. Resident
                    $resident_name = get_csv_val($data, 9);
                    if (!empty($resident_name)) {
                        $primary_resident_type = 'tenant';

                        $stmt = $pdo->prepare("INSERT INTO tenants (unit_id, full_name, id_number, email, phone, portal_access_granted, details_complete, agent_approved, code_of_conduct_accepted, status, owner_approval, move_in_sent) VALUES (?, ?, ?, ?, ?, 1, 1, 1, 1, 'Approved', 1, 1)");
                        $stmt->execute([
                            $unit_id, $resident_name, get_csv_val($data, 10), get_csv_val($data, 11), get_csv_val($data, 12)
                        ]);
                        $primary_resident_id = $pdo->lastInsertId();
                    }

                    // Update Residents single source of truth table
                    $pdo->prepare(
                        "INSERT INTO residents (unit_id, resident_type, resident_id)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE resident_type = VALUES(resident_type), resident_id = VALUES(resident_id)"
                    )->execute([$unit_id, $primary_resident_type, $primary_resident_id]);

                    // 5. Intercom Contacts (update primary resident)
                    $ic1_name = get_csv_val($data, 13);
                    $ic1_phone = get_csv_val($data, 14);
                    $ic2_name = get_csv_val($data, 15);
                    $ic2_phone = get_csv_val($data, 16);

                    if (!empty($ic1_name) || !empty($ic2_name)) {
                        $table = $primary_resident_type === 'owner' ? 'owners' : 'tenants';
                        $stmt = $pdo->prepare("UPDATE {$table} SET intercom_contact1_name=?, intercom_contact1_phone=?, intercom_contact2_name=?, intercom_contact2_phone=? WHERE id=?");
                        $stmt->execute([$ic1_name, $ic1_phone, $ic2_name, $ic2_phone, $primary_resident_id]);
                    }

                    // 6. Vehicles
                    $v1_reg = get_csv_val($data, 17);
                    if (!empty($v1_reg)) {
                        $stmt = $pdo->prepare("INSERT INTO vehicles (unit_id, resident_type, resident_id, registration, make_model, color) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$unit_id, $primary_resident_type, $primary_resident_id, $v1_reg, get_csv_val($data, 18), get_csv_val($data, 19)]);
                    }
                    $v2_reg = get_csv_val($data, 20);
                    if (!empty($v2_reg)) {
                        $stmt = $pdo->prepare("INSERT INTO vehicles (unit_id, resident_type, resident_id, registration, make_model, color) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$unit_id, $primary_resident_type, $primary_resident_id, $v2_reg, get_csv_val($data, 21), get_csv_val($data, 22)]);
                    }

                    // 7. Pets
                    $p1_name = get_csv_val($data, 23);
                    if (!empty($p1_name)) {
                        $stmt = $pdo->prepare("INSERT INTO pets (unit_id, resident_type, resident_id, name, type, breed, adult_size, wears_id_tag, status, house_rules_accepted) VALUES (?, ?, ?, ?, 'Unknown', ?, ?, ?, 'Approved', 1)");
                        $stmt->execute([$unit_id, $primary_resident_type, $primary_resident_id, $p1_name, get_csv_val($data, 24), get_csv_val($data, 25) ?: null, get_csv_val($data, 26) ? 1 : 0]);
                    }
                    $p2_name = get_csv_val($data, 27);
                    if (!empty($p2_name)) {
                        $stmt = $pdo->prepare("INSERT INTO pets (unit_id, resident_type, resident_id, name, type, breed, adult_size, wears_id_tag, status, house_rules_accepted) VALUES (?, ?, ?, ?, 'Unknown', ?, ?, ?, 'Approved', 1)");
                        $stmt->execute([$unit_id, $primary_resident_type, $primary_resident_id, $p2_name, get_csv_val($data, 28), get_csv_val($data, 29) ?: null, get_csv_val($data, 30) ? 1 : 0]);
                    }

                    $pdo->commit();
                    $count++;

                }
                catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Import failed on row $row_number: " . $e->getMessage();
                    break; // Stop outer loop on fatal DB error
                }
            }

            if (!$error) {
                $message = "Successfully imported $count units with all associated data.";
                if ($skipped > 0)
                    $message .= " ($skipped rows skipped).";
            }
        }
        catch (Exception $e) {
            $error = "File reading error: " . $e->getMessage();
        }
        fclose($handle);
    }
    else {
        $error = "Could not open file.";
    }
}
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-900">Data Take-On Import</h1>
    <form method="POST">
        <button type="submit" name="download_template"
            class="bg-indigo-100 hover:bg-indigo-200 text-indigo-800 font-bold py-2 px-4 rounded-lg shadow-sm transition flex items-center gap-2 text-sm">
            <i class="fas fa-file-csv"></i> Download CSV Template
        </button>
    </form>
</div>

<?php if ($message): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 shadow-sm">
    <i class="fas fa-check-circle mr-2"></i>
    <?= h($message)?>
</div>
<?php
endif; ?>

<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 shadow-sm">
    <i class="fas fa-exclamation-triangle mr-2"></i>
    <?= h($error)?>
</div>
<?php
endif; ?>

<?php if (!empty($debug_log)): ?>
<div
    class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded mb-4 text-sm font-mono h-32 overflow-y-auto">
    <strong>Import Log:</strong><br>
    <?= implode("<br>", array_map('h', $debug_log))?>
</div>
<?php
endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white shadow rounded-xl p-6 border-t-4 border-blue-500">
        <h2 class="font-bold text-gray-800 text-lg mb-2"><i class="fas fa-upload text-blue-500 mr-2"></i> Upload Data
            File</h2>
        <p class="text-sm text-gray-600 mb-5">
            Upload the completed <strong>villa_tobago_takeon_template.csv</strong> file. This process will create units,
            owners, residents, and automatically approve them, mimicking a full system take-on.
        </p>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:bg-gray-50 transition">
                <i class="fas fa-file-excel text-3xl text-gray-400 mb-2"></i>
                <label class="block text-gray-700 text-sm font-bold mb-2 cursor-pointer" for="csv_file">Select CSV
                    File</label>
                <input
                    class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                    id="csv_file" name="csv_file" type="file" accept=".csv" required>
            </div>
            <button
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl shadow transition"
                type="submit">
                <i class="fas fa-play-circle mr-2"></i> Run Import Process
            </button>
        </form>
    </div>

    <div class="bg-gray-50 shadow rounded-xl p-6 border border-gray-200">
        <h3 class="font-bold text-gray-800 mb-3"><i class="fas fa-info-circle text-gray-500 mr-2"></i> Important Notes
        </h3>
        <ul class="text-sm text-gray-600 space-y-2 list-disc list-inside">
            <li><strong>Unit Number</strong> and <strong>Main Owner Full Name</strong> are strictly required. Rows
                without these will be skipped.</li>
            <li>If the <strong>Resident Full Name</strong> is left blank, the Main Owner will be assigned as the active
                residing occupant.</li>
            <li>If a Unit Number already exists, it will overwrite the current ownership history with the new Main
                Owner, but won't delete old data.</li>
            <li>All inserted records (Owners, Tenants, Pets) are automatically marked as <strong>Approved</strong> and
                bypassed the standard portal review.</li>
            <li>Ensure date formats (if any) are valid within Excel before exporting to CSV.</li>
        </ul>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>