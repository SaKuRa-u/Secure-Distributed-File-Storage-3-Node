<?php
require_once("connection.php");
require_once("ActivityLog.php");

class Auth {
    protected $sqli;
    protected $logger;

    const MAX_ATTEMPTS  = 5;          
    const LOCKOUT_TIME  = 15 * 60;    

    public function __construct() {
        $this->sqli = Database::get();
        $this->logger = new ActivityLog();
    }

    public function register($username, $password, $role = 'customer') {
        $allowed_roles = ['admin', 'pegawai', 'customer'];
        if (!in_array($role, $allowed_roles)) {
            $role = 'customer';
        }

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $sql  = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
        $stmt = $this->sqli->prepare($sql);
        $stmt->bind_param("sss", $username, $hashed_password, $role);

        $success = $stmt->execute();

        if ($success) {
            $new_id = $this->sqli->insert_id;
            $this->logger->log($new_id, $username, 'REGISTER', "Pendaftaran akun baru dengan role: $role");
        }

        return $success;
    }

    public function isLockedOut($ip_address) {
        $failedCount = $this->logger->countRecentFailedLogins($ip_address, self::LOCKOUT_TIME / 60);
        return $failedCount >= self::MAX_ATTEMPTS;
    }

    public function login($username, $password) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if ($this->isLockedOut($ip_address)) {
            $this->logger->log(null, $username, 'LOGIN_BLOCKED', "Login diblokir sementara karena terlalu banyak percobaan gagal dari IP ini.");
            return 'LOCKED_OUT';
        }

        $sql  = "SELECT * FROM users WHERE username = ?";
        $stmt = $this->sqli->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Akun SSO (password NULL) wajib via SSO — login lokal selalu gagal.
            if (!isset($row['password']) || !is_string($row['password']) || $row['password'] === '') {
                $this->logger->log($row['id'], $row['username'], 'LOGIN_FAILED', "Login lokal ditolak (akun SSO): $username");
                return false;
            }
            if (password_verify($password, $row['password'])) {
                $this->logger->log($row['id'], $row['username'], 'LOGIN_SUCCESS', 'Login berhasil.');
                return $row;
            }
        }

        $this->logger->log(null, $username, 'LOGIN_FAILED', "Percobaan login gagal untuk username: $username");
        return false;
    }
}
?>
