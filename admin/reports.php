<?php
$pageTitle = 'Reports - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Get filter
$filterDateFrom = $_GET['date_from'] ?? serverDate();
$filterDateTo = $_GET['date_to'] ?? serverDate();
$filterEmployee = $_GET['employee'] ?? '';

// Handle export
if (isset($_GET['export'])) {
    $employeeId = $_GET['export'];
    $dateFrom = $_GET['export_date_from'] ?? $filterDateFrom;
    $dateTo = $_GET['export_date_to'] ?? $filterDateTo;
    
    $data = db()->fetchAll("
        SELECT c.name, c.phone, cs.name as status, cl.call_time, cl.notes
        FROM call_logs cl
        JOIN contacts c ON cl.contact_id = c.id
        LEFT JOIN call_statuses cs ON cl.status_id = cs.id
        WHERE cl.user_id = ? AND DATE(cl.call_time) BETWEEN ? AND ?
        ORDER BY cl.call_time
    ", [$employeeId, $dateFrom, $dateTo]);
    
    $employee = db()->fetch("SELECT name FROM users WHERE id = ?", [$employeeId]);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . $employee['name'] . '_' . $dateFrom . '_to_' . $dateTo . '.csv"');
    
    echo "Name,Phone,Status,Call Time,Notes\n";
    foreach ($data as $row) {
        echo '"' . str_replace('"', '""', $row['name']) . '",';
        echo '"' . $row['phone'] . '",';
        echo '"' . $row['status'] . '",';
        echo '"' . $row['call_time'] . '",';
        echo '"' . str_replace('"', '""', $row['notes'] ?? '') . '"';
        echo "\n";
    }
    exit;
}

