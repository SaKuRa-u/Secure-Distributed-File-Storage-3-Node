# Secure Distributed File Storage — 3 Node

Self-hosted, distributed, and secure file-storage/e-commerce platform:
a public storefront (PHP) backed by a 2-node PostgreSQL cluster (Patroni +
etcd quorum) and SeaweedFS object storage, fronted on a hardened VPS edge
(HAProxy + Coraza WAF, NetBird overlay network, Authentik IAM with LLDAP).

## Layout

```text
VPS/      Edge di VPS: NetBird server, HAProxy 3.4 + Coraza WAF,
          Authentik (SSO/OIDC) + LLDAP, etcd voter (quorum witness).
NODE1/    Aplikasi toko + PostgreSQL/Patroni + SeaweedFS.
NODE2/    Aplikasi toko + PostgreSQL/Patroni + SeaweedFS.
```

- `VPS/haproxy/` — reverse proxy + load balancer (round-robin + sticky),
  WAF Coraza SPOA (CRS v4), TLS Let's Encrypt.
- `VPS/netbird/` — NetBird combined server (overlay antar mesin).
- `VPS/authentik/` — IAM: SSO OIDC, enrollment + grup peran
  (`Admin`, `psp-pegawai`, `shop-customer`), LDAP source ke LLDAP.
- `VPS/etcd/` — voter ketiga quorum etcd (tanpa data).
- `NODE1/`, `NODE2/` — aplikasi PHP (`src/`), Postgres/Patroni
  (`patroni-node*.yml`, `docker-compose.patroni.yml`), SeaweedFS,
  migrasi `db-init/`, config PHP `php-conf/`.

## Secrets policy

Repo ini BEBAS secret. Yang tidak ikut ter-commit (lihat `.gitignore`):

- `.env`, `*.pem/*.key/*.crt`, `*.sql` dump data, `uploads/`, `logs/`
- Nilai secret di compose/yaml diganti placeholder/kosong:
  `*_PASSWORD`, `OIDC_CLIENT_SECRET`, `AUTHENTIK_SCIM_TOKEN`.
  Isi via environment saat deploy, JANGAN commit.
- Password default dev (`psp_secret` dkk) + kredensial seed
  (`admin/admin123`…) hanya untuk lab — GANTI di produksi.

## Deploy singkat

1. Tiap folder punya `README.md` sendiri — mulai dari `VPS/README.md`
   (bila ada) lalu per folder.
2. Buat `.env` dari `.env.example`, isi secret, JANGAN commit.
3. Sertifikat via certbot di host → gabung ke `haproxy/certs/` (di-ignore).
4. `docker compose up -d` per folder; verifikasi ikut README masing-masing.

## Keamanan

TLS 1.2+, OIDC SSO + RBAC grup + isolasi NetBird (grup toko tidak bisa
masuk VPN), WAF enforcing + fail-open, audit log (`ACCESS_DENIED`,
`SSO_TAKEOVER_BLOCKED`), backup volume sebelum upgrade, quorum 2-dari-3.
