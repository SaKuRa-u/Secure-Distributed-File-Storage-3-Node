<?php
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_role(['admin', 'pegawai']);
require_once("class/Order.php");
require_once("class/ActivityLog.php");
require_once("csrf_helper.php");
require_once("partials/shop-ui.php");
$orderObj = new Order(); $logger = new ActivityLog(); $message = "";
if (isset($_POST['btnApprove'])) { csrf_validate(); $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
  if ($order_id) { $result = $orderObj->approveOrder($order_id);
    if ($result === true) { $message = "<div class='msg success'>Pesanan #$order_id disetujui, stok dikurangi.</div>"; $logger->log($_SESSION['id'], $_SESSION['username'], 'ORDER_APPROVED', "Menyetujui pesanan #$order_id."); }
    else { $message = "<div class='msg error'>" . htmlspecialchars($result) . "</div>"; } } }
if (isset($_POST['btnReject'])) { csrf_validate(); $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
  if ($order_id) { if ($orderObj->rejectOrder($order_id)) { $message = "<div class='msg success'>Pesanan #$order_id ditolak.</div>"; $logger->log($_SESSION['id'], $_SESSION['username'], 'ORDER_REJECTED', "Menolak pesanan #$order_id."); }
    else { $message = "<div class='msg error'>Gagal menolak pesanan.</div>"; } } }
if (isset($_POST['btnComplete'])) { csrf_validate(); $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
  if ($order_id) { if ($orderObj->completeOrder($order_id)) { $message = "<div class='msg success'>Pesanan #$order_id selesai.</div>"; $logger->log($_SESSION['id'], $_SESSION['username'], 'ORDER_COMPLETED', "Menyelesaikan pesanan #$order_id."); }
    else { $message = "<div class='msg error'>Gagal menyelesaikan pesanan.</div>"; } } }
$statusFilter = $_GET['status'] ?? null; $allOrders = $orderObj->getAllOrders($statusFilter);
$isAdmin = ($_SESSION['role'] === 'admin');
$statusLabel = ['pending'=>['Menunggu','b-yellow'],'approved'=>['Disetujui','b-green'],'rejected'=>['Ditolak','b-red'],'completed'=>['Selesai','b-blue'],];
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Kelola Pesanan - SONY Shop'); ?></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="<?php echo $isAdmin ? 'admin_dashboard.php' : 'pegawai_dashboard.php'; ?>">shop<i>.</i> staff</a>
  <span class="role-dot"><?php echo $isAdmin ? 'ADMIN' : 'PEGAWAI'; ?></span><div class="topbar-spacer"></div><a class="pill" href="katalog.php">Lihat Toko</a><a class="pill pill-dark" href="logout.php">Logout</a>
</div></div>
<div class="app">
  <div class="side"><?php shop_sidebar_staff($_SESSION['role'], 'kelola_pesanan.php'); ?></div>
  <div class="main">
    <?php echo $message; ?>
    <div class="cat-row" style="justify-content:flex-start; margin:0 0 16px;">
      <a class="cat-pill" href="kelola_pesanan.php">Semua</a>
      <a class="cat-pill" href="kelola_pesanan.php?status=pending">Pending</a>
      <a class="cat-pill" href="kelola_pesanan.php?status=approved">Disetujui</a>
      <a class="cat-pill" href="kelola_pesanan.php?status=completed">Selesai</a>
      <a class="cat-pill" href="kelola_pesanan.php?status=rejected">Ditolak</a>
    </div>
    <div class="panel"><h3>Daftar Pesanan</h3>
      <div class="table-wrap"><table class="tbl"><thead><tr><th>#</th><th>Tanggal</th><th>Customer</th><th>Barang</th><th>Qty</th><th>Total</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
      <?php if ($allOrders->num_rows > 0): while ($row = $allOrders->fetch_assoc()): $st = $statusLabel[$row['status']] ?? ['?', 'b-gray']; ?>
        <tr><td><?php echo $row['id']; ?></td><td><?php echo date('d/m/Y H:i', strtotime($row['order_date'])); ?></td>
        <td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td><td><?php echo htmlspecialchars($row['item_name']); ?></td>
        <td><?php echo $row['quantity']; ?></td><td>Rp <?php echo number_format($row['total_price'],0,',','.'); ?></td>
        <td><span class="badge <?php echo $st[1]; ?>"><?php echo $st[0]; ?></span></td>
        <td><?php if ($row['status'] === 'pending'): ?>
          <form method="POST" action="" style="display:inline;"><?php csrf_field(); ?><input type="hidden" name="order_id" value="<?php echo $row['id']; ?>"><button class="btn btn-primary" type="submit" name="btnApprove">Setujui</button></form>
          <form method="POST" action="" style="display:inline;"><?php csrf_field(); ?><input type="hidden" name="order_id" value="<?php echo $row['id']; ?>"><button class="btn" type="submit" name="btnReject" onclick="return confirm('Tolak pesanan ini?')">Tolak</button></form>
        <?php elseif ($row['status'] === 'approved'): ?>
          <form method="POST" action="" style="display:inline;"><?php csrf_field(); ?><input type="hidden" name="order_id" value="<?php echo $row['id']; ?>"><button class="btn btn-dark" type="submit" name="btnComplete">Selesai</button></form>
        <?php else: ?><span class="hint">—</span><?php endif; ?></td></tr>
      <?php endwhile; else: ?><tr><td colspan="8" class="hint" style="text-align:center;">Tidak ada pesanan.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  </div>
</div>
</body>
</html>
