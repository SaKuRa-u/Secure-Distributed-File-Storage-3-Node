<?php
// Mulai login SSO semua peran via Authentik (OIDC). login.php hanya darurat.
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_once("class/OIDC.php");

// Sudah SSO -> langsung ke tujuan
if (is_sso_customer()) {
    header("Location: " . sso_safe_next($_GET['next'] ?? '/katalog.php'));
    exit();
}

// Simpan tujuan (allowlist) lalu buat state+nonce acak
$next = sso_safe_next($_GET['next'] ?? '');
$_SESSION['sso_next']  = $next;
$_SESSION['sso_state'] = bin2hex(random_bytes(24));
$_SESSION['sso_nonce'] = bin2hex(random_bytes(24));

try {
    $url = OIDC::buildAuthorizeUrl($_SESSION['sso_state'], $_SESSION['sso_nonce']);
} catch (Exception $e) {
    error_log("[SSO] buildAuthorizeUrl gagal: " . $e->getMessage());
    http_response_code(500);
    die("Login SSO belum dikonfigurasi. Hubungi administrator.");
}

header("Location: " . $url);
exit();
