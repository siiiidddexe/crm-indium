<?php
$pageTitle = 'Import & Manage Contacts - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$error = '';
$success = '';

// Handle CSV template download
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="contacts_template.csv"');
    echo "Name,Phone\n";
    echo "John Doe,9876543210\n";
    echo "Jane Smith,9123456789\n";
    exit;
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $file = $_FILES['csv_file'] ?? null;
        $importDate = $_POST['import_date'] ?? serverDate();

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($ext === 'csv') {
                $data = parseCSV($file['tmp_name']);
                $count = 0;
                $dupeCount = 0;

                // Collect all phone numbers from CSV and deduplicate within the file
                $seenPhones = [];
                $uniqueData = [];
                foreach ($data as $row) {
                    if (!empty($row['name']) && !empty($row['phone'])) {
                        $phone = preg_replace('/[^0-9]/', '', $row['phone']);
                        if (isset($seenPhones[$phone])) {
                            $dupeCount++;
                            continue;
                        }
                        $seenPhones[$phone] = true;
                        $uniqueData[] = $row;
                    }
                }

                // Check existing phone numbers in database in batches
                $existingPhones = [];
                $phonesToCheck = array_keys($seenPhones);
                $batchSize = 500;
                for ($i = 0; $i < count($phonesToCheck); $i += $batchSize) {
                    $batch = array_slice($phonesToCheck, $i, $batchSize);
                    $placeholders = implode(',', array_fill(0, count($batch), '?'));
                    $existing = db()->fetchAll(
                        "SELECT phone FROM contacts WHERE REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '+', '') IN ($placeholders)",
                        $batch
                    );
                    foreach ($existing as $row) {
                        $cleanPhone = preg_replace('/[^0-9]/', '', $row['phone']);
                        $existingPhones[$cleanPhone] = true;
                    }
                }

                // Insert only non-duplicate contacts
                foreach ($uniqueData as $row) {
                    $phone = preg_replace('/[^0-9]/', '', $row['phone']);
                    if (isset($existingPhones[$phone])) {
                        $dupeCount++;
                        continue;
                    }
                    db()->insert(
                        "INSERT INTO contacts (name, phone, import_date, created_at) VALUES (?, ?, ?, ?)",
                        [$row['name'], $row['phone'], $importDate, serverTime()]
                    );
                    $count++;
                }

                db()->insert(
                    "INSERT INTO import_batches (filename, total_records, imported_by, import_date) VALUES (?, ?, ?, ?)",
                    [$file['name'], $count, $_SESSION['user_id'], $importDate]
                );

                $msg = "$count contacts imported for " . formatDate($importDate) . "!";
                if ($dupeCount > 0) {
                    $msg .= " ($dupeCount duplicates skipped)";
                }
                setFlash('success', $msg);
            } else {
                setFlash('error', 'Please upload a CSV file.');
            }
        }
        header('Location: import.php?date=' . $importDate);
        exit;
    } elseif ($action === 'bulk_assign') {
        $contactIds = $_POST['contact_ids'] ?? [];
        $assignTo = $_POST['assign_to'] ?? '';

        if (!empty($contactIds) && $assignTo) {
            $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
            $params = array_merge([$assignTo], $contactIds);
            db()->update("UPDATE contacts SET assigned_to = ? WHERE id IN ($placeholders)", $params);
            setFlash('success', count($contactIds) . ' contacts assigned!');
        }
        // Redirect without status filter since contacts changed assignment
        $redirectParams = [];
        if (isset($_POST['filter_date']) && $_POST['filter_date']) $redirectParams[] = 'date=' . $_POST['filter_date'];
        header('Location: import.php' . (!empty($redirectParams) ? '?' . implode('&', $redirectParams) : ''));
        exit;
    } elseif ($action === 'auto_assign') {
        // Randomly distribute all unassigned contacts to active employees (round-robin shuffle)
        $autoDate = $_POST['filter_date'] ?? '';
        $whereAuto = "assigned_to IS NULL";
        $autoParams = [];
        if ($autoDate) {
            $whereAuto .= " AND import_date = ?";
            $autoParams[] = $autoDate;
        }
        $unassigned = db()->fetchAll("SELECT id FROM contacts WHERE $whereAuto ORDER BY id", $autoParams);
        $employees  = db()->fetchAll("SELECT id FROM users WHERE role IN ('employee','teamlead') AND is_active = 1 ORDER BY id");

        if (!empty($unassigned) && !empty($employees)) {
            $empIds = array_column($employees, 'id');
            shuffle($empIds); // randomise order
            $total  = count($unassigned);
            $empCnt = count($empIds);
            $assigned = 0;
            foreach ($unassigned as $i => $row) {
                $empId = $empIds[$i % $empCnt];
                db()->update("UPDATE contacts SET assigned_to = ? WHERE id = ?", [$empId, $row['id']]);
                $assigned++;
            }
            setFlash('success', "$assigned contacts auto-assigned across $empCnt staff member(s).");
        } else {
            setFlash('error', empty($employees) ? 'No active staff to assign to.' : 'No unassigned contacts found.');
        }
        $redirectParams = [];
        if ($autoDate) $redirectParams[] = 'date=' . $autoDate;
        header('Location: import.php' . (!empty($redirectParams) ? '?' . implode('&', $redirectParams) : ''));
        exit;
    } elseif ($action === 'bulk_delete') {
        $contactIds = $_POST['contact_ids'] ?? [];

        if (!empty($contactIds)) {
            $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
            db()->delete("DELETE FROM contacts WHERE id IN ($placeholders)", $contactIds);
            setFlash('success', count($contactIds) . ' contacts deleted!');
        }
        // Redirect preserving date filter
        $redirectParams = [];
        if (isset($_POST['filter_date']) && $_POST['filter_date']) $redirectParams[] = 'date=' . $_POST['filter_date'];
        header('Location: import.php' . (!empty($redirectParams) ? '?' . implode('&', $redirectParams) : ''));
        exit;
    } elseif ($action === 'bulk_unassign') {
        $contactIds = $_POST['contact_ids'] ?? [];

        if (!empty($contactIds)) {
            $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
            db()->update("UPDATE contacts SET assigned_to = NULL WHERE id IN ($placeholders)", $contactIds);
            setFlash('success', count($contactIds) . ' contacts unassigned!');
        }
        // Redirect without status filter since contacts changed assignment
        $redirectParams = [];
        if (isset($_POST['filter_date']) && $_POST['filter_date']) $redirectParams[] = 'date=' . $_POST['filter_date'];
        header('Location: import.php' . (!empty($redirectParams) ? '?' . implode('&', $redirectParams) : ''));
        exit;
    } elseif ($action === 'bulk_move_date') {
        $contactIds = $_POST['contact_ids'] ?? [];
        $newDate = $_POST['new_date'] ?? '';

        if (!empty($contactIds) && $newDate) {
            $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
            $params = array_merge([$newDate], $contactIds);
            db()->update("UPDATE contacts SET import_date = ? WHERE id IN ($placeholders)", $params);
            setFlash('success', count($contactIds) . ' contacts moved to ' . formatDate($newDate) . '!');
        }
        header('Location: import.php?date=' . $newDate);
        exit;
    } elseif ($action === 'add_single') {
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $importDate = $_POST['import_date'] ?? serverDate();
        $assignTo = $_POST['assign_to'] ?? null;

        if ($name && $phone) {
            db()->insert(
                "INSERT INTO contacts (name, phone, assigned_to, import_date, created_at) VALUES (?, ?, ?, ?, ?)",
                [$name, $phone, $assignTo ?: null, $importDate, serverTime()]
            );
            setFlash('success', 'Contact added!');
        }
        header('Location: import.php?date=' . $importDate);
        exit;
    } elseif ($action === 'edit_contact') {
        $id = $_POST['id'] ?? 0;
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $assignTo = $_POST['assign_to'] ?? null;

        if ($id && $name && $phone) {
            db()->update(
                "UPDATE contacts SET name = ?, phone = ?, assigned_to = ? WHERE id = ?",
                [$name, $phone, $assignTo ?: null, $id]
            );
            setFlash('success', 'Contact updated!');
        }
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    } elseif ($action === 'delete_single') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            db()->delete("DELETE FROM contacts WHERE id = ?", [$id]);
            setFlash('success', 'Contact deleted!');
        }
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
}

