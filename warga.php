<?php
require_once __DIR__ . "/includes/auth.php";
// Warga / orang tua dapat mengakses halaman ini secara publik tanpa perlu login
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/antropometri.php";
require_once __DIR__ . "/includes/icons.php";

$page_title = "Informasi Tumbuh Kembang Balita";
$user = current_user();
$warga = current_warga();

// Ambil kata kunci pencarian (default ke nama anak saat login)
$cari = trim($_GET['cari'] ?? ($_GET['q'] ?? ($warga['nama'] ?? '')));

$anak_terpilih = null;
$hasil_pencarian = [];

if ($cari !== '') {
    $stmt = $conn->prepare(
        "SELECT a.* 
         FROM anak a 
         WHERE LOWER(a.nama) LIKE LOWER(?) OR a.nik = ? 
         ORDER BY (CASE WHEN LOWER(a.nama) = LOWER(?) THEN 1 ELSE 2 END), a.nama ASC 
         LIMIT 5"
    );
    $likeQ = "%{$cari}%";
    $exactQ = $cari;
    $stmt->bind_param("sss", $likeQ, $cari, $exactQ);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $hasil_pencarian[] = $row;
    }

    if (!empty($hasil_pencarian)) {
        // Pilih hasil yang paling cocok (pertama) atau yang dipilih via ?id=
        $pilih_id = (int)($_GET['id'] ?? 0);
        if ($pilih_id > 0) {
            foreach ($hasil_pencarian as $h) {
                if ((int)$h['id'] === $pilih_id) {
                    $anak_terpilih = $h;
                    break;
                }
            }
        }
        if (!$anak_terpilih) {
            $anak_terpilih = $hasil_pencarian[0];
        }
    }
}

// Jika ada anak terpilih, ambil data pemeriksaan terakhir & riwayatnya
$pemeriksaan_terakhir = null;
$riwayat_pemeriksaan = [];

