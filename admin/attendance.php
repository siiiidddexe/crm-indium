<?php
$pageTitle = 'Attendance Management - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Get break duration setting
$breakSetting = db()->fetch("SELECT setting_value FROM app_settings WHERE setting_key = 'break_duration'");
$breakDuration = $breakSetting ? (int)$breakSetting['setting_value'] : 30;

// Get WhatsApp message setting
$whatsappSetting = db()->fetch("SELECT setting_value FROM app_settings WHERE setting_key = 'whatsapp_message'");
$whatsappMessage = $whatsappSetting ? $whatsappSetting['setting_value'] : 'Hello {name}, this is a message from our team.';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        $newDuration = (int)($_POST['break_duration'] ?? 30);
        $newWhatsappMsg = trim($_POST['whatsapp_message'] ?? '');
        
        if ($newDuration >= 5 && $newDuration <= 120) {
            $existing = db()->fetch("SELECT id FROM app_settings WHERE setting_key = 'break_duration'");
            if ($existing) {
                db()->update("UPDATE app_settings SET setting_value = ?, updated_at = ? WHERE setting_key = 'break_duration'", [$newDuration, serverTime()]);
            } else {
                db()->insert("INSERT INTO app_settings (setting_key, setting_value) VALUES ('break_duration', ?)", [$newDuration]);
            }
        }
        
        // Save WhatsApp message
        if ($newWhatsappMsg) {
            $existingWa = db()->fetch("SELECT id FROM app_settings WHERE setting_key = 'whatsapp_message'");
            if ($existingWa) {
                db()->update("UPDATE app_settings SET setting_value = ?, updated_at = ? WHERE setting_key = 'whatsapp_message'", [$newWhatsappMsg, serverTime()]);
            } else {
                db()->insert("INSERT INTO app_settings (setting_key, setting_value) VALUES ('whatsapp_message', ?)", [$newWhatsappMsg]);
            }
        }
        
        setFlash('success', 'Settings updated!');
        header('Location: attendance.php');
        exit;
    }
    elseif ($action === 'edit_attendance') {
        $id = (int)($_POST['id'] ?? 0);
        $punch_in = $_POST['punch_in'] ?? '';
        $punch_out = $_POST['punch_out'] ?: null;
        $break_start = $_POST['break_start'] ?: null;
        $break_end = $_POST['break_end'] ?: null;
        $notes = sanitize($_POST['notes'] ?? '');
        
        if ($id && $punch_in) {
            db()->update(
                "UPDATE attendance SET punch_in = ?, punch_out = ?, break_start = ?, break_end = ?, notes = ? WHERE id = ?",
                [$punch_in, $punch_out, $break_start, $break_end, $notes, $id]
            );
            setFlash('success', 'Attendance record updated');
            header('Location: attendance.php?' . http_build_query($_GET));
            exit;
        }
    }
    elseif ($action === 'delete_attendance') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            db()->delete("DELETE FROM attendance WHERE id = ?", [$id]);
            setFlash('success', 'Attendance record deleted');
            header('Location: attendance.php?' . http_build_query($_GET));
            exit;
        }
    }
    elseif ($action === 'add_attendance') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $date = $_POST['date'] ?? '';
        $punch_in = $_POST['punch_in'] ?? '';
        $punch_out = $_POST['punch_out'] ?: null;
        
        if ($userId && $date && $punch_in) {
            // Check if record exists
            $existing = db()->fetch("SELECT id FROM attendance WHERE user_id = ? AND date = ?", [$userId, $date]);
            if (!$existing) {
                db()->insert(
                    "INSERT INTO attendance (user_id, date, punch_in, punch_out) VALUES (?, ?, ?, ?)",
                    [$userId, $date, $punch_in, $punch_out]
                );
                setFlash('success', 'Attendance record added');
            } else {
                setFlash('error', 'Record already exists for this date');
            }
            header('Location: attendance.php');
            exit;
        }
    }
}

// Filters
$selectedEmployee = $_GET['employee'] ?? '';
$selectedMonth = $_GET['month'] ?? date('Y-m');
$startDate = $selectedMonth . '-01';
$endDate = date('Y-m-t', strtotime($startDate));
$monthName = date('F Y', strtotime($startDate));

// Get employees
$employees = db()->fetchAll("SELECT id, name, role FROM users WHERE role != 'admin' AND is_active = 1 ORDER BY name");

