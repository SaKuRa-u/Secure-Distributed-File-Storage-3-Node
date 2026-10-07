<?php
// Callback OIDC dari Authentik: validasi state, tukar code, verifikasi
// id_token, petakan claims['groups'] -> role, find-or-create user lokal.
// SEMUA peran (admin/pegawai/customer) via SSO; login.php hanya darurat.
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_once("class/OIDC.php");
require_once("class/ActivityLog.php");

// Gagal -> redirect ke katalog dengan KODE error (allowlist di katalog.php).
// Alasan detail HANYA di server log, tidak pernah ke browser.
function sso_fail(string $msg, string $code = 'umum'): void {
    error_log("[SSO] callback gagal: " . $msg);
    header("Location: katalog.php?e=" . urlencode($code));
    exit();
}

function sso_deny_no_access(string $logDetail): void {
    error_log("[SSO] akses ditolak (tanpa grup dikenal): " . $logDetail);
    try {
        $logger = new ActivityLog();
        $logger->log(null, 'unknown', 'SSO_NO_ACCESS', 'Login SSO ditolak: ' . substr($logDetail, 0, 200));
    } catch (Exception $e) { /* log DB opsional, penolakan tetap jalan */ }
    header("Location: katalog.php?e=akses");
    exit();
}

// 1) Error dari IdP (user batal / ditolak)
if (!empty($_GET['error'])) sso_fail('idp error: ' . substr((string)$_GET['error'], 0, 50), 'idp');

// 2) Validasi state + code
$state = (string)($_GET['state'] ?? '');
$code  = (string)($_GET['code'] ?? '');
if ($state === '' || $code === '' || empty($_SESSION['sso_state']) || empty($_SESSION['sso_nonce'])) {
    sso_fail('state/code/nonce kosong', 'sesi');
}
if (!hash_equals((string)$_SESSION['sso_state'], $state)) sso_fail('state mismatch', 'sesi');
$nonce = (string)$_SESSION['sso_nonce'];

// 3) Tukar code -> token
try {
    $tokens = OIDC::exchangeCode($code);
} catch (Exception $e) {
    sso_fail('exchange: ' . $e->getMessage(), 'token');
}

// 4) Verifikasi id_token (iss/aud/exp/nonce/RS256) — tolak keras bila gagal
try {
    $claims = OIDC::verifyIdToken((string)$tokens['id_token'], $nonce);
} catch (Exception $e) {
    sso_fail('verify: ' . $e->getMessage(), 'token');
}

$sub   = (string)($claims['sub'] ?? '');
$email = isset($claims['email']) && filter_var($claims['email'], FILTER_VALIDATE_EMAIL)
    ? strtolower((string)$claims['email']) : null;
$nick  = (string)($claims['nickname'] ?? $claims['preferred_username'] ?? $claims['name'] ?? '');
if ($sub === '') sso_fail('sub kosong', 'token');
if ($email === null) sso_fail('email kosong', 'email');

// Petakan groups Authentik (nama grup, dari scope profile) -> role app.
// Grup final (keputusan owner): Admin -> admin, psp-pegawai -> pegawai,
// shop-customer -> customer.
// Toleran: string tunggal -> array 1 elemen; absen/bukan array -> array kosong.
$rawGroups = $claims['groups'] ?? [];
if (is_string($rawGroups)) $rawGroups = [$rawGroups];
if (!is_array($rawGroups)) $rawGroups = [];
$groups = array_values(array_filter(array_map('strval', $rawGroups), function ($g) { return $g !== ''; }));
if (in_array('Admin', $groups, true)) {
    $role = 'admin';
} elseif (in_array('psp-pegawai', $groups, true)) {
    $role = 'pegawai';
} elseif (in_array('shop-customer', $groups, true)) {
    $role = 'customer';
} else {
    sso_deny_no_access('groups=[' . implode(',', array_slice($groups, 0, 10)) . ']');
}

// 5) Find-or-create user lokal: by authentik_sub, lalu email (case-insensitive)
$db = Database::get();
$user = null;

