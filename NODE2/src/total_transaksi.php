<?php
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_role(['admin', 'pegawai']);
require_once("class/Transaction.php");
require_once("partials/shop-ui.php");
$transObj = new Transaction(); $history = $transObj->getTransactionHistory(); $grandTotal = $transObj->getTotalRevenue();
$isAdmin = ($_SESSION['role'] === 'admin');
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Total Transaksi - SONY Shop'); ?></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="<?php echo $isAdmin ? 'admin_dashboard.php' : 'pegawai_dashboard.php'; ?>">shop<i>.</i> staff</a>
  <span class="role-dot"><?php echo $isAdmin ? 'ADMIN' : 'PEGAWAI'; ?></span><div class="topbar-spacer"></div><a class="pill" href="katalog.php">Lihat Toko</a><a class="pill pill-dark" href="logout.php">Logout</a>
</div></div>
<div class="app">
  <div class="side"><?php shop_sidebar_staff($_SESSION['role'], 'total_transaksi.php'); ?></div>
  <div class="main">
    <div class="stat-row"><div class="stat"><h4>Total Pendapatan</h4><p class="n">Rp <?php echo number_format($grandTotal,0,',','.'); ?></p></div></div>
    <div class="panel"><h3>Rincian Transaksi Pembeli</h3>
      <div class="table-wrap"><table class="tbl"><thead><tr><th>Waktu</th><th>Pembeli</th><th>Item</th><th>Qty</th><th>Subtotal</th></tr></thead><tbody>
      <?php if ($history->num_rows > 0): while ($row = $history->fetch_assoc()): ?>
        <tr><td><?php echo date('d/m/Y H:i', strtotime($row['transaction_date'])); ?></td><td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td><td><?php echo htmlspecialchars($row['item_name']); ?></td><td><?php echo $row['quantity']; ?></td><td>Rp <?php echo number_format($row['total_price'],0,',','.'); ?></td></tr>
      <?php endwhile; else: ?><tr><td colspan="5" class="hint" style="text-align:center;">Belum ada transaksi.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  </div>
</div>
</body>
</html>
