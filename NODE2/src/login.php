<?php
// Login lokal BREAK-GLASS / darurat saja. Staf normal (admin/pegawai) via SSO Authentik.
session_start();
require_once("security_headers.php");

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (($_SESSION['role'] ?? '') === 'admin') { header("Location: admin_dashboard.php"); exit(); }
    if (($_SESSION['role'] ?? '') === 'pegawai') { header("Location: pegawai_dashboard.php"); exit(); }
    header("Location: katalog.php");
    exit();
}

require_once("class/Auth.php");
require_once("csrf_helper.php");
require_once("partials/shop-ui.php");
$error = "";

if (isset($_POST['btnLogin'])) {
    csrf_validate();
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $auth      = new Auth();
    $user_data = $auth->login($username, $password);
    if ($user_data === 'LOCKED_OUT') {
        $error = "Terlalu banyak percobaan login gagal. Akun dikunci sementara selama 15 menit.";
    } elseif ($user_data) {
        if (($user_data['role'] ?? '') === 'customer') {
            $error = "Akun customer wajib masuk via SSO. Silakan pakai tombol Masuk dengan SSO.";
        } else {
            session_regenerate_id(true);
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id']   = $user_data['id'];
            $_SESSION['id']        = $user_data['id'];
            $_SESSION['username']  = htmlspecialchars($user_data['username']);
            $_SESSION['role']      = $user_data['role'];
            $_SESSION['sso']       = false;
            $_SESSION['last_activity'] = time();
            if ($user_data['role'] === 'admin') { header("Location: admin_dashboard.php"); }
            else { header("Location: pegawai_dashboard.php"); }
            exit();
        }
    } else { $error = "Username atau Password salah!"; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Login Staf - SONY Shop'); ?></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="katalog.php">shop<i>.</i></a><div class="topbar-spacer"></div>
  <a class="pill" href="katalog.php">Katalog</a>
</div></div>
<div class="auth-wrap">
  <div class="auth-card">
    <h1>shop<span style="color:#5433eb;">.</span> staf</h1>
    <p class="sub">Akses darurat lokal — staf normal via SSO</p>
    <div class="msg" style="background:#fff8e1;border:1px solid #f0c36d;padding:10px;border-radius:8px;margin-bottom:12px;">Akses darurat lokal — staf normal via SSO (Authentik). Halaman ini hanya untuk break-glass bila IdP tidak terjangkau.</div>
    <?php if ($error != ""): ?><div class="msg error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="POST" action="">
      <?php csrf_field(); ?>
      <div class="field"><label>Username</label><input type="text" name="username" placeholder="Username" required autocomplete="off"></div>
      <div class="field"><label>Password</label><input type="password" name="password" placeholder="Password" required></div>
      <button class="btn btn-primary btn-block" type="submit" name="btnLogin">Masuk →</button>
    </form>
    <div style="margin-top:16px; display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
      <a class="pill" href="sso-login.php">Customer? Masuk dengan SSO</a>
      <a class="pill pill-muted" href="katalog.php">← Katalog</a>
    </div>
  </div>
</div>
</body>
</html>
