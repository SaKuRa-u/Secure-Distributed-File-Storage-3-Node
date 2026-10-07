<?php
// Wrapper PDO-PostgreSQL dengan API mirip mysqli.
// Tujuannya: migrasi MySQL -> PostgreSQL TANPA mengubah semua view
// ($res->fetch_assoc(), $res->num_rows tetap jalan).
//
// Pemakaian (menggantikan `new mysqli(...)`):
//   $db   = Database::get();
//   $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
//   $stmt->bind_param("i", $id);   // $types diabaikan (PG strongly-typed via PDO)
//   $stmt->execute();
//   $row = $stmt->get_result()->fetch_assoc();
//
//   $res = $db->query("SELECT COUNT(*) as total FROM items");
//   $row = $res->fetch_assoc();

if (!defined('SERVER_NAME')) {
    require_once(__DIR__ . "/../connection.php");
}

class DbResult {
    public $num_rows = 0;
    private $rows = [];
    private $pos = 0;

    public function __construct(array $rows = []) {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc() {
        if ($this->pos < count($this->rows)) {
            return $this->rows[$this->pos++];
        }
        return null;
    }

    // Alias agar kode yang memakai fetch_array / fetch_row tetap jalan
    public function fetch_array() { return $this->fetch_assoc(); }
    public function fetch_row() {
        $r = $this->fetch_assoc();
        return $r === null ? null : array_values($r);
    }
}

class DbStatement {
    private $pdo;
    private $sql;
    private $params = [];
    private $result = null;
    private $ok = false;

    public function __construct($pdo, $sql) {
        $this->pdo = $pdo;
        $this->sql = $sql;
    }

    // mysqli: bind_param("isdis", $a, $b, ...) — $types diabaikan, value dicopy.
    public function bind_param($types, ...$vars) {
        $this->params = $vars;
        return true;
    }

    public function execute($extra = null) {
        if (is_array($extra) && !empty($extra)) {
            $this->params = $extra;
        }
        try {
            $st = $this->pdo->prepare($this->sql);
            $ok = $st->execute($this->params);
            $this->ok = $ok;
            if ($ok) {
                // get_result() ala mysqli: kumpulkan semua baris.
                // Untuk INSERT/UPDATE/DELETE, fetchAll = [] (tidak error).
                try {
                    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $rows = [];
                }
                $this->result = new DbResult($rows ?: []);
            } else {
                $this->result = new DbResult([]);
            }
            return $ok;
        } catch (Exception $e) {
            error_log("[DB] execute gagal: " . $e->getMessage() . " | SQL: " . $this->sql);
            $this->result = new DbResult([]);
            return false;
        }
    }

    public function get_result() {
        return $this->result ?: new DbResult([]);
    }
}

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $host = defined('SERVER_NAME') ? SERVER_NAME : 'db';
        $port = defined('DB_PORT') ? DB_PORT : '5432';
        $db   = defined('DB_NAME') ? DB_NAME : 'psp_project';
        $user = defined('USER_NAME') ? USER_NAME : 'psp';
        $pass = defined('PASSWORD') ? PASSWORD : 'psp_secret';
        $dsn  = "pgsql:host=$host;port=$port;dbname=$db";
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function get() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function prepare($sql) {
        return new DbStatement($this->pdo, $sql);
    }

    public function query($sql) {
        try {
            $st = $this->pdo->query($sql);
            $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
            return new DbResult($rows ?: []);
        } catch (Exception $e) {
            error_log("[DB] query gagal: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }

    // mysqli: $db->insert_id (properti) — PDO PG butuh lastval()
    public function __get($name) {
        if ($name === 'insert_id') {
            return $this->insertId();
        }
        if ($name === 'connect_error') {
            return null; // konstruktor throw kalau gagal, jadi tidak ada connect_error
        }
        return null;
    }

    private function insertId() {
        try {
            $v = $this->pdo->lastInsertId();
            if ($v !== '' && $v !== false && $v !== null) return (int) $v;
        } catch (Exception $e) { /* fallback ke lastval() */ }
        try {
            $r = $this->pdo->query("SELECT lastval()");
            $row = $r->fetch(PDO::FETCH_NUM);
            return (int) ($row[0] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    public function begin_transaction() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollback() { return $this->pdo->rollBack(); }
}
