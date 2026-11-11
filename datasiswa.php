
<?php
require_once __DIR__ . '/cek_session.php';

// Fungsi untuk mengamankan output
function aman($teks) {
    return htmlspecialchars(
        (string) $teks,
        ENT_QUOTES,
        'UTF-8'
    );
}

$error = "";

// Memperbarui data siswa aktif berdasarkan ID agar NIS yang sama tidak salah sasaran.
if (isset($_POST['simpan_edit'])) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $nis = trim($_POST['nis'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $jk = $_POST['jenis_kelamin'] ?? '';
    $tanggalLahir = trim($_POST['tanggal_lahir'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $tanggal = $tanggalLahir === ''
        ? false
        : DateTime::createFromFormat('!Y-m-d', $tanggalLahir);
    $tanggalValid = $tanggalLahir === '' ||
        ($tanggal && $tanggal->format('Y-m-d') === $tanggalLahir);

    if (
        !$id || $nis === '' || $nisn === '' || $nama === '' ||
        !$tanggalValid || !in_array($jk, ['L', 'P', 'U'], true)
    ) {
        $error = "Lengkapi data siswa dengan benar.";
    } else {
        try {
            $stmt = mysqli_prepare(
                $koneksi,
                "SELECT id FROM t_siswa WHERE id = ? AND status_aktif = 1"
            );
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $siswaAktif = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$siswaAktif) {
                $error = "Data siswa aktif yang akan diedit tidak ditemukan.";
            } else {
                $stmt = mysqli_prepare(
                    $koneksi,
                    "UPDATE t_siswa
                     SET nis = ?, nisn = ?, nama = ?, jenis_kelamin = ?,
                         tanggal_lahir = NULLIF(?, ''),
                         alamat = NULLIF(?, ''),
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = ? AND status_aktif = 1"
                );
                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssssi",
                    $nis,
                    $nisn,
                    $nama,
                    $jk,
                    $tanggalLahir,
                    $alamat,
                    $id
                );

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header("Location: datasiswa.php?pesan=edit");
                    exit;
                }

                $error = "Gagal mengubah data siswa: " . mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
            }
        } catch (mysqli_sql_exception $e) {
            $error = "Gagal mengubah data siswa. Periksa kembali data yang dimasukkan.";
        }
    }
}

