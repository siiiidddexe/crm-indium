<?php
/**
 * Plugin Sync API
 * Fetches leads from configured plugins and imports them as contacts
 * Supports: google_sheets (pull), meta_ads (pull), google_ads (webhook-based, manual test)
 */
require_once __DIR__ . '/../config/config.php';
requireAuth();

if (!isAdmin()) {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

$data     = json_decode(file_get_contents('php://input'), true) ?? [];
$pluginId = intval($data['plugin_id'] ?? 0);

if (!$pluginId) {
    jsonResponse(['error' => 'Missing plugin_id'], 400);
}

$plugin = db()->fetch("SELECT * FROM plugins WHERE id = ? AND is_active = 1", [$pluginId]);
if (!$plugin) {
    jsonResponse(['error' => 'Plugin not found or inactive'], 404);
}

$config   = json_decode($plugin['config'], true) ?: [];
$mappings = db()->fetchAll("SELECT * FROM plugin_mappings WHERE plugin_id = ?", [$pluginId]);

if (empty($mappings)) {
    jsonResponse(['error' => 'No field mappings configured for this plugin'], 400);
}

$imported = 0;
$errors   = [];

// ─── Route to correct fetcher ───────────────────────────────────────────────
switch ($plugin['type']) {
    case 'google_sheets':
        [$imported, $errors] = syncGoogleSheets($plugin, $config, $mappings);
        break;
    case 'meta_ads':
        [$imported, $errors] = syncMetaAds($plugin, $config, $mappings);
        break;
    case 'google_ads':
        // Google Ads uses webhook push — no pull available. Inform admin.
        jsonResponse([
            'success' => false,
            'error'   => 'Google Ads uses webhook mode. Leads are pushed automatically. No manual sync needed.',
        ]);
}

db()->update("UPDATE plugins SET last_sync = CURRENT_TIMESTAMP WHERE id = ?", [$pluginId]);

// Fire notification
sendNotification('sync_complete', [
    'count'       => $imported,
    'plugin_name' => $plugin['name'],
    'errors'      => count($errors),
]);

jsonResponse([
    'success'  => true,
    'imported' => $imported,
    'errors'   => $errors,
]);

// ─── Google Sheets ────────────────────────────────────────────────────────────
function syncGoogleSheets(array $plugin, array $config, array $mappings): array
{
    $spreadsheetId = trim($config['spreadsheet_id'] ?? '');
    $range         = trim($config['range'] ?? 'Sheet1!A:Z');
    $apiKey        = trim($config['api_key'] ?? '');

    // Support full URL → extract ID
    if (preg_match('/spreadsheets\/d\/([a-zA-Z0-9_-]+)/', $spreadsheetId, $m)) {
        $spreadsheetId = $m[1];
    }

    if (!$spreadsheetId) {
        return [0, ['Spreadsheet ID is not configured']];
    }

    // Extract sheet name for CSV fallback
    $sheetName = 'Sheet1';
    if (preg_match('/^([^!]+)!/', $range, $snm)) {
        $sheetName = $snm[1];
    }

    if ($apiKey) {
        // Use Sheets API v4 with key
        $url      = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . urlencode($range) . '?key=' . urlencode($apiKey);
        $response = httpGet($url);
        if (!$response['ok']) {
            return [0, ['Google Sheets API error: ' . $response['body']]];
        }
        $body   = json_decode($response['body'], true);
        $values = $body['values'] ?? [];
    } else {
        // Fall back to public CSV export (works for "Anyone with link" sheets without an API key)
        $csvUrl   = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv&sheet=" . urlencode($sheetName);
        $response = httpGet($csvUrl);
        if (!$response['ok'] || empty($response['body'])) {
            return [0, ['Could not fetch sheet. Make sure it is shared publicly ("Anyone with the link → Viewer") or provide a Google API Key.']];
        }
        // Parse CSV into $values array
        $lines  = explode("\n", str_replace("\r\n", "\n", trim($response['body'])));
        $values = array_map('str_getcsv', $lines);
    }

    if (count($values) < 2) {
        return [0, ['Sheet has no data rows (first row is treated as headers)']];
    }

    $headers = array_map('trim', $values[0]);
    $rows    = array_slice($values, 1);
    $imported = 0;

    foreach ($rows as $rowIndex => $row) {
        // Build associative row using headers
        $assoc = [];
        foreach ($headers as $hi => $header) {
            $assoc[$header] = $row[$hi] ?? '';
        }

        // Apply mappings
        $lead = applyMappings($mappings, $assoc);
        if (empty($lead['phone'])) {
            continue; // phone is required
        }

        // Use hash of row index + sheet ID as external ID
        $externalId = 'row_' . ($rowIndex + 2) . '_' . substr(md5($spreadsheetId), 0, 8);
        $imported  += upsertLead($plugin['id'], $externalId, $lead);
    }

    return [$imported, []];
}

// ─── Meta Lead Ads ────────────────────────────────────────────────────────────
function syncMetaAds(array $plugin, array $config, array $mappings): array
{
    $accessToken = trim($config['access_token'] ?? '');
    $formId      = trim($config['form_id'] ?? '');
    $apiVersion  = trim($config['api_version'] ?? 'v19.0');

    if (!$accessToken || !$formId) {
        return [0, ['Meta Ads access_token and form_id are required']];
    }

    $url  = "https://graph.facebook.com/{$apiVersion}/{$formId}/leads";
    $url .= '?access_token=' . urlencode($accessToken);
    $url .= '&fields=id,created_time,field_data';
    $url .= '&limit=100';

    $imported = 0;
    $errors   = [];

    do {
        $response = httpGet($url);
        if (!$response['ok']) {
            $errors[] = 'Meta API error: ' . $response['body'];
            break;
        }

        $body  = json_decode($response['body'], true);
        $leads = $body['data'] ?? [];

        foreach ($leads as $lead) {
            $externalId = $lead['id'];

            // Flatten field_data into associative array
            $assoc = [];
            foreach ($lead['field_data'] ?? [] as $field) {
                $assoc[$field['name']] = $field['values'][0] ?? '';
            }

            $mappedLead = applyMappings($mappings, $assoc);
            if (empty($mappedLead['phone'])) {
                continue;
            }

            $imported += upsertLead($plugin['id'], $externalId, $mappedLead);
        }

        $url = $body['paging']['next'] ?? null;
    } while ($url);

    return [$imported, $errors];
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function applyMappings(array $mappings, array $row): array
{
    $lead = [];
    foreach ($mappings as $m) {
        $value = applyMapping($m['source_template'], $row);
        $value = preg_replace('/\s+/', ' ', trim($value));
        if ($value !== '') {
            $lead[$m['target_field']] = $value;
        }
    }
    return $lead;
}

function upsertLead(int $pluginId, string $externalId, array $lead): int
{
    // Check if already imported
    $existing = db()->fetch(
        "SELECT id FROM plugin_lead_imports WHERE plugin_id = ? AND external_id = ?",
        [$pluginId, $externalId]
    );
    if ($existing) {
        return 0; // already imported
    }

    $name  = $lead['name']  ?? 'Unknown';
    $phone = $lead['phone'] ?? '';
    $email = $lead['email'] ?? null;
    $notes = $lead['notes'] ?? null;

    // Check if contact with same phone exists
    $contact = db()->fetch("SELECT id FROM contacts WHERE phone = ?", [$phone]);
    if ($contact) {
        $contactId = $contact['id'];
        // Update email/notes if newly available
        if ($email !== null) {
            db()->query("UPDATE contacts SET email = ? WHERE id = ? AND (email IS NULL OR email = '')", [$email, $contactId]);
        }
    } else {
        $contactId = db()->insert(
            "INSERT INTO contacts (name, phone, email, notes, import_date, created_at) VALUES (?, ?, ?, ?, DATE('now'), CURRENT_TIMESTAMP)",
            [$name, $phone, $email, $notes]
        );
    }

    // Record import
    db()->insert(
        "INSERT OR IGNORE INTO plugin_lead_imports (plugin_id, external_id, contact_id) VALUES (?, ?, ?)",
        [$pluginId, $externalId, $contactId]
    );

    return 1;
}

function httpGet(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'ObsiguardCRM/1.0',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['ok' => false, 'body' => $err];
    }
    return ['ok' => ($code >= 200 && $code < 300), 'body' => $body];
}
