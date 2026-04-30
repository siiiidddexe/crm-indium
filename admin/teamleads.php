<?php
$pageTitle = 'Team Leads - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign_employee') {
        $employeeId = $_POST['employee_id'] ?? 0;
        $teamleadId = $_POST['teamlead_id'] ?? null;

        if ($employeeId) {
            db()->update(
                "UPDATE users SET teamlead_id = ? WHERE id = ? AND role = 'employee'",
                [$teamleadId ?: null, $employeeId]
            );
            setFlash('success', 'Employee assignment updated!');
        }
        header('Location: teamleads.php' . (isset($_GET['tl']) ? '?tl=' . $_GET['tl'] : ''));
        exit;
    }
}

// Get all team leads
$teamleads = db()->fetchAll("SELECT * FROM users WHERE role = 'teamlead' AND is_active = 1 ORDER BY name");

// Get selected team lead
$selectedTL = $_GET['tl'] ?? ($teamleads[0]['id'] ?? null);

// Get team lead details
$teamlead = null;
$teamMembers = [];
$teamStats = null;

if ($selectedTL) {
    $teamlead = db()->fetch("SELECT * FROM users WHERE id = ? AND role = 'teamlead'", [$selectedTL]);

    if ($teamlead) {
        // Get team members assigned to this TL
        $teamMembers = db()->fetchAll("SELECT * FROM users WHERE teamlead_id = ? AND role = 'employee' ORDER BY name", [$selectedTL]);

        // Get TL stats
        $teamStats = db()->fetch("
            SELECT 
                (SELECT COUNT(*) FROM contacts WHERE assigned_to = ?) as tl_contacts,
                (SELECT COUNT(*) FROM contacts WHERE assigned_to = ? AND is_called = 1) as tl_called,
                (SELECT COUNT(*) FROM contacts WHERE assigned_to IN (SELECT id FROM users WHERE teamlead_id = ?)) as team_contacts,
                (SELECT COUNT(*) FROM contacts WHERE assigned_to IN (SELECT id FROM users WHERE teamlead_id = ?) AND is_called = 1) as team_called
        ", [$selectedTL, $selectedTL, $selectedTL, $selectedTL]);

        // Get TL's attendance
        $tlAttendance = db()->fetchAll("
            SELECT * FROM attendance WHERE user_id = ? 
            ORDER BY date DESC LIMIT 10
        ", [$selectedTL]);

        // Get call logs for TL
        $tlCallLogs = db()->fetchAll("
            SELECT cl.*, c.name as contact_name, cs.name as status_name, cs.color
            FROM call_logs cl
            LEFT JOIN contacts c ON cl.contact_id = c.id
            LEFT JOIN call_statuses cs ON cl.status_id = cs.id
            WHERE cl.user_id = ?
            ORDER BY cl.call_time DESC
            LIMIT 20
        ", [$selectedTL]);
    }
}

// Get unassigned employees
$unassignedEmployees = db()->fetchAll("SELECT * FROM users WHERE role = 'employee' AND is_active = 1 AND teamlead_id IS NULL ORDER BY name");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-black">Team Leads</h1>
            <p class="text-gray-500 mt-1">Manage team leads, assignments, and view reports</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-4 gap-6">
        <!-- Team Lead Selector -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl border border-gray-200 p-4 sticky top-20">
                <h3 class="font-bold text-sm text-gray-600 uppercase tracking-wider mb-3">Team Leads</h3>

                <?php if (empty($teamleads)): ?>
                    <p class="text-sm text-gray-400 py-4 text-center">No team leads found</p>
                <?php else: ?>
                    <div class="space-y-1">
                        <?php foreach ($teamleads as $tl): ?>
                            <?php $memberCount = count(array_filter($teamMembers, fn($m) => $m['teamlead_id'] == $tl['id'])); ?>
                            <a href="?tl=<?= $tl['id'] ?>"
                                class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm <?= $selectedTL == $tl['id'] ? 'bg-black text-white' : 'hover:bg-gray-100' ?>">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="w-8 h-8 rounded-full <?= $selectedTL == $tl['id'] ? 'bg-white text-black' : 'bg-gray-100' ?> flex items-center justify-center font-bold text-xs">
                                        <?= strtoupper(substr($tl['name'], 0, 1)) ?>
                                    </div>
                                    <span class="truncate">
                                        <?= sanitize($tl['name']) ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-3">
            <?php if ($teamlead): ?>

                <!-- TL Info Card -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-16 h-16 rounded-xl bg-black text-white flex items-center justify-center text-2xl font-bold">
                            <?= strtoupper(substr($teamlead['name'], 0, 1)) ?>
                        </div>
                        <div class="flex-1">
                            <h2 class="text-xl font-bold">
                                <?= sanitize($teamlead['name']) ?>
                            </h2>
                            <p class="text-gray-500">
                                <?= sanitize($teamlead['email']) ?>
                            </p>
                            <p class="text-sm text-gray-400 mt-1">Team Lead since
                                <?= formatDate($teamlead['created_at'], 'M d, Y') ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                        <p class="text-2xl font-bold">
                            <?= count($teamMembers) ?>
                        </p>
                        <p class="text-xs text-gray-500">Team Members</p>
                    </div>
                    <div class="bg-blue-50 rounded-xl border border-blue-200 p-4 text-center">
                        <p class="text-2xl font-bold text-blue-600">
                            <?= $teamStats['tl_contacts'] ?? 0 ?>
                        </p>
                        <p class="text-xs text-blue-600">TL's Contacts</p>
                    </div>
                    <div class="bg-green-50 rounded-xl border border-green-200 p-4 text-center">
                        <p class="text-2xl font-bold text-green-600">
                            <?= $teamStats['team_contacts'] ?? 0 ?>
                        </p>
                        <p class="text-xs text-green-600">Team Contacts</p>
                    </div>
                    <div class="bg-purple-50 rounded-xl border border-purple-200 p-4 text-center">
                        <p class="text-2xl font-bold text-purple-600">
                            <?= $teamStats['team_called'] ?? 0 ?>
                        </p>
                        <p class="text-xs text-purple-600">Team Calls Done</p>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="flex border-b border-gray-200">
                        <button onclick="switchTab('team')" id="tab-team"
                            class="flex-1 px-4 py-3 text-sm font-medium border-b-2 border-black">Team Members</button>
                        <button onclick="switchTab('attendance')" id="tab-attendance"
                            class="flex-1 px-4 py-3 text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-black">Attendance</button>
                        <button onclick="switchTab('calls')" id="tab-calls"
                            class="flex-1 px-4 py-3 text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-black">Call
                            Logs</button>
                    </div>

                    <!-- Team Members Tab -->
                    <div id="content-team" class="p-4">
                        <?php if (empty($teamMembers)): ?>
                            <p class="text-center text-gray-500 py-8">No team members assigned</p>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($teamMembers as $member): ?>
                                    <?php
                                    $memberStats = db()->fetch("SELECT COUNT(*) as total, SUM(is_called) as called FROM contacts WHERE assigned_to = ?", [$member['id']]);
                                    ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center font-bold text-gray-600">
                                                <?= strtoupper(substr($member['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <p class="font-medium">
                                                    <?= sanitize($member['name']) ?>
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    <?= $memberStats['called'] ?? 0 ?>/
                                                    <?= $memberStats['total'] ?? 0 ?> contacts called
                                                </p>
                                            </div>
                                        </div>
                                        <form method="POST" class="flex items-center gap-2">
                                            <input type="hidden" name="action" value="assign_employee">
                                            <input type="hidden" name="employee_id" value="<?= $member['id'] ?>">
                                            <select name="teamlead_id" onchange="this.form.submit()"
                                                class="text-sm px-2 py-1 border border-gray-300 rounded-lg">
                                                <option value="">Unassign</option>
                                                <?php foreach ($teamleads as $tl): ?>
                                                    <option value="<?= $tl['id'] ?>" <?= $member['teamlead_id'] == $tl['id'] ? 'selected' : '' ?>>
                                                        <?= sanitize($tl['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Unassigned Employees -->
                        <?php if (!empty($unassignedEmployees)): ?>
                            <div class="mt-6">
                                <h4 class="font-semibold text-sm text-gray-600 mb-3">Unassigned Employees</h4>
                                <div class="space-y-2">
                                    <?php foreach ($unassignedEmployees as $emp): ?>
                                        <div
                                            class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-xl">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="w-10 h-10 rounded-full bg-yellow-200 flex items-center justify-center font-bold text-yellow-700">
                                                    <?= strtoupper(substr($emp['name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <p class="font-medium">
                                                        <?= sanitize($emp['name']) ?>
                                                    </p>
                                                    <p class="text-xs text-yellow-600">Not assigned to any TL</p>
                                                </div>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="assign_employee">
                                                <input type="hidden" name="employee_id" value="<?= $emp['id'] ?>">
                                                <input type="hidden" name="teamlead_id" value="<?= $selectedTL ?>">
                                                <button type="submit"
                                                    class="px-3 py-1.5 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800">
                                                    Assign to
                                                    <?= sanitize(explode(' ', $teamlead['name'])[0]) ?>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Attendance Tab -->
                    <div id="content-attendance" class="p-4 hidden">
                        <?php if (empty($tlAttendance)): ?>
                            <p class="text-center text-gray-500 py-8">No attendance records</p>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Date</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Punch In</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Break</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Punch Out</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach ($tlAttendance as $att): ?>
                                            <tr>
                                                <td class="px-4 py-3 font-medium">
                                                    <?= formatDate($att['date'], 'M d, Y') ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <?= $att['punch_in'] ? formatTime($att['punch_in']) : '-' ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <?php if ($att['break_start']): ?>
                                                        <?= formatTime($att['break_start']) ?> -
                                                        <?= $att['break_end'] ? formatTime($att['break_end']) : 'ongoing' ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <?= $att['punch_out'] ? formatTime($att['punch_out']) : '-' ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <?php if ($att['punch_out']): ?>
                                                        <span
                                                            class="px-2 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-medium">Complete</span>
                                                    <?php elseif ($att['punch_in']): ?>
                                                        <span
                                                            class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-lg text-xs font-medium">Active</span>
                                                    <?php else: ?>
                                                        <span
                                                            class="px-2 py-1 bg-gray-100 text-gray-600 rounded-lg text-xs font-medium">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Call Logs Tab -->
                    <div id="content-calls" class="p-4 hidden">
                        <?php if (empty($tlCallLogs)): ?>
                            <p class="text-center text-gray-500 py-8">No call logs</p>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($tlCallLogs as $log): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="font-medium">
                                                    <?= sanitize($log['contact_name'] ?? 'Unknown') ?>
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    <?= formatDate($log['call_time'], 'M d, h:i A') ?>
                                                </p>
                                            </div>
                                        </div>
                                        <?php if ($log['status_name']): ?>
                                            <span class="px-2 py-1 rounded-lg text-xs font-medium"
                                                style="background-color: <?= $log['color'] ?>20; color: <?= $log['color'] ?>">
                                                <?= sanitize($log['status_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="text-lg font-medium text-gray-500">No team leads found</p>
                    <p class="text-gray-400 mt-1">Add team leads from the Employees page</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function switchTab(tab) {
        // Hide all content
        document.querySelectorAll('[id^="content-"]').forEach(el => el.classList.add('hidden'));
        // Reset all tabs
        document.querySelectorAll('[id^="tab-"]').forEach(el => {
            el.classList.remove('border-black', 'text-black');
            el.classList.add('border-transparent', 'text-gray-500');
        });
        // Show selected content
        document.getElementById('content-' + tab).classList.remove('hidden');
        // Highlight selected tab
        const tabEl = document.getElementById('tab-' + tab);
        tabEl.classList.add('border-black', 'text-black');
        tabEl.classList.remove('border-transparent', 'text-gray-500');
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>