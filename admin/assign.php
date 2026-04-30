<?php
$pageTitle = 'Assign Contacts - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign') {
        $contactIds = $_POST['contact_ids'] ?? [];
        $assignTo = $_POST['assign_to'] ?? '';

        if (!empty($contactIds) && $assignTo) {
            $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
            $params = array_merge([$assignTo], $contactIds);

            db()->update(
                "UPDATE contacts SET assigned_to = ? WHERE id IN ($placeholders)",
                $params
            );

            setFlash('success', count($contactIds) . ' contacts assigned successfully!');
        }
    } elseif ($action === 'quick_assign') {
        $contactId = $_POST['contact_id'] ?? 0;
        $assignTo = $_POST['assign_to'] ?? '';

        if ($contactId && $assignTo) {
            db()->update("UPDATE contacts SET assigned_to = ? WHERE id = ?", [$assignTo, $contactId]);
            setFlash('success', 'Contact assigned successfully!');
        }
    }

    header('Location: assign.php');
    exit;
}

// Get filters
$filterDate = $_GET['date'] ?? '';
$filterAssigned = $_GET['assigned'] ?? '';
$filterStatus = $_GET['status'] ?? 'unassigned';

// Build query
$where = [];
$params = [];

if ($filterDate) {
    $where[] = "DATE(c.import_date) = ?";
    $params[] = $filterDate;
}

if ($filterAssigned) {
    $where[] = "c.assigned_to = ?";
    $params[] = $filterAssigned;
}

