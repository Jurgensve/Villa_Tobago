<?php
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

// Fetch current settings
$settings_rows = $pdo->query("SELECT setting_key, setting_value FROM pet_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$pet_enabled = $settings_rows['pet_management_enabled'] ?? '1';
$max_pets = $settings_rows['max_pets_per_unit'] ?? '2';
$allowed_types = $settings_rows['allowed_pet_types'] ?? 'Dog, Cat, Bird, Fish';
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
                        <input type="checkbox" name="pet_management_enabled" value="1" <?= $pet_enabled ? 'checked' : ''
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

            </div>
        </div>

        <div>
            <button type="submit" name="save_settings"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow transition duration-150">
                <i class="fas fa-save mr-2"></i> Save Settings
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