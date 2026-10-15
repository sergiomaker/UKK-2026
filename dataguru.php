
<?php
require_once __DIR__ . '/cek_session.php';

function aman($teks) {
    return htmlspecialchars(
        (string) $teks,
        ENT_QUOTES,
        'UTF-8'
    );
}

$error = "";

// MENAMBAHKAN DATA GURU
if (isset($_POST['tambah'])) {
    $nip = trim($_POST['nip'] ?? '');
    $nama = trim($_POST['nama_guru'] ?? '');
    $jk = $_POST['jenis_kelamin'] ?? '';
    $mapel = trim($_POST['mata_pelajaran'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');

    if (
        $nip === '' ||
        $nama === '' ||
        $mapel === '' ||
        !in_array($jk, ['Laki-laki', 'Perempuan'], true)
    ) {
        $error = "Mohon lengkapi data guru dengan benar.";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO guru
            (nip, nama_guru, jenis_kelamin, mata_pelajaran, alamat, no_hp)
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "ssssss",
                $nip,
                $nama,
                $jk,
                $mapel,
                $alamat,
                $no_hp
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: dataguru.php?pesan=berhasil");
                exit;
            } else {
                $error = "Gagal menambahkan guru. Pastikan NIP belum digunakan.";
                mysqli_stmt_close($stmt);
            }
        } else {
            $error = "Query tambah gagal: " . mysqli_error($koneksi);
        }
    }
}

// MENGHAPUS DATA GURU
if (isset($_POST['hapus'])) {
    $nipHapus = trim($_POST['nip_hapus'] ?? '');

    if ($nipHapus !== '') {
        $stmt = mysqli_prepare(
            $koneksi,
            "DELETE FROM guru WHERE nip = ?"
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $nipHapus);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: dataguru.php?pesan=hapus");
                exit;
            } else {
                $error = "Gagal menghapus data guru.";
                mysqli_stmt_close($stmt);
            }
        } else {
            $error = "Query hapus gagal: " . mysqli_error($koneksi);
        }
    }
}

// MENAMPILKAN DATA GURU
$result = mysqli_query( 
    $koneksi,
    "SELECT nip, nama_guru, jenis_kelamin,
            mata_pelajaran, alamat,
     FROM t_guru
     ORDER BY nip DESC"
);

if (!$result) {
    die("Gagal mengambil data guru: " . mysqli_error($koneksi));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Guru</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 1150px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: white;
            background: #2563eb;
        }

        button:hover {
            background: #1d4ed8;
        }

        .hapus {
            background: #dc2626;
        }

        .hapus:hover {
            background: #b91c1c;
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
                min-width: 850px;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <h2>Data Guru</h2>
    <p>Kelola data guru yang terdaftar di sekolah.</p>

    <?php if (($_GET['pesan'] ?? '') === 'berhasil'): ?>
        <p class="pesan">Data guru berhasil ditambahkan!</p>
    <?php elseif (($_GET['pesan'] ?? '') === 'hapus'): ?>
        <p class="pesan">Data guru berhasil dihapus!</p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="error"><?= aman($error) ?></p>
    <?php endif; ?>

    <h3>Tambah Guru</h3>

    <form method="POST" action="dataguru.php">
        <label>NIP</label>
        <input
            type="text"
            name="nip"
            maxlength="30"
            required
        >

        <label>Nama Guru</label>
        <input
            type="text"
            name="nama_guru"
            maxlength="100"
            required
        >

        <label>Jenis Kelamin</label>
        <select name="jenis_kelamin" required>
            <option value="">-- Pilih Jenis Kelamin --</option>
            <option value="Laki-laki">Laki-laki</option>
            <option value="Perempuan">Perempuan</option>
        </select>

        <label>Mata Pelajaran</label>
        <input
            type="text"
            name="mata_pelajaran"
            maxlength="100"
            placeholder="Contoh: Pemrograman Web"
            required
        >

        <label>Alamat</label>
        <textarea name="alamat"></textarea>

        <label>Nomor HP</label>
        <input type="text" name="no_hp" maxlength="20">

        <button type="submit" name="tambah">
            Tambah Guru
        </button>
    </form>

    <h3>Daftar Guru</h3>

    <table class="tabel">
        <thead>
            <tr>
                <th>No.</th>
                <th>NIP</th>
                <th>Nama Guru</th>
                <th>Jenis Kelamin</th>
                <th>Mata Pelajaran</th>
                <th>Alamat</th>
                <th>No. HP</th>
                <th>Aksi</th>
            </tr>
        </thead>

        <tbody>
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php $no = 1; ?>

            <?php while ($guru = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= aman($guru['nip']) ?></td>
                    <td><?= aman($guru['nama_guru']) ?></td>
                    <td><?= aman($guru['jenis_kelamin']) ?></td>
                    <td><?= aman($guru['mata_pelajaran']) ?></td>
                    <td><?= aman($guru['alamat']) ?></td>
                    <td><?= aman($guru['no_hp']) ?></td>
                    <td>
                        <form method="POST" action="dataguru.php"
                              onsubmit="return confirm('Yakin ingin menghapus data guru ini?');">
                            <input
                                type="hidden"
                                name="nip_hapus"
                                value="<?= aman($guru['nip']) ?>"
                            >

                            <button
                                type="submit"
                                name="hapus"
                                class="hapus"
                            >
                                Hapus
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>

        <?php else: ?>
            <tr>
                <td colspan="8">Belum ada data guru.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

</div>
</body>
</html>
