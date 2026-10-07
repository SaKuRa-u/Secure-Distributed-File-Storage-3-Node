<?php
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_role(['admin', 'pegawai']);
require_once("class/Category.php");
require_once("class/ActivityLog.php");
require_once("csrf_helper.php");
require_once("partials/shop-ui.php");
$catObj = new Category(); $logger = new ActivityLog(); $message = "";
if (isset($_POST['btnSimpan'])) {
    csrf_validate();
    $category_name = trim($_POST['category_name']);
    if (!empty($category_name)) {
        if ($catObj->insertCategory($category_name)) { $message = "<div class='msg success'>Kategori berhasil ditambahkan!</div>"; $logger->log($_SESSION['id'], $_SESSION['username'], 'CATEGORY_CREATE', "Menambahkan kategori: $category_name"); }
        else { $message = "<div class='msg error'>Gagal menambahkan kategori.</div>"; }
    }
}
$categories = $catObj->getCategories();
$isAdmin = ($_SESSION['role'] === 'admin');
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Kelola Kategori - SONY Shop'); ?></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="<?php echo $isAdmin ? 'admin_dashboard.php' : 'pegawai_dashboard.php'; ?>">shop<i>.</i> staff</a>
  <span class="role-dot"><?php echo $isAdmin ? 'ADMIN' : 'PEGAWAI'; ?></span><div class="topbar-spacer"></div><a class="pill" href="katalog.php">Lihat Toko</a><a class="pill pill-dark" href="logout.php">Logout</a>
</div></div>
<div class="app">
  <div class="side"><?php shop_sidebar_staff($_SESSION['role'], 'tambah_kategori.php'); ?></div>
  <div class="main">
    <div class="panel"><h3>Tambah Kategori Baru</h3><?php echo $message; ?>
      <form method="POST" action=""><?php csrf_field(); ?>
        <div class="field"><label>Nama Kategori</label><input type="text" name="category_name" placeholder="Contoh: Makanan, Elektronik..." required autocomplete="off"></div>
        <button class="btn btn-primary" type="submit" name="btnSimpan">Simpan Kategori</button>
      </form></div>
    <div class="panel"><h3>Daftar Kategori</h3>
      <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <?php while ($row = $categories->fetch_assoc()): ?>
        <span class="cat-pill"><span class="dot" style="background:#5433eb"></span><?php echo htmlspecialchars($row['category_name']); ?> · #<?php echo $row['id']; ?></span>
      <?php endwhile; ?>
      </div></div>
  </div>
</div>
</body>
</html>
