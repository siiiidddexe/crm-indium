<?php
$pageTitle = 'Super Admin - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireSuperAdmin();

$totalAdmins    = db()->fetch("SELECT COUNT(*) as c FROM users WHERE role='admin'")['c'];
$totalTeamLeads = db()->fetch("SELECT COUNT(*) as c FROM users WHERE role='teamlead'")['c'];
$totalEmployees = db()->fetch("SELECT COUNT(*) as c FROM users WHERE role='employee'")['c'];
$totalContacts  = db()->fetch("SELECT COUNT(*) as c FROM contacts")['c'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-black">Super Admin Panel</h1>
        <p class="text-gray-500 mt-1">Manage admins, control feature visibility, configure integrations</p>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-sm text-gray-500 mb-1">Admins</p>
            <p class="text-3xl font-bold text-black"><?= $totalAdmins ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-sm text-gray-500 mb-1">Team Leads</p>
            <p class="text-3xl font-bold text-black"><?= $totalTeamLeads ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-sm text-gray-500 mb-1">Employees</p>
            <p class="text-3xl font-bold text-black"><?= $totalEmployees ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-sm text-gray-500 mb-1">Total Contacts</p>
            <p class="text-3xl font-bold text-black"><?= number_format($totalContacts) ?></p>
        </div>
    </div>

    <!-- Navigation Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="<?= APP_URL ?>/superadmin/admins.php"
            class="bg-white rounded-2xl border border-gray-200 p-6 hover:border-black hover:shadow-md transition-all group">
            <div class="w-12 h-12 bg-black text-white rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-black mb-1">Manage Admins</h3>
            <p class="text-sm text-gray-500">Add, edit, or remove admin accounts</p>
        </a>

        <a href="<?= APP_URL ?>/superadmin/features.php"
            class="bg-white rounded-2xl border border-gray-200 p-6 hover:border-black hover:shadow-md transition-all group">
            <div class="w-12 h-12 bg-purple-600 text-white rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-black mb-1">Feature Visibility</h3>
            <p class="text-sm text-gray-500">Toggle sidebar nav items and buttons per role</p>
        </a>

        <a href="<?= APP_URL ?>/admin/email_templates.php"
            class="bg-white rounded-2xl border border-gray-200 p-6 hover:border-black hover:shadow-md transition-all group">
            <div class="w-12 h-12 bg-blue-600 text-white rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-black mb-1">Email Templates</h3>
            <p class="text-sm text-gray-500">Create and manage custom HTML email templates</p>
        </a>

        <a href="<?= APP_URL ?>/admin/settings.php"
            class="bg-white rounded-2xl border border-gray-200 p-6 hover:border-black hover:shadow-md transition-all group">
            <div class="w-12 h-12 bg-gray-700 text-white rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-black mb-1">CRM Settings</h3>
            <p class="text-sm text-gray-500">Configure NexoMailer, auto-assign, and more</p>
        </a>

        <a href="<?= APP_URL ?>/admin/plugins.php"
            class="bg-white rounded-2xl border border-gray-200 p-6 hover:border-black hover:shadow-md transition-all group">
            <div class="w-12 h-12 bg-green-600 text-white rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-black mb-1">Integrations</h3>
            <p class="text-sm text-gray-500">Google Sheets, Meta Ads, Google Ads plugins</p>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
