<?php
session_start();
require_once("class/ActivityLog.php");

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $logger = new ActivityLog();
    $logger->log(
        $_SESSION['id'] ?? null,
        $_SESSION['username'] ?? 'unknown',
        'LOGOUT',
        'User logout dari sistem.'
    );
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header("Location: index.php");
exit();
?>
