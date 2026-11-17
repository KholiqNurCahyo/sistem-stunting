<?php
session_start();
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/icons.php";

// Jika sudah login sebagai petugas/admin, langsung ke dashboard
if (!empty($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Login khusus petugas / kader / admin menggunakan username & password
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Username dan password wajib diisi.";
    } else {
        $stmt = $conn->prepare("SELECT id, nama, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($user = $res->fetch_assoc()) {
            if ($user['role'] === 'orang_tua') {
                $error = "Akun orang tua tidak dapat digunakan untuk login. Orang tua dapat langsung memantau data balita tanpa perlu login.";
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'nama' => $user['nama'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ];
                header("Location: index.php");
                exit;
            } else {
                $error = "Password yang Anda masukkan salah.";
            }
        } else {
            $error = "Akun dengan username tersebut tidak ditemukan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Sistem - SiTumbuh Posyandu</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">

<main class="login-card">
    <header class="login-brand">
        <span class="brand-mark" aria-hidden="true"><?= ui_icon('shield', '', 20) ?></span>
        <div>
            <strong style="font-family:'Bricolage Grotesque', sans-serif; font-size:18px; font-weight:700; color:var(--ink); display:block;">SiTumbuh</strong>
            <small style="color:var(--muted); font-size:11px;">Portal Layanan Posyandu &amp; Stunting</small>
        </div>
    </header>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:18px;" role="alert">
            <?= ui_icon('alert-circle', '', 16) ?> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <section>
        <h1 style="font-size:18px; font-weight:700; margin:0 0 6px;">Masuk Sebagai Petugas</h1>
        <p class="muted" style="margin-bottom:18px; line-height:1.5;">Khusus kader posyandu, bidan desa, atau administrator sistem.</p>

        <form method="POST">
            <div class="form-group" style="margin-bottom:14px;">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" required autofocus placeholder="Masukkan username petugas / admin" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="Masukkan password">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:10px;">
                <?= ui_icon('shield', '', 15) ?> Masuk ke Dashboard Posyandu
            </button>
        </form>

        <div style="margin-top:20px; padding:12px 14px; background:var(--canvas-subtle, #f8fafc); border-radius:8px; border:1px solid var(--border-color, #e2e8f0); text-align:center; font-size:13px;">
            <span style="color:var(--muted); display:block; margin-bottom:8px;">Orang tua balita tidak perlu login untuk melihat data penimbangan:</span>
            <a href="warga.php" class="btn btn-outline btn-sm" style="display:inline-flex; align-items:center; gap:6px;">
                <?= ui_icon('search', '', 14) ?> Cek Hasil Tumbuh Kembang Balita
            </a>
        </div>
    </section>

    <footer class="app-footer" style="padding-top:16px; margin-top:24px; font-size:11px; color:var(--muted); text-align:center; display:block;">
        SiTumbuh &bull; Posyandu Digital
    </footer>
</main>

</body>
</html>
