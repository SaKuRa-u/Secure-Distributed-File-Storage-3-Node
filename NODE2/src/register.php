<?php
// Pendaftaran customer PINDAH ke Authentik (IAM). File lokal ini hanya info.
session_start();
require_once("security_headers.php");
require_once("partials/shop-ui.php");
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) { header("Location: katalog.php"); exit(); }
$enrollEnv = getenv('AUTHENTIK_ENROLL_URL');
if (!$enrollEnv && isset($_ENV['AUTHENTIK_ENROLL_URL'])) $enrollEnv = $_ENV['AUTHENTIK_ENROLL_URL'];
$enrollUrl = trim((string)($enrollEnv ?: ''));
if ($enrollUrl === '') $enrollUrl = 'https://authentik.example.com/if/flow/shop-enrollment/';
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Daftar - SONY Shop'); ?></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="katalog.php">shop<i>.</i></a><div class="topbar-spacer"></div>
  <a class="pill" href="katalog.php">Katalog</a>
</div></div>
<div class="auth-wrap">
  <div class="auth-card">
    <h1>shop<span style="color:#5433eb;">.</span></h1>
    <p class="sub">Pendaftaran akun customer via <b>Authentik (IAM)</b>, bukan di aplikasi ini. Daftar via Authentik pada tautan di bawah, lalu masuk dengan SSO. Akun baru otomatis masuk grup <b>shop-customer</b>.</p>
    <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
      <a class="btn btn-primary" href="<?php echo htmlspecialchars($enrollUrl); ?>">Daftar via Authentik</a>
      <a class="btn" href="sso-login.php">Masuk dengan SSO</a>
      <a class="btn" href="katalog.php">Katalog</a>
    </div>
  </div>
</div>
</body>
</html>
