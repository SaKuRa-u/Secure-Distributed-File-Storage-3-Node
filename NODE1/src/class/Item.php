<?php
require_once("connection.php");
require_once(__DIR__ . "/SeaweedFS.php");

class Item {
    protected $sqli;

    const ALLOWED_MIME_TYPES = ['image/png'];
    const MAX_FILE_SIZE      = 2 * 1024 * 1024;

    public function __construct() {
        $this->sqli = Database::get();
    }

    public function getItems() {
        $sql  = "SELECT i.*, c.category_name 
                 FROM items i 
                 JOIN categories c ON i.category_id = c.id
                 ORDER BY i.id DESC";
        $stmt = $this->sqli->prepare($sql);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getItemById($id) {
        $sql  = "SELECT i.*, c.category_name
                 FROM items i
                 JOIN categories c ON i.category_id = c.id
                 WHERE i.id = ?";
        $stmt = $this->sqli->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Simpan barang baru. Gambar WAJIB: kembalikan pesan error (string) bila
    // file tidak ada/gagal, JANGAN diam-diam membuat barang tanpa gambar.
    // Return: true (sukses) atau string pesan error.
    public function insertItem($category_id, $item_name, $price, $stock, $fileData) {
        if (!$fileData || !isset($fileData['error'])) {
            return "Gambar produk wajib diunggah.";
        }
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            $map = [
                UPLOAD_ERR_INI_SIZE   => "Gambar melebihi batas server (max 2MB).",
                UPLOAD_ERR_FORM_SIZE  => "Gambar melebihi batas form.",
                UPLOAD_ERR_PARTIAL    => "Upload gambar terputus, coba lagi.",
                UPLOAD_ERR_NO_FILE    => "Tidak ada file gambar yang dipilih.",
                UPLOAD_ERR_NO_TMP_DIR => "Folder sementara server tidak tersedia.",
                UPLOAD_ERR_CANT_WRITE => "Server gagal menulis file gambar.",
                UPLOAD_ERR_EXTENSION  => "Upload dihentikan oleh ekstensi PHP.",
            ];
            error_log("[Item] upload gambar gagal, kode=" . (int)$fileData['error']);
            return $map[$fileData['error']] ?? ("Upload gambar gagal (kode " . (int)$fileData['error'] . ").");
        }

        $fileTmpPath = $fileData['tmp_name'];
        $fileSize    = $fileData['size'];

        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $fileTmpPath);
        finfo_close($finfo);

        if (!in_array($real_mime, self::ALLOWED_MIME_TYPES, true)) {
            return "File harus PNG yang valid (terdeteksi: " . $real_mime . ").";
        }
        if ($fileSize > self::MAX_FILE_SIZE) {
            return "Ukuran gambar terlalu besar (maksimal 2MB).";
        }

        {

            $newFileName = bin2hex(random_bytes(16)) . '.png';
            $objectKey   = 'images/products/' . $newFileName;

            // Upload ke SeaweedFS (penyimpanan utama) langsung dari tmp PHP.
            // Kalau filer tak terjangkau (mis. 1 node mati, placement tak
            // terpenuhi) -> SIMPAN LOKAL + catat basename (mode degraded).
            // File yatim ini dipungut ke SeaweedFS oleh heal-uploads.sh
            // (manual pasca-outage + cron tiap 5 menit). Tidak ada tulis
            // yang ditolak selama DB primary hidup.
            $okSeaweed = SeaweedFS::uploadTmp($fileTmpPath, $objectKey, 'image/png');
            if (!$okSeaweed) {
                error_log("[Item] SeaweedFS upload gagal untuk $objectKey, simpan lokal (heal menyusul).");
            }

            // Salinan lokal: selalu disimpan sebagai cache + fallback, dan
            // sebagai rumah sementara bila SeaweedFS sedang tak terjangkau.
            $uploadFileDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            $dest_path = $uploadFileDir . $newFileName;
            if (is_uploaded_file($fileTmpPath) && file_exists($fileTmpPath)) {
                if (!@move_uploaded_file($fileTmpPath, $dest_path)) {
                    @copy($fileTmpPath, $dest_path);
                }
                if (file_exists($dest_path)) chmod($dest_path, 0644);
            }

            // Sukses SeaweedFS -> object key; gagal -> basename lokal agar
            // view tetap tampil via fallback + sweeper bisa menemukan filenya.
            $imageNameForDB = $okSeaweed ? $objectKey : $newFileName;
        }

        // Kalau SeaweedFS gagal DAN salinan lokal juga gagal -> jangan insert
        // baris tanpa gambar (data yatim tak bisa diheal).
        if (!$okSeaweed && !file_exists($dest_path)) {
            return "Gagal menyimpan gambar (object storage & lokal tidak tersedia).";
        }

        $sql  = "INSERT INTO items (category_id, item_name, price, stock, image) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->sqli->prepare($sql);
        $stmt->bind_param("isdis", $category_id, $item_name, $price, $stock, $imageNameForDB);
        return $stmt->execute() ? true : "Gagal menyimpan barang ke database.";
    }

    // Hapus barang + gambarnya. DIJAGA: tolak bila baris dirujuk orders
    // (FK CASCADE akan ikut menghapus riwayat PO!) atau transactions
    // (FK menahan). Kembalikan true, atau string alasan bila ditolak/gagal.
    public function deleteItem($item_id) {
        $item_id = (int)$item_id;
        if ($item_id <= 0) return "ID barang tidak valid.";

        $stmt = $this->sqli->prepare("SELECT * FROM items WHERE id = ?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) return "Barang tidak ditemukan.";

        $stmt = $this->sqli->prepare("SELECT COUNT(*) AS c FROM orders WHERE item_id = ?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $nOrder = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        if ($nOrder > 0) {
            return "Tidak dapat dihapus: sudah ada $nOrder pesanan memakai barang ini (riwayat PO harus utuh). Kosongkan stok bila ingin menghentikan penjualan.";
        }

        $stmt = $this->sqli->prepare("SELECT COUNT(*) AS c FROM transactions WHERE item_id = ?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $nTrx = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        if ($nTrx > 0) {
            return "Tidak dapat dihapus: sudah ada $nTrx transaksi memakai barang ini (laporan harus utuh). Kosongkan stok bila ingin menghentikan penjualan.";
        }

        $image = $row['image'] ?? null;
        $stmt = $this->sqli->prepare("DELETE FROM items WHERE id = ?");
        $stmt->bind_param("i", $item_id);
        if (!$stmt->execute() || $stmt->affected_rows < 1) {
            return "Gagal menghapus barang.";
        }

        // Bersihkan gambar (best-effort, kegagalan tidak menggagalkan hapus DB).
        if (!empty($image)) {
            $key = SeaweedFS::normalizeKey($image, 'product');
            SeaweedFS::delete($key);
            $local = __DIR__ . '/../uploads/' . basename($image);
            if (is_file($local)) @unlink($local);
        }
        return true;
    }
}
?>
