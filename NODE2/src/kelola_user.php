<?php
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_role(['admin']);
require_once("class/User.php");
require_once("class/ActivityLog.php");
require_once("class/ScimClient.php");
require_once("csrf_helper.php");
require_once("partials/shop-ui.php");
// TODO (follow-up, DI LUAR scope): hapus/deaktivasi pegawai.
// Untuk sekarang nonaktifkan dari Authentik Users (Directory > Users).
// Sinkron penghapusan penuh (SCIM DELETE + baris lokal) belum diimplementasikan.
$userObj = new User(); $logger = new ActivityLog(); $message = "";
if (isset($_POST['btnTambahPegawai'])) {
    csrf_validate();
    $new_username = trim((string)($_POST['new_username'] ?? ''));
    $new_nama = trim((string)($_POST['new_nama'] ?? ''));
    $new_email = trim((string)($_POST['new_email'] ?? ''));
    $new_pass1 = (string)($_POST['new_password'] ?? '');
    $new_pass2 = (string)($_POST['new_password2'] ?? '');
    if (!preg_match('/^[A-Za-z0-9._-]{1,50}$/', $new_username)) {
        $message = "<div class='msg error'>Username tidak valid (huruf/angka/._- , max 50).</div>";
    } elseif ($new_nama === '' || mb_strlen($new_nama) > 100) {
        $message = "<div class='msg error'>Nama tidak valid (wajib diisi, max 100).</div>";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL) || strlen($new_email) > 255) {
        $message = "<div class='msg error'>Email tidak valid.</div>";
    } elseif (mb_strlen($new_pass1) < 8 || mb_strlen($new_pass1) > 128) {
        $message = "<div class='msg error'>Password minimal 8 karakter (max 128).</div>";
    } elseif (!hash_equals($new_pass1, $new_pass2)) {
        $message = "<div class='msg error'>Password dan ulangi password tidak sama.</div>";
    } else {
        // CEGAH duplikat lokal (parameterized, case-insensitive untuk email).
        $db = Database::get();
        $chk = $db->prepare("SELECT id FROM users WHERE username = ? OR LOWER(email) = LOWER(?)");
        $chk->bind_param("ss", $new_username, $new_email);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $message = "<div class='msg error'>Username/email sudah dipakai (lokal).</div>";
            $logger->log($_SESSION['id'] ?? null, $_SESSION['username'] ?? 'admin', 'SCIM_USER_CREATE_FAILED', "Provisioning ditolak (duplikat lokal): $new_username <$new_email>");
        } elseif (!ScimClient::isConfigured()) {
            $message = "<div class='msg error'>Gagal, cek koneksi/token.</div>";
            error_log("[SCIM] provisioning ditolak: env SCIM belum lengkap.");
            $logger->log($_SESSION['id'] ?? null, $_SESSION['username'] ?? 'admin', 'SCIM_USER_CREATE_FAILED', "Provisioning ditolak (env belum lengkap): $new_username");
        } else {
            // Password awal dari admin (dikirim apa adanya via SCIM; BUKAN disimpan
            // di app — hanya transit di request ini). Grup diikutkan saat create,
            // lalu DIVERIFIKASI via GET; bila belum masuk -> adopsi via POST /Groups
            // (grup manual ikut ter-link), lalu PATCH; terakhir manual.
            $created = ScimClient::createUser($new_username, $new_nama, $new_email, ScimClient::pegawaiGroupId(), $new_pass1);
            if (!empty($created['conflict'])) {
                $message = "<div class='msg error'>Username/email sudah dipakai di Authentik.</div>";
                $logger->log($_SESSION['id'] ?? null, $_SESSION['username'] ?? 'admin', 'SCIM_USER_CREATE_FAILED', "Provisioning conflict di Authentik: $new_username <$new_email>");
            } elseif (empty($created['ok']) || empty($created['id'])) {
                $message = "<div class='msg error'>Gagal, cek koneksi/token.</div>";
                $logger->log($_SESSION['id'] ?? null, $_SESSION['username'] ?? 'admin', 'SCIM_USER_CREATE_FAILED', "Provisioning SCIM gagal: $new_username <$new_email>");
            } else {
                $scimId = (string)$created['id'];
                $inGroup = ScimClient::userInGroup($scimId, ScimClient::pegawaiGroupId());
                if (!$inGroup) {
                    // Jalur 2: adopsi grup manual via POST /Groups (verified path).
                    $adp = ScimClient::adoptGroupWithMember('psp-pegawai', $scimId, $new_username);
                    $inGroup = !empty($adp['ok']) && ScimClient::userInGroup($scimId, ScimClient::pegawaiGroupId());
                }
                if (!$inGroup) {
                    // Jalur 3 (terakhir): PATCH anggota RFC. Gagal -> manual.
                    $grp = ScimClient::addUserToGroup($scimId, $new_username);
                    $inGroup = !empty($grp['ok']) || ScimClient::userInGroup($scimId, ScimClient::pegawaiGroupId());
                    if (!$inGroup) {
                        error_log("[SCIM] add-to-group gagal HTTP=" . (int)$grp['http']);
                    }
                }
                if ($inGroup) {
                    $message = "<div class='msg success'>Akun Authentik untuk <strong>" . htmlspecialchars($new_username) . "</strong> sudah dibuat + masuk grup <strong>psp-pegawai</strong>. WAJIB verifikasi 1x: login sebagai user ini dengan password tadi — bila GAGAL, password tidak tersimpan oleh SCIM: pakai halaman login &gt; Forgot password untuk SET password, atau Reset password dari Authentik Users. Baris lokal terbentuk otomatis saat login pertama (role dari grup).</div>";
                    $logger->log($_SESSION['id'] ?? null, $_SESSION['username'] ?? 'admin', 'SCIM_USER_CREATE', "Provisioning pegawai via SCIM: $new_username <$new_email> (grup=ok)");
                } else {
                    $message = "<div class='msg error'>User jadi, gabung manual ke psp-pegawai di Authentik (tambah user <strong>" . htmlspecialchars($new_username) . "</strong> ke grup psp-pegawai), lalu minta pegawai buka halaman login &gt; Forgot password untuk SET password sendiri.</div>";
                    error_log("[SCIM] add-to-group gagal HTTP=" . (int)$grp['http']);
                    $logger->log($_SESSION['id'] ?? null, $_SESSION['username'] ?? 'admin', 'SCIM_GROUP_ADD_FAILED', "User SCIM jadi tapi gagal masuk grup: $new_username");
                }
            }
        }
    }
}
$allUsers = $userObj->getAllUsers();
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Kelola User - SONY Shop'); ?></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="admin_dashboard.php">shop<i>.</i> admin</a><span class="role-dot">ADMIN</span><div class="topbar-spacer"></div><a class="pill" href="katalog.php">Lihat Toko</a><a class="pill pill-dark" href="logout.php">Logout</a>
</div></div>
<div class="app">
  <div class="side"><?php shop_sidebar_staff('admin', 'kelola_user.php'); ?></div>
  <div class="main">
    <div class="panel"><h3>Tambah Pegawai (via Authentik SCIM, tanpa password)</h3><?php echo $message; ?>
      <form method="POST" action=""><?php csrf_field(); ?>
        <div class="field"><label>Username</label><input type="text" name="new_username" required autocomplete="off" maxlength="50" pattern="[A-Za-z0-9._-]{1,50}" placeholder="huruf/angka/._- max 50"></div>
        <div class="field"><label>Nama</label><input type="text" name="new_nama" required autocomplete="off" maxlength="100" placeholder="Nama lengkap pegawai"></div>
        <div class="field"><label>Email</label><input type="email" name="new_email" required autocomplete="off" maxlength="255" placeholder="pegawai@contoh.id"></div>
        <div class="field"><label>Password awal</label><input type="password" name="new_password" required autocomplete="new-password" minlength="8" maxlength="128" placeholder="min 8 karakter"></div>
        <div class="field"><label>Ulangi password</label><input type="password" name="new_password2" required autocomplete="new-password" minlength="8" maxlength="128" placeholder="sama dengan di atas"></div>
        <button class="btn btn-primary" type="submit" name="btnTambahPegawai">Tambah Pegawai</button>
      </form>
      <p class="sub" style="margin-top:10px;">Identitas single-source di Authentik. Akun dibuat TANPA password; pegawai SET password sendiri via halaman login &gt; Forgot password. Hapus/deaktivasi: nonaktifkan dari Authentik Users untuk sekarang (sinkron penuh follow-up).</p></div>
    <div class="panel"><h3>Daftar Semua User</h3>
      <div class="table-wrap"><table class="tbl"><thead><tr><th>ID</th><th>Username</th><th>Role</th></tr></thead><tbody>
      <?php while ($row = $allUsers->fetch_assoc()): $bc = $row['role']==='admin'?'b-red':($row['role']==='pegawai'?'b-yellow':'b-green'); ?>
        <tr><td><?php echo $row['id']; ?></td><td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td><td><span class="badge <?php echo $bc; ?>"><?php echo strtoupper($row['role']); ?></span></td></tr>
      <?php endwhile; ?>
      </tbody></table></div></div>
  </div>
</div>
</body>
</html>
