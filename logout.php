<?php
// Set default timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// Start the session
session_start();

// Store logout message in session before destroying it
$_SESSION['logout_message'] = 'Anda telah berjaya log keluar.';

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Delete the remember me cookie if it exists
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

// Redirect to index page
header('Location: index.php');
exit();
?>