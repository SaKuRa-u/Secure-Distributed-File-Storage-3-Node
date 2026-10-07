<?php
// image-proxy.php — Proxy gambar SeaweedFS via App (same-origin HTTPS).
//
// Kenapa file ini ada (arsitektur VPS + Node1 + Node2):
//   - Browser buka https://app.cloudferdi.web.id (HTTPS publik via HAProxy di VPS).
//   - Filer SeaweedFS jalan di http://filer:8888 (internal Docker) /
//     http://192.168.x.x:8888 (NetBird privat antar node).
//   - Browser dari internet TIDAK BISA akses IP privat + HTTP (Mixed Content
//     + Private Network Access -> ERR_CONNECTION_REFUSED / blocked).
//   - Jadi browser JANGAN dikasih URL filer langsung. Kasih URL sama-origin:
//       image-proxy.php?key=images/products/xxx.png
//     lalu PHP di server yang fetch ke filer internal (http://filer:8888)
//     dan stream balik ke browser sebagai HTTPS.
//
// Penyimpanan utama tetap SeaweedFS (sesuai arsitektur). Salinan lokal di
// uploads/ hanya cache/fallback kalau filer sedang down / object belum
// tereplikasi antar Node1 <-> Node2 (SeaweedFS Native Replication).
//
// Keamanan (SSRF): key di-whitelist ketat, host filer fixed dari ENV
// SEAWEED_FILER_INTERNAL + SEAWEED_FILER_PEERS, user tidak bisa kontrol host/skema.

require_once(__DIR__ . "/class/SeaweedFS.php");

$key = $_GET['key'] ?? '';
$key = trim($key);
// tolak URL penuh, hanya object key relatif
if (preg_match('#^https?://#i', $key)) {
    http_response_code(400);
    exit("Bad key");
}
// normalisasi: buang leading slash
$key = ltrim($key, '/');

// whitelist: hanya images/products/ dan images/profile/, nama file aman
if (!preg_match('#^images/(products|profile)/[A-Za-z0-9._-]+\.(png|jpg|jpeg)$#', $key)) {
    http_response_code(404);
    exit("Not found");
}

$tried = [];
$fetch = function ($base, $key, $ct, $ctConn) {
    $ch = curl_init($base . '/' . $key);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $ct);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $ctConn);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return [$code, $body, $ctype];
};

// 1) filer lokal dulu (tercepat).
list($code, $body, $ctype) = $fetch(SeaweedFS::filerInternal(), $key, 10, 5);

// 2) read-through peer (namespace per filer: entry hanya di node penulis).
//    Lewati URL yang sama dengan lokal agar tidak dobel.
if ($code !== 200 || $body === false) {
    $local = SeaweedFS::filerInternal();
    foreach (SeaweedFS::filerPeers() as $peer) {
        if ($peer === $local) continue;
        list($code, $body, $ctype) = $fetch($peer, $key, 8, 3);
        if ($code === 200 && $body !== false) break;
    }
}

if ($code === 200 && $body !== false) {
    $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
    $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
    // pakai content-type dari filer kalau masuk akal, selain itu pakai tebakan
    if (is_string($ctype) && preg_match('#^image/(png|jpeg|jpg)#i', $ctype)) {
        $mime = $ctype;
    }
    header("Content-Type: " . $mime);
    header("Content-Length: " . strlen($body));
    header("Cache-Control: public, max-age=86400");
    header("X-Content-Type-Options: nosniff");
    echo $body;
    exit();
}

// Fallback: salinan lokal uploads/<basename> (cache migrasi / filer down)
$local = __DIR__ . '/uploads/' . basename($key);
if (is_file($local)) {
    $ext = strtolower(pathinfo($local, PATHINFO_EXTENSION));
    $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
    header("Content-Type: " . $mime);
    header("Content-Length: " . filesize($local));
    header("Cache-Control: public, max-age=86400");
    header("X-Content-Type-Options: nosniff");
    readfile($local);
    exit();
}

http_response_code(404);
header("Content-Type: text/plain");
echo "Image not found";
