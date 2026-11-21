<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function rupiah($value): string {
    return 'Rp' . number_format((float)$value, 0, ',', '.');
}
function redirect(string $path): never {
    header('Location: ' . BASE_URL . $path);
    exit;
}
function dashboard_path(?array $user = null): string {
    $user = $user ?? current_user();
    if (!$user) return '/auth/login.php';
    return match ($user['role'] ?? '') {
        'pekerja' => '/pekerja/dashboard.php',
        'penyedia' => '/penyedia/dashboard.php',
        'admin' => '/admin/dashboard.php',
        default => '/auth/login.php',
    };
}
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function verify_csrf(): void {
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Sesi formulir tidak valid. Silakan muat ulang halaman.');
    }
}
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}
function require_login(?string $role = null): void {
    $user = current_user();
    if (!$user) redirect('/auth/login.php');
    if ($role !== null && ($user['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Anda tidak memiliki akses ke halaman ini.');
    }
}
function flash(string $key, ?string $message = null): ?string {
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}
