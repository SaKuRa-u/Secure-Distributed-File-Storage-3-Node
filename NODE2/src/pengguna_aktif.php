<?php
// Pengguna Aktif (admin only): siapa sedang online + terakhir terlihat.
// Sumber: activity_logs (event LOGIN_SUCCESS/SSO_LOGIN + aksi lain).
// Online = aktivitas <= 5 menit; di bawahnya tampil terakhir terlihat.
// Auto-refresh 60 detik (meta, tanpa JS).
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_once("partials/shop-ui.php");
require_once("connection.php");
require_role(['admin']);

const ACTIVE_ONLINE_SECONDS = 300;   // <= 5 menit = Online
const ACTIVE_WINDOW_SECONDS = 1800;  // tampilkan yang aktif <= 30 menit

$db = Database::get();
$stmt = $db->prepare(
    "SELECT u.id, u.username, u.role, u.email, MAX(a.created_at) AS last_seen,
            (SELECT a2.event_type FROM activity_logs a2
              WHERE a2.user_id = u.id ORDER BY a2.created_at DESC LIMIT 1) AS last_event
       FROM users u LEFT JOIN activity_logs a ON a.user_id = u.id
      GROUP BY u.id, u.username, u.role, u.email
      ORDER BY last_seen DESC NULLS LAST"
);
$stmt->execute();
$rows = [];
$res = $stmt->get_result();
if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }

$now = time();
$online = 0;
foreach ($rows as &$r) {
    $ts = $r['last_seen'] !== null ? strtotime((string)$r['last_seen']) : false;
    $r['_ts'] = $ts;
    $r['_online'] = ($ts !== false && ($now - $ts) <= ACTIVE_ONLINE_SECONDS);
    if ($r['_online']) $online++;
    $r['_recent'] = ($ts !== false && ($now - $ts) <= ACTIVE_WINDOW_SECONDS);
}
unset($r);
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Pengguna Aktif - SONY Shop'); ?>
<meta http-equiv="refresh" content="60"></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="admin_dashboard.php">shop<i>.</i> admin</a><span class="role-dot">ADMIN</span><div class="topbar-spacer"></div><a class="pill" href="katalog.php">Lihat Toko</a><a class="pill pill-dark" href="logout.php">Logout</a>
</div></div>
<div class="app">
  <div class="side"><?php shop_sidebar_staff('admin', 'pengguna_aktif.php'); ?></div>
  <div class="main">
    <div class="stat-row"><div class="stat"><h4>Sedang Online</h4><p class="n"><?php echo $online; ?></p></div></div>
    <div class="panel"><h3>Pengguna Aktif</h3>
      <p class="hint">Online = aktivitas &le; 5 menit. Data dari log aktivitas; refresh otomatis 60 detik.</p>
      <div class="table-wrap"><table class="tbl"><thead><tr><th>Status</th><th>Username</th><th>Role</th><th>Email</th><th>Aksi terakhir</th><th>Terakhir terlihat</th></tr></thead><tbody>
      <?php $shown = 0; foreach ($rows as $r): ?>
        <?php if (!$r['_recent'] && !$r['_online']) continue; $shown++; ?>
        <?php $bc = $r['role']==='admin'?'b-red':($r['role']==='pegawai'?'b-yellow':'b-green'); ?>
        <tr>
          <td><span class="badge <?php echo $r['_online'] ? 'b-green' : 'b-gray'; ?>"><?php echo $r['_online'] ? 'Online' : 'Aktif'; ?></span></td>
          <td><strong><?php echo htmlspecialchars((string)$r['username']); ?></strong></td>
          <td><span class="badge <?php echo $bc; ?>"><?php echo strtoupper(htmlspecialchars((string)$r['role'])); ?></span></td>
          <td><?php echo htmlspecialchars((string)($r['email'] ?? '-')); ?></td>
          <td><span class="badge b-gray"><?php echo htmlspecialchars((string)($r['last_event'] ?? '-')); ?></span></td>
          <td><?php echo $r['_ts'] !== false ? date('d-m H:i:s', $r['_ts']) : '-'; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($shown === 0): ?><tr><td colspan="6" class="hint" style="text-align:center;">Tidak ada pengguna aktif dalam 30 menit terakhir.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  </div>
</div>
</body>
</html>
