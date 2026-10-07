<?php
require_once("connection.php");
require_once(__DIR__ . "/SeaweedFS.php");

class User {
    protected $sqli;

    public function __construct() {
        $this->sqli = Database::get();
    }

    public function getUserById($id) {
        $sql  = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->sqli->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Update username & password
    public function updateProfile($id, $new_username, $new_password) {
        if (!empty($new_password)) {
            $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
            $sql  = "UPDATE users SET username = ?, password = ? WHERE id = ?";
            $stmt = $this->sqli->prepare($sql);
            $stmt->bind_param("ssi", $new_username, $hashed_pass, $id);
        } else {
            $sql  = "UPDATE users SET username = ? WHERE id = ?";
            $stmt = $this->sqli->prepare($sql);
            $stmt->bind_param("si", $new_username, $id);
        }

        if ($stmt->execute()) {
            $_SESSION['username'] = $new_username;
            return true;
        }
        return false;
    }

    // Update foto profil
    public function updateProfilePhoto($id, $photo_filename) {
        $sql  = "UPDATE users SET profile_photo = ? WHERE id = ?";
        $stmt = $this->sqli->prepare($sql);
        $stmt->bind_param("si", $photo_filename, $id);
        return $stmt->execute();
    }

    const ALLOWED_PHOTO_MIME = ['image/png', 'image/jpeg'];
    const MAX_PHOTO_SIZE     = 2 * 1024 * 1024;

    public function handlePhotoUpload($id, $fileData, $oldPhoto = null) {
        if (!$fileData || $fileData['error'] !== UPLOAD_ERR_OK) {
            return "Tidak ada file yang diupload atau terjadi error saat upload.";
        }

        $fileTmpPath = $fileData['tmp_name'];
        $fileSize    = $fileData['size'];

        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $fileTmpPath);
        finfo_close($finfo);

        if (!in_array($real_mime, self::ALLOWED_PHOTO_MIME, true)) {
            return "File harus berformat PNG atau JPEG (terdeteksi: " . htmlspecialchars($real_mime) . ").";
        }
        if ($fileSize > self::MAX_PHOTO_SIZE) {
            return "Ukuran foto maksimal 2MB.";
        }

        $ext = ($real_mime === 'image/png') ? 'png' : 'jpg';
        $newFileName = 'profile_' . bin2hex(random_bytes(16)) . '.' . $ext;
        $objectKey   = 'images/profile/' . $newFileName;

        // 1) Upload ke SeaweedFS (penyimpanan utama)
        $okSeaweed = SeaweedFS::uploadTmp($fileTmpPath, $objectKey, $real_mime);
        if (!$okSeaweed) {
            error_log("[User] SeaweedFS upload gagal untuk $objectKey, fallback ke lokal saja.");
        }

        // 2) Salinan lokal sebagai cache/fallback
        $uploadDir = __DIR__ . '/../uploads/profile/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $dest_path = $uploadDir . $newFileName;

        $movedLocal = false;
        if (is_uploaded_file($fileTmpPath) && file_exists($fileTmpPath)) {
            if (@move_uploaded_file($fileTmpPath, $dest_path)) {
                $movedLocal = true;
            } elseif (@copy($fileTmpPath, $dest_path)) {
                $movedLocal = true;
            }
            if ($movedLocal && file_exists($dest_path)) chmod($dest_path, 0644);
        }

        if (!$okSeaweed && !$movedLocal) {
            return "Gagal memindahkan file foto.";
        }

        $valueForDB = $okSeaweed ? $objectKey : $newFileName;

        if (!$this->updateProfilePhoto($id, $valueForDB)) {
            if ($movedLocal) @unlink($dest_path);
            if ($okSeaweed) SeaweedFS::delete($objectKey);
            return "Gagal menyimpan data foto ke database.";
        }

        if (!empty($oldPhoto)) {
            // $oldPhoto bisa nama file lama ("profile_abc.jpg") atau key penuh
            $oldKey = SeaweedFS::normalizeKey($oldPhoto, 'profile');
            SeaweedFS::delete($oldKey);
            // Hapus juga file lokal lama
            $oldLocal = $uploadDir . basename($oldPhoto);
            if (is_file($oldLocal)) {
                @unlink($oldLocal);
            }
        }

        return true;
    }

    public function getAllUsers() {
        $sql  = "SELECT id, username, role, profile_photo FROM users ORDER BY id ASC";
        $stmt = $this->sqli->prepare($sql);
        $stmt->execute();
        return $stmt->get_result();
    }
}
?>
