<?php
require_once("connection.php");

class ActivityLog {
    protected $sqli;

    public function __construct() {
        $this->sqli = Database::get();
    }

    public function log($user_id, $username, $event_type, $description = '') {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $sql  = "INSERT INTO activity_logs (user_id, username, event_type, description, ip_address) 
                 VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->sqli->prepare($sql);
        $stmt->bind_param("issss", $user_id, $username, $event_type, $description, $ip_address);
        return $stmt->execute();
    }

    public function getRecentLogs($limit = 100) {
        $limit = (int) $limit;
        $sql   = "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT $limit";
        $stmt  = $this->sqli->prepare($sql);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function countRecentFailedLogins($ip_address, $minutes = 15) {
        $minutes = (int) $minutes;
        // PostgreSQL: NOW() - (INTERVAL '1 minute' * N)
        $sql  = "SELECT COUNT(*) as total FROM activity_logs 
                 WHERE event_type = 'LOGIN_FAILED' 
                 AND ip_address = ? 
                 AND created_at >= NOW() - (INTERVAL '1 minute' * $minutes)";
        $stmt = $this->sqli->prepare($sql);
        $stmt->bind_param("s", $ip_address);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row['total'] ?? 0;
    }
}
?>
