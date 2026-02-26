<?php
$required_roles = ['admin', 'managing_agent'];
require_once 'includes/header.php';

$message = '';
$error = '';

// Handle Bulk Action: Set Owners as Residents
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_set_owner_residents'])) {
    try {
        // Find all units that have a current owner but NO tenant record at all
        $units_to_update = $pdo->query(
            "SELECT oh.unit_id, oh.owner_id
             FROM ownership_history oh
             WHERE oh.is_current = 1
               AND oh.unit_id NOT IN (SELECT DISTINCT unit_id FROM tenants)
               AND oh.unit_id NOT IN (SELECT unit_id FROM residents)
             GROUP BY oh.unit_id
             ORDER BY oh.owner_id ASC"
        )->fetchAll();

        $count = 0;
        $stmt = $pdo->prepare(
            "INSERT INTO residents (unit_id, resident_type, resident_id)
             VALUES (?, 'owner', ?)
             ON DUPLICATE KEY UPDATE resident_type = 'owner', resident_id = VALUES(resident_id)"
        );
        foreach ($units_to_update as $row) {
            $stmt->execute([$row['unit_id'], $row['owner_id']]);
            $count++;
        }
        $message = "Done! Set $count unit(s) where the owner is now the default resident.";
    }
    catch (PDOException $e) {
        $error = "Error: " . $e->getMessage() . " (Have you run the migration yet?)";
    }
}

// Handle Database Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_database'])) {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $pdo->exec("TRUNCATE TABLE amendment_logs;");
        $pdo->exec("TRUNCATE TABLE pets;");
        $pdo->exec("TRUNCATE TABLE modifications;");
        $pdo->exec("TRUNCATE TABLE occupants;");
        $pdo->exec("TRUNCATE TABLE residents;");
        $pdo->exec("TRUNCATE TABLE tenants;");
        $pdo->exec("TRUNCATE TABLE ownership_history;");
        $pdo->exec("TRUNCATE TABLE owners;");
        $pdo->exec("TRUNCATE TABLE units;");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        $message = "System Database has been completely reset.";
    }
    catch (PDOException $e) {
        $error = "Error resetting database: " . $e->getMessage();
    }
}

// Handle Settings Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $settings = [
            'pet_management_enabled' => isset($_POST['pet_management_enabled']) ? '1' : '0',
            'max_pets_per_unit' => (int)$_POST['max_pets_per_unit'],
            'allowed_pet_types' => trim($_POST['allowed_pet_types']),
            'max_vehicles_per_unit' => (int)$_POST['max_vehicles_per_unit'],
        ];

        $stmt = $pdo->prepare("INSERT INTO pet_settings (setting_key, setting_value) 
                                VALUES (?, ?) 
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        $message = "Settings saved successfully.";
    }
    catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle System / Notification Settings Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_system_settings'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value)
                               VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

        $stmt->execute(['security_email', trim($_POST['security_email'] ?? '')]);
        $stmt->execute(['footer_text', trim($_POST['footer_text'] ?? '')]);
        $stmt->execute(['max_truck_gwm', (int)($_POST['max_truck_gwm'] ?? 3500)]);

        if (!empty($_FILES['logo_file']['name'])) {
            $logo_ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
            $logo_dest = __DIR__ . '/../uploads/logo.' . $logo_ext;
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $logo_dest)) {
                $stmt->execute(['logo_path', 'uploads/logo.' . $logo_ext]);
            }
        }

        if (!empty($_FILES['rules_pdf']['name'])) {
            $pdf_dest = __DIR__ . '/../uploads/complex_rules.pdf';
            if (move_uploaded_file($_FILES['rules_pdf']['tmp_name'], $pdf_dest)) {
                $stmt->execute(['complex_rules_pdf', 'uploads/complex_rules.pdf']);
            }
        }

        $total_units_new = (int)($_POST['total_units'] ?? 0);
        if ($total_units_new > 0) {
            $current_count = $pdo->query("SELECT COUNT(*) FROM units")->fetchColumn();
            if ($current_count == 0) {
                $stmt->execute(['total_units', $total_units_new]);
            }
            else {
                $error = "Total Units is locked: {$current_count} unit record(s) already exist in the database.";
            }
        }

        if (!$error)
            $message = "System settings saved successfully.";
    }
    catch (PDOException $e) {
        $error = "Error saving system settings: " . $e->getMessage() . ". (Have you run the move_logistics_schema.sql migration?)";
    }
}

