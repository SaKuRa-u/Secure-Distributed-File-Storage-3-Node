<?php
// Shared Shop UI helpers (DESIGN.md). Include CSS once per page via shop_head().
function shop_head($title) {
  echo '<meta charset="UTF-8">';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
  echo '<title>' . htmlspecialchars($title) . ' — shop.</title>';
  echo '<link rel="stylesheet" href="assets/shop.css">';
}
function shop_topbar_public($isLogin, $username = '', $role = '') {
  $dashUrl = ($role === 'admin') ? 'admin_dashboard.php' : (($role === 'pegawai') ? 'pegawai_dashboard.php' : '');
  ?>
  <div class="shop-topbar"><div class="shop-topbar-inner">
    <a class="wordmark" href="katalog.php">shop<i>.</i></a>
    <div class="topbar-spacer"></div>
    <a class="pill pill-muted" href="keranjang.php">Keranjang</a>
    <?php if ($isLogin): ?>
      <?php if ($dashUrl !== ''): ?>
        <a class="pill" href="<?php echo $dashUrl; ?>">Dashboard</a>
      <?php else: ?>
        <a class="pill" href="customer_orders.php">Pesanan Saya</a>
      <?php endif; ?>
      <span class="role-dot"><?php echo htmlspecialchars($username); ?></span>
      <a class="pill pill-dark" href="sso-logout.php">Logout</a>
    <?php else: ?>
      <a class="pill pill-primary" href="sso-login.php?next=%2Fkatalog.php">Masuk dengan SSO</a>
      <?php /* break-glass login.php sengaja tidak ditautkan di navigasi publik */ ?>
    <?php endif; ?>
  </div></div>
  <?php
}
function shop_sidebar_customer($active = '') {
  $items = [
    'customer_dashboard.php' => 'Belanja',
    'keranjang.php' => 'Keranjang',
    'customer_orders.php' => 'Pesanan Saya',
    'riwayat.php' => 'Riwayat',
    'customer_profile.php' => 'Profil Saya',
  ];
  foreach ($items as $href => $label) {
    $cls = ($active === $href) ? 'active' : '';
    echo '<a class="' . $cls . '" href="' . $href . '">' . htmlspecialchars($label) . '</a>';
  }
}
function shop_sidebar_staff($role, $active = '') {
  $isAdmin = ($role === 'admin');
  $links = [];
  $links[$isAdmin ? 'admin_dashboard.php' : 'pegawai_dashboard.php'] = 'Home';
  $links['tambah_kategori.php'] = 'Kelola Kategori';
  $links['tambah_barang.php'] = 'Kelola Barang';
  if ($isAdmin) $links['pengguna_aktif.php'] = 'Pengguna Aktif';
  $links['total_transaksi.php'] = 'Total Transaksi';
  $links['kelola_pesanan.php'] = 'Kelola Pesanan';
  $links['cetak_laporan.php'] = 'Cetak Laporan';
  if ($isAdmin) $links['activity_logs.php'] = 'Activity Logs';
  foreach ($links as $href => $label) {
    $cls = ($active === $href) ? 'active' : '';
    echo '<a class="' . $cls . '" href="' . $href . '">' . htmlspecialchars($label) . '</a>';
  }
}
