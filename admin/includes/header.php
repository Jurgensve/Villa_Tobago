<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

require_login();
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
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <span class="font-bold text-xl text-blue-600">Villa Tobago Admin</span>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="index.php"
                            class="border-transparent text-gray-500 hover:border-blue-500 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-home mr-2"></i> Dashboard
                        </a>
                        <a href="units.php"
                            class="border-transparent text-gray-500 hover:border-blue-500 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-building mr-2"></i> Units
                        </a>
                        <a href="pets.php"
                            class="border-transparent text-gray-500 hover:border-blue-500 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-paw mr-2"></i> Pets
                        </a>
                        <a href="modifications.php"
                            class="border-transparent text-gray-500 hover:border-blue-500 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-hammer mr-2"></i> Modifications
                        </a>
                        <a href="import.php"
                            class="border-transparent text-gray-500 hover:border-blue-500 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-file-import mr-2"></i> Import
                        </a>
                        <a href="settings.php"
                            class="border-transparent text-gray-500 hover:border-blue-500 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-cog mr-2"></i> Settings
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-700 mr-4">Hello,
                        <?= h($_SESSION['username'])?>
                    </span>
                    <div class="flex space-x-4">
                        <a href="change_password.php"
                            class="text-blue-600 hover:text-blue-800 text-sm font-medium">Change Password</a>
                        <a href="logout.php" class="text-red-600 hover:text-red-800 text-sm font-medium">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">