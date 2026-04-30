<?php
$pageTitle = 'WhatsApp Templates - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $isDefault = isset($_POST['is_default']) ? 1 : 0;

        if ($name && $message) {
            if ($isDefault) {
                db()->update("UPDATE whatsapp_templates SET is_default = 0");
            }
            db()->insert("INSERT INTO whatsapp_templates (name, message, is_default) VALUES (?, ?, ?)", [$name, $message, $isDefault]);
            setFlash('success', 'Template added successfully!');
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? 0;
        $name = sanitize($_POST['name'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $isDefault = isset($_POST['is_default']) ? 1 : 0;

        if ($id && $name && $message) {
            if ($isDefault) {
                db()->update("UPDATE whatsapp_templates SET is_default = 0");
            }
            db()->update("UPDATE whatsapp_templates SET name = ?, message = ?, is_default = ? WHERE id = ?", [$name, $message, $isDefault, $id]);
            setFlash('success', 'Template updated successfully!');
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            db()->delete("DELETE FROM whatsapp_templates WHERE id = ?", [$id]);
            setFlash('success', 'Template deleted.');
        }
    }

    header('Location: templates.php');
    exit;
}

$templates = getWhatsAppTemplates();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-black">WhatsApp Templates</h1>
            <p class="text-gray-500 mt-1">Manage message templates for employees</p>
        </div>
        <button onclick="showModal('addModal')"
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Template
        </button>
    </div>

    <!-- Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
        <p class="text-sm text-blue-700">
            <strong>Tip:</strong> Use <code class="bg-blue-100 px-1 rounded">{name}</code> as a placeholder — it will be replaced with the contact's name when sending.
        </p>
    </div>

    <!-- Templates Grid -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($templates as $tpl): ?>
            <div class="bg-white rounded-2xl border border-gray-200 p-5 card-hover <?= $tpl['is_default'] ? 'ring-2 ring-black' : '' ?>">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h3 class="font-bold text-lg"><?= sanitize($tpl['name']) ?></h3>
                        <?php if ($tpl['is_default']): ?>
                        <span class="inline-block px-2 py-0.5 bg-black text-white text-xs rounded-full font-medium mt-1">Default</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-1">
                        <button onclick='editTemplate(<?= json_encode($tpl) ?>)'
                            class="p-2 hover:bg-gray-100 rounded-lg">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this template?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $tpl['id'] ?>">
                            <button type="submit" class="p-2 hover:bg-red-50 rounded-lg">
                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-xl p-3">
                    <p class="text-sm text-gray-600 whitespace-pre-wrap"><?= sanitize($tpl['message']) ?></p>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($templates)): ?>
        <div class="col-span-full bg-white rounded-2xl border border-gray-200 p-12 text-center text-gray-500">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
            <p class="text-lg font-medium">No templates yet</p>
            <p class="text-sm mt-1">Create your first WhatsApp message template</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Template Modal -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4" data-modal-backdrop>
    <div class="bg-white rounded-2xl w-full max-w-lg">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-black">Add Template</h2>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Template Name *</label>
                <input type="text" name="name" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black"
                    placeholder="e.g., Welcome Message">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Message *</label>
                <textarea name="message" required rows="5"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black resize-none"
                    placeholder="Hello {name}, welcome to our service..."></textarea>
            </div>
            <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl cursor-pointer">
                <input type="checkbox" name="is_default" class="w-5 h-5 rounded border-gray-300 text-black focus:ring-black">
                <span class="text-sm font-medium">Set as default template</span>
            </label>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="hideModal('addModal')"
                    class="flex-1 px-4 py-3 border border-gray-300 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 px-4 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
                    Add Template
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Template Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4" data-modal-backdrop>
    <div class="bg-white rounded-2xl w-full max-w-lg">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-black">Edit Template</h2>
        </div>
        <form method="POST" id="editForm" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Template Name *</label>
                <input type="text" name="name" id="editName" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Message *</label>
                <textarea name="message" id="editMessage" required rows="5"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black resize-none"></textarea>
            </div>
            <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl cursor-pointer">
                <input type="checkbox" name="is_default" id="editDefault" class="w-5 h-5 rounded border-gray-300 text-black focus:ring-black">
                <span class="text-sm font-medium">Set as default template</span>
            </label>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="hideModal('editModal')"
                    class="flex-1 px-4 py-3 border border-gray-300 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 px-4 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editTemplate(tpl) {
    document.getElementById('editId').value = tpl.id;
    document.getElementById('editName').value = tpl.name;
    document.getElementById('editMessage').value = tpl.message;
    document.getElementById('editDefault').checked = tpl.is_default == 1;
    showModal('editModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
