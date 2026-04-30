<?php
/**
 * Contacts API
 * Handles contact status updates and CRUD operations
 */

ob_start();
require_once __DIR__ . '/../config/config.php';
ob_end_clean();

header('Content-Type: application/json');

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $action = $input['action'] ?? '';

    if ($action === 'update_status') {
        $contactId = $input['contact_id'] ?? 0;
        $statusId = $input['status_id'] ?? 0;
        $userId = $input['user_id'] ?? 0;
        $notes = $input['notes'] ?? '';

        if (!$contactId || !$statusId || !$userId) {
            jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
        }

        // Update contact
        db()->update(
            "UPDATE contacts SET status_id = ?, is_called = 1, last_call_date = ? WHERE id = ?",
            [$statusId, serverTime(), $contactId]
        );

        // Log the call
        db()->insert(
            "INSERT INTO call_logs (contact_id, user_id, status_id, call_time, notes) VALUES (?, ?, ?, ?, ?)",
            [$contactId, $userId, $statusId, serverTime(), $notes]
        );

        jsonResponse(['success' => true]);
    } elseif ($action === 'add') {
        $name = sanitize($input['name'] ?? '');
        $phone = sanitize($input['phone'] ?? '');
        $assignTo = $input['assigned_to'] ?? null;

        if (!$name || !$phone) {
            jsonResponse(['success' => false, 'error' => 'Name and phone required'], 400);
        }

        $id = db()->insert(
            "INSERT INTO contacts (name, phone, assigned_to, import_date, created_at) VALUES (?, ?, ?, ?, ?)",
            [$name, $phone, $assignTo, serverDate(), serverTime()]
        );

        jsonResponse(['success' => true, 'id' => $id]);
    } elseif ($action === 'assign') {
        $contactIds = $input['contact_ids'] ?? [];
        $assignTo = $input['assign_to'] ?? '';

        if (empty($contactIds) || !$assignTo) {
            jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
        }

        $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
        $params = array_merge([$assignTo], $contactIds);

        $updated = db()->update(
            "UPDATE contacts SET assigned_to = ? WHERE id IN ($placeholders)",
            $params
        );

        jsonResponse(['success' => true, 'updated' => $updated]);
    } elseif ($action === 'delete') {
        $contactId = $input['contact_id'] ?? 0;

        if (!$contactId) {
            jsonResponse(['success' => false, 'error' => 'Contact ID required'], 400);
        }

        db()->delete("DELETE FROM contacts WHERE id = ?", [$contactId]);
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
    }
} elseif ($method === 'GET') {
    $userId = $_GET['user_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $date = $_GET['date'] ?? '';

    $where = [];
    $params = [];

    if ($userId) {
        $where[] = "c.assigned_to = ?";
        $params[] = $userId;
    }

    if ($status === 'pending') {
        $where[] = "c.is_called = 0";
    } elseif ($status === 'called') {
        $where[] = "c.is_called = 1";
    }

    if ($date) {
        $where[] = "DATE(c.import_date) = ?";
        $params[] = $date;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $contacts = db()->fetchAll("
        SELECT c.*, u.name as assigned_name, cs.name as status_name, cs.color
        FROM contacts c
        LEFT JOIN users u ON c.assigned_to = u.id
        LEFT JOIN call_statuses cs ON c.status_id = cs.id
        $whereClause
        ORDER BY c.created_at DESC
    ", $params);

    jsonResponse(['success' => true, 'data' => $contacts]);
} else {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}
?>