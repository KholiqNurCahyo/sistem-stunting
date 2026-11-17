<?php
require_once __DIR__ . "/includes/auth.php";
require_login();
require_admin(); // Khusus Administrator
require_once __DIR__ . "/config/database.php";

$page_title = "Manajemen Pengguna";
$user = current_user();

$error = "";
$success = "";

// Proses Tambah User Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'tambah') {
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = trim($_POST['role'] ?? 'petugas');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($nama === '' || $username === '' || $password === '') {
        $error = "Nama, username, dan password wajib diisi.";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal harus 6 karakter.";
    } else {
        // Cek username sudah ada atau belum
        $stmt_c = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_c->bind_param("s", $username);
        $stmt_c->execute();
        if ($stmt_c->get_result()->num_rows > 0) {
            $error = "Username '{$username}' sudah terdaftar. Silakan pilih username lain.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $emailVal = $email !== '' ? $email : null;

            $stmt_i = $conn->prepare("INSERT INTO users (nama, username, email, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt_i->bind_param("sssss", $nama, $username, $emailVal, $hashed, $role);
            if ($stmt_i->execute()) {
                $success = "Pengguna baru berhasil didaftarkan.";
            } else {
                $error = "Gagal mendaftarkan pengguna: " . $stmt_i->error;
            }
        }
    }
}

// Proses Hapus User
if (isset($_GET['hapus'])) {
    $hapus_id = (int)$_GET['hapus'];
    if ($hapus_id === (int)$user['id']) {
        $error = "Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.";
    } else {
        $stmt_del = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt_del->bind_param("i", $hapus_id);
        if ($stmt_del->execute()) {
            $success = "Pengguna berhasil dihapus.";
        } else {
            $error = "Gagal menghapus pengguna: " . $stmt_del->error;
        }
    }
}

// Daftar Pengguna
$users_list = $conn->query("SELECT id, nama, username, email, role, created_at FROM users ORDER BY role ASC, nama ASC");

include __DIR__ . "/includes/header.php";
include __DIR__ . "/includes/sidebar.php";
?>

<main class="main-content">
    <?php include __DIR__ . "/includes/navbar.php"; ?>

    <div class="toolbar">
        <div>
            <h2>Manajemen Pengguna</h2>
            <p class="muted">Kelola akun administrator dan kader posyandu.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:16px;" role="alert">
            <?= ui_icon('alert-circle', '', 16) ?> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom:16px;" role="alert">
            <?= ui_icon('check-circle', '', 16) ?> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="content-grid" style="align-items:start; margin-top:0;">
        <!-- Tabel Pengguna -->
        <div class="data-table-container" style="margin-top:0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama Pengguna</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($u = $users_list->fetch_assoc()): 
                        $badge_role = 'badge-secondary';
                        if ($u['role'] === 'admin') $badge_role = 'badge-info';
                        elseif ($u['role'] === 'petugas') $badge_role = 'badge-success';
                        elseif ($u['role'] === 'orang_tua') $badge_role = 'badge-warning';
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($u['nama']) ?></strong>
                                <?php if ((int)$u['id'] === (int)$user['id']): ?>
                                    <span class="pill" style="font-size:9px; padding:2px 6px;">Anda</span>
                                <?php endif; ?>
                            </td>
                            <td><code>@<?= htmlspecialchars($u['username']) ?></code></td>
                            <td><span class="badge <?= $badge_role ?>"><?= ucfirst(str_replace('_', ' ', $u['role'])) ?></span></td>
                            <td><small><?= htmlspecialchars($u['email'] ?: '—') ?></small></td>
                            <td style="text-align:right;">
                                <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                                    <a href="users.php?hapus=<?= $u['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);" onclick="return confirm('Hapus akun pengguna ini?');">
                                        <?= ui_icon('trash', '', 12) ?> Hapus
                                    </a>
                                <?php else: ?>
                                    <span class="muted" style="font-size:11px;">Aktif</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Form Tambah User -->
        <div class="form-card" style="margin-top:0;">
            <h3 style="margin-top:0; font-size:15px; font-weight:700; color:var(--ink);">
                Tambah Pengguna Baru
            </h3>
            <p class="muted" style="font-size:12px; margin-bottom:16px;">Buat akun untuk kader posyandu atau admin.</p>

            <form method="POST">
                <input type="hidden" name="action" value="tambah">

                <div class="form-group" style="margin-bottom:12px;">
                    <label for="usr_nama">Nama Lengkap <span style="color:var(--danger);">*</span></label>
                    <input type="text" id="usr_nama" name="nama" class="form-control" required placeholder="Contoh: Siti Aminah (Kader RW 01)">
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label for="usr_username">Username Login <span style="color:var(--danger);">*</span></label>
                    <input type="text" id="usr_username" name="username" class="form-control" required placeholder="Contoh: kader_siti">
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label for="usr_role">Hak Akses (Role) <span style="color:var(--danger);">*</span></label>
                    <select id="usr_role" name="role" class="form-control" required>
                        <option value="petugas">Petugas / Kader Posyandu</option>
                        <option value="admin">Administrator Sistem</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label for="usr_email">Email (Opsional)</label>
                    <input type="email" id="usr_email" name="email" class="form-control" placeholder="nama@email.com">
                </div>

                <div class="form-group" style="margin-bottom:18px;">
                    <label for="usr_password">Password <span style="color:var(--danger);">*</span></label>
                    <input type="password" id="usr_password" name="password" class="form-control" required minlength="6" placeholder="Minimal 6 karakter">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; padding:10px;">
                    <?= ui_icon('plus', '', 14) ?> Daftarkan Pengguna
                </button>
            </form>
        </div>
    </div>

<?php include __DIR__ . "/includes/footer.php"; ?>
