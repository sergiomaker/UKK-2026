<?php

session_start();
include "cek_session.php";

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header(($_SESSION['role'] ?? '') === 'guru'
        ? 'Location: guru_dashboard.php'
        : 'Location: login.php');
    exit;
}

$hasilRingkasan = mysqli_query(
    $koneksi,
    "SELECT
        (SELECT COUNT(*) FROM t_siswa WHERE status_aktif = 1) AS siswa,
        (SELECT COUNT(*) FROM t_guru WHERE status_aktif = 1) AS guru,
        (SELECT COALESCE(SUM(poin), 0) FROM t_pelanggaran_siswa) AS total_poin"
);
if (!$hasilRingkasan) {
    die('Gagal mengambil ringkasan dashboard: ' . htmlspecialchars(mysqli_error($koneksi), ENT_QUOTES, 'UTF-8'));
}
$ringkasan = mysqli_fetch_assoc($hasilRingkasan);

$namaBulan = [
    1 => 'Jan',
    2 => 'Feb',
    3 => 'Mar',
    4 => 'Apr',
    5 => 'Mei',
    6 => 'Jun',
    7 => 'Jul',
    8 => 'Agu',
    9 => 'Sep',
    10 => 'Okt',
    11 => 'Nov',
    12 => 'Des',
];
$awalGrafik = new DateTimeImmutable('first day of this month');
$awalGrafik = $awalGrafik->modify('-5 months');
$bulanGrafik = [];
for ($i = 0; $i < 6; $i++) {
    $bulan = $awalGrafik->modify("+{$i} months");
    $kunciBulan = $bulan->format('Y-m');
    $bulanGrafik[$kunciBulan] = [
        'label' => $namaBulan[(int)$bulan->format('n')] . ' ' . $bulan->format('Y'),
        'poin' => 0,
        'jumlah' => 0,
    ];
}

$stmtGrafik = mysqli_prepare(
    $koneksi,
    "SELECT DATE_FORMAT(tanggal, '%Y-%m') AS bulan,
            COALESCE(SUM(poin), 0) AS poin,
            COUNT(*) AS jumlah
     FROM t_pelanggaran_siswa
     WHERE tanggal >= ? AND tanggal <= CURDATE()
     GROUP BY DATE_FORMAT(tanggal, '%Y-%m')"
);
if (!$stmtGrafik) {
    die('Gagal menyiapkan grafik rekap poin: ' . htmlspecialchars(mysqli_error($koneksi), ENT_QUOTES, 'UTF-8'));
}
$tanggalAwalGrafik = $awalGrafik->format('Y-m-d');
mysqli_stmt_bind_param($stmtGrafik, 's', $tanggalAwalGrafik);
if (!mysqli_stmt_execute($stmtGrafik)) {
    die('Gagal mengambil grafik rekap poin: ' . htmlspecialchars(mysqli_stmt_error($stmtGrafik), ENT_QUOTES, 'UTF-8'));
}
$hasilGrafik = mysqli_stmt_get_result($stmtGrafik);
while ($barisGrafik = mysqli_fetch_assoc($hasilGrafik)) {
    if (isset($bulanGrafik[$barisGrafik['bulan']])) {
        $bulanGrafik[$barisGrafik['bulan']]['poin'] = (int)$barisGrafik['poin'];
        $bulanGrafik[$barisGrafik['bulan']]['jumlah'] = (int)$barisGrafik['jumlah'];
    }
}
mysqli_stmt_close($stmtGrafik);

$hasilRekapPoin = mysqli_query(
    $koneksi,
    "SELECT siswa_id, nama_siswa, COALESCE(SUM(poin), 0) AS total_poin,
            COUNT(*) AS jumlah_pelanggaran
     FROM t_pelanggaran_siswa
     GROUP BY siswa_id, nama_siswa
     ORDER BY total_poin DESC, jumlah_pelanggaran DESC, nama_siswa ASC
     LIMIT 5"
);
if (!$hasilRekapPoin) {
    die('Gagal mengambil rekap poin siswa: ' . htmlspecialchars(mysqli_error($koneksi), ENT_QUOTES, 'UTF-8'));
}
$rekapPoin = [];
while ($barisRekap = mysqli_fetch_assoc($hasilRekapPoin)) {
    $rekapPoin[] = $barisRekap;
}
$poinTertinggiBulanan = max(
    1,
    ...array_column($bulanGrafik, 'poin')
);

