<?php
require_once __DIR__ . '/cek_session.php';

mysqli_set_charset($koneksi, 'utf8mb4');

function aman($nilai)
{
    return htmlspecialchars((string)$nilai, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_penempatan'])) {
    $_SESSION['csrf_penempatan'] = bin2hex(random_bytes(32));
}

$pesan = '';
$error = '';
$tahunAjaranId = filter_input(INPUT_GET, 'tahun_ajaran', FILTER_VALIDATE_INT);
$tahunAjaranId = $tahunAjaranId === false || $tahunAjaranId === null ? 0 : $tahunAjaranId;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['simpan'])) {
    $tahunAjaranId = filter_input(INPUT_POST, 'tahun_ajaran_id', FILTER_VALIDATE_INT);
    $siswaId = filter_input(INPUT_POST, 'siswa_id', FILTER_VALIDATE_INT);
    $kelasId = filter_input(INPUT_POST, 'kelas_id', FILTER_VALIDATE_INT);
    $tanggalMulaiInput = $_POST['tanggal_mulai'] ?? '';
    $tanggalMulai = is_string($tanggalMulaiInput) ? trim($tanggalMulaiInput) : '';
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_penempatan'], $token)) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman, lalu coba lagi.';
    } elseif (!$tahunAjaranId || !$siswaId || !$kelasId) {
        $error = 'Pilih tahun ajaran, siswa, dan kelas dengan benar.';
    } else {
        $tanggal = DateTime::createFromFormat('!Y-m-d', $tanggalMulai);
        $tanggalValid = $tanggal && $tanggal->format('Y-m-d') === $tanggalMulai;

        if (!$tanggalValid) {
            $error = 'Tanggal mulai tidak valid.';
        } else {
            $transaksiAktif = false;
            try {
                $stmt = $koneksi->prepare(
                    "SELECT tanggal_mulai, tanggal_selesai
                     FROM t_tahun_ajaran
                     WHERE id = ? AND status_aktif = 1"
                );
                $stmt->bind_param('i', $tahunAjaranId);
                $stmt->execute();
                $tahunAjaran = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $stmt = $koneksi->prepare(
                    "SELECT id FROM t_siswa WHERE id = ? AND status_aktif = 1"
                );
                $stmt->bind_param('i', $siswaId);
                $stmt->execute();
                $siswaAktif = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $stmt = $koneksi->prepare(
                    "SELECT id FROM t_kelas WHERE id = ? AND status_aktif = 1"
                );
                $stmt->bind_param('i', $kelasId);
                $stmt->execute();
                $kelasAktif = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$tahunAjaran || !$siswaAktif || !$kelasAktif) {
                    $error = 'Tahun ajaran, siswa, atau kelas tidak aktif.';
                } elseif (
                    $tanggalMulai < $tahunAjaran['tanggal_mulai'] ||
                    $tanggalMulai > $tahunAjaran['tanggal_selesai']
                ) {
                    $error = 'Tanggal mulai harus berada dalam rentang tahun ajaran yang dipilih.';
                } else {
                    $koneksi->begin_transaction();
                    $transaksiAktif = true;

                    $stmt = $koneksi->prepare(
                        "SELECT id FROM t_siswa WHERE id = ? AND status_aktif = 1 FOR UPDATE"
                    );
                    $stmt->bind_param('i', $siswaId);
                    $stmt->execute();
                    $siswaTerkunci = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$siswaTerkunci) {
                        $koneksi->rollback();
                        $transaksiAktif = false;
                        $error = 'Siswa tidak lagi aktif.';
                    } else {
                        $stmt = $koneksi->prepare(
                            "SELECT id, kelas_id, tanggal_mulai
                             FROM t_kelas_siswa
                             WHERE siswa_id = ? AND tahun_ajaran_id = ? AND status_aktif = 1
                             ORDER BY id DESC
                             LIMIT 1
                             FOR UPDATE"
                        );
                        $stmt->bind_param('ii', $siswaId, $tahunAjaranId);
                        $stmt->execute();
                        $penempatanAktif = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if ($penempatanAktif && (int)$penempatanAktif['kelas_id'] === $kelasId) {
                            $koneksi->rollback();
                            $transaksiAktif = false;
                            $pesan = 'Siswa sudah ditempatkan di kelas tersebut.';
                        } elseif (
                            $penempatanAktif &&
                            $tanggalMulai <= $penempatanAktif['tanggal_mulai']
                        ) {
                            $koneksi->rollback();
                            $transaksiAktif = false;
                            $error = 'Tanggal pindah harus setelah tanggal mulai penempatan sebelumnya.';
                        } else {
                            if ($penempatanAktif) {
                                $stmt = $koneksi->prepare(
                                    "UPDATE t_kelas_siswa
                                     SET tanggal_selesai = DATE_SUB(?, INTERVAL 1 DAY),
                                         status_aktif = 0,
                                         updated_at = CURRENT_TIMESTAMP
                                     WHERE id = ?"
                                );
                                $stmt->bind_param('si', $tanggalMulai, $penempatanAktif['id']);
                                $stmt->execute();
                                $stmt->close();
                            }

                            $stmt = $koneksi->prepare(
                                "INSERT INTO t_kelas_siswa
                                 (siswa_id, tahun_ajaran_id, kelas_id, tanggal_mulai,
                                  tanggal_selesai, status_aktif, created_at, updated_at)
                                 VALUES (?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
                            );
                            $stmt->bind_param(
                                'iiiss',
                                $siswaId,
                                $tahunAjaranId,
                                $kelasId,
                                $tanggalMulai,
                                $tahunAjaran['tanggal_selesai']
                            );
                            $stmt->execute();
                            $stmt->close();

                            $koneksi->commit();
                            $transaksiAktif = false;
                            header(
                                'Location: penempatan_siswa.php?tahun_ajaran=' .
                                $tahunAjaranId . '&pesan=berhasil'
                            );
                            exit;
                        }
                    }
                }
            } catch (mysqli_sql_exception $e) {
                if ($transaksiAktif) {
                    $koneksi->rollback();
                }
                $error = 'Gagal menyimpan penempatan siswa. Periksa kembali data dan struktur database.';
            }
        }
    }
}

