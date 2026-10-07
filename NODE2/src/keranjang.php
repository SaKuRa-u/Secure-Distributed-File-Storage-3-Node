<?php
// Keranjang belanja (wajib SSO customer). Sumber: $_SESSION['cart'][item_id => qty].
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_sso();
require_once("connection.php");
require_once("class/Transaction.php");
require_once("class/ActivityLog.php");
require_once("csrf_helper.php");
require_once("partials/shop-ui.php");
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
$transObj = new Transaction(); $logger = new ActivityLog(); $message = ""; $receipt = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action  = $_POST['action'] ?? '';
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    if ($action === 'update' && $item_id > 0) {
        $qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        if ($qty === false || $qty === null || $qty < 0) { $message = "<div class='msg error'>Jumlah tidak valid.</div>"; }
        elseif ($qty === 0) { unset($_SESSION['cart'][$item_id]); header("Location: keranjang.php"); exit(); }
        else { $_SESSION['cart'][$item_id] = $qty; header("Location: keranjang.php"); exit(); }
    } elseif ($action === 'remove' && $item_id > 0) {
        unset($_SESSION['cart'][$item_id]); header("Location: keranjang.php"); exit();
    } elseif ($action === 'checkout') {
        if (empty($_SESSION['cart'])) { $message = "<div class='msg error'>Keranjang masih kosong.</div>"; }
        else {
            $lines = []; $errors = []; $total = 0;
            foreach ($_SESSION['cart'] as $cid => $cqty) {
                $cid = (int)$cid; $cqty = (int)$cqty; if ($cid <= 0 || $cqty <= 0) continue;
                $r = $transObj->buyItem((int)$_SESSION['id'], $cid, $cqty);
                if ($r === true) {
                    $stmt = Database::get()->prepare("SELECT item_name, price FROM items WHERE id = ?");
                    $stmt->bind_param("i", $cid); $stmt->execute(); $it = $stmt->get_result()->fetch_assoc();
                    $sub = ($it ? (float)$it['price'] : 0) * $cqty; $total += $sub;
                    $lines[] = ['nama' => $it['item_name'] ?? ('#' . $cid), 'qty' => $cqty, 'sub' => $sub];
                    $logger->log((int)$_SESSION['id'], $_SESSION['username'], 'PURCHASE', "Checkout SSO item_id=$cid sejumlah $cqty unit.");
                    unset($_SESSION['cart'][$cid]);
                } else { $errors[] = "Item #$cid: " . $r; }
            }
            if (!empty($lines)) {
                $receipt = ['lines' => $lines, 'total' => $total, 'date' => date('d/m/Y H:i')];
                $message = empty($errors) ? "<div class='msg success'>Checkout berhasil! Struk di bawah. <a href='customer_orders.php'>Lihat pesanan saya</a></div>"
                  : "<div class='msg error'>Sebagian gagal:<br>" . implode('<br>', array_map('htmlspecialchars', $errors)) . "</div>";
            } else { $message = "<div class='msg error'>Checkout gagal:<br>" . implode('<br>', array_map('htmlspecialchars', $errors)) . "</div>"; }
        }
    }
}
$cart = $_SESSION['cart']; $rows = []; $grand = 0;
if (!empty($cart)) {
    $ids = array_values(array_filter(array_map('intval', array_keys($cart)), fn($v) => $v > 0));
    if (!empty($ids)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::get()->prepare("SELECT i.*, c.category_name FROM items i JOIN categories c ON i.category_id = c.id WHERE i.id IN ($ph)");
        $stmt->execute($ids);
        while ($r = $stmt->get_result()->fetch_assoc()) {
            $q = (int)($cart[$r['id']] ?? 0); if ($q <= 0) continue;
            $r['qty'] = $q; $r['subtotal'] = (float)$r['price'] * $q; $grand += $r['subtotal']; $rows[] = $r;
        }
    }
}
require_once("class/SeaweedFS.php");
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Keranjang - SONY Shop'); ?></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="katalog.php">shop<i>.</i></a>
  <span class="role-dot"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
  <div class="topbar-spacer"></div>
  <a class="pill" href="customer_orders.php">Pesanan Saya</a>
  <a class="pill pill-dark" href="sso-logout.php">Logout</a>
</div></div>
<div class="app">
  <div class="side"><?php shop_sidebar_customer('keranjang.php'); ?></div>
  <div class="main">
  <?php echo $message; ?>
  <?php if ($receipt): ?>
  <div class="panel"><h3>Struk pembelian (<?php echo htmlspecialchars($receipt['date']); ?>)</h3>
    <div class="table-wrap"><table class="tbl"><tr><th>Barang</th><th>Qty</th><th>Subtotal</th></tr>
    <?php foreach ($receipt['lines'] as $l): ?><tr><td><?php echo htmlspecialchars($l['nama']); ?></td><td><?php echo (int)$l['qty']; ?></td><td>Rp <?php echo number_format($l['sub'], 0, ',', '.'); ?></td></tr><?php endforeach; ?>
    <tr><td colspan="2"><b>Total</b></td><td><b>Rp <?php echo number_format($receipt['total'], 0, ',', '.'); ?></b></td></tr></table></div>
  </div><?php endif; ?>
  <div class="panel"><h3>Isi Keranjang</h3>
    <?php if (empty($rows)): ?><p class="hint">Keranjang kosong. <a href="katalog.php">Belanja dulu →</a></p>
    <?php else: foreach ($rows as $r):
      $fb = !empty($r['image']) ? "uploads/".basename($r['image']) : "";
      $img = !empty($r['image']) ? SeaweedFS::resolveUrl($r['image'],'product',$fb) : "https://via.placeholder.com/100?text=No";
    ?>
      <div class="cart-row">
        <img class="cart-thumb" src="<?php echo htmlspecialchars($img); ?>" alt="">
        <div style="flex:1;"><b><?php echo htmlspecialchars($r['item_name']); ?></b><br><span class="hint">Stok <?php echo (int)$r['stock']; ?> · Rp <?php echo number_format($r['price'],0,',','.'); ?></span></div>
        <form method="POST" action="" style="display:flex; gap:8px; align-items:center;">
          <?php csrf_field(); ?><input type="hidden" name="action" value="update"><input type="hidden" name="item_id" value="<?php echo (int)$r['id']; ?>">
          <input class="qty" type="number" name="quantity" min="0" max="<?php echo (int)$r['stock']; ?>" value="<?php echo (int)$r['qty']; ?>">
          <button class="btn" type="submit">Ubah</button>
        </form>
        <form method="POST" action=""><?php csrf_field(); ?><input type="hidden" name="action" value="remove"><input type="hidden" name="item_id" value="<?php echo (int)$r['id']; ?>"><button class="btn" type="submit">Hapus</button></form>
        <b>Rp <?php echo number_format($r['subtotal'],0,',','.'); ?></b>
      </div>
    <?php endforeach; ?>
      <p style="text-align:right; font-size:20px; letter-spacing:-0.05em;">Total: Rp <?php echo number_format($grand,0,',','.'); ?></p>
      <form method="POST" action=""><?php csrf_field(); ?><input type="hidden" name="action" value="checkout"><button class="btn btn-primary" type="submit">Checkout Sekarang →</button></form>
    <?php endif; ?>
  </div>
  </div>
</div>
</body>
</html>
