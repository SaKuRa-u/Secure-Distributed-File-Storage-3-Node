<?php
// Profil Saya (customer): read-only. Identitas single-source di Authentik —
// username/password diubah via halaman user settings IAM, bukan di sini.
// Fitur foto profil dihapus.
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_role(['customer']);
require_once("class/User.php");
require_once("partials/shop-ui.php");
$userObj = new User();
$user_id  = $_SESSION['id'];
$userData = $userObj->getUserById($user_id);

// URL user settings Authentik: {auth-base}/if/user/#/settings,
// auth-base diturunkan dari AUTHENTIK_ISSUER (tanpa hardcode domain).
$issuer = getenv('AUTHENTIK_ISSUER');
if (!$issuer && isset($_ENV['AUTHENTIK_ISSUER'])) $issuer = $_ENV['AUTHENTIK_ISSUER'];
$authSettings = null;
if (is_string($issuer) && $issuer !== '') {
    $p = parse_url($issuer);
    if (!empty($p['scheme']) && !empty($p['host'])) {
        $authSettings = $p['scheme'] . '://' . $p['host']
            . (!empty($p['port']) ? ':' . (int)$p['port'] : '')
            . '/if/user/#/settings';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Profil Saya - SONY Shop'); ?></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="katalog.php">shop<i>.</i></a><span class="role-dot">CUSTOMER</span><div class="topbar-spacer"></div>
  <a class="pill pill-dark" href="sso-logout.php">Logout</a>
</div></div>
<div class="app">
  <div class="side"><?php shop_sidebar_customer('customer_profile.php'); ?></div>
  <div class="main">
    <div class="panel" style="max-width:520px;"><h3>Profil Saya</h3>
      <p class="hint">Data sesuai yang didaftarkan saat sign up. Akun dikelola di IAM (Authentik).</p>
      <div class="field"><label>Username</label><input type="text" value="<?php echo htmlspecialchars($userData['username'] ?? '-'); ?>" disabled></div>
      <div class="field"><label>Email</label><input type="text" value="<?php echo htmlspecialchars($userData['email'] ?? '-'); ?>" disabled></div>
      <div class="field"><label>Role</label><input type="text" value="Customer" disabled></div>
      <?php if ($authSettings): ?>
        <a class="btn btn-primary btn-block" href="<?php echo htmlspecialchars($authSettings); ?>" target="_blank" rel="noopener">Change Username / Password</a>
      <?php else: ?>
        <p class="hint">Untuk mengubah username/password, buka user settings Authentik atau hubungi admin.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