$tahunAjaranList = [];
$result = $koneksi->query(
    "SELECT id, nama, tanggal_mulai, tanggal_selesai
     FROM t_tahun_ajaran
     WHERE status_aktif = 1
     ORDER BY tanggal_mulai DESC, id DESC"
);
while ($row = $result->fetch_assoc()) {
    $tahunAjaranList[] = $row;
}

$tahunTerpilih = null;
foreach ($tahunAjaranList as $tahun) {
    if ((int)$tahun['id'] === $tahunAjaranId) {
        $tahunTerpilih = $tahun;
        break;
    }
}
if (!$tahunTerpilih && $tahunAjaranList) {
    $tahunTerpilih = $tahunAjaranList[0];
    $tahunAjaranId = (int)$tahunTerpilih['id'];
}

$kelasList = [];
$siswaList = [];
$penempatanList = [];
if ($tahunTerpilih) {
    $result = $koneksi->query(
        "SELECT id, nama, tingkat, jurusan
         FROM t_kelas
         WHERE status_aktif = 1
         ORDER BY tingkat, nama"
    );
    while ($row = $result->fetch_assoc()) {
        $kelasList[] = $row;
    }

    $stmt = $koneksi->prepare(
        "SELECT s.id, s.nis, s.nama,
                ks.kelas_id AS kelas_aktif_id, k.nama AS nama_kelas_aktif
         FROM t_siswa s
         LEFT JOIN t_kelas_siswa ks
           ON ks.siswa_id = s.id
          AND ks.tahun_ajaran_id = ?
          AND ks.status_aktif = 1
         LEFT JOIN t_kelas k ON k.id = ks.kelas_id
         WHERE s.status_aktif = 1
         ORDER BY s.nama, s.nis"
    );
    $stmt->bind_param('i', $tahunAjaranId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $siswaList[] = $row;
    }
    $stmt->close();

    $stmt = $koneksi->prepare(
        "SELECT ks.id, s.nis, s.nama AS nama_siswa, k.nama AS nama_kelas,
                k.tingkat, k.jurusan, ks.tanggal_mulai
         FROM t_kelas_siswa ks
         INNER JOIN t_siswa s ON s.id = ks.siswa_id
         INNER JOIN t_kelas k ON k.id = ks.kelas_id
         WHERE ks.tahun_ajaran_id = ? AND ks.status_aktif = 1
         ORDER BY k.tingkat, k.nama, s.nama"
    );
    $stmt->bind_param('i', $tahunAjaranId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $penempatanList[] = $row;
    }
    $stmt->close();
}

if (($_GET['pesan'] ?? '') === 'berhasil') {
    $pesan = 'Penempatan siswa berhasil disimpan.';
}

