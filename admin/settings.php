<?php
$pageTitle = 'Settings - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        setSetting('enable_notes',        isset($_POST['enable_notes'])        ? '1' : '0');
        setSetting('auto_assign_enabled', isset($_POST['auto_assign_enabled']) ? '1' : '0');
        setSetting('auto_assign_interval', max(10, intval($_POST['auto_assign_interval'] ?? 20)));
        setFlash('success', 'Settings saved!');
        header('Location: settings.php');
        exit;
    }

    if ($action === 'save_email_settings') {
        $apiKey  = trim($_POST['nexomailer_api_key'] ?? '');
        setSetting('nexomailer_api_key', $apiKey);
        setSetting('nexomailer_enabled', isset($_POST['nexomailer_enabled']) ? '1' : '0');
        // Button visibility stored as feature flags
        db()->update("UPDATE feature_flags SET is_enabled = ? WHERE flag_key = 'whatsapp_btn'",
            [isset($_POST['show_whatsapp_btn']) ? 1 : 0]);
        db()->update("UPDATE feature_flags SET is_enabled = ? WHERE flag_key = 'email_btn'",
            [isset($_POST['show_email_btn']) ? 1 : 0]);
        setFlash('success', 'Email settings saved!');
        header('Location: settings.php');
        exit;
    }

    if ($action === 'save_notifications') {
        setSetting('notif_recipient_email', trim($_POST['notif_recipient_email'] ?? ''));
        setSetting('notif_new_leads',       isset($_POST['notif_new_leads'])    ? '1' : '0');
        setSetting('notif_sync_complete',   isset($_POST['notif_sync_complete']) ? '1' : '0');
        setSetting('notif_eod_report',      isset($_POST['notif_eod_report'])   ? '1' : '0');
        // Regenerate cron key if requested
        if (!empty($_POST['regen_cron_key'])) {
            setSetting('notif_eod_cron_key', bin2hex(random_bytes(16)));
        }
        // Also auto-enable NexoMailer if a key exists
        if (getSetting('nexomailer_api_key', '') !== '') {
            setSetting('nexomailer_enabled', '1');
        }
        setFlash('success', 'Notification settings saved!');
        header('Location: settings.php');
        exit;
    }

    if ($action === 'add_rule') {
        $name      = sanitize($_POST['rule_name'] ?? '');
        $statusId  = intval($_POST['status_id'] ?? 0);
        $days      = max(1, intval($_POST['reassign_every_days'] ?? 7));
        if ($name && $statusId) {
            db()->insert(
                "INSERT INTO auto_assign_rules (name, status_id, reassign_every_days) VALUES (?, ?, ?)",
                [$name, $statusId, $days]
            );
            setFlash('success', 'Rule added!');
        }
        header('Location: settings.php');
        exit;
    }

    if ($action === 'toggle_rule') {
        $id   = intval($_POST['id'] ?? 0);
        $rule = db()->fetch("SELECT is_active FROM auto_assign_rules WHERE id = ?", [$id]);
        if ($rule) {
            db()->update("UPDATE auto_assign_rules SET is_active = ? WHERE id = ?", [$rule['is_active'] ? 0 : 1, $id]);
        }
        header('Location: settings.php');
        exit;
    }

    if ($action === 'delete_rule') {
        $id = intval($_POST['id'] ?? 0);
        db()->delete("DELETE FROM auto_assign_rules WHERE id = ?", [$id]);
        setFlash('success', 'Rule deleted.');
        header('Location: settings.php');
        exit;
    }
}

