<?php
require_once __DIR__ . '/../config/config.php';
$currentPage = basename($_SERVER['PHP_SELF']);
// Load feature flags once for nav visibility
$_ff = isLoggedIn() ? getAllFeatureFlags() : [];
function ff(string $key, int $default = 1): int {
    global $_ff;
    return isset($_ff[$key]) ? (int)$_ff[$key] : $default;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Obsiguard CRM' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        .card-hover {
            transition: all 0.2s;
        }

        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .pulse-call {
            animation: pulse-ring 2s ease infinite;
        }

        @keyframes pulse-ring {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4);
            }

            50% {
                box-shadow: 0 0 0 15px rgba(34, 197, 94, 0);
            }
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fade-in 0.3s ease-out;
        }

        /* Skeleton Loading Animation */
        @keyframes skeleton-loading {
            0% {
                background-position: -200px 0;
            }
            100% {
                background-position: calc(200px + 100%) 0;
            }
        }

        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200px 100%;
            animation: skeleton-loading 1.5s ease-in-out infinite;
        }

        #pageLoader {
            transition: opacity 0.3s ease-out, visibility 0.3s ease-out;
        }

        #pageLoader.hidden {
            opacity: 0;
            visibility: hidden;
        }

        /* Ensure full width responsiveness */
        * {
            box-sizing: border-box;
        }

        html, body {
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
        }

        main {
            width: auto;
            max-width: 100%;
        }

        /* Prevent any direct child of main from overflowing */
        main > div {
            max-width: 100%;
        }

        /* Fix all container widths */
        .container, .grid {
            max-width: 100%;
        }

        /* Flex containers should not push beyond parent */
        .flex {
            min-width: 0;
        }

        /* Flex children must shrink */
        .flex > * {
            min-width: 0;
        }

        /* Grid children must not overflow */
        .grid > * {
            min-width: 0;
        }

        /* Truncate long text in table cells */
        td, th {
            word-break: break-word;
        }

        /* Images should not overflow */
        img, video, canvas {
            max-width: 100%;
            height: auto;
        }

        /* Mobile-first responsive utilities */
        @media (max-width: 640px) {
            .responsive-text-sm {
                font-size: 0.875rem;
            }
            .responsive-padding-sm {
                padding: 1rem;
            }

            /* Reduce padding on mobile to prevent overflow */
            .p-4 {
                padding: 0.75rem !important;
            }

            .p-6 {
                padding: 1rem !important;
            }

            .p-8 {
                padding: 0.75rem !important;
            }

            .px-4 {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }

            .px-6 {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }

            .px-8 {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }

            /* Reduce large gaps on mobile */
            .gap-6 {
                gap: 1rem !important;
            }

            .gap-8 {
                gap: 1rem !important;
            }
        }

        /* Mobile button stacking */
        @media (max-width: 640px) {
            .flex.flex-wrap.gap-2,
            .flex.flex-wrap.gap-3 {
                width: 100%;
            }

            /* Ensure buttons don't overflow on mobile */
            button, .btn, select, input[type="date"], input[type="text"] {
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            /* Mobile table improvements */
            table {
                font-size: 0.875rem;
            }

            th, td {
                padding: 0.5rem !important;
            }
        }

        /* Improve modal responsiveness */
        [data-modal-backdrop] {
            padding: 1rem;
        }

        @media (max-width: 640px) {
            [data-modal-backdrop] > div {
                max-height: 90vh;
                overflow-y: auto;
                width: calc(100% - 2rem);
                margin: 0 auto;
            }
        }

        /* Fix table overflow */
        .overflow-x-auto {
            max-width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <!-- Page Skeleton Loader -->
    <div id="pageLoader" class="fixed inset-0 bg-gray-50 z-[9999] overflow-hidden">
        <!-- Sidebar Skeleton (only on desktop) -->
        <div class="hidden lg:block fixed left-0 top-0 w-72 bg-white border-r border-gray-200 p-6 h-screen overflow-y-auto">
            <!-- Logo skeleton -->
            <div class="h-8 w-40 skeleton rounded-lg mb-8"></div>

            <!-- User info skeleton -->
            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                <div class="w-12 h-12 skeleton rounded-full"></div>
                <div class="flex-1">
                    <div class="h-4 skeleton rounded w-3/4 mb-2"></div>
                    <div class="h-3 skeleton rounded w-1/2"></div>
                </div>
            </div>

            <!-- Navigation items skeleton -->
            <div class="space-y-2">
                <div class="h-10 skeleton rounded-xl"></div>
                <div class="h-10 skeleton rounded-xl"></div>
                <div class="h-10 skeleton rounded-xl"></div>
                <div class="h-10 skeleton rounded-xl"></div>
                <div class="h-10 skeleton rounded-xl"></div>
                <div class="h-10 skeleton rounded-xl"></div>
            </div>
        </div>

        <!-- Main Content Skeleton -->
        <div class="lg:ml-72 pt-16 lg:pt-0 min-h-screen p-4 sm:p-6 lg:p-8">
            <!-- Mobile header skeleton -->
            <div class="lg:hidden mb-6">
                <div class="h-8 skeleton rounded w-48 mb-2"></div>
                <div class="h-4 skeleton rounded w-64"></div>
            </div>

            <!-- Desktop header skeleton -->
            <div class="hidden lg:block mb-6">
                <div class="h-10 skeleton rounded w-80 mb-2"></div>
                <div class="h-5 skeleton rounded w-96"></div>
            </div>

            <!-- Stats cards skeleton -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6">
                    <div class="h-8 skeleton rounded w-20 mb-2 mx-auto"></div>
                    <div class="h-4 skeleton rounded w-16 mx-auto"></div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6">
                    <div class="h-8 skeleton rounded w-20 mb-2 mx-auto"></div>
                    <div class="h-4 skeleton rounded w-16 mx-auto"></div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 hidden sm:block">
                    <div class="h-8 skeleton rounded w-20 mb-2 mx-auto"></div>
                    <div class="h-4 skeleton rounded w-16 mx-auto"></div>
                </div>
            </div>

            <!-- Table skeleton -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4 sm:p-6">
                <div class="space-y-3">
                    <div class="h-10 skeleton rounded"></div>
                    <div class="h-10 skeleton rounded"></div>
                    <div class="h-10 skeleton rounded"></div>
                    <div class="h-10 skeleton rounded hidden sm:block"></div>
                    <div class="h-10 skeleton rounded hidden sm:block"></div>
                    <div class="h-10 skeleton rounded hidden lg:block"></div>
                    <div class="h-10 skeleton rounded hidden lg:block"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Hide loader when page is fully loaded
        window.addEventListener('load', function() {
            const loader = document.getElementById('pageLoader');
            if (loader) {
                setTimeout(() => {
                    loader.classList.add('hidden');
                }, 200); // Small delay for smooth transition
            }
        });

        // Show loader on navigation
        document.addEventListener('DOMContentLoaded', function() {
            // Show loader when clicking links (except external links and anchors)
            document.addEventListener('click', function(e) {
                const link = e.target.closest('a');
                if (link &&
                    link.href &&
                    !link.href.startsWith('tel:') &&
                    !link.href.startsWith('mailto:') &&
                    !link.href.startsWith('#') &&
                    !link.target &&
                    link.href.indexOf(window.location.host) !== -1) {

                    const loader = document.getElementById('pageLoader');
                    if (loader) {
                        loader.classList.remove('hidden');
                    }
                }
            });

            // Show loader on form submit
            document.addEventListener('submit', function(e) {
                const form = e.target;
                if (form && !form.hasAttribute('data-no-loader')) {
                    const loader = document.getElementById('pageLoader');
                    if (loader) {
                        loader.classList.remove('hidden');
                    }
                }
            });
        });
    </script>

    <?php if (isLoggedIn()): ?>
        <!-- Mobile Header -->
        <header
            class="lg:hidden fixed top-0 left-0 right-0 h-16 bg-white border-b border-gray-200 z-40 flex items-center justify-between px-4">
            <button id="mobileMenuBtn" class="p-2 hover:bg-gray-100 rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <svg viewBox="0 0 200 40" class="h-7 w-auto" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="shieldGradientMobile" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#1e40af;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#3b82f6;stop-opacity:1" />
                    </linearGradient>
                </defs>
                <path d="M12 4L4 8v6c0 5.5 3.8 10.7 8 12 4.2-1.3 8-6.5 8-12V8l-8-4z"
                      fill="url(#shieldGradientMobile)"
                      transform="translate(0, 8)"/>
                <path d="M12 10l-3 3 3 3"
                      stroke="white"
                      stroke-width="1.5"
                      fill="none"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      transform="translate(0, 8)"/>
                <text x="28" y="28" font-family="system-ui, -apple-system, sans-serif" font-size="18" font-weight="700" fill="#111827" letter-spacing="-0.5">OBSI</text>
                <text x="75" y="28" font-family="system-ui, -apple-system, sans-serif" font-size="18" font-weight="700" fill="#1e40af" letter-spacing="-0.5">GUARD</text>
                <rect x="146" y="15" width="50" height="16" rx="8" fill="#dbeafe"/>
                <text x="171" y="26" font-family="system-ui, -apple-system, sans-serif" font-size="10" font-weight="600" fill="#1e40af" text-anchor="middle">CRM</text>
            </svg>
            <a href="<?= APP_URL ?>/logout.php" class="p-2 hover:bg-gray-100 rounded-xl text-gray-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
            </a>
        </header>

        <!-- Sidebar Overlay -->
        <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden"></div>

        <!-- Sidebar -->
        <aside id="sidebar"
            class="fixed top-0 left-0 h-full w-72 bg-white border-r border-gray-200 z-50 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 flex flex-col">
            <!-- Logo -->
            <div class="h-16 flex items-center px-6 border-b border-gray-100">
                <svg viewBox="0 0 200 40" class="h-8 w-auto" xmlns="http://www.w3.org/2000/svg">
                    <!-- Shield Icon -->
                    <defs>
                        <linearGradient id="shieldGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#1e40af;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#3b82f6;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                    <path d="M12 4L4 8v6c0 5.5 3.8 10.7 8 12 4.2-1.3 8-6.5 8-12V8l-8-4z"
                          fill="url(#shieldGradient)"
                          transform="translate(0, 8)"/>
                    <path d="M12 10l-3 3 3 3"
                          stroke="white"
                          stroke-width="1.5"
                          fill="none"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          transform="translate(0, 8)"/>

                    <!-- OBSIGUARD Text -->
                    <text x="28" y="28" font-family="system-ui, -apple-system, sans-serif" font-size="18" font-weight="700" fill="#111827" letter-spacing="-0.5">OBSI</text>
                    <text x="75" y="28" font-family="system-ui, -apple-system, sans-serif" font-size="18" font-weight="700" fill="#1e40af" letter-spacing="-0.5">GUARD</text>

                    <!-- CRM Badge -->
                    <rect x="146" y="15" width="50" height="16" rx="8" fill="#dbeafe"/>
                    <text x="171" y="26" font-family="system-ui, -apple-system, sans-serif" font-size="10" font-weight="600" fill="#1e40af" text-anchor="middle">CRM</text>
                </svg>
            </div>

            <!-- User Info -->
            <div class="p-4 border-b border-gray-100">
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                    <div class="w-10 h-10 rounded-full bg-black text-white flex items-center justify-center font-bold">
                        <?= strtoupper(substr(currentUser()['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm truncate"><?= sanitize(currentUser()['name'] ?? '') ?></p>
                        <p class="text-xs text-gray-500 capitalize"><?= userRole() ?></p>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto p-4 space-y-1">
                <?php if (isSuperAdmin()): ?>
                    <a href="<?= APP_URL ?>/superadmin/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '/superadmin/') !== false ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span>Dashboard</span>
                    </a>
                    <a href="<?= APP_URL ?>/superadmin/admins.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'admins.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Manage Admins</span>
                    </a>
                    <a href="<?= APP_URL ?>/superadmin/features.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'features.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                        <span>Feature Visibility</span>
                    </a>
                    <a href="<?= APP_URL ?>/admin/email_templates.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'email_templates.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>Email Templates</span>
                    </a>
                    <a href="<?= APP_URL ?>/admin/settings.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'settings.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Settings</span>
                    </a>
                    <a href="<?= APP_URL ?>/admin/plugins.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'plugins.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
                        </svg>
                        <span>Plugins</span>
                    </a>

                <?php elseif (isAdmin()): ?>
                    <a href="<?= APP_URL ?>/admin/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span>Dashboard</span>
                    </a>
                    <?php if (ff('nav_admin_employees')): ?>
                    <a href="<?= APP_URL ?>/admin/employees.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'employees.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <span>Employees</span>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_admin_teamleads')): ?>
                    <a href="<?= APP_URL ?>/admin/teamleads.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'teamleads.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>Team Leads</span>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_admin_import')): ?>
                    <a href="<?= APP_URL ?>/admin/import.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'import.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span>Import & Manage</span>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_admin_reports')): ?>
                    <a href="<?= APP_URL ?>/admin/reports.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'reports.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <span>Reports</span>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_admin_attendance')): ?>
                    <a href="<?= APP_URL ?>/admin/attendance.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'attendance.php' && strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>Attendance</span>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_admin_statuses')): ?>
                    <a href="<?= APP_URL ?>/admin/statuses.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'statuses.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        <span>Call Statuses</span>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_admin_language')): ?>
                    <?php $adminPendingLang = getPendingMoveRequestsCount(); ?>
                    <a href="<?= APP_URL ?>/admin/language_conflicts.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'language_conflicts.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                        </svg>
                        <span>Language Conflicts</span>
                        <?php if ($adminPendingLang > 0): ?>
                            <span class="ml-auto px-2 py-0.5 bg-red-500 text-white text-xs rounded-full font-bold"><?= $adminPendingLang ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_admin_templates')): ?>
                    <a href="<?= APP_URL ?>/admin/templates.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'templates.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                        <span>WA Templates</span>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_admin_email_templates')): ?>
                    <a href="<?= APP_URL ?>/admin/email_templates.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'email_templates.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>Email Templates</span>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_admin_plugins')): ?>
                    <a href="<?= APP_URL ?>/admin/plugins.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'plugins.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
                        </svg>
                        <span>Plugins</span>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_admin_settings')): ?>
                    <a href="<?= APP_URL ?>/admin/settings.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'settings.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Settings</span>
                    </a>
                    <?php endif; ?>

                <?php elseif (isTeamLead()): ?>
                    <a href="<?= APP_URL ?>/teamlead/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'index.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span>Dashboard</span>
                    </a>
                    <?php if (ff('nav_tl_contacts')): ?>
                    <a href="<?= APP_URL ?>/teamlead/contacts.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'contacts.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>My Contacts</span>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_tl_calls')): ?>
                    <a href="<?= APP_URL ?>/teamlead/calls.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'calls.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <span>Calling Cards</span>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_tl_team')): ?>
                    <a href="<?= APP_URL ?>/teamlead/team.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'team.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <span>My Team</span>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_tl_reports')): ?>
                    <a href="<?= APP_URL ?>/teamlead/reports.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'reports.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <span>Reports</span>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_tl_language')): ?>
                    <?php $tlPendingLang = getPendingMoveRequestsCount($_SESSION['user_id']); ?>
                    <a href="<?= APP_URL ?>/teamlead/language_conflicts.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'language_conflicts.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                        </svg>
                        <span>Language Conflicts</span>
                        <?php if ($tlPendingLang > 0): ?>
                            <span class="ml-auto px-2 py-0.5 bg-red-500 text-white text-xs rounded-full font-bold"><?= $tlPendingLang ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_tl_attendance')): ?>
                    <a href="<?= APP_URL ?>/teamlead/attendance.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'attendance.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>My Attendance</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= APP_URL ?>/employee/profile.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'profile.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>My Profile</span>
                    </a>

                <?php else: ?>
                    <a href="<?= APP_URL ?>/employee/index.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'index.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span>Dashboard</span>
                    </a>
                    <?php if (ff('nav_emp_calls')): ?>
                    <a href="<?= APP_URL ?>/employee/calls.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'calls.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <span>Calling Cards</span>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_emp_language')): ?>
                    <?php $empPendingLang = db()->fetch("SELECT COUNT(*) as count FROM language_move_requests WHERE (requested_by = ? OR assigned_to = ?) AND status = 'pending'", [$_SESSION['user_id'], $_SESSION['user_id']])['count']; ?>
                    <a href="<?= APP_URL ?>/employee/language_conflicts.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'language_conflicts.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                        </svg>
                        <span>Language Conflicts</span>
                        <?php if ($empPendingLang > 0): ?>
                            <span class="ml-auto px-2 py-0.5 bg-red-500 text-white text-xs rounded-full font-bold"><?= $empPendingLang ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_emp_attendance')): ?>
                    <a href="<?= APP_URL ?>/employee/attendance.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'attendance.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>My Attendance</span>
                    </a>
                    <?php endif; ?>
                    <?php if (ff('nav_emp_profile')): ?>
                    <a href="<?= APP_URL ?>/employee/profile.php"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $currentPage === 'profile.php' ? 'bg-black text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>My Profile</span>
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>

            <!-- Version & Company Info -->
            <div class="mt-auto p-4 border-t border-gray-100">
                <div class="px-3 py-3 bg-gray-50 rounded-xl">
                    <p class="text-xs font-semibold text-gray-600 mb-1">Version Code: V2.2.12</p>
                    <p class="text-[10px] text-gray-400 leading-tight">© 2026 Logic Launch Software Solutions</p>
                </div>
            </div>

            <!-- Logout -->
            <div class="p-4 border-t border-gray-100">
                <a href="<?= APP_URL ?>/logout.php"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-600 hover:bg-red-50 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="lg:ml-72 pt-16 lg:pt-0 min-h-screen">
        <?php endif; ?>

        <!-- Flash Messages -->
        <?php if ($flash = getFlash('success')): ?>
            <div class="fixed top-20 lg:top-4 right-4 z-50 animate-fade-in">
                <div
                    class="flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-xl shadow-lg">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="text-sm font-medium"><?= sanitize($flash) ?></span>
                    <button onclick="this.parentElement.parentElement.remove()"
                        class="ml-2 text-green-500 hover:text-green-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($flash = getFlash('error')): ?>
            <div class="fixed top-20 lg:top-4 right-4 z-50 animate-fade-in">
                <div
                    class="flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl shadow-lg">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm font-medium"><?= sanitize($flash) ?></span>
                    <button onclick="this.parentElement.parentElement.remove()"
                        class="ml-2 text-red-500 hover:text-red-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        <?php endif; ?>