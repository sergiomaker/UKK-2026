<?php
require_once __DIR__ . '/cek_session.php';

function aman($teks)
{
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

$error = "";

// ================= TAMBAH DATA GURU =================
if (isset($_POST['tambah'])) {
    $nip = trim($_POST['nip'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $statusAktif = (int)($_POST['status_aktif'] ?? 1);
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($nip === '' || $nama === '' || $email === '') {
        $error = "Mohon lengkapi data guru dengan benar.";
    } else {
        $sql = "INSERT INTO t_guru
                (nip, nama, email, status_aktif, user_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = mysqli_prepare($koneksi, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "ssssi",
                $nip,
                $nama,
                $email,
                $statusAktif,
                $userId
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: dataguru.php?pesan=berhasil");
                exit;
            } else {
                $error = "Gagal menambahkan guru: " .
                    mysqli_stmt_error($stmt);
            }

            mysqli_stmt_close($stmt);
        } else {
            $error = "Query tambah gagal: " .
                mysqli_error($koneksi);
        }
    }
}

// ================= HAPUS DATA GURU =================
if (isset($_POST['hapus'])) {
    $nipHapus = trim($_POST['nip_hapus'] ?? '');

    if ($nipHapus !== '') {
        $sql = "DELETE FROM t_guru WHERE nip = ?";
        $stmt = mysqli_prepare($koneksi, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $nipHapus);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: dataguru.php?pesan=hapus");
                exit;
            } else {
                $error = "Gagal menghapus data guru: " .
                    mysqli_stmt_error($stmt);
            }

            mysqli_stmt_close($stmt);
        } else {
            $error = "Query hapus gagal: " .
                mysqli_error($koneksi);
        }
    }
}

// ================= MENAMPILKAN DATA GURU =================
$result = mysqli_query(
    $koneksi,
    "SELECT id, nip, nama, email, status_aktif, user_id, created_at
     FROM t_guru
     ORDER BY id DESC"
);


if (!$result) {
    $error = "Gagal menampilkan data guru: " .
        mysqli_error($koneksi);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Guru</title>

    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
        }

        h2 {
            color: #1d4ed8;
        }

        input,
        select,
        textarea,
        button {
            padding: 10px;
            margin: 5px 0;
            box-sizing: border-box;
        }

        input,
        select,
        textarea {
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

        .tabel th,
        .tabel td {
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
            background: #fee2e2;
            padding: 10px;
            border-radius: 5px;
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
            <input type="text" name="nip" maxlength="30" required>

            <label>Nama Guru</label>
            <input type="text" name="nama" maxlength="100" required>

            <label>Email</label>
            <input type="email" name="email" maxlength="150" required>

            <label>Status Aktif</label>
            <select name="status_aktif" required>
                <option value="1">Aktif</option>
                <option value="0">Tidak Aktif</option>
            </select>

            <label>User ID</label>
            <input type="number" name="user_id" min="0" value="0">

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
                    <th>Email</th>
                    <th>Status</th>
                    <th>User ID</th>
                    <th>Created At</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($guru = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= aman($guru['nip']) ?></td>
                            <td><?= aman($guru['nama']) ?></td>
                            <td><?= aman($guru['email']) ?></td>
                            <td><?= aman($guru['status_aktif'] ? 'Aktif' : 'Tidak Aktif') ?></td>
                            <td><?= aman($guru['user_id']) ?></td>
                            <td><?= aman($guru['created_at']) ?></td>
                            <td>
                                <form method="POST" action="dataguru.php"
                                    onsubmit="return confirm('Yakin ingin menghapus data guru ini?');">

                                    <input type="hidden"
                                        name="nip_hapus"
                                        value="<?= aman($guru['nip']) ?>">

                                    <button type="submit"
                                        name="hapus"
                                        class="hapus">
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="8">
                            Belum ada data guru.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div>
</body>

</html>