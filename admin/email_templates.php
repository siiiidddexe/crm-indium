<?php
$pageTitle = 'Email Templates - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_template') {
        $id       = intval($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $subject  = trim($_POST['subject'] ?? '');
        $htmlBody = $_POST['html_body'] ?? '';

        if (!$name || !$subject || !$htmlBody) {
            $error = 'All fields are required.';
        } else {
            if ($id) {
                db()->update(
                    "UPDATE email_templates SET name=?, subject=?, html_body=? WHERE id=?",
                    [$name, $subject, $htmlBody, $id]
                );
                setFlash('success', 'Template updated.');
            } else {
                db()->insert(
                    "INSERT INTO email_templates (name, subject, html_body) VALUES (?,?,?)",
                    [$name, $subject, $htmlBody]
                );
                setFlash('success', 'Template created.');
            }
            header('Location: email_templates.php'); exit;
        }
    }

    if ($action === 'delete_template') {
        $id = intval($_POST['id'] ?? 0);
        db()->delete("DELETE FROM email_templates WHERE id = ?", [$id]);
        setFlash('success', 'Template deleted.');
        header('Location: email_templates.php'); exit;
    }

    if ($action === 'toggle_template') {
        $id  = intval($_POST['id'] ?? 0);
        $row = db()->fetch("SELECT is_active FROM email_templates WHERE id = ?", [$id]);
        if ($row) {
            db()->update("UPDATE email_templates SET is_active = ? WHERE id = ?", [$row['is_active'] ? 0 : 1, $id]);
        }
        header('Location: email_templates.php'); exit;
    }
}

