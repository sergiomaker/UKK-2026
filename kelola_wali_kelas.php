
<?php
require_once __DIR__ . '/cek_session.php';

mysqli_set_charset($koneksi, "utf8mb4");

function aman($data) {
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

    if ($nip === '' || $nama === '' || $kelas === '' ||
        !in_array($jk, ['Laki-laki', 'Perempuan'], true)) {
        $error = 'NIP, nama, jenis kelamin, dan kelas wajib diisi.';
    } else {
        try {
            $stmt = $koneksi->prepare(
                "INSERT INTO wali_kelas
                (nip, nama_wali_kelas, jenis_kelamin, kelas, no_hp, alamat)
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssssss", $nip, $nama, $jk, $kelas, $no_hp, $alamat
            );
            $stmt->execute();

            header("Location: wali_kelas.php?pesan=tambah");
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

    if ($id <= 0 || $nip === '' || $nama === '' || $kelas === '' ||
        !in_array($jk, ['Laki-laki', 'Perempuan'], true)) {
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
                "ssssssi", $nip, $nama, $jk, $kelas,
                $no_hp, $alamat, $id
            );
            $stmt->execute();

            header("Location: wali_kelas.php?pesan=edit");
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
            "DELETE FROM t_wali_kelas WHERE id=?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    header("Location: wali_kelas.php?pesan=hapus");
    exit;
}

// Ambil data untuk form edit
$data_edit = null;

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];

    $stmt = $koneksi->prepare(
        "SELECT * FROM t_wali_kelas WHERE id=?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $data_edit = $stmt->get_result()->fetch_assoc();
}

// Ambil semua data
$result = $koneksi->query(
    "SELECT * FROM t_wali_kelas ORDER BY id DESC"
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
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #F1F5F9;
    color: #1E293B;
}

.sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    width: 220px;
    padding: 25px 15px;
    background: #1E293B;
    color: white;
}

.sidebar h2 {
    text-align: center;
    font-size: 20px;
    margin-bottom: 30px;
}

.sidebar a {
    display: block;
    padding: 12px;
    margin-bottom: 8px;
    color: white;
    text-decoration: none;
    border-radius: 7px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #3B82F6;
}

.main {
    margin-left: 220px;
    padding: 30px;
}

.card {
    background: white;
    padding: 22px;
    margin-bottom: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 12px #0000000D;
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

input, select, textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #CBD5E1;
    border-radius: 7px;
    font: inherit;
}

button, .btn {
    display: inline-block;
    border: none;
    border-radius: 6px;
    padding: 10px 14px;
    background: #1E293B;
    color: white;
    cursor: pointer;
    text-decoration: none;
    font-size: 13px;
}

button:hover, .btn:hover { background: #3B82F6; }
.btn-edit { background: #3B82F6; }
.btn-hapus { background: #DC2626; }

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

.table-wrap { overflow-x: auto; }

table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    border-bottom: 1px solid #E2E8F0;
    text-align: left;
    font-size: 14px;
}

th { background: #1E293B; color: white; }

.aksi { display: flex; gap: 6px; }

@media (max-width: 700px) {
    .sidebar {
        position: static;
        width: 100%;
    }
    .sidebar a { display: inline-block; }
    .main { margin-left: 0; padding: 15px; }
    .form-grid { grid-template-columns: 1fr; }
}
</style>
</head>

<body>

<div class="sidebar">
    <h2>ADMIN SEKOLAH</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="data_siswa.php">Data Siswa</a>
    <a href="data_guru.php">Data Guru</a>
    <a href="wali_kelas.php" class="active">Wali Kelas</a>
    <a href="logout.php">Logout</a>
</div>

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

<form method="POST" action="wali_kelas.php">
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
        <a href="wali_kelas.php" class="btn">Batal</a>
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
                   href="wali_kelas.php?edit=<?= (int)$row['id'] ?>">
                   Edit
                </a>

                <form method="POST" action="wali_kelas.php"
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

