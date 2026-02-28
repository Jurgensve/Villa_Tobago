<?php require_once 'includes/header.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); ?>

<?php
// Fetch some quick stats
$stmt = $pdo->query("SELECT COUNT(*) FROM units");
$unit_count = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM owners WHERE is_active = 1");
$owner_count = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM tenants WHERE is_active = 1");
$tenant_count = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM modifications WHERE status = 'pending'");
$pending_modifications = $stmt->fetchColumn();

$pending_moves = 0;
$pending_approvals = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM move_logistics WHERE status = 'Pending'");
    $pending_moves = $stmt->fetchColumn();
    $pending_approvals = $pdo->query("SELECT COUNT(*) FROM tenants WHERE status NOT IN ('Approved','Declined','Completed') AND is_active=1")->fetchColumn()
        + $pdo->query("SELECT COUNT(*) FROM owners WHERE COALESCE(status,'Pending') NOT IN ('Approved','Declined','Completed') AND is_active=1")->fetchColumn();
}
catch (PDOException $e) { /* Tables not yet created */
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
    <p class="text-gray-500 text-sm mt-1">Overview and actionable alerts for Villa Tobago.</p>
</div>

<!-- Key Stats Overview -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white overflow-hidden shadow rounded-lg px-4 py-5 sm:p-6 border-l-4 border-blue-500">
        <dt class="text-sm font-medium text-gray-500 truncate">Total Units</dt>
        <dd class="mt-1 text-3xl font-semibold text-gray-900">
            <?= $unit_count?>
        </dd>
    </div>
    <div class="bg-white overflow-hidden shadow rounded-lg px-4 py-5 sm:p-6 border-l-4 border-green-500">
        <dt class="text-sm font-medium text-gray-500 truncate">Active Owners</dt>
        <dd class="mt-1 text-3xl font-semibold text-gray-900">
            <?= $owner_count?>
        </dd>
    </div>
    <div class="bg-white overflow-hidden shadow rounded-lg px-4 py-5 sm:p-6 border-l-4 border-purple-500">
        <dt class="text-sm font-medium text-gray-500 truncate">Active Tenants</dt>
        <dd class="mt-1 text-3xl font-semibold text-gray-900">
            <?= $tenant_count?>
        </dd>
    </div>
    <div class="bg-white overflow-hidden shadow rounded-lg px-4 py-5 sm:p-6 border-l-4 border-red-500">
        <dt class="text-sm font-medium text-gray-500 truncate">Total Action Required</dt>
        <dd class="mt-1 text-3xl font-semibold text-gray-900">
            <?=($pending_modifications + $pending_moves + $pending_approvals)?>
        </dd>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Action Required Feed -->
    <div class="lg:col-span-2">
        <div class="bg-white shadow sm:rounded-lg overflow-hidden border border-gray-200">
            <div class="px-4 py-5 sm:px-6 bg-red-50 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg leading-6 font-bold text-red-800"><i class="fas fa-bell mr-2"></i> Action Required
                    Feed</h3>
                <span class="bg-red-200 text-red-800 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">
                    <?=($pending_modifications + $pending_moves + $pending_approvals)?> Pending
                </span>
            </div>

            <ul class="divide-y divide-gray-200">
                <?php
$has_alerts = false;

// Fetch Pending Modifications
try {
    $mods = $pdo->query("SELECT m.id, m.unit_id, m.modification_type, m.created_at, u.unit_number FROM modifications m JOIN units u ON m.unit_id = u.id WHERE m.status = 'pending' ORDER BY m.created_at DESC")->fetchAll();
    foreach ($mods as $mod) {
        $has_alerts = true;
        echo "<li class='hover:bg-gray-50 transition'>
                                <a href='units.php?action=view&id={$mod['unit_id']}#modifications' class='block px-4 py-4 sm:px-6'>
                                    <div class='flex items-center justify-between'>
                                        <div class='flex items-center'>
                                            <div class='bg-yellow-100 text-yellow-600 rounded-full p-2 mr-4'><i class='fas fa-hammer fa-fw'></i></div>
                                            <div>
                                                <p class='text-sm font-bold text-gray-900'>Unit {$mod['unit_number']} — Pending Modification</p>
                                                <p class='text-xs text-gray-500 uppercase mt-0.5'>Type: " . h($mod['modification_type']) . " • Opened: " . date('M j, Y', strtotime($mod['created_at'])) . "</p>
                                            </div>
                                        </div>
                                        <div><i class='fas fa-chevron-right text-gray-400'></i></div>
                                    </div>
                                </a>
                              </li>";
    }
}
catch (Exception $e) {
}

// Fetch Pending Moves
try {
    $moves = $pdo->query("SELECT ml.id, ml.unit_id, ml.move_type, ml.preferred_date, u.unit_number FROM move_logistics ml JOIN units u ON ml.unit_id = u.id WHERE ml.status = 'Pending' ORDER BY ml.created_at DESC")->fetchAll();
    foreach ($moves as $move) {
        $has_alerts = true;
        $move_type_str = $move['move_type'] === 'move_in' ? 'Move-In' : 'Move-Out';
        echo "<li class='hover:bg-gray-50 transition'>
                                <a href='units.php?action=view&id={$move['unit_id']}#logistics' class='block px-4 py-4 sm:px-6'>
                                    <div class='flex items-center justify-between'>
                                        <div class='flex items-center'>
                                            <div class='bg-blue-100 text-blue-600 rounded-full p-2 mr-4'><i class='fas fa-truck-moving fa-fw'></i></div>
                                            <div>
                                                <p class='text-sm font-bold text-gray-900'>Unit {$move['unit_number']} — Pending {$move_type_str}</p>
                                                <p class='text-xs text-gray-500 uppercase mt-0.5'>Requested Date: " . ($move['preferred_date'] ? date('M j, Y', strtotime($move['preferred_date'])) : 'Unknown') . "</p>
                                            </div>
                                        </div>
                                        <div><i class='fas fa-chevron-right text-gray-400'></i></div>
                                    </div>
                                </a>
                              </li>";
    }
}
catch (Exception $e) {
}

// Fetch Pending Residents (Tenants)
try {
    $tenants = $pdo->query("SELECT t.id, t.unit_id, t.full_name, u.unit_number FROM tenants t JOIN units u ON t.unit_id = u.id WHERE t.status NOT IN ('Approved','Declined','Completed') AND t.is_active=1 ORDER BY t.created_at DESC")->fetchAll();
    foreach ($tenants as $t) {
        $has_alerts = true;
        echo "<li class='hover:bg-gray-50 transition'>
                                <a href='resident_application.php?unit_id={$t['unit_id']}' class='block px-4 py-4 sm:px-6'>
                                    <div class='flex items-center justify-between'>
                                        <div class='flex items-center'>
                                            <div class='bg-green-100 text-green-600 rounded-full p-2 mr-4'><i class='fas fa-user-plus fa-fw'></i></div>
                                            <div>
                                                <p class='text-sm font-bold text-gray-900'>Unit {$t['unit_number']} — Pending Tenant Approval</p>
                                                <p class='text-xs text-gray-500 uppercase mt-0.5'>" . h($t['full_name']) . " requires sub-approvals or final review.</p>
                                            </div>
                                        </div>
                                        <div><i class='fas fa-chevron-right text-gray-400'></i></div>
                                    </div>
                                </a>
                              </li>";
    }
}
catch (Exception $e) {
}

if (!$has_alerts):
?>
                <li class="px-4 py-8 sm:px-6 text-center text-gray-500 italic">
                    <i class="fas fa-check-double text-4xl text-gray-300 mb-3 block"></i>
                    All caught up! No pending actions require your attention.
                </li>
                <?php
endif; ?>
            </ul>
        </div>
    </div>

    <!-- Quick Actions Sidebar -->
    <div class="lg:col-span-1">
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6 border-b border-gray-100">
                <h3 class="text-lg leading-6 font-bold text-gray-900"><i class="fas fa-bolt text-yellow-500 mr-2"></i>
                    Quick Actions</h3>
            </div>
            <div class="p-4 space-y-3">
                <a href="units.php"
                    class="flex items-center justify-center w-full px-4 py-3 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                    <i class="fas fa-building mr-2 text-gray-400"></i> Browse All Units
                </a>
                <a href="modifications.php?action=add"
                    class="flex items-center justify-center w-full px-4 py-3 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition">
                    <i class="fas fa-hammer mr-2"></i> Log Modification
                </a>
                <a href="owners.php?action=add"
                    class="flex items-center justify-center w-full px-4 py-3 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 shadow-sm transition">
                    <i class="fas fa-user-tie mr-2"></i> Add New Owner
                </a>
                <a href="tenants.php?action=add"
                    class="flex items-center justify-center w-full px-4 py-3 border border-transparent text-sm font-medium rounded-md text-white bg-teal-600 hover:bg-teal-700 shadow-sm transition">
                    <i class="fas fa-user mr-2"></i> Add New Tenant
                </a>
                <a href="import.php"
                    class="flex items-center justify-center w-full px-4 py-3 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                    <i class="fas fa-file-import mr-2 text-gray-400"></i> Import Data
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>