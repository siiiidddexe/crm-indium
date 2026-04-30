<?php
$pageTitle = 'My Attendance - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAuth();

$userId = $_SESSION['user_id'];

// Get break duration setting (default 30 minutes)
$breakSetting = db()->fetch("SELECT setting_value FROM app_settings WHERE setting_key = 'break_duration'");
$breakDuration = $breakSetting ? (int) $breakSetting['setting_value'] : 30;

// Check today's attendance
$todayAttendance = db()->fetch("SELECT * FROM attendance WHERE user_id = ? AND date = ?", [$userId, serverDate()]);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $clientTime = $_POST['client_time'] ?? serverTime();

    if ($action === 'punch_in' && !$todayAttendance) {
        db()->insert(
            "INSERT INTO attendance (user_id, punch_in, date) VALUES (?, ?, ?)",
            [$userId, $clientTime, serverDate()]
        );
        setFlash('success', 'Punched in at ' . formatTime($clientTime));
        header('Location: attendance.php');
        exit;
    } elseif ($action === 'punch_out' && $todayAttendance && !$todayAttendance['punch_out']) {
        // If on break, end break first
        if ($todayAttendance['break_start'] && !$todayAttendance['break_end']) {
            db()->update(
                "UPDATE attendance SET break_end = ? WHERE id = ?",
                [$clientTime, $todayAttendance['id']]
            );
        }
        db()->update(
            "UPDATE attendance SET punch_out = ? WHERE id = ?",
            [$clientTime, $todayAttendance['id']]
        );
        setFlash('success', 'Punched out at ' . formatTime($clientTime));
        header('Location: attendance.php');
        exit;
    } elseif ($action === 'start_break' && $todayAttendance && !$todayAttendance['punch_out'] && !$todayAttendance['break_start']) {
        db()->update(
            "UPDATE attendance SET break_start = ? WHERE id = ?",
            [$clientTime, $todayAttendance['id']]
        );
        setFlash('success', 'Break started! Duration: ' . $breakDuration . ' minutes');
        header('Location: attendance.php');
        exit;
    } elseif ($action === 'end_break' && $todayAttendance && $todayAttendance['break_start'] && !$todayAttendance['break_end']) {
        db()->update(
            "UPDATE attendance SET break_end = ? WHERE id = ?",
            [$clientTime, $todayAttendance['id']]
        );
        setFlash('success', 'Break ended at ' . formatTime($clientTime));
        header('Location: attendance.php');
        exit;
    }
}

// Refresh attendance data
$todayAttendance = db()->fetch("SELECT * FROM attendance WHERE user_id = ? AND date = ?", [$userId, serverDate()]);

// Check if on break
$onBreak = $todayAttendance && $todayAttendance['break_start'] && !$todayAttendance['break_end'];

// Calculate break elapsed time (no limit)
$breakElapsed = 0;
if ($onBreak) {
    $breakStartTime = strtotime($todayAttendance['break_start']);
    $breakElapsed = time() - $breakStartTime;
}

// Get month data for calendar
$selectedMonth = $_GET['month'] ?? date('Y-m');
$startDate = $selectedMonth . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

