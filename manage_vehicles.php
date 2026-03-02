<?php
// manage_vehicles.php
session_start();
require_once 'admin/config/db.php';
require_once 'admin/includes/functions.php';

if (!isset($_SESSION['auth_resident'])) {
    header("Location: resident_portal.php");
    exit;
}

$res = $_SESSION['auth_resident'];
$uid = $res['unit_id'];
$rtype = $res['type'];
$rid = $res['user_id'];
$message = '';
$error = '';

// Max vehicles
$max_vehicles = (int)($pdo->query("SELECT setting_value FROM pet_settings WHERE setting_key = 'max_vehicles_per_unit'")->fetchColumn() ?: 2);

// Handle Vehicle Addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle'])) {
    $count_v_stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE unit_id = ?");
    $count_v_stmt->execute([$uid]);
    if ($max_vehicles > 0 && $count_v_stmt->fetchColumn() >= $max_vehicles) {
        $error = "Maximum vehicle limit reached.";
    } else {
        $reg = strtoupper(trim($_POST['registration']));
        $make = trim($_POST['make_model']);
        $color = trim($_POST['color']);
        if ($reg && $make && $color) {
            try {
                $pdo->prepare("INSERT INTO vehicles (unit_id, resident_type, resident_id, registration, make_model, color) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$uid, $rtype, $rid, $reg, $make, $color]);
                $message = "Vehicle added successfully.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "This vehicle registration already exists.";
                } else {
                    $error = "Error adding vehicle.";
                }
            }
        }
    }
}

// Handle Vehicle Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_vehicle'])) {
    $vid = (int)$_POST['vehicle_id'];
    try {
        $pdo->prepare("DELETE FROM vehicles WHERE id = ? AND unit_id = ?")->execute([$vid, $uid]);
        $message = "Vehicle removed successfully.";
    } catch (PDOException $e) {
        $error = "Error removing vehicle.";
    }
}

// Fetch Vehicles
$vehicles = [];
try {
    $vstmt = $pdo->prepare("SELECT * FROM vehicles WHERE unit_id = ? AND resident_type = ? AND resident_id = ? ORDER BY created_at ASC");
    $vstmt->execute([$uid, $rtype, $rid]);
    $vehicles = $vstmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error fetching vehicles.";
}

require_once 'admin/includes/header.php';
?>

<div class="max-w-4xl mx-auto py-8 px-4">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center shadow-sm">
                    <i class="fas fa-car"></i>
                </div>
                Manage Vehicles
            </h1>
            <p class="text-gray-500 mt-2">Unit <?= h($res['unit_number']) ?> &middot; Register vehicles kept on the property permanently.</p>
        </div>
        <a href="resident_portal.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 px-5 rounded-xl transition shadow-sm flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-2 shadow-sm">
        <i class="fas fa-check-circle text-lg"></i> <?= h($message) ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-2 shadow-sm">
        <i class="fas fa-exclamation-circle text-lg"></i> <?= h($error) ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
        
        <!-- Left: Current Vehicles -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-5 border-b bg-gray-50 flex justify-between items-center">
                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-list text-blue-500"></i> Registered Vehicles
                </h2>
                <div class="text-xs font-bold bg-blue-100 text-blue-800 px-2.5 py-1 rounded-full">
                    <?= count($vehicles) ?> / <?= $max_vehicles == 0 ? 'Unlimited' : $max_vehicles ?>
                </div>
            </div>
            
            <div class="p-5">
                <?php if (empty($vehicles)): ?>
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-car text-gray-300 text-2xl"></i>
                    </div>
                    <p class="text-gray-500 text-sm">No vehicles currently registered to this unit.</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($vehicles as $v): ?>
                    <div class="border-2 border-gray-100 rounded-xl p-4 flex justify-between items-center bg-gray-50 hover:bg-white transition relative group">
                        <div>
                            <div class="font-extrabold text-blue-900 tracking-wider mb-1 text-lg">
                                <?= h($v['registration']) ?>
                            </div>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm text-gray-600">
                                <div><span class="text-gray-400 text-xs">Make/Model:</span><br><?= h($v['make_model']) ?></div>
                                <div><span class="text-gray-400 text-xs">Color:</span><br><?= h($v['color']) ?></div>
                            </div>
                        </div>
                        
                        <form method="POST" onsubmit="return confirm('Are you sure you want to remove this vehicle?');">
                            <input type="hidden" name="vehicle_id" value="<?= $v['id'] ?>">
                            <button type="submit" name="delete_vehicle" class="w-10 h-10 rounded-full bg-red-50 text-red-500 flex items-center justify-center hover:bg-red-500 hover:text-white transition shadow-sm">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Add New Vehicle -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-5 border-b bg-gray-50">
                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-plus-circle text-green-500"></i> Add New Vehicle
                </h2>
            </div>
            
            <div class="p-5">
                <?php if ($max_vehicles > 0 && count($vehicles) >= $max_vehicles): ?>
                <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-6 text-center">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-3xl mb-3"></i>
                    <p class="font-bold text-yellow-800">Vehicle Limit Reached</p>
                    <p class="text-sm text-yellow-700 mt-1">You have reached the maximum allowed vehicles (<?= $max_vehicles ?>) for this unit.</p>
                </div>
                <?php else: ?>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Registration (Number Plate) <span class="text-red-500">*</span></label>
                        <input type="text" name="registration" required placeholder="e.g. CA 123-456"
                            class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-blue-400 outline-none uppercase transition">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Make & Model <span class="text-red-500">*</span></label>
                        <input type="text" name="make_model" required placeholder="e.g. Toyota Hilux"
                            class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-blue-400 outline-none transition">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Color <span class="text-red-500">*</span></label>
                        <input type="text" name="color" required placeholder="e.g. White"
                            class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-blue-400 outline-none transition">
                    </div>
                    
                    <button type="submit" name="add_vehicle" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl shadow-md transition flex justify-center items-center gap-2 mt-4">
                        <i class="fas fa-plus"></i> Add Vehicle Record
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<?php require_once 'admin/includes/footer.php'; ?>
