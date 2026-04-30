<?php
$pageTitle = 'My Contacts - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireTeamLead();

$userId = $_SESSION['user_id'];

// Get team members for assignment
$teamMembers = getTeamMembers($userId);

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'bulk_assign') {
        $contactIds = $_POST['contact_ids'] ?? [];
        $assignTo = $_POST['assign_to'] ?? '';

        if (!empty($contactIds) && $assignTo) {
            // Only allow assignment to self or team members
            $allowedIds = array_merge([$userId], array_column($teamMembers, 'id'));
            if (in_array($assignTo, $allowedIds)) {
                $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
                $params = array_merge([$assignTo], $contactIds);
                db()->update("UPDATE contacts SET assigned_to = ? WHERE id IN ($placeholders)", $params);
                setFlash('success', count($contactIds) . ' contacts assigned!');
            }
        }
        header('Location: contacts.php');
        exit;
    }

    if ($action === 'bulk_unassign') {
        $contactIds = $_POST['contact_ids'] ?? [];

        if (!empty($contactIds)) {
            // Reassign contacts back to TL (not NULL - only admin can fully unassign)
            $allowedIds = array_merge([$userId], array_column($teamMembers, 'id'));
            $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
            $allowedPlaceholders = implode(',', array_fill(0, count($allowedIds), '?'));
            $params = array_merge([$userId], $contactIds, $allowedIds);
            db()->update("UPDATE contacts SET assigned_to = ? WHERE id IN ($placeholders) AND assigned_to IN ($allowedPlaceholders)", $params);
            setFlash('success', count($contactIds) . ' contacts reassigned to you!');
        }
        header('Location: contacts.php');
        exit;
    }

    if ($action === 'single_assign') {
        $contactId = $_POST['contact_id'] ?? 0;
        $assignTo = $_POST['assign_to'] ?? '';

        if ($contactId && $assignTo) {
            $allowedIds = array_merge([$userId], array_column($teamMembers, 'id'));
            if (in_array($assignTo, $allowedIds)) {
                db()->update("UPDATE contacts SET assigned_to = ? WHERE id = ?", [$assignTo, $contactId]);
                setFlash('success', 'Contact assigned!');
            }
        }
        header('Location: contacts.php');
        exit;
    }
}

// Get filter
$filterAssign = $_GET['filter'] ?? 'all';
$filterMember = $_GET['member'] ?? '';

// Build query - get contacts assigned to TL or their team members
$teamMemberIds = array_column($teamMembers, 'id');
$allIds = array_merge([$userId], $teamMemberIds);
$placeholders = implode(',', array_fill(0, count($allIds), '?'));

$where = "c.assigned_to IN ($placeholders)";
$params = $allIds;

if ($filterAssign === 'mine') {
    $where = "c.assigned_to = ?";
    $params = [$userId];
} elseif ($filterAssign === 'team') {
    if (!empty($teamMemberIds)) {
        $placeholders = implode(',', array_fill(0, count($teamMemberIds), '?'));
        $where = "c.assigned_to IN ($placeholders)";
        $params = $teamMemberIds;
    } else {
        $where = "1=0";
        $params = [];
    }
} elseif ($filterAssign === 'unassigned') {
    $where = "c.assigned_to = ? AND c.is_called = 0";
    $params = [$userId];
}

// Filter by specific team member
if ($filterMember) {
    $allowedIds = array_merge([$userId], $teamMemberIds);
    if (in_array(intval($filterMember), $allowedIds)) {
        $where = "c.assigned_to = ?";
        $params = [intval($filterMember)];
    }
}

