<?php
// Helper SeaweedFS via Filer HTTP API.
// Konsep: SeaweedFS Filer jalan di http://<host>:8888
//   Upload : PUT {FILER}/images/products/<nama>.png  (body = binary file)
//   Baca   : GET  {FILER}/images/products/<nama>.png
//   Hapus  : DELETE {FILER}/images/products/<nama>.png
//
// ENV yang dipakai (di-set di docker-compose / VM):
//   SEAWEED_FILER_INTERNAL : URL filer dari DALAM container web, cth http://filer:8888
//                            (dipakai untuk upload + image-proxy.php).
//   SEAWEED_FILER_PUBLIC   : URL filer untuk BROWSER LANGSUNG, cth http://<IP-Node2>:8888
//                            (HANYA untuk akses lokal/NetBird, JANGAN dipakai untuk
//                            situs publik https://app.cloudferdi.web.id karena browser
//                            internet tidak bisa menjangkau IP privat + HTTP.
//                            Untuk publik, browser memakai image-proxy.php same-origin.
//                            Nanti kalau VPS/HAProxy sudah expose filer via HTTPS,
//                            mis. https://files.cloudferdi.web.id, isi PUBLIC dengan
//                            domain itu dan ubah publicUrl() ke mode direct.)
//
// Strategi DB (backward compatible):
//   - Data LAMA : kolom `image` / `profile_photo` hanya nama file, cth "abc123.png"
//                 -> otomatis dipetakan ke "images/products/abc123.png"
//                    atau "images/profile/abc123.png"
//   - Data BARU : disimpan object key penuh, cth "images/products/abc123.png"
//   - Kalau value sudah http(s):// -> dipakai apa adanya (untuk S3 gateway / CDN).

class SeaweedFS {

    public static function filerInternal() {
        $v = getenv('SEAWEED_FILER_INTERNAL');
        if (!$v && isset($_ENV['SEAWEED_FILER_INTERNAL'])) $v = $_ENV['SEAWEED_FILER_INTERNAL'];
        if (!$v) $v = 'http://filer:8888';
        return rtrim($v, '/');
    }

    public static function filerPublic() {
        $v = getenv('SEAWEED_FILER_PUBLIC');
        if (!$v && isset($_ENV['SEAWEED_FILER_PUBLIC'])) $v = $_ENV['SEAWEED_FILER_PUBLIC'];
        if (!$v) $v = self::filerInternal(); // fallback: sama (dev lokal)
        return rtrim($v, '/');
    }

    // Daftar filer peer untuk READ-THROUGH image-proxy (sama di semua node,
    // cth "http://100.101.136.77:8888,http://100.101.133.237:8888").
    // Namespace filer per node (entry hanya di node penulis); proxy mencoba
    // satu per satu hingga 200. Host dari ENV admin, bukan input user (aman SSRF).
    public static function filerPeers() {
        $v = getenv('SEAWEED_FILER_PEERS');
        if (!$v && isset($_ENV['SEAWEED_FILER_PEERS'])) $v = $_ENV['SEAWEED_FILER_PEERS'];
        $out = [];
        foreach (explode(',', (string)($v ?? '')) as $u) {
            $u = rtrim(trim($u), '/');
            if (preg_match('#^https?://[^/]+$#i', $u)) $out[] = $u;
        }
        return $out;
    }

    public static function isConfigured() {
        // Dipakai untuk fallback: kalau filer tidak bisa dihubungi, pakai lokal.
        return true; // selalu coba, gagal = fallback otomatis
    }

    // Upload file lokal ($localPath) ke object key, cth "images/products/xxx.png"
    // Return true jika sukses, false jika gagal.
    public static function upload($localPath, $objectKey, $mime = 'application/octet-stream') {
        $url = self::filerInternal() . '/' . ltrim($objectKey, '/');
        $fp  = fopen($localPath, 'r');
        if (!$fp) return false;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localPath));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: ' . $mime]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($err) { error_log("[SeaweedFS] upload cURL error: $err"); return false; }
        if ($code < 200 || $code >= 300) { error_log("[SeaweedFS] upload HTTP $code: $body (url=$url)"); return false; }
        return true;
    }

    // Upload langsung dari $_FILES tmp (tanpa pindah dulu)
    public static function uploadTmp($tmpPath, $objectKey, $mime) {
        return self::upload($tmpPath, $objectKey, $mime);
    }

    public static function delete($objectKey) {
        if (empty($objectKey)) return false;
        // Jangan hapus kalau value-nya URL penuh ke tempat lain / placeholder
        if (preg_match('#^https?://#i', $objectKey)) return false;
        $url = self::filerInternal() . '/' . ltrim($objectKey, '/');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($code >= 200 && $code < 300);
    }

    // URL publik untuk <img src="...">
    // MODE PROXY (arsitektur VPS + Node1/Node2): kembalikan URL same-origin
    //   image-proxy.php?key=images/products/xxx.png
    // agar browser akses via https://app.cloudferdi.web.id (HTTPS publik),
    // bukan via http://192.168.x.x:8888 (IP privat, diblokir Mixed Content +
    // Private Network Access). Penyimpanan utama tetap SeaweedFS — proxy hanya
    // meneruskan dari filer internal (http://filer:8888).
    public static function publicUrl($objectKey) {
        if (preg_match('#^https?://#i', $objectKey)) return $objectKey;
        return 'image-proxy.php?key=' . urlencode(ltrim($objectKey, '/'));
    }

    // Normalisasi isi DB jadi object key penuh.
    // $type: 'product' | 'profile'
    public static function normalizeKey($dbValue, $type = 'product') {
        $dbValue = trim((string)$dbValue);
        if ($dbValue === '') return '';
        if (preg_match('#^https?://#i', $dbValue)) return $dbValue; // URL penuh
        if (strpos($dbValue, '/') !== false) return ltrim($dbValue, '/'); // sudah key penuh
        $prefix = ($type === 'profile') ? 'images/profile/' : 'images/products/';
        return $prefix . basename($dbValue);
    }

    // Resolver untuk view: kembalikan URL SeaweedFS, tapi kalau $localFallback
    // ada file-nya dan filer tidak reachable, pakai lokal.
    // $dbValue  : isi kolom DB
    // $type     : 'product' | 'profile'
    // $localFallback : path relatif web, cth "uploads/abc.png" (boleh kosong)
    public static function resolveUrl($dbValue, $type = 'product', $localFallback = '') {
        if (empty($dbValue)) return $localFallback;
        $key = self::normalizeKey($dbValue, $type);
        if (preg_match('#^https?://#i', $key)) return $key;
        return self::publicUrl($key);
    }

    // Cek cepat apakah sebuah object ada di filer (dipakai saat migrasi/debug).
    public static function exists($objectKey) {
        $url = self::filerInternal() . '/' . ltrim($objectKey, '/');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($code === 200);
    }
}
