# SSO Customer via Authentik (OIDC) — Panduan Manual

Keputusan: customer login **HANYA via SSO Authentik**. Login lokal (`login.php`)
khusus staf **admin/pegawai**; role `customer` ditolak di login lokal dengan
pesan "gunakan SSO". Alur belanja: `katalog.php` (publik) → `produk.php?id=`
(publik) → keranjang/beli-sekarang → redirect `sso-login.php?next=...` bila
belum SSO. Daftar akun via enrollment Authentik (`register.php` hanya info).

Tanpa composer: OIDC hand-rolled (`src/class/OIDC.php`, curl+openssl).

## 1. Authentik — OAuth2 Provider + Application

1. Buat **OAuth2/OIDC Provider** (Confidential), mis. slug `psp-shop`.
2. Client type: **Confidential**. Redirect URI (case-sensitive):
   `https://app.cloudferdi.web.id/oauth-callback.php`
3. Authorization flow default; magistral scope: `openid profile email`
   (+ entitlements bila perlu, sesuai kebutuhan aplikasi).
4. Buat **Application** memakai provider tersebut, catat **Client ID** dan
   **Client Secret**.
5. Isi ke environment service `web` (jangan commit secret):
   `AUTHENTIK_ISSUER=https://auth.<domain>/application/o/psp-shop/`,
   `OIDC_CLIENT_ID=...`, `OIDC_CLIENT_SECRET=...`,
   `APP_URL=https://app.cloudferdi.web.id`.
   Contoh: `OIDC_CLIENT_SECRET=... docker compose up -d` atau via file
   environment di host (bukan di repo).

## 2. Grup + Enrollment (isolasi dari NetBird)

1. Buat grup mis. `shop-customer` — grup khusus aplikasi shop.
2. Buat **Enrollment Flow**: tahap **User Write** → policy **Create users
   group** agar user baru otomatis masuk `shop-customer`.
3. Bagikan **link enrollment** ke calon customer (ganti placeholder
   `AUTHENTIK_ENROLL_URL` / link di `register.php`).
4. Sisi Authentik saja (tanpa perubahan NetBird di repo ini): grup
   `shop-customer` **tidak** diberi akses ke aplikasi NetBird.

## 3. Isolasi NetBird (dokumentasi — tanpa perubahan kode)

1. Di Authentik: **Applications → netbird** → batasi via **policy/group
   binding** HANYA ke grup yang berhak (mis. `netbird-allowed`).
2. Pastikan grup `shop-customer` **TIDAK** termasuk binding tersebut.
3. Catat: user SSO baru tetap butuh **approve di dashboard NetBird**
   sebelum bisa join network (tidak otomatis aktif).

## 4. Migrasi DB

- DB **baru** (volume kosong): `db-init/02-sso.sql` jalan otomatis
  (menambah `users.email`, `users.authentik_sub`, `password` nullable).
- DB **lama**: `db-init` TIDAK jalan ulang — apply manual via psql:

  ```sql
  ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(255) UNIQUE NULL;
  ALTER TABLE users ADD COLUMN IF NOT EXISTS authentik_sub VARCHAR(255) UNIQUE NULL;
  ALTER TABLE users ALTER COLUMN password DROP NOT NULL;
  ```

## 5. Tes end-to-end (berurutan)

1. `docker compose up -d --build`; buka `/katalog.php` (publik, tanpa login).
2. Buka `/produk.php?id=<id>`; klik **Masuk Keranjang** tanpa login →
   harus redirect `sso-login.php?next=/produk.php?id=<id>`.
3. Register + login di Authentik (enrollment flow) → callback harus kembali
   ke produk tujuan, sesi `role=customer, sso=true`.
4. Tambah item → `/keranjang.php` → ubah qty/hapus → **Checkout** →
   struk tampil, cart kosong.
5. Buka `/customer_orders.php` (riwayat), `sso-logout.php` (logout +
   end-session Authentik, kembali ke katalog).
6. Negatif: buka `/keranjang.php` tanpa login → redirect SSO;
   login lokal `login.php` dengan akun customer → ditolak ("gunakan SSO");
   `?next=https://evil.test` → fallback `/katalog.php`.
