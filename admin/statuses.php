<?php
$pageTitle = 'Call Statuses - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $color = $_POST['color'] ?? '#6b7280';
        $order = db()->fetch("SELECT MAX(sort_order) as max_order FROM call_statuses")['max_order'] + 1;

        if ($name) {
            db()->insert("INSERT INTO call_statuses (name, color, sort_order) VALUES (?, ?, ?)", [$name, $color, $order]);
            setFlash('success', 'Status added successfully!');
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? 0;
        $name = sanitize($_POST['name'] ?? '');
        $color = $_POST['color'] ?? '#6b7280';

        if ($id && $name) {
            db()->update("UPDATE call_statuses SET name = ?, color = ? WHERE id = ?", [$name, $color, $id]);
            setFlash('success', 'Status updated successfully!');
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            db()->delete("DELETE FROM call_statuses WHERE id = ?", [$id]);
            setFlash('success', 'Status deleted.');
        }
    }

    header('Location: statuses.php');
    exit;
}

$statuses = getCallStatuses();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-black">Call Statuses</h1>
            <p class="text-gray-500 mt-1">Manage call outcome statuses</p>
        </div>
        <button onclick="showModal('addModal')"
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Status
        </button>
    </div>

    <!-- Statuses Grid -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($statuses as $status): ?>
            <div class="bg-white rounded-2xl border border-gray-200 p-4 card-hover">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center"
                        style="background-color: <?= $status['color'] ?>20">
                        <div class="w-6 h-6 rounded-full" style="background-color: <?= $status['color'] ?>"></div>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-medium">
                            <?= sanitize($status['name']) ?>
                        </h3>
                        <p class="text-sm text-gray-500">
                            <?= $status['color'] ?>
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <button onclick="editStatus(<?= htmlspecialchars(json_encode($status)) ?>)"
                            class="p-2 hover:bg-gray-100 rounded-lg">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this status?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $status['id'] ?>">
                            <button type="submit" class="p-2 hover:bg-red-50 rounded-lg">
                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Status Modal -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4"
    data-modal-backdrop>
    <div class="bg-white rounded-2xl w-full max-w-md">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-black">Add Status</h2>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status Name *</label>
                <input type="text" name="name" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black"
                    placeholder="e.g., Interested">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Color</label>
                <div class="flex gap-3">
                    <input type="color" name="color" value="#22c55e"
                        class="w-12 h-12 rounded-xl border border-gray-300 cursor-pointer">
                    <div class="flex-1 grid grid-cols-6 gap-2">
                        <?php
                        $colors = ['#22c55e', '#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6', '#ec4899', '#6b7280', '#14b8a6'];
                        foreach ($colors as $color):
                            ?>
                            <button type="button" onclick="this.form.color.value='<?= $color ?>'"
                                class="w-8 h-8 rounded-lg hover:ring-2 ring-offset-2 ring-black"
                                style="background-color: <?= $color ?>"></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="hideModal('addModal')"
                    class="flex-1 px-4 py-3 border border-gray-300 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 px-4 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
                    Add Status
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Status Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4"
    data-modal-backdrop>
    <div class="bg-white rounded-2xl w-full max-w-md">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-black">Edit Status</h2>
        </div>
        <form method="POST" id="editForm" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status Name *</label>
                <input type="text" name="name" id="editName" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Color</label>
                <input type="color" name="color" id="editColor"
                    class="w-full h-12 rounded-xl border border-gray-300 cursor-pointer">
            </div>

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
    function editStatus(status) {
        document.getElementById('editId').value = status.id;
        document.getElementById('editName').value = status.name;
        document.getElementById('editColor').value = status.color;
        showModal('editModal');
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>