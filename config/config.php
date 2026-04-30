<?php
/**
 * Application Configuration and Helper Functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

// Application settings
define('APP_NAME', 'Obsiguard CRM');

// APP_URL: '/crm' on MAMP local, '' (empty) on production
$_host = $_SERVER['HTTP_HOST'] ?? '';
define('APP_URL', (str_contains($_host, 'localhost') || str_contains($_host, '127.0.0.1') || str_contains($_host, '.local')) ? '/crm' : '');

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

/**
 * Get current user data
 */
function currentUser()
{
    if (!isLoggedIn())
        return null;
    return db()->fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

/**
 * Get current user role
 */
function userRole()
{
    return $_SESSION['user_role'] ?? null;
}

/**
 * Check if current user is admin
 */
function isAdmin()
{
    return userRole() === 'admin' || userRole() === 'super_admin';
}

/**
 * Check if current user is super admin
 */
function isSuperAdmin()
{
    return userRole() === 'super_admin';
}

/**
 * Check if current user is team lead
 */
function isTeamLead()
{
    return userRole() === 'teamlead';
}

/**
 * Check if current user is employee
 */
function isEmployee()
{
    return userRole() === 'employee';
}

/**
 * Require authentication
 */
function requireAuth()
{
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/**
 * Require admin role
 */
function requireAdmin()
{
    requireAuth();
    if (!isAdmin()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/**
 * Require super admin role
 */
function requireSuperAdmin()
{
    requireAuth();
    if (!isSuperAdmin()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/**
 * Require team lead role
 */
function requireTeamLead()
{
    requireAuth();
    if (!isTeamLead() && !isAdmin()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/**
 * Require employee role
 */
function requireEmployee()
{
    requireAuth();
    if (!isEmployee() && !isTeamLead() && !isAdmin()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/**
 * Redirect based on user role
 */
function redirectByRole()
{
    $role = userRole();
    switch ($role) {
        case 'super_admin':
            header('Location: ' . APP_URL . '/superadmin/index.php');
            break;
        case 'admin':
            header('Location: ' . APP_URL . '/admin/index.php');
            break;
        case 'teamlead':
            header('Location: ' . APP_URL . '/teamlead/index.php');
            break;
        case 'employee':
            header('Location: ' . APP_URL . '/employee/index.php');
            break;
        default:
            header('Location: ' . APP_URL . '/login.php');
    }
    exit;
}

/**
 * Flash message helper
 */
function setFlash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash($type = null)
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        if ($type && $flash['type'] !== $type) {
            return null;
        }
        unset($_SESSION['flash']);
        return $flash['message'];
    }
    return null;
}

/**
 * Sanitize input
 */
function sanitize($input)
{
    if (!is_string($input)) {
        return '';
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Format date
 */
function formatDate($date, $format = 'M d, Y')
{
    return date($format, strtotime($date));
}

/**
 * Format time
 */
function formatTime($time)
{
    return date('h:i A', strtotime($time));
}

/**
 * JSON response helper
 */
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get server date/time (for attendance)
 */
function serverDate()
{
    return date('Y-m-d');
}

function serverTime()
{
    return date('Y-m-d H:i:s');
}

/**
 * Parse CSV file
 */
function parseCSV($filepath)
{
    $rows = [];
    if (($handle = fopen($filepath, "r")) !== FALSE) {
        $header = fgetcsv($handle);
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) >= 2) {
                $rows[] = [
                    'name' => trim($data[0]),
                    'phone' => trim($data[1])
                ];
            }
        }
        fclose($handle);
    }
    return $rows;
}

/**
 * Get all employees under a team lead
 */
function getTeamMembers($teamleadId)
{
    return db()->fetchAll("SELECT * FROM users WHERE teamlead_id = ? AND role = 'employee' AND is_active = 1", [$teamleadId]);
}

/**
 * Get all team leads
 */
function getTeamLeads()
{
    return db()->fetchAll("SELECT * FROM users WHERE role = 'teamlead' AND is_active = 1 ORDER BY name");
}

/**
 * Get all employees
 */
function getEmployees()
{
    return db()->fetchAll("SELECT * FROM users WHERE role = 'employee' AND is_active = 1 ORDER BY name");
}

/**
 * Get all call statuses
 */
function getCallStatuses()
{
    return db()->fetchAll("SELECT * FROM call_statuses ORDER BY sort_order");
}

/**
 * Get all available languages
 */
function getLanguages()
{
    return db()->fetchAll("SELECT * FROM languages ORDER BY name");
}

/**
 * Get languages known by a user
 */
function getUserLanguages($userId)
{
    return db()->fetchAll("SELECT l.* FROM languages l JOIN user_languages ul ON l.id = ul.language_id WHERE ul.user_id = ? ORDER BY l.name", [$userId]);
}

/**
 * Get all WhatsApp templates
 */
function getWhatsAppTemplates()
{
    return db()->fetchAll("SELECT * FROM whatsapp_templates ORDER BY is_default DESC, name");
}

/**
 * Get pending language move requests count
 */
function getPendingMoveRequestsCount($teamleadId = null)
{
    if ($teamleadId) {
        return db()->fetch("SELECT COUNT(*) as count FROM language_move_requests lmr JOIN users u ON lmr.requested_by = u.id WHERE lmr.status = 'pending' AND u.teamlead_id = ?", [$teamleadId])['count'];
    }
    return db()->fetch("SELECT COUNT(*) as count FROM language_move_requests WHERE status = 'pending'")['count'];
}

/**
 * Get a single app setting by key
 */
function getSetting($key, $default = null)
{
    $row = db()->fetch("SELECT setting_value FROM app_settings WHERE setting_key = ?", [$key]);
    return $row ? $row['setting_value'] : $default;
}

/**
 * Save an app setting
 */
function setSetting($key, $value)
{
    db()->insert(
        "INSERT OR REPLACE INTO app_settings (setting_key, setting_value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)",
        [$key, $value]
    );
}

/**
 * Apply a plugin field mapping template, replacing {field} placeholders with row data
 */
function applyMapping($template, array $row): string
{
    return preg_replace_callback('/\{([^}]+)\}/', function ($m) use ($row) {
        $key = $m[1];
        return isset($row[$key]) ? trim((string)$row[$key]) : '';
    }, $template);
}

/**
 * Get a feature flag value (1 = enabled, 0 = disabled)
 */
function getFeatureFlag(string $key, int $default = 1): int
{
    $row = db()->fetch("SELECT is_enabled FROM feature_flags WHERE flag_key = ?", [$key]);
    return $row !== false ? (int)$row['is_enabled'] : $default;
}

/**
 * Get all feature flags as associative array key => is_enabled
 */
function getAllFeatureFlags(): array
{
    $rows = db()->fetchAll("SELECT flag_key, is_enabled FROM feature_flags");
    $result = [];
    foreach ($rows as $r) {
        $result[$r['flag_key']] = (int)$r['is_enabled'];
    }
    return $result;
}

/**
 * Send an email via NexoMailer
 * Returns true on success, false on failure
 */
function sendNexoEmail(string $to, string $subject, string $html): bool
{
    $apiKey = getSetting('nexomailer_api_key', '');
    if (empty($apiKey) || getSetting('nexomailer_enabled', '0') !== '1') {
        return false;
    }

    $payload = json_encode([
        'to'       => $to,
        'subject'  => $subject,
        'html'     => $html,
        'app_name' => APP_NAME,
    ]);

    $ch = curl_init('https://nexomail.logiclaunch.in/api/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: ' . $apiKey,
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        return false;
    }

    $data = json_decode($response, true);
    return !empty($data['success']) || !empty($data['queued']);
}

/**
 * Get all email templates
 */
function getEmailTemplates()
{
    return db()->fetchAll("SELECT * FROM email_templates WHERE is_active = 1 ORDER BY name");
}

// ─── Notification Helpers ─────────────────────────────────────────────────────

/**
 * Send an automated event notification if that event is enabled in settings.
 * Events: new_leads, sync_complete
 */
function sendNotification(string $event, array $data = []): bool
{
    if (getSetting('nexomailer_enabled', '0') !== '1') return false;
    if (getSetting('notif_' . $event, '0') !== '1') return false;
    $to = trim(getSetting('notif_recipient_email', ''));
    if (empty($to)) return false;

    [$subject, $bodyHtml] = _buildNotifContent($event, $data);
    if (empty($subject)) return false;

    return sendNexoEmail($to, $subject, _notifWrap($subject, $bodyHtml));
}

/** Build subject + inner HTML for each event type */
function _buildNotifContent(string $event, array $data): array
{
    $today   = date('d M Y');
    $appName = APP_NAME;

    switch ($event) {
        case 'new_leads':
            $count  = intval($data['count'] ?? 0);
            $source = htmlspecialchars($data['source'] ?? 'Import');
            $date   = htmlspecialchars($data['date'] ?? $today);
            return [
                "[$appName] {$count} New Lead(s) Imported — {$date}",
                '<p style="font-size:15px;color:#374151;margin:0 0 8px">New contacts have been imported into the CRM.</p>' .
                _notifTable([
                    'Source'    => $source,
                    'New Leads' => "<strong style='color:#16a34a'>{$count}</strong>",
                    'Date'      => $date,
                    'Time'      => date('h:i A'),
                ]),
            ];

        case 'sync_complete':
            $count   = intval($data['count'] ?? 0);
            $plugin  = htmlspecialchars($data['plugin_name'] ?? 'Plugin');
            $errors  = intval($data['errors'] ?? 0);
            $errHtml = $errors ? "<strong style='color:#dc2626'>{$errors}</strong>" : '0';
            return [
                "[$appName] Sync Complete — {$count} lead(s) from {$plugin}",
                '<p style="font-size:15px;color:#374151;margin:0 0 8px">A plugin sync has finished.</p>' .
                _notifTable([
                    'Plugin'    => $plugin,
                    'New Leads' => "<strong style='color:#16a34a'>{$count}</strong>",
                    'Errors'    => $errHtml,
                    'Time'      => date('h:i A'),
                ]),
            ];

        default:
            return ['', ''];
    }
}

/** Render a two-column key→value table for email bodies */
function _notifTable(array $rows): string
{
    $html = "<table style='width:100%;border-collapse:collapse;font-size:14px;margin-top:16px'>";
    $keys = array_keys($rows);
    $last = end($keys);
    foreach ($rows as $label => $value) {
        $border = ($label === $last) ? '' : 'border-bottom:1px solid #f0f0f0;';
        $html  .= "<tr>
            <td style='padding:10px 0;color:#6b7280;{$border}'>{$label}</td>
            <td style='padding:10px 0;font-weight:600;text-align:right;{$border}'>{$value}</td>
        </tr>";
    }
    return $html . '</table>';
}

/** Wrap notification body HTML in a full branded email layout */
function _notifWrap(string $title, string $body): string
{
    $ts = date('d M Y, h:i A');
    return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px">
  <tr><td align="center">
    <table width="580" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.08)">
      <tr><td style="background:#111;padding:24px 32px">
        <p style="color:#fff;font-size:22px;font-weight:700;margin:0;letter-spacing:-0.5px">Obsiguard CRM</p>
        <p style="color:#888;font-size:13px;margin:4px 0 0">Automated Notification</p>
      </td></tr>
      <tr><td style="padding:32px">
        <p style="font-size:18px;font-weight:700;color:#111;margin:0 0 12px">$title</p>
        $body
      </td></tr>
      <tr><td style="background:#fafafa;padding:16px 32px;border-top:1px solid #f0f0f0">
        <p style="color:#aaa;font-size:12px;margin:0;text-align:center">$ts &middot; Obsiguard CRM &middot; Automated notification. Do not reply.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;
}
?>