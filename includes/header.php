<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if we need to load the config file
if (!defined('SITE_NAME')) {
    // Handle different include scenarios
    $config_path = __DIR__ . '/config.php';
    require_once $config_path;
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Campus Event Management</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/styles.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <a href="<?= BASE_URL ?>">
                    <h1><?= SITE_NAME ?></h1>
                </a>
            </div>
            <nav>
                <input type="checkbox" id="nav-toggle" class="nav-toggle">
                <label for="nav-toggle" class="nav-toggle-label">
                    <span></span>
                </label>
                <ul>
                    <li><a href="<?= BASE_URL ?>">Home</a></li>
                    <li><a href="<?= BASE_URL ?>pages/events.php">Events</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li><a href="<?= BASE_URL ?>pages/dashboard.php">My Dashboard</a></li>
                        <li><a href="<?= BASE_URL ?>pages/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="<?= BASE_URL ?>pages/login.php">Login</a></li>
                        <li><a href="<?= BASE_URL ?>pages/signup.php">Sign Up</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main class="container"><?php // Main content will go here ?> 