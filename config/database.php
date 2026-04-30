<?php
/**
 * Database Configuration and Connection
 * SQLite database with PDO
 */

class Database
{
    private static $instance = null;
    private $pdo;
    private $dbPath;

    private function __construct()
    {
        // Try app config dir first, fall back to writable temp dir on hosts
        // that don't allow writing to the app directory
        $appDir = __DIR__ . '/crm.db';
        if (is_writable(__DIR__) || file_exists($appDir)) {
            $this->dbPath = $appDir;
        } else {
            // Production: store in a persistent writable location outside webroot
            $dataDir = sys_get_temp_dir() . '/obsiguard_crm';
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            $this->dbPath = $dataDir . '/crm.db';
        }
        $this->connect();
        $this->initializeDatabase();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect()
    {
        try {
            $this->pdo = new PDO('sqlite:' . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    private function initializeDatabase()
    {
        $schema = "
        -- Users table
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            phone TEXT,
            role TEXT NOT NULL CHECK(role IN ('admin', 'teamlead', 'employee', 'super_admin')),
            teamlead_id INTEGER,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (teamlead_id) REFERENCES users(id)
        );

        -- Call statuses table
        CREATE TABLE IF NOT EXISTS call_statuses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            color TEXT DEFAULT '#6b7280',
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Contacts table
        CREATE TABLE IF NOT EXISTS contacts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            phone TEXT NOT NULL,
            assigned_to INTEGER,
            status_id INTEGER,
            import_date DATE,
            last_call_date DATETIME,
            notes TEXT,
            is_called INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (assigned_to) REFERENCES users(id),
            FOREIGN KEY (status_id) REFERENCES call_statuses(id)
        );

        -- Call logs table
        CREATE TABLE IF NOT EXISTS call_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            contact_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            status_id INTEGER,
            call_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            FOREIGN KEY (contact_id) REFERENCES contacts(id),
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (status_id) REFERENCES call_statuses(id)
        );

        -- Attendance table
        CREATE TABLE IF NOT EXISTS attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            punch_in DATETIME,
            punch_out DATETIME,
            break_start DATETIME,
            break_end DATETIME,
            notes TEXT,
            date DATE NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id),
            UNIQUE(user_id, date)
        );

