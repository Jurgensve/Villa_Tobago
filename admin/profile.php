<?php
require_once 'includes/header.php';

$user = current_user($pdo);
if (!$user) {
    die('User not found.');
}

$success = '';
$error = '';

// ── Save profile ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE id=?");
    $stmt->execute([$full_name ?: null, $email ?: null, $phone ?: null, $_SESSION['user_id']]);

    // Refresh session display name
    $_SESSION['full_name'] = $full_name ?: $_SESSION['username'];

    // Reload user row
    $user = current_user($pdo);
    $success = 'Profile updated successfully.';
}

// ── Change password ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password_hash'])) {
        $error = 'Current password is incorrect.';
    }
    elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    }
    elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    }
    else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $_SESSION['user_id']]);
        $success = 'Password changed successfully.';
        $user = current_user($pdo);
    }
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">My Profile</h1>
    <p class="text-gray-500 text-sm mt-1">Update your personal details and change your password.</p>
</div>

<?php if ($success): ?>
<div class="mb-6 bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2">
    <i class="fas fa-check-circle"></i>
    <?= h($success)?>
</div>
<?php
endif; ?>
<?php if ($error): ?>
<div class="mb-6 bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded-lg flex items-center gap-2">
    <i class="fas fa-exclamation-circle"></i>
    <?= h($error)?>
</div>
<?php
endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

    <!-- Profile Details -->
    <form method="POST">
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b flex items-center gap-3">
                <i class="fas fa-user-circle text-blue-500 text-xl"></i>
                <h2 class="text-lg font-bold text-gray-800">Profile Details</h2>
            </div>
            <div class="p-6 space-y-5">

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Username</label>
                    <input type="text" value="<?= h($user['username'])?>" disabled
                        class="shadow border rounded w-full py-2 px-3 text-gray-400 bg-gray-50 cursor-not-allowed">
                    <p class="text-xs text-gray-400 mt-1">Username cannot be changed.</p>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1" for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?= h($user['full_name'] ?? '')?>"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                        placeholder="e.g. Jane Smith">
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1" for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= h($user['email'] ?? '')?>"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                        placeholder="you@example.com">
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1" for="phone">Contact Number</label>
                    <input type="text" id="phone" name="phone" value="<?= h($user['phone'] ?? '')?>"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                        placeholder="e.g. 083 000 0000">
                </div>

                <div class="pt-1">
                    <span
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold
                        <?= $user['role'] === 'super_admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'?>">
                        <i class="fas fa-shield-alt mr-1"></i>
                        <?= $user['role'] === 'super_admin' ? 'Super Admin' : 'Admin'?>
                    </span>
                </div>

            </div>
        </div>
        <div class="mt-4">
            <button type="submit" name="save_profile"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow transition">
                <i class="fas fa-save mr-2"></i> Save Profile
            </button>
        </div>
    </form>

    <!-- Change Password -->
    <form method="POST">
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b flex items-center gap-3">
                <i class="fas fa-lock text-orange-500 text-xl"></i>
                <h2 class="text-lg font-bold text-gray-800">Change Password</h2>
            </div>
            <div class="p-6 space-y-5">

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1" for="current_password">Current
                        Password</label>
                    <input type="password" id="current_password" name="current_password" required
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline">
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1" for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline">
                    <p class="text-xs text-gray-400 mt-1">Minimum 8 characters.</p>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1" for="confirm_password">Confirm New
                        Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline">
                </div>

            </div>
        </div>
        <div class="mt-4">
            <button type="submit" name="change_password"
                class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-6 rounded shadow transition">
                <i class="fas fa-key mr-2"></i> Change Password
            </button>
        </div>
    </form>

</div>

<?php require_once 'includes/footer.php'; ?>