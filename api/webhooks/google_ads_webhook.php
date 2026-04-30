<?php
/**
 * Google Ads Lead Form Webhook Receiver
 * Google Ads POSTs lead data to this URL when someone submits a lead form.
 * URL format: /api/webhooks/google_ads_webhook.php?token=<plugin_webhook_token>
 */
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

$token = trim($_GET['token'] ?? '');
if (!$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing token']);
    exit;
}

// Find plugin by webhook token
$plugin = db()->fetch("SELECT * FROM plugins WHERE webhook_token = ? AND type = 'google_ads' AND is_active = 1", [$token]);
if (!$plugin) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

// Parse incoming payload
$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!$payload) {
    // Google Ads may also POST form-encoded data
    $payload = $_POST;
}

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty payload']);
    exit;
}

// Build associative array from Google Ads webhook structure
// Google Ads Lead Form payload structure:
// { "lead_id": "...", "user_column_data": [{"column_name": "FULL_NAME", "string_value": "..."}] }
$assoc = [];

if (isset($payload['user_column_data']) && is_array($payload['user_column_data'])) {
    // Native Google Ads Lead Form Extensions format
    foreach ($payload['user_column_data'] as $field) {
        $key         = strtolower($field['column_name'] ?? '');
        $assoc[$key] = $field['string_value'] ?? $field['integer_value'] ?? '';
    }
    $externalId = $payload['lead_id'] ?? ('webhook_' . time() . '_' . rand(1000, 9999));
} elseif (isset($payload['leadId'])) {
    // Alternative camelCase format
    foreach ($payload['fieldData'] ?? [] as $field) {
        $assoc[strtolower($field['columnName'] ?? '')] = $field['value'] ?? '';
    }
    $externalId = $payload['leadId'];
} else {
    // Flat key-value format (pass-through)
    foreach ($payload as $k => $v) {
        $assoc[strtolower($k)] = is_string($v) ? $v : (string)$v;
    }
    $externalId = 'webhook_' . md5($rawBody) . '_' . time();
}

// Get mappings
$mappings = db()->fetchAll("SELECT * FROM plugin_mappings WHERE plugin_id = ?", [$plugin['id']]);
if (empty($mappings)) {
    // No mappings — try sensible defaults
    $mappings = [
        ['target_field' => 'name',  'source_template' => '{full_name}'],
        ['target_field' => 'phone', 'source_template' => '{phone_number}'],
    ];
}

// Apply mappings
$lead = [];
foreach ($mappings as $m) {
    $value = applyMapping($m['source_template'], $assoc);
    $value = preg_replace('/\s+/', ' ', trim($value));
    if ($value !== '') {
        $lead[$m['target_field']] = $value;
    }
}

if (empty($lead['phone'])) {
    http_response_code(422);
    echo json_encode(['error' => 'No phone number found in payload', 'received' => $assoc]);
    exit;
}

// Dedup check
$existing = db()->fetch(
    "SELECT id FROM plugin_lead_imports WHERE plugin_id = ? AND external_id = ?",
    [$plugin['id'], $externalId]
);

if ($existing) {
    http_response_code(200);
    echo json_encode(['status' => 'duplicate', 'message' => 'Lead already imported']);
    exit;
}

$name  = $lead['name']  ?? 'Unknown';
$phone = $lead['phone'];
$notes = $lead['notes'] ?? null;

// Check existing contact
$contact = db()->fetch("SELECT id FROM contacts WHERE phone = ?", [$phone]);
if ($contact) {
    $contactId = $contact['id'];
} else {
    $contactId = db()->insert(
        "INSERT INTO contacts (name, phone, notes, import_date, created_at) VALUES (?, ?, ?, DATE('now'), CURRENT_TIMESTAMP)",
        [$name, $phone, $notes]
    );
}

db()->insert(
    "INSERT OR IGNORE INTO plugin_lead_imports (plugin_id, external_id, contact_id) VALUES (?, ?, ?)",
    [$plugin['id'], $externalId, $contactId]
);

db()->update("UPDATE plugins SET last_sync = CURRENT_TIMESTAMP WHERE id = ?", [$plugin['id']]);

http_response_code(200);
echo json_encode(['status' => 'ok', 'contact_id' => $contactId]);