if ($anak_terpilih) {
    $stmt_p = $conn->prepare(
        "SELECT p.*, u.nama AS nama_petugas 
         FROM pemeriksaan p 
         LEFT JOIN users u ON p.petugas_id = u.id 
         WHERE p.anak_id = ? 
         ORDER BY p.tanggal_periksa DESC, p.id DESC"
    );
    $stmt_p->bind_param("i", $anak_terpilih['id']);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();

    while ($p = $res_p->fetch_assoc()) {
        $riwayat_pemeriksaan[] = $p;
    }
    if (!empty($riwayat_pemeriksaan)) {
        $pemeriksaan_terakhir = $riwayat_pemeriksaan[0];
    }
}

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Tumbuh Kembang Balita - SiTumbuh</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<main class="warga-container">
    <!-- Topbar Warga -->
    <header class="warga-topbar">
        <div style="display:flex; align-items:center; gap:12px;">
            <span class="brand-mark" style="width:36px; height:36px;" aria-hidden="true"><?= ui_icon('shield', '', 18) ?></span>
            <div>
                <strong style="font-family:'Bricolage Grotesque', sans-serif; font-size:17px; font-weight:700; color:var(--ink); display:block;">SiTumbuh Warga</strong>
                <small style="color:var(--muted); font-size:11px;">Hasil Pemeriksaan Posyandu</small>
            </div>
        </div>
        
        <div style="display:flex; align-items:center; gap:10px;">
            <?php if (!empty($user)): ?>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <?= ui_icon('dashboard', '', 14) ?> Dashboard Petugas
                </a>
                <a href="logout.php" class="btn btn-outline btn-sm">
                    <?= ui_icon('logout', '', 14) ?> Keluar
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline btn-sm">
                    <?= ui_icon('shield', '', 14) ?> Login Petugas
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Pencarian Cepat Balita (To the point di bagian atas) -->
    <section class="warga-search-card" style="margin-bottom:20px;">
        <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <label for="warga_cari_input" style="position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); border:0;">Nama Balita</label>
            <input type="text" id="warga_cari_input" name="cari" class="form-control" style="flex:1; min-width:240px;" placeholder="Ketik nama balita (misal: Muhammad Fathan / Kenzo)..." value="<?= htmlspecialchars($cari) ?>" required>
            <button type="submit" class="btn btn-primary">
                <?= ui_icon('search', '', 14) ?> Cari
            </button>
            <?php if (!empty($warga['nama']) && $cari !== $warga['nama']): ?>
                <a href="warga.php?cari=<?= urlencode($warga['nama']) ?>" class="btn btn-secondary">
                    Balita Saya (<?= htmlspecialchars($warga['nama']) ?>)
                </a>
            <?php endif; ?>
        </form>

        <?php if (count($hasil_pencarian) > 1): ?>
            <div style="margin-top:12px; padding-top:10px; border-top:1px solid var(--line-light); font-size:12px; display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                <span class="muted">Pilih balita:</span>
                <?php foreach ($hasil_pencarian as $h): ?>
                    <a href="warga.php?cari=<?= urlencode($cari) ?>&amp;id=<?= $h['id'] ?>" class="btn <?= ($anak_terpilih && (int)$anak_terpilih['id'] === (int)$h['id']) ? 'btn-primary' : 'btn-outline' ?> btn-sm">
                        <?= htmlspecialchars($h['nama']) ?> (Ibu: <?= htmlspecialchars($h['nama_ibu'] ?: '—') ?>)
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Hasil Pemeriksaan Balita Terpilih -->
    <?php if ($anak_terpilih): 
        $usia_anak = format_umur($anak_terpilih['tanggal_lahir'], $today);
    ?>
        <article class="result-card-warga">
            <!-- Header Identitas -->
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; border-bottom:1px solid var(--line-light); padding-bottom:14px;">
                <div>
                    <span class="badge <?= $anak_terpilih['jenis_kelamin'] === 'L' ? 'badge-gender-l' : 'badge-gender-p' ?>">
                        <?= $anak_terpilih['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?>
                    </span>
                    <h2 style="font-size:22px; margin:4px 0 2px; color:var(--ink);">
                        <?= htmlspecialchars($anak_terpilih['nama']) ?>
                    </h2>
                    <p class="muted" style="margin:0; font-size:12px;">
                        Lahir: <strong><?= date('d/m/Y', strtotime($anak_terpilih['tanggal_lahir'])) ?></strong> (Usia: <strong><?= $usia_anak ?></strong>) &bull; Ibu: <strong><?= htmlspecialchars($anak_terpilih['nama_ibu'] ?: '—') ?></strong>
                    </p>
                </div>
                
                <div style="text-align:right;">
                    <span class="muted" style="font-size:11px;">Alamat:</span>
                    <div style="font-size:12px; font-weight:600; color:var(--ink);"><?= htmlspecialchars($anak_terpilih['alamat'] ?: 'Dusun Desa') ?></div>
                </div>
            </div>

            <?php if ($pemeriksaan_terakhir): 
                $status_tbu = $pemeriksaan_terakhir['status_tbu'];
                $callout_class = 'normal';
                $status_title = 'Pertumbuhan Normal';
                $status_desc = 'Tinggi badan ananda sesuai standar seusianya. Pertahankan pola asuh dan hadir rutin di Posyandu.';

                if ($status_tbu === 'Sangat Pendek') {
                    $callout_class = 'danger';
                    $status_title = 'Perhatian: Sangat Pendek (Risiko Stunting Berat)';
                    $status_desc = 'Tinggi badan ananda berada di bawah standar. Disarankan konsultasi dengan Bidan Desa / Puskesmas untuk evaluasi gizi.';
                } elseif ($status_tbu === 'Pendek') {
                    $callout_class = 'warning';
                    $status_title = 'Perhatian: Pendek (Risiko Stunting)';
                    $status_desc = 'Tinggi badan ananda masih di bawah normal. Tambah asupan protein hewani harian (telur/ikan) dan pantau bulan depan.';
                } elseif ($status_tbu === 'Tinggi') {
                    $callout_class = 'normal';
                    $status_title = 'Pertumbuhan Sangat Baik (Tinggi)';
                    $status_desc = 'Tinggi badan ananda di atas rata-rata seusianya dengan pertumbuhan yang sangat baik.';
                }
            ?>
                <!-- Status Box Ringkas -->
                <div class="status-callout <?= $callout_class ?>">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span aria-hidden="true"><?= ui_icon($callout_class === 'normal' ? 'check-circle' : 'alert-circle', '', 18) ?></span>
                        <strong style="font-size:15px;"><?= $status_title ?></strong>
                    </div>
                    <p style="margin:6px 0 0; font-size:13px; line-height:1.5;">
                        <?= $status_desc ?>
                    </p>
                </div>

                <!-- 3 Angka Pengukuran Terakhir -->
                <div class="growth-stat-grid">
                    <div class="growth-stat-box">
                        <small>Tinggi / Panjang Badan</small>
                        <strong><?= number_format($pemeriksaan_terakhir['tinggi_cm'], 1) ?> cm</strong>
                        <span style="font-size:11px; color:var(--muted);"><?= $pemeriksaan_terakhir['jenis_pengukuran'] === 'PB' ? 'Ukur Telentang' : 'Ukur Berdiri' ?></span>
                    </div>

                    <div class="growth-stat-box">
                        <small>Berat Badan</small>
                        <strong><?= number_format($pemeriksaan_terakhir['berat_kg'], 2) ?> kg</strong>
                        <span style="font-size:11px; color:var(--muted);">Status: <?= htmlspecialchars($pemeriksaan_terakhir['status_bbu']) ?></span>
                    </div>

                    <div class="growth-stat-box">
                        <small>Pemeriksaan Terakhir</small>
                        <strong><?= date('d/m/Y', strtotime($pemeriksaan_terakhir['tanggal_periksa'])) ?></strong>
                        <span style="font-size:11px; color:var(--muted);">Usia periksa: <?= $pemeriksaan_terakhir['umur_bulan'] ?> bln</span>
                    </div>
                </div>

                <!-- 3 Poin Penting di Rumah (To the point) -->
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:10px; margin:16px 0;">
                    <div style="background:#ffffff; border:1px solid var(--line); border-radius:var(--radius-control); padding:10px 12px; font-size:12px;">
                        <strong style="color:var(--primary-dark); display:block; margin-bottom:2px;">1. Protein Hewani Harian</strong>
                        <span class="muted">Minimal 1 butir telur atau ikan setiap hari.</span>
                    </div>
                    <div style="background:#ffffff; border:1px solid var(--line); border-radius:var(--radius-control); padding:10px 12px; font-size:12px;">
                        <strong style="color:var(--primary-dark); display:block; margin-bottom:2px;">2. Timbang Rutin</strong>
                        <span class="muted">Hadir tiap bulan di jadwal Posyandu.</span>
                    </div>
                    <div style="background:#ffffff; border:1px solid var(--line); border-radius:var(--radius-control); padding:10px 12px; font-size:12px;">
                        <strong style="color:var(--primary-dark); display:block; margin-bottom:2px;">3. Air Minum Matang</strong>
                        <span class="muted">Cegah diare infeksius pada anak.</span>
                    </div>
                </div>

                <!-- Riwayat Penimbangan Bulanan -->
                <div style="margin-top:20px;">
                    <h3 style="margin:0 0 10px; font-size:15px;">Riwayat Penimbangan Posyandu</h3>
                    
                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Usia</th>
                                    <th>Tinggi</th>
                                    <th>Berat</th>
                                    <th>Status Pertumbuhan</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($riwayat_pemeriksaan as $rp): 
                                    $b_class = 'badge-secondary';
                                    if ($rp['status_tbu'] === 'Normal') $b_class = 'badge-success';
                                    elseif ($rp['status_tbu'] === 'Pendek') $b_class = 'badge-warning';
                                    elseif ($rp['status_tbu'] === 'Sangat Pendek') $b_class = 'badge-danger';
                                    elseif ($rp['status_tbu'] === 'Tinggi') $b_class = 'badge-info';
                                ?>
                                    <tr>
                                        <td><strong><?= date('d/m/Y', strtotime($rp['tanggal_periksa'])) ?></strong></td>
                                        <td><?= $rp['umur_bulan'] ?> bln</td>
                                        <td><?= number_format($rp['tinggi_cm'], 1) ?> cm</td>
                                        <td><?= number_format($rp['berat_kg'], 2) ?> kg</td>
                                        <td><span class="badge <?= $b_class ?>"><?= htmlspecialchars($rp['status_tbu']) ?></span></td>
                                        <td><small class="muted"><?= htmlspecialchars($rp['catatan'] ?: 'Sehat') ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <div class="empty-state" style="padding:24px 20px;">
                    <div class="empty-state-icon"><?= ui_icon('scale', '', 32) ?></div>
                    <h3>Belum ada data penimbangan</h3>
                    <p>Ananda <strong><?= htmlspecialchars($anak_terpilih['nama']) ?></strong> sudah terdaftar, silakan hadir di hari buka Posyandu berikutnya.</p>
                </div>
            <?php endif; ?>
        </article>
    <?php else: ?>
        <article class="result-card-warga">
            <div class="empty-state" style="padding:32px 20px;">
                <div class="empty-state-icon"><?= ui_icon('search', '', 32) ?></div>
                <h3>Balita Tidak Ditemukan</h3>
                <p>Data "<strong><?= htmlspecialchars($cari) ?></strong>" tidak ditemukan. Pastikan nama sesuai atau hubungi kader Posyandu desa.</p>
            </div>
        </article>
    <?php endif; ?>

    <footer class="app-footer no-print">
        <p><strong>SiTumbuh Warga</strong> &mdash; Informasi Posyandu Balita</p>
        <small>Posyandu &amp; KKN Desa</small>
    </footer>
</main>

</body>
</html>
