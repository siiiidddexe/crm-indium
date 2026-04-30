<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();
header('Content-Type: application/json');

$spreadsheetId = trim($_GET['spreadsheet_id'] ?? '');
$range         = trim($_GET['range']          ?? 'Sheet1!A:Z');
$apiKey        = trim($_GET['api_key']        ?? '');

if (!$spreadsheetId) {
    echo json_encode(['success' => false, 'error' => 'spreadsheet_id is required.']);
    exit;
}

// Extract just the ID if a full URL was pasted
if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/', $spreadsheetId, $m)) {
    $spreadsheetId = $m[1];
}

// Extract sheet name from range
if (preg_match('/^([^!]+)!/', $range, $sm)) {
    $sheetName = $sm[1];
} else {
    $sheetName = 'Sheet1';
}

function fetchUrl(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'ObsiguardCRM/1.0',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['body' => $body, 'code' => $code, 'err' => $err];
}

// --- Strategy 1: Google Sheets API v4 (requires API key) ---
if ($apiKey !== '') {
    $headerRange = urlencode($sheetName . '!A1:ZZ1');
    $url         = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$headerRange}?key=" . urlencode($apiKey);

    $res  = fetchUrl($url);
    $json = json_decode($res['body'], true);

    if ($res['err']) {
        echo json_encode(['success' => false, 'error' => 'cURL error: ' . $res['err']]);
        exit;
    }

    if ($res['code'] === 200) {
        $rows = $json['values'] ?? [];
        if (!empty($rows[0])) {
            $headers = array_values(array_filter(array_map('trim', $rows[0]), fn($h) => $h !== ''));
            echo json_encode(['success' => true, 'headers' => $headers]);
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'The sheet appears to be empty or the range has no data.']);
        exit;
    }

    $msg = $json['error']['message'] ?? ('HTTP ' . $res['code']);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// --- Strategy 2: Public CSV export (no API key needed, works for "anyone with link" sheets) ---
$csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv&sheet=" . urlencode($sheetName);

$res = fetchUrl($csvUrl);

if ($res['err']) {
    echo json_encode(['success' => false, 'error' => 'cURL error: ' . $res['err']]);
    exit;
}

if ($res['code'] === 200 && !empty($res['body'])) {
    // Parse first line of CSV as headers
    $lines = explode("\n", str_replace("\r\n", "\n", $res['body']));
    $firstLine = $lines[0] ?? '';
    if ($firstLine === '') {
        echo json_encode(['success' => false, 'error' => 'The sheet appears to be empty.']);
        exit;
    }
    // Use str_getcsv to handle quoted values
    $headers = str_getcsv($firstLine);
    $headers = array_values(array_filter(array_map('trim', $headers), fn($h) => $h !== ''));
    if (empty($headers)) {
        echo json_encode(['success' => false, 'error' => 'No headers found in the first row.']);
        exit;
    }
    echo json_encode(['success' => true, 'headers' => $headers]);
    exit;
}

if ($res['code'] === 302 || $res['code'] === 401 || $res['code'] === 403) {
    echo json_encode(['success' => false, 'error' => 'Sheet is not publicly accessible. Set sharing to "Anyone with the link → Viewer", or provide a Google API Key.']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Could not fetch sheet data (HTTP ' . $res['code'] . '). Make sure the sheet is public or provide an API key.']);
