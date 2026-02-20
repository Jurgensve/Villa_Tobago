<?php
require_once 'includes/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    }
    elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    }
    elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    }
    else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user && password_verify($current_password, $user['password_hash'])) {
            // Update password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($stmt->execute([$new_hash, $_SESSION['user_id']])) {
                $success = "Password changed successfully!";
            }
            else {
                $error = "Failed to update password. Please try again.";
            }
        }
        else {
            $error = "Incorrect current password.";
        }
    }
}
?>

<div class="max-w-md mx-auto bg-white shadow-md rounded-lg overflow-hidden mt-10">
    <div class="px-6 py-4">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Change Password</h2>

        <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>
                <?= h($error)?>
            </p>
        </div>
        <?php
endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
            <p>
                <?= h($success)?>
            </p>
        </div>
        <?php
endif; ?>

        <form action="change_password.php" method="POST" class="space-y-4">
            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                <input type="password" name="current_password" id="current_password" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>

            <div>
                <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                <input type="password" name="new_password" id="new_password" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>

            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New
                    Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>

            <div class="flex items-center justify-between">
                <a href="index.php" class="text-sm text-blue-600 hover:text-blue-500 font-medium">Back to Dashboard</a>
                <button type="submit"
                    class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Change Password
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>