<?php
session_start();
require_once("security_headers.php");
require_once("session_guard.php");
require_role(['admin']);
require_once("class/ActivityLog.php");
require_once("partials/shop-ui.php");
$logger = new ActivityLog(); $logs = $logger->getRecentLogs(150);
require_once("connection.php");
$conn = Database::get(); $suspicious_ips = [];
$alertQuery = $conn->query("SELECT ip_address, COUNT(*) as total FROM activity_logs WHERE event_type = 'LOGIN_FAILED' AND created_at >= NOW() - INTERVAL '15 minutes' GROUP BY ip_address HAVING COUNT(*) >= 3");
if ($alertQuery) { while ($row = $alertQuery->fetch_assoc()) { $suspicious_ips[$row['ip_address']] = $row['total']; } }
?>
<!DOCTYPE html>
<html lang="id">
<head><?php shop_head('Activity Logs - SONY Shop'); ?></head>
<body>
<div class="shop-topbar"><div class="shop-topbar-inner">
  <a class="wordmark" href="admin_dashboard.php">shop<i>.</i> admin</a><span class="role-dot">ADMIN</span><div class="topbar-spacer"></div><a class="pill" href="katalog.php">Lihat Toko</a><a class="pill pill-dark" href="logout.php">Logout</a>
</div></div>
<div class="app">
  <div class="side"><?php shop_sidebar_staff('admin', 'activity_logs.php'); ?></div>
  <div class="main">
    <?php if (!empty($suspicious_ips)): ?><div class="panel" style="border:1px solid #f5c6cb;"><h3>Peringatan brute-force</h3><ul>
      <?php foreach ($suspicious_ips as $ip => $count): ?><li>IP <strong><?php echo htmlspecialchars($ip); ?></strong> — <?php echo $count; ?>x gagal dalam 15 menit.</li><?php endforeach; ?>
    </ul></div><?php endif; ?>
    <div class="panel"><h3>Log Aktivitas (150 terbaru)</h3>
      <div class="table-wrap"><table class="tbl"><thead><tr><th>Waktu</th><th>User</th><th>Event</th><th>Deskripsi</th><th>IP</th></tr></thead><tbody>
      <?php if ($logs->num_rows > 0): while ($row = $logs->fetch_assoc()): ?>
        <tr><td><?php echo date('d/m/Y H:i:s', strtotime($row['created_at'])); ?></td><td><?php echo htmlspecialchars($row['username'] ?? '-'); ?></td>
        <td><span class="badge b-gray"><?php echo htmlspecialchars($row['event_type']); ?></span></td>
        <td><?php echo htmlspecialchars($row['description'] ?? '-'); ?></td><td style="font-family:monospace;"><?php echo htmlspecialchars($row['ip_address'] ?? '-'); ?></td></tr>
      <?php endwhile; else: ?><tr><td colspan="5" class="hint" style="text-align:center;">Belum ada log.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  </div>
</div>
</body>
</html>