$enableNotes        = getSetting('enable_notes', '0') === '1';
$autoAssignEnabled  = getSetting('auto_assign_enabled', '0') === '1';
$autoAssignInterval = intval(getSetting('auto_assign_interval', '20'));
$nexoMailerEnabled  = getSetting('nexomailer_enabled', '0') === '1';
$nexoMailerApiKey   = getSetting('nexomailer_api_key', '');
$showWhatsAppBtn    = getFeatureFlag('whatsapp_btn', 1);
$showEmailBtn       = getFeatureFlag('email_btn', 0);
$notifEmail         = getSetting('notif_recipient_email', '');
$notifNewLeads      = getSetting('notif_new_leads', '0') === '1';
$notifSyncComplete  = getSetting('notif_sync_complete', '0') === '1';
$notifEodReport     = getSetting('notif_eod_report', '0') === '1';
$notifCronKey       = getSetting('notif_eod_cron_key', '');
if (!$notifCronKey) {
    $notifCronKey = bin2hex(random_bytes(16));
    setSetting('notif_eod_cron_key', $notifCronKey);
}
$rules              = db()->fetchAll("
    SELECT r.*, cs.name as status_name, cs.color as status_color
    FROM auto_assign_rules r
    JOIN call_statuses cs ON r.status_id = cs.id
    ORDER BY r.created_at DESC
");
$statuses = getCallStatuses();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-black">Settings</h1>
        <p class="text-gray-500 mt-1">Configure CRM behaviour and automation</p>
    </div>

    <?php $flash = getFlash(); if ($flash): ?>
        <div class="mb-4 p-4 rounded-xl bg-green-50 text-green-800 border border-green-200"><?= sanitize($flash) ?></div>
    <?php endif; ?>

    <!-- General Settings -->
    <form method="POST" class="mb-6">
        <input type="hidden" name="action" value="save_settings">
                        Automatically distributes unassigned contacts to active employees in a round-robin fashion.
                        Runs every <strong id="intervalPreview"><?= $autoAssignInterval ?></strong> seconds while an admin is logged in.
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-1">
                    <input type="checkbox" name="auto_assign_enabled" id="autoAssignToggle" class="sr-only peer" <?= $autoAssignEnabled ? 'checked' : '' ?>>
                    <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-7 peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-black"></div>
                </label>
            </div>

            <!-- Auto-Assign Interval -->
            <div class="pl-0 pt-2 flex items-center gap-3">
                <label class="text-sm text-gray-600 shrink-0">Poll every</label>
                <input type="number" name="auto_assign_interval" id="autoAssignInterval"
                    value="<?= $autoAssignInterval ?>" min="10" max="300"
                    oninput="document.getElementById('intervalPreview').textContent = this.value"
                    class="w-24 px-3 py-2 border border-gray-300 rounded-xl text-sm font-mono focus:ring-2 focus:ring-black focus:border-black">
                <label class="text-sm text-gray-600">seconds</label>
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="px-6 py-2.5 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
                    Save Settings
                </button>
            </div>
        </div>
    </form>

    <!-- NexoMailer & Button Visibility -->
    <form method="POST" class="mb-6">
        <input type="hidden" name="action" value="save_email_settings">
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-6">
            <h2 class="text-lg font-bold text-black border-b border-gray-100 pb-3">Email (NexoMailer)</h2>

            <!-- NexoMailer Enabled -->
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <h3 class="font-semibold text-black">Enable NexoMailer</h3>
                    <p class="text-sm text-gray-500 mt-1">Allow sending emails from calling cards via NexoMailer. Requires a valid API key below.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-1">
                    <input type="checkbox" name="nexomailer_enabled" class="sr-only peer" <?= $nexoMailerEnabled ? 'checked' : '' ?>>
                    <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-7 peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-black"></div>
                </label>
            </div>

            <!-- API Key -->
            <div class="pt-2 border-t border-gray-100">
                <label class="block text-sm font-medium text-gray-700 mb-1">NexoMailer API Key</label>
                <input type="text" name="nexomailer_api_key" value="<?= sanitize($nexoMailerApiKey) ?>"
                    placeholder="nxm_your_key_here"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm font-mono focus:ring-2 focus:ring-black focus:border-black">
                <p class="text-xs text-gray-400 mt-1">Get your key from <a href="https://nexomail.logiclaunch.in" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">nexomail.logiclaunch.in</a></p>
            </div>

            <!-- WhatsApp Button Toggle -->
            <div class="flex items-start justify-between gap-4 pt-4 border-t border-gray-100">
                <div class="flex-1">
                    <h3 class="font-semibold text-black">Show WhatsApp Button</h3>
                    <p class="text-sm text-gray-500 mt-1">Display the WhatsApp button on calling cards for employees and team leads.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-1">
                    <input type="checkbox" name="show_whatsapp_btn" class="sr-only peer" <?= $showWhatsAppBtn ? 'checked' : '' ?>>
                    <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-7 peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-black"></div>
                </label>
            </div>

            <!-- Email Button Toggle -->
            <div class="flex items-start justify-between gap-4 pt-4 border-t border-gray-100">
                <div class="flex-1">
                    <h3 class="font-semibold text-black">Show Email Button</h3>
                    <p class="text-sm text-gray-500 mt-1">Display an Email button on calling cards. Opens a modal to choose a template and send via NexoMailer.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-1">
                    <input type="checkbox" name="show_email_btn" class="sr-only peer" <?= $showEmailBtn ? 'checked' : '' ?>>
                    <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-7 peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-black"></div>
                </label>
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="px-6 py-2.5 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
                    Save Email Settings
                </button>
            </div>
        </div>
    </form>

    <!-- Notifications -->
    <form method="POST" class="mb-6">
        <input type="hidden" name="action" value="save_notifications">
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-6">
            <h2 class="text-lg font-bold text-black border-b border-gray-100 pb-3">Notifications (NexoMailer)</h2>

            <!-- Recipient -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notification Recipient Email *</label>
                <input type="email" name="notif_recipient_email" value="<?= sanitize($notifEmail) ?>"
                    placeholder="admin@yourdomain.com"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-black focus:border-black">
                <p class="text-xs text-gray-400 mt-1">All event notifications and the daily report are sent to this address.</p>
            </div>

            <!-- New Leads -->
            <div class="flex items-start justify-between gap-4 pt-4 border-t border-gray-100">
                <div class="flex-1">
                    <h3 class="font-semibold text-black">New Leads Imported</h3>
                    <p class="text-sm text-gray-500 mt-1">Send an email instantly when contacts are imported via CSV upload or plugin sync.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-1">
                    <input type="checkbox" name="notif_new_leads" class="sr-only peer" <?= $notifNewLeads ? 'checked' : '' ?>>
                    <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-7 peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-black"></div>
                </label>
            </div>

            <!-- Sync Complete -->
            <div class="flex items-start justify-between gap-4 pt-4 border-t border-gray-100">
                <div class="flex-1">
                    <h3 class="font-semibold text-black">Plugin Sync Complete</h3>
                    <p class="text-sm text-gray-500 mt-1">Send an email when a Google Sheets or Meta Ads sync finishes, with lead count and any errors.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-1">
                    <input type="checkbox" name="notif_sync_complete" class="sr-only peer" <?= $notifSyncComplete ? 'checked' : '' ?>>
                    <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-7 peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-black"></div>
                </label>
            </div>

            <!-- EOD Report -->
            <div class="flex items-start justify-between gap-4 pt-4 border-t border-gray-100">
                <div class="flex-1">
                    <h3 class="font-semibold text-black">End of Day Report</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Enable the EOD report endpoint. Trigger via cron or the button below.
                        Includes today's import stats, calls made, top performers, and pending leads.
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-1">
                    <input type="checkbox" name="notif_eod_report" class="sr-only peer" <?= $notifEodReport ? 'checked' : '' ?>>
                    <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-7 peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-black"></div>
                </label>
            </div>

            <!-- Cron URL -->
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-2">
                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Cron URL (schedule daily at 11 PM)</p>
                <div class="flex gap-2">
                    <input type="text" id="cronUrlField" readonly
                        value="<?= sanitize(APP_URL) ?>/api/eod_report.php?key=<?= sanitize($notifCronKey) ?>"
                        class="flex-1 px-3 py-2 bg-white border border-gray-300 rounded-xl text-xs font-mono text-gray-700 focus:outline-none select-all">
                    <button type="button" onclick="copyCronUrl()"
                        class="px-3 py-2 bg-gray-200 rounded-xl text-xs font-medium hover:bg-gray-300 transition-colors shrink-0">Copy</button>
                </div>
                <p class="text-xs text-gray-400">Example cron: <code class="bg-gray-100 px-1 rounded">0 23 * * * curl "URL_ABOVE" &gt; /dev/null</code></p>
                <label class="flex items-center gap-2 cursor-pointer mt-1">
                    <input type="checkbox" name="regen_cron_key" class="w-4 h-4 rounded border-gray-300">
                    <span class="text-xs text-gray-500">Regenerate secret key on save</span>
                </label>
            </div>

            <div class="flex gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                    class="px-6 py-2.5 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
                    Save Notification Settings
                </button>
                <button type="button" onclick="sendEodNow(this)"
                    class="px-6 py-2.5 bg-indigo-600 text-white rounded-xl font-medium hover:bg-indigo-700 transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    Send EOD Report Now
                </button>
            </div>
        </div>
    </form>

    <!-- Auto-Assign Rules -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-5 border-b border-gray-100 pb-3">
            <div>
                <h2 class="text-lg font-bold text-black">Auto-Assign Rules</h2>
                <p class="text-sm text-gray-500 mt-1">Contacts in a given status get randomly reassigned every X days if uncalled</p>
            </div>
            <button onclick="showModal('addRuleModal')"
                class="inline-flex items-center gap-2 px-4 py-2 bg-black text-white rounded-xl text-sm font-medium hover:bg-gray-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Rule
            </button>
        </div>

        <?php if (empty($rules)): ?>
            <div class="text-center py-10 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p class="font-medium">No rules configured</p>
                <p class="text-sm mt-1">Add a rule to automatically reassign stale leads</p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($rules as $rule): ?>
                    <div class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-200 <?= $rule['is_active'] ? '' : 'opacity-60' ?>">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" style="background-color:<?= $rule['status_color'] ?>20">
                                <div class="w-3 h-3 rounded-full" style="background-color:<?= $rule['status_color'] ?>"></div>
                            </div>
                            <div>
                                <p class="font-medium text-black"><?= sanitize($rule['name']) ?></p>
                                <p class="text-sm text-gray-500">
                                    Contacts with status <strong><?= sanitize($rule['status_name']) ?></strong>
                                    → reassign every <strong><?= $rule['reassign_every_days'] ?></strong> day(s)
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="px-2 py-0.5 text-xs rounded-full <?= $rule['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                                <?= $rule['is_active'] ? 'Active' : 'Off' ?>
                            </span>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle_rule">
                                <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                                <button type="submit"
                                    class="px-3 py-1 bg-gray-100 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-200 transition-colors">
                                    <?= $rule['is_active'] ? 'Disable' : 'Enable' ?>
                                </button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this rule?')">
                                <input type="hidden" name="action" value="delete_rule">
                                <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                                <button type="submit"
                                    class="px-3 py-1 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100 transition-colors">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Rule Modal -->
<div id="addRuleModal"
    class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4"
    data-modal-backdrop>
    <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden">
        <form method="POST">
            <input type="hidden" name="action" value="add_rule">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-bold text-black">Add Auto-Assign Rule</h2>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rule Name *</label>
                    <input type="text" name="rule_name" required placeholder="e.g. Re-engage Rerefer leads"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                    <select name="status_id" required class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                        <option value="">— Select Status —</option>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= sanitize($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Contacts in this status will be re-assigned if not called within X days</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reassign Every (days) *</label>
                    <input type="number" name="reassign_every_days" required min="1" max="365" value="7"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                </div>
            </div>
            <div class="p-6 border-t border-gray-100 bg-gray-50 flex gap-3">
                <button type="button" onclick="hideModal('addRuleModal')"
                    class="flex-1 py-3 bg-gray-200 rounded-xl font-medium hover:bg-gray-300 transition-colors">Cancel</button>
                <button type="submit"
                    class="flex-1 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">Add Rule</button>
            </div>
        </form>
    </div>
</div>

<script>
function showModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideModal(id) {
    document.getElementById(id).classList.add('hidden');
    document.body.style.overflow = '';
}
document.querySelectorAll('[data-modal-backdrop]').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) hideModal(el.id); });
});

function copyCronUrl() {
    const el = document.getElementById('cronUrlField');
    el.select();
    navigator.clipboard.writeText(el.value).then(() => {
        el.blur();
        alert('Cron URL copied!');
    });
}

function sendEodNow(btn) {
    const key = <?= json_encode($notifCronKey) ?>;
    btn.disabled = true;
    btn.textContent = 'Sending...';
    fetch('<?= APP_URL ?>/api/eod_report.php?key=' + encodeURIComponent(key))
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('EOD report sent to ' + (data.sent_to || 'recipient') + '!');
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        }).catch(() => alert('Network error sending EOD report.'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg> Send EOD Report Now';
        });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
