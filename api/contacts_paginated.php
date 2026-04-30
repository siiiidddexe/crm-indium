<?php
/**
 * Paginated Contacts API
 * Cursor-based pagination for handling millions of contacts efficiently
 */

// Enable error reporting for logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Disable any output buffering that might cause issues
while (ob_get_level()) {
    ob_end_clean();
}

// Start output buffering so we can catch any stray output
ob_start();

require_once __DIR__ . '/../config/config.php';

// Discard any output generated during config/session init
ob_end_clean();

// Now set headers after session has started
header('Content-Type: application/json');

// Custom error handler to return JSON on PHP errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "PHP Error: $errstr in $errfile:$errline"]);
    exit;
});

set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Get pagination parameters
$cursor = $_GET['cursor'] ?? '';
$limit = min(intval($_GET['limit'] ?? 50), 500); // Max 500 per page

// Get filter parameters
$filterDate = $_GET['import_date'] ?? '';
$filterStatus = $_GET['status'] ?? 'all';
$filterCallStatus = $_GET['call_status'] ?? '';
$filterAssignedTo = $_GET['assigned_to'] ?? '';

// Build WHERE conditions
$where = [];
$params = [];

// Cursor condition (for pagination)
// NOTE: Cursor is inlined (already intval'd) because PDO binds params as strings,
// and SQLite integer comparisons fail with string-bound values for large IDs
$cursorInt = $cursor ? intval($cursor) : 0;
if ($cursorInt > 0) {
    $where[] = "c.id < $cursorInt";
}

// Date filter
if ($filterDate) {
    $where[] = "c.import_date = ?";
    $params[] = $filterDate;
}

// Assignment status filter
if ($filterStatus === 'unassigned') {
    $where[] = "c.assigned_to IS NULL";
} elseif ($filterStatus === 'assigned') {
    $where[] = "c.assigned_to IS NOT NULL";
}

// Assigned to specific employee filter
if ($filterAssignedTo) {
    $where[] = "c.assigned_to = ?";
    $params[] = intval($filterAssignedTo);
}

// Call status filter
if ($filterCallStatus) {
    if ($filterCallStatus === 'none') {
        $where[] = "c.status_id IS NULL";
    } else {
        $where[] = "c.status_id = ?";
        $params[] = intval($filterCallStatus);
    }
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// NOTE: LIMIT is inlined (already intval'd) because PDO binds params as strings,
// and SQLite LIMIT fails with string-bound values
$fetchLimit = intval($limit + 1);

try {
    $contacts = db()->fetchAll("
        SELECT c.*, u.name as assigned_name, u.role as assigned_role, cs.name as status_name, cs.color
        FROM contacts c
        LEFT JOIN users u ON c.assigned_to = u.id
        LEFT JOIN call_statuses cs ON c.status_id = cs.id
        $whereClause
        ORDER BY c.id DESC
        LIMIT $fetchLimit
    ", $params);
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => 'Query failed: ' . $e->getMessage(),
        'debug' => [
            'where' => $whereClause,
            'params' => $params,
            'limit' => $fetchLimit
        ]
    ], 500);
}

// Check if there are more results
$hasMore = count($contacts) > $limit;
if ($hasMore) {
    array_pop($contacts); // Remove the extra record
}

// Get next cursor (last contact ID in current page)
$nextCursor = !empty($contacts) ? end($contacts)['id'] : null;

// Calculate stats (caching disabled for now to avoid permission issues)
$statsWhere = [];
$statsParams = [];

if ($filterDate) {
    $statsWhere[] = "import_date = ?";
    $statsParams[] = $filterDate;
}

if ($filterStatus === 'unassigned') {
    $statsWhere[] = "assigned_to IS NULL";
} elseif ($filterStatus === 'assigned') {
    $statsWhere[] = "assigned_to IS NOT NULL";
}

if ($filterAssignedTo) {
    $statsWhere[] = "assigned_to = ?";
    $statsParams[] = intval($filterAssignedTo);
}

if ($filterCallStatus) {
    if ($filterCallStatus === 'none') {
        $statsWhere[] = "status_id IS NULL";
    } else {
        $statsWhere[] = "status_id = ?";
        $statsParams[] = intval($filterCallStatus);
    }
}

$statsWhereClause = !empty($statsWhere) ? 'WHERE ' . implode(' AND ', $statsWhere) : '';

$statsQuery = db()->fetch("
    SELECT
        COUNT(*) as total,
        COUNT(assigned_to) as assigned,
        COUNT(*) - COUNT(assigned_to) as unassigned
    FROM contacts
    $statsWhereClause
", $statsParams);

$stats = [
    'total' => intval($statsQuery['total']),
    'assigned' => intval($statsQuery['assigned']),
    'unassigned' => intval($statsQuery['unassigned'])
];

// Build response
jsonResponse([
    'success' => true,
    'data' => [
        'contacts' => $contacts,
        'pagination' => [
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
            'total_count' => $stats['total'],
            'page_count' => count($contacts)
        ],
        'stats' => $stats
    ]
]);
?>
