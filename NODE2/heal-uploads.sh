#!/usr/bin/env bash
# heal-uploads.sh — pungut file lokal yatim ke SeaweedFS.
# Latar: saat 1 node mati, upload produk sukses via salinan lokal (mode
# degraded, placement 010 tak terpenuhi). File itu belum punya entry filer.
# Script ini mendaftarkannya (PUT) sehingga dapat 2-copy. Idempoten: hanya
# PUT bila filer mengembalikan non-200; aman dijalankan kapan pun, manual
# pasca-outage maupun cron tiap 5 menit.
#   Manual : bash ~/psp-project/heal-uploads.sh
#   Cron   : */5 * * * * bash ~/psp-project/heal-uploads.sh >> /var/log/weed-heal.log 2>&1
set -u
FILER="${SEAWEED_FILER:-http://localhost:8888}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC="$ROOT/src/uploads"
[ -d "$SRC" ] || exit 0

mime_of() {
    case "$(echo "$1" | tr '[:upper:]' '[:lower:]')" in
        *.png) echo "image/png" ;;
        *)     echo "image/jpeg" ;;
    esac
}

push_one() { # $1=path lokal $2=object key
    local code
    code=$(curl -s -o /dev/null --max-time 10 -w "%{http_code}" "$FILER/$2")
    if [ "$code" = "200" ]; then return 0; fi
    code=$(curl -s -o /dev/null --max-time 30 -w "%{http_code}" -X PUT \
        --data-binary "@$1" -H "Content-Type: $(mime_of "$1")" "$FILER/$2")
    echo "$2 -> $code"
    return 0
}

n=0
for f in "$SRC"/*.png "$SRC"/*.jpg "$SRC"/*.jpeg; do
    [ -f "$f" ] || continue
    push_one "$f" "images/products/$(basename "$f")"
    n=$((n + 1))
done
for f in "$SRC"/profile/*.png "$SRC"/profile/*.jpg "$SRC"/profile/*.jpeg; do
    [ -f "$f" ] || continue
    push_one "$f" "images/profile/$(basename "$f")"
    n=$((n + 1))
done
echo "heal selesai: $n file diperiksa ($(date -u +%FT%TZ))"
