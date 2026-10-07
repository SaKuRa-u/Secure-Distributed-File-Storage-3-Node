<?php
// Belanja (customer): etalase sama seperti katalog publik — klik Rincian
// untuk ke halaman rincian (produk.php), beli via sana / keranjang.
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_role(['customer']);
require_once("class/Item.php");
require_once("partials/shop-ui.php");

$itemObj = new Item();
$res = $itemObj->getItems();
$products = []; if ($res) { while ($r = $res->fetch_assoc()) { $products[] = $r; } }

$q = strtolower(trim($_GET['q'] ?? ''));
$catF = trim($_GET['cat'] ?? '');
if ($q !== '') {
  $products = array_values(array_filter($products, function($p) use ($q) {
    return strpos(strtolower($p['item_name'] . ' ' . $p['category_name']), $q) !== false;
  }));
}
if ($catF !== '') {
  $products = array_values(array_filter($products, function($p) use ($catF) {
    return $p['category_name'] === $catF;
  }));
}
$resAll = $itemObj->getItems();
$allCats = [];
if ($resAll) { while ($r = $resAll->fetch_assoc()) { $allCats[$r['category_name']] = true; } }
$allCats = array_keys($allCats);
sort($allCats);
$pal = ['#5433eb','#e36d5d','#5d9e6b','#c9a24b','#5d8fe3'];
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Belanja - SONY Shop'); ?></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="katalog.php">shop<i>.</i></a>
  <span class="role-dot">CUSTOMER · <?php echo htmlspecialchars($_SESSION['username']); ?></span>
  <div class="topbar-spacer"></div>
  <a class="pill pill-dark" href="sso-logout.php">Logout</a>
</div></div>
<div class="app">
  <div class="side"><?php shop_sidebar_customer('customer_dashboard.php'); ?></div>
  <div class="main">
    <form class="search-pill" method="GET" action="customer_dashboard.php" style="margin-bottom:12px;">
      <input type="text" name="q" placeholder="What are you shopping for today?" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
      <input type="hidden" name="cat" value="<?php echo htmlspecialchars($catF); ?>">
      <button class="search-go" type="submit" aria-label="Cari">→</button>
    </form>
    <div class="cat-row" style="justify-content:flex-start; margin:0 0 8px;">
      <a class="cat-pill" href="customer_dashboard.php<?php echo $q !== '' ? '?q='.urlencode($q) : ''; ?>">Semua</a>
      <?php $i=0; foreach ($allCats as $c): $col=$pal[$i % count($pal)]; $i++; ?>
        <a class="cat-pill" href="customer_dashboard.php?cat=<?php echo urlencode($c); ?><?php echo $q !== '' ? '&q='.urlencode($q) : ''; ?>">
          <span class="dot" style="background:<?php echo $col; ?>"></span><?php echo htmlspecialchars($c); ?>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="section-head"><h2><?php echo $catF !== '' ? htmlspecialchars($catF) : 'Produk Tersedia'; ?></h2><span class="chev">›</span></div>
    <div class="grid-4">
      <?php if (!empty($products)): foreach ($products as $row): ?>
        <?php
        require_once("class/SeaweedFS.php");
        $fallbackImg = !empty($row['image']) ? "uploads/" . basename($row['image']) : "https://via.placeholder.com/250x200?text=No+Image";
        $imgPath = !empty($row['image']) ? SeaweedFS::resolveUrl($row['image'], 'product', $fallbackImg) : "https://via.placeholder.com/250x200?text=No+Image";
        ?>
        <div class="p-card">
          <a class="p-img" href="produk.php?id=<?php echo (int)$row['id']; ?>"><img src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($row['item_name']); ?>" loading="lazy"></a>
          <div class="p-body">
            <p class="p-brand"><?php echo htmlspecialchars($row['item_name']); ?></p>
            <div class="p-meta"><?php echo htmlspecialchars($row['category_name']); ?> · Stok <?php echo (int)$row['stock']; ?></div>
            <p class="p-price">Rp <?php echo number_format($row['price'], 0, ',', '.'); ?></p>
            <div class="p-actions"><a class="btn btn-primary btn-block" href="produk.php?id=<?php echo (int)$row['id']; ?>">Rincian</a></div>
          </div>
        </div>
      <?php endforeach; else: ?><p class="hint">Belum ada produk.</p><?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