$templates = db()->fetchAll("SELECT * FROM email_templates ORDER BY created_at DESC");
$editId    = intval($_GET['edit'] ?? 0);
$editTpl   = $editId ? db()->fetch("SELECT * FROM email_templates WHERE id = ?", [$editId]) : null;
$flashMsg  = getFlash();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-black">Email Templates</h1>
            <p class="text-gray-500 mt-1">Create custom HTML email templates for NexoMailer</p>
        </div>
        <button onclick="showModal('tplModal'); resetForm()"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-black text-white rounded-xl font-medium text-sm hover:bg-gray-800 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            New Template
        </button>
    </div>

    <?php if ($flashMsg): ?>
        <div class="mb-4 p-4 rounded-xl bg-green-50 text-green-800 border border-green-200"><?= sanitize($flashMsg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="mb-4 p-4 rounded-xl bg-red-50 text-red-800 border border-red-200"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <!-- Available Variables Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 text-sm text-blue-800">
        <strong>Template variables:</strong> Use <code class="bg-blue-100 px-1 rounded">{name}</code>,
        <code class="bg-blue-100 px-1 rounded">{phone}</code>,
        <code class="bg-blue-100 px-1 rounded">{email}</code> in your HTML — they are replaced with contact data when sending.
    </div>

    <!-- Templates Grid -->
    <?php if (empty($templates)): ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <p class="text-gray-500">No email templates yet. Create one to start sending emails.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <?php foreach ($templates as $tpl): ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-5 hover:border-gray-400 transition-colors">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-black truncate"><?= sanitize($tpl['name']) ?></h3>
                            <p class="text-sm text-gray-500 truncate mt-0.5"><?= sanitize($tpl['subject']) ?></p>
                        </div>
                        <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-semibold flex-shrink-0 <?= $tpl['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                            <?= $tpl['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 mb-3 max-h-24 overflow-hidden relative">
                        <div class="text-xs text-gray-400 font-mono overflow-hidden" style="display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;">
                            <?= sanitize(strip_tags($tpl['html_body'])) ?>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="editTemplate(<?= htmlspecialchars(json_encode($tpl), ENT_QUOTES) ?>)"
                            class="flex-1 py-2 text-sm font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Edit</button>
                        <button onclick="previewTemplate(<?= $tpl['id'] ?>)"
                            class="flex-1 py-2 text-sm font-medium bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors">Preview</button>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this template?')">
                            <input type="hidden" name="action" value="delete_template">
                            <input type="hidden" name="id" value="<?= $tpl['id'] ?>">
                            <button type="submit" class="py-2 px-3 text-sm bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create/Edit Template Modal -->
<div id="tplModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-5 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-xl font-bold text-black" id="tplModalTitle">New Email Template</h2>
            <button onclick="hideModal('tplModal')" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form method="POST" class="flex flex-col flex-1 overflow-hidden" id="tplForm">
            <input type="hidden" name="action" value="save_template">
            <input type="hidden" name="id" id="tplId" value="0">
            <div class="p-5 space-y-4 flex-1 overflow-y-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Template Name *</label>
                        <input type="text" name="name" id="tplName" required placeholder="e.g. Welcome Email"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Subject *</label>
                        <input type="text" name="subject" id="tplSubject" required placeholder="e.g. Welcome, {name}!"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm">
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-gray-700">HTML Body *</label>
                        <div class="flex gap-2 text-xs">
                            <button type="button" onclick="insertSnippet('{name}')" class="px-2 py-1 bg-gray-100 text-gray-600 rounded hover:bg-gray-200">{name}</button>
                            <button type="button" onclick="insertSnippet('{phone}')" class="px-2 py-1 bg-gray-100 text-gray-600 rounded hover:bg-gray-200">{phone}</button>
                            <button type="button" onclick="insertSnippet('{email}')" class="px-2 py-1 bg-gray-100 text-gray-600 rounded hover:bg-gray-200">{email}</button>
                            <button type="button" onclick="previewHtml()" class="px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">Preview</button>
                        </div>
                    </div>
                    <textarea name="html_body" id="tplHtmlBody" required rows="14"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black text-sm font-mono resize-y"
                        placeholder="<h2>Hello {name},</h2><p>Thank you for your interest!</p>"></textarea>
                </div>
            </div>
            <div class="p-5 border-t border-gray-100 flex gap-3">
                <button type="button" onclick="hideModal('tplModal')"
                    class="flex-1 py-3 bg-gray-200 rounded-xl font-medium text-sm hover:bg-gray-300 transition-colors">Cancel</button>
                <button type="submit"
                    class="flex-1 py-3 bg-black text-white rounded-xl font-medium text-sm hover:bg-gray-800 transition-colors">Save Template</button>
            </div>
        </form>
    </div>
</div>

<!-- HTML Preview Modal -->
<div id="previewModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
        <div class="p-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-lg font-bold">Email Preview</h2>
            <button onclick="hideModal('previewModal')" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="flex-1 overflow-hidden">
            <iframe id="previewFrame" class="w-full h-full border-none" style="min-height:400px"></iframe>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('tplId').value = '0';
    document.getElementById('tplName').value = '';
    document.getElementById('tplSubject').value = '';
    document.getElementById('tplHtmlBody').value = '';
    document.getElementById('tplModalTitle').textContent = 'New Email Template';
}

function editTemplate(tpl) {
    document.getElementById('tplId').value = tpl.id;
    document.getElementById('tplName').value = tpl.name;
    document.getElementById('tplSubject').value = tpl.subject;
    document.getElementById('tplHtmlBody').value = tpl.html_body;
    document.getElementById('tplModalTitle').textContent = 'Edit Template';
    showModal('tplModal');
}

function insertSnippet(snippet) {
    const ta = document.getElementById('tplHtmlBody');
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    ta.value = ta.value.substring(0, start) + snippet + ta.value.substring(end);
    ta.focus();
    ta.setSelectionRange(start + snippet.length, start + snippet.length);
}

function previewHtml() {
    const html = document.getElementById('tplHtmlBody').value;
    const preview = html
        .replace(/\{name\}/g, 'John Doe')
        .replace(/\{phone\}/g, '+91 9876543210')
        .replace(/\{email\}/g, 'john@example.com');
    const frame = document.getElementById('previewFrame');
    frame.srcdoc = preview;
    showModal('previewModal');
}

function previewTemplate(id) {
    fetch('<?= APP_URL ?>/api/email.php?action=get_template&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.template) {
                const html = data.template.html_body
                    .replace(/\{name\}/g, 'John Doe')
                    .replace(/\{phone\}/g, '+91 9876543210')
                    .replace(/\{email\}/g, 'john@example.com');
                document.getElementById('previewFrame').srcdoc = html;
                showModal('previewModal');
            }
        });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
