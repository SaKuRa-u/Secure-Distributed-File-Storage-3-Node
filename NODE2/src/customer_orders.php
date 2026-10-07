<?php
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_role(['customer']);
require_once("class/Item.php");
require_once("class/Order.php");
require_once("class/ActivityLog.php");
require_once("csrf_helper.php");
require_once("partials/shop-ui.php");
$itemObj  = new Item(); $orderObj = new Order(); $logger = new ActivityLog(); $message = "";
if (isset($_POST['btnPesan'])) {
    csrf_validate();
    $item_id  = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    if ($item_id === false || $item_id === null || $item_id <= 0 || $quantity === false || $quantity === null || $quantity <= 0) {
        $message = "<div class='msg error'>Input tidak valid.</div>";
    } else {
        $result = $orderObj->createOrder($_SESSION['id'], $item_id, $quantity);
        if ($result === true) { $message = "<div class='msg success'>Pesanan berhasil dibuat! Menunggu approval.</div>"; $logger->log($_SESSION['id'], $_SESSION['username'], 'ORDER_CREATE', "Membuat pesanan item_id=$item_id sejumlah $quantity unit."); }
        else { $message = "<div class='msg error'>" . htmlspecialchars($result) . "</div>"; }
    }
}
$items = $itemObj->getItems(); $myOrders = $orderObj->getOrdersByUser($_SESSION['id']);
$statusLabel = ['pending'=>['Menunggu Approval','b-yellow'],'approved'=>['Disetujui','b-green'],'rejected'=>['Ditolak','b-red'],'completed'=>['Selesai','b-blue'],];
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Pemesanan Saya - SONY Shop'); ?></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="katalog.php">shop<i>.</i></a><span class="role-dot">CUSTOMER</span><div class="topbar-spacer"></div>
  <a class="pill pill-dark" href="sso-logout.php">Logout</a>
</div></div>
<div class="app">
  <div class="side"><?php shop_sidebar_customer('customer_orders.php'); ?></div>
  <div class="main">
    <div class="panel"><h3>Buat Pesanan Baru (PO)</h3>
      <p class="hint">Pesanan diproses setelah disetujui admin/pegawai. Stok dikurangi saat disetujui.</p>
      <?php echo $message; ?>
      <form method="POST" action="" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
        <?php csrf_field(); ?>
        <div class="field" style="flex:1; min-width:200px;"><label>Pilih Barang</label>
          <select name="item_id" required><option value="">-- Pilih Barang --</option>
          <?php while ($it = $items->fetch_assoc()): ?><option value="<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['item_name']); ?> — Rp <?php echo number_format($it['price'],0,',','.'); ?> (Stok: <?php echo $it['stock']; ?>)</option><?php endwhile; ?>
          </select></div>
        <div class="field" style="max-width:120px;"><label>Jumlah</label><input type="number" name="quantity" min="1" value="1" required></div>
        <button class="btn btn-primary" type="submit" name="btnPesan">Buat Pesanan</button>
      </form>
    </div>
    <div class="panel"><h3>Riwayat Pesanan Saya</h3>
      <p class="hint">Status PO: menunggu / disetujui / ditolak / selesai. Transaksi langsung yang sudah dibayar ada di <a href="riwayat.php">Riwayat</a>.</p>
      <div class="table-wrap"><table class="tbl"><thead><tr><th></th><th>Tanggal</th><th>Barang</th><th>Qty</th><th>Total</th><th>Status</th></tr></thead><tbody>
      <?php if ($myOrders->num_rows > 0): while ($row = $myOrders->fetch_assoc()): $st = $statusLabel[$row['status']] ?? ['?', 'b-gray'];
        $fb = !empty($row['image']) ? "uploads/".basename($row['image']) : "https://via.placeholder.com/100?text=No";
        $thumb = !empty($row['image']) ? SeaweedFS::resolveUrl($row['image'],'product',$fb) : "https://via.placeholder.com/100?text=No";
      ?>
        <tr><td><img src="<?php echo htmlspecialchars($thumb); ?>" alt="" loading="lazy" style="width:48px;height:48px;object-fit:cover;border-radius:12px;border:1px solid #ebebeb;"></td><td><?php echo date('d/m/Y H:i', strtotime($row['order_date'])); ?></td><td><?php echo htmlspecialchars($row['item_name']); ?></td><td><?php echo $row['quantity']; ?></td><td>Rp <?php echo number_format($row['total_price'],0,',','.'); ?></td><td><span class="badge <?php echo $st[1]; ?>"><?php echo $st[0]; ?></span></td></tr>
      <?php endwhile; else: ?><tr><td colspan="6" style="text-align:center;" class="hint">Belum ada pesanan.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  </div>
</div>
</body>
</html>
