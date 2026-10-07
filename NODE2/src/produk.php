<?php
// Rincian produk PUBLIK + aksi keranjang (wajib SSO saat POST).
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_once("class/Item.php");
require_once("csrf_helper.php");
require_once("partials/shop-ui.php");

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    http_response_code(404);
    die("Produk tidak ditemukan. <a href='katalog.php'>Kembali ke katalog</a>");
}

$itemObj = new Item();
$item = $itemObj->getItemById($id);
if (!$item) {
    http_response_code(404);
    die("Produk tidak ditemukan. <a href='katalog.php'>Kembali ke katalog</a>");
}

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    if (!is_sso_customer()) {
        header("Location: sso-login.php?next=" . urlencode('/produk.php?id=' . $id));
        exit();
    }
    $action   = $_POST['action'] ?? '';
    $item_id  = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    if (!in_array($action, ['add', 'buy'], true) || $item_id !== $id || $quantity === false || $quantity === null || $quantity <= 0 || $quantity > (int)$item['stock']) {
        $msg = "<div class='msg error'>Jumlah tidak valid.</div>";
    } else {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
        $_SESSION['cart'][$id] = min((int)$item['stock'], (($_SESSION['cart'][$id] ?? 0) + $quantity));
        if ($action === 'buy') { header("Location: keranjang.php"); exit(); }
        $msg = "<div class='msg success'>Masuk keranjang. <a href='keranjang.php'>Lihat keranjang</a></div>";
    }
}

require_once("class/SeaweedFS.php");
$fallbackImg = !empty($item['image']) ? "uploads/" . basename($item['image']) : "https://via.placeholder.com/400x300?text=No+Image";
$imgPath = !empty($item['image']) ? SeaweedFS::resolveUrl($item['image'], 'product', $fallbackImg) : $fallbackImg;
$login = is_sso_login();
$loginRole = ($login ? ($_SESSION['role'] ?? '') : '');
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head($item['item_name'] . ' - SONY Shop'); ?></head>
<body>
<?php shop_topbar_public($login, $_SESSION['username'] ?? '', $loginRole); ?>
<div class="shop-shell">
  <div style="margin-top:20px;"><a class="pill" href="katalog.php">← Katalog</a></div>
  <div class="detail-wrap">
    <div class="detail-media"><img src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>"></div>
    <div class="detail-info">
      <div class="p-meta"><?php echo htmlspecialchars($item['category_name']); ?></div>
      <h1><?php echo htmlspecialchars($item['item_name']); ?></h1>
      <p class="p-price" style="font-size:20px;">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></p>
      <p class="hint">Stok: <?php echo (int)$item['stock']; ?> · via SeaweedFS object storage</p>
      <?php echo $msg; ?>
      <?php if ((int)$item['stock'] > 0): ?>
        <form method="POST" action="produk.php?id=<?php echo $id; ?>">
          <?php csrf_field(); ?>
          <input type="hidden" name="item_id" value="<?php echo $id; ?>">
          <div class="field"><label>Jumlah</label><input type="number" name="quantity" min="1" max="<?php echo (int)$item['stock']; ?>" value="1" required></div>
          <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button class="btn btn-dark" type="submit" name="action" value="add">Masuk Keranjang</button>
            <button class="btn btn-primary" type="submit" name="action" value="buy">Beli Sekarang</button>
          </div>
        </form>
        <?php if (!$login): ?><p class="hint">Checkout membutuhkan login SSO — kamu akan diarahkan ke Authentik.</p><?php endif; ?>
      <?php else: ?><p><b>Stok habis.</b></p><?php endif; ?>
    </div>
  </div>
  <div class="footer">SONY Shop — shop.</div>
</div>
</body>
</html>
