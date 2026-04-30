<?php
/**
 * End of Day Report
 * Triggered by: cron (?key=SECRET) or admin manually from Settings
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Auth: valid cron key OR active admin session
$cronKey = trim($_GET['key'] ?? '');
$storedKey = getSetting('notif_eod_cron_key', '');

$isAuthorised = false;
if ($cronKey && $storedKey && hash_equals($storedKey, $cronKey)) {
    $isAuthorised = true;
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
    if (!empty($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
        $isAuthorised = true;
    }
} elseif (!empty($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    $isAuthorised = true;
}

if (!$isAuthorised) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorised']);
    exit;
}

if (getSetting('nexomailer_enabled', '0') !== '1') {
    echo json_encode(['success' => false, 'error' => 'NexoMailer is not enabled.']);
    exit;
}

$to = trim(getSetting('notif_recipient_email', ''));
if (empty($to)) {
    echo json_encode(['success' => false, 'error' => 'No notification recipient email set in Settings.']);
    exit;
}

// ─── Gather Report Data ───────────────────────────────────────────────────────

$today     = date('Y-m-d');
$todayFmt  = date('d M Y');
$appName   = APP_NAME;

// Contacts imported today
$importedToday = intval(db()->fetch(
    "SELECT COUNT(*) as c FROM contacts WHERE import_date = ?", [$today]
)['c'] ?? 0);

// Total contacts in system
$totalContacts = intval(db()->fetch("SELECT COUNT(*) as c FROM contacts")['c'] ?? 0);

// Unassigned (all time)
$unassigned = intval(db()->fetch(
    "SELECT COUNT(*) as c FROM contacts WHERE assigned_to IS NULL"
)['c'] ?? 0);

// Calls logged today
$callsToday = intval(db()->fetch(
    "SELECT COUNT(*) as c FROM call_logs WHERE DATE(call_time) = ?", [$today]
)['c'] ?? 0);

// Unique contacts called today
$contactsCalledToday = intval(db()->fetch(
    "SELECT COUNT(DISTINCT contact_id) as c FROM call_logs WHERE DATE(call_time) = ?", [$today]
)['c'] ?? 0);

// Top 5 staff by calls today
$topStaff = db()->fetchAll("
    SELECT u.name, u.role, COUNT(cl.id) as call_count
    FROM call_logs cl
    JOIN users u ON cl.user_id = u.id
    WHERE DATE(cl.call_time) = ?
    GROUP BY cl.user_id
    ORDER BY call_count DESC
    LIMIT 5
", [$today]);

// Call status distribution today
$statusBreakdown = db()->fetchAll("
    SELECT cs.name, cs.color, COUNT(cl.id) as cnt
    FROM call_logs cl
    LEFT JOIN call_statuses cs ON cl.status_id = cs.id
    WHERE DATE(cl.call_time) = ?
    GROUP BY cl.status_id
    ORDER BY cnt DESC
    LIMIT 8
", [$today]);

// Plugin syncs today
$syncedToday = intval(db()->fetch(
    "SELECT COUNT(*) as c FROM plugins WHERE DATE(last_sync) = ?", [$today]
)['c'] ?? 0);

$newLeadsFromPlugins = intval(db()->fetch(
    "SELECT COUNT(*) as c FROM plugin_lead_imports WHERE DATE(created_at) = ?", [$today]
)['c'] ?? 0);

// ─── Build HTML Email ─────────────────────────────────────────────────────────

function eodRow(string $label, string $value, string $color = '#374151'): string {
    return "<tr>
        <td style='padding:10px 12px;color:#6b7280;border-bottom:1px solid #f3f4f6;font-size:14px'>{$label}</td>
        <td style='padding:10px 12px;font-weight:700;text-align:right;border-bottom:1px solid #f3f4f6;font-size:14px;color:{$color}'>{$value}</td>
    </tr>";
}

function eodSection(string $title, string $icon, string $content): string {
    return "<div style='margin-bottom:24px'>
        <p style='font-size:13px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 8px'>{$icon} {$title}</p>
        <table width='100%' style='border-collapse:collapse;background:#f9fafb;border-radius:10px;overflow:hidden'>{$content}</table>
    </div>";
}

// Build stats rows
$statsHtml = eodRow('New Contacts Imported Today', number_format($importedToday), '#16a34a')
           . eodRow('Total Contacts in CRM', number_format($totalContacts))
           . eodRow('Unassigned Contacts', number_format($unassigned), $unassigned > 0 ? '#d97706' : '#16a34a')
           . eodRow('Calls Logged Today', number_format($callsToday), '#2563eb')
           . eodRow('Contacts Called Today', number_format($contactsCalledToday))
           . eodRow('Plugin Syncs Today', number_format($syncedToday))
           . eodRow('Leads from Plugins Today', number_format($newLeadsFromPlugins));

// Top performers
$topHtml = '';
if (!empty($topStaff)) {
    foreach ($topStaff as $i => $s) {
        $medal = $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : ''));
        $topHtml .= eodRow(
            "{$medal} " . htmlspecialchars($s['name']) . " <span style='color:#9ca3af;font-weight:400'>(" . ucfirst($s['role']) . ")</span>",
            $s['call_count'] . ' call(s)'
        );
    }
} else {
    $topHtml = "<tr><td colspan='2' style='padding:12px;color:#9ca3af;text-align:center;font-size:14px'>No calls logged today</td></tr>";
}

// Status breakdown
$sbHtml = '';
if (!empty($statusBreakdown)) {
    foreach ($statusBreakdown as $sb) {
        $name  = $sb['name'] ? htmlspecialchars($sb['name']) : 'No Status';
        $color = $sb['color'] ?? '#6b7280';
        $sbHtml .= "<tr>
            <td style='padding:10px 12px;border-bottom:1px solid #f3f4f6;font-size:14px'>
                <span style='display:inline-block;width:8px;height:8px;border-radius:50%;background:{$color};margin-right:6px'></span>{$name}
            </td>
            <td style='padding:10px 12px;font-weight:700;text-align:right;border-bottom:1px solid #f3f4f6;font-size:14px'>{$sb['cnt']}</td>
        </tr>";
    }
} else {
    $sbHtml = "<tr><td colspan='2' style='padding:12px;color:#9ca3af;text-align:center;font-size:14px'>No status data today</td></tr>";
}

$ts    = date('d M Y, h:i A');
$html  = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.08)">

      <!-- Header -->
      <tr><td style="background:#111;padding:28px 32px">
        <p style="color:#fff;font-size:22px;font-weight:700;margin:0;letter-spacing:-0.5px">Obsiguard CRM</p>
        <p style="color:#888;font-size:13px;margin:4px 0 0">End of Day Report</p>
      </td></tr>

      <!-- Date banner -->
      <tr><td style="background:#f0fdf4;padding:16px 32px;border-bottom:1px solid #dcfce7">
        <p style="color:#16a34a;font-size:16px;font-weight:700;margin:0">📅 {$todayFmt}</p>
      </td></tr>

      <!-- Body -->
      <tr><td style="padding:32px">

        <!-- Overview stats -->
        {$this_stats}

        <!-- Top performers -->
        {$this_top}

        <!-- Status breakdown -->
        {$this_sb}

      </td></tr>

      <!-- Footer -->
      <tr><td style="background:#fafafa;padding:16px 32px;border-top:1px solid #f0f0f0">
        <p style="color:#aaa;font-size:12px;margin:0;text-align:center">{$ts} &middot; Obsiguard CRM &middot; Automated daily report.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;

$statsSection = eodSection('Daily Overview', '📊', $statsHtml);
$topSection   = eodSection('Top Callers Today', '🏆', $topHtml);
$sbSection    = eodSection('Call Status Breakdown', '📋', $sbHtml);

$html = str_replace(
    ['{$this_stats}', '{$this_top}', '{$this_sb}'],
    [$statsSection, $topSection, $sbSection],
    $html
);

// ─── Send ─────────────────────────────────────────────────────────────────────

$subject = "[{$appName}] End of Day Report — {$todayFmt}";
$sent    = sendNexoEmail($to, $subject, $html);

if ($sent) {
    echo json_encode(['success' => true, 'sent_to' => $to, 'date' => $todayFmt]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to send via NexoMailer. Check API key and that NexoMailer is enabled.']);
}