$tanggalDefault = '';
if ($tahunTerpilih) {
    $hariIni = date('Y-m-d');
    $tanggalDefault = min(
        max($hariIni, $tahunTerpilih['tanggal_mulai']),
        $tahunTerpilih['tanggal_selesai']
    );
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penempatan Siswa</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            background: #f3f4f6;
            color: #1f2937;
            font-family: Arial, sans-serif;
        }
        .container {
            max-width: 1150px;
            margin: auto;
            padding: 24px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 4px 16px rgba(15, 23, 42, .08);
        }
        h1 { margin: 0 0 8px; color: #1d4ed8; font-size: 26px; }
        .intro { margin: 0 0 22px; color: #64748b; }
        .panel {
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }
        h2 { margin: 0 0 16px; font-size: 18px; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        label { display: block; margin-bottom: 6px; font-weight: 600; }
        input, select, button {
            width: 100%;
            min-height: 42px;
            padding: 9px 11px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font: inherit;
        }
        button {
            border: 0;
            background: #2563eb;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #1d4ed8; }
        .submit { margin-top: 16px; }
        .notice {
            margin: 14px 0;
            padding: 12px 14px;
            border-radius: 7px;
        }
        .success { background: #dcfce7; color: #166534; }
        .failure { background: #fee2e2; color: #991b1b; }
        .muted { color: #64748b; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th, td {
            padding: 11px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        th { background: #eff6ff; color: #1e3a8a; }
        @media (max-width: 650px) {
            body { padding: 12px; }
            .container { padding: 16px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<main class="container">
    <h1>Penempatan Siswa</h1>
    <p class="intro">Tempatkan siswa ke kelas pada tahun ajaran aktif. Penempatan sebelumnya tetap tersimpan sebagai riwayat.</p>

    <?php if ($pesan !== ''): ?>
        <div class="notice success"><?= aman($pesan) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="notice failure"><?= aman($error) ?></div>
    <?php endif; ?>

    <?php if (!$tahunAjaranList): ?>
        <div class="notice failure">Belum ada tahun ajaran aktif. Aktifkan tahun ajaran terlebih dahulu.</div>
    <?php else: ?>
        <section class="panel">
            <h2>Pilih Tahun Ajaran</h2>
            <form method="get" action="penempatan_siswa.php">
                <label for="tahun_ajaran">Tahun ajaran</label>
                <select id="tahun_ajaran" name="tahun_ajaran" onchange="this.form.submit()">
                    <?php foreach ($tahunAjaranList as $tahun): ?>
                        <option value="<?= (int)$tahun['id'] ?>" <?= (int)$tahun['id'] === $tahunAjaranId ? 'selected' : '' ?>>
                            <?= aman($tahun['nama']) ?> (<?= aman($tahun['tanggal_mulai']) ?> s.d. <?= aman($tahun['tanggal_selesai']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript><button class="submit" type="submit">Tampilkan</button></noscript>
            </form>
        </section>

        <section class="panel">
            <h2>Tempatkan atau Pindahkan Siswa</h2>
            <?php if (!$kelasList): ?>
                <p class="muted">Belum ada kelas aktif untuk dipilih.</p>
            <?php elseif (!$siswaList): ?>
                <p class="muted">Belum ada siswa aktif untuk ditempatkan.</p>
            <?php else: ?>
                <form method="post" action="penempatan_siswa.php?tahun_ajaran=<?= $tahunAjaranId ?>">
                    <input type="hidden" name="csrf_token" value="<?= aman($_SESSION['csrf_penempatan']) ?>">
                    <input type="hidden" name="tahun_ajaran_id" value="<?= $tahunAjaranId ?>">
                    <div class="form-grid">
                        <div>
                            <label for="siswa_id">Siswa</label>
                            <select id="siswa_id" name="siswa_id" required>
                                <option value="">-- Pilih siswa --</option>
                                <?php foreach ($siswaList as $siswa): ?>
                                    <option value="<?= (int)$siswa['id'] ?>">
                                        <?= aman($siswa['nis']) ?> - <?= aman($siswa['nama']) ?>
                                        <?php if ($siswa['kelas_aktif_id'] !== null): ?>
                                            (saat ini: <?= aman($siswa['nama_kelas_aktif']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="kelas_id">Kelas tujuan</label>
                            <select id="kelas_id" name="kelas_id" required>
                                <option value="">-- Pilih kelas --</option>
                                <?php foreach ($kelasList as $kelas): ?>
                                    <option value="<?= (int)$kelas['id'] ?>">
                                        <?= aman($kelas['tingkat'] . ' ' . $kelas['nama'] . ' - ' . $kelas['jurusan']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="tanggal_mulai">Tanggal mulai penempatan</label>
                            <input
                                type="date"
                                id="tanggal_mulai"
                                name="tanggal_mulai"
                                min="<?= aman($tahunTerpilih['tanggal_mulai']) ?>"
                                max="<?= aman($tahunTerpilih['tanggal_selesai']) ?>"
                                value="<?= aman($tanggalDefault) ?>"
                                required
                            >
                        </div>
                    </div>
                    <button class="submit" type="submit" name="simpan">Simpan Penempatan</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2>Daftar Penempatan Aktif</h2>
            <?php if (!$penempatanList): ?>
                <p class="muted">Belum ada siswa yang ditempatkan pada tahun ajaran ini.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>No.</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Mulai</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($penempatanList as $index => $penempatan): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= aman($penempatan['nis']) ?></td>
                                <td><?= aman($penempatan['nama_siswa']) ?></td>
                                <td><?= aman($penempatan['tingkat'] . ' ' . $penempatan['nama_kelas'] . ' - ' . $penempatan['jurusan']) ?></td>
                                <td><?= aman($penempatan['tanggal_mulai']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
