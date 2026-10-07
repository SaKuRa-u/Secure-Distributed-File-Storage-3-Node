<?php
// Koneksi database PostgreSQL (dulu MySQL).
// Nilai default bisa dioverride via environment (di-set di docker-compose.yml).
define("SERVER_NAME", getenv('DB_HOST') ?: "db");
define("DB_PORT", getenv('DB_PORT') ?: "5432");
define("USER_NAME", getenv('DB_USER') ?: "psp");
define("PASSWORD", getenv('DB_PASSWORD') ?: "psp_secret");
define("DB_NAME", getenv('DB_NAME') ?: "psp_project");

require_once(__DIR__ . "/class/Database.php");
