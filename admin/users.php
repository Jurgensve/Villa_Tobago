<?php
require_once 'includes/header.php';
require_super_admin(); // Only super-admins may manage users

$success = '';
$error = '';

// ── Add new user ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['super_admin', 'admin']) ? $_POST['role'] : 'admin';
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Username and password are required.';
    }
    elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    }
    else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (username, full_name, email, phone, role, password_hash) VALUES (?,?,?,?,?,?)"
            );
            $stmt->execute([$username, $full_name ?: null, $email ?: null, $phone ?: null, $role, $hash]);
            $success = "User '{$username}' created successfully.";
        }
        catch (PDOException $e) {
            $error = str_contains($e->getMessage(), 'Duplicate') ? "Username '{$username}' is already taken." : $e->getMessage();
        }
    }
}

// ── Edit existing user ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $uid = (int)$_POST['uid'];
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['super_admin', 'admin']) ? $_POST['role'] : 'admin';

    $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, role=? WHERE id=?")
        ->execute([$full_name ?: null, $email ?: null, $phone ?: null, $role, $uid]);

    // If editing self, refresh session name
    if ($uid === (int)$_SESSION['user_id']) {
        $_SESSION['full_name'] = $full_name ?: $_SESSION['username'];
    }

    $success = 'User updated.';
}

// ── Reset password ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $uid = (int)$_POST['uid'];
    $new_pass = $_POST['new_password'] ?? '';
    if (strlen($new_pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    }
    else {
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
            ->execute([password_hash($new_pass, PASSWORD_DEFAULT), $uid]);
        $success = 'Password reset successfully.';
    }
}

// ── Toggle active status ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    $uid = (int)$_POST['uid'];
    if ($uid === (int)$_SESSION['user_id']) {
        $error = 'You cannot deactivate your own account.';
    }
    else {
        $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=?")->execute([$uid]);
        $success = 'User status updated.';
    }
}

// ── Fetch all users ─────────────────────────────────────────────
$users = $pdo->query("SELECT * FROM users ORDER BY role DESC, username ASC")->fetchAll();
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">User Management</h1>
        <p class="text-gray-500 text-sm mt-1">Manage admin accounts and their access levels.</p>
    </div>
    <button onclick="document.getElementById('addUserModal').classList.remove('hidden')"
        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded shadow flex items-center gap-2">
        <i class="fas fa-user-plus"></i> Add User
    </button>
</div>

<?php if ($success): ?>
<div class="mb-5 bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2">
    <i class="fas fa-check-circle"></i>
    <?= h($success)?>
</div>
<?php
endif; ?>
<?php if ($error): ?>
<div class="mb-5 bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded-lg flex items-center gap-2">
    <i class="fas fa-exclamation-circle"></i>
    <?= h($error)?>
</div>
<?php
endif; ?>

