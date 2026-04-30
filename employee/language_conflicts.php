<?php
$pageTitle = 'Language Conflicts - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireEmployee();

$userId = $_SESSION['user_id'];

// Filters
$filterStatus = $_GET['status'] ?? 'pending';
$filterType = $_GET['type'] ?? 'all'; // all, outgoing, incoming

// Build query — show requests created BY this TC or assigned TO this TC
$where = [];
$params = [];

if ($filterType === 'outgoing') {
    $where[] = "lmr.requested_by = ?";
    $params[] = $userId;
} elseif ($filterType === 'incoming') {
    $where[] = "lmr.assigned_to = ?";
    $params[] = $userId;
} else {
    $where[] = "(lmr.requested_by = ? OR lmr.assigned_to = ?)";
    $params[] = $userId;
    $params[] = $userId;
}

if ($filterStatus) {
    $where[] = "lmr.status = ?";
    $params[] = $filterStatus;
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

// Count pending requests for this TC
$myPendingCount = db()->fetch(
    "SELECT COUNT(*) as count FROM language_move_requests WHERE (requested_by = ? OR assigned_to = ?) AND status = 'pending'",
    [$userId, $userId]
)['count'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-black">Language Conflicts</h1>
            <p class="text-gray-500 mt-1">
                <?= $myPendingCount ?> pending requests
            </p>
        </div>
        <div class="flex gap-3 flex-wrap">
            <!-- Type Filter -->
            <select id="typeFilter" onchange="applyFilters()"
                class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>All</option>
                <option value="outgoing" <?= $filterType === 'outgoing' ? 'selected' : '' ?>>My Requests</option>
                <option value="incoming" <?= $filterType === 'incoming' ? 'selected' : '' ?>>Incoming Transfers</option>
            </select>
            <!-- Status Filter -->
            <select id="statusFilter" onchange="applyFilters()"
                class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="" <?= $filterStatus === '' ? 'selected' : '' ?>>All</option>
            </select>
        </div>
    </div>

    <!-- Requests List -->
    <div class="space-y-3">
        <?php if (empty($requests)): ?>
            <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center text-gray-500">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                </svg>
                <p class="text-lg font-medium">No <?= $filterStatus ? $filterStatus : '' ?> requests</p>
            </div>
        <?php else: ?>
            <?php foreach ($requests as $req): ?>
                <?php
                    $isOutgoing = $req['requested_by'] == $userId;
                    $isIncoming = $req['assigned_to'] == $userId;
                ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <!-- Direction Badge -->
                        <div>
                            <?php if ($isOutgoing && $isIncoming): ?>
                                <span class="px-2.5 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium">Self</span>
                            <?php elseif ($isOutgoing): ?>
                                <span class="px-2.5 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-medium">Outgoing</span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">Incoming</span>
                            <?php endif; ?>
                        </div>

                        <!-- Contact Info -->
                        <div class="flex items-center gap-3 flex-1">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-orange-400 to-red-500 text-white flex items-center justify-center font-bold">
                                <?= strtoupper(substr($req['contact_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <p class="font-bold"><?= sanitize($req['contact_name']) ?></p>
                                <p class="text-sm text-gray-500 font-mono"><?= sanitize($req['contact_phone']) ?></p>
                            </div>
                        </div>

                        <!-- Language -->
                        <span class="px-3 py-1.5 bg-blue-50 text-blue-700 rounded-full text-sm font-medium">
                            <?= sanitize($req['language_name']) ?>
                        </span>

                        <!-- From / To Info -->
                        <div class="text-sm text-gray-500">
                            <?php if ($isOutgoing): ?>
                                <span class="text-xs uppercase tracking-wide text-gray-400">Requested by:</span>
                                <p class="font-medium text-gray-700">You</p>
                            <?php else: ?>
                                <span class="text-xs uppercase tracking-wide text-gray-400">From:</span>
                                <p class="font-medium text-gray-700"><?= sanitize($req['requested_by_name']) ?></p>
                            <?php endif; ?>
                        </div>

                        <?php if ($req['assigned_to_name'] && !$isIncoming): ?>
                            <div class="text-sm text-gray-500">
                                <span class="text-xs uppercase tracking-wide text-gray-400">Assigned to:</span>
                                <p class="font-medium text-gray-700"><?= sanitize($req['assigned_to_name']) ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Status -->
                        <?php if ($req['status'] === 'pending'): ?>
                            <span class="px-3 py-1.5 bg-yellow-100 text-yellow-700 rounded-full text-sm font-medium">Pending</span>
                        <?php elseif ($req['status'] === 'approved'): ?>
                            <span class="px-3 py-1.5 bg-green-100 text-green-700 rounded-full text-sm font-medium">Approved</span>
                        <?php else: ?>
                            <span class="px-3 py-1.5 bg-red-100 text-red-700 rounded-full text-sm font-medium">Rejected</span>
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

<script>
    function applyFilters() {
        const status = document.getElementById('statusFilter').value;
        const type = document.getElementById('typeFilter').value;
        let url = '?';
        if (type) url += 'type=' + type + '&';
        if (status) url += 'status=' + status;
        window.location.href = url;
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
