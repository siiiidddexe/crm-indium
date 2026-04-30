<?php
$pageTitle = 'Login - Obsiguard CRM';
require_once __DIR__ . '/config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $user = db()->fetch("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            redirectByRole();
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please enter email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
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
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center p-4">
    <!-- Page Skeleton Loader -->
    <div id="pageLoader" class="fixed inset-0 bg-gradient-to-br from-gray-50 to-gray-100 z-[9999] flex items-center justify-center">
        <div class="w-full max-w-md">
            <!-- Logo skeleton -->
            <div class="text-center mb-8">
                <div class="h-16 w-48 skeleton rounded-2xl mx-auto mb-4"></div>
                <div class="h-4 w-40 skeleton rounded mx-auto"></div>
            </div>

            <!-- Card skeleton -->
            <div class="bg-white rounded-2xl border border-gray-200 p-8">
                <div class="space-y-5">
                    <div>
                        <div class="h-4 skeleton rounded w-32 mb-2"></div>
                        <div class="h-12 skeleton rounded-xl"></div>
                    </div>
                    <div>
                        <div class="h-4 skeleton rounded w-20 mb-2"></div>
                        <div class="h-12 skeleton rounded-xl"></div>
                    </div>
                    <div class="h-12 skeleton rounded-xl"></div>
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
                }, 200);
            }
        });

        // Show loader on form submit
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function() {
                    const loader = document.getElementById('pageLoader');
                    if (loader) {
                        loader.classList.remove('hidden');
                    }
                });
            }
        });
    </script>
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="mb-6 flex justify-center">
                <svg viewBox="0 0 280 60" class="h-16 w-auto" xmlns="http://www.w3.org/2000/svg">
                    <!-- Shield Icon -->
                    <defs>
                        <linearGradient id="shieldGradientLogin" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#1e40af;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#3b82f6;stop-opacity:1" />
                        </linearGradient>
                        <filter id="shadow">
                            <feDropShadow dx="0" dy="2" stdDeviation="3" flood-opacity="0.3"/>
                        </filter>
                    </defs>
                    <g filter="url(#shadow)">
                        <path d="M20 8L8 14v10c0 8 5.5 15 12 17 6.5-2 12-9 12-17V14l-12-6z"
                              fill="url(#shieldGradientLogin)"/>
                        <path d="M20 17l-5 5 5 5"
                              stroke="white"
                              stroke-width="2.5"
                              fill="none"
                              stroke-linecap="round"
                              stroke-linejoin="round"/>
                    </g>

                    <!-- OBSIGUARD Text -->
                    <text x="46" y="38" font-family="system-ui, -apple-system, sans-serif" font-size="26" font-weight="700" fill="#111827" letter-spacing="-1">OBSI</text>
                    <text x="118" y="38" font-family="system-ui, -apple-system, sans-serif" font-size="26" font-weight="700" fill="#1e40af" letter-spacing="-1">GUARD</text>

                    <!-- CRM Badge -->
                    <rect x="210" y="20" width="65" height="22" rx="11" fill="#dbeafe"/>
                    <text x="242.5" y="36" font-family="system-ui, -apple-system, sans-serif" font-size="13" font-weight="600" fill="#1e40af" text-anchor="middle">CRM</text>
                </svg>
            </div>
            <p class="text-gray-500">Sign in to your account</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-xl shadow-gray-200/50 p-8">
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3 text-red-700">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm font-medium"><?= sanitize($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                    <input type="email" name="email" required autofocus value="<?= sanitize($_POST['email'] ?? '') ?>"
                        class="w-full px-4 py-3.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black transition-all text-base"
                        placeholder="you@company.com">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" required
                        class="w-full px-4 py-3.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black transition-all text-base"
                        placeholder="••••••••">
                </div>

                <button type="submit"
                    class="w-full py-4 bg-black text-white rounded-xl font-semibold text-base hover:bg-gray-800 active:scale-[0.98] transition-all shadow-lg shadow-black/20">
                    Sign In
                </button>
            </form>
        </div>

        <!-- Footer -->
        <p class="text-center text-sm text-gray-400 mt-6">
            Admin? <a href="regi.php" class="text-black font-medium hover:underline">Register here</a>
        </p>
    </div>
</body>

</html>