        -- App settings table
        CREATE TABLE IF NOT EXISTS app_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_key TEXT UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Import batches table
        CREATE TABLE IF NOT EXISTS import_batches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT,
            total_records INTEGER,
            imported_by INTEGER,
            import_date DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (imported_by) REFERENCES users(id)
        );

        -- Available languages
        CREATE TABLE IF NOT EXISTS languages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- User known languages (many-to-many)
        CREATE TABLE IF NOT EXISTS user_languages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            language_id INTEGER NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (language_id) REFERENCES languages(id),
            UNIQUE(user_id, language_id)
        );

        -- Language conflict move requests
        CREATE TABLE IF NOT EXISTS language_move_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            contact_id INTEGER NOT NULL,
            requested_by INTEGER NOT NULL,
            target_language_id INTEGER NOT NULL,
            assigned_to INTEGER,
            status TEXT DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected')),
            approved_by INTEGER,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME,
            FOREIGN KEY (contact_id) REFERENCES contacts(id),
            FOREIGN KEY (requested_by) REFERENCES users(id),
            FOREIGN KEY (target_language_id) REFERENCES languages(id),
            FOREIGN KEY (assigned_to) REFERENCES users(id),
            FOREIGN KEY (approved_by) REFERENCES users(id)
        );

        -- WhatsApp message templates
        CREATE TABLE IF NOT EXISTS whatsapp_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            message TEXT NOT NULL,
            is_default INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Plugins table (Google Sheets, Google Ads, Meta Ads)
        CREATE TABLE IF NOT EXISTS plugins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL CHECK(type IN ('google_sheets','google_ads','meta_ads')),
            name TEXT NOT NULL,
            config TEXT NOT NULL DEFAULT '{}',
            webhook_token TEXT,
            is_active INTEGER DEFAULT 1,
            last_sync DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Plugin field mappings (source template → target CRM field)
        CREATE TABLE IF NOT EXISTS plugin_mappings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            plugin_id INTEGER NOT NULL,
            target_field TEXT NOT NULL,
            source_template TEXT NOT NULL,
            FOREIGN KEY (plugin_id) REFERENCES plugins(id) ON DELETE CASCADE
        );

        -- Plugin lead import tracking (deduplication)
        CREATE TABLE IF NOT EXISTS plugin_lead_imports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            plugin_id INTEGER NOT NULL,
            external_id TEXT NOT NULL,
            contact_id INTEGER,
            imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(plugin_id, external_id),
            FOREIGN KEY (plugin_id) REFERENCES plugins(id),
            FOREIGN KEY (contact_id) REFERENCES contacts(id)
        );

        -- Auto-assign rules (status-based periodic reassignment)
        CREATE TABLE IF NOT EXISTS auto_assign_rules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            status_id INTEGER NOT NULL,
            reassign_every_days INTEGER NOT NULL DEFAULT 7,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (status_id) REFERENCES call_statuses(id)
        );

        -- Contact call notes (timestamped, per-user notes)
        CREATE TABLE IF NOT EXISTS contact_notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            contact_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            note TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (contact_id) REFERENCES contacts(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        );

        -- Email templates (custom HTML, for NexoMailer)
        CREATE TABLE IF NOT EXISTS email_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            subject TEXT NOT NULL,
            html_body TEXT NOT NULL,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Feature flags (sidebar visibility, button toggles)
        CREATE TABLE IF NOT EXISTS feature_flags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            flag_key TEXT UNIQUE NOT NULL,
            is_enabled INTEGER DEFAULT 1,
            label TEXT NOT NULL,
            category TEXT DEFAULT 'general'
        );
        ";

        $this->pdo->exec($schema);

        // ── Run schema migrations ────────────────────────────────────────────────

        // 1. Migrate users table to support super_admin role
        $userDef = $this->pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
        if ($userDef && strpos($userDef['sql'], 'super_admin') === false) {
            $this->pdo->exec("PRAGMA foreign_keys=OFF");
            $this->pdo->exec("CREATE TABLE users_v2 (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                phone TEXT,
                role TEXT NOT NULL CHECK(role IN ('admin','teamlead','employee','super_admin')),
                teamlead_id INTEGER,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (teamlead_id) REFERENCES users_v2(id)
            )");
            $this->pdo->exec("INSERT INTO users_v2 SELECT * FROM users");
            $this->pdo->exec("DROP TABLE users");
            $this->pdo->exec("ALTER TABLE users_v2 RENAME TO users");
            $this->pdo->exec("PRAGMA foreign_keys=ON");
        }

        // 2. Add email column to contacts
        try { $this->pdo->exec("ALTER TABLE contacts ADD COLUMN email TEXT"); } catch (\Exception $e) {}

        // 3. Seed default feature flags
        $ffCount = $this->pdo->query("SELECT COUNT(*) as c FROM feature_flags")->fetch();
        if ($ffCount['c'] == 0) {
            $flags = [
                // Buttons
                ['whatsapp_btn',              1, 'WhatsApp Button (calling cards)', 'buttons'],
                ['email_btn',                 0, 'Email Button (calling cards)',     'buttons'],
                // Admin nav
                ['nav_admin_employees',       1, 'Employees',         'nav_admin'],
                ['nav_admin_teamleads',       1, 'Team Leads',        'nav_admin'],
                ['nav_admin_import',          1, 'Import & Manage',   'nav_admin'],
                ['nav_admin_reports',         1, 'Reports',           'nav_admin'],
                ['nav_admin_attendance',      1, 'Attendance',        'nav_admin'],
                ['nav_admin_statuses',        1, 'Call Statuses',     'nav_admin'],
                ['nav_admin_language',        1, 'Language Conflicts','nav_admin'],
                ['nav_admin_templates',       1, 'WA Templates',      'nav_admin'],
                ['nav_admin_email_templates', 1, 'Email Templates',   'nav_admin'],
                ['nav_admin_plugins',         1, 'Plugins',           'nav_admin'],
                ['nav_admin_settings',        1, 'Settings',          'nav_admin'],
                // Employee nav
                ['nav_emp_calls',             1, 'Calling Cards',        'nav_employee'],
                ['nav_emp_attendance',        1, 'Attendance',           'nav_employee'],
                ['nav_emp_profile',           1, 'Profile',              'nav_employee'],
                ['nav_emp_language',          1, 'Language Conflicts',   'nav_employee'],
                // Teamlead nav
                ['nav_tl_contacts',           1, 'My Contacts',          'nav_teamlead'],
                ['nav_tl_calls',              1, 'Calling Cards',        'nav_teamlead'],
                ['nav_tl_team',               1, 'My Team',              'nav_teamlead'],
                ['nav_tl_reports',            1, 'Reports',              'nav_teamlead'],
                ['nav_tl_language',           1, 'Language Conflicts',   'nav_teamlead'],
                ['nav_tl_attendance',         1, 'Attendance',           'nav_teamlead'],
            ];
            $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO feature_flags (flag_key, is_enabled, label, category) VALUES (?,?,?,?)");
            foreach ($flags as $f) {
                $stmt->execute($f);
            }
        }

        // ── End migrations ───────────────────────────────────────────────────────

        // Enable WAL mode for better concurrent read performance
        $this->pdo->exec("PRAGMA journal_mode=WAL;");

        // Create performance indexes for contacts table
        $indexes = "
        CREATE INDEX IF NOT EXISTS idx_contacts_import_date ON contacts(import_date);
        CREATE INDEX IF NOT EXISTS idx_contacts_assigned_to ON contacts(assigned_to);
        CREATE INDEX IF NOT EXISTS idx_contacts_status_id ON contacts(status_id);
        CREATE INDEX IF NOT EXISTS idx_contacts_is_called ON contacts(is_called);
        CREATE INDEX IF NOT EXISTS idx_contacts_created_at ON contacts(created_at DESC);
        CREATE INDEX IF NOT EXISTS idx_contacts_filter ON contacts(import_date, assigned_to, status_id);

        -- Indexes for call_logs table
        CREATE INDEX IF NOT EXISTS idx_call_logs_contact_id ON call_logs(contact_id);
        CREATE INDEX IF NOT EXISTS idx_call_logs_user_id ON call_logs(user_id);
        CREATE INDEX IF NOT EXISTS idx_call_logs_call_time ON call_logs(call_time);
        ";

        $this->pdo->exec($indexes);

        // Insert default call statuses if not exist
        $check = $this->pdo->query("SELECT COUNT(*) as count FROM call_statuses")->fetch();
        if ($check['count'] == 0) {
            $statuses = [
                ['Interested', '#22c55e', 1],
                ['Not Interested', '#ef4444', 2],
                ['Call Back', '#f59e0b', 3],
                ['Not Reachable', '#6b7280', 4],
                ['Wrong Number', '#8b5cf6', 5],
                ['Already Customer', '#3b82f6', 6]
            ];
            $stmt = $this->pdo->prepare("INSERT INTO call_statuses (name, color, sort_order) VALUES (?, ?, ?)");
            foreach ($statuses as $status) {
                $stmt->execute($status);
            }
        }

        // Insert default languages if not exist
        $langCheck = $this->pdo->query("SELECT COUNT(*) as count FROM languages")->fetch();
        if ($langCheck['count'] == 0) {
            $languages = ['English', 'Hindi', 'Kannada', 'Tamil', 'Telugu', 'Malayalam', 'Marathi', 'Bengali', 'Gujarati', 'Punjabi', 'Urdu', 'Odia'];
            $stmt = $this->pdo->prepare("INSERT INTO languages (name) VALUES (?)");
            foreach ($languages as $lang) {
                $stmt->execute([$lang]);
            }
        }

        // Migrate existing whatsapp_message from app_settings to whatsapp_templates
        $tplCheck = $this->pdo->query("SELECT COUNT(*) as count FROM whatsapp_templates")->fetch();
        if ($tplCheck['count'] == 0) {
            $existing = $this->pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'whatsapp_message'")->fetch();
            $defaultMsg = $existing ? $existing['setting_value'] : 'Hello {name}, this is a message from our team.';
            $this->pdo->prepare("INSERT INTO whatsapp_templates (name, message, is_default) VALUES (?, ?, 1)")
                ->execute(['Default Template', $defaultMsg]);
        }

        // Seed default admin account on first boot
        $userCount = $this->pdo->query("SELECT COUNT(*) as count FROM users")->fetch();
        if ($userCount['count'] == 0) {
            $this->pdo->prepare(
                "INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, 'admin', 1)"
            )->execute([
                'Admin',
                'admin@obsiguard.com',
                password_hash('Admin@2026', PASSWORD_DEFAULT)
            ]);
        }

        // Seed default super admin if none exists
        $superCount = $this->pdo->query("SELECT COUNT(*) as count FROM users WHERE role='super_admin'")->fetch();
        if ($superCount['count'] == 0) {
            $this->pdo->prepare(
                "INSERT OR IGNORE INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, 'super_admin', 1)"
            )->execute([
                'Super Admin',
                'superadmin@obsiguard.com',
                password_hash('Super@2026', PASSWORD_DEFAULT)
            ]);
        }

        // Seed NexoMailer API key if not already set
        $nxmKey = $this->pdo->query("SELECT setting_value FROM app_settings WHERE setting_key='nexomailer_api_key'")->fetch();
        if (!$nxmKey || empty($nxmKey['setting_value'])) {
            $this->pdo->prepare(
                "INSERT OR REPLACE INTO app_settings (setting_key, setting_value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)"
            )->execute(['nexomailer_api_key', 'nxm_cd77931840e648035c758086363830520c46bbe493005c7b']);
            $this->pdo->prepare(
                "INSERT OR REPLACE INTO app_settings (setting_key, setting_value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)"
            )->execute(['nexomailer_enabled', '1']);
        }

    public function query($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch($sql, $params = [])
    {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = [])
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert($sql, $params = [])
    {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    public function update($sql, $params = [])
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function delete($sql, $params = [])
    {
        return $this->query($sql, $params)->rowCount();
    }
}

// Helper function to get database instance
function db()
{
    return Database::getInstance();
}
?>