<?php
session_start();
require_once 'cek_session.php';

mysqli_report(MYSQLI_REPORT_OFF);
mysqli_set_charset($koneksi, "utf8mb4");

// Fungsi untuk mengamankan teks HTML
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Menghitung jumlah data dengan aman jika tabel belum tersedia
function jumlahData($koneksi, $tabel) {
    $tabelDiizinkan = ['siswa', 'kelas', 'tahun_ajaran'];

    if (!in_array($tabel, $tabelDiizinkan, true)) {
        return 0;
    }

    try {
        $result = mysqli_query(
            $koneksi,
            "SELECT COUNT(*) AS total FROM `$tabel`"
        );

        if ($result) {
            return (int)mysqli_fetch_assoc($result)['total'];
        }
    } catch (Throwable $err) {
        // Tabel belum tersedia
    }

    return 0;
}

$totalSiswa = jumlahData($koneksi, 'siswa');
$totalKelas = jumlahData($koneksi, 'kelas');
$totalTahun = jumlahData($koneksi, 'tahun_ajaran');

$tahunAktif = null;
$daftarTahun = null;

try {
    $resultAktif = mysqli_query(
        $koneksi,
        "SELECT tahun_ajaran, semester
         FROM tahun_ajaran
         WHERE status = 'Aktif'
         LIMIT 1"
    );

    if ($resultAktif) {
        $tahunAktif = mysqli_fetch_assoc($resultAktif);
    }

    $daftarTahun = mysqli_query(
        $koneksi,
        "SELECT id_tahun_ajaran, tahun_ajaran, semester, status
         FROM tahun_ajaran
         ORDER BY tahun_ajaran DESC,
         FIELD(semester, 'Ganjil', 'Genap')
         LIMIT 5"
    );
} catch (Throwable $err) {
    $daftarTahun = false;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Admin | UKK-2026</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <style>
        :root {
            --sidebar: #172554;
            --sidebar-hover: #263b75;
            --primary: #2563eb;
            --background: #f4f7fb;
        }

        body {
            margin: 0;
            background: var(--background);
            font-family: Arial, sans-serif;
            color: #1e293b;
        }

        .sidebar {
            width: 250px;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 24px 16px;
            background: var(--sidebar);
            color: white;
            z-index: 1000;
        }

        .brand {
            font-size: 21px;
            font-weight: bold;
            padding: 8px 12px 28px;
        }

        .brand small {
            display: block;
            margin-top: 7px;
            color: #bfdbfe;
            font-size: 12px;
            font-weight: normal;
        }

        .menu-label {
            color: #94a3b8;
            font-size: 11px;
            text-transform: uppercase;
            padding: 10px 12px;
            letter-spacing: 1px;
        }

        .sidebar a {
            display: flex;
            gap: 12px;
            align-items: center;
            color: #dbeafe;
            text-decoration: none;
            padding: 12px;
            margin: 4px 0;
            border-radius: 9px;
            font-size: 14px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: var(--sidebar-hover);
            color: white;
        }

        .sidebar a.active {
            border-left: 3px solid #60a5fa;
        }

        .main {
            margin-left: 250px;
            padding: 28px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
        }

        .page-title {
            font-size: 25px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .muted {
            color: #64748b;
            font-size: 13px;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 10px 14px;
            border-radius: 12px;
            box-shadow: 0 3px 15px #0f172a08;
        }

        .avatar {
            display: grid;
            place-items: center;
            background: #dbeafe;
            color: #1d4ed8;
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        .stat-card {
            border: 0;
            border-radius: 16px;
            padding: 22px;
            background: white;
            height: 100%;
            box-shadow: 0 4px 18px #0f172a08;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            font-size: 23px;
            margin-bottom: 20px;
        }

        .blue { background: #dbeafe; color: #2563eb; }
        .green { background: #dcfce7; color: #16a34a; }
        .orange { background: #ffedd5; color: #ea580c; }
        .purple { background: #ede9fe; color: #7c3aed; }

        .stat-number {
            font-size: 30px;
            font-weight: bold;
            margin: 6px 0;
        }

        .panel {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 18px #0f172a08;
        }

        .panel-heading {
            padding: 20px 22px;
            background: white;
            border-bottom: 1px solid #eef2f7;
        }

        .table td, .table th {
            padding: 15px 18px;
            vertical-align: middle;
            font-size: 13px;
        }

        .active-year {
            background: linear-gradient(120deg, #1d4ed8, #4338ca);
            color: white;
            padding: 24px;
            border-radius: 16px;
            height: 100%;
        }

        .active-year .year {
            font-size: 28px;
            font-weight: bold;
            margin: 14px 0 5px;
        }

        .active-year .semester {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 30px;
            background: #ffffff26;
            font-size: 12px;
        }

        .quick-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 15px 0;
            border-bottom: 1px solid #eef2f7;
            color: #334155;
            text-decoration: none;
        }

        .quick-link:last-child { border-bottom: 0; }
        .quick-link:hover { color: var(--primary); }

        .footer {
            margin-top: 30px;
            padding: 18px 0;
            color: #94a3b8;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: static;
                width: 100%;
                min-height: auto;
                padding: 12px;
            }

            .brand { padding: 10px; }

            .sidebar nav {
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
            }

            .sidebar nav a {
                padding: 9px;
                font-size: 12px;
            }

            .menu-label { width: 100%; }

            .main {
                margin-left: 0;
                padding: 18px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">
        <i class="bi bi-mortarboard-fill me-2"></i>
        UKK-2026
        <small>Sistem Informasi Sekolah</small>
    </div>

    <nav>
        <div class="menu-label">Menu Utama</div>

        <a href="index.php" class="active">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>

        <a href="datasiswa.php">
            <i class="bi bi-people-fill"></i> Data Siswa
        </a>

        <a href="datakelas.php">
            <i class="bi bi-building"></i> Data Kelas
        </a>

        <div class="menu-label">Pengaturan</div>

        <a href="tahunajaran.php">
            <i class="bi bi-calendar3"></i> Tahun Ajaran
        </a>
    </nav>
</aside>

<main class="main">
    <header class="topbar">
        <div>
            <div class="page-title">Dashboard</div>
            <div class="muted">
                Selamat datang di panel administrasi UKK-2026.
            </div>
        </div>

        <div class="profile">
            <div class="avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div>
                <div class="fw-bold small">Administrator</div>
                <div class="muted">Panel Admin</div>
            </div>
        </div>
    </header>

    <section class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="muted">Total Siswa</div>
                <div class="stat-number"><?= $totalSiswa ?></div>
                <div class="muted">Data siswa terdaftar</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="bi bi-building"></i>
                </div>
                <div class="muted">Total Kelas</div>
                <div class="stat-number"><?= $totalKelas ?></div>
                <div class="muted">Kelas terdaftar</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="muted">Tahun Ajaran</div>
                <div class="stat-number"><?= $totalTahun ?></div>
                <div class="muted">Data tahun ajaran</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div class="muted">Status Sistem</div>
                <div class="stat-number fs-4">Online</div>
                <div class="muted">Halaman admin tersedia</div>
            </div>
        </div>
    </section>

    <section class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="active-year">
                <div>
                    <i class="bi bi-calendar2-week fs-3"></i>
                </div>
                <div class="mt-3">Tahun Ajaran Aktif</div>

                <?php if ($tahunAktif): ?>
                    <div class="year">
                        <?= e($tahunAktif['tahun_ajaran']) ?>
                    </div>
                    <span class="semester">
                        Semester <?= e($tahunAktif['semester']) ?>
                    </span>
                <?php else: ?>
                    <div class="year">Belum Diatur</div>
                    <p class="small mb-3">
                        Belum ada tahun ajaran aktif di database.
                    </p>
                <?php endif; ?>

                <div class="mt-4">
                    <a href="tahunajaran.php"
                       class="btn btn-light btn-sm text-primary fw-bold">
                        Kelola Tahun Ajaran
                        <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="panel bg-white h-100">
                <div class="panel-heading">
                    <h6 class="fw-bold mb-1">Akses Cepat</h6>
                    <div class="muted">Menu pengelolaan data</div>
                </div>

                <div class="p-3 px-4">
                    <a href="datasiswa.php" class="quick-link">
                        <span>
                            <i class="bi bi-person-lines-fill me-2"></i>
                            Kelola Data Siswa
                        </span>
                        <i class="bi bi-chevron-right"></i>
                    </a>

                    <a href="datakelas.php" class="quick-link">
                        <span>
                            <i class="bi bi-buildings me-2"></i>
                            Kelola Data Kelas
                        </span>
                        <i class="bi bi-chevron-right"></i>
                    </a>

                    <a href="tahunajaran.php" class="quick-link">
                        <span>
                            <i class="bi bi-calendar-event me-2"></i>
                            Kelola Tahun Ajaran
                        </span>
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="panel bg-white">
        <div class="panel-heading d-flex justify-content-between
                    align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold mb-1">Data Tahun Ajaran</h6>
                <div class="muted">Lima data tahun ajaran terbaru</div>
            </div>

            <a href="tahunajaran.php"
               class="btn btn-primary btn-sm">
                <i class="bi bi-gear me-1"></i> Kelola Data
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No.</th>
                        <th>Tahun Ajaran</th>
                        <th>Semester</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($daftarTahun && mysqli_num_rows($daftarTahun) > 0): ?>
                        <?php $no = 1; ?>
                        <?php while ($row = mysqli_fetch_assoc($daftarTahun)): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="fw-semibold">
                                    <?= e($row['tahun_ajaran']) ?>
                                </td>
                                <td><?= e($row['semester']) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'Aktif'): ?>
                                        <span class="badge text-bg-success">
                                            Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">
                                            Tidak Aktif
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4"
                                class="text-center text-secondary py-4">
                                Belum ada data tahun ajaran.
                                <a href="tahunajaran.php">Tambah sekarang</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <footer class="footer">
        &copy; <?= date('Y') ?> UKK-2026
        &middot; Sistem Informasi Sekolah
    </footer>
</main>

</body>
</html>
2. Struktur folder proyek
Pastikan folder kamu seperti berikut:

