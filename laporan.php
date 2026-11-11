<?php
require_once __DIR__ . '/cek_session.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'] ?? '';
$aksesGuru = $role === 'guru';
$laporanTersemat = $role === 'admin' && ($_GET['embedded'] ?? '') === '1';
if ($role !== 'admin' && !$aksesGuru) {
    header('Location: login.php');
    exit;
}

$dataSiswa = [];
$aksesDitolak = false;
$namaGuru = $_SESSION['name'] ?? 'Guru';

if ($aksesGuru) {
    $userId = (int)$_SESSION['id'];
    $stmt = $koneksi->prepare(
        "SELECT wk.id, g.nama
         FROM t_guru g
         INNER JOIN t_wali_kelas wk
            ON wk.guru_id = g.id AND wk.status_aktif = 1
         INNER JOIN t_tahun_ajaran ta
            ON ta.id = wk.tahun_ajaran_id AND ta.status_aktif = 1
         INNER JOIN t_kelas k ON k.id = wk.kelas_id AND k.status_aktif = 1
         WHERE g.user_id = ? AND g.status_aktif = 1
           AND ta.id = (
               SELECT id
               FROM t_tahun_ajaran
               WHERE status_aktif = 1
               ORDER BY (CURDATE() BETWEEN tanggal_mulai AND tanggal_selesai) DESC,
                        tanggal_mulai DESC, id DESC
               LIMIT 1
           )
         LIMIT 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $penugasanWali = $stmt->get_result()->fetch_assoc();
    $aksesDitolak = !$penugasanWali;
    if ($penugasanWali) {
        $namaGuru = $penugasanWali['nama'];
    }

    if (!$aksesDitolak) {
        $stmt = $koneksi->prepare(
            "SELECT DISTINCT s.nis, s.nama,
                    CASE
                        WHEN k.nama LIKE CONCAT(k.tingkat, ' %') THEN k.nama
                        ELSE CONCAT_WS(' ', k.tingkat, k.nama)
                    END AS kelas
             FROM t_guru g
             INNER JOIN t_wali_kelas wk
                ON wk.guru_id = g.id AND wk.status_aktif = 1
             INNER JOIN t_tahun_ajaran ta
                ON ta.id = wk.tahun_ajaran_id AND ta.status_aktif = 1
             INNER JOIN t_kelas k ON k.id = wk.kelas_id AND k.status_aktif = 1
             INNER JOIN t_kelas_siswa ks
                ON ks.kelas_id = wk.kelas_id
               AND ks.tahun_ajaran_id = wk.tahun_ajaran_id
               AND ks.status_aktif = 1
             INNER JOIN t_siswa s ON s.id = ks.siswa_id AND s.status_aktif = 1
             WHERE g.user_id = ? AND g.status_aktif = 1
               AND ta.id = (
                   SELECT id
                   FROM t_tahun_ajaran
                   WHERE status_aktif = 1
                   ORDER BY (CURDATE() BETWEEN tanggal_mulai AND tanggal_selesai) DESC,
                            tanggal_mulai DESC, id DESC
                   LIMIT 1
               )
             ORDER BY kelas ASC, s.nama ASC, s.nis ASC"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $hasil = $stmt->get_result();
        while ($row = $hasil->fetch_assoc()) {
            $dataSiswa[] = $row;
        }
    }
} else {
    $result = mysqli_query(
        $koneksi,
        "SELECT s.nis, s.nama,
                CASE
                    WHEN k.id IS NULL THEN 'Belum ditempatkan'
                    ELSE CONCAT_WS(' ', k.tingkat, k.nama)
                END AS kelas
         FROM t_siswa s
         LEFT JOIN t_kelas_siswa ks
           ON ks.id = (
               SELECT ks_terbaru.id
               FROM t_kelas_siswa ks_terbaru
               WHERE ks_terbaru.siswa_id = s.id
                 AND ks_terbaru.status_aktif = 1
               ORDER BY ks_terbaru.tanggal_mulai DESC, ks_terbaru.id DESC
               LIMIT 1
           )
         LEFT JOIN t_kelas k ON k.id = ks.kelas_id
         ORDER BY s.nama ASC, s.nis ASC"
    );

    while ($row = mysqli_fetch_assoc($result)) {
        $dataSiswa[] = $row;
    }
}

if ($aksesDitolak) {
    http_response_code(403);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Data Siswa</title>

    <style>
        :root {
            --ink: #17232c;
            --muted: #64748b;
            --blue: #1769aa;
            --green: #16836b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: var(--ink);
            font-family: Arial, sans-serif;
            background: #f2f6f5;
        }

        .app-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 252px minmax(0, 1fr);
        }

        .embedded-report {
            min-height: 100vh;
        }

        .layout {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 252px minmax(0, 1fr);
        }

        @media (max-width: 850px) {
            .layout {
                grid-template-columns: 220px minmax(0, 1fr);
            }
        }

        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 24px 18px;
            color: #f8fafc;
            background: #17232c;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 2px 4px 34px;
        }

        .brand-mark {
            width: 40px;
            height: 40px;
            display: grid;
            flex: 0 0 40px;
            place-items: center;
            border-radius: 10px;
            color: #fff;
            background: var(--green);
            font-size: 13px;
            font-weight: 700;
        }

        .brand h1 {
            margin: 0;
            font-size: 15px;
            line-height: 1.3;
        }

        .brand p {
            margin: 3px 0 0;
            color: #a9bac1;
            font-size: 12px;
        }

        .nav-label {
            margin: 0 10px 10px;
            color: #8fa3ac;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .nav-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .nav-spacer {
            flex: 1;
        }

        .nav-link {
            display: block;
            padding: 10px 12px;
            border-radius: 7px;
            color: #d7e1e5;
            font-size: 14px;
            text-decoration: none;
        }

        .nav-link:hover,
        .nav-link:focus-visible {
            color: #fff;
            background: #293943;
        }

        .nav-link.active {
            color: #fff;
            background: var(--blue);
            font-weight: 700;
        }

        .nav-divider {
            height: 1px;
            margin: 18px 8px 12px;
            border: 0;
            background: #35434b;
        }

        .nav-link.logout {
            color: #ffb4a9;
        }

        .layout .nav-list {
            flex: 1;
        }

        .layout .nav-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 9px;
            font-size: 15px;
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            flex: 0 0 20px;
        }

        .layout .nav-link {
            color: #d7e1e5;
            transition: background-color .15s ease, color .15s ease;
        }

        .layout .nav-link.active {
            background: #0789f5;
        }

        .layout .nav-divider {
            margin: 12px 8px;
        }

        .layout .nav-link.logout {
            color: #f87171;
        }

        .layout .nav-link.logout:hover,
        .layout .nav-link.logout:focus-visible {
            color: #fca5a5;
            background: #293943;
        }

        .sidebar-footer {
            margin: auto 10px 0;
            color: #a9bac1;
            font-size: 12px;
        }

        .main-content {
            min-width: 0;
            padding: 30px clamp(18px, 4vw, 56px);
        }

        .content {
            width: min(1120px, 100%);
            margin: 0 auto;
        }

        .panel {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 6px #00000010;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
        }

        input,
        select,
        button {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        input {
            flex: 1;
            min-width: 180px;
        }

        button {
            background: #0d6efd;
            color: white;
            border: none;
            cursor: pointer;
        }

        .print-btn {
            background: #198754;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #0d6efd;
            color: white;
        }

        tr:nth-child(even) {
            background: #f8f9fa;
        }

        .jumlah {
            margin-top: 15px;
            font-weight: bold;
        }

        @media print {

            .sidebar,
            .toolbar,
            .no-print {
                display: none !important;
            }

            .content {
                padding: 0;
            }

            .app-shell {
                display: block;
            }

            .layout {
                display: block;
            }

            .main-content {
                padding: 0;
            }

            .panel {
                box-shadow: none;
            }

            body {
                background: white;
            }
        }

        @media (max-width: 850px) {
            .layout {
                grid-template-columns: 220px minmax(0, 1fr);
            }
        }

        @media (max-width: 650px) {
            .app-shell {
                display: block;
            }

            .app-shell .sidebar {
                position: static;
                height: auto;
                padding: 16px;
            }

            .app-shell .brand {
                margin: 0 2px 18px;
            }

            .app-shell .nav-list {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                overflow: visible;
            }

            .app-shell .nav-divider,
            .app-shell .sidebar-footer {
                display: none;
            }

            .app-shell .main-content {
                padding: 22px 16px;
            }
        }

        @media (max-width: 600px) {
            .layout {
                display: block;
            }

            .layout .sidebar {
                position: static;
                height: auto;
                padding: 16px;
            }

            .layout .brand {
                margin: 0 2px 18px;
            }

            .layout .nav-list {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .layout .nav-spacer,
            .layout .nav-divider,
            .layout .sidebar-footer {
                display: none;
            }

            .layout .main-content {
                padding: 22px 16px;
            }
        }
    </style>
</head>

<body>
    <div class="<?= $laporanTersemat ? 'embedded-report' : ($aksesGuru ? 'layout' : 'app-shell') ?>">
        <?php if (!$laporanTersemat): ?>
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">AS</div>
                <div>
                    <h1><?= $aksesGuru ? 'Guru Sekolah' : 'Admin Sekolah' ?></h1>
                    <p><?= $aksesGuru ? htmlspecialchars((string)$namaGuru, ENT_QUOTES, 'UTF-8') : 'Sistem informasi sekolah' ?></p>
                </div>
            </div>
            <p class="nav-label">Menu utama</p>
            <ul class="nav-list">
                <?php if ($aksesGuru): ?>
                    <li><a class="nav-link" href="guru_dashboard.php?menu=dashboard#dashboard">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 10.8 12 3l9 7.8v9.7a.5.5 0 0 1-.5.5h-6v-6h-5v6h-6a.5.5 0 0 1-.5-.5z"/></svg>
                        <span>Dashboard</span>
                    </a></li>
                    <li><a class="nav-link" href="guru_dashboard.php?menu=catat-pelanggaran#catat-pelanggaran">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 4h8l2 2h2v15H4V6h2z"/><path d="M9 4V2h6v2M12 10v7m-3.5-3.5h7"/></svg>
                        <span>Catat Pelanggaran</span>
                    </a></li>
                    <li><a class="nav-link" href="guru_dashboard.php?menu=tindakan#tindakan">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 20 8.5-8.5"/><path d="m13.5 4 6.5 6.5-3 3L10.5 7z"/><path d="m3 21 3-3"/></svg>
                        <span>Tindakan</span>
                    </a></li>
                    <li><a class="nav-link" href="guru_dashboard.php?menu=rekap-poin#rekap-poin">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 13h4v8H4zm6-6h4v14h-4zm6-5h4v19h-4z"/></svg>
                        <span>Rekap Poin</span>
                    </a></li>
                    <li><a class="nav-link" href="guru_dashboard.php?menu=riwayat#riwayat">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        <span>Riwayat</span>
                    </a></li>
                    <li><a class="nav-link" href="guru_dashboard.php?menu=laporan#laporan">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                            <path d="M8 7h8M8 11h8"/>
                        </svg>
                        <span>Laporan Pelanggaran</span>
                    </a></li>
                    <li class="nav-spacer" aria-hidden="true"></li>
                <?php else: ?>
                    <li><a href="dashboard.php?page=dashboard" class="nav-link">Dashboard</a></li>
                    <li><a href="dashboard.php?page=dataguru" class="nav-link">Data Guru</a></li>
                    <li><a href="dashboard.php?page=penempatan_siswa" class="nav-link">Penempatan Siswa</a></li>
                    <li><a href="dashboard.php?page=datasiswa" class="nav-link">Data Siswa</a></li>
                    <li><a href="dashboard.php?page=datakelas" class="nav-link">Data Kelas</a></li>
                    <li><a href="laporan.php" class="nav-link active" aria-current="page">Laporan</a></li>
                    <li><a href="dashboard.php?page=tahun_ajaran" class="nav-link">Tahun Ajaran</a></li>
                    <li><a href="dashboard.php?page=wali_kelas" class="nav-link">Wali Kelas</a></li>
                    <li><a href="dashboard.php?page=pelanggaran" class="nav-link">Kategori Pelanggaran</a></li>
                    <li><a href="dashboard.php?page=jenis_pelanggaran" class="nav-link">Jenis Pelanggaran</a></li>
                    <li><a href="dashboard.php?page=aboutme" class="nav-link">About Me</a></li>
                <?php endif; ?>
                <li><hr class="nav-divider"></li>
                <li><a href="logout.php" class="nav-link logout">
                    <?php if ($aksesGuru): ?>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M10 17l5-5-5-5m5 5H3"/>
                            <path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>
                        </svg>
                    <?php endif; ?>
                    <span>Logout</span>
                </a></li>
            </ul>
            <p class="sidebar-footer">UKK 2026</p>
        </aside>
        <?php endif; ?>

        <main class="main-content">
        <div class="content">
        <h2>Laporan Data Siswa</h2>
        <?php if ($aksesDitolak): ?>
            <section class="panel">
                <h3>Akses laporan ditolak</h3>
                <p>Halaman laporan hanya tersedia bagi guru yang ditugaskan sebagai wali kelas pada tahun ajaran aktif.</p>
                <a href="guru_dashboard.php">Kembali ke Dashboard Guru</a>
            </section>
        <?php else: ?>
        <p><?= $aksesGuru ? 'Laporan siswa dari kelas binaan Anda.' : 'Halaman laporan data siswa sekolah.' ?></p>

        <section class="panel">
            <h3>Daftar Siswa</h3>

            <div class="toolbar no-print">
                <input
                    type="text"
                    id="cari"
                    placeholder="Cari NIS, nama, atau kelas...">

                <button type="button" class="print-btn"
                    onclick="window.print()">
                    Cetak Laporan
                </button>
            </div>

            <div class="table-wrapper">
                <table id="tabelSiswa">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($dataSiswa) > 0): ?>
                            <?php foreach ($dataSiswa as $i => $siswa): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars((string)$siswa['nis']) ?></td>
                                    <td><?= htmlspecialchars($siswa['nama']) ?></td>
                                    <td><?= htmlspecialchars($siswa['kelas']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">Belum ada data siswa.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <p class="jumlah" id="jumlahData">
                Total data: <?= count($dataSiswa) ?> siswa
            </p>
        </section>
        <?php endif; ?>
        </div>
        </main>
    </div>

    <?php if (!$aksesDitolak): ?>
    <script>
        const inputCari = document.getElementById('cari');
        const tabel = document.getElementById('tabelSiswa');
        const jumlahData = document.getElementById('jumlahData');

        inputCari.addEventListener('input', function() {
            const kata = this.value.toLowerCase();
            const baris = tabel.querySelectorAll('tbody tr');
            let jumlah = 0;

            baris.forEach(function(tr) {
                const cocok = tr.textContent.toLowerCase().includes(kata);
                tr.style.display = cocok ? '' : 'none';

                if (cocok && tr.cells.length === 4) {
                    jumlah++;
                }
            });

            jumlahData.textContent = 'Total data: ' + jumlah + ' siswa';
        });
    </script>
    <?php endif; ?>

</body>

</html>