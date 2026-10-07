# A10:2021 — Server-Side Request Forgery (SSRF)

## Status: N/A (Tidak Berlaku) — dengan justifikasi

## Apa itu SSRF?

Berdasarkan materi Week 1 (Introduction to OWASP), SSRF adalah celah keamanan
yang terjadi ketika attacker memanipulasi sebuah web application atau API
agar membuat request ke internal resource, yang berpotensi menyebabkan akses
tidak sah, kebocoran data, kompromi sistem, hingga remote code execution.

## Mengapa SONY Shop tidak rentan terhadap SSRF?

SSRF terjadi ketika aplikasi menerima **URL atau alamat dari input user**,
lalu server membuat HTTP request ke URL tersebut atas nama aplikasi. Setelah
diaudit menyeluruh terhadap seluruh fitur SONY Shop, **tidak ditemukan satupun
fitur yang melakukan hal ini**:

| Fitur | Apakah fetch URL dari input user? |
|---|---|
| Login / Register | Tidak — hanya query ke database lokal |
| Upload gambar barang | Tidak — file diupload langsung via `$_FILES`, bukan diambil dari URL |
| Tambah kategori/barang | Tidak — input berupa text/angka, bukan URL |
| Pembelian barang | Tidak — hanya update tabel `transactions` dan `items` |
| Update profil | Tidak — hanya update tabel `users` |
| Laporan transaksi | Tidak — hanya query database lokal |

Tidak ada fitur seperti "import gambar dari URL", "webhook URL", "preview
link eksternal", "fetch avatar dari URL pihak ketiga", atau API integration
ke layanan eksternal yang menerima URL dinamis dari user.

## Rekomendasi Jika Fitur Berisiko Ditambahkan di Masa Depan

Jika suatu saat SONY Shop menambahkan fitur yang melibatkan pengambilan
resource dari URL eksternal (misalnya: import gambar produk dari link,
integrasi payment gateway dengan callback URL, atau webhook notification),
maka mitigasi berikut WAJIB diterapkan:

1. **Whitelist domain** — hanya izinkan request ke domain yang sudah
   ditentukan sebelumnya, tolak domain lain secara default (fail closed,
   sesuai prinsip di Week 11 slide #15).
2. **Blokir akses ke IP internal/private** — tolak request ke
   `127.0.0.1`, `169.254.169.254` (metadata cloud), `10.0.0.0/8`,
   `172.16.0.0/12`, `192.168.0.0/16`.
3. **Validasi skema URL** — hanya izinkan `https://`, tolak `file://`,
   `gopher://`, `ftp://`, dll.
4. **Gunakan timeout pendek** dan batasi jumlah redirect yang diikuti.
5. **Jangan kirim balik response mentah** dari server tujuan ke client —
   proses dan sanitasi dulu.

## Kesimpulan

Karena tidak ada fitur di SONY Shop yang melakukan server-side fetch
terhadap URL yang berasal dari input pengguna, kategori A10:2021 SSRF
dinyatakan **N/A** untuk versi aplikasi ini. Dokumentasi ini akan
ditinjau ulang apabila ada penambahan fitur yang relevan di masa depan.
