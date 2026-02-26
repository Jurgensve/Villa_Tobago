<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

require_login();

// Always refresh role + full_name from DB on each request so stale sessions
// (e.g., after code deployments) self-heal without requiring a logout.
try {
    $__stmt = $pdo->prepare("SELECT full_name, role FROM users WHERE id = ?");
    $__stmt->execute([$_SESSION['user_id']]);
    $__row = $__stmt->fetch();
    // If the role column doesn't exist yet (migration not run), default to 'admin'
    $_SESSION['role'] = $__row['role'] ?? 'admin';
    $_SESSION['full_name'] = $__row['full_name'] ?? $_SESSION['username'];

    // SAFETY OVERRIDE: If you are the primary user (ID 1), force admin role
    if ($_SESSION['user_id'] == 1 || $_SESSION['username'] === 'admin') {
        $_SESSION['role'] = 'admin';
    }
}
catch (PDOException $__e) {
    // Column doesn't exist yet — treat as admin so no one gets locked out
    $_SESSION['role'] = $_SESSION['role'] ?? 'admin';
    $_SESSION['full_name'] = $_SESSION['full_name'] ?? $_SESSION['username'];
}

// Role gate — pages set $required_roles before including this file.
// This runs BEFORE any HTML so header() redirects still work.
if (!empty($required_roles)) {
    require_role($required_roles);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Villa Tobago Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" />
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <style>
        /* Custom DataTables Styling to match Tailwind */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_processing,
        .dataTables_wrapper .dataTables_paginate {
            color: #4a5568 !important;
            /* text-gray-700 */
            font-size: 0.875rem !important;
            /* text-sm */
            margin-bottom: 1rem;
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e2e8f0 !important;
            /* border-gray-300 */
            border-radius: 0.375rem !important;
            padding: 0.4rem 0.75rem !important;
            margin-left: 0.5rem !important;
            outline: none !important;
        }

        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #e2e8f0 !important;
            border-radius: 0.375rem !important;
            padding: 0.25rem 1.5rem 0.25rem 0.5rem !important;
        }

        table.dataTable thead th {
            border-bottom: 1px solid #e2e8f0 !important;
            background-color: #f8fafc !important;
            /* bg-gray-50 */
            font-weight: 600 !important;
            color: #64748b !important;
            /* text-gray-500 */
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            font-size: 0.75rem !important;
        }

        table.dataTable td {
            border-bottom: 1px solid #e2e8f0 !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #3b82f6 !important;
            /* bg-blue-500 */
            color: white !important;
            border: 1px solid #3b82f6 !important;
            border-radius: 0.375rem !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #eff6ff !important;
            /* bg-blue-50 */
            color: #1d4ed8 !important;
            /* blue-700 */
            border: 1px solid #dbeafe !important;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen font-sans">
    <?php $__role = $_SESSION['role'] ?? 'managing_agent'; ?>
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <!-- Brand = dashboard link -->
                    <a href="index.php" class="font-bold text-xl text-blue-600 hover:text-blue-800 mr-8 flex-shrink-0">
                        Villa Tobago Admin
                    </a>

                    <div class="hidden sm:flex sm:space-x-2 sm:items-center">

                        <?php if ($__role === 'trustee'): ?>
                        <!-- Trustee: Approvals only -->
                        <a href="pending_approvals.php" class="nav-btn">
                            <i class="fas fa-clipboard-check mr-1"></i> Approvals
                        </a>

                        <?php
else: ?>
                        <!-- Admin + Managing Agent: full nav -->

                        <!-- Units dropdown -->
                        <div class="relative nav-dropdown-wrapper">
                            <button type="button" onclick="toggleDropdown(this)" class="nav-btn">
                                <i class="fas fa-building mr-1"></i> Units
                                <i class="fas fa-chevron-down ml-1 text-xs"></i>
                            </button>
                            <div class="nav-dropdown hidden">
                                <a href="units.php" class="nav-drop-item">
                                    <i class="fas fa-building text-gray-400 w-4"></i> All Units
                                </a>
                                <a href="owners.php" class="nav-drop-item">
                                    <i class="fas fa-key text-gray-400 w-4"></i> Owners
                                </a>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="modifications.php" class="nav-drop-item">
                                    <i class="fas fa-hammer text-gray-400 w-4"></i> Modifications
                                </a>
                            </div>
                        </div>

                        <!-- Residents dropdown -->
                        <div class="relative nav-dropdown-wrapper">
                            <button type="button" onclick="toggleDropdown(this)" class="nav-btn">
                                <i class="fas fa-users mr-1"></i> Residents
                                <i class="fas fa-chevron-down ml-1 text-xs"></i>
                            </button>
                            <div class="nav-dropdown hidden">
                                <a href="tenants.php" class="nav-drop-item">
                                    <i class="fas fa-users text-gray-400 w-4"></i> Tenants
                                </a>
                                <a href="pets.php" class="nav-drop-item">
                                    <i class="fas fa-paw text-gray-400 w-4"></i> Pets
                                </a>
                                <a href="move_management.php" class="nav-drop-item">
                                    <i class="fas fa-truck-moving text-gray-400 w-4"></i> Moves
                                </a>
                            </div>
                        </div>

                        <!-- Settings dropdown -->
                        <div class="relative nav-dropdown-wrapper">
                            <button type="button" onclick="toggleDropdown(this)" class="nav-btn">
                                <i class="fas fa-cog mr-1"></i> Settings
                                <i class="fas fa-chevron-down ml-1 text-xs"></i>
                            </button>
                            <div class="nav-dropdown hidden">
                                <a href="settings.php" class="nav-drop-item">
                                    <i class="fas fa-sliders-h text-gray-400 w-4"></i> Settings
                                </a>
                                <a href="pending_approvals.php" class="nav-drop-item">
                                    <i class="fas fa-clipboard-check text-gray-400 w-4"></i> Pending Approvals
                                </a>
                                <a href="import.php" class="nav-drop-item">
                                    <i class="fas fa-file-import text-gray-400 w-4"></i> Import Data
                                </a>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="run_migrations.php" class="nav-drop-item">
                                    <i class="fas fa-database text-gray-400 w-4"></i> Run Migrations
                                </a>
                                <?php if ($__role === 'admin'): ?>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="users.php" class="nav-drop-item">
                                    <i class="fas fa-users-cog text-gray-400 w-4"></i> User Management
                                </a>
                                <?php
    endif; ?>
                            </div>
                        </div>

                        <?php
endif; // end admin/agent nav ?>

                    </div>
                </div>

                <!-- Right: user info -->
                <div class="flex items-center gap-4">
                    <span class="text-gray-600 text-sm">Hello, <strong>
                            <?= h($_SESSION['full_name'] ?? $_SESSION['username'])?>
                        </strong></span>
                        <a href="profile.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium"><i
                                class="fas fa-user-circle mr-1"></i>Profile</a>
                        <a href="logout.php" class="text-red-600 hover:text-red-800 text-sm font-medium">Logout</a>
                    </div>
                </div>
            </div>
    </nav>

    <style>
        .nav-btn {
            @apply border-transparent text-gray-500 hover:text-gray-800 inline-flex items-center px-3 py-2 rounded text-sm font-medium focus:outline-none hover:bg-gray-50 transition;
        }

        .nav-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            min-width: 185px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.10);
            z-index: 100;
            padding: 4px 0;
        }

        .nav-drop-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            font-size: 0.875rem;
            color: #374151;
            text-decoration: none;
            transition: background 0.15s;
        }

        .nav-drop-item:hover {
            background: #f9fafb;
        }

        .nav-btn {
            border: none;
            background: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #6b7280;
            transition: background 0.15s, color 0.15s;
        }

        .nav-btn:hover {
            background: #f3f4f6;
            color: #111827;
        }
    </style>

    <script>
        function toggleDropdown(btn) {
            const menu = btn.nextElementSibling;
            const isOpen = !menu.classList.contains('hidden');
            // close all
            document.querySelectorAll('.nav-dropdown').forEach(m => m.classList.add('hidden'));
            if (!isOpen) menu.classList.remove('hidden');
        }
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.nav-dropdown-wrapper')) {
                document.querySelectorAll('.nav-dropdown').forEach(m => m.classList.add('hidden'));
            }
        });
    </script>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">