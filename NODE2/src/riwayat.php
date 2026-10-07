<?php
// Riwayat Transaksi customer: semua pembelian LANGSUNG yang sudah dibayar
// (checkout keranjang). Status PO (disetujui/ditolak/selesai) ada di
// customer_orders.php ("Pesanan Saya").
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_role(['customer']);
require_once("class/Transaction.php");
require_once("class/SeaweedFS.php");
require_once("partials/shop-ui.php");
$transObj = new Transaction();
$history = $transObj->getHistoryByUser($_SESSION['id']);
$rows = []; $total = 0;
if ($history) { while ($r = $history->fetch_assoc()) { $rows[] = $r; $total += (float)$r['total_price']; } }
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Riwayat Transaksi - SONY Shop'); ?></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="katalog.php">shop<i>.</i></a><span class="role-dot">CUSTOMER</span><div class="topbar-spacer"></div>
  <a class="pill pill-dark" href="sso-logout.php">Logout</a>
</div></div>
<div class="app">
  <div class="side"><?php shop_sidebar_customer('riwayat.php'); ?></div>
  <div class="main">
    <div class="stat-row"><div class="stat"><h4>Total Belanja Saya</h4><p class="n" style="font-size:16px;">Rp <?php echo number_format($total,0,',','.'); ?></p></div></div>
    <div class="panel"><h3>Riwayat Transaksi</h3>
      <p class="hint">Pembelian langsung yang sudah dibayar. Status PO (disetujui / ditolak / selesai) lihat di <a href="customer_orders.php">Pesanan Saya</a>.</p>
      <div class="table-wrap"><table class="tbl"><thead><tr><th></th><th>Tanggal</th><th>Barang</th><th>Qty</th><th>Total</th></tr></thead><tbody>
      <?php if (!empty($rows)): foreach ($rows as $row):
        $fb = !empty($row['image']) ? "uploads/".basename($row['image']) : "https://via.placeholder.com/100?text=No";
        $thumb = !empty($row['image']) ? SeaweedFS::resolveUrl($row['image'],'product',$fb) : "https://via.placeholder.com/100?text=No";
      ?>
        <tr><td><img src="<?php echo htmlspecialchars($thumb); ?>" alt="" loading="lazy" style="width:48px;height:48px;object-fit:cover;border-radius:12px;border:1px solid #ebebeb;"></td><td><?php echo date('d/m/Y H:i', strtotime($row['transaction_date'])); ?></td><td><?php echo htmlspecialchars($row['item_name']); ?></td><td><?php echo (int)$row['quantity']; ?></td><td>Rp <?php echo number_format($row['total_price'],0,',','.'); ?></td></tr>
      <?php endforeach; else: ?><tr><td colspan="5" style="text-align:center;" class="hint">Belum ada transaksi.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  </div>
</div>
</body>
</html>
