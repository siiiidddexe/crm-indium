<?php
$pageTitle = 'My Profile - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAuth();

$userId = $_SESSION['user_id'];
$user = currentUser();
$languages = getLanguages();
$userLangs = getUserLanguages($userId);
$userLangIds = array_column($userLangs, 'id');

// Handle language save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_languages') {
    $selectedLangs = $_POST['languages'] ?? [];
    
    db()->delete("DELETE FROM user_languages WHERE user_id = ?", [$userId]);
    
    $stmt = db()->getConnection()->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
    foreach ($selectedLangs as $langId) {
        $stmt->execute([$userId, intval($langId)]);
    }
    
    setFlash('success', 'Languages updated successfully!');
    header('Location: profile.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-black">My Profile</h1>
        <p class="text-gray-500 mt-1">Manage your profile and language preferences</p>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <!-- Profile Info -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-lg font-bold text-black">Profile Information</h2>
            </div>
            <div class="p-6">
                <div class="flex items-center gap-5 mb-6">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-gray-800 to-black text-white flex items-center justify-center font-bold text-3xl shadow-lg">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-black"><?= sanitize($user['name']) ?></h3>
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-medium mt-1 <?= $user['role'] === 'teamlead' ? 'bg-purple-100 text-purple-700' : ($user['role'] === 'admin' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700') ?>">
                            <?= ucfirst($user['role']) ?>
                        </span>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <div>
                            <p class="text-xs text-gray-500">Email</p>
                            <p class="font-medium"><?= sanitize($user['email']) ?></p>
                        </div>
                    </div>
                    
                    <?php if ($user['phone']): ?>
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        <div>
                            <p class="text-xs text-gray-500">Phone</p>
                            <p class="font-medium"><?= sanitize($user['phone']) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <div>
                            <p class="text-xs text-gray-500">Member Since</p>
                            <p class="font-medium"><?= formatDate($user['created_at']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Languages Known -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-lg font-bold text-black">Languages Known</h2>
                <p class="text-sm text-gray-500 mt-1">Select all languages you can communicate in</p>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="save_languages">
                
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <?php foreach ($languages as $lang): ?>
                    <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors border-2 border-transparent has-[:checked]:border-black has-[:checked]:bg-gray-100">
                        <input type="checkbox" name="languages[]" value="<?= $lang['id'] ?>" 
                               <?= in_array($lang['id'], $userLangIds) ? 'checked' : '' ?>
                               class="w-5 h-5 rounded border-gray-300 text-black focus:ring-black">
                        <span class="font-medium text-sm"><?= sanitize($lang['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" class="w-full px-4 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
                    Save Languages
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
