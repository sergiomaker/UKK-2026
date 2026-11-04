
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

// Menambahkan data siswa
if (isset($_POST['tambah'])) {
    $nis = trim($_POST['nis'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $jk = $_POST['jenis_kelamin'] ?? '';
    $tanggalLahir = trim($_POST['tanggal_lahir'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $tanggalValid = false;
    $tanggal = DateTime::createFromFormat('!Y-m-d', $tanggalLahir);
    if ($tanggal && $tanggal->format('Y-m-d') === $tanggalLahir) {
        $tanggalValid = true;
    }

    if (
        $nis === '' || $nisn === '' || $nama === '' ||
        !$tanggalValid || $alamat === '' ||
        !in_array($jk, ['L', 'P'], true)
    ) {
        $error = "Lengkapi data siswa dengan benar.";
    } else {
        try {
            $stmt = mysqli_prepare(
                $koneksi,
                "INSERT INTO t_siswa
                (nis, nisn, nama, jenis_kelamin, tanggal_lahir, alamat, status_aktif, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
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

// Mengambil data siswa aktif.
$result = mysqli_query(
    $koneksi,
    "SELECT nis, nama, jenis_kelamin, alamat
     FROM t_siswa
     WHERE status_aktif = 1
     ORDER BY nis DESC"
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
</head>

<body>
<div class="container">

    <h2>Data Siswa</h2>
    <p>Tambah dan kelola data siswa.</p>

    <?php if (($_GET['pesan'] ?? '') === 'berhasil'): ?>
        <p class="pesan">Data siswa berhasil ditambahkan!</p>
    <?php elseif (($_GET['pesan'] ?? '') === 'nonaktif'): ?>
        <p class="pesan">Data siswa berhasil dinonaktifkan.</p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="error"><?= aman($error) ?></p>
    <?php endif; ?>

    <h3>Tambah Siswa</h3>

    <form method="POST" action="datasiswa.php">
        <label>NIS</label>
        <input type="text" name="nis" required>

        <label>Nama Siswa</label>
        <label>NISN</label>
        <input type="text" name="nisn" required>

        <label>Nama Siswa</label>
        <input type="text" name="nama" required>

        <label>Jenis Kelamin</label>
        <select name="jenis_kelamin" required>
            <option value="">-- Pilih Jenis Kelamin --</option>
            <option value="L">Laki-laki</option>
            <option value="P">Perempuan</option>
        </select>

        <label>Tanggal Lahir</label>
        <input type="date" name="tanggal_lahir" required>

        <label>Alamat</label>
        <textarea name="alamat" required></textarea>

        <button type="submit" name="tambah">
            Tambah Siswa
        </button>
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
                    <td><?= aman($siswa['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan') ?></td>
                    <td><?= aman($siswa['alamat']) ?></td>
                    <td>
                        <form method="POST" action="datasiswa.php"
                              onsubmit="return confirm('Yakin ingin menonaktifkan siswa ini?');">
                            <input type="hidden" name="nis_hapus"
                                   value="<?= aman($siswa['nis']) ?>">

                            <button type="submit" name="hapus"
                                    class="hapus">
                                Nonaktifkan
                            </button>
                        </form>
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