// Get attendance records
if ($selectedEmployee) {
    $records = db()->fetchAll("
        SELECT a.*, u.name as user_name, u.role as user_role
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE a.user_id = ? AND a.date BETWEEN ? AND ?
        ORDER BY a.date DESC
    ", [$selectedEmployee, $startDate, $endDate]);
    
    // Calendar data
    $attendanceData = [];
    foreach ($records as $record) {
        $attendanceData[$record['date']] = $record;
    }
} else {
    $records = db()->fetchAll("
        SELECT a.*, u.name as user_name, u.role as user_role
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE a.date BETWEEN ? AND ?
        ORDER BY a.date DESC, u.name
    ", [$startDate, $endDate]);
}

// Today's attendance
$todayRecords = db()->fetchAll("
    SELECT a.*, u.name as user_name, u.role as user_role
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.date = ?
    ORDER BY a.punch_in
", [serverDate()]);

// Calendar setup
$firstDay = date('N', strtotime($startDate));
$daysInMonth = date('t', strtotime($startDate));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-black">Attendance</h1>
            <p class="text-gray-500 mt-1"><?= $monthName ?></p>
        </div>
        <div class="flex gap-3">
            <button onclick="showModal('settingsModal')" class="px-4 py-2.5 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Settings
            </button>
            <button onclick="showModal('addModal')" class="px-4 py-2.5 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Add Record
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-6">
        <form method="GET" class="flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-0 sm:min-w-[200px]">
                <select name="employee" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl" onchange="this.form.submit()">
                    <option value="">All Employees</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= $selectedEmployee == $emp['id'] ? 'selected' : '' ?>><?= sanitize($emp['name']) ?> (<?= ucfirst($emp['role']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <a href="?employee=<?= $selectedEmployee ?>&month=<?= date('Y-m', strtotime($selectedMonth . ' -1 month')) ?>" class="p-2.5 border border-gray-300 rounded-xl hover:bg-gray-50">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <input type="month" name="month" value="<?= $selectedMonth ?>" class="px-4 py-2.5 border border-gray-300 rounded-xl" onchange="this.form.submit()">
                <a href="?employee=<?= $selectedEmployee ?>&month=<?= date('Y-m', strtotime($selectedMonth . ' +1 month')) ?>" class="p-2.5 border border-gray-300 rounded-xl hover:bg-gray-50">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </form>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Calendar (if employee selected) -->
        <?php if ($selectedEmployee): ?>
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-100">
                <h2 class="font-bold">Calendar View</h2>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-7 gap-1 mb-2">
                    <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day): ?>
                    <div class="text-center text-xs font-semibold text-gray-500 py-2"><?= $day ?></div>
                    <?php endforeach; ?>
                </div>
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
                        <div class="h-full rounded-lg flex flex-col items-center justify-center text-xs cursor-pointer hover:ring-2 hover:ring-gray-300 relative
                            <?php if ($isToday): ?>ring-2 ring-black<?php endif; ?>
                            <?php if ($att): ?>
                                <?php if ($att['punch_out']): ?>bg-green-100 text-green-700
                                <?php else: ?>bg-yellow-100 text-yellow-700<?php endif; ?>
                            <?php elseif ($isWeekend): ?>bg-gray-50 text-gray-400
                            <?php else: ?>bg-white text-gray-600<?php endif; ?>
                        " <?php if ($att): ?>onclick='openEditModal(<?= json_encode($att) ?>)'<?php endif; ?>>
                            <span class="font-medium"><?= $day ?></span>
                            <?php if ($att): ?>
                            <span class="text-[10px]"><?= date('H:i', strtotime($att['punch_in'])) ?></span>
                            <?php endif; ?>
                            <?php if ($hadBreak): ?>
                            <span class="absolute top-1 right-1 w-2 h-2 bg-amber-400 rounded-full"></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Records List -->
        <div class="<?= $selectedEmployee ? '' : 'lg:col-span-3' ?> bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-bold">Attendance Records</h2>
                <span class="text-sm text-gray-500"><?= count($records) ?> records</span>
            </div>
            <div class="divide-y divide-gray-50 max-h-[600px] overflow-y-auto">
                <?php if (empty($records)): ?>
                <div class="p-8 text-center text-gray-500">No records found</div>
                <?php else: ?>
                <?php foreach ($records as $record): ?>
                <div class="p-4 hover:bg-gray-50 cursor-pointer" onclick='openEditModal(<?= json_encode($record) ?>)'>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center font-bold text-gray-600 text-sm">
                            <?= strtoupper(substr($record['user_name'], 0, 1)) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-sm"><?= sanitize($record['user_name']) ?></p>
                            <p class="text-xs text-gray-500"><?= formatDate($record['date'], 'D, M d') ?></p>
                        </div>
                        <div class="text-right text-sm">
                            <div class="flex items-center gap-2 text-green-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14"/></svg>
                                <?= formatTime($record['punch_in']) ?>
                            </div>
                            <?php if ($record['punch_out']): ?>
                            <div class="flex items-center gap-2 text-red-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"/></svg>
                                <?= formatTime($record['punch_out']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($record['break_start']): ?>
                        <div class="w-2 h-2 bg-amber-400 rounded-full" title="Had break"></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Today's Summary -->
    <div class="mt-6 bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold">Today's Attendance</h2>
            <span class="px-3 py-1 bg-gray-100 rounded-full text-sm"><?= count($todayRecords) ?> checked in</span>
        </div>
        <div class="p-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($todayRecords as $rec): ?>
            <div class="p-4 bg-gray-50 rounded-xl flex items-center gap-3">
                <div class="w-10 h-10 rounded-full <?= $rec['punch_out'] ? 'bg-green-500' : ($rec['break_start'] && !$rec['break_end'] ? 'bg-amber-500' : 'bg-blue-500') ?> text-white flex items-center justify-center font-bold">
                    <?= strtoupper(substr($rec['user_name'], 0, 1)) ?>
                </div>
                <div>
                    <p class="font-medium text-sm"><?= sanitize($rec['user_name']) ?></p>
                    <p class="text-xs text-gray-500">
                        <?php if ($rec['punch_out']): ?>
                        Out at <?= formatTime($rec['punch_out']) ?>
                        <?php elseif ($rec['break_start'] && !$rec['break_end']): ?>
                        On break
                        <?php else: ?>
                        In at <?= formatTime($rec['punch_in']) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($todayRecords)): ?>
            <div class="col-span-full text-center text-gray-500 py-4">No one has checked in yet</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Settings Modal -->
<div id="settingsModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold">Settings</h2>
        </div>
        <form method="POST" class="p-6 space-y-5">
            <input type="hidden" name="action" value="update_settings">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Break Duration (minutes)</label>
                <input type="number" name="break_duration" value="<?= $breakDuration ?>" min="5" max="120" class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                <p class="text-xs text-gray-500 mt-1">Employees will see this as their allowed break time</p>
            </div>
            
            <div class="border-t border-gray-200 pt-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp Message Template</label>
                <textarea name="whatsapp_message" rows="4" class="w-full px-4 py-3 border border-gray-300 rounded-xl" placeholder="Hello {name}, this is a message from our team."><?= sanitize($whatsappMessage) ?></textarea>
                <p class="text-xs text-gray-500 mt-1">Use <code class="bg-gray-100 px-1 rounded">{name}</code> to insert the contact's name</p>
            </div>
            
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="hideModal('settingsModal')" class="flex-1 px-4 py-3 border border-gray-300 rounded-xl font-medium hover:bg-gray-50">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Record Modal -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold">Add Attendance Record</h2>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add_attendance">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Employee *</label>
                <select name="user_id" required class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= sanitize($emp['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date *</label>
                <input type="date" name="date" required value="<?= serverDate() ?>" class="w-full px-4 py-3 border border-gray-300 rounded-xl">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Punch In *</label>
                    <input type="time" name="punch_in" required class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Punch Out</label>
                    <input type="time" name="punch_out" class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="hideModal('addModal')" class="flex-1 px-4 py-3 border border-gray-300 rounded-xl font-medium hover:bg-gray-50">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800">Add</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Record Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-xl font-bold">Edit Attendance</h2>
            <span id="editEmployeeName" class="text-sm text-gray-500"></span>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit_attendance">
            <input type="hidden" name="id" id="editId">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                <input type="text" id="editDate" disabled class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Punch In *</label>
                    <input type="datetime-local" name="punch_in" id="editPunchIn" required class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Punch Out</label>
                    <input type="datetime-local" name="punch_out" id="editPunchOut" class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Break Start</label>
                    <input type="datetime-local" name="break_start" id="editBreakStart" class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Break End</label>
                    <input type="datetime-local" name="break_end" id="editBreakEnd" class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                <textarea name="notes" id="editNotes" rows="2" class="w-full px-4 py-3 border border-gray-300 rounded-xl"></textarea>
            </div>
            
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="hideModal('editModal')" class="flex-1 px-4 py-3 border border-gray-300 rounded-xl font-medium hover:bg-gray-50">Cancel</button>
                <button type="button" onclick="deleteAttendance()" class="px-4 py-3 bg-red-500 text-white rounded-xl font-medium hover:bg-red-600">Delete</button>
                <button type="submit" class="flex-1 px-4 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="delete_attendance">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function openEditModal(record) {
    document.getElementById('editId').value = record.id;
    document.getElementById('editEmployeeName').textContent = record.user_name || '';
    document.getElementById('editDate').value = record.date;
    
    // Format datetime-local values
    document.getElementById('editPunchIn').value = record.punch_in ? record.punch_in.replace(' ', 'T').slice(0, 16) : '';
    document.getElementById('editPunchOut').value = record.punch_out ? record.punch_out.replace(' ', 'T').slice(0, 16) : '';
    document.getElementById('editBreakStart').value = record.break_start ? record.break_start.replace(' ', 'T').slice(0, 16) : '';
    document.getElementById('editBreakEnd').value = record.break_end ? record.break_end.replace(' ', 'T').slice(0, 16) : '';
    document.getElementById('editNotes').value = record.notes || '';
    
    showModal('editModal');
}

function deleteAttendance() {
    if (confirm('Delete this attendance record?')) {
        document.getElementById('deleteId').value = document.getElementById('editId').value;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>