$stmt = $db->prepare("SELECT * FROM users WHERE authentik_sub = ?");
$stmt->bind_param("s", $sub);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    // User lama: sinkronkan role dari groups (grup bisa berubah) + isi email bila kosong.
    // Kolom password TIDAK disentuh (akun SSO password NULL; break-glass lokal aman).
    $storedEmail = $user['email'] ?? null;
    if (($storedEmail === null || $storedEmail === '') && $email !== null) {
        $up = $db->prepare("UPDATE users SET role = ?, email = ? WHERE id = ?");
        $up->bind_param("ssi", $role, $email, $user['id']);
        $up->execute();
    } else {
        $up = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
        $up->bind_param("si", $role, $user['id']);
        $up->execute();
    }
    // Sync username dari IdP (preferred_username): nama di Authentik bisa
    // berubah; kunci tetap authentik_sub. Guard duplikat UNIQUE.
    $idpUsername = mb_substr(trim((string)($claims['preferred_username'] ?? $claims['nickname'] ?? $claims['name'] ?? '')), 0, 50);
    if ($idpUsername !== '' && $idpUsername !== (string)$user['username']) {
        $chk = $db->prepare("SELECT id FROM users WHERE username = ? AND id <> ?");
        $chk->bind_param("si", $idpUsername, $user['id']);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            $un = $db->prepare("UPDATE users SET username = ? WHERE id = ?");
            $un->bind_param("si", $idpUsername, $user['id']);
            $un->execute();
        } else {
            error_log("[SSO] sync username dilewati (duplikat lokal): $idpUsername");
        }
    }
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) sso_fail('readback user gagal', 'db');
} else {
    $byEmail = null;
    $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?)");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $byEmail = $stmt->get_result()->fetch_assoc();
    if ($byEmail) {
        $linkedSub = $byEmail['authentik_sub'] ?? null;
        if ($linkedSub !== null && $linkedSub !== '' && $linkedSub !== $sub) {
            // Email diklaim 2 sub. Bedakan hapus-daftar-ulang (aman) vs duplikat
            // hidup (serangan): sub lama yang SUDAH DIHAPUS (SCIM 404) boleh
            // ditautkan ulang; sub lama yang MASIH ADA -> tetap tolak.
            // SCIM tak terjangkau -> fail closed (tolak).
            require_once("class/ScimClient.php");
            $oldAlive = ScimClient::userExists($linkedSub);
            if ($oldAlive !== false) {
                // true (duplikat asli) atau null (tak pasti) -> tolak + log.
                error_log("[SSO] takeover diblokir: email sudah tertaut sub lain.");
                try {
                    $logger = new ActivityLog();
                    $logger->log($byEmail['id'], $byEmail['username'], 'SSO_TAKEOVER_BLOCKED', 'Login SSO ditolak: email sudah tertaut sub lain.');
                } catch (Exception $e) {}
                sso_fail('email sudah tertaut akun lain', 'taut');
            }
            try {
                $logger = new ActivityLog();
                $logger->log($byEmail['id'], $byEmail['username'], 'SSO_SUB_RELINKED', 'Sub lama terhapus di Authentik; taut ulang ke sub baru.');
            } catch (Exception $e) {}
            // Lanjut ke UPDATE di bawah (adopsi sub baru).
        }
        // Tautkan sub + sinkronkan role. Password TIDAK disentuh.
        $up = $db->prepare("UPDATE users SET authentik_sub = ?, role = ? WHERE id = ?");
        $up->bind_param("ssi", $sub, $role, $byEmail['id']);
        $up->execute();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $byEmail['id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) sso_fail('readback user gagal', 'db');
    }
}

if (!$user) {
    // Buat username unik dari nickname/email
    $base = strtolower(trim(preg_replace('/[^a-z0-9_.\-]+/i', '', $nick !== '' ? $nick : strtok($email ?? 'sso_user', '@'))));
    if ($base === '') $base = 'sso_user';
    $base = substr($base, 0, 40);
    $username = $base;
    for ($i = 0; $i < 5; $i++) {
        $c = $db->prepare("SELECT id FROM users WHERE username = ?");
        $c->bind_param("s", $username);
        $c->execute();
        if (!$c->get_result()->fetch_assoc()) break;
        $username = $base . '_' . bin2hex(random_bytes(2));
    }
    // Akun SSO: password NULL, role dari groups Authentik
    $ins = $db->prepare("INSERT INTO users (username, password, role, email, authentik_sub) VALUES (?, NULL, ?, ?, ?)");
    $ins->bind_param("ssss", $username, $role, $email, $sub);
    if (!$ins->execute()) sso_fail('insert user gagal', 'db');
    $id = (int)$db->insert_id;
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) sso_fail('readback user gagal', 'db');
}

// 6) Sesi SSO (regenerasi cegah fixation), role hasil mapping groups
session_regenerate_id(true);
$_SESSION['logged_in']    = true;
$_SESSION['user_id']     = $user['id'];
$_SESSION['id']          = $user['id'];
$_SESSION['username']    = htmlspecialchars($user['username']);
$_SESSION['role']        = $role;
$_SESSION['sso']         = true;
$_SESSION['authentik_sub'] = $sub;
$_SESSION['id_token']    = (string)$tokens['id_token'];
$_SESSION['last_activity'] = time();
unset($_SESSION['sso_state'], $_SESSION['sso_nonce']);
$next = sso_safe_next($_SESSION['sso_next'] ?? '/katalog.php');
unset($_SESSION['sso_next']);

$logger = new ActivityLog();
$logger->log($user['id'], $user['username'], 'SSO_LOGIN', 'Login SSO Authentik berhasil (role=' . $role . ').');

header("Location: " . $next);
exit();
