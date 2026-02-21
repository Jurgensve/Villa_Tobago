<?php
require_once 'includes/header.php';

$message = '';
$error = '';

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
                        <input type="checkbox" name="pet_management_enabled" value="1" <?=$pet_enabled ? 'checked' : ''?> class="sr-only peer">
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
</div>

<?php require_once 'includes/footer.php'; ?>