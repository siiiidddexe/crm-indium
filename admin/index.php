<?php
$pageTitle = 'Admin Dashboard - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Get stats
$totalStaff = db()->fetch("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND is_active = 1")['count'];
$totalContacts = db()->fetch("SELECT COUNT(*) as count FROM contacts")['count'];
$totalCalled = db()->fetch("SELECT COUNT(*) as count FROM contacts WHERE is_called = 1")['count'];
$totalPending = $totalContacts - $totalCalled;
$todayCalls = db()->fetch("SELECT COUNT(*) as count FROM call_logs WHERE DATE(call_time) = ?", [serverDate()])['count'];

// Recent call logs
$recentCalls = db()->fetchAll("
    SELECT cl.*, c.name as contact_name, c.phone, u.name as user_name, cs.name as status_name, cs.color
    FROM call_logs cl
    JOIN contacts c ON cl.contact_id = c.id
    JOIN users u ON cl.user_id = u.id
    LEFT JOIN call_statuses cs ON cl.status_id = cs.id
    ORDER BY cl.call_time DESC
    LIMIT 10
");

// Top performers today
$topPerformers = db()->fetchAll("
    SELECT u.id, u.name, COUNT(cl.id) as call_count
    FROM users u
    LEFT JOIN call_logs cl ON u.id = cl.user_id AND DATE(cl.call_time) = ?
    WHERE u.role != 'admin' AND u.is_active = 1
    GROUP BY u.id
    ORDER BY call_count DESC
    LIMIT 5
", [serverDate()]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-black">Dashboard</h1>
        <p class="text-gray-500 mt-1"><?= formatDate(serverDate(), 'l, F j, Y') ?></p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-2xl p-5 border border-gray-200 card-hover">
            <div class="flex items-center gap-3 mb-3">
                <div
                    class="w-12 h-12 rounded-xl bg-gradient-to-br from-gray-800 to-black flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-black"><?= $totalStaff ?></p>
            <p class="text-sm text-gray-500 mt-1">Total Staff</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-gray-200 card-hover">
            <div class="flex items-center gap-3 mb-3">
                <div
                    class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-black"><?= number_format($totalContacts) ?></p>
            <p class="text-sm text-gray-500 mt-1">Total Contacts</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-gray-200 card-hover">
            <div class="flex items-center gap-3 mb-3">
                <div
                    class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-black"><?= number_format($totalCalled) ?></p>
            <p class="text-sm text-gray-500 mt-1">Calls Made</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-gray-200 card-hover">
            <div class="flex items-center gap-3 mb-3">
                <div
                    class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-black"><?= number_format($totalPending) ?></p>
            <p class="text-sm text-gray-500 mt-1">Pending</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid sm:grid-cols-3 gap-4 mb-8">
        <a href="import.php"
            class="bg-black text-white rounded-2xl p-5 flex items-center gap-4 hover:bg-gray-800 transition-colors group">
            <div
                class="w-12 h-12 rounded-xl bg-white bg-opacity-20 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
            </div>
            <div>
                <p class="font-bold">Import Contacts</p>
                <p class="text-sm text-white text-opacity-70">Upload CSV file</p>
            </div>
        </a>

        <a href="employees.php"
            class="bg-white border border-gray-200 rounded-2xl p-5 flex items-center gap-4 hover:border-gray-300 hover:shadow-md transition-all group">
            <div
                class="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center group-hover:bg-gray-200 transition-colors">
                <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
            </div>
            <div>
                <p class="font-bold text-black">Add Staff</p>
                <p class="text-sm text-gray-500">Manage employees</p>
            </div>
        </a>

        <a href="reports.php"
            class="bg-white border border-gray-200 rounded-2xl p-5 flex items-center gap-4 hover:border-gray-300 hover:shadow-md transition-all group">
            <div
                class="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center group-hover:bg-gray-200 transition-colors">
                <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </div>
            <div>
                <p class="font-bold text-black">View Reports</p>
                <p class="text-sm text-gray-500">Employee performance</p>
            </div>
        </a>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <!-- Today's Activity -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-lg font-bold text-black">Today's Activity</h2>
                <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium"><?= $todayCalls ?>
                    calls</span>
            </div>
            <?php if (empty($recentCalls)): ?>
                <div class="p-8 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                    <p>No calls made yet today</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-50 max-h-96 overflow-y-auto">
                    <?php foreach ($recentCalls as $call): ?>
                        <div class="p-4 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center font-bold text-gray-600 text-sm">
                                    <?= strtoupper(substr($call['contact_name'], 0, 1)) ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-sm truncate"><?= sanitize($call['contact_name']) ?></p>
                                    <p class="text-xs text-gray-500">by <?= sanitize($call['user_name']) ?></p>
                                </div>
                                <div class="text-right">
                                    <?php if ($call['status_name']): ?>
                                        <span class="px-2 py-1 rounded-lg text-xs font-medium"
                                            style="background-color: <?= $call['color'] ?>20; color: <?= $call['color'] ?>"><?= sanitize($call['status_name']) ?></span>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-400 mt-1"><?= formatTime($call['call_time']) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top Performers -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="p-5 border-b border-gray-100">
                <h2 class="text-lg font-bold text-black">Today's Leaderboard</h2>
            </div>
            <div class="p-4 space-y-3">
                <?php foreach ($topPerformers as $index => $performer): ?>
                    <div
                        class="flex items-center gap-3 p-3 rounded-xl <?= $index === 0 && $performer['call_count'] > 0 ? 'bg-gradient-to-r from-yellow-50 to-amber-50 border border-yellow-200' : 'bg-gray-50' ?>">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm
                        <?php if ($index === 0 && $performer['call_count'] > 0): ?>bg-yellow-500 text-white
                        <?php elseif ($index === 1 && $performer['call_count'] > 0): ?>bg-gray-400 text-white
                        <?php elseif ($index === 2 && $performer['call_count'] > 0): ?>bg-amber-600 text-white
                        <?php else: ?>bg-gray-200 text-gray-600<?php endif; ?>">
                            <?= $index + 1 ?>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-sm"><?= sanitize($performer['name']) ?></p>
                        </div>
                        <div class="text-right">
                            <span
                                class="text-lg font-bold <?= $performer['call_count'] > 0 ? 'text-black' : 'text-gray-400' ?>"><?= $performer['call_count'] ?></span>
                            <span class="text-xs text-gray-500 ml-1">calls</span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($topPerformers)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>No staff members yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>