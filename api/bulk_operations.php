<?php
/**
 * Bulk Operations API
 * Handle range-based bulk assignments and operations on contacts
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Buffer any stray output during config/session init
ob_start();
require_once __DIR__ . '/../config/config.php';
ob_end_clean();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'assign_range') {
    $filters = $input['filters'] ?? [];
    $range = $input['range'] ?? [];
    $assignTo = intval($input['assign_to'] ?? 0);

    $rangeStart = intval($range['start'] ?? 0);
    $rangeEnd = intval($range['end'] ?? 0);

    // Validation
    if ($rangeStart <= 0 || $rangeEnd <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid range values'], 400);
    }

    if ($rangeStart > $rangeEnd) {
        jsonResponse(['success' => false, 'error' => 'Start must be <= end'], 400);
    }

    if (!$assignTo) {
        jsonResponse(['success' => false, 'error' => 'Assign to user is required'], 400);
    }

    // Build filter conditions — only date and call_status
    // NOTE: We skip the assignment status filter so range assign always
    // overrides existing assignments (assigns regardless of current owner)
    $where = [];
    $params = [];

    if (!empty($filters['import_date'])) {
        $where[] = "import_date = ?";
        $params[] = $filters['import_date'];
    }

    if (!empty($filters['call_status'])) {
        if ($filters['call_status'] === 'none') {
            $where[] = "status_id IS NULL";
        } else {
            $where[] = "status_id = ?";
            $params[] = intval($filters['call_status']);
        }
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        db()->getConnection()->beginTransaction();

        // Collect all contact IDs in the range first (stable snapshot)
        // NOTE: Range values are inlined (already intval'd) because PDO binds
        // params as strings, and SQLite ROW_NUMBER() comparisons fail with string-bound values
        $idsSql = "
            SELECT id FROM (
                SELECT id, ROW_NUMBER() OVER (ORDER BY id DESC) as row_num
                FROM contacts
                $whereClause
            ) ranked
            WHERE row_num BETWEEN $rangeStart AND $rangeEnd
        ";
        $idsParams = $params;
        $rows = db()->fetchAll($idsSql, $idsParams);
        $ids = array_column($rows, 'id');

        $totalAffected = 0;
        if (!empty($ids)) {
            // Batch update by collected IDs
            $batchSize = 1000;
            for ($i = 0; $i < count($ids); $i += $batchSize) {
                $batch = array_slice($ids, $i, $batchSize);
                $placeholders = implode(',', array_fill(0, count($batch), '?'));
                $updateParams = array_merge([$assignTo], $batch);
                $totalAffected += db()->update(
                    "UPDATE contacts SET assigned_to = ? WHERE id IN ($placeholders)",
                    $updateParams
                );
            }
        }

        db()->getConnection()->commit();

        jsonResponse([
            'success' => true,
            'data' => [
                'affected_rows' => $totalAffected,
                'range' => [
                    'start' => $rangeStart,
                    'end' => $rangeEnd,
                    'size' => $rangeEnd - $rangeStart + 1
                ]
            ]
        ]);
    } catch (Exception $e) {
        db()->getConnection()->rollBack();
        jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    }
} elseif ($action === 'reassign_range') {
    // Reassign contacts FROM one user TO another by row range
    $fromUser = intval($input['from_user'] ?? 0);
    $assignTo = intval($input['assign_to'] ?? 0);
    $range = $input['range'] ?? [];

    $rangeStart = intval($range['start'] ?? 0);
    $rangeEnd = intval($range['end'] ?? 0);

    if ($rangeStart <= 0 || $rangeEnd <= 0 || $rangeStart > $rangeEnd) {
        jsonResponse(['success' => false, 'error' => 'Invalid range values'], 400);
    }

    if (!$fromUser || !$assignTo) {
        jsonResponse(['success' => false, 'error' => 'Both From and To users are required'], 400);
    }

    if ($fromUser === $assignTo) {
        jsonResponse(['success' => false, 'error' => 'From and To users must be different'], 400);
    }

    // Get total pending contacts for "from" user
    $totalAvailable = db()->fetch(
        "SELECT COUNT(*) as count FROM contacts WHERE assigned_to = ? AND is_called = 0",
        [$fromUser]
    )['count'];

    try {
        db()->getConnection()->beginTransaction();

        // Collect all contact IDs in the range first (stable snapshot)
        // NOTE: Range values are inlined (already intval'd) because PDO binds
        // params as strings, and SQLite ROW_NUMBER() comparisons fail with string-bound values
        $idsSql = "
            SELECT id FROM (
                SELECT id, ROW_NUMBER() OVER (ORDER BY id DESC) as row_num
                FROM contacts
                WHERE assigned_to = ? AND is_called = 0
            ) ranked
            WHERE row_num BETWEEN $rangeStart AND $rangeEnd
        ";
        $rows = db()->fetchAll($idsSql, [$fromUser]);
        $ids = array_column($rows, 'id');

        $totalAffected = 0;
        if (!empty($ids)) {
            // Batch update by collected IDs
            $batchSize = 1000;
            for ($i = 0; $i < count($ids); $i += $batchSize) {
                $batch = array_slice($ids, $i, $batchSize);
                $placeholders = implode(',', array_fill(0, count($batch), '?'));
                $updateParams = array_merge([$assignTo], $batch);
                $totalAffected += db()->update(
                    "UPDATE contacts SET assigned_to = ? WHERE id IN ($placeholders)",
                    $updateParams
                );
            }
        }

        db()->getConnection()->commit();

        jsonResponse([
            'success' => true,
            'data' => [
                'affected_rows' => $totalAffected,
                'total_available' => intval($totalAvailable),
                'range' => [
                    'start' => $rangeStart,
                    'end' => $rangeEnd,
                    'size' => $rangeEnd - $rangeStart + 1
                ]
            ]
        ]);
    } catch (Exception $e) {
        db()->getConnection()->rollBack();
        jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    }

} elseif ($action === 'delete_range') {
    $filters = $input['filters'] ?? [];
    $range = $input['range'] ?? [];

    $rangeStart = intval($range['start'] ?? 0);
    $rangeEnd = intval($range['end'] ?? 0);

    // Validation
    if ($rangeStart <= 0 || $rangeEnd <= 0 || $rangeStart > $rangeEnd) {
        jsonResponse(['success' => false, 'error' => 'Invalid range values'], 400);
    }

    // Build filter conditions
    $where = [];
    $params = [];

    if (!empty($filters['import_date'])) {
        $where[] = "import_date = ?";
        $params[] = $filters['import_date'];
    }

    if (isset($filters['status'])) {
        if ($filters['status'] === 'unassigned') {
            $where[] = "assigned_to IS NULL";
        } elseif ($filters['status'] === 'assigned') {
            $where[] = "assigned_to IS NOT NULL";
        }
    }

    if (!empty($filters['call_status'])) {
        if ($filters['call_status'] === 'none') {
            $where[] = "status_id IS NULL";
        } else {
            $where[] = "status_id = ?";
            $params[] = intval($filters['call_status']);
        }
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        db()->getConnection()->beginTransaction();

        // NOTE: Range values are inlined (already intval'd) because PDO binds
        // params as strings, and SQLite ROW_NUMBER() comparisons fail with string-bound values
        $sql = "
            DELETE FROM contacts
            WHERE id IN (
                SELECT id FROM (
                    SELECT id, ROW_NUMBER() OVER (ORDER BY id DESC) as row_num
                    FROM contacts
                    $whereClause
                ) ranked
                WHERE row_num BETWEEN $rangeStart AND $rangeEnd
            )
        ";

        $deleteParams = $params;
        $totalDeleted = db()->delete($sql, $deleteParams);

        db()->getConnection()->commit();

        jsonResponse([
            'success' => true,
            'data' => [
                'deleted_rows' => $totalDeleted
            ]
        ]);
    } catch (Exception $e) {
        db()->getConnection()->rollBack();
        jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    }
} else {
    jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}
?>
