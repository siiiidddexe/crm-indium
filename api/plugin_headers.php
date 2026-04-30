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

// Use only the sheet name + first row for the header fetch (A1:ZZ1)
// Strip any existing row suffix and force row 1 only
$headerRange = preg_replace('/![A-Z]+[0-9]*:[A-Z]+[0-9]*/i', '!A1:ZZ1', $spreadsheetId);
// Build the range from the user's range: extract sheet name
if (preg_match('/^([^!]+)!/', $range, $sm)) {
    $sheetName   = $sm[1];
    $headerRange = urlencode($sheetName . '!A1:ZZ1');
} else {
    $headerRange = urlencode('Sheet1!A1:ZZ1');
}

$url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$headerRange}";
if ($apiKey !== '') {
    $url .= '?key=' . urlencode($apiKey);
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'ObsiguardCRM/1.0',
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['success' => false, 'error' => 'cURL error: ' . $err]);
    exit;
}

$json = json_decode($body, true);

if ($code !== 200) {
    $msg = $json['error']['message'] ?? ('HTTP ' . $code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$rows = $json['values'] ?? [];
if (empty($rows) || empty($rows[0])) {
    echo json_encode(['success' => false, 'error' => 'The sheet appears to be empty or the range has no data.']);
    exit;
}

$headers = array_map('trim', $rows[0]);
$headers = array_filter($headers, fn($h) => $h !== '');

echo json_encode(['success' => true, 'headers' => array_values($headers)]);
