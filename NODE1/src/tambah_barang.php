<?php
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_role(['admin', 'pegawai']);
require_once("class/Item.php");
require_once("class/Category.php");
require_once("class/ActivityLog.php");
require_once("csrf_helper.php");
require_once("partials/shop-ui.php");
$itemObj = new Item(); $catObj = new Category(); $logger = new ActivityLog(); $message = "";
if (isset($_POST['btnHapus'])) {
    csrf_validate();
    $del_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    if ($del_id === false || $del_id === null || $del_id <= 0) {
        $message = "<div class='msg error'>ID barang tidak valid.</div>";
    } else {
        $res = $itemObj->deleteItem($del_id);
        if ($res === true) {
            $message = "<div class='msg success'>Barang #$del_id berhasil dihapus.</div>";
            $logger->log($_SESSION['id'], $_SESSION['username'], 'ITEM_DELETE', "Menghapus barang id=$del_id.");
        } else {
            $message = "<div class='msg error'>" . htmlspecialchars($res) . "</div>";
            $logger->log($_SESSION['id'], $_SESSION['username'], 'ITEM_DELETE_FAILED', "Gagal hapus barang id=$del_id: $res");
        }
    }
}
if (isset($_POST['btnSimpan'])) {
    csrf_validate();
    $item_name = trim($_POST['item_name']); $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT); $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
    $imageData = $_FILES['image'];
    if (empty($item_name) || $category_id === false || $category_id === null || $price === false || $price === null || $price < 0 || $stock === false || $stock === null || $stock < 0) {
        $message = "<div class='msg error'>Input tidak valid.</div>";
    } else {
        $res = $itemObj->insertItem($category_id, $item_name, $price, $stock, $imageData);
        if ($res === true) {
            $message = "<div class='msg success'>Barang berhasil ditambahkan!</div>";
            $logger->log($_SESSION['id'], $_SESSION['username'], 'ITEM_CREATE', "Menambahkan barang: $item_name (kategori_id=$category_id, stok=$stock)");
        } else {
            $message = "<div class='msg error'>" . htmlspecialchars($res) . "</div>";
            $logger->log($_SESSION['id'], $_SESSION['username'], 'ITEM_CREATE_FAILED', "Gagal tambah barang: $item_name — $res");
        }
    }
}
$categories = $catObj->getCategories(); $items = $itemObj->getItems();
$isAdmin = ($_SESSION['role'] === 'admin');
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Kelola Barang - SONY Shop'); ?></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="<?php echo $isAdmin ? 'admin_dashboard.php' : 'pegawai_dashboard.php'; ?>">shop<i>.</i> staff</a>
  <span class="role-dot"><?php echo $isAdmin ? 'ADMIN' : 'PEGAWAI'; ?></span><div class="topbar-spacer"></div><a class="pill" href="katalog.php">Lihat Toko</a><a class="pill pill-dark" href="logout.php">Logout</a>
</div></div>
<div class="app">
  <div class="side"><?php shop_sidebar_staff($_SESSION['role'], 'tambah_barang.php'); ?></div>
  <div class="main">
    <div class="panel"><h3>Tambah Barang Baru</h3><?php echo $message; ?>
      <form method="POST" action="" enctype="multipart/form-data">
        <?php csrf_field(); ?>
        <div class="field"><label>Nama Barang</label><input type="text" name="item_name" required autocomplete="off"></div>
        <div class="field"><label>Kategori</label><select name="category_id" required><option value="">-- Pilih Kategori --</option>
        <?php while ($cat = $categories->fetch_assoc()): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option><?php endwhile; ?></select></div>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
          <div class="field" style="flex:1; min-width:140px;"><label>Harga (Rp)</label><input type="number" name="price" required min="0"></div>
          <div class="field" style="flex:1; min-width:140px;"><label>Stok Awal</label><input type="number" name="stock" required min="0"></div>
        </div>
        <div class="field"><label>Gambar Produk (Wajib .png, Maks 2MB → SeaweedFS)</label><input type="file" name="image" accept=".png" required></div>
        <button class="btn btn-primary" type="submit" name="btnSimpan">+ Tambah Barang</button>
      </form>
    </div>
    <div class="panel"><h3>Daftar Barang Saat Ini</h3>
      <div class="table-wrap"><table class="tbl"><tr><th>Gambar</th><th>Nama</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr>
      <?php require_once("class/SeaweedFS.php"); while ($row = $items->fetch_assoc()):
        $fallback = !empty($row['image']) ? "uploads/" . basename($row['image']) : "uploads/default.png";
        $img = !empty($row['image']) ? SeaweedFS::resolveUrl($row['image'], 'product', $fallback) : "uploads/default.png"; ?>
        <tr><td><img src="<?php echo htmlspecialchars($img); ?>" style="width:50px;height:50px;object-fit:cover;border-radius:12px;border:1px solid #ebebeb;" loading="lazy"></td>
        <td><?php echo htmlspecialchars($row['item_name']); ?></td><td><?php echo htmlspecialchars($row['category_name']); ?></td>
        <td>Rp <?php echo number_format($row['price'],0,',','.'); ?></td><td><?php echo $row['stock']; ?></td>
        <td><form method="POST" action="" style="display:inline;" onsubmit="return confirm('Hapus <?php echo htmlspecialchars(addslashes($row['item_name'])); ?>? Hanya bisa bila belum ada pesanan/transaksi.');">
          <?php csrf_field(); ?><input type="hidden" name="item_id" value="<?php echo (int)$row['id']; ?>">
          <button class="btn" type="submit" name="btnHapus" style="background:#dc3545;border-color:#dc3545;color:#fff;padding:8px 14px;">Hapus</button>
        </form></td></tr>
      <?php endwhile; ?></table></div>
    </div>
  </div>
</div>
</body>
</html>