// Get filter parameters
$filterDate = $_GET['date'] ?? '';
$filterStatus = $_GET['status'] ?? 'all';
$filterCallStatus = $_GET['call_status'] ?? '';
$filterAssignedTo = $_GET['assigned_to'] ?? '';

// Get call statuses for filter
$callStatuses = getCallStatuses();

// Get available import dates
$importDates = db()->fetchAll("
    SELECT DISTINCT import_date, COUNT(*) as count 
    FROM contacts 
    WHERE import_date IS NOT NULL 
    GROUP BY import_date 
    ORDER BY import_date DESC
");

// Build query for contacts
$where = [];
$params = [];

if ($filterDate) {
    $where[] = "c.import_date = ?";
    $params[] = $filterDate;
}

if ($filterStatus === 'unassigned') {
    $where[] = "c.assigned_to IS NULL";
} elseif ($filterStatus === 'assigned') {
    $where[] = "c.assigned_to IS NOT NULL";
}

if ($filterAssignedTo) {
    $where[] = "c.assigned_to = ?";
    $params[] = $filterAssignedTo;
}

if ($filterCallStatus) {
    if ($filterCallStatus === 'none') {
        $where[] = "c.status_id IS NULL";
    } else {
        $where[] = "c.status_id = ?";
        $params[] = $filterCallStatus;
    }
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Load initial batch of contacts server-side (fallback if AJAX fails)
$initialLimit = 200;
$contacts = db()->fetchAll("
    SELECT c.*, u.name as assigned_name, u.role as assigned_role, cs.name as status_name, cs.color
    FROM contacts c
    LEFT JOIN users u ON c.assigned_to = u.id
    LEFT JOIN call_statuses cs ON c.status_id = cs.id
    $whereClause
    ORDER BY c.id DESC
    LIMIT $initialLimit
", $params);

// Get the last ID for cursor-based pagination continuation
$initialCursor = !empty($contacts) ? end($contacts)['id'] : null;
$initialHasMore = count($contacts) >= $initialLimit;

// Get team leads and employees
$teamleads = getTeamLeads();
$employees = getEmployees();
$allStaff = array_merge($teamleads, $employees);

// Stats for current view (calculated from database)
$statsQuery = db()->fetch("
    SELECT
        COUNT(*) as total,
        COUNT(assigned_to) as assigned,
        COUNT(*) - COUNT(assigned_to) as unassigned
    FROM contacts c
    $whereClause
", $params);

$totalContacts = intval($statsQuery['total']);
$assignedCount = intval($statsQuery['assigned']);
$unassignedCount = intval($statsQuery['unassigned']);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-full overflow-x-hidden">
    <!-- Page Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-black">Import & Manage</h1>
            <p class="text-gray-500 mt-1">Import, organize, and assign contacts by date</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="?download_template"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-100 text-black rounded-xl font-medium hover:bg-gray-200 transition-colors text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Template
            </a>
            <button onclick="showModal('uploadModal')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                Import CSV
            </button>
            <button onclick="showModal('addModal')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-green-600 text-white rounded-xl font-medium hover:bg-green-700 transition-colors text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Contact
            </button>
        </div>
    </div>

    <div class="grid lg:grid-cols-4 gap-4 lg:gap-6 max-w-full overflow-hidden">
        <!-- Left Sidebar: Date Picker -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl border border-gray-200 p-4 sticky top-20">
                <h3 class="font-bold text-sm text-gray-600 uppercase tracking-wider mb-3">Import Dates</h3>

                <a href="import.php"
                    class="block px-3 py-2 rounded-xl mb-1 text-sm font-medium <?= !$filterDate ? 'bg-black text-white' : 'hover:bg-gray-100' ?>">
                    All Contacts
                </a>

                <?php if (!empty($importDates)): ?>
                    <div class="space-y-1 max-h-64 overflow-y-auto">
                        <?php foreach ($importDates as $d): ?>
                            <a href="?date=<?= $d['import_date'] ?>"
                                class="flex items-center justify-between px-3 py-2 rounded-xl text-sm <?= $filterDate === $d['import_date'] ? 'bg-black text-white' : 'hover:bg-gray-100' ?>">
                                <span><?= formatDate($d['import_date'], 'M d, Y') ?></span>
                                <span
                                    class="<?= $filterDate === $d['import_date'] ? 'bg-white bg-opacity-20' : 'bg-gray-100' ?> px-2 py-0.5 rounded-full text-xs"><?= $d['count'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-gray-400 py-4 text-center">No imports yet</p>
                <?php endif; ?>

                <hr class="my-4 border-gray-100">

                <h3 class="font-bold text-sm text-gray-600 uppercase tracking-wider mb-3">Assignment</h3>
                <div class="space-y-1">
                    <?php
                    $baseUrl = '?';
                    if ($filterDate)
                        $baseUrl .= 'date=' . $filterDate . '&';
                    if ($filterCallStatus)
                        $baseUrl .= 'call_status=' . $filterCallStatus . '&';
                    if ($filterAssignedTo)
                        $baseUrl .= 'assigned_to=' . $filterAssignedTo . '&';
                    ?>
                    <a href="<?= $baseUrl ?>status=all"
                        class="block px-3 py-2 rounded-xl text-sm <?= $filterStatus === 'all' ? 'bg-gray-100 font-medium' : 'hover:bg-gray-50' ?>">All</a>
                    <a href="<?= $baseUrl ?>status=unassigned"
                        class="block px-3 py-2 rounded-xl text-sm <?= $filterStatus === 'unassigned' ? 'bg-yellow-100 text-yellow-700 font-medium' : 'hover:bg-gray-50' ?>">Unassigned</a>
                    <a href="<?= $baseUrl ?>status=assigned"
                        class="block px-3 py-2 rounded-xl text-sm <?= $filterStatus === 'assigned' ? 'bg-green-100 text-green-700 font-medium' : 'hover:bg-gray-50' ?>">Assigned</a>
                </div>

                <hr class="my-4 border-gray-100">

                <h3 class="font-bold text-sm text-gray-600 uppercase tracking-wider mb-3">Assigned To</h3>
                <select id="assignedToFilter" onchange="applyAssignedToFilter(this.value)"
                    class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-black focus:border-black">
                    <option value="">All Staff</option>
                    <optgroup label="Team Leads">
                        <?php foreach ($teamleads as $tl): ?>
                            <option value="<?= $tl['id'] ?>" <?= $filterAssignedTo == $tl['id'] ? 'selected' : '' ?>><?= sanitize($tl['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Employees">
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $filterAssignedTo == $emp['id'] ? 'selected' : '' ?>><?= sanitize($emp['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>

                <hr class="my-4 border-gray-100">

                <h3 class="font-bold text-sm text-gray-600 uppercase tracking-wider mb-3">Call Status</h3>
                <div class="space-y-1 max-h-48 overflow-y-auto">
                    <?php
                    $statusBaseUrl = '?';
                    if ($filterDate)
                        $statusBaseUrl .= 'date=' . $filterDate . '&';
                    if ($filterStatus !== 'all')
                        $statusBaseUrl .= 'status=' . $filterStatus . '&';
                    if ($filterAssignedTo)
                        $statusBaseUrl .= 'assigned_to=' . $filterAssignedTo . '&';
                    ?>
                    <a href="<?= $statusBaseUrl ?>call_status="
                        class="block px-3 py-2 rounded-xl text-sm <?= !$filterCallStatus ? 'bg-gray-100 font-medium' : 'hover:bg-gray-50' ?>">All
                        Statuses</a>
                    <a href="<?= $statusBaseUrl ?>call_status=none"
                        class="block px-3 py-2 rounded-xl text-sm <?= $filterCallStatus === 'none' ? 'bg-gray-200 font-medium' : 'hover:bg-gray-50' ?>">No
                        Status</a>
                    <?php foreach ($callStatuses as $cs): ?>
                        <a href="<?= $statusBaseUrl ?>call_status=<?= $cs['id'] ?>"
                            class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm <?= $filterCallStatus == $cs['id'] ? 'font-medium' : 'hover:bg-gray-50' ?>"
                            style="<?= $filterCallStatus == $cs['id'] ? 'background-color: ' . $cs['color'] . '20; color: ' . $cs['color'] : '' ?>">
                            <span class="w-2 h-2 rounded-full" style="background-color: <?= $cs['color'] ?>"></span>
                            <?= sanitize($cs['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Main Content: Contacts Table -->
        <div class="lg:col-span-3">
            <!-- Stats Bar -->
            <div class="grid grid-cols-3 gap-2 sm:gap-4 mb-4 sticky top-0 z-20 bg-gray-50 pt-4 pb-2 max-w-full">
                <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 text-center shadow-sm min-w-0">
                    <p class="text-xl sm:text-2xl font-bold truncate"><?= $totalContacts ?></p>
                    <p class="text-xs text-gray-500">Total</p>
                </div>
                <div class="bg-green-50 rounded-xl border border-green-200 p-3 sm:p-4 text-center shadow-sm min-w-0">
                    <p class="text-xl sm:text-2xl font-bold text-green-600 truncate"><?= $assignedCount ?></p>
                    <p class="text-xs text-green-600">Assigned</p>
                </div>
                <div class="bg-yellow-50 rounded-xl border border-yellow-200 p-3 sm:p-4 text-center shadow-sm min-w-0">
                    <p class="text-xl sm:text-2xl font-bold text-yellow-600 truncate"><?= $unassignedCount ?></p>
                    <p class="text-xs text-yellow-600">Unassigned</p>
                </div>
            </div>

            <!-- Bulk Actions Bar -->
            <form method="POST" id="bulkForm">
                <input type="hidden" name="filter_date" value="<?= $filterDate ?>">

                <div class="bg-white rounded-xl border border-gray-200 p-2 sm:p-3 mb-4 flex flex-col sm:flex-row flex-wrap gap-2 sm:gap-3 items-start sm:items-center sticky top-[120px] z-10 shadow-sm max-w-full overflow-hidden">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="selectAll"
                            class="w-5 h-5 rounded border-gray-300 text-black focus:ring-black">
                        <span class="text-sm font-medium">Select All</span>
                    </label>

                    <div class="h-6 w-px bg-gray-200 hidden sm:block"></div>

                    <span class="text-sm text-gray-500">Selected: <strong id="selectedCount">0</strong></span>

                    <div class="flex-1 hidden sm:block"></div>

                    <!-- Action Controls - Stack on mobile -->
                    <div class="w-full sm:w-auto flex flex-col sm:flex-row sm:flex-wrap gap-2 sm:gap-3 max-w-full">
                        <select name="assign_to" id="bulkAssignTo"
                            class="text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-black w-full sm:w-auto">
                            <option value="">Assign to...</option>
                            <optgroup label="Team Leads">
                                <?php foreach ($teamleads as $tl): ?>
                                    <option value="<?= $tl['id'] ?>"><?= sanitize($tl['name']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Employees">
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>"><?= sanitize($emp['name']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>

                        <button type="submit" name="action" value="bulk_assign" id="btnAssign" disabled
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed w-full sm:w-auto">
                            Assign
                        </button>

                        <input type="date" name="new_date" id="moveDate"
                            class="text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-black w-full sm:w-auto">

                        <button type="submit" name="action" value="bulk_move_date" id="btnMove" disabled
                            class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed w-full sm:w-auto">
                            Move
                        </button>

                        <button type="submit" name="action" value="bulk_delete" id="btnDelete" disabled
                            class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed w-full sm:w-auto">
                            Delete
                        </button>

                        <button type="submit" name="action" value="bulk_unassign" id="btnUnassign" disabled
                            class="px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm font-medium hover:bg-yellow-700 disabled:opacity-50 disabled:cursor-not-allowed w-full sm:w-auto">
                            Unassign
                        </button>

                        <div class="h-6 w-px bg-gray-200 hidden sm:block"></div>

                        <button type="button" onclick="showModal('rangeAssignModal')"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 w-full sm:w-auto">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Assign Range
                        </button>

                        <button type="button" onclick="confirmAutoAssign()"
                            class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 w-full sm:w-auto flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Auto Assign
                        </button>
                    </div>
                </div>

                <!-- Contacts Table -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
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
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Date</th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-600">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="contactsTableBody" class="divide-y divide-gray-100">
                                <!-- Initial contacts rendered server-side -->
                                <?php $slNo = 0; foreach ($contacts as $contact): $slNo++; ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <input type="checkbox" name="contact_ids[]" value="<?= $contact['id'] ?>"
                                                    class="contact-cb w-5 h-5 rounded border-gray-300 text-black focus:ring-black">
                                            </td>
                                            <td class="px-3 py-3 text-gray-400 text-xs font-mono"><?= $slNo ?></td>
                                            <td class="px-4 py-3 font-medium"><?= sanitize($contact['name']) ?></td>
                                            <td class="px-4 py-3 text-gray-600 font-mono"><?= sanitize($contact['phone']) ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <?php if ($contact['assigned_name']): ?>
                                                    <span
                                                        class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-medium">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                                        <?= sanitize($contact['assigned_name']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span
                                                        class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-lg text-xs font-medium">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <?php if ($contact['status_name']): ?>
                                                    <span class="px-2 py-1 rounded-lg text-xs font-medium"
                                                        style="background-color: <?= $contact['color'] ?>20; color: <?= $contact['color'] ?>"><?= sanitize($contact['status_name']) ?></span>
                                                <?php elseif ($contact['is_called']): ?>
                                                    <span
                                                        class="px-2 py-1 bg-gray-100 text-gray-600 rounded-lg text-xs">Called</span>
                                                <?php else: ?>
                                                    <span class="text-gray-400">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500 text-xs">
                                                <?= $contact['import_date'] ? formatDate($contact['import_date'], 'M d') : '—' ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center justify-center gap-1">
                                                    <button type="button" onclick='editContact(<?= json_encode($contact) ?>)'
                                                        class="p-1.5 hover:bg-gray-100 rounded-lg" title="Edit">
                                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                    <button type="button" onclick="deleteContact(<?= $contact['id'] ?>)"
                                                        class="p-1.5 hover:bg-red-50 rounded-lg" title="Delete">
                                                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <!-- Infinite Scroll Sentinel -->
                            <div id="scrollSentinel" class="p-8 text-center">
                                <div id="loadingIndicator" class="text-gray-400 text-sm">
                                    <svg class="animate-spin h-5 w-5 mx-auto mb-2 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Loading contacts...
                                </div>
                                <div id="endOfResults" class="hidden text-gray-400 text-sm">
                                    No more contacts to load
                                </div>
                            </div>
                        </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4"
    data-modal-backdrop>
    <div class="bg-white rounded-2xl w-full max-w-md">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-black">Import CSV</h2>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="action" value="upload">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Import Date *</label>
                <input type="date" name="import_date" value="<?= serverDate() ?>" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                <p class="text-xs text-gray-500 mt-1">Contacts will be grouped by this date</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">CSV File *</label>
                <input type="file" name="csv_file" accept=".csv" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-black file:text-white file:font-medium file:cursor-pointer">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="hideModal('uploadModal')"
                    class="flex-1 px-4 py-3 border border-gray-300 rounded-xl font-medium hover:bg-gray-50">Cancel</button>
                <button type="submit"
                    class="flex-1 px-4 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800">Import</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Contact Modal -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4"
    data-modal-backdrop>
    <div class="bg-white rounded-2xl w-full max-w-md">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-black">Add Contact</h2>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add_single">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                <input type="text" name="name" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Phone *</label>
                <input type="tel" name="phone" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Import Date</label>
                <input type="date" name="import_date" value="<?= $filterDate ?: serverDate() ?>"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Assign To</label>
                <select name="assign_to"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                    <option value="">-- Unassigned --</option>
                    <optgroup label="Team Leads">
                        <?php foreach ($teamleads as $tl): ?>
                            <option value="<?= $tl['id'] ?>"><?= sanitize($tl['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Employees">
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= sanitize($emp['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="hideModal('addModal')"
                    class="flex-1 px-4 py-3 border border-gray-300 rounded-xl font-medium hover:bg-gray-50">Cancel</button>
                <button type="submit"
                    class="flex-1 px-4 py-3 bg-green-600 text-white rounded-xl font-medium hover:bg-green-700">Add</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Contact Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4"
    data-modal-backdrop>
    <div class="bg-white rounded-2xl w-full max-w-md">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-black">Edit Contact</h2>
        </div>
        <form method="POST" id="editForm" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit_contact">
            <input type="hidden" name="id" id="editId">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                <input type="text" name="name" id="editName" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Phone *</label>
                <input type="tel" name="phone" id="editPhone" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Assign To</label>
                <select name="assign_to" id="editAssign"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                    <option value="">-- Unassigned --</option>
                    <optgroup label="Team Leads">
                        <?php foreach ($teamleads as $tl): ?>
                            <option value="<?= $tl['id'] ?>"><?= sanitize($tl['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Employees">
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= sanitize($emp['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="hideModal('editModal')"
                    class="flex-1 px-4 py-3 border border-gray-300 rounded-xl font-medium hover:bg-gray-50">Cancel</button>
                <button type="submit"
                    class="flex-1 px-4 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form (hidden) -->
<form id="deleteForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="delete_single">
    <input type="hidden" name="id" id="deleteId">
</form>

<!-- Auto Assign Form (hidden) -->
<form id="autoAssignForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="auto_assign">
    <input type="hidden" name="filter_date" value="<?= htmlspecialchars($filterDate) ?>">
</form>

<!-- Range Assignment Modal -->
<div id="rangeAssignModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4"
    data-modal-backdrop>
    <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-black">Assign Contact Range</h2>
                    <p class="text-sm text-gray-500">Assign rows to a user (overrides existing)</p>
                </div>
            </div>
        </div>
        <form id="rangeAssignForm" class="p-6 space-y-5">
            <!-- Current filters summary -->
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-3">
                <div class="flex items-center gap-2 text-sm">
                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    <span class="text-gray-500">Filters:</span>
                    <span class="font-medium text-gray-700" id="rangeFilterSummary">
                        <?php
                        $filterParts = [];
                        if ($filterDate) $filterParts[] = formatDate($filterDate, 'M d, Y');
                        if ($filterStatus !== 'all') $filterParts[] = ucfirst($filterStatus);
                        if ($filterCallStatus) {
                            $callStatusName = $filterCallStatus === 'none' ? 'No Status' : '';
                            foreach ($callStatuses as $cs) {
                                if ($cs['id'] == $filterCallStatus) $callStatusName = $cs['name'];
                            }
                            $filterParts[] = $callStatusName;
                        }
                        echo !empty($filterParts) ? implode(' / ', $filterParts) : 'All contacts';
                        ?>
                    </span>
                </div>
            </div>

            <!-- Assign to dropdown -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Assign To *</label>
                <select id="rangeAssignTo" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Select user...</option>
                    <optgroup label="Team Leads">
                        <?php foreach ($teamleads as $tl): ?>
                            <option value="<?= $tl['id'] ?>"><?= sanitize($tl['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Employees">
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= sanitize($emp['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
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
                        <input type="number" id="rangeEnd" min="1" value="1000" required
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-right font-mono">
                    </div>
                </div>
                <!-- Quick range buttons -->
                <div class="flex gap-1.5 mt-2">
                    <button type="button" onclick="setRange(1, 1000)" class="flex-1 text-xs px-2 py-1.5 bg-gray-100 hover:bg-indigo-100 hover:text-indigo-700 rounded-lg font-medium transition-colors">1-1K</button>
                    <button type="button" onclick="setRange(1001, 2000)" class="flex-1 text-xs px-2 py-1.5 bg-gray-100 hover:bg-indigo-100 hover:text-indigo-700 rounded-lg font-medium transition-colors">1K-2K</button>
                    <button type="button" onclick="setRange(2001, 5000)" class="flex-1 text-xs px-2 py-1.5 bg-gray-100 hover:bg-indigo-100 hover:text-indigo-700 rounded-lg font-medium transition-colors">2K-5K</button>
                    <button type="button" onclick="setRange(5001, 10000)" class="flex-1 text-xs px-2 py-1.5 bg-gray-100 hover:bg-indigo-100 hover:text-indigo-700 rounded-lg font-medium transition-colors">5K-10K</button>
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Assign Range
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // ========== Infinite Scroll Contact Manager ==========
    class ContactsManager {
        constructor() {
            this.container = document.getElementById('contactsTableBody');
            this.loadingIndicator = document.getElementById('loadingIndicator');
            this.endOfResults = document.getElementById('endOfResults');
            // Start from where server-side rendering left off
            this.currentCursor = <?= $initialCursor ? json_encode($initialCursor) : 'null' ?>;
            this.isLoading = false;
            this.hasMore = <?= $initialHasMore ? 'true' : 'false' ?>;
            this.filters = this.getFiltersFromURL();
            this.limit = 200;
            this.rowCount = <?= count($contacts) ?>;

            // If no more data from server, show end message
            if (!this.hasMore) {
                this.showEndOfResults();
            }
        }

        getFiltersFromURL() {
            const params = new URLSearchParams(window.location.search);
            return {
                import_date: params.get('date') || '',
                status: params.get('status') || 'all',
                call_status: params.get('call_status') || '',
                assigned_to: params.get('assigned_to') || ''
            };
        }

        async loadMore() {
            if (this.isLoading || !this.hasMore) return;

            this.isLoading = true;
            this.showLoading();

            const params = new URLSearchParams({
                cursor: this.currentCursor || '',
                limit: this.limit,
                ...this.filters
            });

            const url = `<?= APP_URL ?>/api/contacts_paginated.php?${params}`;

            try {
                const response = await fetch(url);

                // Check if we got a valid response
                if (!response.ok) {
                    throw new Error(`HTTP error ${response.status}`);
                }

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    // Got HTML redirect or error page instead of JSON
                    const text = await response.text();
                    console.error('Non-JSON response:', text.substring(0, 500));
                    throw new Error('Server returned non-JSON response');
                }

                const result = await response.json();

                if (result.success) {
                    this.renderContacts(result.data.contacts);
                    this.currentCursor = result.data.pagination.next_cursor;
                    this.hasMore = result.data.pagination.has_more;
                    this.updateStats(result.data.stats);

                    if (!this.hasMore) {
                        this.showEndOfResults();
                    }
                } else {
                    console.error('API error:', result.error);
                    this.showError('API error: ' + (result.error || 'Unknown'));
                }
            } catch (error) {
                console.error('Error loading contacts:', error);
                this.showError(error.message);
            } finally {
                this.isLoading = false;
            }
        }

        renderContacts(contacts) {
            contacts.forEach(contact => {
                this.rowCount++;
                const row = this.createContactRow(contact, this.rowCount);
                this.container.appendChild(row);
            });

            // Update select all checkbox state
            updateBulkButtons();
        }

        createContactRow(contact, slNo) {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50';

            const assignedBadge = contact.assigned_name
                ? `<span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-medium">
                       <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                       ${this.escapeHtml(contact.assigned_name)}
                   </span>`
                : `<span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-lg text-xs font-medium">Unassigned</span>`;

            const statusBadge = contact.status_name
                ? `<span class="px-2 py-1 rounded-lg text-xs font-medium" style="background-color: ${contact.color}20; color: ${contact.color}">${this.escapeHtml(contact.status_name)}</span>`
                : `<span class="text-gray-400">—</span>`;

            const importDate = contact.import_date ? this.formatDate(contact.import_date) : '—';

            tr.innerHTML = `
                <td class="px-4 py-3">
                    <input type="checkbox" name="contact_ids[]" value="${contact.id}"
                           class="contact-cb w-5 h-5 rounded border-gray-300 text-black focus:ring-black">
                </td>
                <td class="px-3 py-3 text-gray-400 text-xs font-mono">${slNo}</td>
                <td class="px-4 py-3 font-medium">${this.escapeHtml(contact.name)}</td>
                <td class="px-4 py-3 text-gray-600 font-mono">${this.escapeHtml(contact.phone)}</td>
                <td class="px-4 py-3">${assignedBadge}</td>
                <td class="px-4 py-3">${statusBadge}</td>
                <td class="px-4 py-3 text-gray-500 text-xs">${importDate}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-center gap-1">
                        <button type="button" onclick='editContact(${JSON.stringify(contact)})'
                            class="p-1.5 hover:bg-gray-100 rounded-lg" title="Edit">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <button type="button" onclick="deleteContact(${contact.id})"
                            class="p-1.5 hover:bg-red-50 rounded-lg" title="Delete">
                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </td>
            `;

            // Add event listener for checkbox
            const checkbox = tr.querySelector('.contact-cb');
            checkbox.addEventListener('change', updateBulkButtons);

            return tr;
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        formatDate(dateStr) {
            const date = new Date(dateStr);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return `${months[date.getMonth()]} ${date.getDate()}`;
        }

        updateStats(stats) {
            // Update stats counters in the stats bar
            const statCards = document.querySelectorAll('.grid.grid-cols-3 .font-bold.truncate');
            if (statCards.length >= 3) {
                statCards[0].textContent = stats.total.toLocaleString();
                statCards[1].textContent = stats.assigned.toLocaleString();
                statCards[2].textContent = stats.unassigned.toLocaleString();
            }
        }

        setupInfiniteScroll() {
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting && this.hasMore && !this.isLoading) {
                    this.loadMore();
                }
            }, { threshold: 0.1 });

            const sentinel = document.getElementById('scrollSentinel');
            if (sentinel) {
                observer.observe(sentinel);
            }
        }

        showLoading() {
            if (this.loadingIndicator) {
                this.loadingIndicator.classList.remove('hidden');
            }
        }

        hideLoading() {
            if (this.loadingIndicator) {
                this.loadingIndicator.classList.add('hidden');
            }
        }

        showEndOfResults() {
            if (this.endOfResults) {
                this.endOfResults.classList.remove('hidden');
            }
            if (this.loadingIndicator) {
                this.loadingIndicator.classList.add('hidden');
            }
        }

        showError(msg) {
            if (this.loadingIndicator) {
                this.loadingIndicator.classList.remove('hidden');
                this.loadingIndicator.innerHTML = `
                    <span class="text-red-500">Error loading contacts: ${msg || 'Unknown error'}
                    <br><button onclick="contactsManager.reload()" class="underline mt-2">Retry</button></span>
                `;
            }
        }

        reload() {
            this.container.innerHTML = '';
            this.currentCursor = null;
            this.hasMore = true;
            this.rowCount = 0;
            if (this.endOfResults) {
                this.endOfResults.classList.add('hidden');
            }
            // On reload, fetch fresh from API
            this.loadMore();
        }
    }

    // Initialize contacts manager
    const contactsManager = new ContactsManager();

    // Load contacts - handle case where DOMContentLoaded already fired
    function initContactsLoad() {
        // Only set up infinite scroll for loading MORE data
        // Initial data is already rendered server-side
        contactsManager.setupInfiniteScroll();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => setTimeout(initContactsLoad, 100));
    } else {
        // DOM already ready
        setTimeout(initContactsLoad, 100);
    }

    // ========== Original Select All Logic ==========
    // Select all
    document.getElementById('selectAll')?.addEventListener('change', function () {
        document.querySelectorAll('.contact-cb').forEach(cb => cb.checked = this.checked);
        updateBulkButtons();
    });

    // Individual checkboxes
    document.querySelectorAll('.contact-cb').forEach(cb => {
        cb.addEventListener('change', updateBulkButtons);
    });

    function updateBulkButtons() {
        const count = document.querySelectorAll('.contact-cb:checked').length;
        document.getElementById('selectedCount').textContent = count;

        const hasSelection = count > 0;
        document.getElementById('btnAssign').disabled = !hasSelection || !document.getElementById('bulkAssignTo').value;
        document.getElementById('btnMove').disabled = !hasSelection || !document.getElementById('moveDate').value;
        document.getElementById('btnDelete').disabled = !hasSelection;
        document.getElementById('btnUnassign').disabled = !hasSelection;
    }

    document.getElementById('bulkAssignTo')?.addEventListener('change', updateBulkButtons);
    document.getElementById('moveDate')?.addEventListener('change', updateBulkButtons);

    function applyAssignedToFilter(value) {
        const params = new URLSearchParams(window.location.search);
        if (value) {
            params.set('assigned_to', value);
        } else {
            params.delete('assigned_to');
        }
        window.location.href = '?' + params.toString();
    }

    function editContact(contact) {
        document.getElementById('editId').value = contact.id;
        document.getElementById('editName').value = contact.name;
        document.getElementById('editPhone').value = contact.phone;
        document.getElementById('editAssign').value = contact.assigned_to || '';
        showModal('editModal');
    }

    function deleteContact(id) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }

    function confirmAutoAssign() {
        const unassigned = <?= $unassignedCount ?>;
        const staff = <?= count($allStaff) ?>;
        if (staff === 0) { showToast('No active staff to assign to.', 'error'); return; }
        if (unassigned === 0) { showToast('No unassigned contacts to distribute.', 'error'); return; }
        const dateLabel = <?= $filterDate ? json_encode('for ' . formatDate($filterDate, 'M d, Y')) : "'(all dates)'" ?>;
        if (confirm(`Auto-assign ${unassigned} unassigned contacts ${dateLabel} randomly across ${staff} staff members?\n\nThis cannot be undone.`)) {
            document.getElementById('autoAssignForm').submit();
        }
    }

    // ========== Range Assignment Functions ==========
    function setRange(start, end) {
        document.getElementById('rangeStart').value = start;
        document.getElementById('rangeEnd').value = end;
        updateRangePreview();
    }

    function updateRangePreview() {
        const start = parseInt(document.getElementById('rangeStart').value);
        const end = parseInt(document.getElementById('rangeEnd').value);
        const assignTo = document.getElementById('rangeAssignTo');
        const userName = assignTo.options[assignTo.selectedIndex]?.text;

        if (start && end && userName && userName !== 'Select user...') {
            const count = end - start + 1;
            const previewDiv = document.getElementById('rangePreview');
            const previewText = document.getElementById('rangePreviewText');

            previewText.textContent = `Assign ${count.toLocaleString()} contacts (rows ${start.toLocaleString()}-${end.toLocaleString()}) to ${userName}`;
            previewDiv.classList.remove('hidden');
        } else {
            document.getElementById('rangePreview').classList.add('hidden');
        }
    }

    // Range assignment form handler
    document.getElementById('rangeAssignForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const start = parseInt(document.getElementById('rangeStart').value);
        const end = parseInt(document.getElementById('rangeEnd').value);
        const assignTo = parseInt(document.getElementById('rangeAssignTo').value);

        // Validation
        if (!assignTo || isNaN(assignTo)) {
            showToast('Please select a user to assign to', 'error');
            return;
        }

        if (start > end) {
            showToast('Start row must be less than or equal to end row', 'error');
            return;
        }

        const count = end - start + 1;
        if (count > 50000) {
            showToast('Cannot assign more than 50,000 contacts at once. Please use smaller ranges.', 'error');
            return;
        }

        // Show loading state
        const btn = document.getElementById('rangeAssignBtn');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin h-4 w-4 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

        try {
            const response = await fetch('<?= APP_URL ?>/api/bulk_operations.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'assign_range',
                    filters: contactsManager.filters,
                    range: { start, end },
                    assign_to: assignTo
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast(`Successfully assigned ${result.data.affected_rows.toLocaleString()} contacts!`);
                hideModal('rangeAssignModal');

                // Reload the contacts view - remove status filter since contacts are now assigned
                const reloadParams = new URLSearchParams(window.location.search);
                reloadParams.delete('status');
                setTimeout(() => {
                    window.location.href = '?' + reloadParams.toString();
                }, 1000);

                // Reset form
                document.getElementById('rangeAssignForm').reset();
                document.getElementById('rangeStart').value = '1';
                document.getElementById('rangeEnd').value = '1000';
                document.getElementById('rangePreview').classList.add('hidden');
            } else {
                showToast('Error: ' + (result.error || 'Unknown error occurred'), 'error');
            }
        } catch (error) {
            showToast('Failed to assign contacts: ' + error.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });

    // Add event listeners for range preview
    document.getElementById('rangeStart')?.addEventListener('input', updateRangePreview);
    document.getElementById('rangeEnd')?.addEventListener('input', updateRangePreview);
    document.getElementById('rangeAssignTo')?.addEventListener('change', updateRangePreview);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>