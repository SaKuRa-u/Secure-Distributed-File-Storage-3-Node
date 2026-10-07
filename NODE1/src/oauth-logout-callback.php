<?php
// Callback logout front-channel dari Authentik (Logout URI di provider).
// Dipanggil via redirect browser saat user logout dari sisi Authentik.
// Tugas: hancurkan sesi lokal TANPA syarat (tidak perlu token login),
// lalu kembalikan ke katalog. Target redirect TETAP (anti open-redirect).
session_start();
require_once("class/ActivityLog.php");

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $logger = new ActivityLog();
    $logger->log(
        $_SESSION['id'] ?? null,
        $_SESSION['username'] ?? 'unknown',
        'LOGOUT',
        'Logout via front-channel Authentik.'
    );
}

$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

header("Location: katalog.php");
exit();
