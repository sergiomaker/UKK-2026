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

function keteranganTampilan($nilai)
{
    $keterangan = trim((string)$nilai);

    return $keterangan === '' || strpos($keterangan, 'DEMO:') === 0
        ? '-'
        : $keterangan;
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
$laporanDetail = [];
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
                                CASE
                                    WHEN k.nama LIKE CONCAT(k.tingkat, ' %') THEN k.nama
                                    ELSE CONCAT_WS(' ', k.tingkat, k.nama)
                                END AS nama_kelas
                         FROM t_kelas_siswa ks
                         INNER JOIN t_siswa s ON s.id = ks.siswa_id AND s.status_aktif = 1
                         INNER JOIN t_kelas k
                            ON k.id = ks.kelas_id AND k.status_aktif = 1
                         INNER JOIN t_tahun_ajaran ta
                            ON ta.id = ks.tahun_ajaran_id AND ta.status_aktif = 1
                         WHERE ks.siswa_id = ?
                           AND ks.tahun_ajaran_id = ?
                           AND ks.status_aktif = 1
                         LIMIT 1"
                    );
                    $stmt->bind_param('ii', $siswaId, $tahunAjaranId);
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
                        $error = 'Siswa harus aktif dan sudah ditempatkan pada tahun ajaran ini, serta jenis pelanggaran harus aktif.';
                    } else {
                        $namaSiswa = $siswaTerpilih['nama_siswa'];
                        $kelasId = (int)$siswaTerpilih['kelas_id'];
                        $namaKelas = $siswaTerpilih['nama_kelas'];
                        $guruId = (int)$guru['id'];
                        $namaPelanggaran = $pelanggaranTerpilih['nama'];
                        $kategoriId = (int)$pelanggaranTerpilih['pelanggaran_kategori_id'];
                        $poin = (int)$pelanggaranTerpilih['poin'];
                        $namaGuru = $guru['nama'];
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
                         WHERE ps.id = ? AND ps.tahun_ajaran_id = ?
                           AND (
                               ps.guru_id IN (
                                   SELECT id FROM t_guru
                                   WHERE user_id = ? AND status_aktif = 1
                               )
                               OR EXISTS (
                                   SELECT 1
                                   FROM t_wali_kelas wk
                                   WHERE wk.kelas_id = ps.kelas_id
                                     AND wk.tahun_ajaran_id = ps.tahun_ajaran_id
                                     AND wk.status_aktif = 1
                                     AND wk.guru_id IN (
                                         SELECT id FROM t_guru
                                         WHERE user_id = ? AND status_aktif = 1
                                     )
                               )
                           )
                         LIMIT 1"
                    );
                    $stmt->bind_param(
                        'iiii',
                        $pelanggaranSiswaId,
                        $tahunAjaranId,
                        $userId,
                        $userId
                    );
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
                    CASE
                        WHEN k.nama LIKE CONCAT(k.tingkat, ' %') THEN k.nama
                        ELSE CONCAT_WS(' ', k.tingkat, k.nama)
                    END AS nama_tampil,
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
                    CASE
                        WHEN k.nama LIKE CONCAT(k.tingkat, ' %') THEN k.nama
                        ELSE CONCAT_WS(' ', k.tingkat, k.nama)
                    END AS nama_kelas
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
            "SELECT ps.id, ps.tanggal, s.nis, ps.nama_siswa, ps.nama_kelas,
                    ps.nama_pelanggaran, ps.poin, ps.status, ps.tindakan,
                    ps.keterangan, ps.nama_guru
             FROM t_pelanggaran_siswa ps
             LEFT JOIN t_siswa s ON s.id = ps.siswa_id
             WHERE ps.tahun_ajaran_id = ?
               AND (
                   ps.guru_id IN (
                       SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
                   )
                   OR EXISTS (
                       SELECT 1
                       FROM t_wali_kelas wk
                       WHERE wk.kelas_id = ps.kelas_id
                         AND wk.tahun_ajaran_id = ps.tahun_ajaran_id
                         AND wk.status_aktif = 1
                         AND wk.guru_id IN (
                             SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
                         )
                   )
               )
             ORDER BY ps.tanggal DESC, ps.id DESC
             LIMIT 5
             "
        );
        $stmt->bind_param('iii', $tahunAjaranId, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($baris = $result->fetch_assoc()) {
            $pelanggaranList[] = $baris;
        }

        $stmt = $koneksi->prepare(
            "SELECT ps.tanggal, s.nis, ps.nama_siswa, ps.nama_kelas,
                    ps.nama_pelanggaran, ps.poin, ps.nama_guru, ps.keterangan
             FROM t_pelanggaran_siswa ps
             LEFT JOIN t_siswa s ON s.id = ps.siswa_id
             WHERE ps.tahun_ajaran_id = ?
               AND ps.guru_id IN (
                   SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
               )
             ORDER BY ps.tanggal DESC, ps.id DESC"
        );
        $stmt->bind_param('ii', $tahunAjaranId, $userId);
        $stmt->execute();
        $laporanDetail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $koneksi->prepare(
            "SELECT ps.id, ps.tanggal, ps.nama_siswa, ps.nama_kelas,
                    ps.nama_pelanggaran, ps.poin, ps.status, ps.tindakan,
                    ps.keterangan
             FROM t_pelanggaran_siswa ps
             WHERE ps.tahun_ajaran_id = ?
               AND ps.status <> 'Ditindaklanjuti'
               AND (
                   ps.guru_id IN (
                       SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
                   )
                   OR EXISTS (
                       SELECT 1
                       FROM t_wali_kelas wk
                       WHERE wk.kelas_id = ps.kelas_id
                         AND wk.tahun_ajaran_id = ps.tahun_ajaran_id
                         AND wk.status_aktif = 1
                         AND wk.guru_id IN (
                             SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
                         )
                   )
               )
             ORDER BY ps.tanggal DESC, ps.id DESC
             "
        );
        $stmt->bind_param('iii', $tahunAjaranId, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($baris = $result->fetch_assoc()) {
            $tindakanList[] = $baris;
        }

        $stmt = $koneksi->prepare(
            "SELECT s.id, s.nis, s.nama AS nama_siswa,
                    CASE
                        WHEN k.nama LIKE CONCAT(k.tingkat, ' %') THEN k.nama
                        ELSE CONCAT_WS(' ', k.tingkat, k.nama)
                    END AS nama_kelas
             FROM t_kelas_siswa ks
             INNER JOIN t_siswa s ON s.id = ks.siswa_id AND s.status_aktif = 1
             INNER JOIN t_kelas k
                ON k.id = ks.kelas_id AND k.status_aktif = 1
             WHERE ks.tahun_ajaran_id = ? AND ks.status_aktif = 1
             ORDER BY k.tingkat, k.nama, s.nama"
        );
        $stmt->bind_param('i', $tahunAjaranId);
        $stmt->execute();
        $result = $stmt->get_result();
        $siswaUntukInput = $result->fetch_all(MYSQLI_ASSOC);

        $stmt = $koneksi->prepare(
            "SELECT s.nama AS nama_siswa, s.nis,
                    CASE
                        WHEN k.nama LIKE CONCAT(k.tingkat, ' %') THEN k.nama
                        ELSE CONCAT_WS(' ', k.tingkat, k.nama)
                    END AS nama_kelas,
                    COUNT(ps.id) AS jumlah_pelanggaran,
                    COALESCE(SUM(ps.poin), 0) AS total_poin
             FROM t_pelanggaran_siswa ps
             INNER JOIN t_siswa s ON s.id = ps.siswa_id AND s.status_aktif = 1
             INNER JOIN t_kelas k ON k.id = ps.kelas_id AND k.status_aktif = 1
             WHERE ps.tahun_ajaran_id = ?
               AND (
                   ps.guru_id IN (
                       SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
                   )
                   OR EXISTS (
                       SELECT 1
                       FROM t_wali_kelas wk
                       WHERE wk.kelas_id = ps.kelas_id
                         AND wk.tahun_ajaran_id = ps.tahun_ajaran_id
                         AND wk.status_aktif = 1
                         AND wk.guru_id IN (
                             SELECT id FROM t_guru WHERE user_id = ? AND status_aktif = 1
                         )
                   )
               )
             GROUP BY s.id, s.nama, s.nis, k.id, k.tingkat, k.nama
             ORDER BY k.tingkat, k.nama, s.nama
             "
        );
        $stmt->bind_param('iii', $tahunAjaranId, $userId, $userId);
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
            flex: 1;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 9px;
            color: #d7e1e5;
            font-size: 15px;
            text-decoration: none;
            transition: background-color .15s ease, color .15s ease;
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            flex: 0 0 20px;
        }

        .nav-link:hover,
        .nav-link:focus-visible {
            color: #fff;
            background: #293943;
        }

        .nav-link.active {
            color: #fff;
            background: #0789f5;
            font-weight: 700;
        }

        .nav-divider {
            height: 1px;
            margin: 12px 8px;
            border: 0;
            background: #35434b;
        }

        .nav-link.logout {
            color: #f87171;
        }

        .nav-link.logout:hover,
        .nav-link.logout:focus-visible {
            color: #fca5a5;
            background: #293943;
        }

        .nav-spacer {
            flex: 1;
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

            .nav-spacer {
                display: none;
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
    <script src="pencarian_tabel.js" defer></script>
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
                    data-menu="dashboard" href="guru_dashboard.php?menu=dashboard#dashboard">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M3 10.8 12 3l9 7.8v9.7a.5.5 0 0 1-.5.5h-6v-6h-5v6h-6a.5.5 0 0 1-.5-.5z"/>
                    </svg>
                    <span>Dashboard</span>
                </a></li>
                <li><a class="nav-link <?= $menuAktif === 'catat-pelanggaran' ? 'active' : '' ?>"
                    data-menu="catat-pelanggaran" href="guru_dashboard.php?menu=catat-pelanggaran#catat-pelanggaran">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M8 4h8l2 2h2v15H4V6h2z"/>
                        <path d="M9 4V2h6v2M12 10v7m-3.5-3.5h7"/>
                    </svg>
                    <span>Catat Pelanggaran</span>
                </a></li>
                <li><a class="nav-link <?= $menuAktif === 'tindakan' ? 'active' : '' ?>"
                    data-menu="tindakan" href="guru_dashboard.php?menu=tindakan#tindakan">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m4 20 8.5-8.5"/>
                        <path d="m13.5 4 6.5 6.5-3 3L10.5 7z"/>
                        <path d="m3 21 3-3"/>
                    </svg>
                    <span>Tindakan</span>
                </a></li>
                <li><a class="nav-link <?= $menuAktif === 'rekap-poin' ? 'active' : '' ?>"
                    data-menu="rekap-poin" href="guru_dashboard.php?menu=rekap-poin#rekap-poin">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M4 13h4v8H4zm6-6h4v14h-4zm6-5h4v19h-4z"/>
                    </svg>
                    <span>Rekap Poin</span>
                </a></li>
                <li><a class="nav-link <?= $menuAktif === 'riwayat' ? 'active' : '' ?>"
                    data-menu="riwayat" href="guru_dashboard.php?menu=riwayat#riwayat">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 7v5l3 2"/>
                    </svg>
                    <span>Riwayat</span>
                </a></li>
                <li><a class="nav-link <?= $menuAktif === 'laporan' ? 'active' : '' ?>"
                    data-menu="laporan" href="guru_dashboard.php?menu=laporan#laporan">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                        <path d="M8 7h8M8 11h8"/>
                    </svg>
                    <span>Laporan Pelanggaran</span>
                </a></li>
                <li class="nav-spacer" aria-hidden="true"></li>
                <li><hr class="nav-divider"></li>
                <li><a class="nav-link logout" href="logout.php">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M10 17l5-5-5-5m5 5H3"/>
                        <path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>
                    </svg>
                    <span>Logout</span>
                </a></li>
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
                                            <td><?= aman($kelas['nama_tampil']) ?></td>
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
                                            <td><?= aman($siswa['nama_kelas']) ?></td>
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
                                <input class="full" type="search" id="cari-siswa-pelanggaran"
                                    placeholder="Cari nama, NIS, atau kelas siswa (contoh: XII 1)..."
                                    aria-label="Cari nama, NIS, atau kelas siswa"
                                    autocomplete="off">
                                <select class="full" name="siswa_id" id="siswa-pelanggaran" required>
                                    <option value="">Pilih siswa (<?= count($siswaUntukInput) ?> tersedia)</option>
                                    <?php foreach ($siswaUntukInput as $siswa): ?>
                                        <option value="<?= (int)$siswa['id'] ?>">
                                            <?= aman($siswa['nis'] . ' - ' . $siswa['nama_siswa'] . ' (' . $siswa['nama_kelas'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="muted full" id="hasil-cari-siswa" aria-live="polite">
                                    <?= count($siswaUntukInput) ?> siswa tersedia. Cari nama, NIS, atau kelas, misalnya XII 1.
                                </p>
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
                    <h3>Laporan Pelanggaran</h3>
                    <p class="muted">
                        Detail seluruh pelanggaran yang Anda catat pada tahun ajaran <?= aman($tahunAjaran['nama']) ?>.
                    </p>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th>Pelanggaran</th>
                                    <th>Poin</th>
                                    <th>Dicatat Oleh</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($laporanDetail): ?>
                                    <?php foreach ($laporanDetail as $index => $pelanggaran): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= aman($pelanggaran['tanggal']) ?></td>
                                            <td><?= aman($pelanggaran['nis'] ?? '') ?></td>
                                            <td><?= aman($pelanggaran['nama_siswa']) ?></td>
                                            <td><?= aman($pelanggaran['nama_kelas']) ?></td>
                                            <td><?= aman($pelanggaran['nama_pelanggaran']) ?></td>
                                            <td><?= number_format((int)$pelanggaran['poin']) ?></td>
                                            <td><?= aman($pelanggaran['nama_guru']) ?></td>
                                            <td><?= aman(keteranganTampilan($pelanggaran['keterangan'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td class="empty" colspan="9">Belum ada catatan pelanggaran.</td></tr>
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
                    <p class="muted">Rekap poin dari kelas binaan dan catatan pelanggaran yang Anda buat.</p>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th>Jumlah Pelanggaran</th>
                                    <th>Total Poin</th>
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
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td class="empty" colspan="5">Belum ada catatan poin di kelas binaan.</td></tr>
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
                                    <th>Keterangan</th>
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
                                            <td><?= aman(keteranganTampilan($pelanggaran['keterangan'])) ?></td>
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
                                    <tr><td class="empty" colspan="8">Tidak ada pelanggaran yang menunggu tindak lanjut.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel" id="riwayat" data-menu-panel="riwayat">
                    <h3>Riwayat Pelanggaran</h3>
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
                                    <th>Keterangan</th>
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
                                            <td><?= aman(keteranganTampilan($pelanggaran['keterangan'])) ?></td>
                                            <td><?= aman($pelanggaran['tindakan']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td class="empty" colspan="9">Belum ada riwayat pelanggaran.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
<script>
    const pencarianSiswaPelanggaran = document.getElementById('cari-siswa-pelanggaran');
    const pilihanSiswaPelanggaran = document.getElementById('siswa-pelanggaran');
    const hasilCariSiswaPelanggaran = document.getElementById('hasil-cari-siswa');

    if (pencarianSiswaPelanggaran && pilihanSiswaPelanggaran && hasilCariSiswaPelanggaran) {
        const opsiSiswaPelanggaran = Array.from(pilihanSiswaPelanggaran.options).slice(1);
        const normalkanTeksSiswa = (teks) => teks
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('id-ID')
            .trim();

        pencarianSiswaPelanggaran.addEventListener('input', function() {
            const kataKunci = normalkanTeksSiswa(this.value);
            const kataPencarian = kataKunci.split(/\s+/).filter(Boolean);
            let jumlahCocok = 0;

            opsiSiswaPelanggaran.forEach(function(opsi) {
                const teksOpsi = normalkanTeksSiswa(opsi.textContent);
                const kataOpsi = teksOpsi.split(/[^\p{L}\p{N}]+/u).filter(Boolean);
                const cocok = kataPencarian.every(function(kata) {
                    return kataOpsi.some(function(kataOpsiItem) {
                        if (/^\d+$/.test(kata)) {
                            return kataOpsiItem === kata ||
                                (kata.length >= 3 && kataOpsiItem.startsWith(kata));
                        }
                        return kataOpsiItem.includes(kata);
                    });
                });
                opsi.hidden = !cocok;
                if (cocok) {
                    jumlahCocok += 1;
                }
            });

            if (pilihanSiswaPelanggaran.selectedOptions[0]?.hidden) {
                pilihanSiswaPelanggaran.value = '';
            }

            hasilCariSiswaPelanggaran.textContent = kataKunci
                ? (jumlahCocok > 0
                    ? `${jumlahCocok} siswa cocok. Pilih siswa dari daftar.`
                    : 'Siswa tidak ditemukan. Coba kata kunci lain.')
                : `${opsiSiswaPelanggaran.length} siswa tersedia. Ketik untuk menyaring daftar.`;
        });
    }

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
