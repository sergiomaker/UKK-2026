<?php
require_once __DIR__ . '/cek_session.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function aman($nilai)
{
    return htmlspecialchars((string)$nilai, ENT_QUOTES, 'UTF-8');
}

$error = '';
$pesan = $_GET['pesan'] ?? '';
$tahunAktif = null;
$daftarKelas = null;

try {
    if (isset($_POST['hapus'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (!$id) {
            $error = 'Data kelas yang akan dinonaktifkan tidak valid.';
        } else {
            $stmt = $koneksi->prepare(
                "UPDATE t_kelas
                 SET status_aktif = 0, updated_at = NOW()
                 WHERE id = ? AND status_aktif = 1"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($stmt->affected_rows === 1) {
                header('Location: datakelas.php?pesan=hapus');
                exit;
            }

            $stmt = $koneksi->prepare(
                "SELECT id FROM t_kelas WHERE id = ?"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $kelasAda = $stmt->get_result()->fetch_assoc();
            $error = $kelasAda
                ? 'Kelas sudah tidak aktif.'
                : 'Data kelas tidak ditemukan.';
        }
    }

    if (isset($_POST['simpan_edit'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $nama = trim($_POST['nama'] ?? '');
        $tingkat = trim($_POST['tingkat'] ?? '');
        $jurusan = trim($_POST['jurusan'] ?? '');
        $jurusanDiizinkan = ['BD', 'TSM', 'RPL', 'TKJ', 'TKR', 'AKL', 'MPLB', 'Umum'];

        if (
            !$id || $nama === '' || strlen($nama) > 100 ||
            !in_array($tingkat, ['X', 'XI', 'XII'], true) ||
            !in_array($jurusan, $jurusanDiizinkan, true)
        ) {
            $error = 'Nama, tingkat, atau jurusan kelas tidak valid.';
        } else {
            $stmt = $koneksi->prepare(
                "SELECT id FROM t_kelas
                 WHERE nama = ? AND tingkat = ? AND jurusan = ?
                   AND status_aktif = 1 AND id <> ?
                 LIMIT 1"
            );
            $stmt->bind_param('sssi', $nama, $tingkat, $jurusan, $id);
            $stmt->execute();

            if ($stmt->get_result()->num_rows > 0) {
                $error = 'Kelas dengan nama, tingkat, dan jurusan tersebut sudah terdaftar.';
            } else {
                $stmt = $koneksi->prepare(
                    "UPDATE t_kelas
                     SET nama = ?, tingkat = ?, jurusan = ?, updated_at = NOW()
                     WHERE id = ?"
                );
                $stmt->bind_param('sssi', $nama, $tingkat, $jurusan, $id);
                $stmt->execute();
                if ($stmt->affected_rows === 0) {
                    $stmt = $koneksi->prepare("SELECT id FROM t_kelas WHERE id = ?");
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    if (!$stmt->get_result()->fetch_assoc()) {
                        $error = 'Data kelas yang akan diedit tidak ditemukan.';
                    }
                }
                if ($error === '') {
                    header('Location: datakelas.php?pesan=edit');
                    exit;
                }
            }
        }
    }

    if (isset($_POST['tambah'])) {
        $nama = trim($_POST['nama'] ?? '');
        $tingkat = trim($_POST['tingkat'] ?? '');
        $jurusan = trim($_POST['jurusan'] ?? '');
        $jurusanDiizinkan = ['BD', 'TSM', 'RPL', 'TKJ', 'TKR', 'AKL', 'MPLB', 'Umum'];

        if (
            $nama === '' || strlen($nama) > 100 ||
            !in_array($tingkat, ['X', 'XI', 'XII'], true) ||
            !in_array($jurusan, $jurusanDiizinkan, true)
        ) {
            $error = 'Nama, tingkat, atau jurusan kelas tidak valid.';
        } else {
            $stmt = $koneksi->prepare(
                "SELECT id FROM t_kelas
                 WHERE nama=? AND tingkat=? AND jurusan=? AND status_aktif=1
                 LIMIT 1"
            );
            $stmt->bind_param('sss', $nama, $tingkat, $jurusan);
            $stmt->execute();

            if ($stmt->get_result()->num_rows > 0) {
                $error = 'Kelas dengan nama, tingkat, dan jurusan tersebut sudah terdaftar.';
            } else {
                $stmt = $koneksi->prepare(
                    "INSERT INTO t_kelas
                        (nama, tingkat, jurusan, status_aktif, created_at, updated_at)
                     VALUES (?, ?, ?, 1, NOW(), NOW())"
                );
                $stmt->bind_param('sss', $nama, $tingkat, $jurusan);
                $stmt->execute();
                header('Location: datakelas.php?pesan=tambah');
                exit;
            }
        }
    }

    $kelasEdit = null;
    $idEdit = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($idEdit) {
        $stmt = $koneksi->prepare(
            "SELECT id, nama, tingkat, jurusan
             FROM t_kelas WHERE id = ?"
        );
        $stmt->bind_param('i', $idEdit);
        $stmt->execute();
        $kelasEdit = $stmt->get_result()->fetch_assoc();
        if (!$kelasEdit) {
            $error = 'Data kelas yang akan diedit tidak ditemukan.';
        }
    }

    $resultTahunAktif = $koneksi->query(
        "SELECT id, nama
         FROM t_tahun_ajaran
         ORDER BY (CURDATE() BETWEEN tanggal_mulai AND tanggal_selesai) DESC,
                  status_aktif DESC, tanggal_mulai DESC, id DESC
         LIMIT 1"
    );
    $tahunAktif = $resultTahunAktif->fetch_assoc();

    if ($tahunAktif) {
        $stmt = $koneksi->prepare(
            "SELECT k.id, k.nama, k.tingkat, k.jurusan,
                    COALESCE(g.nama, 'Belum ditentukan') AS nama_wali,
                    (
                        SELECT COUNT(DISTINCT ks.siswa_id)
                        FROM t_kelas_siswa ks
                        INNER JOIN t_kelas kelas_siswa ON kelas_siswa.id = ks.kelas_id
                        WHERE kelas_siswa.nama = k.nama
                          AND kelas_siswa.tingkat = k.tingkat
                          AND kelas_siswa.jurusan = k.jurusan
                          AND ks.tahun_ajaran_id = ?
                          AND ks.status_aktif = 1
                    ) AS jumlah_siswa
             FROM t_kelas k
             INNER JOIN (
                 SELECT MAX(id) AS id
                 FROM t_kelas
                 GROUP BY nama, tingkat, jurusan
             ) kelas_terbaru ON kelas_terbaru.id = k.id
             LEFT JOIN t_wali_kelas wk ON wk.id = (
                 SELECT MAX(wk_terbaru.id)
                 FROM t_wali_kelas wk_terbaru
                 WHERE wk_terbaru.kelas_id = k.id
                   AND wk_terbaru.tahun_ajaran_id = ?
                   AND wk_terbaru.status_aktif = 1
             )
             LEFT JOIN t_guru g ON g.id = wk.guru_id
             WHERE k.status_aktif = 1
             ORDER BY k.tingkat, k.jurusan, k.nama"
        );
        $stmt->bind_param('ii', $tahunAktif['id'], $tahunAktif['id']);
        $stmt->execute();
        $daftarKelas = $stmt->get_result();
    }
} catch (mysqli_sql_exception $e) {
    error_log('datakelas.php: ' . $e->getMessage());
    $error = 'Data kelas gagal dimuat atau disimpan. Periksa database dan coba lagi.';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Kelas</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            background: #f1f5f9;
            color: #17232c;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 1000px;
            margin: auto;
            padding: 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px #ccc;
        }

        h1 {
            color: #1565c0;
            text-align: center;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        input,
        select,
        button {
            width: 100%;
            margin: 7px 0;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font: inherit;
        }

        button {
            border: 0;
            background: #1565c0;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background: #0d47a1;
        }

        .alert {
            margin: 15px 0;
            padding: 12px;
            border-radius: 6px;
            background: #dcfce7;
            color: #166534;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .tabel-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 760px;
            margin-top: 20px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #1565c0;
            color: white;
        }

        .aksi {
            white-space: nowrap;
        }

        .aksi a {
            color: #1565c0;
        }

        .aksi a.btn-edit {
            display: inline-block;
            padding: 8px 10px;
            border-radius: 4px;
            background: #ffc107;
            color: #212529;
            font-size: 13px;
            line-height: 1.2;
            text-decoration: none;
        }

        .aksi a.btn-edit:hover {
            background: #e0a800;
        }

        .aksi form {
            display: inline;
            margin: 0;
        }

        .aksi .btn-hapus {
            width: auto;
            margin: 0;
            padding: 8px 10px;
            border: 0;
            border-radius: 4px;
            background: #dc3545;
            color: white;
            font-size: 13px;
            line-height: 1.2;
            cursor: pointer;
        }

        .aksi .btn-hapus:hover {
            background: #c82333;
        }

        @media (max-width: 700px) {
            body {
                padding: 12px;
            }

            .container {
                padding: 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
    <script src="pencarian_tabel.js" defer></script>
</head>

<body>
    <main class="container">
        <h1>Data Kelas</h1>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= aman($error) ?></div>
        <?php elseif ($pesan === 'tambah'): ?>
            <div class="alert">Data kelas berhasil ditambahkan.</div>
        <?php elseif ($pesan === 'edit'): ?>
            <div class="alert">Data kelas berhasil diperbarui.</div>
        <?php elseif ($pesan === 'hapus'): ?>
            <div class="alert">Kelas berhasil dinonaktifkan. Riwayat wali kelas dan penempatan tetap tersimpan.</div>
        <?php endif; ?>

        <h3><?= $kelasEdit ? 'Edit Data Kelas' : 'Tambah Data Kelas' ?></h3>
        <form method="POST" action="datakelas.php<?= $kelasEdit ? '?edit=' . (int)$kelasEdit['id'] : '' ?>">
            <?php if ($kelasEdit): ?>
                <input type="hidden" name="id" value="<?= (int)$kelasEdit['id'] ?>">
            <?php endif; ?>
            <div class="form-grid">
                <input type="text" name="nama" maxlength="100"
                    placeholder="Nama kelas, contoh: RPL 1"
                    value="<?= aman($_POST['nama'] ?? $kelasEdit['nama'] ?? '') ?>" required>

                <select name="tingkat" required>
                    <option value="">Pilih tingkat</option>
                    <?php foreach (['X', 'XI', 'XII'] as $tingkat): ?>
                        <option value="<?= aman($tingkat) ?>"
                            <?= ($_POST['tingkat'] ?? $kelasEdit['tingkat'] ?? '') === $tingkat ? 'selected' : '' ?>>
                            <?= aman($tingkat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="jurusan" required>
                    <option value="">Pilih jurusan</option>
                    <?php foreach (['BD', 'TSM', 'RPL', 'TKJ', 'TKR', 'AKL', 'MPLB', 'Umum'] as $jurusan): ?>
                        <option value="<?= aman($jurusan) ?>"
                            <?= ($_POST['jurusan'] ?? $kelasEdit['jurusan'] ?? '') === $jurusan ? 'selected' : '' ?>>
                            <?= aman($jurusan) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="<?= $kelasEdit ? 'simpan_edit' : 'tambah' ?>">
                <?= $kelasEdit ? 'Simpan Perubahan' : 'Tambah Kelas' ?>
            </button>
            <?php if ($kelasEdit): ?>
                <a href="datakelas.php">Batal</a>
            <?php endif; ?>
        </form>

        <h3>Daftar Kelas <?= $tahunAktif ? '(Tahun Ajaran ' . aman($tahunAktif['nama']) . ')' : '' ?></h3>
        <div class="tabel-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode</th>
                        <th>Nama Kelas</th>
                        <th>Wali Kelas</th>
                        <th>Jumlah Siswa</th>
                        <th>Jurusan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($daftarKelas instanceof mysqli_result && $daftarKelas->num_rows > 0): ?>
                        <?php $no = 1; ?>
                        <?php while ($kelas = $daftarKelas->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= aman('K' . str_pad((string)$kelas['id'], 3, '0', STR_PAD_LEFT)) ?></td>
                                <td><?= aman($kelas['tingkat'] . ' ' . $kelas['nama']) ?></td>
                                <td><?= aman($kelas['nama_wali']) ?></td>
                                <td><?= (int)$kelas['jumlah_siswa'] ?></td>
                                <td><?= aman($kelas['jurusan']) ?></td>
                                <td class="aksi">
                                    <a href="dashboard.php?page=wali_kelas" target="_top">Kelola wali kelas</a>
                                    |
                                    <a href="dashboard.php?page=penempatan_siswa" target="_top">Atur siswa</a>
                                    |
                                    <a class="btn-edit" href="datakelas.php?edit=<?= (int)$kelas['id'] ?>">Edit</a>
                                    <form method="POST" action="datakelas.php"
                                        onsubmit="return confirm('Nonaktifkan kelas ini? Riwayat wali kelas dan penempatan tetap disimpan.');">
                                        <input type="hidden" name="id" value="<?= (int)$kelas['id'] ?>">
                                        <button class="btn-hapus" type="submit" name="hapus">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center">
                                <?= $error === '' ? 'Belum ada data kelas.' : 'Data kelas tidak dapat ditampilkan.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>

</html>
