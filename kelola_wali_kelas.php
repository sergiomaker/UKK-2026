<?php
require_once __DIR__ . '/cek_session.php';

mysqli_set_charset($koneksi, "utf8mb4");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function aman($data)
{
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

$pesan = '';
$error = '';

$data_edit = null;
$guruList = null;
$kelasList = null;
$tahunAjaranList = null;
$result = null;

try {
    if (isset($_POST['tambah']) || isset($_POST['edit'])) {
        $id = (int)($_POST['id'] ?? 0);
        $tahunAjaranId = (int)($_POST['tahun_ajaran_id'] ?? 0);
        $kelasId = (int)($_POST['kelas_id'] ?? 0);
        $guruId = (int)($_POST['guru_id'] ?? 0);
        $tanggalMulai = trim($_POST['tanggal_mulai'] ?? '');
        $tanggalSelesai = trim($_POST['tanggal_selesai'] ?? '');
        $statusAktif = (int)($_POST['status_aktif'] ?? -1);
        $tanggalMulaiValid = DateTime::createFromFormat('!Y-m-d', $tanggalMulai);
        $tanggalSelesaiValid = DateTime::createFromFormat('!Y-m-d', $tanggalSelesai);

        if (
            (isset($_POST['edit']) && $id <= 0) ||
            $tahunAjaranId <= 0 || $kelasId <= 0 || $guruId <= 0 ||
            !$tanggalMulaiValid || $tanggalMulaiValid->format('Y-m-d') !== $tanggalMulai ||
            !$tanggalSelesaiValid || $tanggalSelesaiValid->format('Y-m-d') !== $tanggalSelesai ||
            $tanggalMulai > $tanggalSelesai ||
            !in_array($statusAktif, [0, 1], true)
        ) {
            $error = 'Lengkapi data wali kelas dengan benar.';
        } else {
            if (isset($_POST['edit'])) {
                $stmt = $koneksi->prepare(
                    "UPDATE t_wali_kelas
                     SET tahun_ajaran_id=?, kelas_id=?, guru_id=?,
                         tanggal_mulai=?, tanggal_selesai=?, status_aktif=?,
                         updated_at=NOW()
                     WHERE id=?"
                );
                $stmt->bind_param(
                    "iiissii",
                    $tahunAjaranId,
                    $kelasId,
                    $guruId,
                    $tanggalMulai,
                    $tanggalSelesai,
                    $statusAktif,
                    $id
                );
                $stmt->execute();
                header("Location: kelola_wali_kelas.php?pesan=edit");
            } else {
                $stmt = $koneksi->prepare(
                    "INSERT INTO t_wali_kelas
                     (tahun_ajaran_id, kelas_id, guru_id, tanggal_mulai,
                      tanggal_selesai, status_aktif, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
                );
                $stmt->bind_param(
                    "iiissi",
                    $tahunAjaranId,
                    $kelasId,
                    $guruId,
                    $tanggalMulai,
                    $tanggalSelesai,
                    $statusAktif
                );
                $stmt->execute();
                header("Location: kelola_wali_kelas.php?pesan=tambah");
            }
            exit;
        }
    }

    if (isset($_POST['hapus'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $error = 'Data wali kelas tidak valid.';
        } else {
            $stmt = $koneksi->prepare("DELETE FROM t_wali_kelas WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            header("Location: kelola_wali_kelas.php?pesan=hapus");
            exit;
        }
    }

    if (isset($_GET['edit'])) {
        $id = (int)$_GET['edit'];
        $stmt = $koneksi->prepare("SELECT * FROM t_wali_kelas WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $data_edit = $stmt->get_result()->fetch_assoc();
    }

    $guruList = $koneksi->query(
        "SELECT id, nip, nama FROM t_guru ORDER BY status_aktif DESC, nama"
    );
    $kelasList = $koneksi->query(
        "SELECT k.id, k.nama
         FROM t_kelas k
         JOIN (SELECT MAX(id) AS id FROM t_kelas GROUP BY nama) terbaru
           ON terbaru.id = k.id
         ORDER BY k.status_aktif DESC, k.nama"
    );
    $tahunAjaranList = $koneksi->query(
        "SELECT ta.id, ta.nama, ta.tanggal_mulai, ta.tanggal_selesai
         FROM t_tahun_ajaran ta
         JOIN (
             SELECT MAX(id) AS id FROM t_tahun_ajaran GROUP BY nama
         ) terbaru ON terbaru.id = ta.id
         ORDER BY (CURDATE() BETWEEN ta.tanggal_mulai AND ta.tanggal_selesai) DESC,
                  ta.status_aktif DESC, ta.tanggal_mulai DESC, ta.id DESC"
    );
    $result = $koneksi->query(
        "SELECT wk.id, wk.tanggal_mulai, wk.tanggal_selesai, wk.status_aktif,
                wk.guru_id, wk.kelas_id, wk.tahun_ajaran_id,
                g.nip, g.nama AS nama_guru, k.nama AS nama_kelas,
                ta.nama AS nama_tahun_ajaran
         FROM t_wali_kelas wk
         LEFT JOIN t_guru g ON g.id = wk.guru_id
         LEFT JOIN t_kelas k ON k.id = wk.kelas_id
         LEFT JOIN t_tahun_ajaran ta ON ta.id = wk.tahun_ajaran_id
         ORDER BY wk.id DESC"
    );
} catch (mysqli_sql_exception $e) {
    error_log('kelola_wali_kelas.php: ' . $e->getMessage());
    $error = 'Gagal memproses data wali kelas. Periksa struktur dan isi database.';
}

$pesan = $_GET['pesan'] ?? '';
$formData = $data_edit ?? $_POST;
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Wali Kelas</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
        }

        .sidebar {
            display: none;
        }

        .main {
            margin-left: 0;
            padding: 30px;
        }

        h1,
        h2 {
            color: #1d4ed8;
            margin-bottom: 12px;
        }

        .card {
            background: white;
            padding: 22px;
            margin-bottom: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: bold;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            font: inherit;
        }

        button,
        .btn {
            display: inline-block;
            border: none;
            border-radius: 5px;
            padding: 10px 14px;
            background: #2563eb;
            color: white;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
        }

        button:hover,
        .btn:hover {
            background: #1d4ed8;
        }

        .btn-edit {
            background: #2563eb;
        }

        .btn-hapus {
            background: #dc2626;
        }

        .btn-hapus:hover {
            background: #b91c1c;
        }

        .alert {
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 15px;
            background: #DBEAFE;
        }

        .alert-error {
            background: #FEE2E2;
            color: #B91C1C;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #2563eb;
            color: white;
        }

        .aksi {
            display: flex;
            gap: 6px;
        }

        @media (max-width: 700px) {
            .sidebar {
                position: static;
                width: 100%;
            }

            .sidebar a {
                display: inline-block;
            }

            .main {
                margin-left: 0;
                padding: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <main class="main">
        <h1>Data Wali Kelas</h1>
        <p>Pengelolaan data wali kelas sekolah.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= aman($error) ?></div>
        <?php endif; ?>

        <?php if ($pesan !== ''): ?>
            <div class="alert">
                <?= aman([
                    'tambah' => 'Data wali kelas berhasil ditambahkan.',
                    'edit' => 'Data wali kelas berhasil diperbarui.',
                    'hapus' => 'Proses hapus selesai.'
                ][$pesan] ?? 'Proses selesai.') ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <h2><?= $data_edit ? 'Edit Wali Kelas' : 'Tambah Wali Kelas' ?></h2>

            <form method="POST" action="kelola_wali_kelas.php">
                <?php if ($data_edit): ?>
                    <input type="hidden" name="id"
                        value="<?= aman($data_edit['id']) ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div>
                        <label>Guru</label>
                        <select name="guru_id" required>
                            <option value="">-- Pilih guru --</option>
                            <?php if ($guruList instanceof mysqli_result): ?>
                                <?php while ($guru = $guruList->fetch_assoc()): ?>
                                    <option value="<?= (int)$guru['id'] ?>"
                                        <?= (int)($formData['guru_id'] ?? 0) === (int)$guru['id']
                                            ? 'selected' : '' ?>>
                                        <?= aman($guru['nama'] . ' (' . $guru['nip'] . ')') ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label>Kelas</label>
                        <select name="kelas_id" required>
                            <option value="">-- Pilih kelas --</option>
                            <?php if ($kelasList instanceof mysqli_result): ?>
                                <?php while ($kelas = $kelasList->fetch_assoc()): ?>
                                    <option value="<?= (int)$kelas['id'] ?>"
                                        <?= (int)($formData['kelas_id'] ?? 0) === (int)$kelas['id']
                                            ? 'selected' : '' ?>>
                                        <?= aman($kelas['nama']) ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label>Tahun Ajaran</label>
                        <select name="tahun_ajaran_id" required>
                            <option value="">-- Pilih tahun ajaran --</option>
                            <?php if ($tahunAjaranList instanceof mysqli_result): ?>
                                <?php while ($tahun = $tahunAjaranList->fetch_assoc()): ?>
                                    <option value="<?= (int)$tahun['id'] ?>"
                                        <?= (int)($formData['tahun_ajaran_id'] ?? 0) === (int)$tahun['id']
                                            ? 'selected' : '' ?>>
                                        <?= aman($tahun['nama'] . ' (' . $tahun['tanggal_mulai'] . ' - ' . $tahun['tanggal_selesai'] . ')') ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label>Tanggal Mulai</label>
                        <input type="date" name="tanggal_mulai"
                            value="<?= aman($formData['tanggal_mulai'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label>Tanggal Selesai</label>
                        <input type="date" name="tanggal_selesai"
                            value="<?= aman($formData['tanggal_selesai'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label>Status</label>
                        <select name="status_aktif" required>
                            <option value="1"
                                <?= (string)($formData['status_aktif'] ?? '1') === '1'
                                    ? 'selected' : '' ?>>Aktif</option>
                            <option value="0"
                                <?= (string)($formData['status_aktif'] ?? '') === '0'
                                    ? 'selected' : '' ?>>Tidak aktif</option>
                        </select>
                    </div>
                </div>

                <br>
                <button type="submit" name="<?= $data_edit ? 'edit' : 'tambah' ?>">
                    <?= $data_edit ? 'Simpan Perubahan' : 'Tambah Data' ?>
                </button>

                <?php if ($data_edit): ?>
                    <a href="kelola_wali_kelas.php" class="btn">Batal</a>
                <?php endif; ?>
            </form>
        </section>

        <section class="card">
            <h2>Daftar Wali Kelas</h2>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Guru</th>
                            <th>NIP</th>
                            <th>Kelas</th>
                            <th>Tahun Ajaran</th>
                            <th>Masa Tugas</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result instanceof mysqli_result && $result->num_rows > 0): ?>
                            <?php $no = 1; ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= aman($row['nama_guru'] ?? '-') ?></td>
                                    <td><?= aman($row['nip'] ?? '-') ?></td>
                                    <td><?= aman($row['nama_kelas'] ?? '-') ?></td>
                                    <td><?= aman($row['nama_tahun_ajaran'] ?? '-') ?></td>
                                    <td><?= aman($row['tanggal_mulai']) ?> s.d. <?= aman($row['tanggal_selesai']) ?></td>
                                    <td><?= (int)$row['status_aktif'] === 1 ? 'Aktif' : 'Tidak aktif' ?></td>
                                    <td>
                                        <div class="aksi">
                                            <a class="btn btn-edit"
                                                href="kelola_wali_kelas.php?edit=<?= (int)$row['id'] ?>">
                                                Edit
                                            </a>

                                            <form method="POST" action="kelola_wali_kelas.php"
                                                onsubmit="return confirm('Yakin ingin menghapus data ini?')">
                                                <input type="hidden" name="id"
                                                    value="<?= (int)$row['id'] ?>">
                                                <button class="btn-hapus" type="submit" name="hapus">
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center">
                                    Belum ada data wali kelas.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>

</html>