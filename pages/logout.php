<?php
// Use the correct path to the includes directory
require_once dirname(__FILE__) . '/../includes/config.php';
session_start();

// Unset all session variables
$_SESSION = array();

// If a session cookie is used, delete it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to home page
header("Location: " . BASE_URL);
exit(); 