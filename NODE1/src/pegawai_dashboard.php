<?php
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_role(['pegawai']);
require_once("class/Item.php");
require_once("class/Transaction.php");
require_once("connection.php");
require_once("partials/shop-ui.php");
$itemObj  = new Item(); $transObj = new Transaction(); $items = $itemObj->getItems();
$conn = Database::get();
$total_transaksi = 0; $q = $conn->query("SELECT COUNT(*) as total FROM transactions"); if ($q) $total_transaksi = $q->fetch_assoc()['total'];
$total_barang = 0; $q2 = $conn->query("SELECT COUNT(*) as total FROM items"); if ($q2) $total_barang = $q2->fetch_assoc()['total'];
$grand_total = $transObj->getTotalRevenue();
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Pegawai Dashboard - SONY Shop'); ?></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="pegawai_dashboard.php">shop<i>.</i> staff</a><span class="role-dot">PEGAWAI · <?php echo htmlspecialchars($_SESSION['username']); ?></span>
  <div class="topbar-spacer"></div><a class="pill" href="katalog.php">Lihat Toko</a><a class="pill pill-dark" href="logout.php">Logout</a>
</div></div>
<div class="app">
  <div class="side"><?php shop_sidebar_staff('pegawai', 'pegawai_dashboard.php'); ?></div>
  <div class="main">
    <div class="panel"><h3>Selamat Datang, <?php echo htmlspecialchars($_SESSION['username']); ?></h3><p class="hint">Kelola barang dan pantau transaksi di sini. Kelola user hanya untuk Admin.</p></div>
    <div class="stat-row">
      <div class="stat"><h4>Total Transaksi</h4><p class="n"><?php echo $total_transaksi; ?></p></div>
      <div class="stat"><h4>Total Barang</h4><p class="n"><?php echo $total_barang; ?></p></div>
      <div class="stat"><h4>Pendapatan</h4><p class="n" style="font-size:16px;">Rp <?php echo number_format($grand_total,0,',','.'); ?></p></div>
    </div>
    <div class="panel">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;"><h3 style="margin:0;">Daftar Barang</h3><a href="tambah_barang.php" class="btn btn-primary">+ Tambah Barang</a></div>
      <div class="table-wrap"><table class="tbl"><thead><tr><th>ID</th><th>Nama</th><th>Kategori</th><th>Harga</th><th>Stok</th></tr></thead><tbody>
      <?php if ($items && $items->num_rows > 0): while ($row = $items->fetch_assoc()): ?>
        <tr><td><?php echo $row['id']; ?></td><td><strong><?php echo htmlspecialchars($row['item_name']); ?></strong></td><td><?php echo htmlspecialchars($row['category_name']); ?></td><td>Rp <?php echo number_format($row['price'],0,',','.'); ?></td><td><?php echo $row['stock']; ?></td></tr>
      <?php endwhile; else: ?><tr><td colspan="5" class="hint" style="text-align:center;">Belum ada data.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  </div>
</div>
</body>
</html>
