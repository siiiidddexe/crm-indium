<?php
/**
 * Email API - Send emails via NexoMailer
 */
require_once __DIR__ . '/../config/config.php';
requireAuth();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ── GET: fetch a template ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_template') {
    $id  = intval($_GET['id'] ?? 0);
    $tpl = db()->fetch("SELECT * FROM email_templates WHERE id = ?", [$id]);
    if ($tpl) {
        jsonResponse(['template' => $tpl]);
    } else {
        jsonResponse(['error' => 'Template not found'], 404);
    }
}

// ── POST: send email ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'send') {
    $body       = json_decode(file_get_contents('php://input'), true) ?? [];
    $contactId  = intval($body['contact_id'] ?? 0);
    $templateId = intval($body['template_id'] ?? 0);
    $toEmail    = filter_var(trim($body['to'] ?? ''), FILTER_VALIDATE_EMAIL);
    $subject    = trim($body['subject'] ?? '');
    $htmlBody   = trim($body['html'] ?? '');

    if (!$toEmail) {
        jsonResponse(['success' => false, 'error' => 'Invalid or missing email address'], 400);
    }

    // Build HTML from template if template_id given
    if ($templateId) {
        $tpl = db()->fetch("SELECT * FROM email_templates WHERE id = ? AND is_active = 1", [$templateId]);
        if (!$tpl) {
            jsonResponse(['success' => false, 'error' => 'Template not found'], 404);
        }
        $subject  = $tpl['subject'];
        $htmlBody = $tpl['html_body'];
    }

    if (!$subject || !$htmlBody) {
        jsonResponse(['success' => false, 'error' => 'Subject and HTML body are required'], 400);
    }

    // Replace contact placeholders if contact_id given
    if ($contactId) {
        $contact = db()->fetch("SELECT * FROM contacts WHERE id = ?", [$contactId]);
        if ($contact) {
            $htmlBody = str_replace(
                ['{name}', '{phone}', '{email}'],
                [sanitize($contact['name']), sanitize($contact['phone']), sanitize($contact['email'] ?? '')],
                $htmlBody
            );
            $subject = str_replace(
                ['{name}', '{phone}', '{email}'],
                [$contact['name'], $contact['phone'], $contact['email'] ?? ''],
                $subject
            );
        }
    }

    if (!getSetting('nexomailer_enabled', '0') === '1') {
        jsonResponse(['success' => false, 'error' => 'NexoMailer is not enabled. Enable it in Settings.'], 400);
    }

    $ok = sendNexoEmail($toEmail, $subject, $htmlBody);

    if ($ok) {
        jsonResponse(['success' => true, 'message' => 'Email sent successfully!']);
    } else {
        jsonResponse(['success' => false, 'error' => 'Failed to send email. Check NexoMailer settings.'], 500);
    }
}

jsonResponse(['error' => 'Invalid request'], 400);
