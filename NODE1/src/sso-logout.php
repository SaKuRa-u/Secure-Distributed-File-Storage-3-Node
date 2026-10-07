<?php
// Logout SSO: hancurkan sesi lokal lalu lanjut ke end-session Authentik.
// id_token_hint dibaca DULU (sebelum destroy) agar sesi SSO ikut mati;
// tanpa itu Authentik hanya menampilkan halaman konfirmasi dan sesi SSO hidup.
session_start();
require_once("class/ActivityLog.php");

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $logger = new ActivityLog();
    $logger->log(
        $_SESSION['id'] ?? null,
        $_SESSION['username'] ?? 'unknown',
        'LOGOUT',
        'User SSO logout dari sistem.'
    );
}

require_once("class/OIDC.php");
$idTokenHint = isset($_SESSION['id_token']) && is_string($_SESSION['id_token']) ? $_SESSION['id_token'] : '';

$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

// Lanjut ke Authentik end-session (kembali ke katalog). Gagal config -> lokal saja.
try {
    header("Location: " . OIDC::buildLogoutUrl($idTokenHint));
} catch (Exception $e) {
    error_log("[SSO] logout url gagal: " . $e->getMessage());
    header("Location: katalog.php");
}
exit();