$currentPage = $_GET['page'] ?? 'dashboard';
$pages = [
    'dashboard' => null,
    'dataguru' => 'dataguru.php',
    'penempatan_siswa' => 'penempatan_siswa.php',
    'datakelas' => 'datakelas.php',
    'datasiswa' => 'datasiswa.php',
    'laporan' => 'laporan.php',
    'tahun_ajaran' => 't_tahun_ajaran.php',
    'wali_kelas' => 'kelola_wali_kelas.php',
    'pelanggaran' => 'kategori%20pelanggaran.php',
    'jenis_pelanggaran' => 'jenis_pelanggaran.php',
    'aboutme' => 'aboutme.php',
];

$pageFile = $pages[$currentPage] ?? null;
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Admin Sekolah</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #17232c;
            --muted: #64748b;
            --line: #dce4e8;
            --blue: #1769aa;
            --green: #16836b;
            --amber: #bf6d24;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: var(--ink);
            background-color: #f2f6f5;
            background-image: radial-gradient(#d8e6e2 0.7px, transparent 0.7px);
            background-size: 18px 18px;
            font-family: Arial, sans-serif;
        }

        .app-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 252px minmax(0, 1fr);
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
            place-items: center;
            flex: 0 0 40px;
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

        .nav-link {
            display: block;
            padding: 10px 12px;
            border-radius: 7px;
            color: #d7e1e5;
            font-size: 14px;
            text-decoration: none;
            transition: background-color .15s ease, color .15s ease;
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

        .sidebar-footer {
            margin: auto 10px 0;
            color: #a9bac1;
            font-size: 12px;
        }

        .main-content {
            min-width: 0;
            padding: 30px clamp(18px, 4vw, 56px);
        }

        .dashboard-content {
            width: min(1120px, 100%);
            margin: 0 auto;
        }

        .page-heading {
            margin-bottom: 26px;
        }

        .eyebrow {
            margin: 0 0 8px;
            color: var(--green);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .page-heading h2 {
            margin: 0;
            color: var(--ink);
            font-size: 30px;
        }

        .page-heading p:last-child {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 15px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .stat-card {
            min-height: 152px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
            padding: 20px;
            border: 1px solid var(--line);
            border-top: 3px solid var(--accent);
            border-radius: 8px;
            color: var(--ink);
            background: #fff;
            text-decoration: none;
            box-shadow: 0 3px 12px rgba(23, 35, 44, .04);
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .stat-card:hover,
        .stat-card:focus-visible {
            color: var(--ink);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(23, 35, 44, .09);
        }

        .stat-card:nth-child(1) { --accent: var(--blue); }
        .stat-card:nth-child(2) { --accent: var(--green); }
        .stat-card:nth-child(3) { --accent: var(--amber); }
        .stat-card:nth-child(4) { --accent: #7952b3; }

        .stat-label {
            color: var(--muted);
            font-size: 14px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 34px;
            font-weight: 700;
            line-height: 1;
        }

        .stat-note {
            color: var(--muted);
            font-size: 12px;
        }

        .quick-panel {
            margin-top: 22px;
            padding: 22px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }

        .recap-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(0, .85fr);
            gap: 16px;
            margin-top: 22px;
        }

        .recap-panel {
            min-width: 0;
            padding: 22px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }

        .recap-panel h3 {
            margin: 0;
            font-size: 17px;
        }

        .recap-description {
            margin: 7px 0 18px;
            color: var(--muted);
            font-size: 13px;
        }

        .points-chart {
            min-height: 235px;
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 10px;
            align-items: end;
            padding: 16px 8px 0;
            border-bottom: 1px solid var(--line);
        }

        .chart-month {
            min-width: 0;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
        }

        .chart-value {
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
        }

        .chart-bar-track {
            width: min(100%, 42px);
            height: 150px;
            display: flex;
            align-items: flex-end;
            overflow: hidden;
            border-radius: 6px 6px 0 0;
            background: #f1f5f9;
        }

        .chart-bar {
            width: 100%;
            min-height: 2px;
            border-radius: 6px 6px 0 0;
            background: linear-gradient(180deg, #39a88d, #1769aa);
        }

        .chart-label {
            min-height: 30px;
            color: var(--muted);
            font-size: 11px;
            text-align: center;
        }

        .recap-table-wrap {
            overflow-x: auto;
        }

        .recap-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .recap-table th,
        .recap-table td {
            padding: 11px 8px;
            border-bottom: 1px solid var(--line);
            text-align: left;
        }

        .recap-table th {
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
        }

        .recap-table td:last-child,
        .recap-table th:last-child {
            text-align: right;
            white-space: nowrap;
        }

        .recap-empty {
            padding: 25px 10px;
            color: var(--muted);
            text-align: center;
        }

        .quick-panel h3 {
            margin: 0 0 6px;
            font-size: 17px;
        }

        .quick-panel p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .quick-link {
            display: inline-flex;
            align-items: center;
            min-height: 40px;
            padding: 0 14px;
            border: 1px solid var(--line);
            border-radius: 6px;
            color: var(--ink);
            background: #fff;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .quick-link:hover,
        .quick-link:focus-visible {
            border-color: var(--blue);
            color: var(--blue);
        }

        .content-frame {
            width: 100%;
            height: calc(100vh - 60px);
            min-height: 700px;
            border: 0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 3px 14px rgba(23, 35, 44, .06);
        }

        @media (max-width: 900px) {
            .app-shell {
                grid-template-columns: 220px minmax(0, 1fr);
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .stat-card {
                min-height: 126px;
            }

            .recap-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .app-shell {
                display: block;
            }

            .sidebar {
                position: static;
                height: auto;
                padding: 16px;
            }

            .brand {
                margin: 0 2px 18px;
            }

            .nav-list {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .nav-divider,
            .sidebar-footer {
                display: none;
            }

            .main-content {
                padding: 22px 16px;
            }

            .page-heading h2 {
                font-size: 26px;
            }

            .content-frame {
                height: 75vh;
                min-height: 620px;
            }
        }
    </style>
    <script src="pencarian_tabel.js" defer></script>
</head>

<body>
    <div class="app-shell">
            <aside class="sidebar">
                <div class="brand">
                    <div class="brand-mark" aria-hidden="true">AS</div>
                    <div>
                        <h1>Admin Sekolah</h1>
                        <p>Sistem informasi sekolah</p>
                    </div>
                </div>

                <p class="nav-label">Menu utama</p>
                <ul class="nav-list">
                    <li class="nav-item mb-2">
                        <a href="dashboard.php?page=dashboard" class="nav-link <?php echo ($currentPage === 'dashboard') ? 'active' : 'text-white'; ?>">
                            Dashboard
                        </a>
                    </li>

                    <li class="nav-item mb-2">
                        <a href="dashboard.php?page=dataguru" class="nav-link <?php echo ($currentPage === 'dataguru') ? 'active' : 'text-white'; ?>">
                            Data Guru
                        </a>
                    </li>

                    <li class="nav-item mb-2">
                        <a href="dashboard.php?page=penempatan_siswa" class="nav-link <?php echo ($currentPage === 'penempatan_siswa') ? 'active' : 'text-white'; ?>">
                            Penempatan Siswa
                        </a>
                    </li>

                    <li class="nav-item mb-2">
                        <a href="dashboard.php?page=datasiswa" class="nav-link <?php echo ($currentPage === 'datasiswa') ? 'active' : 'text-white'; ?>">
                            Data Siswa
                        </a>
                    </li>

                    <li class="nav-item mb-2">
                        <a href="dashboard.php?page=datakelas" class="nav-link <?php echo ($currentPage === 'datakelas') ? 'active' : 'text-white'; ?>">
                            Data Kelas
                        </a>
                    </li>

                    <li class="nav-item mb-2">
                        <a href="dashboard.php?page=laporan" class="nav-link <?php echo ($currentPage === 'laporan') ? 'active' : 'text-white'; ?>">
                            Laporan
                        </a>
                    </li>

                    <li class="nav-item mb-2">
                        <a href="dashboard.php?page=tahun_ajaran" class="nav-link <?php echo ($currentPage === 'tahun_ajaran') ? 'active' : 'text-white'; ?>">
                            Tahun Ajaran
                        </a>
                    </li>

                    <li class="nav-item mb-2">
                        <a href="dashboard.php?page=wali_kelas" class="nav-link <?php echo ($currentPage === 'wali_kelas') ? 'active' : 'text-white'; ?>">
                            Wali Kelas
                        </a>
                    </li>
                    

                    <li class="nav-item mb-2">
                        <a href="dashboard.php?page=pelanggaran" class="nav-link <?php echo ($currentPage === 'pelanggaran') ? 'active' : 'text-white'; ?>">
                            Kategori Pelanggaran
                        </a>
                    
                    </li>

                    <li class="nav-item mb-2">
                        <a href="dashboard.php?page=jenis_pelanggaran" class="nav-link <?php echo ($currentPage === 'jenis_pelanggaran') ? 'active' : 'text-white'; ?>">
                            Jenis Pelanggaran
                        </a>
                    </li>

                    <li class="nav-item mb-2">
                        <a href="dashboard.php?page=aboutme" class="nav-link <?php echo ($currentPage === 'aboutme') ? 'active' : 'text-white'; ?>">
                            About Me
                        </a>
                    </li>

                    <li><hr class="nav-divider"></li>

                    <li class="nav-item">
                        <a href="logout.php" class="nav-link logout">Logout</a>
                    </li>
                </ul>
                <p class="sidebar-footer">UKK 2026</p>
            </aside>

            <main class="main-content">
                <?php if ($pageFile === null): ?>
                    <div class="dashboard-content">
                        <header class="page-heading">
                            <p class="eyebrow">Ringkasan sekolah</p>
                            <h2>Dashboard</h2>
                            <p>Selamat datang di Sistem Informasi Sekolah UKK-2026.</p>
                        </header>

                        <section class="stats-grid" aria-label="Ringkasan data aktif">
                            <a class="stat-card" href="dashboard.php?page=datasiswa">
                                <span class="stat-label">Data Siswa</span>
                                <span class="stat-value"><?= number_format((int)$ringkasan['siswa']) ?></span>
                                <span class="stat-note">Siswa aktif</span>
                            </a>
                            <a class="stat-card" href="dashboard.php?page=dataguru">
                                <span class="stat-label">Data Guru</span>
                                <span class="stat-value"><?= number_format((int)$ringkasan['guru']) ?></span>
                                <span class="stat-note">Guru aktif</span>
                            </a>
                            <div class="stat-card">
                                <span class="stat-label">Rekap Poin Pelanggaran</span>
                                <span class="stat-value"><?= number_format((int)$ringkasan['total_poin']) ?></span>
                                <span class="stat-note">Total poin dari pelanggaran tercatat</span>
                            </div>
                        </section>

                        <section class="recap-grid" aria-label="Grafik dan rekap poin pelanggaran">
                            <div class="recap-panel">
                                <h3>Grafik Poin Pelanggaran</h3>
                                <p class="recap-description">Poin tercatat per bulan selama enam bulan terakhir.</p>
                                <div class="points-chart" role="img" aria-label="Grafik batang poin pelanggaran per bulan">
                                    <?php foreach ($bulanGrafik as $bulan): ?>
                                        <?php
                                        $tinggiBatang = $bulan['poin'] > 0
                                            ? max(4, (int)round(($bulan['poin'] / $poinTertinggiBulanan) * 100))
                                            : 0;
                                        ?>
                                        <div
                                            class="chart-month"
                                            aria-label="<?= htmlspecialchars($bulan['label'] . ': ' . number_format($bulan['poin']) . ' poin dari ' . number_format($bulan['jumlah']) . ' pelanggaran', ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            <span class="chart-value"><?= number_format($bulan['poin']) ?></span>
                                            <div class="chart-bar-track">
                                                <div class="chart-bar" style="height: <?= $tinggiBatang ?>%"></div>
                                            </div>
                                            <span class="chart-label"><?= htmlspecialchars($bulan['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="recap-panel">
                                <h3>Rekap Poin Siswa</h3>
                                <p class="recap-description">Lima siswa dengan akumulasi poin tertinggi.</p>
                                <div class="recap-table-wrap">
                                    <table class="recap-table">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Nama Siswa</th>
                                            <th>Pelanggaran</th>
                                            <th>Poin</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if ($rekapPoin): ?>
                                            <?php foreach ($rekapPoin as $index => $siswaPoin): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($siswaPoin['nama_siswa'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= number_format((int)$siswaPoin['jumlah_pelanggaran']) ?></td>
                                                    <td><strong><?= number_format((int)$siswaPoin['total_poin']) ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td class="recap-empty" colspan="4">Belum ada data poin pelanggaran.</td>
                                            </tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>

                        <section class="quick-panel">
                            <h3>Akses cepat</h3>
                            <p>Pilih menu yang ingin Anda kelola.</p>
                            <div class="quick-links">
                                <a class="quick-link" href="dashboard.php?page=dataguru">Data Guru</a>
                                <a class="quick-link" href="dashboard.php?page=datasiswa">Data Siswa</a>
                                <a class="quick-link" href="dashboard.php?page=datakelas">Data Kelas</a>
                                <a class="quick-link" href="dashboard.php?page=penempatan_siswa">Penempatan Siswa</a>
                            </div>
                        </section>
                    </div>
                <?php else: ?>
                    <iframe class="content-frame"
                        src="<?php echo $currentPage === 'laporan' ? 'laporan.php?embedded=1' : $pageFile; ?>"
                        title="Content page"></iframe>
                <?php endif; ?>
            </main>
    </div>
</body>

</html>