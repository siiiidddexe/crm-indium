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
define('APP_URL', '/crm');

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
    return userRole() === 'admin';
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
?>