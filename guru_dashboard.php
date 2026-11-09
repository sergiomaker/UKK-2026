<?php
require_once __DIR__ . '/cek_session.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'guru') {
    header('Location: dashboard.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
mysqli_set_charset($koneksi, 'utf8mb4');

function aman($nilai)
{
    return htmlspecialchars((string)$nilai, ENT_QUOTES, 'UTF-8');
}

$error = '';
$pesan = '';
$guru = null;
$tahunAjaran = null;
$ringkasan = ['jumlah_kelas' => 0, 'jumlah_siswa' => 0, 'jumlah_pelanggaran' => 0, 'total_poin' => 0];
$kelasList = [];
$siswaList = [];
$pelanggaranList = [];
$tindakanList = [];
$jenisPelanggaran = [];
$siswaUntukInput = [];
$laporanSiswa = [];
$jumlahDataDemo = 0;
$menuAktif = $_GET['menu'] ?? 'dashboard';
$menuDiizinkan = [
    'dashboard', 'kelas-binaan', 'daftar-siswa', 'catat-pelanggaran',
    'tindakan', 'riwayat', 'laporan', 'rekap-poin',
];
if (!in_array($menuAktif, $menuDiizinkan, true)) {
    $menuAktif = 'dashboard';
}
$judulMenu = [
    'dashboard' => 'Dashboard Guru',
    'kelas-binaan' => 'Kelas Binaan',
    'daftar-siswa' => 'Data Siswa',
    'catat-pelanggaran' => 'Catat Pelanggaran',
    'tindakan' => 'Tindak Lanjut Pelanggaran',
    'riwayat' => 'Riwayat Pelanggaran',
    'laporan' => 'Laporan Pelanggaran',
    'rekap-poin' => 'Rekap Poin',
];
$_SESSION['csrf_guru_dashboard'] ??= bin2hex(random_bytes(32));

try {
    $stmt = $koneksi->prepare(
        "SELECT id, nip, nama
         FROM t_guru
         WHERE user_id = ? AND status_aktif = 1
         ORDER BY id"
    );
    $userId = (int)$_SESSION['id'];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $profilGuru = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $guru = $profilGuru[0] ?? null;

    if (!$guru) {
        throw new RuntimeException('Akun guru tidak ditemukan atau tidak aktif.');
    }
    $guru['nama'] = implode(' / ', array_column($profilGuru, 'nama'));

    $resultTahun = $koneksi->query(
        "SELECT id, nama, tanggal_mulai, tanggal_selesai
         FROM t_tahun_ajaran
         WHERE status_aktif = 1
         ORDER BY (CURDATE() BETWEEN tanggal_mulai AND tanggal_selesai) DESC,
                  tanggal_mulai DESC, id DESC
         LIMIT 1"
    );
    $tahunAjaran = $resultTahun->fetch_assoc();

    if ($tahunAjaran) {
        $tahunAjaranId = (int)$tahunAjaran['id'];

        if (isset($_POST['catat_pelanggaran']) || isset($_POST['simpan_tindakan'])) {
            $token = $_POST['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_guru_dashboard'], $token)) {
                $error = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
            } elseif (isset($_POST['catat_pelanggaran'])) {
                $siswaId = (int)($_POST['siswa_id'] ?? 0);
                $pelanggaranId = (int)($_POST['pelanggaran_id'] ?? 0);
                $tanggal = trim($_POST['tanggal'] ?? '');
                $keterangan = trim($_POST['keterangan'] ?? '');
                $tanggalValid = DateTime::createFromFormat('!Y-m-d', $tanggal);

                if (
                    $siswaId <= 0 || $pelanggaranId <= 0 ||
                    !$tanggalValid || $tanggalValid->format('Y-m-d') !== $tanggal ||
                    $tanggal > date('Y-m-d') || strlen($keterangan) > 2000
                ) {
                    $error = 'Lengkapi tanggal, siswa, dan jenis pelanggaran dengan benar.';
                } else {
                    $stmt = $koneksi->prepare(
                        "SELECT s.nama AS nama_siswa, k.id AS kelas_id,
                                wk.guru_id, g.nama AS nama_guru,
                                CONCAT_WS(' ', k.tingkat, k.nama) AS nama_kelas
                         FROM t_kelas_siswa ks
                         INNER JOIN t_siswa s ON s.id = ks.siswa_id AND s.status_aktif = 1
                         INNER JOIN t_kelas k ON k.id = ks.kelas_id
                         INNER JOIN t_wali_kelas wk
                            ON wk.kelas_id = ks.kelas_id
                           AND wk.tahun_ajaran_id = ks.tahun_ajaran_id
                           AND wk.guru_id IN (
                               SELECT id FROM t_guru
                               WHERE user_id = ? AND status_aktif = 1
                           )
                           AND wk.status_aktif = 1
                         INNER JOIN t_guru g ON g.id = wk.guru_id
                         WHERE ks.siswa_id = ?
                           AND ks.tahun_ajaran_id = ?
                           AND ks.status_aktif = 1
                         LIMIT 1"
                    );
                    $stmt->bind_param('iii', $userId, $siswaId, $tahunAjaranId);
                    $stmt->execute();
                    $siswaTerpilih = $stmt->get_result()->fetch_assoc();

                    $stmt = $koneksi->prepare(
                        "SELECT id, pelanggaran_kategori_id, nama, poin
                         FROM t_pelanggaran
                         WHERE id = ? AND status_aktif = 1
                         LIMIT 1"
                    );
                    $stmt->bind_param('i', $pelanggaranId);
                    $stmt->execute();
                    $pelanggaranTerpilih = $stmt->get_result()->fetch_assoc();

                    if (!$siswaTerpilih || !$pelanggaranTerpilih) {
                        $error = 'Siswa harus berasal dari kelas binaan dan jenis pelanggaran harus aktif.';
                    } else {
                        $namaSiswa = $siswaTerpilih['nama_siswa'];
                        $kelasId = (int)$siswaTerpilih['kelas_id'];
                        $namaKelas = $siswaTerpilih['nama_kelas'];
                        $guruId = (int)$siswaTerpilih['guru_id'];
                        $namaPelanggaran = $pelanggaranTerpilih['nama'];
                        $kategoriId = (int)$pelanggaranTerpilih['pelanggaran_kategori_id'];
                        $poin = (int)$pelanggaranTerpilih['poin'];
                        $namaGuru = $siswaTerpilih['nama_guru'];
                        $tindakan = 'Belum ditindaklanjuti';
                        $status = 'Baru';
                        $stmt = $koneksi->prepare(
                            "INSERT INTO t_pelanggaran_siswa
                                (tahun_ajaran_id, siswa_id, nama_siswa, kelas_id,
                                 nama_kelas, pelanggaran_id, nama_pelanggaran,
                                 pelanggaran_kategori_id, guru_id, nama_guru, tanggal,
                                 keterangan, poin, tindakan, status, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                        );
                        $bindTypes = 'iisisisiisssiss';
                        $stmt->bind_param(
                            $bindTypes,
                            $tahunAjaranId,
                            $siswaId,
                            $namaSiswa,
                            $kelasId,
                            $namaKelas,
                            $pelanggaranId,
                            $namaPelanggaran,
                            $kategoriId,
                            $guruId,
                            $namaGuru,
                            $tanggal,
                            $keterangan,
                            $poin,
                            $tindakan,
                            $status
                        );
                        $stmt->execute();
                        header('Location: guru_dashboard.php?menu=riwayat&pesan=pelanggaran#riwayat');
                        exit;
                    }
                }
            } else {
                $pelanggaranSiswaId = (int)($_POST['pelanggaran_siswa_id'] ?? 0);
                $tindakan = trim($_POST['tindakan'] ?? '');
                if ($pelanggaranSiswaId <= 0 || $tindakan === '' || strlen($tindakan) > 2000) {
                    $error = 'Tindakan wajib diisi dan maksimal 2.000 karakter.';
                } else {
                    $stmt = $koneksi->prepare(
                        "SELECT ps.id
                         FROM t_pelanggaran_siswa ps
                         INNER JOIN t_wali_kelas wk
                            ON wk.kelas_id = ps.kelas_id
                           AND wk.tahun_ajaran_id = ps.tahun_ajaran_id
                           AND wk.guru_id IN (
                               SELECT id FROM t_guru
                               WHERE user_id = ? AND status_aktif = 1
                           )
                           AND wk.status_aktif = 1
                         WHERE ps.id = ? AND ps.tahun_ajaran_id = ?
                         LIMIT 1"
                    );
                    $stmt->bind_param('iii', $userId, $pelanggaranSiswaId, $tahunAjaranId);
                    $stmt->execute();
                    if (!$stmt->get_result()->fetch_assoc()) {
                        $error = 'Catatan tidak ditemukan di kelas binaan Anda.';
                    } else {
                        $stmt = $koneksi->prepare(
                            "UPDATE t_pelanggaran_siswa
                             SET tindakan = ?, status = 'Ditindaklanjuti',
                                 updated_at = NOW()
                             WHERE id = ? AND tahun_ajaran_id = ?"
                        );
                        $stmt->bind_param(
                            'sii',
                            $tindakan,
                            $pelanggaranSiswaId,
                            $tahunAjaranId
                        );
                        $stmt->execute();
                        header('Location: guru_dashboard.php?menu=tindakan&pesan=tindakan#tindakan');
                        exit;
                    }
                }
            }
        }

        $pesan = match ($_GET['pesan'] ?? '') {
            'pelanggaran' => 'Catatan pelanggaran berhasil disimpan.',
            'tindakan' => 'Tindakan berhasil diperbarui.',
            default => '',
        };

        $stmt = $koneksi->prepare(
            "SELECT COUNT(*) AS jumlah_kelas
             FROM t_wali_kelas
            WHERE guru_id IN (
                SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
            ) AND tahun_ajaran_id = ? AND status_aktif = 1"
        );
        $stmt->bind_param('ii', $userId, $tahunAjaranId);
        $stmt->execute();
        $ringkasan['jumlah_kelas'] = (int)$stmt->get_result()->fetch_assoc()['jumlah_kelas'];

        $stmt = $koneksi->prepare(
            "SELECT COUNT(DISTINCT ks.siswa_id) AS jumlah_siswa
             FROM t_kelas_siswa ks
             INNER JOIN t_siswa s ON s.id = ks.siswa_id AND s.status_aktif = 1
             INNER JOIN t_wali_kelas wk
                ON wk.kelas_id = ks.kelas_id
               AND wk.tahun_ajaran_id = ks.tahun_ajaran_id
               AND wk.status_aktif = 1
             WHERE wk.guru_id IN (
                 SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
             )
               AND ks.tahun_ajaran_id = ?
               AND ks.status_aktif = 1"
        );
        $stmt->bind_param('ii', $userId, $tahunAjaranId);
        $stmt->execute();
        $ringkasan['jumlah_siswa'] = (int)$stmt->get_result()->fetch_assoc()['jumlah_siswa'];

        $stmt = $koneksi->prepare(
            "SELECT COUNT(*) AS jumlah_pelanggaran,
                    COALESCE(SUM(poin), 0) AS total_poin
             FROM t_pelanggaran_siswa
             WHERE guru_id IN (
                 SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
             ) AND tahun_ajaran_id = ?"
        );
        $stmt->bind_param('ii', $userId, $tahunAjaranId);
        $stmt->execute();
        $rekapPelanggaran = $stmt->get_result()->fetch_assoc();
        $ringkasan['jumlah_pelanggaran'] = (int)$rekapPelanggaran['jumlah_pelanggaran'];
        $ringkasan['total_poin'] = (int)$rekapPelanggaran['total_poin'];

        $stmt = $koneksi->prepare(
            "SELECT k.nama, k.tingkat, k.jurusan,
                    wk.tanggal_mulai, wk.tanggal_selesai,
                    (
                        SELECT COUNT(DISTINCT ks.siswa_id)
                        FROM t_kelas_siswa ks
                        INNER JOIN t_siswa s ON s.id = ks.siswa_id AND s.status_aktif = 1
                        WHERE ks.kelas_id = k.id
                          AND ks.tahun_ajaran_id = wk.tahun_ajaran_id
                          AND ks.status_aktif = 1
                    ) AS jumlah_siswa
             FROM t_wali_kelas wk
             INNER JOIN t_kelas k ON k.id = wk.kelas_id
             WHERE wk.guru_id IN (
                 SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
             )
               AND wk.tahun_ajaran_id = ?
               AND wk.status_aktif = 1
             ORDER BY k.tingkat, k.nama
             "
        );
        $stmt->bind_param('ii', $userId, $tahunAjaranId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($baris = $result->fetch_assoc()) {
            $kelasList[] = $baris;
        }

        $stmt = $koneksi->prepare(
            "SELECT DISTINCT s.nis, s.nama AS nama_siswa, s.jenis_kelamin,
                    k.nama AS nama_kelas, k.tingkat
             FROM t_wali_kelas wk
             INNER JOIN t_kelas_siswa ks
                ON ks.kelas_id = wk.kelas_id
               AND ks.tahun_ajaran_id = wk.tahun_ajaran_id
               AND ks.status_aktif = 1
             INNER JOIN t_siswa s ON s.id = ks.siswa_id AND s.status_aktif = 1
             INNER JOIN t_kelas k ON k.id = wk.kelas_id
             WHERE wk.guru_id IN (
                 SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
             )
               AND wk.tahun_ajaran_id = ?
               AND wk.status_aktif = 1
             ORDER BY k.tingkat, k.nama, s.nama
             "
        );
        $stmt->bind_param('ii', $userId, $tahunAjaranId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($baris = $result->fetch_assoc()) {
            $siswaList[] = $baris;
        }

        $stmt = $koneksi->prepare(
            "SELECT ps.id, ps.tanggal, ps.nama_siswa, ps.nama_kelas,
                    ps.nama_pelanggaran, ps.poin, ps.status, ps.tindakan
             FROM t_pelanggaran_siswa ps
             INNER JOIN t_wali_kelas wk
                ON wk.kelas_id = ps.kelas_id
               AND wk.tahun_ajaran_id = ps.tahun_ajaran_id
               AND wk.guru_id IN (
                   SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
               )
               AND wk.status_aktif = 1
             WHERE ps.tahun_ajaran_id = ?
             ORDER BY ps.tanggal DESC, ps.id DESC
             "
        );
        $stmt->bind_param('ii', $userId, $tahunAjaranId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($baris = $result->fetch_assoc()) {
            $pelanggaranList[] = $baris;
        }

        $stmt = $koneksi->prepare(
            "SELECT COUNT(*) AS jumlah
             FROM t_pelanggaran_siswa
             WHERE tahun_ajaran_id = ? AND keterangan LIKE 'DEMO:%'"
        );
        $stmt->bind_param('i', $tahunAjaranId);
        $stmt->execute();
        $jumlahDataDemo = (int)$stmt->get_result()->fetch_assoc()['jumlah'];

        $stmt = $koneksi->prepare(
            "SELECT ps.id, ps.tanggal, ps.nama_siswa, ps.nama_kelas,
                    ps.nama_pelanggaran, ps.poin, ps.status, ps.tindakan
             FROM t_pelanggaran_siswa ps
             INNER JOIN t_wali_kelas wk
                ON wk.kelas_id = ps.kelas_id
               AND wk.tahun_ajaran_id = ps.tahun_ajaran_id
               AND wk.guru_id IN (
                   SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
               )
               AND wk.status_aktif = 1
             WHERE ps.tahun_ajaran_id = ?
               AND ps.status <> 'Ditindaklanjuti'
             ORDER BY ps.tanggal DESC, ps.id DESC
             "
        );
        $stmt->bind_param('ii', $userId, $tahunAjaranId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($baris = $result->fetch_assoc()) {
            $tindakanList[] = $baris;
        }

        $stmt = $koneksi->prepare(
            "SELECT s.id, s.nis, s.nama AS nama_siswa, k.nama AS nama_kelas,
                    k.tingkat
             FROM t_kelas_siswa ks
             INNER JOIN t_siswa s ON s.id = ks.siswa_id AND s.status_aktif = 1
             INNER JOIN t_kelas k ON k.id = ks.kelas_id
             INNER JOIN t_wali_kelas wk
                ON wk.kelas_id = ks.kelas_id
               AND wk.tahun_ajaran_id = ks.tahun_ajaran_id
               AND wk.guru_id IN (
                   SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
               )
               AND wk.status_aktif = 1
             WHERE ks.tahun_ajaran_id = ? AND ks.status_aktif = 1
             ORDER BY k.tingkat, k.nama, s.nama"
        );
        $stmt->bind_param('ii', $userId, $tahunAjaranId);
        $stmt->execute();
        $result = $stmt->get_result();
        $siswaUntukInput = $result->fetch_all(MYSQLI_ASSOC);

        $stmt = $koneksi->prepare(
            "SELECT s.nama AS nama_siswa, s.nis,
                    CONCAT_WS(' ', k.tingkat, k.nama) AS nama_kelas,
                    COUNT(ps.id) AS jumlah_pelanggaran,
                    COALESCE(SUM(ps.poin), 0) AS total_poin,
                    MAX(ps.keterangan LIKE 'DEMO:%') AS is_demo
             FROM t_kelas_siswa ks
             INNER JOIN t_siswa s ON s.id = ks.siswa_id AND s.status_aktif = 1
             INNER JOIN t_kelas k ON k.id = ks.kelas_id
             INNER JOIN t_wali_kelas wk
                ON wk.kelas_id = ks.kelas_id
               AND wk.tahun_ajaran_id = ks.tahun_ajaran_id
               AND wk.guru_id IN (
                   SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
               )
               AND wk.status_aktif = 1
             LEFT JOIN t_pelanggaran_siswa ps
                ON ps.siswa_id = s.id
               AND ps.kelas_id = k.id
               AND ps.tahun_ajaran_id = ks.tahun_ajaran_id
             WHERE ks.tahun_ajaran_id = ? AND ks.status_aktif = 1
             GROUP BY s.id, s.nama, s.nis, k.id, k.tingkat, k.nama
             HAVING COUNT(ps.id) > 0
             ORDER BY k.tingkat, k.nama, s.nama
             "
        );
        $stmt->bind_param('ii', $userId, $tahunAjaranId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($baris = $result->fetch_assoc()) {
            $laporanSiswa[] = $baris;
        }

        $resultJenis = $koneksi->query(
            "SELECT id, nama, poin
             FROM t_pelanggaran
             WHERE status_aktif = 1
             ORDER BY nama"
        );
        $jenisPelanggaran = $resultJenis->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception | RuntimeException $e) {
    error_log('guru_dashboard.php: ' . $e->getMessage());
    $error = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Data dashboard tidak dapat dimuat. Periksa database dan coba lagi.';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru</title>
    <style>
        :root {
            font-family: Arial, sans-serif;
            color: #17232c;
            background: #f1f5f9;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
        }

        .layout {
            display: grid;
            grid-template-columns: 252px minmax(0, 1fr);
            min-height: 100vh;
        }

        .sidebar {
            position: sticky;
            top: 0;
            display: flex;
            height: 100vh;
            flex-direction: column;
            padding: 24px 18px;
            color: white;
            background: #17232c;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 2px 4px 34px;
        }

        .brand-mark {
            display: grid;
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            place-items: center;
            border-radius: 10px;
            background: #16836b;
            color: #fff;
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
            background: #1769aa;
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

        main {
            min-width: 0;
            padding: 30px clamp(18px, 4vw, 56px);
        }

        header {
            margin-bottom: 24px;
        }

        header h2 {
            margin: 0 0 8px;
            color: #1565c0;
        }

        .muted {
            color: #64748b;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .card,
        .panel {
            padding: 20px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 8px #0000000d;
        }

        .card span {
            display: block;
        }

        .card-label {
            color: #64748b;
            font-size: 14px;
        }

        .card-value {
            margin-top: 8px;
            color: #1565c0;
            font-size: 28px;
            font-weight: bold;
        }

        .panel {
            margin-bottom: 20px;
        }

        .panel h3 {
            margin-top: 0;
            color: #1d4ed8;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        th,
        td {
            padding: 11px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }

        th {
            background: #2563eb;
            color: white;
        }

        .alert {
            padding: 14px;
            border-radius: 7px;
            background: #fee2e2;
            color: #991b1b;
        }

        .empty {
            color: #64748b;
            text-align: center;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .form-grid .full {
            grid-column: 1 / -1;
        }

        input,
        select,
        textarea,
        .button {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font: inherit;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        .button {
            border: 0;
            background: #1769aa;
            color: white;
            cursor: pointer;
            font-weight: 700;
        }

        .button:hover {
            background: #12568d;
        }

        .button-small {
            width: auto;
            padding: 7px 10px;
        }

        .inline-action {
            display: flex;
            min-width: 280px;
            gap: 6px;
        }

        .inline-action input {
            min-width: 140px;
        }

        .alert-success {
            margin-bottom: 16px;
            padding: 14px;
            border-radius: 7px;
            background: #dcfce7;
            color: #166534;
        }

        .alert-demo {
            margin-bottom: 16px;
            padding: 14px;
            border: 1px solid #f59e0b;
            border-radius: 7px;
            background: #fef3c7;
            color: #92400e;
        }

        .panel,
        .cards,
        header[id="dashboard"] {
            scroll-margin-top: 20px;
        }

        main[data-active-menu] [data-menu-panel] {
            display: none;
        }

        main[data-active-menu="dashboard"] [data-menu-panel="dashboard"],
        main[data-active-menu="kelas-binaan"] [data-menu-panel="kelas-binaan"],
        main[data-active-menu="daftar-siswa"] [data-menu-panel="daftar-siswa"],
        main[data-active-menu="catat-pelanggaran"] [data-menu-panel="catat-pelanggaran"],
        main[data-active-menu="tindakan"] [data-menu-panel="tindakan"],
        main[data-active-menu="riwayat"] [data-menu-panel="riwayat"],
        main[data-active-menu="laporan"] [data-menu-panel="laporan"],
        main[data-active-menu="rekap-poin"] [data-menu-panel="rekap-poin"] {
            display: block;
        }

        html {
            scroll-behavior: smooth;
        }

        @media print {
            body.print-report,
            body.print-report .layout {
                display: block;
                background: #fff;
            }

            body.print-report .sidebar,
            body.print-report main > header,
            body.print-report main > section:not(#laporan),
            body.print-report #laporan .no-print {
                display: none !important;
            }

            body.print-report main {
                padding: 0;
            }

            body.print-report #laporan {
                box-shadow: none;
            }
        }

        @media (max-width: 850px) {
            .layout {
                grid-template-columns: 220px minmax(0, 1fr);
            }

            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .layout {
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

            main {
                padding: 22px 16px;
            }

            .cards {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .card,
            .panel {
                padding: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">AS</div>
                <div>
                    <h1>Guru Sekolah</h1>
                    <p><?= aman($guru['nama'] ?? $_SESSION['name'] ?? 'Guru') ?></p>
                </div>
            </div>

            <p class="nav-label">Menu utama</p>
            <ul class="nav-list">
                <li><a class="nav-link <?= $menuAktif === 'dashboard' ? 'active' : '' ?>"
                    data-menu="dashboard" href="guru_dashboard.php?menu=dashboard#dashboard">Dashboard</a></li>
                <li><a class="nav-link <?= $menuAktif === 'kelas-binaan' ? 'active' : '' ?>"
                    data-menu="kelas-binaan" href="guru_dashboard.php?menu=kelas-binaan#kelas-binaan">Kelas Binaan</a></li>
                <li><a class="nav-link <?= $menuAktif === 'daftar-siswa' ? 'active' : '' ?>"
                    data-menu="daftar-siswa" href="guru_dashboard.php?menu=daftar-siswa#daftar-siswa">Data Siswa</a></li>
                <li><a class="nav-link <?= $menuAktif === 'catat-pelanggaran' ? 'active' : '' ?>"
                    data-menu="catat-pelanggaran" href="guru_dashboard.php?menu=catat-pelanggaran#catat-pelanggaran">Catat Pelanggaran</a></li>
                <li><a class="nav-link <?= $menuAktif === 'tindakan' ? 'active' : '' ?>"
                    data-menu="tindakan" href="guru_dashboard.php?menu=tindakan#tindakan">Tindakan</a></li>
                <li><a class="nav-link <?= $menuAktif === 'riwayat' ? 'active' : '' ?>"
                    data-menu="riwayat" href="guru_dashboard.php?menu=riwayat#riwayat">Riwayat Pelanggaran</a></li>
                <li><a class="nav-link <?= $menuAktif === 'laporan' ? 'active' : '' ?>"
                    data-menu="laporan" href="guru_dashboard.php?menu=laporan#laporan">Laporan</a></li>
                <li><a class="nav-link <?= $menuAktif === 'rekap-poin' ? 'active' : '' ?>"
                    data-menu="rekap-poin" href="guru_dashboard.php?menu=rekap-poin#rekap-poin">Rekap Poin</a></li>
                <li><hr class="nav-divider"></li>
                <li><a class="nav-link logout" href="logout.php">Logout</a></li>
            </ul>
            <p class="sidebar-footer">UKK 2026</p>
        </aside>

        <main data-active-menu="<?= aman($menuAktif) ?>">
            <header id="dashboard">
                <h2><?= aman($judulMenu[$menuAktif]) ?></h2>
                <div class="muted">Guru: <?= aman($guru['nama'] ?? $_SESSION['name'] ?? 'Guru') ?></div>
                <div class="muted">
                    Tahun ajaran <?= aman($tahunAjaran['nama'] ?? 'belum tersedia') ?>
                </div>
            </header>

            <?php if ($error !== ''): ?>
                <div class="alert"><?= aman($error) ?></div>
            <?php endif; ?>
            <?php if ($pesan !== ''): ?>
                <div class="alert-success"><?= aman($pesan) ?></div>
            <?php endif; ?>
            <?php if ($jumlahDataDemo > 0): ?>
                <div class="alert-demo" role="status">
                    PERHATIAN: Ada <?= number_format($jumlahDataDemo) ?> catatan DEMO untuk simulasi.
                    Data ini bukan kejadian nyata dan jangan digunakan sebagai catatan siswa sebenarnya.
                </div>
            <?php endif; ?>
            <?php if ($error === ''): ?>
                <section class="cards" data-menu-panel="dashboard" aria-label="Ringkasan guru">
                    <div class="card">
                        <span class="card-label">Kelas Wali</span>
                        <span class="card-value"><?= number_format($ringkasan['jumlah_kelas']) ?></span>
                    </div>
                    <div class="card">
                        <span class="card-label">Siswa di Kelas Binaan</span>
                        <span class="card-value"><?= number_format($ringkasan['jumlah_siswa']) ?></span>
                    </div>
                    <div class="card">
                        <span class="card-label">Pelanggaran Dicatat</span>
                        <span class="card-value"><?= number_format($ringkasan['jumlah_pelanggaran']) ?></span>
                    </div>
                    <div class="card">
                        <span class="card-label">Total Poin</span>
                        <span class="card-value"><?= number_format($ringkasan['total_poin']) ?></span>
                    </div>
                </section>

                <section class="panel" id="kelas-binaan" data-menu-panel="kelas-binaan">
                    <h3>Kelas Binaan</h3>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Kelas</th>
                                    <th>Jurusan</th>
                                    <th>Jumlah Siswa</th>
                                    <th>Masa Tugas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($kelasList): ?>
                                    <?php foreach ($kelasList as $kelas): ?>
                                        <tr>
                                            <td><?= aman($kelas['tingkat'] . ' ' . $kelas['nama']) ?></td>
                                            <td><?= aman($kelas['jurusan']) ?></td>
                                            <td><?= number_format((int)$kelas['jumlah_siswa']) ?></td>
                                            <td><?= aman($kelas['tanggal_mulai']) ?> s.d. <?= aman($kelas['tanggal_selesai']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td class="empty" colspan="4">Belum ada kelas binaan pada tahun ajaran ini.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel" id="daftar-siswa" data-menu-panel="daftar-siswa">
                    <h3>Daftar Siswa di Kelas Binaan</h3>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($siswaList): ?>
                                    <?php foreach ($siswaList as $index => $siswa): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= aman($siswa['nis']) ?></td>
                                            <td><?= aman($siswa['nama_siswa']) ?></td>
                                            <td><?= aman($siswa['tingkat'] . ' ' . $siswa['nama_kelas']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td class="empty" colspan="4">Belum ada siswa yang ditempatkan di kelas binaan.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel" id="catat-pelanggaran" data-menu-panel="catat-pelanggaran">
                    <h3>Catat Pelanggaran</h3>
                    <?php if (!$siswaUntukInput || !$jenisPelanggaran): ?>
                        <p class="muted">
                            Pencatatan memerlukan siswa yang sudah ditempatkan pada kelas binaan
                            dan jenis pelanggaran aktif dari admin.
                        </p>
                    <?php else: ?>
                        <form method="POST" action="guru_dashboard.php?menu=catat-pelanggaran#catat-pelanggaran">
                            <input type="hidden" name="csrf_token"
                                value="<?= aman($_SESSION['csrf_guru_dashboard']) ?>">
                            <div class="form-grid">
                                <select name="siswa_id" required>
                                    <option value="">Pilih siswa</option>
                                    <?php foreach ($siswaUntukInput as $siswa): ?>
                                        <option value="<?= (int)$siswa['id'] ?>">
                                            <?= aman($siswa['nis'] . ' - ' . $siswa['nama_siswa'] . ' (' . $siswa['tingkat'] . ' ' . $siswa['nama_kelas'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="pelanggaran_id" required>
                                    <option value="">Pilih jenis pelanggaran</option>
                                    <?php foreach ($jenisPelanggaran as $jenis): ?>
                                        <option value="<?= (int)$jenis['id'] ?>">
                                            <?= aman($jenis['nama'] . ' - ' . $jenis['poin'] . ' poin') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="date" name="tanggal" max="<?= aman(date('Y-m-d')) ?>"
                                    value="<?= aman(date('Y-m-d')) ?>" required>
                                <textarea class="full" name="keterangan" maxlength="2000"
                                    placeholder="Keterangan kejadian (opsional)"></textarea>
                                <button class="button full" type="submit" name="catat_pelanggaran">
                                    Simpan Catatan
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="panel" id="laporan" data-menu-panel="laporan">
                    <h3>Laporan Pelanggaran Kelas Binaan</h3>
                    <p class="muted">
                        Ringkasan siswa yang memiliki catatan pada tahun ajaran <?= aman($tahunAjaran['nama']) ?>.
                    </p>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th>Jumlah Pelanggaran</th>
                                    <th>Total Poin</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($laporanSiswa): ?>
                                    <?php foreach ($laporanSiswa as $index => $siswa): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= aman($siswa['nis']) ?></td>
                                            <td><?= aman($siswa['nama_siswa']) ?></td>
                                            <td><?= aman($siswa['nama_kelas']) ?></td>
                                            <td><?= number_format((int)$siswa['jumlah_pelanggaran']) ?></td>
                                            <td><?= number_format((int)$siswa['total_poin']) ?></td>
                                            <td><?= (int)$siswa['is_demo'] === 1 ? 'DEMO' : 'Asli' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td class="empty" colspan="7">Belum ada catatan pelanggaran di kelas binaan.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <button class="button button-small no-print" type="button"
                        onclick="document.body.classList.add('print-report'); window.print();">
                        Cetak Laporan
                    </button>
                </section>

                <section class="panel" id="rekap-poin" data-menu-panel="rekap-poin">
                    <h3>Rekap Poin Siswa</h3>
                    <p class="muted">Rekap poin siswa yang memiliki catatan di kelas binaan.</p>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th>Jumlah Pelanggaran</th>
                                    <th>Total Poin</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($laporanSiswa): ?>
                                    <?php foreach ($laporanSiswa as $index => $rekap): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= aman($rekap['nama_siswa']) ?></td>
                                            <td><?= aman($rekap['nama_kelas']) ?></td>
                                            <td><?= number_format((int)$rekap['jumlah_pelanggaran']) ?></td>
                                            <td><?= number_format((int)$rekap['total_poin']) ?></td>
                                            <td><?= (int)$rekap['is_demo'] === 1 ? 'DEMO' : 'Asli' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td class="empty" colspan="6">Belum ada catatan poin di kelas binaan.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel" id="tindakan" data-menu-panel="tindakan">
                    <h3>Tindak Lanjut Pelanggaran</h3>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th>Pelanggaran</th>
                                    <th>Poin</th>
                                    <th>Status</th>
                                    <th>Tindakan Baru</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($tindakanList): ?>
                                    <?php foreach ($tindakanList as $pelanggaran): ?>
                                        <tr>
                                            <td><?= aman($pelanggaran['tanggal']) ?></td>
                                            <td><?= aman($pelanggaran['nama_siswa']) ?></td>
                                            <td><?= aman($pelanggaran['nama_kelas']) ?></td>
                                            <td><?= aman($pelanggaran['nama_pelanggaran']) ?></td>
                                            <td><?= number_format((int)$pelanggaran['poin']) ?></td>
                                            <td><?= aman($pelanggaran['status']) ?></td>
                                            <td>
                                                <form class="inline-action" method="POST"
                                                    action="guru_dashboard.php?menu=tindakan#tindakan">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= aman($_SESSION['csrf_guru_dashboard']) ?>">
                                                    <input type="hidden" name="pelanggaran_siswa_id"
                                                        value="<?= (int)$pelanggaran['id'] ?>">
                                                    <input type="text" name="tindakan" maxlength="2000"
                                                        placeholder="Tulis tindakan" required>
                                                    <button class="button button-small" type="submit"
                                                        name="simpan_tindakan">Simpan</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td class="empty" colspan="7">Tidak ada pelanggaran yang menunggu tindak lanjut.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel" id="riwayat" data-menu-panel="riwayat">
                    <h3>Riwayat Pelanggaran Kelas Binaan</h3>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th>Pelanggaran</th>
                                    <th>Poin</th>
                                    <th>Status</th>
                                    <th>Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($pelanggaranList): ?>
                                    <?php foreach ($pelanggaranList as $index => $pelanggaran): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= aman($pelanggaran['tanggal']) ?></td>
                                            <td><?= aman($pelanggaran['nama_siswa']) ?></td>
                                            <td><?= aman($pelanggaran['nama_kelas']) ?></td>
                                            <td><?= aman($pelanggaran['nama_pelanggaran']) ?></td>
                                            <td><?= number_format((int)$pelanggaran['poin']) ?></td>
                                            <td><?= aman($pelanggaran['status']) ?></td>
                                            <td><?= aman($pelanggaran['tindakan']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td class="empty" colspan="8">Belum ada riwayat pelanggaran di kelas binaan.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
<script>
    window.addEventListener('afterprint', function () {
        document.body.classList.remove('print-report');
    });

    function activateMenu(menu) {
        const links = document.querySelectorAll('.nav-link[data-menu]');
        links.forEach(function (link) {
            link.classList.toggle('active', link.dataset.menu === menu);
        });
    }

    document.querySelectorAll('.nav-link[data-menu]').forEach(function (link) {
        link.addEventListener('click', function () {
            activateMenu(this.dataset.menu);
        });
    });

    window.addEventListener('hashchange', function () {
        activateMenu((window.location.hash || '#dashboard').slice(1));
    });

    if (window.location.hash) {
        activateMenu(window.location.hash.slice(1));
    }
</script>
</body>
</html>
