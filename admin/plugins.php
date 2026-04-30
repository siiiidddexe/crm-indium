<?php
$pageTitle = 'Plugins - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id     = intval($_POST['id'] ?? 0);
        $type   = $_POST['type'] ?? '';
        $name   = sanitize($_POST['name'] ?? '');

        $validTypes = ['google_sheets', 'google_ads', 'meta_ads'];
        if (!in_array($type, $validTypes) || !$name) {
            setFlash('error', 'Invalid plugin configuration.');
            header('Location: plugins.php');
            exit;
        }

        // Build config JSON based on type
        $config = [];
        if ($type === 'google_sheets') {
            $config['spreadsheet_id'] = trim($_POST['spreadsheet_id'] ?? '');
            $config['range']          = trim($_POST['range'] ?? 'Sheet1!A:Z');
            $config['api_key']        = trim($_POST['api_key'] ?? '');
        } elseif ($type === 'google_ads') {
            $config['developer_token']  = trim($_POST['developer_token'] ?? '');
            $config['client_id']        = trim($_POST['client_id'] ?? '');
            $config['client_secret']    = trim($_POST['client_secret'] ?? '');
            $config['refresh_token']    = trim($_POST['refresh_token'] ?? '');
            $config['customer_id']      = trim($_POST['customer_id'] ?? '');
        } elseif ($type === 'meta_ads') {
            $config['access_token']  = trim($_POST['access_token'] ?? '');
            $config['form_id']       = trim($_POST['form_id'] ?? '');
            $config['api_version']   = trim($_POST['api_version'] ?? 'v19.0');
        }

        $configJson = json_encode($config);

        // Field mappings from POST
        $targetFields    = $_POST['target_field'] ?? [];
        $sourceTemplates = $_POST['source_template'] ?? [];

        if ($action === 'add') {
            $webhookToken = bin2hex(random_bytes(24));
            $pluginId = db()->insert(
                "INSERT INTO plugins (type, name, config, webhook_token) VALUES (?, ?, ?, ?)",
                [$type, $name, $configJson, $webhookToken]
            );
        } else {
            db()->update(
                "UPDATE plugins SET type = ?, name = ?, config = ? WHERE id = ?",
                [$type, $name, $configJson, $id]
            );
            $pluginId = $id;
        }

        // Replace mappings
        db()->delete("DELETE FROM plugin_mappings WHERE plugin_id = ?", [$pluginId]);
        for ($i = 0; $i < count($targetFields); $i++) {
            $tf = trim($targetFields[$i] ?? '');
            $st = trim($sourceTemplates[$i] ?? '');
            if ($tf && $st && in_array($tf, ['name', 'phone', 'notes', 'email'])) {
                db()->insert(
                    "INSERT INTO plugin_mappings (plugin_id, target_field, source_template) VALUES (?, ?, ?)",
                    [$pluginId, $tf, $st]
                );
            }
        }

        setFlash('success', 'Plugin saved successfully!');
        header('Location: plugins.php');
        exit;
    }

    if ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        $plugin = db()->fetch("SELECT is_active FROM plugins WHERE id = ?", [$id]);
        if ($plugin) {
            $newVal = $plugin['is_active'] ? 0 : 1;
            db()->update("UPDATE plugins SET is_active = ? WHERE id = ?", [$newVal, $id]);
            setFlash('success', $newVal ? 'Plugin enabled.' : 'Plugin disabled.');
        }
        header('Location: plugins.php');
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        db()->delete("DELETE FROM plugins WHERE id = ?", [$id]);
        setFlash('success', 'Plugin deleted.');
        header('Location: plugins.php');
        exit;
    }
}

$plugins  = db()->fetchAll("SELECT * FROM plugins ORDER BY created_at DESC");
$statuses = getCallStatuses();

// Enrich plugins with mapping count and import count
foreach ($plugins as &$p) {
    $p['mappings']      = db()->fetchAll("SELECT * FROM plugin_mappings WHERE plugin_id = ?", [$p['id']]);
    $p['import_count']  = db()->fetch("SELECT COUNT(*) as c FROM plugin_lead_imports WHERE plugin_id = ?", [$p['id']])['c'];
    $p['config_data']   = json_decode($p['config'], true) ?: [];
}
unset($p);

require_once __DIR__ . '/../includes/header.php';

$webhookBase = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . APP_URL;
?>

