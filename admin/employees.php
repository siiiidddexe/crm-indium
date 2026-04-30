<?php
$pageTitle = 'Employee Management - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;

    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'employee';
        $teamleadId = $_POST['teamlead_id'] ?? null;

        if ($name && $email && $password) {
            $existing = db()->fetch("SELECT id FROM users WHERE email = ?", [$email]);
            if (!$existing) {
                db()->insert(
                    "INSERT INTO users (name, email, phone, password, role, teamlead_id, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)",
                    [$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $role, $teamleadId ?: null]
                );
                setFlash('success', ucfirst($role) . ' added successfully!');
            } else {
                setFlash('error', 'Email already exists.');
            }
        }
    } elseif ($action === 'edit') {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'employee';
        $teamleadId = $_POST['teamlead_id'] ?? null;
        $password = $_POST['password'] ?? '';

        if ($name && $email && $id) {
            if ($password) {
                db()->update(
                    "UPDATE users SET name = ?, email = ?, phone = ?, role = ?, teamlead_id = ?, password = ? WHERE id = ?",
                    [$name, $email, $phone, $role, $teamleadId ?: null, password_hash($password, PASSWORD_DEFAULT), $id]
                );
            } else {
                db()->update(
                    "UPDATE users SET name = ?, email = ?, phone = ?, role = ?, teamlead_id = ? WHERE id = ?",
                    [$name, $email, $phone, $role, $teamleadId ?: null, $id]
                );
            }
            setFlash('success', 'Employee updated successfully!');
        }
    } elseif ($action === 'toggle_status') {
        $user = db()->fetch("SELECT is_active FROM users WHERE id = ?", [$id]);
        $newStatus = $user['is_active'] ? 0 : 1;
        db()->update("UPDATE users SET is_active = ? WHERE id = ?", [$newStatus, $id]);
        setFlash('success', $newStatus ? 'Employee activated!' : 'Employee deactivated!');
    } elseif ($action === 'delete') {
        db()->delete("DELETE FROM users WHERE id = ? AND role != 'admin'", [$id]);
        setFlash('success', 'Employee deleted permanently.');
    }

    header('Location: employees.php');
    exit;
}

