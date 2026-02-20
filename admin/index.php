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
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <!-- Card 1: Units -->
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <dt class="text-sm font-medium text-gray-500 truncate">Total Units</dt>
            <dd class="mt-1 text-3xl font-semibold text-gray-900">
                <?= $unit_count?>
            </dd>
        </div>
        <div class="bg-gray-50 px-4 py-4 sm:px-6">
            <div class="text-sm">
                <a href="units.php" class="font-medium text-blue-600 hover:text-blue-500">View all units <span
                        aria-hidden="true">&rarr;</span></a>
            </div>
        </div>
    </div>

    <!-- Card 2: Active Owners -->
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <dt class="text-sm font-medium text-gray-500 truncate">Active Owners</dt>
            <dd class="mt-1 text-3xl font-semibold text-gray-900">
                <?= $owner_count?>
            </dd>
        </div>
        <div class="bg-gray-50 px-4 py-4 sm:px-6">
            <div class="text-sm">
                <a href="owners.php" class="font-medium text-blue-600 hover:text-blue-500">Manage owners <span
                        aria-hidden="true">&rarr;</span></a>
            </div>
        </div>
    </div>

    <!-- Card 3: Active Tenants -->
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <dt class="text-sm font-medium text-gray-500 truncate">Active Tenants</dt>
            <dd class="mt-1 text-3xl font-semibold text-gray-900">
                <?= $tenant_count?>
            </dd>
        </div>
        <div class="bg-gray-50 px-4 py-4 sm:px-6">
            <div class="text-sm">
                <a href="tenants.php" class="font-medium text-blue-600 hover:text-blue-500">Manage tenants <span
                        aria-hidden="true">&rarr;</span></a>
            </div>
        </div>
    </div>

    <!-- Card 4: Pending Modifications -->
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <dt class="text-sm font-medium text-gray-500 truncate">Pending Modifications</dt>
            <dd class="mt-1 text-3xl font-semibold text-gray-900">
                <?= $pending_modifications?>
            </dd>
        </div>
        <div class="bg-gray-50 px-4 py-4 sm:px-6">
            <div class="text-sm">
                <a href="modifications.php" class="font-medium text-blue-600 hover:text-blue-500">Review requests <span
                        aria-hidden="true">&rarr;</span></a>
            </div>
        </div>
    </div>
</div>

<div class="bg-white shadow sm:rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">Quick Actions</h3>
        <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <a href="owners.php?action=add"
                class="inline-flex items-center justify-center px-4 py-2 border border-transparent font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 shadow-sm">
                <i class="fas fa-plus mr-2"></i> Add New Owner
            </a>
            <a href="tenants.php?action=add"
                class="inline-flex items-center justify-center px-4 py-2 border border-transparent font-medium rounded-md text-white bg-green-600 hover:bg-green-700 shadow-sm">
                <i class="fas fa-plus mr-2"></i> Add New Tenant
            </a>
            <a href="modifications.php?action=add"
                class="inline-flex items-center justify-center px-4 py-2 border border-transparent font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700 shadow-sm">
                <i class="fas fa-hammer mr-2"></i> Log Modification
            </a>
            <a href="import.php"
                class="inline-flex items-center justify-center px-4 py-2 border border-transparent font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 shadow-sm">
                <i class="fas fa-file-import mr-2"></i> Import Data
            </a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>