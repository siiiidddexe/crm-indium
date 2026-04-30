<?php
/**
 * Contact Notes API
 * Actions: list, add
 */
require_once __DIR__ . '/../config/config.php';
requireAuth();

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? ($_GET['action'] ?? '');

if ($action === 'list') {
    $contactId = intval($data['contact_id'] ?? ($_GET['contact_id'] ?? 0));
    if (!$contactId) {
        jsonResponse(['error' => 'Missing contact_id'], 400);
    }

    $notes = db()->fetchAll("
        SELECT cn.id, cn.note, cn.created_at, u.name as author
        FROM contact_notes cn
        JOIN users u ON cn.user_id = u.id
        WHERE cn.contact_id = ?
        ORDER BY cn.created_at DESC
    ", [$contactId]);

    jsonResponse(['success' => true, 'notes' => $notes]);
}

if ($action === 'add') {
    $contactId = intval($data['contact_id'] ?? 0);
    $note      = trim($data['note'] ?? '');

    if (!$contactId || $note === '') {
        jsonResponse(['error' => 'Missing fields'], 400);
    }

    $id = db()->insert(
        "INSERT INTO contact_notes (contact_id, user_id, note) VALUES (?, ?, ?)",
        [$contactId, $_SESSION['user_id'], $note]
    );

    $row = db()->fetch("
        SELECT cn.id, cn.note, cn.created_at, u.name as author
        FROM contact_notes cn JOIN users u ON cn.user_id = u.id
        WHERE cn.id = ?
    ", [$id]);

    jsonResponse(['success' => true, 'note' => $row]);
}

jsonResponse(['error' => 'Unknown action'], 400);
