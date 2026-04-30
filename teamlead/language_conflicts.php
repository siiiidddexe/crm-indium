<?php
$pageTitle = 'Language Conflicts - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireTeamLead();

$userId = $_SESSION['user_id'];

// Filters
$filterStatus = $_GET['status'] ?? 'pending';
$filterLang = $_GET['language'] ?? '';

// Build query — only show requests from team members
$where = ["u.teamlead_id = ?"];
$params = [$userId];

if ($filterStatus) {
    $where[] = "lmr.status = ?";
    $params[] = $filterStatus;
}
if ($filterLang) {
    $where[] = "lmr.target_language_id = ?";
    $params[] = $filterLang;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

$requests = db()->fetchAll("
    SELECT lmr.*, 
           c.name as contact_name, c.phone as contact_phone,
           u.name as requested_by_name,
           l.name as language_name,
           au.name as assigned_to_name,
           ap.name as approved_by_name
    FROM language_move_requests lmr
    JOIN contacts c ON lmr.contact_id = c.id
    JOIN users u ON lmr.requested_by = u.id
    JOIN languages l ON lmr.target_language_id = l.id
    LEFT JOIN users au ON lmr.assigned_to = au.id
    LEFT JOIN users ap ON lmr.approved_by = ap.id
    $whereClause
    ORDER BY lmr.created_at DESC
", $params);

$languages = getLanguages();
$pendingCount = getPendingMoveRequestsCount($userId);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-black">Team Language Conflicts</h1>
            <p class="text-gray-500 mt-1">
                <?= $pendingCount ?> pending requests from your team
            </p>
        </div>
        <div class="flex gap-3 flex-wrap">
            <?php if ($filterStatus === 'pending' && !empty($requests)): ?>
                <button onclick="autoAssignAll()"
                    id="autoAssignAllBtn"
                    class="px-4 py-2.5 bg-green-600 text-white rounded-xl font-medium hover:bg-green-700 transition-colors text-sm">
                    ⚡ Auto Assign All (<?= count($requests) ?>)
                </button>
            <?php endif; ?>
            <select id="statusFilter" onchange="applyFilters()"
                class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="" <?= $filterStatus === '' ? 'selected' : '' ?>>All</option>
            </select>
            <select id="langFilter" onchange="applyFilters()"
                class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                <option value="">All Languages</option>
                <?php foreach ($languages as $lang): ?>
                    <option value="<?= $lang['id'] ?>" <?= $filterLang == $lang['id'] ? 'selected' : '' ?>>
                        <?= sanitize($lang['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Bulk Actions -->
    <?php if ($filterStatus === 'pending' && !empty($requests)): ?>
        <div id="bulkActions" class="bg-white rounded-xl border border-gray-200 p-4 mb-6 hidden">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <span class="text-sm font-medium text-gray-700"><span id="selectedCount">0</span> selected</span>
                <select id="bulkAssignEmployee"
                    class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                    <option value="">-- Select Employee to Assign --</option>
                </select>
                <button onclick="bulkApprove()"
                    class="px-6 py-2.5 bg-green-600 text-white rounded-xl font-medium hover:bg-green-700 transition-colors">
                    Bulk Assign & Approve
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Requests List -->
    <div class="space-y-3">
        <?php if (empty($requests)): ?>
            <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center text-gray-500">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                </svg>
                <p class="text-lg font-medium">No
                    <?= $filterStatus ? $filterStatus : '' ?> requests
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($requests as $req): ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-md transition-shadow"
                    data-request-id="<?= $req['id'] ?>" data-language-id="<?= $req['target_language_id'] ?>">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <?php if ($req['status'] === 'pending'): ?>
                            <input type="checkbox" class="bulk-checkbox w-5 h-5 rounded border-gray-300 text-black focus:ring-black"
                                value="<?= $req['id'] ?>" onchange="updateBulkUI()">
                        <?php endif; ?>

                        <div class="flex items-center gap-3 flex-1">
                            <div
                                class="w-12 h-12 rounded-full bg-gradient-to-br from-orange-400 to-red-500 text-white flex items-center justify-center font-bold">
                                <?= strtoupper(substr($req['contact_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <p class="font-bold">
                                    <?= sanitize($req['contact_name']) ?>
                                </p>
                                <p class="text-sm text-gray-500 font-mono">
                                    <?= sanitize($req['contact_phone']) ?>
                                </p>
                            </div>
                        </div>

                        <span class="px-3 py-1.5 bg-blue-50 text-blue-700 rounded-full text-sm font-medium">
                            🌐
                            <?= sanitize($req['language_name']) ?>
                        </span>

                        <div class="text-sm text-gray-500">
                            <span class="text-xs uppercase tracking-wide text-gray-400">From:</span>
                            <p class="font-medium text-gray-700">
                                <?= sanitize($req['requested_by_name']) ?>
                            </p>
                        </div>

                        <?php if ($req['status'] === 'pending'): ?>
                            <div class="flex gap-2">
                                <button onclick="autoAssign(<?= $req['id'] ?>)"
                                    class="px-4 py-2 bg-green-100 text-green-700 rounded-xl text-sm font-medium hover:bg-green-200 transition-colors">
                                    ⚡ Auto
                                </button>
                                <button onclick="showManualAssign(<?= $req['id'] ?>, <?= $req['target_language_id'] ?>)"
                                    class="px-4 py-2 bg-blue-100 text-blue-700 rounded-xl text-sm font-medium hover:bg-blue-200 transition-colors">
                                    👤 Manual
                                </button>
                                <button onclick="rejectRequest(<?= $req['id'] ?>)"
                                    class="px-4 py-2 bg-red-100 text-red-700 rounded-xl text-sm font-medium hover:bg-red-200 transition-colors">
                                    ✕
                                </button>
                            </div>
                        <?php elseif ($req['status'] === 'approved'): ?>
                            <div class="text-sm">
                                <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full font-medium">✓ Approved</span>
                                <p class="text-gray-500 mt-1">Assigned to:
                                    <?= sanitize($req['assigned_to_name'] ?? 'N/A') ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium">✕ Rejected</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($req['notes']): ?>
                        <p class="mt-3 text-sm text-gray-500 bg-gray-50 rounded-lg p-3 italic">
                            <?= sanitize($req['notes']) ?>
                        </p>
                    <?php endif; ?>
                    <p class="mt-2 text-xs text-gray-400">
                        <?= formatDate($req['created_at'], 'M d, Y h:i A') ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Manual Assign Modal -->
<div id="manualAssignModal"
    class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4" data-modal-backdrop>
    <div class="bg-white rounded-2xl w-full max-w-md">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-black">Manual Assignment</h2>
            <p class="text-sm text-gray-500 mt-1">Select an employee who speaks this language</p>
        </div>
        <div class="p-6">
            <div id="speakersList" class="space-y-2 max-h-64 overflow-y-auto mb-4">
                <p class="text-gray-500 text-center py-4">Loading speakers...</p>
            </div>
            <button type="button" onclick="hideModal('manualAssignModal')"
                class="w-full px-4 py-3 border border-gray-300 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
    const tlId = <?= $userId ?>;
    let currentRequestId = null;

    function applyFilters() {
        const status = document.getElementById('statusFilter').value;
        const lang = document.getElementById('langFilter').value;
        let url = '?';
        if (status) url += 'status=' + status + '&';
        if (lang) url += 'language=' + lang;
        window.location.href = url;
    }

    function autoAssign(requestId) {
        fetch('<?= APP_URL ?>/api/language.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'approve_request', request_id: requestId, approved_by: tlId })
        }).then(r => r.json()).then(data => {
            if (data.success) { showToast('Assigned to: ' + data.assigned_to); setTimeout(() => location.reload(), 1000); }
            else showToast('Error: ' + data.error, 'error');
        });
    }

    function showManualAssign(requestId, languageId) {
        currentRequestId = requestId;
        const list = document.getElementById('speakersList');
        list.innerHTML = '<p class="text-gray-500 text-center py-4">Loading speakers...</p>';
        showModal('manualAssignModal');

        fetch('<?= APP_URL ?>/api/language.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_language_speakers', language_id: languageId })
        }).then(r => r.json()).then(data => {
            if (data.success && data.data.length > 0) {
                list.innerHTML = data.data.map(emp => `
                <button onclick="manualAssign(${emp.id})" 
                        class="w-full flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors text-left">
                    <div class="w-10 h-10 rounded-full bg-black text-white flex items-center justify-center font-bold text-sm">
                        ${emp.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="font-medium">${emp.name}</p>
                        <p class="text-xs text-gray-500 capitalize">${emp.role}</p>
                    </div>
                </button>
            `).join('');
            } else {
                list.innerHTML = '<p class="text-gray-500 text-center py-4">No employees speak this language yet</p>';
            }
        });
    }

    function manualAssign(employeeId) {
        fetch('<?= APP_URL ?>/api/language.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'approve_request', request_id: currentRequestId, assign_to: employeeId, approved_by: tlId })
        }).then(r => r.json()).then(data => {
            if (data.success) { hideModal('manualAssignModal'); showToast('Assigned to: ' + data.assigned_to); setTimeout(() => location.reload(), 1000); }
            else showToast('Error: ' + data.error, 'error');
        });
    }

    function rejectRequest(requestId) {
        fetch('<?= APP_URL ?>/api/language.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reject_request', request_id: requestId, approved_by: tlId })
        }).then(r => r.json()).then(data => {
            if (data.success) { showToast('Request rejected'); setTimeout(() => location.reload(), 1000); }
            else showToast('Error: ' + data.error, 'error');
        });
    }

    function updateBulkUI() {
        const checked = document.querySelectorAll('.bulk-checkbox:checked');
        const bulkBar = document.getElementById('bulkActions');
        const countEl = document.getElementById('selectedCount');
        if (checked.length > 0) {
            bulkBar?.classList.remove('hidden');
            if (countEl) countEl.textContent = checked.length;
            const firstReq = checked[0].closest('[data-request-id]');
            const langId = firstReq?.dataset.languageId;
            if (langId) loadBulkSpeakers(langId);
        } else {
            bulkBar?.classList.add('hidden');
        }
    }

    function loadBulkSpeakers(languageId) {
        const select = document.getElementById('bulkAssignEmployee');
        fetch('<?= APP_URL ?>/api/language.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_language_speakers', language_id: languageId })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                select.innerHTML = '<option value="">-- Select Employee --</option>' +
                    data.data.map(e => `<option value="${e.id}">${e.name} (${e.role})</option>`).join('');
            }
        });
    }

    function bulkApprove() {
        const checked = document.querySelectorAll('.bulk-checkbox:checked');
        const assignTo = document.getElementById('bulkAssignEmployee').value;
        if (!assignTo) { showToast('Please select an employee', 'error'); return; }

        const requestIds = Array.from(checked).map(cb => parseInt(cb.value));
        fetch('<?= APP_URL ?>/api/language.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'bulk_approve', request_ids: requestIds, assign_to: parseInt(assignTo), approved_by: tlId })
        }).then(r => r.json()).then(data => {
            if (data.success) { showToast(data.approved_count + ' requests approved!'); setTimeout(() => location.reload(), 1000); }
            else showToast('Error: ' + data.error, 'error');
        });
    }

    function autoAssignAll() {
        const btn = document.getElementById('autoAssignAllBtn');
        btn.disabled = true;
        btn.textContent = 'Assigning...';

        fetch('<?= APP_URL ?>/api/language.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'bulk_auto_assign',
                approved_by: tlId,
                teamlead_id: tlId
            })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                let msg = data.assigned + ' requests auto-assigned!';
                if (data.skipped > 0) msg += ' (' + data.skipped + ' skipped - no speakers found)';
                showToast(msg);
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Error: ' + data.error, 'error');
                btn.disabled = false;
                btn.textContent = 'Auto Assign All';
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>