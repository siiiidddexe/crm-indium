<?php
/**
 * Calling Cards API - Lazy Loading for Employee/TeamLead
 * Returns pending contacts for a user with cursor-based pagination
 */

ob_start();
require_once __DIR__ . '/../config/config.php';
ob_end_clean();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$userId = $_SESSION['user_id'];
$cursor = $_GET['cursor'] ?? '';
$limit = min(intval($_GET['limit'] ?? 20), 50); // Load 20 cards at a time, max 50

// Build WHERE clause
$where = ["c.assigned_to = ?", "c.is_called = 0"];
$params = [$userId];

// Add cursor condition for pagination
if ($cursor) {
    $where[] = "c.id < ?";
    $params[] = intval($cursor);
}

$whereClause = implode(' AND ', $where);

// Fetch contacts
$fetchLimit = intval($limit + 1);
$contacts = db()->fetchAll("
    SELECT c.*
    FROM contacts c
    WHERE $whereClause
    ORDER BY c.id DESC
    LIMIT $fetchLimit
", $params); // Fetch one extra to check if there are more

// Check if there are more contacts
$hasMore = count($contacts) > $limit;
if ($hasMore) {
    array_pop($contacts); // Remove the extra contact
}

// Get next cursor (last contact ID)
$nextCursor = !empty($contacts) ? end($contacts)['id'] : null;

// Get total count
$totalCount = db()->fetch("
    SELECT COUNT(*) as count
    FROM contacts
    WHERE assigned_to = ? AND is_called = 0
", [$userId])['count'];

echo json_encode([
    'success' => true,
    'data' => [
        'contacts' => $contacts,
        'pagination' => [
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
            'total_count' => intval($totalCount)
        ]
    ]
]);