$contacts = db()->fetchAll("
    SELECT c.*, cs.name as status_name, cs.color, u.name as assigned_name
    FROM contacts c 
    LEFT JOIN call_statuses cs ON c.status_id = cs.id
    LEFT JOIN users u ON c.assigned_to = u.id
    WHERE $where
    ORDER BY c.is_called, c.created_at DESC
    LIMIT 200
", $params);

// Stats
$myContacts = db()->fetch("SELECT COUNT(*) as count FROM contacts WHERE assigned_to = ?", [$userId])['count'];
$teamContacts = 0;
if (!empty($teamMemberIds)) {
    $placeholders = implode(',', array_fill(0, count($teamMemberIds), '?'));
    $teamContacts = db()->fetch("SELECT COUNT(*) as count FROM contacts WHERE assigned_to IN ($placeholders)", $teamMemberIds)['count'];
}
$pendingContacts = db()->fetch("SELECT COUNT(*) as count FROM contacts WHERE assigned_to = ? AND is_called = 0", [$userId])['count'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-black">My Contacts</h1>
            <p class="text-gray-500 mt-1">Manage and assign contacts to your team</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4 mb-4">
        <a href="?filter=mine"
            class="bg-white rounded-xl border <?= $filterAssign === 'mine' && !$filterMember ? 'border-black ring-2 ring-black' : 'border-gray-200' ?> p-4 text-center hover:border-gray-300 transition-colors">
            <p class="text-2xl font-bold"><?= $myContacts ?></p>
            <p class="text-xs text-gray-500">My Contacts</p>
        </a>
        <a href="?filter=team"
            class="bg-white rounded-xl border <?= $filterAssign === 'team' && !$filterMember ? 'border-black ring-2 ring-black' : 'border-gray-200' ?> p-4 text-center hover:border-gray-300 transition-colors">
            <p class="text-2xl font-bold text-blue-600"><?= $teamContacts ?></p>
            <p class="text-xs text-blue-600">Team Contacts</p>
        </a>
        <a href="?filter=unassigned"
            class="bg-white rounded-xl border <?= $filterAssign === 'unassigned' && !$filterMember ? 'border-black ring-2 ring-black' : 'border-gray-200' ?> p-4 text-center hover:border-gray-300 transition-colors">
            <p class="text-2xl font-bold text-amber-600"><?= $pendingContacts ?></p>
            <p class="text-xs text-amber-600">My Pending</p>
        </a>
    </div>

    <!-- Assigned To Filter -->
    <div class="bg-white rounded-xl border border-gray-200 p-3 mb-4">
        <div class="flex items-center gap-3">
            <span class="text-sm font-medium text-gray-600">Assigned To:</span>
            <select id="memberFilter" onchange="applyMemberFilter(this.value)"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-black focus:border-black">
                <option value="">All</option>
                <option value="<?= $userId ?>" <?= $filterMember == $userId ? 'selected' : '' ?>>Me (<?= sanitize(currentUser()['name']) ?>)</option>
                <?php foreach ($teamMembers as $member): ?>
                    <option value="<?= $member['id'] ?>" <?= $filterMember == $member['id'] ? 'selected' : '' ?>><?= sanitize($member['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Bulk Actions -->
    <form method="POST" id="bulkForm">
        <div class="bg-white rounded-xl border border-gray-200 p-3 mb-4 flex flex-wrap gap-3 items-center">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="selectAll"
                    class="w-5 h-5 rounded border-gray-300 text-black focus:ring-black">
                <span class="text-sm font-medium">Select All</span>
            </label>

            <div class="h-6 w-px bg-gray-200"></div>

            <span class="text-sm text-gray-500">Selected: <strong id="selectedCount">0</strong></span>

            <div class="flex-1"></div>

            <span class="text-sm font-medium text-gray-600">Assign to:</span>
            <div class="flex flex-wrap gap-2 items-center">
                <label class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg cursor-pointer transition-colors">
                    <input type="radio" name="assign_to" value="<?= $userId ?>" class="w-4 h-4 text-black focus:ring-black">
                    <span class="text-sm font-medium">Me</span>
                </label>
                <?php foreach ($teamMembers as $member): ?>
                <label class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 rounded-lg cursor-pointer transition-colors member-checkbox">
                    <input type="radio" name="assign_to" value="<?= $member['id'] ?>" class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm font-medium text-blue-700"><?= sanitize($member['name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" name="action" value="bulk_assign" id="btnAssign" disabled
                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed">
                Assign Selected
            </button>
            <button type="submit" name="action" value="bulk_unassign" id="btnUnassign" disabled
                class="px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm font-medium hover:bg-yellow-700 disabled:opacity-50 disabled:cursor-not-allowed">
                Unassign
            </button>
            <button type="button" onclick="showModal('rangeAssignModal')"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
                Quick Assign
            </button>
        </div>

        <!-- Contacts Table -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <?php if (empty($contacts)): ?>
                <div class="p-12 text-center text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="text-lg font-medium">No contacts found</p>
                    <a href="?filter=all" class="text-sm text-blue-600 hover:underline mt-2 inline-block">View all
                        contacts</a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left w-10"></th>
                                <th class="px-3 py-3 text-left font-semibold text-gray-600 w-14">Sl.No</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Name</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Phone</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Assigned To</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-600">Quick Assign</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($contacts as $slIndex => $contact): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" name="contact_ids[]" value="<?= $contact['id'] ?>"
                                            class="contact-cb w-5 h-5 rounded border-gray-300 text-black focus:ring-black">
                                    </td>
                                    <td class="px-3 py-3 text-gray-400 text-xs font-mono"><?= $slIndex + 1 ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 rounded-full <?= $contact['is_called'] ? 'bg-gray-200 text-gray-600' : 'bg-black text-white' ?> flex items-center justify-center font-bold text-sm">
                                                <?= strtoupper(substr($contact['name'], 0, 1)) ?>
                                            </div>
                                            <span
                                                class="font-medium <?= $contact['is_called'] ? 'text-gray-400' : '' ?>"><?= sanitize($contact['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 font-mono"><?= sanitize($contact['phone']) ?></td>
                                    <td class="px-4 py-3">
                                        <?php if ($contact['assigned_to'] == $userId): ?>
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-1 bg-black text-white rounded-lg text-xs font-medium">Me</span>
                                        <?php else: ?>
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-700 rounded-lg text-xs font-medium"><?= sanitize($contact['assigned_name']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if ($contact['status_name']): ?>
                                            <span class="px-2 py-1 rounded-lg text-xs font-medium"
                                                style="background-color: <?= $contact['color'] ?>20; color: <?= $contact['color'] ?>"><?= sanitize($contact['status_name']) ?></span>
                                        <?php elseif (!$contact['is_called']): ?>
                                            <span
                                                class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-lg text-xs font-medium">Pending</span>
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-center">
                                            <select onchange="quickAssign(<?= $contact['id'] ?>, this.value)"
                                                class="text-xs px-2 py-1 border border-gray-300 rounded-lg focus:ring-1 focus:ring-black">
                                                <option value="">Assign...</option>
                                                <option value="<?= $userId ?>" <?= $contact['assigned_to'] == $userId ? 'selected' : '' ?>>Me</option>
                                                <?php foreach ($teamMembers as $member): ?>
                                                    <option value="<?= $member['id'] ?>" <?= $contact['assigned_to'] == $member['id'] ? 'selected' : '' ?>><?= sanitize($member['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
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
    <input type="hidden" name="action" value="single_assign">
    <input type="hidden" name="contact_id" id="qaContactId">
    <input type="hidden" name="assign_to" id="qaAssignTo">
</form>

<!-- Range Assign Modal -->
<div id="rangeAssignModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4" data-modal-backdrop>
    <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-black">Quick Assign by Range</h2>
                    <p class="text-sm text-gray-500">Transfer pending contacts between team</p>
                </div>
            </div>
        </div>
        <form id="rangeAssignForm" class="p-6 space-y-5">
            <!-- From / To -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">From *</label>
                    <select id="rangeFrom" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        <option value="">Select...</option>
                        <option value="<?= $userId ?>">Me (<?= sanitize(currentUser()['name']) ?>)</option>
                        <?php foreach ($teamMembers as $member): ?>
                            <option value="<?= $member['id'] ?>"><?= sanitize($member['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">To *</label>
                    <select id="rangeTo" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        <option value="">Select...</option>
                        <option value="<?= $userId ?>">Me (<?= sanitize(currentUser()['name']) ?>)</option>
                        <?php foreach ($teamMembers as $member): ?>
                            <option value="<?= $member['id'] ?>"><?= sanitize($member['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <!-- Range inputs -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Row Range</label>
                <div class="grid grid-cols-2 gap-3">
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">FROM</span>
                        <input type="number" id="rangeStart" min="1" value="1" required
                            class="w-full pl-14 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-right font-mono">
                    </div>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">TO</span>
                        <input type="number" id="rangeEnd" min="1" value="50" required
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-right font-mono">
                    </div>
                </div>
                <!-- Quick range buttons -->
                <div class="flex gap-1.5 mt-2">
                    <button type="button" onclick="setRange(1, 50)" class="flex-1 text-xs px-2 py-1.5 bg-gray-100 hover:bg-indigo-100 hover:text-indigo-700 rounded-lg font-medium transition-colors">1-50</button>
                    <button type="button" onclick="setRange(1, 100)" class="flex-1 text-xs px-2 py-1.5 bg-gray-100 hover:bg-indigo-100 hover:text-indigo-700 rounded-lg font-medium transition-colors">1-100</button>
                    <button type="button" onclick="setRange(1, 200)" class="flex-1 text-xs px-2 py-1.5 bg-gray-100 hover:bg-indigo-100 hover:text-indigo-700 rounded-lg font-medium transition-colors">1-200</button>
                    <button type="button" onclick="setRange(1, 500)" class="flex-1 text-xs px-2 py-1.5 bg-gray-100 hover:bg-indigo-100 hover:text-indigo-700 rounded-lg font-medium transition-colors">1-500</button>
                    <button type="button" onclick="setRange(1, 1000)" class="flex-1 text-xs px-2 py-1.5 bg-gray-100 hover:bg-indigo-100 hover:text-indigo-700 rounded-lg font-medium transition-colors">1-1K</button>
                </div>
            </div>
            <!-- Preview -->
            <div id="rangePreview" class="hidden bg-indigo-50 border border-indigo-200 rounded-xl p-3">
                <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-indigo-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm text-indigo-700" id="rangePreviewText"></p>
                </div>
            </div>
            <!-- Actions -->
            <div class="flex gap-3 pt-1">
                <button type="button" onclick="hideModal('rangeAssignModal')"
                    class="flex-1 px-4 py-3 border border-gray-300 rounded-xl font-medium hover:bg-gray-50 transition-colors">Cancel</button>
                <button type="submit" id="rangeAssignBtn"
                    class="flex-1 px-4 py-3 bg-indigo-600 text-white rounded-xl font-medium hover:bg-indigo-700 transition-colors flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    Assign Range
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Select all
    document.getElementById('selectAll')?.addEventListener('change', function () {
        document.querySelectorAll('.contact-cb').forEach(cb => cb.checked = this.checked);
        updateBulkButtons();
    });

    // Individual checkboxes
    document.querySelectorAll('.contact-cb').forEach(cb => {
        cb.addEventListener('change', updateBulkButtons);
    });

    // Radio buttons for assignment
    document.querySelectorAll('input[name="assign_to"]').forEach(radio => {
        radio.addEventListener('change', updateBulkButtons);
    });

    function updateBulkButtons() {
        const count = document.querySelectorAll('.contact-cb:checked').length;
        document.getElementById('selectedCount').textContent = count;

        const hasSelection = count > 0;
        const hasAssignee = document.querySelector('input[name="assign_to"]:checked');
        document.getElementById('btnAssign').disabled = !hasSelection || !hasAssignee;
        document.getElementById('btnUnassign').disabled = !hasSelection;
    }

    function applyMemberFilter(value) {
        if (value) {
            window.location.href = '?member=' + value;
        } else {
            window.location.href = '?filter=all';
        }
    }

    function quickAssign(contactId, assignTo) {
        if (!assignTo) return;
        document.getElementById('qaContactId').value = contactId;
        document.getElementById('qaAssignTo').value = assignTo;
        document.getElementById('quickAssignForm').submit();
    }

    // Range assign functions
    function setRange(start, end) {
        document.getElementById('rangeStart').value = start;
        document.getElementById('rangeEnd').value = end;
        updateRangePreview();
    }

    function updateRangePreview() {
        const start = parseInt(document.getElementById('rangeStart').value);
        const end = parseInt(document.getElementById('rangeEnd').value);
        const fromEl = document.getElementById('rangeFrom');
        const toEl = document.getElementById('rangeTo');
        const fromName = fromEl.options[fromEl.selectedIndex]?.text;
        const toName = toEl.options[toEl.selectedIndex]?.text;

        if (start && end && fromEl.value && toEl.value && fromName !== 'Select employee...' && toName !== 'Select employee...') {
            const count = end - start + 1;
            document.getElementById('rangePreviewText').textContent = `Move ${count.toLocaleString()} pending contacts (rows ${start}-${end}) from ${fromName} to ${toName}`;
            document.getElementById('rangePreview').classList.remove('hidden');
        } else {
            document.getElementById('rangePreview').classList.add('hidden');
        }
    }

    document.getElementById('rangeStart')?.addEventListener('input', updateRangePreview);
    document.getElementById('rangeEnd')?.addEventListener('input', updateRangePreview);
    document.getElementById('rangeFrom')?.addEventListener('change', updateRangePreview);
    document.getElementById('rangeTo')?.addEventListener('change', updateRangePreview);

    document.getElementById('rangeAssignForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const start = parseInt(document.getElementById('rangeStart').value);
        const end = parseInt(document.getElementById('rangeEnd').value);
        const fromUser = parseInt(document.getElementById('rangeFrom').value);
        const assignTo = parseInt(document.getElementById('rangeTo').value);

        if (start > end) {
            showToast('Start row must be less than or equal to end row', 'error');
            return;
        }
        if (fromUser === assignTo) {
            showToast('From and To must be different employees', 'error');
            return;
        }

        const btn = document.getElementById('rangeAssignBtn');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin h-4 w-4 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

        try {
            const response = await fetch('<?= APP_URL ?>/api/bulk_operations.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'reassign_range',
                    from_user: fromUser,
                    assign_to: assignTo,
                    range: { start, end }
                })
            });
            const result = await response.json();
            if (result.success) {
                showToast(`Successfully reassigned ${result.data.affected_rows} contacts!`);
                hideModal('rangeAssignModal');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Error: ' + (result.error || 'Unknown error'), 'error');
            }
        } catch (error) {
            showToast('Failed to assign contacts: ' + error.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>