<?php
require_once __DIR__ . '/cek_session.php';

function aman($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if (isset($_POST['tambah'])) {
    $nama = trim($_POST['nama_kategori'] ?? '');
    $deskripsi = trim($_POST['keterangan'] ?? '');

    if ($nama !== '') {
        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO t_pelanggaran_kategori (nama, deskripsi, status_aktif, created_at, updated_at)
             VALUES (?, ?, 1, NOW(), NOW())"
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $nama, $deskripsi);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("Location: kategori%20pelanggaran.php?pesan=tambah");
            exit;
        }
    }
}

if (isset($_POST['edit'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama_kategori'] ?? '');
    $deskripsi = trim($_POST['keterangan'] ?? '');
    $status = (int)($_POST['status_aktif'] ?? 1);

    if ($id > 0 && $nama !== '') {
        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE t_pelanggaran_kategori
             SET nama = ?, deskripsi = ?, status_aktif = ?
             WHERE id = ?"
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssii", $nama, $deskripsi, $status, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("Location: kategori%20pelanggaran.php?pesan=edit");
            exit;
        }
    }
}

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];

    if ($id > 0) {
        $stmt = mysqli_prepare($koneksi, "DELETE FROM t_pelanggaran_kategori WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    header("Location: kategori%20pelanggaran.php?pesan=hapus");
    exit;
}

$result = mysqli_query(
    $koneksi,
    "SELECT id, nama, deskripsi, status_aktif FROM t_pelanggaran_kategori ORDER BY id DESC"
);

$pesan = $_GET['pesan'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kategori Pelanggaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="pencarian_tabel.js" defer></script>
</head>

<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold">Kategori Pelanggaran</h3>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
                + Tambah Kategori
            </button>
        </div>

        <?php if ($pesan !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show">
                Data kategori berhasil diproses!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-primary">
                            <tr>
                                <th>No</th>
                                <th>Nama Kategori</th>
                                <th>Deskripsi</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; ?>
                            <?php while ($row = mysqli_fetch_assoc($result ?: false)): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= aman($row['nama'] ?? '') ?></td>
                                    <td><?= aman($row['deskripsi'] ?? '') ?></td>
                                    <td>
                                        <span class="badge bg-<?= ((int)($row['status_aktif'] ?? 1) === 1 ? 'success' : 'secondary') ?>">
                                            <?= (int)($row['status_aktif'] ?? 1) === 1 ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#edit<?= (int)($row['id'] ?? 0); ?>">
                                            Edit
                                        </button>
                                        <a href="kategori%20pelanggaran.php?hapus=<?= (int)($row['id'] ?? 0); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus data ini?')">
                                            Hapus
                                        </a>
                                    </td>
                                </tr>

                                <div class="modal fade" id="edit<?= (int)($row['id'] ?? 0); ?>">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Kategori Pelanggaran</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="id" value="<?= (int)($row['id'] ?? 0); ?>">

                                                    <label class="form-label">Nama Kategori</label>
                                                    <input type="text" name="nama_kategori" class="form-control mb-3" value="<?= aman($row['nama'] ?? '') ?>" required>

                                                    <label class="form-label">Keterangan</label>
                                                    <textarea name="keterangan" class="form-control"><?= aman($row['deskripsi'] ?? '') ?></textarea>

                                                    <label class="form-label mt-3">Status</label>
                                                    <select name="status_aktif" class="form-select">
                                                        <option value="1" <?= ((int)($row['status_aktif'] ?? 1) === 1) ? 'selected' : '' ?>>Aktif</option>
                                                        <option value="0" <?= ((int)($row['status_aktif'] ?? 1) === 0) ? 'selected' : '' ?>>Nonaktif</option>
                                                    </select>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit" class="btn btn-primary">Simpan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalTambah">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="kategori%20pelanggaran.php">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Kategori Pelanggaran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" name="nama_kategori" class="form-control mb-3" placeholder="Contoh: Kedisiplinan" required>

                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control" placeholder="Masukkan keterangan"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
```