// Get employees
$employees = db()->fetchAll("
    SELECT u.*, 
        tl.name as teamlead_name,
        (SELECT COUNT(*) FROM contacts WHERE assigned_to = u.id) as contact_count,
        (SELECT COUNT(*) FROM contacts WHERE assigned_to = u.id AND is_called = 1) as called_count
    FROM users u
    LEFT JOIN users tl ON u.teamlead_id = tl.id
    WHERE u.role != 'admin'
    ORDER BY u.role DESC, u.is_active DESC, u.name
");

$teamleads = getTeamLeads();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-black">Employees</h1>
            <p class="text-gray-500 mt-1"><?= count($employees) ?> team members</p>
        </div>
        <button onclick="openAddModal()"
            class="inline-flex items-center gap-2 px-5 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Employee
        </button>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-black">
                <?= count(array_filter($employees, fn($e) => $e['role'] === 'teamlead')) ?></p>
            <p class="text-xs text-gray-500">Team Leads</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-black">
                <?= count(array_filter($employees, fn($e) => $e['role'] === 'employee')) ?></p>
            <p class="text-xs text-gray-500">Employees</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-green-600">
                <?= count(array_filter($employees, fn($e) => $e['is_active'])) ?></p>
            <p class="text-xs text-gray-500">Active</p>
        </div>
    </div>

    <!-- Employees Grid -->
    <?php if (empty($employees)): ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <p class="text-lg font-medium text-black mb-1">No employees yet</p>
            <p class="text-gray-500">Add your first team member to get started</p>
        </div>
    <?php else: ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($employees as $emp):
                $progress = $emp['contact_count'] > 0 ? round(($emp['called_count'] / $emp['contact_count']) * 100) : 0;
                ?>
                <div
                    class="bg-white rounded-2xl border border-gray-200 overflow-hidden <?= !$emp['is_active'] ? 'opacity-60' : '' ?>">
                    <div class="p-5">
                        <div class="flex items-start gap-4">
                            <div
                                class="w-14 h-14 rounded-full flex items-center justify-center font-bold text-lg flex-shrink-0
                        <?= $emp['role'] === 'teamlead' ? 'bg-gradient-to-br from-purple-500 to-indigo-600 text-white' : 'bg-gray-100 text-gray-700' ?>">
                                <?= strtoupper(substr($emp['name'], 0, 1)) ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-bold text-lg truncate"><?= sanitize($emp['name']) ?></h3>
                                    <?php if (!$emp['is_active']): ?>
                                        <span class="px-2 py-0.5 bg-red-100 text-red-600 rounded text-xs">Inactive</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm text-gray-500 truncate"><?= sanitize($emp['email']) ?></p>
                                <div class="flex items-center gap-2 mt-2">
                                    <span
                                        class="px-2 py-1 rounded-lg text-xs font-medium
                                <?= $emp['role'] === 'teamlead' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700' ?>">
                                        <?= $emp['role'] === 'teamlead' ? 'Team Lead' : 'Employee' ?>
                                    </span>
                                    <?php if ($emp['teamlead_name']): ?>
                                        <span class="text-xs text-gray-400">→ <?= sanitize($emp['teamlead_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Progress -->
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="text-gray-500">Progress</span>
                                <span class="font-medium"><?= $emp['called_count'] ?>/<?= $emp['contact_count'] ?></span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-black rounded-full transition-all" style="width: <?= $progress ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-2">
                        <button onclick='openEditModal(<?= json_encode($emp) ?>)'
                            class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Edit">
                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                            <button type="submit" class="p-2 hover:bg-gray-200 rounded-lg transition-colors"
                                title="<?= $emp['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                <?php if ($emp['is_active']): ?>
                                    <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                <?php else: ?>
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                <?php endif; ?>
                            </button>
                        </form>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this employee permanently?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                            <button type="submit" class="p-2 hover:bg-red-100 rounded-lg transition-colors" title="Delete">
                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div id="employeeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4"
    data-modal-backdrop>
    <div class="bg-white rounded-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <h2 id="modalTitle" class="text-xl font-bold text-black">Add Employee</h2>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                <input type="text" name="name" id="formName" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                <input type="email" name="email" id="formEmail" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                <input type="tel" name="phone" id="formPhone"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Password <span id="pwdHint"
                        class="text-gray-400 font-normal">(leave blank to keep)</span></label>
                <input type="password" name="password" id="formPassword"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                <select name="role" id="formRole" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black"
                    onchange="toggleTeamleadSelect()">
                    <option value="employee">Employee</option>
                    <option value="teamlead">Team Lead</option>
                </select>
            </div>

            <div id="teamleadSelectDiv">
                <label class="block text-sm font-medium text-gray-700 mb-2">Assign to Team Lead</label>
                <select name="teamlead_id" id="formTeamlead"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                    <option value="">-- None --</option>
                    <?php foreach ($teamleads as $tl): ?>
                        <option value="<?= $tl['id'] ?>"><?= sanitize($tl['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="hideModal('employeeModal')"
                    class="flex-1 px-4 py-3 border border-gray-300 rounded-xl font-medium hover:bg-gray-50 transition-colors">Cancel</button>
                <button type="submit"
                    class="flex-1 px-4 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Add Employee';
        document.getElementById('formAction').value = 'add';
        document.getElementById('formId').value = '';
        document.getElementById('formName').value = '';
        document.getElementById('formEmail').value = '';
        document.getElementById('formPhone').value = '';
        document.getElementById('formPassword').value = '';
        document.getElementById('formPassword').required = true;
        document.getElementById('pwdHint').classList.add('hidden');
        document.getElementById('formRole').value = 'employee';
        document.getElementById('formTeamlead').value = '';
        toggleTeamleadSelect();
        showModal('employeeModal');
    }

    function openEditModal(emp) {
        document.getElementById('modalTitle').textContent = 'Edit Employee';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formId').value = emp.id;
        document.getElementById('formName').value = emp.name;
        document.getElementById('formEmail').value = emp.email;
        document.getElementById('formPhone').value = emp.phone || '';
        document.getElementById('formPassword').value = '';
        document.getElementById('formPassword').required = false;
        document.getElementById('pwdHint').classList.remove('hidden');
        document.getElementById('formRole').value = emp.role;
        document.getElementById('formTeamlead').value = emp.teamlead_id || '';
        toggleTeamleadSelect();
        showModal('employeeModal');
    }

    function toggleTeamleadSelect() {
        const role = document.getElementById('formRole').value;
        const teamleadDiv = document.getElementById('teamleadSelectDiv');
        if (role === 'teamlead') {
            teamleadDiv.classList.add('hidden');
        } else {
            teamleadDiv.classList.remove('hidden');
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>