<!-- Users Table -->
<div class="bg-white shadow rounded-lg overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">User</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Contact</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Role</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($users as $u): ?>
            <tr class="<?=!$u['is_active'] ? 'opacity-50 bg-gray-50' : ''?>">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full flex items-center justify-center text-white font-bold text-sm
                            <?= $u['role'] === 'super_admin' ? 'bg-purple-500' : 'bg-blue-500'?>">
                            <?= strtoupper(substr($u['full_name'] ?: $u['username'], 0, 1))?>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">
                                <?= h($u['full_name'] ?: '—')?>
                            </p>
                            <p class="text-xs text-gray-500">@
                                <?= h($u['username'])?>
                            </p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600">
                    <?php if ($u['email']): ?>
                    <div><i class="fas fa-envelope text-gray-400 mr-1"></i>
                        <?= h($u['email'])?>
                    </div>
                    <?php
    endif; ?>
                    <?php if ($u['phone']): ?>
                    <div class="mt-1"><i class="fas fa-phone text-gray-400 mr-1"></i>
                        <?= h($u['phone'])?>
                    </div>
                    <?php
    endif; ?>
                    <?php if (!$u['email'] && !$u['phone']): ?>
                    <span class="text-gray-400 text-xs">No contact info</span>
                    <?php
    endif; ?>
                </td>
                <td class="px-6 py-4">
                    <span
                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold
                        <?= $u['role'] === 'super_admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'?>">
                        <i class="fas fa-shield-alt mr-1"></i>
                        <?= $u['role'] === 'super_admin' ? 'Super Admin' : 'Admin'?>
                    </span>
                </td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold
                        <?= $u['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'?>">
                        <?= $u['is_active'] ? '● Active' : '● Inactive'?>
                    </span>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- Edit -->
                        <button onclick='openEditModal(<?= json_encode($u)?>)'
                            class="text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold px-3 py-1.5 rounded transition">
                            <i class="fas fa-pencil-alt mr-1"></i>Edit
                        </button>

                        <!-- Reset Password -->
                        <button onclick='openResetModal(<?=(int)$u["id"]?>, <?= json_encode($u["username"])?>)'
                            class="text-xs bg-yellow-50 hover:bg-yellow-100 text-yellow-700 font-bold px-3 py-1.5 rounded transition">
                            <i class="fas fa-key mr-1"></i>Reset PW
                        </button>

                        <!-- Toggle Active (not self) -->
                        <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                        <form method="POST"
                            onsubmit="return confirm('Toggle status for <?= h(addslashes($u['username']))?>?')">
                            <input type="hidden" name="uid" value="<?= $u['id']?>">
                            <button type="submit" name="toggle_active" class="text-xs px-3 py-1.5 rounded font-bold transition
                                    <?= $u['is_active']
            ? 'bg-red-50 hover:bg-red-100 text-red-600'
            : 'bg-green-50 hover:bg-green-100 text-green-700'?>">
                                <i class="fas <?= $u['is_active'] ? 'fa-user-slash' : 'fa-user-check'?> mr-1"></i>
                                <?= $u['is_active'] ? 'Deactivate' : 'Reactivate'?>
                            </button>
                        </form>
                        <?php
    endif; ?>
                    </div>
                </td>
            </tr>
            <?php
endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- Add User Modal -->
<div id="addUserModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800"><i class="fas fa-user-plus mr-2 text-blue-500"></i>Add New User
            </h3>
            <button onclick="document.getElementById('addUserModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Username *</label>
                    <input type="text" name="username" required
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none"
                        placeholder="jsmith">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Full Name</label>
                    <input type="text" name="full_name"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none"
                        placeholder="Jane Smith">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Email</label>
                    <input type="email" name="email"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none"
                        placeholder="jane@example.com">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none"
                        placeholder="083 000 0000">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Role</label>
                    <select name="role" class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none">
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Temporary Password *</label>
                    <input type="password" name="password" required minlength="8"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none"
                        placeholder="Min. 8 characters">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('addUserModal').classList.add('hidden')"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-5 rounded">Cancel</button>
                <button type="submit" name="add_user"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded shadow">
                    <i class="fas fa-plus mr-1"></i> Create User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800"><i class="fas fa-pencil-alt mr-2 text-indigo-500"></i>Edit User
            </h3>
            <button onclick="document.getElementById('editUserModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="uid" id="edit_uid">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Username</label>
                    <input type="text" id="edit_username" disabled
                        class="shadow border rounded w-full py-2 px-3 text-gray-400 bg-gray-50 cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Full Name</label>
                    <input type="text" name="full_name" id="edit_full_name"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" id="edit_email"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" id="edit_phone"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none">
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Role</label>
                <select name="role" id="edit_role"
                    class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none">
                    <option value="admin">Admin</option>
                    <option value="super_admin">Super Admin</option>
                </select>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('editUserModal').classList.add('hidden')"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-5 rounded">Cancel</button>
                <button type="submit" name="edit_user"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-5 rounded shadow">
                    <i class="fas fa-save mr-1"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPwModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800"><i class="fas fa-key mr-2 text-yellow-500"></i>Reset Password
            </h3>
            <button onclick="document.getElementById('resetPwModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="uid" id="reset_uid">
            <p class="text-sm text-gray-600">Setting new password for: <strong id="reset_username_label"></strong></p>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">New Password</label>
                <input type="password" name="new_password" required minlength="8"
                    class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none"
                    placeholder="Min. 8 characters">
            </div>
            <div class="flex justify-end gap-3 pt-1">
                <button type="button" onclick="document.getElementById('resetPwModal').classList.add('hidden')"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-5 rounded">Cancel</button>
                <button type="submit" name="reset_password"
                    class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-5 rounded shadow">
                    <i class="fas fa-key mr-1"></i> Reset Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(u) {
        document.getElementById('edit_uid').value = u.id;
        document.getElementById('edit_username').value = u.username;
        document.getElementById('edit_full_name').value = u.full_name || '';
        document.getElementById('edit_email').value = u.email || '';
        document.getElementById('edit_phone').value = u.phone || '';
        document.getElementById('edit_role').value = u.role || 'admin';
        document.getElementById('editUserModal').classList.remove('hidden');
    }
    function openResetModal(uid, username) {
        document.getElementById('reset_uid').value = uid;
        document.getElementById('reset_username_label').textContent = username;
        document.getElementById('resetPwModal').classList.remove('hidden');
    }
</script>

<?php require_once 'includes/footer.php'; ?>