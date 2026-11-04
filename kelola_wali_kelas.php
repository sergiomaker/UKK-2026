<?php
require_once __DIR__ . '/cek_session.php';

mysqli_set_charset($koneksi, "utf8mb4");

function aman($data)
{
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

$pesan = '';
$error = '';

// Tambah data wali kelas
if (isset($_POST['tambah'])) {
    $nip = trim($_POST['nip'] ?? '');
    $nama = trim($_POST['nama_wali_kelas'] ?? '');
    $jk = $_POST['jenis_kelamin'] ?? '';
    $kelas = trim($_POST['kelas'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    if (
        $nip === '' || $nama === '' || $kelas === '' ||
        !in_array($jk, ['Laki-laki', 'Perempuan'], true)
    ) {
        $error = 'NIP, nama, jenis kelamin, dan kelas wajib diisi.';
    } else {
        try {
            $stmt = $koneksi->prepare(
                "INSERT INTO wali_kelas
                (nip, nama_wali_kelas, jenis_kelamin, kelas, no_hp, alamat)
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssssss",
                $nip,
                $nama,
                $jk,
                $kelas,
                $no_hp,
                $alamat
            );
            $stmt->execute();

            header("Location: kelola_wali_kelas.php?pesan=tambah");
            exit;
        } catch (mysqli_sql_exception $e) {
            $error = $e->getCode() == 1062
                ? 'NIP sudah terdaftar.'
                : 'Gagal menyimpan data. Periksa tabel database.';
        }
    }
}

// Edit data wali kelas
if (isset($_POST['edit'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nip = trim($_POST['nip'] ?? '');
    $nama = trim($_POST['nama_wali_kelas'] ?? '');
    $jk = $_POST['jenis_kelamin'] ?? '';
    $kelas = trim($_POST['kelas'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    if (
        $id <= 0 || $nip === '' || $nama === '' || $kelas === '' ||
        !in_array($jk, ['Laki-laki', 'Perempuan'], true)
    ) {
        $error = 'Data edit tidak lengkap atau tidak valid.';
    } else {
        try {
            $stmt = $koneksi->prepare(
                "UPDATE wali_kelas SET
                nip=?, nama_wali_kelas=?, jenis_kelamin=?,
                kelas=?, no_hp=?, alamat=?
                WHERE id=?"
            );
            $stmt->bind_param(
                "ssssssi",
                $nip,
                $nama,
                $jk,
                $kelas,
                $no_hp,
                $alamat,
                $id
            );
            $stmt->execute();

            header("Location: kelola_wali_kelas.php?pesan=edit");
            exit;
        } catch (mysqli_sql_exception $e) {
            $error = $e->getCode() == 1062
                ? 'NIP sudah digunakan oleh wali kelas lain.'
                : 'Gagal memperbarui data.';
        }
    }
}

// Hapus data wali kelas
if (isset($_POST['hapus'])) {
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        $stmt = $koneksi->prepare(
            "DELETE FROM wali_kelas WHERE id=?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    header("Location: kelola_wali_kelas.php?pesan=hapus");
    exit;
}

// Ambil data untuk form edit
$data_edit = null;

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];

    $stmt = $koneksi->prepare(
        "SELECT * FROM wali_kelas WHERE id=?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $data_edit = $stmt->get_result()->fetch_assoc();
}

// Ambil semua data
$result = $koneksi->query(
    "SELECT * FROM wali_kelas ORDER BY id DESC"
);

$pesan = $_GET['pesan'] ?? '';
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
                        <label>NIP</label>
                        <input name="nip" maxlength="30"
                            value="<?= aman($data_edit['nip'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label>Nama Wali Kelas</label>
                        <input name="nama_wali_kelas" maxlength="100"
                            value="<?= aman($data_edit['nama_wali_kelas'] ?? '') ?>"
                            required>
                    </div>

                    <div>
                        <label>Jenis Kelamin</label>
                        <select name="jenis_kelamin" required>
                            <option value="">-- Pilih --</option>
                            <option value="Laki-laki"
                                <?= ($data_edit['jenis_kelamin'] ?? '') === 'Laki-laki'
                                    ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="Perempuan"
                                <?= ($data_edit['jenis_kelamin'] ?? '') === 'Perempuan'
                                    ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                    </div>

                    <div>
                        <label>Kelas</label>
                        <input name="kelas" maxlength="20"
                            placeholder="Contoh: X RPL 1"
                            value="<?= aman($data_edit['kelas'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label>Nomor HP</label>
                        <input name="no_hp" maxlength="20"
                            value="<?= aman($data_edit['no_hp'] ?? '') ?>">
                    </div>

                    <div>
                        <label>Alamat</label>
                        <textarea name="alamat" rows="3"><?= aman($data_edit['alamat'] ?? '') ?></textarea>
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
                            <th>NIP</th>
                            <th>Nama</th>
                            <th>Jenis Kelamin</th>
                            <th>Kelas</th>
                            <th>No. HP</th>
                            <th>Alamat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php $no = 1; ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= aman($row['nip']) ?></td>
                                    <td><?= aman($row['nama_wali_kelas']) ?></td>
                                    <td><?= aman($row['jenis_kelamin']) ?></td>
                                    <td><?= aman($row['kelas']) ?></td>
                                    <td><?= aman($row['no_hp']) ?></td>
                                    <td><?= aman($row['alamat']) ?></td>
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