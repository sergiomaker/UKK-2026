
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
    $nama = trim($_POST['nama_siswa'] ?? '');
    $kelas = trim($_POST['kelas'] ?? '');
    $jk = $_POST['jenis_kelamin'] ?? '';
    $alamat = trim($_POST['alamat'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');

    if ($nis === '' || $nama === '' || $kelas === '' ||
        !in_array($jk, ['Laki-laki', 'Perempuan'], true)) {
        $error = "Lengkapi data siswa dengan benar.";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO siswa
            (nis, nama_siswa, kelas, jenis_kelamin, alamat, no_hp)
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            $error = "Query tambah gagal: " . mysqli_error($koneksi);
        } else {
            mysqli_stmt_bind_param(
                $stmt,
                "ssssss",
                $nis,
                $nama,
                $kelas,
                $jk,
                $alamat,
                $no_hp
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: datasiswa.php?pesan=berhasil");
                exit;
            } else {
                $error = "Gagal menyimpan data. Periksa NIS dan struktur tabel.";
            }

            mysqli_stmt_close($stmt);
        }
    }
}

// Menghapus data berdasarkan NIS
if (isset($_POST['hapus'])) {
    $nisHapus = trim($_POST['nis_hapus'] ?? '');

    if ($nisHapus !== '') {
        $stmt = mysqli_prepare(
            $koneksi,
            "DELETE FROM siswa WHERE nis = ?"
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $nisHapus);

            if (!mysqli_stmt_execute($stmt)) {
                $error = "Gagal menghapus data siswa.";
            }

            mysqli_stmt_close($stmt);
        } else {
            $error = "Query hapus gagal: " . mysqli_error($koneksi);
        }
    }

    if ($error === '') {
        header("Location: datasiswa.php?pesan=hapus");
        exit;
    }
}

// Mengambil data siswa tanpa menggunakan kolom ID
$result = mysqli_query(
    $koneksi,
    "SELECT nis, nama, jenis_kelamin, alamat
     FROM t_siswa
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
    <?php elseif (($_GET['pesan'] ?? '') === 'hapus'): ?>
        <p class="pesan">Data siswa berhasil dihapus!</p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="error"><?= aman($error) ?></p>
    <?php endif; ?>

    <h3>Tambah Siswa</h3>

    <form method="POST" action="datasiswa.php">
        <label>NIS</label>
        <input type="text" name="nis" required>

        <label>Nama Siswa</label>
        <input type="text" name="nama_siswa" required>

        <label>Kelas</label>
        <input type="text" name="kelas"
               placeholder="Contoh: X RPL 1" required>

        <label>Jenis Kelamin</label>
        <select name="jenis_kelamin" required>
            <option value="">-- Pilih Jenis Kelamin --</option>
            <option value="Laki-laki">Laki-laki</option>
            <option value="Perempuan">Perempuan</option>
        </select>

        <label>Alamat</label>
        <textarea name="alamat"></textarea>

        <label>Nomor HP</label>
        <input type="text" name="no_hp">

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
                    <td><?= aman($siswa['jenis_kelamin']) ?></td>
                    <td><?= aman($siswa['alamat']) ?></td>
                    <td>
                        <form method="POST" action="datasiswa.php"
                              onsubmit="return confirm('Yakin ingin menghapus siswa ini?');">
                            <input type="hidden" name="nis_hapus"
                                   value="<?= aman($siswa['nis']) ?>">

                            <button type="submit" name="hapus"
                                    class="hapus">
                                Hapus
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>

        <?php else: ?>
            <tr>
                <td colspan="8">Belum ada data siswa.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

</div>
</body>
</html>
