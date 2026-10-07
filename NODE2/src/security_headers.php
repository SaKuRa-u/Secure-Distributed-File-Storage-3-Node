<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);                
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// A05: Hilangkan header X-Powered-By (jangan ekspos versi PHP)
header_remove('X-Powered-By');

// A02 + A05: HTTP Security Headers

// Prevent Clickjacking
header("X-Frame-Options: DENY");

// Block MIME-type sniffing 
header("X-Content-Type-Options: nosniff");

// Mitigate reflected XSS
header("X-XSS-Protection: 1; mode=block");

// Hanya izinkan resource dari domain sendiri (+ placeholder).
// CATATAN: gambar SeaweedFS sekarang via image-proxy.php (same-origin),
// jadi TIDAK perlu whitelist http://192.168.x.x:8888 / http://localhost:8888.
// Whitelist IP privat justru menyebabkan Mixed Content + Private Network Access
// di browser (https -> http IP privat = diblokir).
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://via.placeholder.com https://app.cloudferdi.web.id;");

// Referrer Policy — jangan bocorkan URL ke pihak ketiga
header("Referrer-Policy: strict-origin-when-cross-origin");

// Permissions Policy — matikan fitur browser yang tidak dipakai
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// Pastikan folder logs ada
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0750, true);
}
?>