$attendanceData = [];
$records = db()->fetchAll("
    SELECT * FROM attendance 
    WHERE user_id = ? AND date BETWEEN ? AND ?
    ORDER BY date DESC
", [$userId, $startDate, $endDate]);

foreach ($records as $record) {
    $attendanceData[$record['date']] = $record;
}

// Summary
$summary = db()->fetch("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN punch_out IS NOT NULL THEN 1 ELSE 0 END) as completed_days
    FROM attendance 
    WHERE user_id = ? AND date BETWEEN ? AND ?
", [$userId, $startDate, $endDate]);

// Calendar setup
$firstDay = date('N', strtotime($startDate));
$daysInMonth = date('t', strtotime($startDate));
$monthName = date('F Y', strtotime($startDate));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Today's Attendance Card -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
        <h2 class="text-lg font-bold mb-4">Today - <?= formatDate(serverDate(), 'l, M d') ?></h2>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <!-- Punch In -->
            <div class="text-center p-4 bg-gray-50 rounded-xl">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Punch In</p>
                <p
                    class="text-xl font-bold <?= $todayAttendance && $todayAttendance['punch_in'] ? 'text-green-600' : 'text-gray-300' ?>">
                    <?= $todayAttendance && $todayAttendance['punch_in'] ? formatTime($todayAttendance['punch_in']) : '--:--' ?>
                </p>
            </div>

            <!-- Break Start -->
            <div class="text-center p-4 bg-gray-50 rounded-xl">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Break Start</p>
                <p
                    class="text-xl font-bold <?= $todayAttendance && $todayAttendance['break_start'] ? 'text-amber-600' : 'text-gray-300' ?>">
                    <?= $todayAttendance && $todayAttendance['break_start'] ? formatTime($todayAttendance['break_start']) : '--:--' ?>
                </p>
            </div>

            <!-- Break End -->
            <div class="text-center p-4 bg-gray-50 rounded-xl">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Break End</p>
                <p
                    class="text-xl font-bold <?= $todayAttendance && $todayAttendance['break_end'] ? 'text-amber-600' : 'text-gray-300' ?>">
                    <?= $todayAttendance && $todayAttendance['break_end'] ? formatTime($todayAttendance['break_end']) : '--:--' ?>
                </p>
            </div>

            <!-- Punch Out -->
            <div class="text-center p-4 bg-gray-50 rounded-xl">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Punch Out</p>
                <p
                    class="text-xl font-bold <?= $todayAttendance && $todayAttendance['punch_out'] ? 'text-red-600' : 'text-gray-300' ?>">
                    <?= $todayAttendance && $todayAttendance['punch_out'] ? formatTime($todayAttendance['punch_out']) : '--:--' ?>
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-3">
            <?php if (!$todayAttendance): ?>
                <!-- Not punched in -->
                <form method="POST" class="flex-1 min-w-[150px]" onsubmit="setClientTime(this)">
                    <input type="hidden" name="action" value="punch_in">
                    <input type="hidden" name="client_time" value="">
                    <button type="submit"
                        class="w-full py-4 bg-green-500 text-white rounded-xl font-bold text-lg flex items-center justify-center gap-2 hover:bg-green-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        Punch In
                    </button>
                </form>

            <?php elseif (!$todayAttendance['punch_out']): ?>
                <!-- Punched in, not out yet -->

                <?php if (!$todayAttendance['break_start']): ?>
                    <!-- Can start break -->
                    <form method="POST" class="flex-1 min-w-[150px]" onsubmit="setClientTime(this)">
                        <input type="hidden" name="action" value="start_break">
                        <input type="hidden" name="client_time" value="">
                        <button type="submit"
                            class="w-full py-4 bg-amber-500 text-white rounded-xl font-bold text-lg flex items-center justify-center gap-2 hover:bg-amber-600 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Start Break (<?= $breakDuration ?>m)
                        </button>
                    </form>

                <?php elseif (!$todayAttendance['break_end']): ?>
                    <!-- On break - must end break before other actions -->
                    <div class="flex-1 min-w-[150px]">
                        <div class="w-full py-4 bg-amber-100 border-2 border-amber-500 rounded-xl text-center">
                            <p class="text-amber-700 font-bold text-lg">On Break</p>
                            <p class="text-amber-600 text-sm" id="breakTimer">
                                <?= floor($breakElapsed / 60) ?>:<?= str_pad($breakElapsed % 60, 2, '0', STR_PAD_LEFT) ?>
                                elapsed
                            </p>
                        </div>
                    </div>
                    <form method="POST" class="flex-1 min-w-[150px]" onsubmit="setClientTime(this)">
                        <input type="hidden" name="action" value="end_break">
                        <input type="hidden" name="client_time" value="">
                        <button type="submit"
                            class="w-full py-4 bg-amber-500 text-white rounded-xl font-bold text-lg flex items-center justify-center gap-2 hover:bg-amber-600 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            End Break
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (!$onBreak): ?>
                    <form method="POST" class="flex-1 min-w-[150px]" onsubmit="setClientTime(this)">
                        <input type="hidden" name="action" value="punch_out">
                        <input type="hidden" name="client_time" value="">
                        <button type="submit"
                            class="w-full py-4 bg-red-500 text-white rounded-xl font-bold text-lg flex items-center justify-center gap-2 hover:bg-red-600 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Punch Out
                        </button>
                    </form>
                <?php endif; ?>

            <?php else: ?>
                <!-- Day completed -->
                <div class="flex-1 min-w-[150px]">
                    <div class="w-full py-4 bg-green-100 border-2 border-green-500 rounded-xl text-center">
                        <p class="text-green-700 font-bold text-lg flex items-center justify-center gap-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Day Completed
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Calendar & Summary -->
    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Calendar -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <a href="?month=<?= date('Y-m', strtotime($selectedMonth . ' -1 month')) ?>"
                    class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h2 class="text-lg font-bold"><?= $monthName ?></h2>
                <a href="?month=<?= date('Y-m', strtotime($selectedMonth . ' +1 month')) ?>"
                    class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            <div class="p-4">
                <!-- Day Headers -->
                <div class="grid grid-cols-7 gap-1 mb-2">
                    <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day): ?>
                        <div class="text-center text-xs font-semibold text-gray-500 py-2"><?= $day ?></div>
                    <?php endforeach; ?>
                </div>

                <!-- Days -->
                <div class="grid grid-cols-7 gap-1">
                    <?php for ($i = 1; $i < $firstDay; $i++): ?>
                        <div class="aspect-square"></div>
                    <?php endfor; ?>

                    <?php for ($day = 1; $day <= $daysInMonth; $day++):
                        $currentDate = $selectedMonth . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                        $att = $attendanceData[$currentDate] ?? null;
                        $isToday = $currentDate === serverDate();
                        $isWeekend = in_array(date('N', strtotime($currentDate)), [6, 7]);
                        $hadBreak = $att && $att['break_start'];
                        ?>
                        <div class="aspect-square p-0.5">
                            <div class="h-full rounded-lg flex flex-col items-center justify-center text-xs relative
                            <?php if ($isToday): ?>ring-2 ring-black<?php endif; ?>
                            <?php if ($att): ?>
                                <?php if ($att['punch_out']): ?>bg-green-100 text-green-700
                                <?php else: ?>bg-yellow-100 text-yellow-700<?php endif; ?>
                            <?php elseif ($isWeekend): ?>bg-gray-50 text-gray-400
                            <?php else: ?>bg-white text-gray-600<?php endif; ?>
                        ">
                                <span class="font-medium"><?= $day ?></span>
                                <?php if ($att && $att['punch_in']): ?>
                                    <span class="text-[10px] mt-0.5"><?= date('H:i', strtotime($att['punch_in'])) ?></span>
                                <?php endif; ?>
                                <?php if ($hadBreak): ?>
                                    <span class="absolute top-1 right-1 w-2 h-2 bg-amber-400 rounded-full"
                                        title="Had break"></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Legend -->
            <div class="p-4 border-t border-gray-100 flex flex-wrap gap-4 justify-center text-xs">
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-green-100 border border-green-200"></div>
                    <span class="text-gray-600">Completed</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-yellow-100 border border-yellow-200"></div>
                    <span class="text-gray-600">Active</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-amber-400"></div>
                    <span class="text-gray-600">Had Break</span>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-bold text-lg mb-4">This Month</h3>

            <div class="space-y-4">
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-3xl font-bold text-black"><?= $summary['total_days'] ?? 0 ?></p>
                    <p class="text-sm text-gray-500">Days Present</p>
                </div>

                <div class="p-4 bg-green-50 rounded-xl">
                    <p class="text-3xl font-bold text-green-600"><?= $summary['completed_days'] ?? 0 ?></p>
                    <p class="text-sm text-green-600">Full Days</p>
                </div>

                <div class="p-4 bg-amber-50 rounded-xl">
                    <p class="text-3xl font-bold text-amber-600"><?= $breakDuration ?></p>
                    <p class="text-sm text-amber-600">Min Break Allowed</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($onBreak): ?>
    <script>
        // Break elapsed timer - counts up
        let breakSeconds = <?= $breakElapsed ?>;
        const timerEl = document.getElementById('breakTimer');

        setInterval(() => {
            breakSeconds++;
            const mins = Math.floor(breakSeconds / 60);
            const secs = breakSeconds % 60;
            timerEl.textContent = mins + ':' + String(secs).padStart(2, '0') + ' elapsed';
        }, 1000);
    </script>
<?php endif; ?>

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