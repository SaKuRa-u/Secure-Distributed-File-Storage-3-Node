<?php
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_role(['admin', 'pegawai']);
require_once("class/Transaction.php");
require_once("class/Order.php");
require_once("connection.php");
require_once("partials/shop-ui.php");
$transObj = new Transaction();
$date_from = $_GET['date_from'] ?? ''; $date_to = $_GET['date_to'] ?? '';
$conn = Database::get();
$params = []; $types = ''; $where = '';
if (!empty($date_from) && !empty($date_to)) { $where = "WHERE DATE(t.transaction_date) BETWEEN ? AND ?"; $params = [$date_from, $date_to]; $types = 'ss'; }
$sql = "SELECT t.id, u.username, i.item_name, i.price as unit_price, t.quantity, t.total_price, t.transaction_date FROM transactions t JOIN users u ON t.user_id = u.id JOIN items i ON t.item_id = i.id $where ORDER BY t.transaction_date DESC";
$stmt = $conn->prepare($sql); if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute(); $transactions = $stmt->get_result();
$sqlTotal = "SELECT SUM(t.total_price) as grand_total FROM transactions t $where";
$stmtTotal = $conn->prepare($sqlTotal); if (!empty($params)) { $stmtTotal->bind_param($types, ...$params); }
$stmtTotal->execute(); $grandTotal = $stmtTotal->get_result()->fetch_assoc()['grand_total'] ?? 0;
$sqlCount = "SELECT COUNT(*) as total FROM transactions t $where";
$stmtCount = $conn->prepare($sqlCount); if (!empty($params)) { $stmtCount->bind_param($types, ...$params); }
$stmtCount->execute(); $totalCount = $stmtCount->get_result()->fetch_assoc()['total'] ?? 0;
$isAdmin = ($_SESSION['role'] === 'admin');
$periodeLabel = (!empty($date_from) && !empty($date_to)) ? date('d/m/Y', strtotime($date_from)) . ' — ' . date('d/m/Y', strtotime($date_to)) : 'Semua Waktu';
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Cetak Laporan - SONY Shop'); ?>
<style>
@media print {
  .shop-topbar, .side, .screen-only { display: none !important; }
  body { background: white; } .app { display:block; padding:0; margin:0; } .main { width:100%; }
  .panel { box-shadow:none !important; border-radius:0 !important; }
  .print-header { display:block !important; }
}
</style>
</head>
<body>
<div class="shop-topbar screen-only"><div class="shop-topbar-inner">
  <a class="wordmark" href="<?php echo $isAdmin ? 'admin_dashboard.php' : 'pegawai_dashboard.php'; ?>">shop<i>.</i> staff</a>
  <span class="role-dot"><?php echo $isAdmin ? 'ADMIN' : 'PEGAWAI'; ?></span><div class="topbar-spacer"></div><a class="pill" href="katalog.php">Lihat Toko</a><a class="pill pill-dark" href="logout.php">Logout</a>
</div></div>
<div class="app">
  <div class="side screen-only"><?php shop_sidebar_staff($_SESSION['role'], 'cetak_laporan.php'); ?></div>
  <div class="main">
    <div class="print-header" style="display:none; text-align:center; margin-bottom:20px; border-bottom:2px solid #000; padding-bottom:10px;">
      <h1 style="margin:0;">shop.</h1><p>Laporan Penjualan — <?php echo htmlspecialchars($periodeLabel); ?> | <?php echo date('d/m/Y H:i'); ?></p>
    </div>
    <div class="panel screen-only"><h3>Filter Periode</h3>
      <form method="GET" action="" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
        <div class="field"><label>Dari</label><input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"></div>
        <div class="field"><label>Sampai</label><input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"></div>
        <button class="btn btn-primary" type="submit">Filter</button>
        <a class="btn" href="cetak_laporan.php">Reset</a>
      </form></div>
    <div class="panel">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
        <h3 style="margin:0;">Laporan — <?php echo htmlspecialchars($periodeLabel); ?></h3>
        <button class="btn btn-dark screen-only" onclick="window.print()">Cetak / PDF</button>
      </div>
      <div class="stat-row"><div class="stat"><h4>Total Transaksi</h4><p class="n"><?php echo number_format($totalCount); ?></p></div>
      <div class="stat"><h4>Pendapatan</h4><p class="n" style="font-size:16px;">Rp <?php echo number_format($grandTotal,0,',','.'); ?></p></div></div>
      <div class="table-wrap"><table class="tbl"><thead><tr><th>#</th><th>Tanggal</th><th>Customer</th><th>Barang</th><th>Harga</th><th>Qty</th><th>Subtotal</th></tr></thead><tbody>
      <?php if ($transactions->num_rows > 0): $no=1; while ($row = $transactions->fetch_assoc()): ?>
        <tr><td><?php echo $no++; ?></td><td><?php echo date('d/m/Y H:i', strtotime($row['transaction_date'])); ?></td><td><?php echo htmlspecialchars($row['username']); ?></td><td><?php echo htmlspecialchars($row['item_name']); ?></td><td>Rp <?php echo number_format($row['unit_price'],0,',','.'); ?></td><td><?php echo $row['quantity']; ?></td><td><strong>Rp <?php echo number_format($row['total_price'],0,',','.'); ?></strong></td></tr>
      <?php endwhile; else: ?><tr><td colspan="7" class="hint" style="text-align:center;">Tidak ada transaksi.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  </div>
</div>
</body>
</html>
