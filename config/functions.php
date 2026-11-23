<?php
declare(strict_types=1);
require_once __DIR__ . "/app.php";

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
function rupiah($value): string
{
    return "Rp" . number_format((float) $value, 0, ",", ".");
}
function redirect(string $path): never
{
    header("Location: " . BASE_URL . $path);
    exit();
}
function dashboard_path(?array $user = null): string
{
    $user = $user ?? current_user();
    if (!$user) {
        return "/auth/login.php";
    }
    return match ($user["role"] ?? "") {
        "pekerja" => "/pekerja/dashboard.php",
        "penyedia" => "/penyedia/dashboard.php",
        "admin" => "/admin/dashboard.php",
        default => "/auth/login.php",
    };
}
function csrf_token(): string
{
    if (empty($_SESSION["_csrf"])) {
        $_SESSION["_csrf"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["_csrf"];
}
function verify_csrf(): void
{
    $token = $_POST["_csrf"] ?? "";
    if (!is_string($token) || !hash_equals($_SESSION["_csrf"] ?? "", $token)) {
        http_response_code(419);
        exit("Sesi formulir tidak valid. Silakan muat ulang halaman.");
    }
}
function current_user(): ?array
{
    return $_SESSION["user"] ?? null;
}
function require_login(?string $role = null): void
{
    $user = current_user();
    if (!$user) {
        redirect("/auth/login.php");
    }
    if ($role !== null && ($user["role"] ?? "") !== $role) {
        http_response_code(403);
        exit("Anda tidak memiliki akses ke halaman ini.");
    }
}
function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION["_flash"][$key] = $message;
        return null;
    }
    $value = $_SESSION["_flash"][$key] ?? null;
    unset($_SESSION["_flash"][$key]);
    return $value;
}

function ensure_profile_upload_dir(): string
{
    $dir = dirname(__DIR__) . "/uploads/profiles";
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException("Folder upload foto profil tidak dapat dibuat.");
    }
    return $dir;
}

function save_profile_photo(array $file): ?string
{
    if (($file["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file["error"] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Upload foto profil gagal.");
    }
    if (($file["size"] ?? 0) > 3 * 1024 * 1024) {
        throw new RuntimeException("Ukuran foto maksimal 3 MB.");
    }
    if (!is_uploaded_file($file["tmp_name"] ?? "")) {
        throw new RuntimeException("Berkas foto tidak valid.");
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file["tmp_name"]);
    $allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException("Format foto harus JPG, PNG, atau WebP.");
    }
    $info = @getimagesize($file["tmp_name"]);
    if (!$info || $info[0] > 6000 || $info[1] > 6000) {
        throw new RuntimeException("Dimensi foto tidak valid (maksimal 6000 x 6000 piksel).");
    }
    $ext = $allowed[$mime];
    $name = "avatar_" . bin2hex(random_bytes(16)) . "." . $ext;
    if (!move_uploaded_file($file["tmp_name"], ensure_profile_upload_dir() . "/" . $name)) {
        throw new RuntimeException("Foto profil tidak berhasil disimpan.");
    }
    return $name;
}

function delete_profile_photo(?string $name): void
{
    if (!$name || basename($name) !== $name) {
        return;
    }
    $path = dirname(__DIR__) . "/uploads/profiles/" . $name;
    if (is_file($path)) {
        @unlink($path);
    }
}

function user_avatar(?array $user): string
{
    if ($user && !empty($user["foto"])) {
        $path = dirname(__DIR__) . "/uploads/profiles/" . $user["foto"];
        if (is_file($path)) {
            return BASE_URL . "/uploads/profiles/" . e($user["foto"]);
        }
    }
    $role = $user["role"] ?? "pekerja";
    return match ($role) {
        "admin" => BASE_URL . "/assets/img/avatar-admin.svg",
        "penyedia" => BASE_URL . "/assets/img/avatar-penyedia.svg",
        "pekerja" => BASE_URL . "/assets/img/avatar-pekerja.svg",
        default => BASE_URL . "/assets/img/avatar-default.svg",
    };
}

function role_label(string $role): string
{
    return match ($role) {
        "admin" => "Administrator",
        "penyedia" => "Penyedia Usaha",
        "pekerja" => "Pekerja / Talenta",
        default => ucfirst($role),
    };
}
