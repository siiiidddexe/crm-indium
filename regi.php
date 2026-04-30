<?php
require_once __DIR__ . '/config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole();
}

// Access password protection
$accessPassword = 'ObsiGuard@2026';
$accessGranted = isset($_SESSION['regi_access']) && $_SESSION['regi_access'] === true;

if (!$accessGranted) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_password'])) {
        if ($_POST['access_password'] === $accessPassword) {
            $_SESSION['regi_access'] = true;
            $accessGranted = true;
        } else {
            $accessError = 'Invalid access password.';
        }
    }

    if (!$accessGranted) {
        $pageTitle = 'Access Required - Obsiguard CRM';
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= $pageTitle ?></title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
            <div class="w-full max-w-sm">
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-black rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-black">Access Required</h1>
                    <p class="text-gray-500 mt-1">Enter the access password to continue</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <?php if (!empty($accessError)): ?>
                    <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-4 text-sm">
                        <?= sanitize($accessError) ?>
                    </div>
                    <?php endif; ?>
                    <form method="POST">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Access Password</label>
                        <input type="password" name="access_password" required autofocus
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black mb-4"
                            placeholder="Enter access password">
                        <button type="submit"
                            class="w-full bg-black text-white py-3 px-4 rounded-xl font-medium hover:bg-gray-800 transition-colors">
                            Unlock
                        </button>
                    </form>
                </div>
                <p class="text-center text-sm text-gray-500 mt-6">
                    <a href="login.php" class="text-black font-medium hover:underline">Back to Sign In</a>
                </p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $phone = sanitize($_POST['phone'] ?? '');

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Check if email already exists
        $existing = db()->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        
        if ($existing) {
            $error = 'An account with this email already exists.';
        } else {
            // Check if this is the first user (will be admin)
            $userCount = db()->fetch("SELECT COUNT(*) as count FROM users")['count'];
            $role = $userCount == 0 ? 'admin' : 'admin'; // All registrations via regi.php are admin
            
            // Create user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            db()->insert(
                "INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)",
                [$name, $email, $hashedPassword, $phone, $role]
            );
            
            $success = 'Account created successfully! You can now sign in.';
        }
    }
}

$pageTitle = 'Register - Obsiguard CRM';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-black rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-black">Create Admin Account</h1>
            <p class="text-gray-500 mt-1">Register a new administrator</p>
        </div>

        <!-- Register Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sm:p-8">
            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <?= sanitize($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <?= sanitize($success) ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        required
                        value="<?= sanitize($_POST['name'] ?? '') ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black transition-all text-base"
                        placeholder="John Doe"
                    >
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required
                        value="<?= sanitize($_POST['email'] ?? '') ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black transition-all text-base"
                        placeholder="you@example.com"
                    >
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone"
                        value="<?= sanitize($_POST['phone'] ?? '') ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black transition-all text-base"
                        placeholder="+91 9876543210"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        minlength="6"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black transition-all text-base"
                        placeholder="••••••••"
                    >
                    <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black transition-all text-base"
                        placeholder="••••••••"
                    >
                </div>

                <button 
                    type="submit" 
                    class="w-full bg-black text-white py-3 px-4 rounded-xl font-medium hover:bg-gray-800 transition-colors text-base"
                >
                    Create Account
                </button>
            </form>
        </div>

        <!-- Login Link -->
        <p class="text-center text-sm text-gray-500 mt-6">
            Already have an account? <a href="login.php" class="text-black font-medium hover:underline">Sign in</a>
        </p>
    </div>
</body>
</html>
