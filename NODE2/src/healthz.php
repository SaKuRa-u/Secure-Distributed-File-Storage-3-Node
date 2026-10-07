<?php
// healthz.php — gerbang primary untuk HAProxy httpchk (Fase D) + cek manual.
// 200 HANYA bila: konek DB lokal OK dan node ini PRIMARY (pg_is_in_recovery()=false).
// Replica / DB down -> 503 -> HAProxy (nanti) otomatis menjauhi node ini.
// Tanpa session/login/CSRF: endpoint infrastruktur, tanpa data sensitif.
header('Content-Type: text/plain; charset=utf-8');
$host = getenv('DB_HOST') ?: 'db';
$user = getenv('DB_USER') ?: 'psp';
$pass = getenv('DB_PASSWORD') ?: 'psp_secret';
$db   = getenv('DB_NAME') ?: 'psp_project';
$c = @pg_connect("host={$host} port=5432 dbname={$db} user={$user} password={$pass} connect_timeout=2");
if (!$c) { http_response_code(503); echo "db-down"; exit; }
$r = @pg_query($c, "SELECT pg_is_in_recovery()");
if (!$r) { http_response_code(503); echo "db-error"; exit; }
$row = pg_fetch_row($r);
if ($row && $row[0] === 'f') { http_response_code(200); echo "primary"; }
else { http_response_code(503); echo "replica"; }