// Menambahkan data siswa
if (isset($_POST['tambah'])) {
    $nis = trim($_POST['nis'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $jk = $_POST['jenis_kelamin'] ?? '';
    $tanggalLahir = trim($_POST['tanggal_lahir'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $tanggal = $tanggalLahir === ''
        ? false
        : DateTime::createFromFormat('!Y-m-d', $tanggalLahir);
    $tanggalValid = $tanggalLahir === '' ||
        ($tanggal && $tanggal->format('Y-m-d') === $tanggalLahir);

    if (
        $nis === '' || $nisn === '' || $nama === '' ||
        !$tanggalValid || !in_array($jk, ['L', 'P', 'U'], true)
    ) {
        $error = "Lengkapi data siswa dengan benar.";
    } else {
        try {
            $stmt = mysqli_prepare(
                $koneksi,
                "INSERT INTO t_siswa
                (nis, nisn, nama, jenis_kelamin, tanggal_lahir, alamat, status_aktif, created_at, updated_at)
                VALUES (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                "ssssss",
                $nis,
                $nisn,
                $nama,
                $jk,
                $tanggalLahir,
                $alamat,
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: datasiswa.php?pesan=berhasil");
                exit;
            } else {
                $error = "Gagal menyimpan data siswa.";
            }
            mysqli_stmt_close($stmt);
        } catch (mysqli_sql_exception $e) {
            $error = "Gagal menyimpan data siswa. Periksa NIS/NISN dan struktur tabel.";
        }
    }
}

// Menonaktifkan data berdasarkan NIS agar riwayat penempatan tetap tersimpan.
if (isset($_POST['hapus'])) {
    $nisHapus = trim($_POST['nis_hapus'] ?? '');

    if ($nisHapus !== '') {
        try {
            $stmt = mysqli_prepare(
                $koneksi,
                "UPDATE t_siswa
                 SET status_aktif = 0, updated_at = CURRENT_TIMESTAMP
                 WHERE nis = ? AND status_aktif = 1"
            );
            mysqli_stmt_bind_param($stmt, "s", $nisHapus);

            if (!mysqli_stmt_execute($stmt)) {
                $error = "Gagal menonaktifkan data siswa.";
            } elseif (mysqli_stmt_affected_rows($stmt) === 0) {
                $error = "Data siswa tidak ditemukan atau sudah dinonaktifkan.";
            }

            mysqli_stmt_close($stmt);
        } catch (mysqli_sql_exception $e) {
            $error = "Gagal menonaktifkan data siswa.";
        }
    }

    if ($error === '' && $nisHapus !== '') {
        header("Location: datasiswa.php?pesan=nonaktif");
        exit;
    }
}

// Mengambil data siswa yang dipilih untuk diedit.
$siswaEdit = null;
$idEdit = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($idEdit) {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id, nis, nisn, nama, jenis_kelamin, tanggal_lahir, alamat
         FROM t_siswa
         WHERE id = ? AND status_aktif = 1"
    );
    mysqli_stmt_bind_param($stmt, "i", $idEdit);
    mysqli_stmt_execute($stmt);
    $siswaEdit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$siswaEdit) {
        $error = "Data siswa aktif yang akan diedit tidak ditemukan.";
    }
}

// Mengambil data siswa aktif.
$result = mysqli_query(
    $koneksi,
    "SELECT id, nis, nama, jenis_kelamin, alamat
     FROM t_siswa
     WHERE status_aktif = 1
     ORDER BY
        CASE WHEN nis LIKE 'TMP26-%' THEN 0 ELSE 1 END,
        CASE
            WHEN nis LIKE 'TMP26-%'
            THEN CAST(SUBSTRING_INDEX(nis, '-', -1) AS UNSIGNED)
            ELSE 0
        END,
        nis DESC"
);

if (!$result) {
    die("Gagal mengambil data siswa: " . mysqli_error($koneksi));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Siswa</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        h2 {
            color: #1d4ed8;
        }

        input, select, textarea, button {
            padding: 10px;
            margin: 5px 0;
            box-sizing: border-box;
        }

        input, select, textarea {
            width: 100%;
        }

        button {
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: white;
            background: #2563eb;
        }

        .hapus {
            background: #dc2626;
        }

        .aksi-buttons {
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .aksi-buttons form {
            margin: 0;
        }

        .aksi-buttons .btn-aksi {
            display: inline-block;
            margin: 0;
            padding: 8px 10px;
            border: 0;
            border-radius: 4px;
            color: white;
            font-size: 13px;
            line-height: 1.2;
            text-decoration: none;
            cursor: pointer;
        }

        .aksi-buttons .btn-edit {
            background: #ffc107;
            color: #212529;
        }

        .aksi-buttons .btn-edit:hover {
            background: #e0a800;
        }

        .aksi-buttons .hapus {
            background: #dc3545;
        }

        .aksi-buttons .hapus:hover {
            background: #c82333;
        }

        .tabel {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .tabel th, .tabel td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        .tabel th {
            background: #2563eb;
            color: white;
        }

        .pesan {
            color: green;
        }

        .error {
            color: red;
        }

        @media (max-width: 700px) {
            .container {
                padding: 12px;
                overflow-x: auto;
            }

            .tabel {
                min-width: 700px;
            }
        }
    </style>
    <script src="pencarian_tabel.js" defer></script>
</head>

<body>
<div class="container">

    <h2>Data Siswa</h2>
    <p>Tambah dan kelola data siswa.</p>

    <?php if (($_GET['pesan'] ?? '') === 'berhasil'): ?>
        <p class="pesan">Data siswa berhasil ditambahkan!</p>
    <?php elseif (($_GET['pesan'] ?? '') === 'edit'): ?>
        <p class="pesan">Data siswa berhasil diperbarui!</p>
    <?php elseif (($_GET['pesan'] ?? '') === 'nonaktif'): ?>
        <p class="pesan">Data siswa berhasil dinonaktifkan.</p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="error"><?= aman($error) ?></p>
    <?php endif; ?>

    <h3><?= $siswaEdit ? 'Edit Siswa' : 'Tambah Siswa' ?></h3>

    <form method="POST" action="datasiswa.php<?= $siswaEdit ? '?edit=' . (int)$siswaEdit['id'] : '' ?>">
        <?php if ($siswaEdit): ?>
            <input type="hidden" name="id" value="<?= aman($siswaEdit['id']) ?>">
        <?php endif; ?>
        <label>NIS</label>
        <input type="text" name="nis" value="<?= aman($_POST['nis'] ?? $siswaEdit['nis'] ?? '') ?>" required>

        <label>NISN</label>
        <input type="text" name="nisn" value="<?= aman($_POST['nisn'] ?? $siswaEdit['nisn'] ?? '') ?>" required>

        <label>Nama Siswa</label>
        <input type="text" name="nama" value="<?= aman($_POST['nama'] ?? $siswaEdit['nama'] ?? '') ?>" required>

        <label>Jenis Kelamin</label>
        <select name="jenis_kelamin" required>
            <option value="">-- Pilih Jenis Kelamin --</option>
            <option value="U" <?= ($_POST['jenis_kelamin'] ?? $siswaEdit['jenis_kelamin'] ?? '') === 'U' ? 'selected' : '' ?>>Belum diisi</option>
            <option value="L" <?= ($_POST['jenis_kelamin'] ?? $siswaEdit['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
            <option value="P" <?= ($_POST['jenis_kelamin'] ?? $siswaEdit['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
        </select>

        <label>Tanggal Lahir (opsional)</label>
        <input type="date" name="tanggal_lahir" value="<?= aman($_POST['tanggal_lahir'] ?? $siswaEdit['tanggal_lahir'] ?? '') ?>">

        <label>Alamat (opsional)</label>
        <textarea name="alamat"><?= aman($_POST['alamat'] ?? $siswaEdit['alamat'] ?? '') ?></textarea>
        <small>Data yang belum diketahui dapat dikosongkan dan akan ditampilkan sebagai “Belum diisi”.</small>

        <button type="submit" name="<?= $siswaEdit ? 'simpan_edit' : 'tambah' ?>">
            <?= $siswaEdit ? 'Simpan Perubahan' : 'Tambah Siswa' ?>
        </button>
        <?php if ($siswaEdit): ?>
            <a href="datasiswa.php">Batal</a>
        <?php endif; ?>
    </form>

    <h3>Daftar Siswa</h3>

    <table class="tabel">
        <thead>
        <tr>
            <th>No.</th>
            <th>NIS</th>
            <th>Nama Siswa</th>
            <th>Jenis Kelamin</th>
            <th>Alamat</th>
            <th>Aksi</th>
        </tr>
        </thead>

        <tbody>
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php $no = 1; ?>

            <?php while ($siswa = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= aman($siswa['nis']) ?></td>
                    <td><?= aman($siswa['nama']) ?></td>
                    <td><?= aman(match ($siswa['jenis_kelamin']) {
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                        default => 'Belum diisi',
                    }) ?></td>
                    <td><?= aman($siswa['alamat'] ?: 'Belum diisi') ?></td>
                    <td>
                        <div class="aksi-buttons">
                        <a class="btn-aksi btn-edit" href="datasiswa.php?edit=<?= (int)$siswa['id'] ?>">Edit</a>
                        <form method="POST" action="datasiswa.php"
                              onsubmit="return confirm('Yakin ingin menonaktifkan siswa ini?');">
                            <input type="hidden" name="nis_hapus"
                                   value="<?= aman($siswa['nis']) ?>">

                            <button type="submit" name="hapus"
                                    class="btn-aksi hapus">
                                Nonaktifkan
                            </button>
                        </form>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>

        <?php else: ?>
            <tr>
                <td colspan="6">Belum ada data siswa.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

</div>
</body>
</html>
