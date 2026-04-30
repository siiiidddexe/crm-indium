<?php
$pageTitle = 'Manage Admins - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireSuperAdmin();

$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_admin') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone    = trim($_POST['phone'] ?? '');

        if (!$name || !$email || !$password) {
            $error = 'Name, email, and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $existing = db()->fetch("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existing) {
                $error = 'Email already registered.';
            } else {
                db()->insert(
                    "INSERT INTO users (name, email, password, phone, role, is_active) VALUES (?, ?, ?, ?, 'admin', 1)",
                    [$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone]
                );
                setFlash('success', 'Admin account created.');
                header('Location: admins.php'); exit;
            }
        }
    }

    if ($action === 'toggle_admin') {
        $id = intval($_POST['id'] ?? 0);
        $row = db()->fetch("SELECT is_active, role FROM users WHERE id = ?", [$id]);
        if ($row && $row['role'] === 'admin') {
            db()->update("UPDATE users SET is_active = ? WHERE id = ?", [$row['is_active'] ? 0 : 1, $id]);
        }
        header('Location: admins.php'); exit;
    }

    if ($action === 'delete_admin') {
        $id = intval($_POST['id'] ?? 0);
        $row = db()->fetch("SELECT role FROM users WHERE id = ?", [$id]);
        if ($row && $row['role'] === 'admin') {
            db()->delete("DELETE FROM users WHERE id = ?", [$id]);
            setFlash('success', 'Admin deleted.');
        }
        header('Location: admins.php'); exit;
    }

    if ($action === 'reset_password') {
        $id          = intval($_POST['id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';
        if (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $row = db()->fetch("SELECT role FROM users WHERE id = ?", [$id]);
            if ($row && $row['role'] === 'admin') {
                db()->update("UPDATE users SET password = ? WHERE id = ?", [password_hash($newPassword, PASSWORD_DEFAULT), $id]);
                setFlash('success', 'Password updated.');
            }
            header('Location: admins.php'); exit;
        }
    }
}

$admins = db()->fetchAll("SELECT id, name, email, phone, is_active, created_at FROM users WHERE role = 'admin' ORDER BY created_at DESC");
$flashMsg = getFlash();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="<?= APP_URL ?>/superadmin/index.php" class="text-gray-400 hover:text-black text-sm">Super Admin</a>
                <span class="text-gray-300">/</span>
                <span class="text-sm font-medium">Manage Admins</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-black">Manage Admins</h1>
        </div>
        <button onclick="showModal('addAdminModal')"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-black text-white rounded-xl font-medium text-sm hover:bg-gray-800 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Admin
        </button>
    </div>

    <?php if ($flashMsg): ?>
        <div class="mb-4 p-4 rounded-xl bg-green-50 text-green-800 border border-green-200"><?= sanitize($flashMsg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="mb-4 p-4 rounded-xl bg-red-50 text-red-800 border border-red-200"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <!-- Admin Table -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <?php if (empty($admins)): ?>
            <div class="p-12 text-center text-gray-400">No admin accounts yet.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left px-5 py-3 font-semibold text-gray-600">Name</th>
                            <th class="text-left px-5 py-3 font-semibold text-gray-600">Email</th>
                            <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden sm:table-cell">Phone</th>
                            <th class="text-left px-5 py-3 font-semibold text-gray-600">Status</th>
                            <th class="text-right px-5 py-3 font-semibold text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($admins as $admin): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-black text-white flex items-center justify-center text-sm font-bold flex-shrink-0">
                                            <?= strtoupper(substr($admin['name'], 0, 1)) ?>
                                        </div>
                                        <span class="font-medium"><?= sanitize($admin['name']) ?></span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-gray-600"><?= sanitize($admin['email']) ?></td>
                                <td class="px-5 py-4 text-gray-600 hidden sm:table-cell"><?= sanitize($admin['phone'] ?? '—') ?></td>
                                <td class="px-5 py-4">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $admin['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                                        <?= $admin['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick="openResetModal(<?= $admin['id'] ?>, '<?= sanitize($admin['name']) ?>')"
                                            class="p-2 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="Reset password">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                            </svg>
                                        </button>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="toggle_admin">
                                            <input type="hidden" name="id" value="<?= $admin['id'] ?>">
                                            <button type="submit"
                                                class="p-2 rounded-lg text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 transition-colors"
                                                title="<?= $admin['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                            </button>
                                        </form>
                                        <form method="POST" class="inline" onsubmit="return confirm('Delete <?= sanitize($admin['name']) ?>? This cannot be undone.')">
                                            <input type="hidden" name="action" value="delete_admin">
                                            <input type="hidden" name="id" value="<?= $admin['id'] ?>">
                                            <button type="submit"
                                                class="p-2 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Delete">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Admin Modal -->
<div id="addAdminModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden">
        <div class="p-5 border-b border-gray-200">
            <h2 class="text-xl font-bold text-black">Add Admin</h2>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="add_admin">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                <input type="text" name="name" required placeholder="Admin name"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                <input type="email" name="email" required placeholder="admin@example.com"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                <input type="tel" name="phone" placeholder="+91 9876543210"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password * (min 8 chars)</label>
                <input type="password" name="password" required minlength="8" placeholder="••••••••"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="hideModal('addAdminModal')"
                    class="flex-1 py-3 bg-gray-200 rounded-xl font-medium text-sm hover:bg-gray-300 transition-colors">Cancel</button>
                <button type="submit"
                    class="flex-1 py-3 bg-black text-white rounded-xl font-medium text-sm hover:bg-gray-800 transition-colors">Create Admin</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-sm overflow-hidden">
        <div class="p-5 border-b border-gray-200">
            <h2 class="text-xl font-bold text-black">Reset Password</h2>
            <p class="text-sm text-gray-500 mt-1" id="resetModalName"></p>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" id="resetAdminId">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password (min 8 chars)</label>
                <input type="password" name="new_password" required minlength="8" placeholder="••••••••"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm">
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="hideModal('resetPasswordModal')"
                    class="flex-1 py-3 bg-gray-200 rounded-xl font-medium text-sm hover:bg-gray-300 transition-colors">Cancel</button>
                <button type="submit"
                    class="flex-1 py-3 bg-blue-600 text-white rounded-xl font-medium text-sm hover:bg-blue-700 transition-colors">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResetModal(id, name) {
    document.getElementById('resetAdminId').value = id;
    document.getElementById('resetModalName').textContent = 'Admin: ' + name;
    showModal('resetPasswordModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
