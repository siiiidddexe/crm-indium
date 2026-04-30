<?php
/**
 * Attendance API
 * Handles punch in/out operations
 */

ob_start();
require_once __DIR__ . '/../config/config.php';
ob_end_clean();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $action = $input['action'] ?? '';
    $userId = $input['user_id'] ?? 0;

    if (!$userId) {
        jsonResponse(['success' => false, 'error' => 'User ID required'], 400);
    }

    if ($action === 'punch_in') {
        // Check if already punched in today
        $existing = db()->fetch("SELECT * FROM attendance WHERE user_id = ? AND date = ?", [$userId, serverDate()]);

        if ($existing) {
            jsonResponse(['success' => false, 'error' => 'Already punched in today'], 400);
        }

        $id = db()->insert(
            "INSERT INTO attendance (user_id, punch_in, date) VALUES (?, ?, ?)",
            [$userId, serverTime(), serverDate()]
        );

        jsonResponse(['success' => true, 'id' => $id, 'time' => serverTime()]);
    } elseif ($action === 'punch_out') {
        $attendance = db()->fetch("SELECT * FROM attendance WHERE user_id = ? AND date = ?", [$userId, serverDate()]);

        if (!$attendance) {
            jsonResponse(['success' => false, 'error' => 'Not punched in today'], 400);
        }

        if ($attendance['punch_out']) {
            jsonResponse(['success' => false, 'error' => 'Already punched out'], 400);
        }

        db()->update(
            "UPDATE attendance SET punch_out = ? WHERE id = ?",
            [serverTime(), $attendance['id']]
        );

        jsonResponse(['success' => true, 'time' => serverTime()]);
    } else {
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
    }
} elseif ($method === 'GET') {
    $userId = $_GET['user_id'] ?? '';
    $month = $_GET['month'] ?? date('Y-m');

    if (!$userId) {
        jsonResponse(['success' => false, 'error' => 'User ID required'], 400);
    }

    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));

    $records = db()->fetchAll(
        "SELECT * FROM attendance WHERE user_id = ? AND date BETWEEN ? AND ? ORDER BY date DESC",
        [$userId, $startDate, $endDate]
    );

    jsonResponse(['success' => true, 'data' => $records]);
} else {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}
?>