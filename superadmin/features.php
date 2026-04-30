<?php
$pageTitle = 'Feature Visibility - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_flags') {
    $submitted = $_POST['flags'] ?? [];
    // Get all flag keys
    $allFlags = db()->fetchAll("SELECT flag_key FROM feature_flags");
    foreach ($allFlags as $flag) {
        $key     = $flag['flag_key'];
        $enabled = isset($submitted[$key]) ? 1 : 0;
        db()->update("UPDATE feature_flags SET is_enabled = ? WHERE flag_key = ?", [$enabled, $key]);
    }
    setFlash('success', 'Feature visibility saved!');
    header('Location: features.php'); exit;
}

$flags = db()->fetchAll("SELECT * FROM feature_flags ORDER BY category, label");

// Group by category
$grouped = [];
foreach ($flags as $flag) {
    $grouped[$flag['category']][] = $flag;
}

$categoryLabels = [
    'buttons'      => '🔘 Calling Card Buttons',
    'nav_admin'    => '🛠️ Admin Navigation',
    'nav_employee' => '👤 Employee Navigation',
    'nav_teamlead' => '👥 Team Lead Navigation',
];

$flashMsg = getFlash();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-1">
            <a href="<?= APP_URL ?>/superadmin/index.php" class="text-gray-400 hover:text-black text-sm">Super Admin</a>
            <span class="text-gray-300">/</span>
            <span class="text-sm font-medium">Feature Visibility</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold text-black">Feature Visibility</h1>
        <p class="text-gray-500 mt-1">Toggle which navigation items and buttons appear for each role</p>
    </div>

    <?php if ($flashMsg): ?>
        <div class="mb-4 p-4 rounded-xl bg-green-50 text-green-800 border border-green-200"><?= sanitize($flashMsg) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="action" value="save_flags">

        <div class="space-y-6">
            <?php foreach ($categoryLabels as $catKey => $catLabel): ?>
                <?php if (empty($grouped[$catKey])): continue; endif; ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-base font-bold text-black mb-4 border-b border-gray-100 pb-3"><?= $catLabel ?></h2>
                    <div class="space-y-3">
                        <?php foreach ($grouped[$catKey] as $flag): ?>
                            <div class="flex items-center justify-between py-2">
                                <div>
                                    <p class="font-medium text-sm text-black"><?= sanitize($flag['label']) ?></p>
                                    <p class="text-xs text-gray-400 font-mono"><?= sanitize($flag['flag_key']) ?></p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="flags[<?= sanitize($flag['flag_key']) ?>]"
                                        class="sr-only peer"
                                        <?= $flag['is_enabled'] ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-black"></div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-6">
            <button type="submit"
                class="w-full sm:w-auto px-8 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
                Save All Changes
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
