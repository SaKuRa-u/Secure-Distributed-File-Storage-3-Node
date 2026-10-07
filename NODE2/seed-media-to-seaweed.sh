#!/usr/bin/env bash
# Seed media ke SeaweedFS filer (Node 2 Gabriel).
# Storage utama = SeaweedFS. Folder src/uploads/ HANYA fallback/cache
# (dipakai image-proxy.php kalau filer sedang down).
#
# Cara pakai (di server Node 2, via VSCode terminal):
#   cd ~/gabnode2/psp-project
#   bash seed-media-to-seaweed.sh
#   # atau ke filer lain: bash seed-media-to-seaweed.sh http://localhost:8888
#
# Prasyarat: file p*.jpg (foto unik per produk) + *.png sudah ada di
# src/uploads/ (ikut ter-upload saat timpa kode dari laptop).
set -u

FILER="${1:-${SEAWEED_FILER_INTERNAL:-http://localhost:8888}}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC="$ROOT/src/uploads"

if [ ! -d "$SRC" ]; then
  echo "ERROR: folder tidak ketemu: $SRC"
  exit 1
fi

ok=0
fail=0
failed_files=""

for f in "$SRC"/p*.jpg "$SRC"/*.png; do
  [ -f "$f" ] || continue
  name="$(basename "$f")"
  code="$(curl -s -o /dev/null -w '%{http_code}' -X PUT --data-binary "@${f}" "${FILER}/images/products/${name}")"
  if [ "${code#2}" != "$code" ]; then
    # code diawali '2' -> sukses (200/201)
    ok=$((ok + 1))
  else
    fail=$((fail + 1))
    failed_files="$failed_files $name($code)"
  fi
done

echo "Filer : $FILER"
echo "Sukses: $ok file"
echo "Gagal : $fail file"
[ "$fail" -gt 0 ] && echo "File gagal:$failed_files"

# Verifikasi sampel acak
echo "--- verifikasi ---"
for s in p107.jpg p116.jpg p144.jpg 1776934547_basreng.png; do
  code="$(curl -s -o /dev/null -w '%{http_code}' "${FILER}/images/products/${s}")"
  echo "$s -> HTTP $code"
done

echo "Selesai. Cek via browser:"
echo "  https://app.cloudferdi.web.id/image-proxy.php?key=images/products/p107.jpg"