<div class="p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-black">Plugins</h1>
            <p class="text-gray-500 mt-1">Connect lead sources and auto-import contacts</p>
        </div>
        <button onclick="showModal('addPluginModal')"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Plugin
        </button>
    </div>

    <?php $flash = getFlash(); if ($flash): ?>
        <div class="mb-4 p-4 rounded-xl <?= strpos($flash, 'success') !== false || $flash === getFlash('success') ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= sanitize($flash) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($plugins)): ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-16 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                </svg>
            </div>
            <h3 class="text-xl font-bold text-black mb-2">No plugins configured</h3>
            <p class="text-gray-500 mb-6">Connect Google Sheets, Google Ads, or Meta Ads to auto-import leads.</p>
            <button onclick="showModal('addPluginModal')"
                class="inline-flex items-center gap-2 px-6 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
                Add Your First Plugin
            </button>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <?php foreach ($plugins as $plugin): ?>
                <?php
                $typeLabels = [
                    'google_sheets' => ['label' => 'Google Sheets', 'color' => 'bg-green-100 text-green-800', 'icon' => '📊'],
                    'google_ads'    => ['label' => 'Google Ads', 'color' => 'bg-blue-100 text-blue-800', 'icon' => '🔷'],
                    'meta_ads'      => ['label' => 'Meta Ads', 'color' => 'bg-indigo-100 text-indigo-800', 'icon' => '📘'],
                ];
                $tl = $typeLabels[$plugin['type']] ?? ['label' => $plugin['type'], 'color' => 'bg-gray-100 text-gray-800', 'icon' => '🔌'];
                ?>
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden <?= $plugin['is_active'] ? '' : 'opacity-60' ?>">
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-lg"><?= $tl['icon'] ?></span>
                                    <h3 class="font-bold text-black truncate"><?= sanitize($plugin['name']) ?></h3>
                                </div>
                                <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full <?= $tl['color'] ?>"><?= $tl['label'] ?></span>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-medium <?= $plugin['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?= $plugin['is_active'] ? 'bg-green-500' : 'bg-gray-400' ?>"></span>
                                <?= $plugin['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                            <div class="bg-gray-50 rounded-xl p-2">
                                <p class="text-lg font-bold text-black"><?= number_format($plugin['import_count']) ?></p>
                                <p class="text-xs text-gray-500">Leads</p>
                            </div>
                            <div class="bg-gray-50 rounded-xl p-2">
                                <p class="text-lg font-bold text-black"><?= count($plugin['mappings']) ?></p>
                                <p class="text-xs text-gray-500">Mappings</p>
                            </div>
                            <div class="bg-gray-50 rounded-xl p-2">
                                <p class="text-xs font-bold text-black"><?= $plugin['last_sync'] ? date('M d H:i', strtotime($plugin['last_sync'])) : 'Never' ?></p>
                                <p class="text-xs text-gray-500">Last Sync</p>
                            </div>
                        </div>

                        <?php if ($plugin['type'] === 'google_ads' && $plugin['webhook_token']): ?>
                            <div class="mt-3 p-3 bg-blue-50 rounded-xl">
                                <p class="text-xs font-medium text-blue-700 mb-1">Webhook URL (paste in Google Ads)</p>
                                <div class="flex items-center gap-2">
                                    <code class="text-xs text-blue-800 break-all flex-1" id="wh_<?= $plugin['id'] ?>">
                                        <?= $webhookBase ?>/api/webhooks/google_ads_webhook.php?token=<?= $plugin['webhook_token'] ?>
                                    </code>
                                    <button onclick="copyWebhook('wh_<?= $plugin['id'] ?>')"
                                        class="shrink-0 px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-medium hover:bg-blue-200">Copy</button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Mappings preview -->
                        <?php if (!empty($plugin['mappings'])): ?>
                            <div class="mt-3">
                                <p class="text-xs font-medium text-gray-500 mb-1.5">Field Mappings</p>
                                <div class="space-y-1">
                                    <?php foreach ($plugin['mappings'] as $m): ?>
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="px-2 py-0.5 bg-gray-100 rounded font-mono text-gray-700"><?= sanitize($m['source_template']) ?></span>
                                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                            <span class="px-2 py-0.5 bg-black text-white rounded font-medium"><?= $m['target_field'] ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="border-t border-gray-100 p-3 bg-gray-50 flex gap-2">
                        <button onclick="syncPlugin(<?= $plugin['id'] ?>, this)"
                            class="flex-1 py-2 bg-black text-white rounded-xl text-sm font-medium hover:bg-gray-800 transition-colors flex items-center justify-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Sync Now
                        </button>
                        <button onclick="editPlugin(<?= htmlspecialchars(json_encode($plugin), ENT_QUOTES) ?>)"
                            class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium hover:bg-gray-50 transition-colors">
                            Edit
                        </button>
                        <form method="POST" onsubmit="return confirm('Toggle this plugin?')">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $plugin['id'] ?>">
                            <button type="submit"
                                class="px-4 py-2 <?= $plugin['is_active'] ? 'bg-yellow-50 text-yellow-700' : 'bg-green-50 text-green-700' ?> border border-gray-200 rounded-xl text-sm font-medium hover:opacity-80 transition-colors">
                                <?= $plugin['is_active'] ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Delete this plugin and all its import history?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $plugin['id'] ?>">
                            <button type="submit"
                                class="px-4 py-2 bg-red-50 text-red-600 border border-red-100 rounded-xl text-sm font-medium hover:bg-red-100 transition-colors">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Plugin Modal -->
<div id="addPluginModal"
    class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4"
    data-modal-backdrop>
    <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <form method="POST" id="pluginForm">
            <input type="hidden" name="action" id="pluginFormAction" value="add">
            <input type="hidden" name="id" id="pluginFormId" value="">

            <div class="p-6 border-b border-gray-200 sticky top-0 bg-white z-10">
                <h2 class="text-xl font-bold text-black" id="pluginModalTitle">Add Plugin</h2>
                <p class="text-sm text-gray-500 mt-1">Configure your lead source integration</p>
            </div>

            <div class="p-6 space-y-5">
                <!-- Plugin Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Plugin Name *</label>
                    <input type="text" name="name" id="pluginName" placeholder="e.g. Facebook Campaign Leads"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black" required>
                </div>

                <!-- Plugin Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Plugin Type *</label>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="type-option cursor-pointer">
                            <input type="radio" name="type" value="google_sheets" class="sr-only" onchange="showTypeConfig('google_sheets')">
                            <div class="p-3 rounded-xl border-2 border-gray-200 text-center transition-all hover:border-green-400 type-btn" data-type="google_sheets">
                                <span class="text-2xl block mb-1">📊</span>
                                <span class="text-xs font-medium">Google Sheets</span>
                            </div>
                        </label>
                        <label class="type-option cursor-pointer">
                            <input type="radio" name="type" value="google_ads" class="sr-only" onchange="showTypeConfig('google_ads')">
                            <div class="p-3 rounded-xl border-2 border-gray-200 text-center transition-all hover:border-blue-400 type-btn" data-type="google_ads">
                                <span class="text-2xl block mb-1">🔷</span>
                                <span class="text-xs font-medium">Google Ads</span>
                            </div>
                        </label>
                        <label class="type-option cursor-pointer">
                            <input type="radio" name="type" value="meta_ads" class="sr-only" onchange="showTypeConfig('meta_ads')">
                            <div class="p-3 rounded-xl border-2 border-gray-200 text-center transition-all hover:border-indigo-400 type-btn" data-type="meta_ads">
                                <span class="text-2xl block mb-1">📘</span>
                                <span class="text-xs font-medium">Meta Ads</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Google Sheets Config -->
                <div id="config_google_sheets" class="type-config hidden space-y-4">
                    <div class="p-3 bg-green-50 rounded-xl text-sm text-green-800 space-y-1">
                        <p class="font-semibold">Google Sheets Setup</p>
                        <ol class="list-decimal list-inside space-y-1 text-green-700">
                            <li>Open your Google Sheet and click <strong>Share</strong>.</li>
                            <li>Set access to <strong>"Anyone with the link"</strong> → <strong>Viewer</strong>.</li>
                            <li>Copy the Spreadsheet ID from the URL (see hint below).</li>
                            <li>Set the range (e.g. <code>Sheet1!A:Z</code>). The first row must be the header row.</li>
                            <li>For private sheets, create an API key in <a href="https://console.cloud.google.com/" target="_blank" class="underline">Google Cloud Console</a> and enable the Sheets API.</li>
                            <li>Add field mappings using <strong>Detect Headers</strong> or type column names manually (e.g. <code>{Name}</code>).</li>
                        </ol>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Spreadsheet ID or URL *</label>
                        <input type="text" name="spreadsheet_id" id="gs_spreadsheet_id"
                            placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm font-mono">
                        <p class="text-xs text-gray-500 mt-1">From the URL: docs.google.com/spreadsheets/d/<strong>ID</strong>/edit</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Range</label>
                        <input type="text" name="range" id="gs_range" value="Sheet1!A:Z"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm font-mono">
                        <p class="text-xs text-gray-500 mt-1">First row is treated as headers. Use {column_name} in mappings.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Google API Key (for private sheets)</label>
                        <input type="text" name="api_key" id="gs_api_key"
                            placeholder="AIza... (leave blank for public sheets)"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm font-mono">
                    </div>
                    <button type="button" onclick="detectGoogleSheetHeaders()"
                        class="w-full py-2 bg-green-600 text-white rounded-xl text-sm font-medium hover:bg-green-700 transition-colors flex items-center justify-center gap-2" id="detectHeadersBtn">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Detect Headers
                    </button>
                </div>

                <!-- Google Ads Config -->
                <div id="config_google_ads" class="type-config hidden space-y-4">
                    <div class="p-3 bg-blue-50 rounded-xl text-sm text-blue-800 space-y-1">
                        <p class="font-semibold">Google Ads Webhook Setup</p>
                        <ol class="list-decimal list-inside space-y-1 text-blue-700">
                            <li>Save this plugin first to generate a webhook URL.</li>
                            <li>In Google Ads, go to <strong>Tools &amp; Settings → Lead Forms</strong>.</li>
                            <li>Edit your lead form and find the <strong>Webhook</strong> section.</li>
                            <li>Paste the generated webhook URL from this plugin card.</li>
                            <li>Set the field mappings below to match your Google Ads lead form fields (e.g. <code>{FULL_NAME}</code>, <code>{PHONE_NUMBER}</code>).</li>
                        </ol>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Developer Token</label>
                        <input type="text" name="developer_token" id="ga_dev_token"
                            placeholder="Your Google Ads developer token"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm font-mono">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Client ID</label>
                            <input type="text" name="client_id" id="ga_client_id"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm font-mono">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Client Secret</label>
                            <input type="text" name="client_secret" id="ga_client_secret"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm font-mono">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Refresh Token</label>
                        <input type="text" name="refresh_token" id="ga_refresh_token"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Customer ID</label>
                        <input type="text" name="customer_id" id="ga_customer_id"
                            placeholder="123-456-7890"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm font-mono">
                    </div>
                </div>

                <!-- Meta Ads Config -->
                <div id="config_meta_ads" class="type-config hidden space-y-4">
                    <div class="p-3 bg-indigo-50 rounded-xl text-sm text-indigo-800 space-y-1">
                        <p class="font-semibold">Meta Lead Ads Setup</p>
                        <ol class="list-decimal list-inside space-y-1 text-indigo-700">
                            <li>Go to <strong>Meta Business Suite → Instant Forms</strong> and find your form.</li>
                            <li>Note the <strong>Lead Form ID</strong> (shown in form details).</li>
                            <li>Generate a <strong>long-lived Page Access Token</strong> via Meta Graph API Explorer (permission: <code>leads_retrieval</code>).</li>
                            <li>Set the API version (default <code>v19.0</code> is recommended).</li>
                            <li>Use field key names from your Meta form in mappings (e.g. <code>{full_name}</code>, <code>{phone_number}</code>, <code>{email}</code>).</li>
                        </ol>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Page Access Token *</label>
                        <input type="text" name="access_token" id="ma_access_token"
                            placeholder="EAABkz..."
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lead Form ID *</label>
                        <input type="text" name="form_id" id="ma_form_id"
                            placeholder="123456789"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">API Version</label>
                        <input type="text" name="api_version" id="ma_api_version" value="v19.0"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm font-mono">
                    </div>
                </div>

                <!-- Field Mappings -->
                <div id="mappingsSection" class="hidden">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Field Mappings *</label>
                            <p class="text-xs text-gray-500 mt-0.5">Map source fields to CRM fields. Use <code class="bg-gray-100 px-1 rounded">{field_name}</code> for field values. Concat: <code class="bg-gray-100 px-1 rounded">{first_name} {last_name}</code></p>
                        </div>
                        <button type="button" onclick="addMappingRow()"
                            class="text-xs px-3 py-1.5 bg-black text-white rounded-lg hover:bg-gray-800">+ Add Row</button>
                    </div>
                    <div id="mappingRows" class="space-y-2 bg-gray-50 rounded-xl p-3">
                        <!-- Rows added dynamically -->
                    </div>

                    <!-- Common field hints -->
                    <div id="fieldHints" class="mt-2"></div>
                </div>
            </div>

            <div class="p-6 border-t border-gray-100 bg-gray-50 flex gap-3 sticky bottom-0">
                <button type="button" onclick="hideModal('addPluginModal')"
                    class="flex-1 py-3 bg-gray-200 rounded-xl font-medium hover:bg-gray-300 transition-colors">Cancel</button>
                <button type="submit"
                    class="flex-1 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">Save Plugin</button>
            </div>
        </form>
    </div>
</div>

<script>
const fieldHints = {
    google_sheets: 'Column headers from your sheet, e.g.: <code class="bg-gray-100 px-1 rounded">{Name}</code>, <code class="bg-gray-100 px-1 rounded">{Phone}</code>, <code class="bg-gray-100 px-1 rounded">{First Name} {Last Name}</code>',
    google_ads: 'Common fields: <code class="bg-gray-100 px-1 rounded">{FULL_NAME}</code>, <code class="bg-gray-100 px-1 rounded">{PHONE_NUMBER}</code>, <code class="bg-gray-100 px-1 rounded">{EMAIL}</code>, <code class="bg-gray-100 px-1 rounded">{CITY}</code>',
    meta_ads: 'Common fields: <code class="bg-gray-100 px-1 rounded">{full_name}</code>, <code class="bg-gray-100 px-1 rounded">{phone_number}</code>, <code class="bg-gray-100 px-1 rounded">{email}</code>, <code class="bg-gray-100 px-1 rounded">{first_name} {last_name}</code>'
};

let selectedType = '';

function showTypeConfig(type) {
    selectedType = type;
    document.querySelectorAll('.type-config').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.type-btn').forEach(el => {
        el.classList.remove('border-black', 'bg-black', 'text-white');
        el.classList.add('border-gray-200');
    });

    const configEl = document.getElementById('config_' + type);
    if (configEl) configEl.classList.remove('hidden');

    const btn = document.querySelector(`.type-btn[data-type="${type}"]`);
    if (btn) {
        btn.classList.add('border-black');
        btn.classList.remove('border-gray-200');
    }

    document.getElementById('mappingsSection').classList.remove('hidden');
    document.getElementById('fieldHints').innerHTML = '<p class="text-xs text-gray-500 mt-1">' + (fieldHints[type] || '') + '</p>';

    // Pre-populate if no rows yet
    if (document.querySelectorAll('#mappingRows .mapping-row').length === 0) {
        addMappingRow('name', type === 'meta_ads' ? '{full_name}' : type === 'google_ads' ? '{FULL_NAME}' : '{Name}');
        addMappingRow('phone', type === 'meta_ads' ? '{phone_number}' : type === 'google_ads' ? '{PHONE_NUMBER}' : '{Phone}');
    }
}

