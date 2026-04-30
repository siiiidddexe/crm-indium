<?php
require_once __DIR__ . '/config/config.php';

// Redirect to login if not logged in, otherwise redirect by role
if (isLoggedIn()) {
    redirectByRole();
} else {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}
?>
