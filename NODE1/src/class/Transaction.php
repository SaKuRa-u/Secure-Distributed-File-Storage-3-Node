<?php
require_once("connection.php");

class Transaction {
    protected $sqli;

    public function __construct() {
        $this->sqli = Database::get();
    }

    public function getTransactionHistory() {
        $sql  = "SELECT t.id, u.username, i.item_name, t.quantity, t.total_price, t.transaction_date 
                 FROM transactions t
                 JOIN users u ON t.user_id = u.id
                 JOIN items i ON t.item_id = i.id
                 ORDER BY t.transaction_date DESC";
        $stmt = $this->sqli->prepare($sql);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getTotalRevenue() {
        $sql = "SELECT SUM(total_price) as grand_total FROM transactions";
        $res = $this->sqli->query($sql);
        $row = $res->fetch_assoc();
        return $row['grand_total'] ?? 0;
    }

    // Riwayat transaksi milik 1 customer (beli langsung + PO selesai).
    public function getHistoryByUser($user_id) {
        $sql  = "SELECT t.id, i.item_name, i.image, t.quantity, t.total_price, t.transaction_date
                 FROM transactions t
                 JOIN items i ON t.item_id = i.id
                 WHERE t.user_id = ?
                 ORDER BY t.transaction_date DESC";
        $stmt = $this->sqli->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function buyItem($user_id, $item_id, $quantity) {
        // Cek stok
        $sqlItem  = "SELECT price, stock FROM items WHERE id = ?";
        $stmtItem = $this->sqli->prepare($sqlItem);
        $stmtItem->bind_param("i", $item_id);
        $stmtItem->execute();
        $resultItem = $stmtItem->get_result();

        if ($resultItem->num_rows === 0) return "Barang tidak ditemukan.";
        $item = $resultItem->fetch_assoc();
        if ($item['stock'] < $quantity) return "Stok tidak mencukupi untuk jumlah tersebut.";

        $total_price = $item['price'] * $quantity;

        // Insert transaksi
        $sqlInsert  = "INSERT INTO transactions (user_id, item_id, quantity, total_price) VALUES (?, ?, ?, ?)";
        $stmtInsert = $this->sqli->prepare($sqlInsert);
        $stmtInsert->bind_param("iiid", $user_id, $item_id, $quantity, $total_price);

        if ($stmtInsert->execute()) {
            // Kurangi stok
            $new_stock  = $item['stock'] - $quantity;
            $sqlUpdate  = "UPDATE items SET stock = ? WHERE id = ?";
            $stmtUpdate = $this->sqli->prepare($sqlUpdate);
            $stmtUpdate->bind_param("ii", $new_stock, $item_id);
            $stmtUpdate->execute();
            return true;
        }

        return "Terjadi kesalahan pada sistem database.";
    }
}
?>