// Get all employees with stats
$employees = db()->fetchAll("
    SELECT 
        u.*,
        (SELECT COUNT(*) FROM contacts WHERE assigned_to = u.id) as total_contacts,
        (SELECT COUNT(*) FROM contacts WHERE assigned_to = u.id AND is_called = 1) as called_contacts,
        (SELECT COUNT(*) FROM call_logs WHERE user_id = u.id AND DATE(call_time) BETWEEN ? AND ?) as range_calls
    FROM users u
    WHERE u.role IN ('employee', 'teamlead') AND u.is_active = 1
    ORDER BY u.role DESC, u.name
", [$filterDateFrom, $filterDateTo]);

// Get call statuses for breakdown
$statuses = getCallStatuses();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-black">Reports</h1>
            <p class="text-gray-500 mt-1">View employee performance and progress</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <div class="flex items-center gap-2">
                <label for="reportDateFrom" class="text-sm font-medium text-gray-600">From</label>
                <input type="date" id="reportDateFrom" value="<?= $filterDateFrom ?>" class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
            </div>
            <div class="flex items-center gap-2">
                <label for="reportDateTo" class="text-sm font-medium text-gray-600">To</label>
                <input type="date" id="reportDateTo" value="<?= $filterDateTo ?>" class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
            </div>
            <button onclick="filterByDate()" class="px-5 py-2.5 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
                Filter
            </button>
        </div>
    </div>

    <!-- Employee Reports -->
    <div class="space-y-4">
        <?php if (empty($employees)): ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center text-gray-500">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            <p class="text-lg font-medium">No employees found</p>
        </div>
        <?php else: ?>
        <?php foreach ($employees as $emp): ?>
        <?php
        // Get status breakdown for this employee on selected date range
        $statusBreakdown = db()->fetchAll("
            SELECT cs.name, cs.color, COUNT(*) as count
            FROM call_logs cl
            JOIN call_statuses cs ON cl.status_id = cs.id
            WHERE cl.user_id = ? AND DATE(cl.call_time) BETWEEN ? AND ?
            GROUP BY cs.id
            ORDER BY count DESC
        ", [$emp['id'], $filterDateFrom, $filterDateTo]);
        
        $progress = $emp['total_contacts'] > 0 ? round(($emp['called_contacts'] / $emp['total_contacts']) * 100) : 0;
        ?>
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <!-- Employee Info -->
                    <div class="flex items-center gap-4 flex-1">
                        <div class="w-14 h-14 rounded-full bg-black text-white flex items-center justify-center font-bold text-lg">
                            <?= strtoupper(substr($emp['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-lg"><?= sanitize($emp['name']) ?></h3>
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $emp['role'] === 'teamlead' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700' ?>">
                                    <?= ucfirst($emp['role']) ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-500"><?= sanitize($emp['email']) ?></p>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-4 sm:gap-8">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-black"><?= $emp['total_contacts'] ?></p>
                            <p class="text-xs text-gray-500">Assigned</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-green-600"><?= $emp['called_contacts'] ?></p>
                            <p class="text-xs text-gray-500">Called</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-blue-600"><?= $emp['range_calls'] ?></p>
                            <p class="text-xs text-gray-500"><?= ($filterDateFrom === $filterDateTo) ? 'Today' : 'In Range' ?></p>
                        </div>
                    </div>

                    <!-- Export -->
                    <a href="?export=<?= $emp['id'] ?>&export_date_from=<?= $filterDateFrom ?>&export_date_to=<?= $filterDateTo ?>" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-100 text-black rounded-xl font-medium hover:bg-gray-200 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Export
                    </a>
                </div>

                <!-- Progress Bar -->
                <div class="mt-4">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm text-gray-600">Overall Progress</span>
                        <span class="text-sm font-medium"><?= $progress ?>%</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-black rounded-full transition-all" style="width: <?= $progress ?>%"></div>
                    </div>
                </div>

                <!-- Status Breakdown -->
                <?php if (!empty($statusBreakdown)): ?>
                <div class="mt-4 flex flex-wrap gap-2">
                    <?php foreach ($statusBreakdown as $status): ?>
                    <span class="px-3 py-1 rounded-full text-xs font-medium" style="background-color: <?= $status['color'] ?>20; color: <?= $status['color'] ?>">
                        <?= sanitize($status['name']) ?>: <?= $status['count'] ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Expandable Details -->
            <details class="border-t border-gray-100">
                <summary class="px-6 py-3 bg-gray-50 cursor-pointer hover:bg-gray-100 font-medium text-sm text-gray-600 flex items-center gap-2">
                    <svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    View Call Details
                </summary>
                <div class="p-4">
                    <?php
                    $calls = db()->fetchAll("
                        SELECT cl.*, c.name as contact_name, c.phone, cs.name as status_name, cs.color
                        FROM call_logs cl
                        JOIN contacts c ON cl.contact_id = c.id
                        LEFT JOIN call_statuses cs ON cl.status_id = cs.id
                        WHERE cl.user_id = ? AND DATE(cl.call_time) BETWEEN ? AND ?
                        ORDER BY cl.call_time DESC
                    ", [$emp['id'], $filterDateFrom, $filterDateTo]);
                    ?>
                    <?php if (empty($calls)): ?>
                    <p class="text-center text-gray-500 py-4">No calls for this date range</p>
                    <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($calls as $call): ?>
                        <div class="flex items-center gap-3 py-2 px-3 bg-gray-50 rounded-lg">
                            <div class="flex-1">
                                <p class="font-medium text-sm"><?= sanitize($call['contact_name']) ?></p>
                                <p class="text-xs text-gray-500"><?= sanitize($call['phone']) ?></p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs font-medium" style="background-color: <?= $call['color'] ?>20; color: <?= $call['color'] ?>">
                                <?= sanitize($call['status_name']) ?>
                            </span>
                            <span class="text-xs text-gray-400"><?= formatTime($call['call_time']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </details>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function filterByDate() {
    const dateFrom = document.getElementById('reportDateFrom').value;
    const dateTo = document.getElementById('reportDateTo').value;
    if (dateFrom > dateTo) {
        alert('From date cannot be later than To date');
        return;
    }
    window.location.href = '?date_from=' + dateFrom + '&date_to=' + dateTo;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
