<?php
/**
 * Language API
 * Handles language preferences, move requests, and conflict resolution
 */

ob_start();
require_once __DIR__ . '/../config/config.php';
ob_end_clean();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $action = $input['action'] ?? '';

    if ($action === 'save_languages') {
        // Save user's known languages
        $userId = $input['user_id'] ?? 0;
        $languageIds = $input['language_ids'] ?? [];

        if (!$userId) {
            jsonResponse(['success' => false, 'error' => 'User ID required'], 400);
        }

        // Delete existing
        db()->delete("DELETE FROM user_languages WHERE user_id = ?", [$userId]);

        // Insert new
        $stmt = db()->getConnection()->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
        foreach ($languageIds as $langId) {
            $stmt->execute([$userId, $langId]);
        }

        jsonResponse(['success' => true, 'count' => count($languageIds)]);

    } elseif ($action === 'create_move_request') {
        // Employee creates a language conflict move request
        $contactId = $input['contact_id'] ?? 0;
        $requestedBy = $input['requested_by'] ?? 0;
        $targetLanguageId = $input['target_language_id'] ?? 0;
        $notes = sanitize($input['notes'] ?? '');

        if (!$contactId || !$requestedBy || !$targetLanguageId) {
            jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
        }

        // Check if a pending request already exists for this contact
        $existing = db()->fetch(
            "SELECT id FROM language_move_requests WHERE contact_id = ? AND status = 'pending'",
            [$contactId]
        );
        if ($existing) {
            jsonResponse(['success' => false, 'error' => 'A pending move request already exists for this contact'], 400);
        }

        $id = db()->insert(
            "INSERT INTO language_move_requests (contact_id, requested_by, target_language_id, notes) VALUES (?, ?, ?, ?)",
            [$contactId, $requestedBy, $targetLanguageId, $notes]
        );

        jsonResponse(['success' => true, 'id' => $id]);

    } elseif ($action === 'approve_request') {
        // Admin/TL approves a move request
        $requestId = $input['request_id'] ?? 0;
        $assignTo = $input['assign_to'] ?? null; // null = auto assign
        $approvedBy = $input['approved_by'] ?? 0;

        if (!$requestId || !$approvedBy) {
            jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
        }

        // Get the request details
        $request = db()->fetch("SELECT * FROM language_move_requests WHERE id = ?", [$requestId]);
        if (!$request) {
            jsonResponse(['success' => false, 'error' => 'Request not found'], 404);
        }

        if ($request['status'] !== 'pending') {
            jsonResponse(['success' => false, 'error' => 'Request already processed'], 400);
        }

        // Auto-assign: find a random employee who speaks the target language
        if (!$assignTo) {
            // Don't exclude the requester — they may be the only (or a valid) speaker
            $speakers = db()->fetchAll(
                "SELECT u.id FROM users u
                 JOIN user_languages ul ON u.id = ul.user_id
                 WHERE ul.language_id = ? AND u.is_active = 1
                 ORDER BY RANDOM() LIMIT 1",
                [$request['target_language_id']]
            );

            if (empty($speakers)) {
                jsonResponse(['success' => false, 'error' => 'No available employee speaks this language'], 400);
            }
            $assignTo = $speakers[0]['id'];
        }

        // Update the request
        db()->update(
            "UPDATE language_move_requests SET status = 'approved', assigned_to = ?, approved_by = ?, resolved_at = ? WHERE id = ?",
            [$assignTo, $approvedBy, serverTime(), $requestId]
        );

        // Reassign the contact
        db()->update(
            "UPDATE contacts SET assigned_to = ?, is_called = 0 WHERE id = ?",
            [$assignTo, $request['contact_id']]
        );

        $assignedUser = db()->fetch("SELECT name FROM users WHERE id = ?", [$assignTo]);

        jsonResponse(['success' => true, 'assigned_to' => $assignedUser['name'] ?? 'Unknown']);

    } elseif ($action === 'reject_request') {
        $requestId = $input['request_id'] ?? 0;
        $approvedBy = $input['approved_by'] ?? 0;

        if (!$requestId || !$approvedBy) {
            jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
        }

        db()->update(
            "UPDATE language_move_requests SET status = 'rejected', approved_by = ?, resolved_at = ? WHERE id = ?",
            [$approvedBy, serverTime(), $requestId]
        );

        jsonResponse(['success' => true]);

    } elseif ($action === 'bulk_approve') {
        // Bulk approve multiple requests and assign to one employee
        $requestIds = $input['request_ids'] ?? [];
        $assignTo = $input['assign_to'] ?? 0;
        $approvedBy = $input['approved_by'] ?? 0;

        if (empty($requestIds) || !$assignTo || !$approvedBy) {
            jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
        }

        $count = 0;
        foreach ($requestIds as $reqId) {
            $request = db()->fetch("SELECT * FROM language_move_requests WHERE id = ? AND status = 'pending'", [$reqId]);
            if ($request) {
                db()->update(
                    "UPDATE language_move_requests SET status = 'approved', assigned_to = ?, approved_by = ?, resolved_at = ? WHERE id = ?",
                    [$assignTo, $approvedBy, serverTime(), $reqId]
                );
                db()->update(
                    "UPDATE contacts SET assigned_to = ?, is_called = 0 WHERE id = ?",
                    [$assignTo, $request['contact_id']]
                );
                $count++;
            }
        }

        jsonResponse(['success' => true, 'approved_count' => $count]);

    } elseif ($action === 'get_language_speakers') {
        // Get employees who speak a given language
        $languageId = $input['language_id'] ?? 0;

        if (!$languageId) {
            jsonResponse(['success' => false, 'error' => 'Language ID required'], 400);
        }

        $speakers = db()->fetchAll(
            "SELECT u.id, u.name, u.role FROM users u 
             JOIN user_languages ul ON u.id = ul.user_id 
             WHERE ul.language_id = ? AND u.is_active = 1 
             ORDER BY u.name",
            [$languageId]
        );

        jsonResponse(['success' => true, 'data' => $speakers]);

    } elseif ($action === 'bulk_auto_assign') {
        // Auto-assign all pending requests to random speakers
        $approvedBy = $input['approved_by'] ?? 0;
        $teamleadId = $input['teamlead_id'] ?? null; // null = admin (all), set = teamlead (team only)

        if (!$approvedBy) {
            jsonResponse(['success' => false, 'error' => 'Missing approved_by'], 400);
        }

        // Get pending requests (filtered by team if teamlead)
        if ($teamleadId) {
            $pending = db()->fetchAll(
                "SELECT lmr.* FROM language_move_requests lmr
                 JOIN users u ON lmr.requested_by = u.id
                 WHERE lmr.status = 'pending' AND u.teamlead_id = ?",
                [$teamleadId]
            );
        } else {
            $pending = db()->fetchAll("SELECT * FROM language_move_requests WHERE status = 'pending'");
        }

        $assigned = 0;
        $skipped = 0;

        foreach ($pending as $req) {
            // Find a random speaker of the target language
            $speakers = db()->fetchAll(
                "SELECT u.id FROM users u
                 JOIN user_languages ul ON u.id = ul.user_id
                 WHERE ul.language_id = ? AND u.is_active = 1
                 ORDER BY RANDOM() LIMIT 1",
                [$req['target_language_id']]
            );

            if (!empty($speakers)) {
                $assignTo = $speakers[0]['id'];
                db()->update(
                    "UPDATE language_move_requests SET status = 'approved', assigned_to = ?, approved_by = ?, resolved_at = ? WHERE id = ?",
                    [$assignTo, $approvedBy, serverTime(), $req['id']]
                );
                db()->update(
                    "UPDATE contacts SET assigned_to = ?, is_called = 0 WHERE id = ?",
                    [$assignTo, $req['contact_id']]
                );
                $assigned++;
            } else {
                $skipped++;
            }
        }

        jsonResponse([
            'success' => true,
            'assigned' => $assigned,
            'skipped' => $skipped,
            'total' => count($pending)
        ]);

    } else {
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
    }
} else {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}
?>
