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
} catch (mysqli_sql_exception $e) {
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    exit('Koneksi database gagal. Periksa konfigurasi di config/koneksi.php.');
}