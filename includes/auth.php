<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void {
    if (empty($_SESSION['user'])) {
        header("Location: login.php");
        exit;
    }
}

function current_user(): array {
    $u = $_SESSION['user'] ?? [];
    if (!empty($u)) {
        if (!isset($u['nama']) && isset($u['name'])) {
            $u['nama'] = $u['name'];
        }
        if (!isset($u['name']) && isset($u['nama'])) {
            $u['name'] = $u['nama'];
        }
    }
    return $u;
}

function require_role(array $roles): void {
    require_login();
    if (!in_array(current_user()['role'] ?? '', $roles, true)) {
        http_response_code(403);
        exit("Akses ditolak. Anda tidak memiliki izin untuk membuka halaman ini.");
    }
}

function require_admin(): void {
    require_role(['admin']);
}

function require_admin_or_petugas(): void {
    require_role(['admin', 'petugas']);
}

function is_warga_logged_in(): bool {
    return !empty($_SESSION['warga']);
}

function current_warga(): array {
    return $_SESSION['warga'] ?? [];
}

function require_warga_or_user(): void {
    // Halaman warga bersifat publik untuk orang tua/warga tanpa perlu login
    return;
}
