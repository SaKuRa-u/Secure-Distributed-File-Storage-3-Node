<?php
require_once("connection.php");

class Order {
    protected $sqli;

    public function __construct() {
        $this->sqli = Database::get();
    }

    public function createOrder($user_id, $item_id, $quantity) {
        $sqlItem  = "SELECT price, stock FROM items WHERE id = ?";
        $stmtItem = $this->sqli->prepare($sqlItem);
        $stmtItem->bind_param("i", $item_id);
        $stmtItem->execute();
        $resultItem = $stmtItem->get_result();

        if ($resultItem->num_rows === 0) return "Barang tidak ditemukan.";
        $item = $resultItem->fetch_assoc();

        if ($item['stock'] < $quantity) return "Stok tidak mencukupi untuk jumlah yang dipesan.";

        $total_price = $item['price'] * $quantity;

        $sql  = "INSERT INTO orders (user_id, item_id, quantity, total_price, status) VALUES (?, ?, ?, ?, 'pending')";
        $stmt = $this->sqli->prepare($sql);
        $stmt->bind_param("iiid", $user_id, $item_id, $quantity, $total_price);

        return $stmt->execute() ? true : "Gagal membuat pesanan.";
    }

    public function getOrdersByUser($user_id) {
        $sql  = "SELECT o.*, i.item_name, i.image 
                 FROM orders o
                 JOIN items i ON o.item_id = i.id
                 WHERE o.user_id = ?
                 ORDER BY o.order_date DESC";
        $stmt = $this->sqli->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getAllOrders($statusFilter = null) {
        if ($statusFilter && in_array($statusFilter, ['pending', 'approved', 'rejected', 'completed'], true)) {
            $sql  = "SELECT o.*, u.username, i.item_name 
                     FROM orders o
                     JOIN users u ON o.user_id = u.id
                     JOIN items i ON o.item_id = i.id
                     WHERE o.status = ?
                     ORDER BY o.order_date DESC";
            $stmt = $this->sqli->prepare($sql);
            $stmt->bind_param("s", $statusFilter);
        } else {
            $sql  = "SELECT o.*, u.username, i.item_name 
                     FROM orders o
                     JOIN users u ON o.user_id = u.id
                     JOIN items i ON o.item_id = i.id
                     ORDER BY o.order_date DESC";
            $stmt = $this->sqli->prepare($sql);
        }
        $stmt->execute();
        return $stmt->get_result();
    }

    public function approveOrder($order_id) {
        $sqlOrder  = "SELECT * FROM orders WHERE id = ? AND status = 'pending'";
        $stmtOrder = $this->sqli->prepare($sqlOrder);
        $stmtOrder->bind_param("i", $order_id);
        $stmtOrder->execute();
        $order = $stmtOrder->get_result()->fetch_assoc();

        if (!$order) return "Pesanan tidak ditemukan atau sudah diproses sebelumnya.";

        $sqlItem  = "SELECT stock FROM items WHERE id = ?";
        $stmtItem = $this->sqli->prepare($sqlItem);
        $stmtItem->bind_param("i", $order['item_id']);
        $stmtItem->execute();
        $item = $stmtItem->get_result()->fetch_assoc();

        if (!$item || $item['stock'] < $order['quantity']) {
            return "Stok tidak lagi mencukupi untuk pesanan ini.";
        }

        $this->sqli->begin_transaction();
        try {
            $new_stock  = $item['stock'] - $order['quantity'];
            $sqlUpdate  = "UPDATE items SET stock = ? WHERE id = ?";
            $stmtUpdate = $this->sqli->prepare($sqlUpdate);
            $stmtUpdate->bind_param("ii", $new_stock, $order['item_id']);
            $stmtUpdate->execute();

            $sqlStatus  = "UPDATE orders SET status = 'approved' WHERE id = ?";
            $stmtStatus = $this->sqli->prepare($sqlStatus);
            $stmtStatus->bind_param("i", $order_id);
            $stmtStatus->execute();

            $this->sqli->commit();
            return true;
        } catch (Exception $e) {
            $this->sqli->rollback();
            return "Terjadi kesalahan sistem saat memproses pesanan.";
        }
    }

    public function rejectOrder($order_id) {
        $sql  = "UPDATE orders SET status = 'rejected' WHERE id = ? AND status = 'pending'";
        $stmt = $this->sqli->prepare($sql);
        $stmt->bind_param("i", $order_id);
        return $stmt->execute();
    }

    public function completeOrder($order_id) {
        // Selesaikan pesanan approved + catat ke transactions agar masuk
        // laporan (cetak_laporan.php / total_transaksi.php baca tabel itu).
        // Stok sudah dikurangi saat approve; di sini hanya finalisasi uang.
        $sqlOrder  = "SELECT * FROM orders WHERE id = ? AND status = 'approved'";
        $stmtOrder = $this->sqli->prepare($sqlOrder);
        $stmtOrder->bind_param("i", $order_id);
        $stmtOrder->execute();
        $order = $stmtOrder->get_result()->fetch_assoc();

        if (!$order) return false;

        $this->sqli->begin_transaction();
        try {
            $sqlTrx  = "INSERT INTO transactions (user_id, item_id, quantity, total_price) VALUES (?, ?, ?, ?)";
            $stmtTrx = $this->sqli->prepare($sqlTrx);
            $stmtTrx->bind_param("iiid", $order['user_id'], $order['item_id'], $order['quantity'], $order['total_price']);
            $stmtTrx->execute();

            $sql  = "UPDATE orders SET status = 'completed' WHERE id = ? AND status = 'approved'";
            $stmt = $this->sqli->prepare($sql);
            $stmt->bind_param("i", $order_id);
            $stmt->execute();

            $this->sqli->commit();
            return true;
        } catch (Exception $e) {
            $this->sqli->rollback();
            error_log("[Order] completeOrder gagal #$order_id: " . $e->getMessage());
            return false;
        }
    }
}
?>