// Fetch current pet settings
$settings_rows = $pdo->query("SELECT setting_key, setting_value FROM pet_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$pet_enabled = $settings_rows['pet_management_enabled'] ?? '1';
$max_pets = $settings_rows['max_pets_per_unit'] ?? '2';
$allowed_types = $settings_rows['allowed_pet_types'] ?? 'Dog, Cat, Bird, Fish';
$max_vehicles = $settings_rows['max_vehicles_per_unit'] ?? '2';

// Fetch system settings
$security_email_val = '';
$footer_text_val = '';
$logo_path_val = '';
$rules_pdf_val = '';
$total_units_val = 0;
$current_units_count = 0;
try {
    $sys = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $security_email_val = $sys['security_email'] ?? '';
    $footer_text_val = $sys['footer_text'] ?? '';
    $logo_path_val = $sys['logo_path'] ?? '';
    $rules_pdf_val = $sys['complex_rules_pdf'] ?? '';
    $max_truck_gwm_val = (int)($sys['max_truck_gwm'] ?? 3500);
    $total_units_val = (int)($sys['total_units'] ?? 0);
    $current_units_count = (int)$pdo->query("SELECT COUNT(*) FROM units")->fetchColumn();
}
catch (PDOException $e) { /* Migration not run yet */
}
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-900">Settings</h1>
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

<div class="max-w-2xl">
    <form method="POST" class="space-y-8">

        <!-- Pet Policy Section -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b flex items-center">
                <i class="fas fa-paw mr-3 text-indigo-500"></i>
                <h2 class="text-lg font-bold text-gray-800">Pet Management Policy</h2>
            </div>
            <div class="p-6 space-y-6">

                <!-- Enable/Disable -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border">
                    <div>
                        <p class="font-bold text-gray-800">Enable Pet Management</p>
                        <p class="text-sm text-gray-500">Allow pets to be registered against residents.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="pet_management_enabled" value="1" <?=$pet_enabled ? 'checked' : ''
    ?> class="sr-only peer">
                        <div
                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
                        </div>
                    </label>
                </div>

                <!-- Max Pets -->
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="max_pets">
                        Maximum Pets Per Unit
                    </label>
                    <input type="number" id="max_pets" name="max_pets_per_unit" value="<?= h($max_pets)?>" min="0"
                        max="20"
                        class="shadow border rounded w-32 py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline">
                    <p class="text-xs text-gray-500 mt-1">Set to 0 for unlimited. Policy enforced during pet
                        registration.</p>
                </div>

                <!-- Allowed Pet Types -->
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="allowed_pet_types">
                        Allowed Pet Types
                    </label>
                    <input type="text" id="allowed_pet_types" name="allowed_pet_types" value="<?= h($allowed_types)?>"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                        placeholder="e.g. Dog, Cat, Bird, Fish">
                    <p class="text-xs text-gray-500 mt-1">Comma-separated list of allowed pet types.</p>
                </div>

                <!-- Max Vehicles Per Unit -->
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="max_vehicles">
                        Maximum Vehicles Per Unit
                    </label>
                    <input type="number" id="max_vehicles" name="max_vehicles_per_unit" value="<?= h($max_vehicles)?>"
                        min="0" max="10"
                        class="shadow border rounded w-32 py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline">
                    <p class="text-xs text-gray-500 mt-1">Set to 0 for unlimited. Enforced in the Resident Portal when
                        residents register vehicles.</p>
                </div>

            </div>
        </div>

        <div>
            <button type="submit" name="save_settings"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow transition duration-150">
                <i class="fas fa-save mr-2"></i> Save Settings
            </button>
        </div>
    </form>

    <!-- System Configuration Settings -->
    <form method="POST" enctype="multipart/form-data" class="mt-8">
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b flex items-center">
                <i class="fas fa-cogs mr-3 text-blue-500"></i>
                <h2 class="text-lg font-bold text-gray-800">System Configuration</h2>
            </div>
            <div class="p-6 space-y-6">

                <!-- Security Gate Email -->
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="security_email">
                        <i class="fas fa-shield-alt text-gray-400 mr-1"></i> Security Gate Email Address
                    </label>
                    <input type="email" id="security_email" name="security_email" value="<?= h($security_email_val)?>"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none"
                        placeholder="e.g. security@villatobago.co.za">
                    <p class="text-xs text-gray-500 mt-1">Move-in and move-out notifications will be emailed here. Leave
                        blank to disable.</p>
                </div>

                <!-- Footer Text -->
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="footer_text">
                        <i class="fas fa-align-center text-gray-400 mr-1"></i> Resident-Facing Footer Text
                    </label>
                    <input type="text" id="footer_text" name="footer_text" value="<?= h($footer_text_val)?>"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none"
                        placeholder="e.g. Villa Tobago · enquiries@villatobago.co.za">
                    <p class="text-xs text-gray-500 mt-1">Shown in the footer of public-facing pages (move forms,
                        approval pages).</p>
                </div>

                <!-- Logo Upload -->
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-image text-gray-400 mr-1"></i> Complex Logo
                    </label>
                    <?php if ($logo_path_val): ?>
                    <div class="mb-2 flex items-center gap-3">
                        <img src="<?= SITE_URL . '/' . h($logo_path_val)?>" class="h-12 rounded border border-gray-200"
                            alt="Logo">
                        <span class="text-xs text-green-600 font-bold"><i class="fas fa-check-circle"></i> Logo
                            uploaded</span>
                    </div>
                    <?php
endif; ?>
                    <input type="file" name="logo_file" accept="image/*"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-500 mt-1">PNG or JPG. Saved to <code>uploads/logo.*</code>.</p>
                </div>

                <!-- Body Corporate Rules PDF -->
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-file-pdf text-red-400 mr-1"></i> Body Corporate Rules (PDF)
                    </label>
                    <?php if ($rules_pdf_val): ?>
                    <div class="mb-2">
                        <a href="<?= SITE_URL . '/' . h($rules_pdf_val)?>" target="_blank"
                            class="text-blue-600 hover:underline text-sm font-bold">
                            <i class="fas fa-external-link-alt mr-1"></i> View Current Rules PDF
                        </a>
                    </div>
                    <?php
endif; ?>
                    <input type="file" name="rules_pdf" accept="application/pdf"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-bold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                    <p class="text-xs text-gray-500 mt-1">Saved to <code>uploads/complex_rules.pdf</code>. Residents can
                        download this document.</p>
                </div>

                <!-- Maximum Permitted Truck GWM -->
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="max_truck_gwm">
                        <i class="fas fa-truck-moving text-blue-400 mr-1"></i> Maximum Permitted Truck GWM (kg)
                    </label>
                    <input type="number" id="max_truck_gwm" name="max_truck_gwm" value="<?= $max_truck_gwm_val?>"
                        class="shadow border rounded w-32 py-2 px-3 text-gray-700 focus:outline-none" step="1" min="0">
                    <p class="text-xs text-gray-500 mt-1">Maximum allowed weight for moving trucks. Residents will be
                        warned if they exceed this limit during move-in/out.</p>
                </div>

                <!-- System Initialization: Total Units -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <label class="block text-yellow-800 text-sm font-bold mb-2">
                        <i class="fas fa-lock text-yellow-500 mr-1"></i> Total Units in Complex
                        <?php if ($current_units_count > 0): ?>
                        <span class="ml-2 text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-bold">
                            LOCKED –
                            <?= $current_units_count?> units exist
                        </span>
                        <?php
endif; ?>
                    </label>
                    <input type="number" name="total_units" value="<?= $total_units_val ?: ''?>"
                        <?=$current_units_count> 0 ? 'disabled' : ''?>
                    min="1" max="1000"
                    class="shadow border rounded w-32 py-2 px-3 text-gray-700 focus:outline-none
                    <?= $current_units_count > 0 ? 'opacity-50 cursor-not-allowed' : ''?>"
                    placeholder="e.g. 72">
                    <p class="text-xs text-yellow-700 mt-2">
                        <strong>One-time setup:</strong> Set the total number of units. Locked once unit records exist.
                    </p>
                </div>

            </div>
        </div>
        <div class="mt-4">
            <button type="submit" name="save_system_settings"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow transition duration-150">
                <i class="fas fa-save mr-2"></i> Save System Settings
            </button>
        </div>
    </form>


    <div class="mt-8 bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b flex items-center">
            <i class="fas fa-bolt mr-3 text-orange-500"></i>
            <h2 class="text-lg font-bold text-gray-800">Bulk Actions</h2>
        </div>
        <div class="p-6 space-y-5">
            <div class="flex items-start justify-between p-4 bg-orange-50 rounded-lg border border-orange-100">
                <div class="mr-6">
                    <p class="font-bold text-gray-800">Set Owners as Default Residents</p>
                    <p class="text-sm text-gray-500 mt-1">
                        For every unit that has a current owner but <strong>no tenant</strong> and
                        <strong>no resident record</strong> yet, this will set the owner as the current resident.
                        Units already having an explicit resident or any tenant record are skipped.
                    </p>
                </div>
                <form method="POST" onsubmit="return confirm('This will update all eligible units. Continue?')">
                    <button type="submit" name="bulk_set_owner_residents"
                        class="whitespace-nowrap bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded shadow transition duration-150">
                        <i class="fas fa-user-check mr-2"></i> Run
                    </button>
                </form>
            </div>

            <div class="flex items-start justify-between p-4 bg-red-50 rounded-lg border border-red-100 mt-4">
                <div class="mr-6">
                    <p class="font-bold text-red-800">Reset System Database</p>
                    <p class="text-sm text-red-500 mt-1">
                        <strong>DANGER:</strong> This will delete all owners, tenants, pets, modifications, and
                        application records. This action cannot be undone!
                    </p>
                </div>
                <form method="POST"
                    onsubmit="return confirm('WARNING: Are you sure you want to completely erase all system data? This cannot be undone!')">
                    <button type="submit" name="reset_database"
                        class="whitespace-nowrap bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded shadow transition duration-150">
                        <i class="fas fa-trash-alt mr-2"></i> Factory Reset
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>