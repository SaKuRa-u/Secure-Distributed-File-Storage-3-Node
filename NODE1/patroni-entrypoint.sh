#!/bin/sh
# Entrypoint Patroni: pastikan volume data milik postgres, lalu exec patroni.
# File config dipilih via ENV PATRONI_NODE=node1|node2 (pola sama dgn ETCD_NAME).
set -eu
if [ "$(id -u)" = "0" ]; then
    # Fresh named volume = root-owned + mode longgar -> postgres FATAL.
    # initdb (Node 2, primary) memperbaiki sendiri; pg_basebackup (replica)
    # TIDAK -> chmod eksplisit di sini agar replica tidak FATAL.
    mkdir -p /var/lib/postgresql/data/pgdata
    chown -R postgres:postgres /var/lib/postgresql/data
    chmod 0700 /var/lib/postgresql/data/pgdata
    exec gosu postgres "$0" "$@"
fi
CFG="/etc/patroni-${PATRONI_NODE:?isi node1 ATAU node2}.yml"
exec patroni "$CFG"
