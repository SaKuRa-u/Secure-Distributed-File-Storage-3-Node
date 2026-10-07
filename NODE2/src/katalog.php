<?php
// Katalog PUBLIK (tanpa login): grid produk + tombol Rincian.
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_once("class/Item.php");
require_once("partials/shop-ui.php");

$itemObj = new Item();
$res = $itemObj->getItems();
$login = is_sso_login();
$loginRole = ($login ? ($_SESSION['role'] ?? '') : '');

$products = [];
if ($res) { while ($r = $res->fetch_assoc()) { $products[] = $r; } }

$q = strtolower(trim($_GET['q'] ?? ''));
$catF = trim($_GET['cat'] ?? '');
if ($q !== '') {
  $products = array_values(array_filter($products, function($p) use ($q) {
    return strpos(strtolower($p['item_name'] . ' ' . $p['category_name']), $q) !== false;
  }));
}
if ($catF !== '') {
  $products = array_values(array_filter($products, function($p) use ($catF) {
    return $p['category_name'] === $catF;
  }));
}
$cats = [];
foreach ($products as $p) { $cats[$p['category_name']] = true; }
// kategori untuk pills: ambil dari semua (tanpa filter) biar stabil
$resAll = $itemObj->getItems();
$allCats = [];
if ($resAll) { while ($r = $resAll->fetch_assoc()) { $allCats[$r['category_name']] = true; } }
$allCats = array_keys($allCats);
sort($allCats);
$pal = ['#5433eb','#e36d5d','#5d9e6b','#c9a24b','#5d8fe3'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php shop_head('Katalog - SONY Shop'); ?>
</head>
<body>
<?php shop_topbar_public($login, $_SESSION['username'] ?? '', $loginRole); ?>
<?php
// Banner error login SSO (?e= dari oauth-callback). Allowlist KETAT: nilai
// selain daftar ini diabaikan total (anti reflected-XSS).
$ssoErrMap = [
  'taut'   => 'Email ini sudah tertaut ke akun lain. Hubungi administrator untuk menggabungkan akun.',
  'akses'  => 'Akun belum punya hak akses toko. Hubungi administrator.',
  'email'  => 'Authentik tidak memberikan email. Periksa pengaturan akunmu.',
  'sesi'   => 'Sesi login kedaluwarsa. Silakan coba lagi.',
  'token'  => 'Verifikasi login gagal. Silakan coba lagi.',
  'db'     => 'Terjadi galat sistem. Coba lagi atau hubungi administrator.',
  'idp'    => 'Login dibatalkan di Authentik.',
  'umum'   => 'Login SSO gagal. Silakan coba lagi.',
];
$ssoErr = $_GET['e'] ?? '';
if (isset($ssoErrMap[$ssoErr])): ?>
  <div class="msg error" style="max-width:1100px;margin:14px auto 0;"><?php echo $ssoErrMap[$ssoErr]; ?></div>
<?php endif; ?>
<div class="shop-shell">
  <div class="constellation" aria-hidden="true"><div class="const-track">
    <?php
    $heroCards = [
      ['assets/hero/hero-perfume.jpg','Glossier You Fleur','★★★★★ (1.2K)','-4deg','0s'],
      ['assets/hero/hero-skincare.jpg','OSEA Body Lotion','★★★★★ (8.1K)','3deg','.4s'],
      ['assets/hero/hero-sneakers.jpg','Sneakers Red','★★★★★ (5.4K)','-2deg','.8s'],
      ['assets/hero/hero-bag.jpg','Leather Bag','★★★★★ (2.3K)','2deg','1.2s'],
      ['assets/hero/hero-watch.jpg','Minimal Watch','★★★★★ (11.3K)','-3deg','1.6s'],
      ['assets/hero/hero-hoodie.jpg','Hoodie Oversize','★★★★☆ (900)','4deg','2s'],
      ['assets/hero/hero-chair.jpg','Lounge Chair','★★★★★ (640)','-2deg','2.4s'],
      ['assets/hero/hero-speaker.jpg','Home Speaker','★★★★★ (1.9K)','3deg','2.8s'],
    ];
    foreach ($heroCards as $h): ?>
      <div class="float-card" style="--tilt:<?php echo $h[3]; ?>; --d:<?php echo $h[4]; ?>">
        <img src="<?php echo $h[0]; ?>" alt="<?php echo htmlspecialchars($h[1]); ?>" loading="lazy">
        <p><?php echo htmlspecialchars($h[1]); ?></p><small><?php echo htmlspecialchars($h[2]); ?></small>
      </div>
    <?php endforeach; ?>
  </div></div>
  <div class="hero">
    <h1>shop<span class="v">.</span> — Katalog</h1>
    <p>Belum login? Klik Rincian lalu Masuk Keranjang / Beli Sekarang — kamu akan diarahkan login via Authentik.</p>
    <form class="search-pill" method="GET" action="katalog.php">
      <input type="text" name="q" placeholder="What are you shopping for today?" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
      <input type="hidden" name="cat" value="<?php echo htmlspecialchars($catF); ?>">
      <button class="search-go" type="submit" aria-label="Cari">→</button>
    </form>
    <div class="cat-row">
      <a class="cat-pill" href="katalog.php<?php echo $q !== '' ? '?q='.urlencode($q) : ''; ?>">Semua</a>
      <?php $i=0; foreach ($allCats as $c): $col=$pal[$i % count($pal)]; $i++; ?>
        <a class="cat-pill" href="katalog.php?cat=<?php echo urlencode($c); ?><?php echo $q !== '' ? '&q='.urlencode($q) : ''; ?>">
          <span class="dot" style="background:<?php echo $col; ?>"></span><?php echo htmlspecialchars($c); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="section">
    <div class="section-head"><h2><?php echo $catF !== '' ? htmlspecialchars($catF) : 'Semua produk'; ?></h2><span class="chev">›</span></div>
    <div class="grid-4">
      <?php if (!empty($products)): ?>
        <?php foreach ($products as $row): ?>
          <?php
          require_once("class/SeaweedFS.php");
          $fallbackImg = !empty($row['image']) ? "uploads/" . basename($row['image']) : "https://via.placeholder.com/250x200?text=No+Image";
          $imgPath = !empty($row['image']) ? SeaweedFS::resolveUrl($row['image'], 'product', $fallbackImg) : "https://via.placeholder.com/250x200?text=No+Image";
          ?>
          <div class="p-card">
            <a class="p-img" href="produk.php?id=<?php echo (int)$row['id']; ?>"><img src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($row['item_name']); ?>" loading="lazy"></a>
            <div class="p-body">
              <p class="p-brand"><?php echo htmlspecialchars($row['item_name']); ?></p>
              <div class="p-meta"><?php echo htmlspecialchars($row['category_name']); ?></div>
              <p class="p-price">Rp <?php echo number_format($row['price'], 0, ',', '.'); ?></p>
              <div class="p-actions"><a class="btn btn-primary btn-block" href="produk.php?id=<?php echo (int)$row['id']; ?>">Rincian</a></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="hint" style="grid-column:1/-1; text-align:center;">Belum ada produk yang dijual saat ini.</p>
      <?php endif; ?>
    </div>
  </div>
  <div class="footer">SONY Shop — shop. constellation · SeaweedFS object storage · Node 2 Gabriel</div>
</div>
</body>
</html>
