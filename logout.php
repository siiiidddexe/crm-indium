<?php
require_once __DIR__ . '/config/config.php';

// Destroy session and redirect to login
session_destroy();
header('Location: ' . APP_URL . '/login.php');
exit;
?>