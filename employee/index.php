<?php
$pageTitle = 'Employee Dashboard - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAuth();

$userId = $_SESSION['user_id'];

// Check today's attendance
$todayAttendance = db()->fetch("SELECT * FROM attendance WHERE user_id = ? AND date = ?", [$userId, serverDate()]);

// Handle punch in/out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $clientTime = $_POST['client_time'] ?? serverTime();
    
    if ($action === 'punch_in' && !$todayAttendance) {
        db()->insert(
            "INSERT INTO attendance (user_id, punch_in, date) VALUES (?, ?, ?)",
            [$userId, $clientTime, serverDate()]
        );
        setFlash('success', 'Punched in at ' . formatTime($clientTime));
        header('Location: index.php');
        exit;
    } elseif ($action === 'punch_out' && $todayAttendance && !$todayAttendance['punch_out']) {
        db()->update(
            "UPDATE attendance SET punch_out = ? WHERE id = ?",
            [$clientTime, $todayAttendance['id']]
        );
        setFlash('success', 'Punched out at ' . formatTime($clientTime));
        header('Location: index.php');
        exit;
    }
}

// Refresh attendance data
$todayAttendance = db()->fetch("SELECT * FROM attendance WHERE user_id = ? AND date = ?", [$userId, serverDate()]);

// Get stats
$totalContacts = db()->fetch("SELECT COUNT(*) as count FROM contacts WHERE assigned_to = ?", [$userId])['count'];
$calledContacts = db()->fetch("SELECT COUNT(*) as count FROM contacts WHERE assigned_to = ? AND is_called = 1", [$userId])['count'];
$pendingContacts = $totalContacts - $calledContacts;
$todayCalls = db()->fetch("SELECT COUNT(*) as count FROM call_logs WHERE user_id = ? AND DATE(call_time) = ?", [$userId, serverDate()])['count'];

// Get pending contacts for preview
$pendingContactsList = db()->fetchAll("
    SELECT c.* FROM contacts c 
    WHERE c.assigned_to = ? AND c.is_called = 0
    ORDER BY c.created_at
    LIMIT 5
", [$userId]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-black">Hey, <?= sanitize(explode(' ', currentUser()['name'])[0]) ?>! 👋</h1>
            <p class="text-gray-500 mt-1"><?= formatDate(serverDate(), 'l, F j, Y') ?></p>
        </div>
        
        <!-- Punch In/Out Card -->
        <div class="bg-white rounded-2xl border border-gray-200 p-4 w-full sm:min-w-[200px] sm:w-auto">
            <?php if (!$todayAttendance): ?>
            <form method="POST" onsubmit="setClientTime(this)">
                <input type="hidden" name="action" value="punch_in">
                <input type="hidden" name="client_time" value="">
                <button type="submit" class="w-full flex items-center justify-center gap-3 px-5 py-3 bg-green-500 text-white rounded-xl font-semibold hover:bg-green-600 active:scale-[0.98] transition-all shadow-md shadow-green-500/30">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    Punch In
                </button>
            </form>
            <?php elseif (!$todayAttendance['punch_out']): ?>
            <div class="text-center mb-3">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Working since</p>
                <p class="text-2xl font-bold text-green-600"><?= formatTime($todayAttendance['punch_in']) ?></p>
            </div>
            <form method="POST" onsubmit="setClientTime(this)">
                <input type="hidden" name="action" value="punch_out">
                <input type="hidden" name="client_time" value="">
                <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-red-500 text-white rounded-xl font-medium hover:bg-red-600 active:scale-[0.98] transition-all text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Punch Out
                </button>
            </form>
            <?php else: ?>
            <div class="grid grid-cols-2 gap-3 text-center">
                <div>
                    <p class="text-xs text-gray-500">In</p>
                    <p class="text-lg font-bold text-green-600"><?= formatTime($todayAttendance['punch_in']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Out</p>
                    <p class="text-lg font-bold text-red-600"><?= formatTime($todayAttendance['punch_out']) ?></p>
                </div>
            </div>
            <p class="text-xs text-gray-400 text-center mt-2">Completed ✓</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-2xl p-5 border border-gray-200">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-black"><?= $totalContacts ?></p>
            <p class="text-sm text-gray-500">Total Assigned</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-gray-200">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-black"><?= $calledContacts ?></p>
            <p class="text-sm text-gray-500">Completed</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-gray-200">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-black"><?= $pendingContacts ?></p>
            <p class="text-sm text-gray-500">Pending</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-gray-200">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-black"><?= $todayCalls ?></p>
            <p class="text-sm text-gray-500">Today's Calls</p>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-8">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-lg">Your Progress</h3>
            <span class="text-2xl font-bold"><?= $totalContacts > 0 ? round(($calledContacts / $totalContacts) * 100) : 0 ?>%</span>
        </div>
        <div class="h-4 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-gradient-to-r from-gray-800 to-black rounded-full transition-all duration-500" style="width: <?= $totalContacts > 0 ? ($calledContacts / $totalContacts) * 100 : 0 ?>%"></div>
        </div>
        <div class="flex justify-between text-sm text-gray-500 mt-2">
            <span><?= $calledContacts ?> completed</span>
            <span><?= $pendingContacts ?> remaining</span>
        </div>
    </div>

    <!-- Main Action & Preview -->
    <div class="grid lg:grid-cols-2 gap-6">
        <!-- Start Calling CTA -->
        <?php if ($pendingContacts > 0): ?>
        <a href="calls.php" class="bg-gradient-to-br from-gray-900 to-black text-white rounded-2xl p-8 flex flex-col items-center justify-center text-center hover:from-gray-800 hover:to-gray-900 transition-all group shadow-xl shadow-black/20">
            <div class="w-20 h-20 rounded-full bg-white bg-opacity-10 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold mb-2">Start Calling</h2>
            <p class="text-white text-opacity-70"><?= $pendingContacts ?> contacts waiting</p>
        </a>
        <?php else: ?>
        <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-2xl p-8 flex flex-col items-center justify-center text-center shadow-xl shadow-green-500/20">
            <div class="w-20 h-20 rounded-full bg-white bg-opacity-20 flex items-center justify-center mb-5">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold mb-2">All Done! 🎉</h2>
            <p class="text-white text-opacity-80">You've completed all your calls</p>
        </div>
        <?php endif; ?>

        <!-- Next Up Preview -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-lg font-bold text-black">Next Up</h2>
                <?php if (!empty($pendingContactsList)): ?>
                <a href="calls.php" class="text-sm text-gray-500 hover:text-black font-medium">View All →</a>
                <?php endif; ?>
            </div>
            <?php if (empty($pendingContactsList)): ?>
            <div class="p-8 text-center text-gray-500">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <p class="font-medium">No pending contacts</p>
            </div>
            <?php else: ?>
            <div class="divide-y divide-gray-50">
                <?php foreach ($pendingContactsList as $contact): ?>
                <div class="flex items-center gap-4 p-4 hover:bg-gray-50 transition-colors">
                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center font-bold text-gray-600">
                        <?= strtoupper(substr($contact['name'], 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold truncate"><?= sanitize($contact['name']) ?></p>
                        <p class="text-sm text-gray-500 font-mono"><?= sanitize($contact['phone']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function setClientTime(form) {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const clientTime = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    form.querySelector('input[name="client_time"]').value = clientTime;
    return true;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>