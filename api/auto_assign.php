<?php
/**
 * Auto-Assign API
 * - Assigns unassigned contacts round-robin to active employees
 * - Runs auto-assign rules (status-based reassignment)
 * Called by browser polling (admin pages) every N seconds
 */
require_once __DIR__ . '/../config/config.php';
requireAuth();

if (!isAdmin()) {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

$autoAssignEnabled = getSetting('auto_assign_enabled', '0') === '1';
if (!$autoAssignEnabled) {
    jsonResponse(['success' => true, 'message' => 'disabled', 'assigned' => 0, 'reassigned' => 0]);
}

// ── Active employees + teamleads (sorted fewest contacts first for round-robin) ──
$workers = db()->fetchAll("
    SELECT u.id, COUNT(c.id) as assigned_count
    FROM users u
    LEFT JOIN contacts c ON c.assigned_to = u.id AND c.is_called = 0
    WHERE u.role IN ('employee','teamlead') AND u.is_active = 1
    GROUP BY u.id
    ORDER BY assigned_count ASC
");

if (empty($workers)) {
    jsonResponse(['success' => true, 'message' => 'no_workers', 'assigned' => 0, 'reassigned' => 0]);
}

// ── Step 1: Assign unassigned contacts (only NEW leads) ──
$assignedCount = 0;
$unassigned = db()->fetchAll(
    "SELECT id FROM contacts WHERE assigned_to IS NULL ORDER BY created_at ASC LIMIT 200"
);

if (!empty($unassigned)) {
    $idx = 0;
    foreach ($unassigned as $contact) {
        $worker = $workers[$idx % count($workers)];
        db()->update(
            "UPDATE contacts SET assigned_to = ? WHERE id = ?",
            [$worker['id'], $contact['id']]
        );
        $idx++;
        $assignedCount++;
    }
}

// ── Step 2: Auto-assign rules (status-based re-assignment) ──
$reassignedCount = 0;
$rules = db()->fetchAll("SELECT * FROM auto_assign_rules WHERE is_active = 1");

foreach ($rules as $rule) {
    $days     = max(1, intval($rule['reassign_every_days']));
    $statusId = intval($rule['status_id']);
    $daysStr  = "-{$days} days";

    $stale = db()->fetchAll("
        SELECT id FROM contacts
        WHERE status_id = ?
          AND assigned_to IS NOT NULL
          AND (
               (last_call_date IS NOT NULL AND last_call_date < datetime('now', ?))
            OR (last_call_date IS NULL      AND created_at      < datetime('now', ?))
          )
    ", [$statusId, $daysStr, $daysStr]);

    if (!empty($stale)) {
        foreach ($stale as $contact) {
            $randomWorker = $workers[array_rand($workers)];
            db()->update(
                "UPDATE contacts SET assigned_to = ? WHERE id = ?",
                [$randomWorker['id'], $contact['id']]
            );
            $reassignedCount++;
        }
    }
}

jsonResponse([
    'success'    => true,
    'assigned'   => $assignedCount,
    'reassigned' => $reassignedCount,
    'timestamp'  => date('Y-m-d H:i:s'),
]);