if ($filterStatus === 'unassigned') {
    $where[] = "c.assigned_to IS NULL";
} elseif ($filterStatus === 'assigned') {
    $where[] = "c.assigned_to IS NOT NULL";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$contacts = db()->fetchAll("
    SELECT c.*, u.name as assigned_name, u.role as assigned_role, cs.name as status_name, cs.color as status_color
    FROM contacts c 
    LEFT JOIN users u ON c.assigned_to = u.id 
    LEFT JOIN call_statuses cs ON c.status_id = cs.id
    $whereClause
    ORDER BY c.created_at DESC
", $params);

// Get team leads and employees
$teamleads = getTeamLeads();
$employees = getEmployees();
$allStaff = array_merge($teamleads, $employees);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-black">Assign Contacts</h1>
        <p class="text-gray-500 mt-1">Quick assign contacts to team leads or employees</p>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Import Date</label>
                <input type="date" name="date" value="<?= $filterDate ?>"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Assigned To</label>
                <select name="assigned"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                    <option value="">All</option>
                    <?php foreach ($allStaff as $staff): ?>
                        <option value="<?= $staff['id'] ?>" <?= $filterAssigned == $staff['id'] ? 'selected' : '' ?>>
                            <?= sanitize($staff['name']) ?> (
                            <?= ucfirst($staff['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                    <option value="">All</option>
                    <option value="unassigned" <?= $filterStatus === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
                    <option value="assigned" <?= $filterStatus === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit"
                    class="w-full px-4 py-2.5 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Bulk Actions -->
    <form method="POST" id="bulkForm">
        <input type="hidden" name="action" value="assign">

        <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex flex-col sm:flex-row gap-4 items-center">
            <div class="flex items-center gap-2">
                <input type="checkbox" id="selectAll"
                    class="w-5 h-5 rounded border-gray-300 text-black focus:ring-black">
                <label for="selectAll" class="font-medium">Select All</label>
            </div>
            <div class="flex-1 flex flex-col sm:flex-row gap-4 items-stretch sm:items-center">
                <select name="assign_to" id="bulkAssignTo"
                    class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                    <option value="">-- Assign Selected To --</option>
                    <optgroup label="Team Leads">
                        <?php foreach ($teamleads as $tl): ?>
                            <option value="<?= $tl['id'] ?>">
                                <?= sanitize($tl['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Employees">
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>">
                                <?= sanitize($emp['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
                <button type="submit" id="bulkAssignBtn" disabled
                    class="px-6 py-2.5 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    Assign (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>

        <!-- Contacts List -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <?php if (empty($contacts)): ?>
                <div class="p-12 text-center text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="text-lg font-medium">No contacts found</p>
                    <p class="mt-1">Import contacts to get started</p>
                </div>
            <?php else: ?>
                <!-- Mobile Cards -->
                <div class="sm:hidden divide-y divide-gray-100">
                    <?php foreach ($contacts as $contact): ?>
                        <div class="p-4">
                            <div class="flex items-start gap-3">
                                <input type="checkbox" name="contact_ids[]" value="<?= $contact['id'] ?>"
                                    class="contact-checkbox w-5 h-5 mt-1 rounded border-gray-300 text-black focus:ring-black">
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-medium">
                                        <?= sanitize($contact['name']) ?>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        <?= sanitize($contact['phone']) ?>
                                    </p>
                                    <div class="flex items-center gap-2 mt-2">
                                        <?php if ($contact['assigned_name']): ?>
                                            <span class="px-2 py-1 bg-gray-100 rounded-lg text-xs font-medium">
                                                <?= sanitize($contact['assigned_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-lg text-xs font-medium">Unassigned</span>
                                        <?php endif; ?>
                                        <?php if ($contact['status_name']): ?>
                                            <span class="px-2 py-1 rounded-lg text-xs font-medium"
                                                style="background-color: <?= $contact['status_color'] ?>20; color: <?= $contact['status_color'] ?>">
                                                <?= sanitize($contact['status_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop Table -->
                <div class="overflow-x-auto">
                <table class="hidden sm:table w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left w-12"></th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Contact</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Phone</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Assigned To</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Status</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Import Date</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Quick Assign</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($contacts as $contact): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <input type="checkbox" name="contact_ids[]" value="<?= $contact['id'] ?>"
                                        class="contact-checkbox w-5 h-5 rounded border-gray-300 text-black focus:ring-black">
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-medium">
                                        <?= sanitize($contact['name']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?= sanitize($contact['phone']) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($contact['assigned_name']): ?>
                                        <span class="px-3 py-1 bg-gray-100 rounded-full text-xs font-medium">
                                            <?= sanitize($contact['assigned_name']) ?>
                                            <span class="text-gray-400">(
                                                <?= ucfirst($contact['assigned_role']) ?>)
                                            </span>
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($contact['status_name']): ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                                            style="background-color: <?= $contact['status_color'] ?>20; color: <?= $contact['status_color'] ?>">
                                            <?= sanitize($contact['status_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?= $contact['import_date'] ? formatDate($contact['import_date']) : '-' ?>
                                </td>
                                <td class="px-6 py-4">
                                    <select onchange="quickAssign(<?= $contact['id'] ?>, this.value)"
                                        class="text-sm px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-black">
                                        <option value="">Quick Assign</option>
                                        <optgroup label="Team Leads">
                                            <?php foreach ($teamleads as $tl): ?>
                                                <option value="<?= $tl['id'] ?>" <?= $contact['assigned_to'] == $tl['id'] ? 'selected' : '' ?>>
                                                    <?= sanitize($tl['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <optgroup label="Employees">
                                            <?php foreach ($employees as $emp): ?>
                                                <option value="<?= $emp['id'] ?>" <?= $contact['assigned_to'] == $emp['id'] ? 'selected' : '' ?>>
                                                    <?= sanitize($emp['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Quick Assign Form (hidden) -->
<form id="quickAssignForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="quick_assign">
    <input type="hidden" name="contact_id" id="quickContactId">
    <input type="hidden" name="assign_to" id="quickAssignTo">
</form>

<script>
    // Select all
    document.getElementById('selectAll')?.addEventListener('change', function () {
        document.querySelectorAll('.contact-checkbox').forEach(cb => {
            cb.checked = this.checked;
        });
        updateSelectedCount();
    });

    // Individual checkboxes
    document.querySelectorAll('.contact-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });

    function updateSelectedCount() {
        const count = document.querySelectorAll('.contact-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = count;
        document.getElementById('bulkAssignBtn').disabled = count === 0;
    }

    // Quick assign
    function quickAssign(contactId, assignTo) {
        if (!assignTo) return;
        document.getElementById('quickContactId').value = contactId;
        document.getElementById('quickAssignTo').value = assignTo;
        document.getElementById('quickAssignForm').submit();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>