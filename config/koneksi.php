<?php
declare(strict_types=1);

$host = 'localhost';
$db   = 'db_nusakarya';
$user = 'root';
$pass = '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $mysqli = new mysqli($host, $user, $pass, $db);
    $mysqli->set_charset('utf8mb4');
    require_once __DIR__ . '/database.php';
    $pdo = new Database($mysqli);

    // Auto-migrate kolom baru jika belum ada di database lokal
    try {
        $colCheck = $mysqli->query("SHOW COLUMNS FROM lowongan LIKE 'gambar'");
        if ($colCheck && $colCheck->num_rows === 0) {
            $mysqli->query("ALTER TABLE lowongan ADD COLUMN gambar VARCHAR(255) NULL");
        }
        $colCheck2 = $mysqli->query("SHOW COLUMNS FROM pelatihan LIKE 'gambar'");
        if ($colCheck2 && $colCheck2->num_rows === 0) {
            $mysqli->query("ALTER TABLE pelatihan ADD COLUMN gambar VARCHAR(255) NULL");
        }
        $colCheck3 = $mysqli->query("SHOW COLUMNS FROM users LIKE 'foto'");
        if ($colCheck3 && $colCheck3->num_rows === 0) {
            $mysqli->query("ALTER TABLE users ADD COLUMN foto VARCHAR(255) NULL AFTER skills");
        }
    } catch (Throwable $ignored) {
        // Abaikan jika migrasi skema gagal di latar belakang
    }
} catch (mysqli_sql_exception $e) {
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    exit('Koneksi database gagal. Periksa konfigurasi di config/koneksi.php.');
}