function addMappingRow(targetVal = '', sourceVal = '') {
    const row = document.createElement('div');
    row.className = 'mapping-row flex items-center gap-2';
    row.innerHTML = `
        <select name="target_field[]" class="w-32 px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-black" required>
            <option value="">Target</option>
            <option value="name" ${targetVal === 'name' ? 'selected' : ''}>name</option>
            <option value="phone" ${targetVal === 'phone' ? 'selected' : ''}>phone</option>
            <option value="email" ${targetVal === 'email' ? 'selected' : ''}>email</option>
            <option value="notes" ${targetVal === 'notes' ? 'selected' : ''}>notes</option>
        </select>
        <span class="text-gray-400 text-xs">←</span>
        <input type="text" name="source_template[]" value="${escapeAttr(sourceVal)}"
            placeholder="{field_name} or {first} {last}"
            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-1 focus:ring-black" required>
        <button type="button" onclick="this.parentElement.remove()" class="p-2 text-red-400 hover:text-red-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>`;
    document.getElementById('mappingRows').appendChild(row);
}

function escapeAttr(s) {
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function editPlugin(plugin) {
    document.getElementById('pluginFormAction').value = 'edit';
    document.getElementById('pluginFormId').value = plugin.id;
    document.getElementById('pluginModalTitle').textContent = 'Edit Plugin';
    document.getElementById('pluginName').value = plugin.name;
    document.getElementById('mappingRows').innerHTML = '';

    // Select type
    const typeInput = document.querySelector(`input[name="type"][value="${plugin.type}"]`);
    if (typeInput) {
        typeInput.checked = true;
        showTypeConfig(plugin.type);
    }

    // Fill config
    const cfg = plugin.config_data || {};
    if (plugin.type === 'google_sheets') {
        document.getElementById('gs_spreadsheet_id').value = cfg.spreadsheet_id || '';
        document.getElementById('gs_range').value = cfg.range || 'Sheet1!A:Z';
        document.getElementById('gs_api_key').value = cfg.api_key || '';
    } else if (plugin.type === 'google_ads') {
        document.getElementById('ga_dev_token').value = cfg.developer_token || '';
        document.getElementById('ga_client_id').value = cfg.client_id || '';
        document.getElementById('ga_client_secret').value = cfg.client_secret || '';
        document.getElementById('ga_refresh_token').value = cfg.refresh_token || '';
        document.getElementById('ga_customer_id').value = cfg.customer_id || '';
    } else if (plugin.type === 'meta_ads') {
        document.getElementById('ma_access_token').value = cfg.access_token || '';
        document.getElementById('ma_form_id').value = cfg.form_id || '';
        document.getElementById('ma_api_version').value = cfg.api_version || 'v19.0';
    }

    // Load mappings
    document.getElementById('mappingRows').innerHTML = '';
    (plugin.mappings || []).forEach(m => addMappingRow(m.target_field, m.source_template));

    showModal('addPluginModal');
}

function detectGoogleSheetHeaders() {
    const spreadsheetId = document.getElementById('gs_spreadsheet_id').value.trim();
    const range = document.getElementById('gs_range').value.trim() || 'Sheet1!A:Z';
    const apiKey = document.getElementById('gs_api_key').value.trim();

    if (!spreadsheetId) {
        showToast('Please enter a Spreadsheet ID first.', 'error');
        return;
    }

    const btn = document.getElementById('detectHeadersBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Detecting...';

    const params = new URLSearchParams({ spreadsheet_id: spreadsheetId, range, api_key: apiKey });
    fetch('<?= APP_URL ?>/api/plugin_headers.php?' + params.toString())
        .then(r => r.json())
        .then(data => {
            if (data.success && data.headers && data.headers.length > 0) {
                document.getElementById('mappingRows').innerHTML = '';
                data.headers.forEach(h => addMappingRow('', '{' + h + '}'));
                showToast('Detected ' + data.headers.length + ' column(s). Map target fields below.');
            } else {
                showToast(data.error || 'Could not detect headers. Check Spreadsheet ID and API key.', 'error');
            }
        }).catch(() => showToast('Network error detecting headers.', 'error'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg> Detect Headers';
        });
}

function syncPlugin(pluginId, btn) {
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Syncing...';

    fetch('<?= APP_URL ?>/api/plugin_sync.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ plugin_id: pluginId })
    }).then(r => r.json()).then(data => {
        if (data.success) {
            showToast(`Sync complete! ${data.imported} new lead(s) imported.`);
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.error || 'Sync failed', 'error');
        }
    }).catch(() => showToast('Network error during sync', 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
}

function copyWebhook(elementId) {
    const el = document.getElementById(elementId);
    navigator.clipboard.writeText(el.textContent.trim()).then(() => showToast('Webhook URL copied!'));
}

function showModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideModal(id) {
    document.getElementById(id).classList.add('hidden');
    document.body.style.overflow = '';
}

// Reset form when modal opens for 'add'
document.getElementById('addPluginModal').addEventListener('click', function(e) {
    if (e.target === this) hideModal('addPluginModal');
});

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `fixed bottom-6 right-6 z-[9999] px-4 py-3 rounded-xl text-white text-sm font-medium shadow-lg transition-all
        ${type === 'error' ? 'bg-red-500' : 'bg-gray-900'}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Add CSS for spinner
const style = document.createElement('style');
style.textContent = `.animate-spin { animation: spin 1s linear infinite; } @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`;
document.head.appendChild(style);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
