<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_logout();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (verify_login($username, $password, $pdo)) {
        // Run pending migrations before redirecting
        require_once 'includes/migrations.php';
        $migration_results = run_pending_migrations($pdo);

        if ($migration_results && $migration_results['ran_migrations']) {
            $show_migration_results = true;
        }
        else {
            header("Location: index.php");
            exit;
        }
    }
    else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Villa Tobago Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded shadow-md w-full max-w-md">
        <?php if (!empty($show_migration_results)): ?>
        <div class="text-center mb-6">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-4">
                <i class="fas fa-database text-3xl text-blue-600"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Database Updated</h2>
            <p class="text-gray-600">The system automatically applied new database migrations during your login.</p>
        </div>

        <div class="bg-gray-50 border rounded-lg p-4 mb-6 max-h-48 overflow-y-auto">
            <ul class="space-y-2 text-sm">
                <?php if (empty($migration_results['messages'])): ?>
                <li class="text-gray-500 italic">No detailed messages.</li>
                <?php
    else: ?>
                <?php foreach ($migration_results['messages'] as $msg): ?>
                <li class="flex items-start">
                    <?php if (str_contains($msg, 'Failed') || str_contains($msg, 'Error')): ?>
                    <i class="fas fa-times-circle text-red-500 mt-0.5 mr-2"></i>
                    <span class="text-red-700 font-medium">
                        <?= h($msg)?>
                    </span>
                    <?php
            else: ?>
                    <i class="fas fa-check-circle text-green-500 mt-0.5 mr-2"></i>
                    <span class="text-green-700 font-medium">
                        <?= h($msg)?>
                    </span>
                    <?php
            endif; ?>
                </li>
                <?php
        endforeach; ?>
                <?php
    endif; ?>
            </ul>
        </div>

        <a href="index.php"
            class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg shadow transition">
            Continue to Dashboard <i class="fas fa-arrow-right ml-2"></i>
        </a>

        <?php
else: ?>
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Admin Login</h2>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= h($error)?>
        </div>
        <?php
    endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="username">Username</label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="username" name="username" type="text" required>
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Password</label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline"
                    id="password" name="password" type="password" required>
            </div>
            <div class="flex items-center justify-between">
                <button
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full"
                    type="submit">
                    Sign In
                </button>
            </div>
        </form>
        <?php
endif; ?>
    </div>
</body>

</html>