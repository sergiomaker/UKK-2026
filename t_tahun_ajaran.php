
<?php
require_once __DIR__ . '/cek_session.php';

$hasilRingkasan = mysqli_query(
    $koneksi,
    "SELECT
        (SELECT COUNT(*) FROM t_siswa WHERE status_aktif = 1) AS total_siswa,
        (SELECT COUNT(*) FROM t_kelas WHERE status_aktif = 1) AS total_kelas,
        (SELECT COUNT(*) FROM t_tahun_ajaran) AS total_tahun"
);
if (!$hasilRingkasan) {
    die('Gagal mengambil ringkasan data: ' . htmlspecialchars(mysqli_error($koneksi), ENT_QUOTES, 'UTF-8'));
}
$ringkasan = mysqli_fetch_assoc($hasilRingkasan);

$hasilTahunAktif = mysqli_query(
    $koneksi,
    "SELECT nama, tanggal_mulai, tanggal_selesai
     FROM t_tahun_ajaran
     WHERE status_aktif = 1
     ORDER BY tanggal_mulai DESC, id DESC
     LIMIT 1"
);
if (!$hasilTahunAktif) {
    die('Gagal mengambil tahun ajaran aktif: ' . htmlspecialchars(mysqli_error($koneksi), ENT_QUOTES, 'UTF-8'));
}
$tahunAktif = mysqli_fetch_assoc($hasilTahunAktif);

$hasilDaftarTahun = mysqli_query(
    $koneksi,
    "SELECT nama, tanggal_mulai, tanggal_selesai, status_aktif
     FROM t_tahun_ajaran
     ORDER BY tanggal_mulai DESC, id DESC
     LIMIT 10"
);
if (!$hasilDaftarTahun) {
    die('Gagal mengambil daftar tahun ajaran: ' . htmlspecialchars(mysqli_error($koneksi), ENT_QUOTES, 'UTF-8'));
}

function aman($nilai)
{
    return htmlspecialchars((string)$nilai, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard UKK-2026</title>

<style>
* {
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    margin: 0;
    background: #f1f3f5;
    color: #222;
}

.container {
    max-width: 1120px;
    margin: 30px auto;
    background: white;
    padding: 25px;
    border-radius: 10px;
}

h2 {
    color: #2864e8;
    margin-top: 10px;
}

.subtitle {
    margin-bottom: 25px;
}

.menu {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.menu a {
    text-decoration: none;
    background: #2864e8;
    color: white;
    padding: 10px 16px;
    border-radius: 5px;
    font-size: 14px;
}

.menu a:hover {
    background: #174bc0;
}

.cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.card {
    border: 1px solid #ddd;
    padding: 20px;
    border-radius: 7px;
    background: #fff;
}

.card h3 {
    margin: 0 0 12px;
    font-size: 16px;
}

.card h1 {
    color: #2864e8;
    margin: 0;
    font-size: 30px;
}

.card p {
    color: #666;
    font-size: 14px;
}

.section {
    border: 1px solid #ddd;
    border-radius: 7px;
    margin-bottom: 20px;
    overflow: hidden;
}

.section-header {
    padding: 15px;
    border-bottom: 1px solid #ddd;
}

.section-header h3 {
    margin: 0;
}

.section-body {
    padding: 15px;
}

.btn {
    display: inline-block;
    background: #2864e8;
    color: white;
    text-decoration: none;
    padding: 9px 13px;
    border-radius: 5px;
    font-size: 14px;
}

.btn:hover {
    background: #174bc0;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #2864e8;
    color: white;
    padding: 12px;
    text-align: left;
}

td {
    padding: 12px;
    border: 1px solid #ddd;
}

.empty {
    text-align: center;
    color: #777;
}

@media(max-width: 700px) {
    .container {
        margin: 10px;
        padding: 15px;
    }

    .cards {
        grid-template-columns: 1fr;
    }

    table {
        font-size: 13px;
    }
}
</style>
</head>

<body>

<div class="container">

    <h2>Dashboard</h2>
    <p class="subtitle">
        Selamat datang di Sistem Informasi Sekolah UKK-2026.
    </p>

    <div class="menu">
        <a href="dashboard.php?page=dashboard" target="_top">Dashboard</a>
        <a href="dashboard.php?page=datasiswa" target="_top">Data Siswa</a>
        <a href="dashboard.php?page=datakelas" target="_top">Data Kelas</a>
        <a href="dashboard.php?page=tahun_ajaran" target="_top">Tahun Ajaran</a>
        <a href="logout.php" target="_top">Keluar</a>
    </div>

    <h3>Informasi Sistem</h3>

    <div class="cards">
        <div class="card">
            <h3>Total Siswa</h3>
            <h1><?= number_format((int)$ringkasan['total_siswa']) ?></h1>
            <p>Jumlah siswa terdaftar</p>
        </div>

        <div class="card">
            <h3>Total Kelas</h3>
            <h1><?= number_format((int)$ringkasan['total_kelas']) ?></h1>
            <p>Jumlah kelas tersedia</p>
        </div>

        <div class="card">
            <h3>Tahun Ajaran</h3>
            <h1><?= number_format((int)$ringkasan['total_tahun']) ?></h1>
            <p>Data tahun ajaran</p>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <h3>Tahun Ajaran Aktif</h3>
        </div>

        <div class="section-body">
            <?php if ($tahunAktif): ?>
                <p><strong><?= aman($tahunAktif['nama']) ?></strong></p>
                <p>
                    Periode:
                    <?= aman($tahunAktif['tanggal_mulai']) ?>
                    sampai
                    <?= aman($tahunAktif['tanggal_selesai']) ?>
                </p>
            <?php else: ?>
                <p>Belum ada tahun ajaran aktif.</p>
            <?php endif; ?>
            <a href="dashboard.php?page=tahun_ajaran" class="btn" target="_top">
                Kelola Tahun Ajaran
            </a>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <h3>Akses Menu</h3>
        </div>

        <div class="section-body">
            <a href="dashboard.php?page=datasiswa" class="btn" target="_top">Data Siswa</a>
            <a href="dashboard.php?page=datakelas" class="btn" target="_top">Data Kelas</a>
            <a href="dashboard.php?page=tahun_ajaran" class="btn" target="_top">Tahun Ajaran</a>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <h3>Data Tahun Ajaran</h3>
        </div>

        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Tahun Ajaran</th>
                    <th>Periode</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                <?php $no = 1; ?>
                <?php while ($tahun = mysqli_fetch_assoc($hasilDaftarTahun)): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= aman($tahun['nama']) ?></td>
                        <td>
                            <?= aman($tahun['tanggal_mulai']) ?>
                            s.d.
                            <?= aman($tahun['tanggal_selesai']) ?>
                        </td>
                        <td><?= (int)$tahun['status_aktif'] === 1 ? 'Aktif' : 'Tidak Aktif' ?></td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($no === 1): ?>
                    <tr>
                        <td colspan="4" class="empty">Belum ada data tahun ajaran.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
