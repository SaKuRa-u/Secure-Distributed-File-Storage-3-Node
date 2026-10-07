<?php
define('SESSION_TIMEOUT_SECONDS', 30 * 60); // 30 menit idle timeout

function check_session_timeout(): void {
    if (isset($_SESSION['last_activity'])) {
        $idle_time = time() - $_SESSION['last_activity'];
        if ($idle_time > SESSION_TIMEOUT_SECONDS) {
            $_SESSION = array();
            session_destroy();
            header("Location: index.php?timeout=1");
            exit();
        }
    }
    $_SESSION['last_activity'] = time();
}

/**
 * A01: pastikan user sudah login. Fail closed — kalau ragu, tolak akses.
 */
function require_login(): void {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: index.php");
        exit();
    }
    check_session_timeout();
}

/**
 * A01: pastikan user login DAN role-nya termasuk dalam daftar yang diizinkan.
 *
 * @param array $allowed_roles contoh: ['admin'], ['admin','pegawai']
 */
function require_role(array $allowed_roles): void {
    require_login();

    if (!in_array($_SESSION['role'], $allowed_roles, true)) {
        require_once(__DIR__ . "/class/ActivityLog.php");
        $logger = new ActivityLog();
        $logger->log(
            $_SESSION['id'] ?? null,
            $_SESSION['username'] ?? 'unknown',
            'ACCESS_DENIED',
            'Percobaan akses ke halaman yang memerlukan role: ' . implode(',', $allowed_roles) .
            ' (role user: ' . $_SESSION['role'] . ')'
        );

        http_response_code(403);
        die("<!DOCTYPE html><html><head><meta charset='UTF-8'><title>403 Forbidden</title>
            <style>body{font-family:Arial;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#f4f6f9;}
            .box{background:white;padding:40px;border-radius:8px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
            h1{color:#dc3545;margin:0 0 10px;}a{color:#007bff;}</style></head>
            <body><div class='box'><h1>403 — Akses Ditolak</h1>
            <p>Kamu tidak memiliki izin untuk mengakses halaman ini.</p>
            <a href='javascript:history.back()'>← Kembali</a></div></body></html>");
    }
}

/**
 * SSO customer (Authentik): logged_in + role customer + flag sso.
 * Gagal -> redirect ke sso-login.php?next=<path saat ini>. Fail closed.
 * require_login/require_role TIDAK diubah (tetap untuk staf).
 */
function require_sso(): void {
    $ok = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true
        && ($_SESSION['role'] ?? '') === 'customer'
        && ($_SESSION['sso'] ?? false) === true;
    if (!$ok) {
        // Sudah login sebagai staf -> jangan loop ke SSO, kembalikan ke katalog.
        if (is_sso_login()) {
            header("Location: katalog.php");
            exit();
        }
        $cur  = $_SERVER['REQUEST_URI'] ?? '/katalog.php';
        $next = sso_safe_next($cur);
        header("Location: sso-login.php?next=" . urlencode($next));
        exit();
    }
    check_session_timeout();
}

/** True bila sesi ini customer hasil SSO (dipakai navbar publik). */
function is_sso_customer(): bool {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true
        && ($_SESSION['role'] ?? '') === 'customer'
        && ($_SESSION['sso'] ?? false) === true;
}

/** True bila sesi ini login SSO peran APA PUN (dipakai navbar publik). */
function is_sso_login(): bool {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true
        && ($_SESSION['sso'] ?? false) === true
        && in_array(($_SESSION['role'] ?? ''), ['admin', 'pegawai', 'customer'], true);
}

/**
 * ALLOWLIST redirect internal pasca-SSO.
 * Hanya: katalog / produk (+?id=N) / keranjang / pesanan / dashboard customer.
 * Selain itu -> /katalog.php (anti open-redirect).
 */
function sso_safe_next(string $next): string {
    $allowed = [
        '/katalog.php',
        '/produk.php',
        '/keranjang.php',
        '/customer_orders.php',
        '/customer_dashboard.php',
    ];
    $next = trim($next);
    if ($next === '' || str_starts_with($next, '//')) return '/katalog.php';
    $path = parse_url($next, PHP_URL_PATH);
    if (!is_string($path) || !in_array($path, $allowed, true)) return '/katalog.php';
    if ($path === '/produk.php') {
        $qs = [];
        parse_str((string)parse_url($next, PHP_URL_QUERY), $qs);
        $id = (int)($qs['id'] ?? 0);
        return $id > 0 ? '/produk.php?id=' . $id : '/katalog.php';
    }
    return $path;
}
?>
