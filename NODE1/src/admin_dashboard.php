<?php
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_role(['admin']);
require_once("class/Item.php");
require_once("class/User.php");
require_once("connection.php");
require_once("partials/shop-ui.php");
$itemObj = new Item(); $userObj = new User(); $items = $itemObj->getItems();
$conn = Database::get();
$total_transaksi = 0; $q = $conn->query("SELECT COUNT(*) as total FROM transactions"); if ($q) $total_transaksi = $q->fetch_assoc()['total'];
$total_barang = 0; $q2 = $conn->query("SELECT COUNT(*) as total FROM items"); if ($q2) $total_barang = $q2->fetch_assoc()['total'];
$total_customer = 0; $q3 = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'customer'"); if ($q3) $total_customer = $q3->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Admin Dashboard - SONY Shop'); ?></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="admin_dashboard.php">shop<i>.</i> admin</a><span class="role-dot">ADMIN · <?php echo htmlspecialchars($_SESSION['username']); ?></span>
  <div class="topbar-spacer"></div><a class="pill" href="katalog.php">Lihat Toko</a><a class="pill pill-dark" href="logout.php">Logout</a>
</div></div>
<div class="app">
  <div class="side"><?php shop_sidebar_staff('admin', 'admin_dashboard.php'); ?></div>
  <div class="main">
    <div class="panel"><h3>Selamat Datang, <?php echo htmlspecialchars($_SESSION['username']); ?></h3><p class="hint">Kelola seluruh data toko dari panel admin ini.</p></div>
    <div class="stat-row">
      <div class="stat"><h4>Total Transaksi</h4><p class="n"><?php echo $total_transaksi; ?></p></div>
      <div class="stat"><h4>Total Barang</h4><p class="n"><?php echo $total_barang; ?></p></div>
      <div class="stat"><h4>Total Customer</h4><p class="n"><?php echo $total_customer; ?></p></div>
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
