-- ============================================================
-- 02-sso.sql — kolom SSO untuk login customer via Authentik (OIDC).
-- Jalan OTOMATIS hanya saat init DB baru (docker volume kosong).
-- Untuk DB LAMA: apply manual via psql (lihat README-SSO.md).
-- ============================================================

ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(255) UNIQUE NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS authentik_sub VARCHAR(255) UNIQUE NULL;

-- Akun SSO tidak punya password lokal
ALTER TABLE users ALTER COLUMN password DROP NOT NULL;
