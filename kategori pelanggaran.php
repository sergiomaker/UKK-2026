
<?php
require_once __DIR__ . '/cek_session.php';
require_once __DIR__ . '/koneksi.php';

// TAMBAH DATA
if (isset($_POST['tambah'])) {
    $nama = trim($_POST['nama']);
    $tingkat = $_POST['tingkat'];
    $poin = (int) $_POST['poin'];
    $keterangan = trim($_POST['keterangan']);

    $stmt = mysqli_prepare($koneksi,
        "INSERT INTO t_pelanggaran_kategori
        (nama, tingkat, poin, keterangan)
        VALUES (?, ?, ?, ?)");

    mysqli_stmt_bind_param($stmt, "ssis",
        $nama, $tingkat, $poin, $keterangan);

    mysqli_stmt_execute($stmt);

    header("Location: t_pelanggaran_kategori.php?pesan=tambah");
    exit;
}

// EDIT DATA
if (isset($_POST['edit'])) {
    $id = (int) $_POST['id'];
    $nama = trim($_POST['nama_kategori']);
    $tingkat = $_POST['tingkat'];
    $poin = (int) $_POST['poin'];
    $keterangan = trim($_POST['keterangan']);

    $stmt = mysqli_prepare($koneksi,
        "UPDATE t_pelanggaran_kategori
        SET nama_kategori=?, tingkat=?, poin=?, keterangan=?
        WHERE id_kategori=?");

    mysqli_stmt_bind_param($stmt, "ssisi",
        $nama, $tingkat, $poin, $keterangan, $id);

    mysqli_stmt_execute($stmt);

    header("Location:t_pelanggaran_kategori.php?pesan=edit");
    exit;
}

// HAPUS DATA
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    $stmt = mysqli_prepare($koneksi,
        "DELETE FROM t_pelanggaran_kategori WHERE id=?");

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    header("Location: t_pelanggaran_kategori.php?pesan=hapus");
    exit;
}

$data = mysqli_query($koneksi,
    "SELECT * FROM t_pelanggaran_kategori
    ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kategori Pelanggaran</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold">Kategori Pelanggaran</h3>

        <button class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#modalTambah">
            + Tambah Kategori
        </button>
    </div>

    <?php if (isset($_GET['pesan'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Data kategori berhasil diproses!
            <button type="button" class="btn-close"
                data-bs-dismiss="alert"></button>
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
                            <th>Tingkat</th>
                            <th>Poin</th>
                            <th>Keterangan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php $no = 1; ?>
                    <?php while ($row = mysqli_fetch_assoc($data)): ?>

                        <tr>
                            <td><?= $no++; ?></td>

                            <td>
                                <?= htmlspecialchars($row['nama_kategori']); ?>
                            </td>

                            <td>
                                <span class="badge bg-warning text-dark">
                                    <?= htmlspecialchars($row['tingkat']); ?>
                                </span>
                            </td>

                            <td><?= (int) $row['poin']; ?></td>

                            <td>
                                <?= htmlspecialchars($row['keterangan'] ?? ''); ?>
                            </td>

                            <td>
                                <button class="btn btn-warning btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#edit<?= $row['id']; ?>">
                                    Edit
                                </button>

                                <a href="kategoripelanggaran.php?hapus=<?= $row['id']; ?>"
                                    class="btn btn-danger btn-sm"
                                    onclick="return confirm('Yakin ingin menghapus data ini?')">
                                    Hapus
                                </a>
                            </td>
                        </tr>

                        <!-- MODAL EDIT -->
                        <div class="modal fade"
                            id="edit<?= $row['id']; ?>">

                            <div class="modal-dialog">
                                <div class="modal-content">

                                    <form method="POST">

                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                Edit Kategori Pelanggaran
                                            </h5>
                                            <button type="button"
                                                class="btn-close"
                                                data-bs-dismiss="modal"></button>
                                        </div>

                                        <div class="modal-body">

                                            <input type="hidden"
                                                name="id_kategori"
                                                value="<?= $row['id_kategori']; ?>">

                                            <label class="form-label">
                                                Nama Kategori
                                            </label>

                                            <input type="text"
                                                name="nama_kategori"
                                                class="form-control mb-3"
                                                value="<?= htmlspecialchars($row['nama_kategori']); ?>"
                                                required>

                                            <label class="form-label">
                                                Tingkat Pelanggaran
                                            </label>

                                            <select name="tingkat"
                                                class="form-select mb-3" required>
                                                <option value="Ringan"
                                                    <?= $row['tingkat']=='Ringan'?'selected':'' ?>>
                                                    Ringan
                                                </option>
                                                <option value="Sedang"
                                                    <?= $row['tingkat']=='Sedang'?'selected':'' ?>>
                                                    Sedang
                                                </option>
                                                <option value="Berat"
                                                    <?= $row['tingkat']=='Berat'?'selected':'' ?>>
                                                    Berat
                                                </option>
                                            </select>

                                            <label class="form-label">Poin</label>
                                            <input type="number" name="poin"
                                                class="form-control mb-3"
                                                min="0"
                                                value="<?= (int) $row['poin']; ?>"
                                                required>

                                            <label class="form-label">Keterangan</label>
                                            <textarea name="keterangan"
                                                class="form-control"><?= htmlspecialchars($row['keterangan'] ?? ''); ?></textarea>

                                        </div>

                                        <div class="modal-footer">
                                            <button type="button"
                                                class="btn btn-secondary"
                                                data-bs-dismiss="modal">
                                                Batal
                                            </button>

                                            <button type="submit"
                                                name="edit"
                                                class="btn btn-primary">
                                                Simpan
                                            </button>
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

<!-- MODAL TAMBAH -->
<div class="modal fade" id="modalTambah">
    <div class="modal-dialog">
        <div class="modal-content">

            <form method="POST">

                <div class="modal-header">
                    <h5 class="modal-title">
                        Tambah Kategori Pelanggaran
                    </h5>

                    <button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <label class="form-label">Nama Kategori</label>
                    <input type="text" name="nama_kategori"
                        class="form-control mb-3"
                        placeholder="Contoh: Kedisiplinan"
                        required>

                    <label class="form-label">Tingkat Pelanggaran</label>
                    <select name="tingkat" class="form-select mb-3" required>
                        <option value="">-- Pilih Tingkat --</option>
                        <option value="Ringan">Ringan</option>
                        <option value="Sedang">Sedang</option>
                        <option value="Berat">Berat</option>
                    </select>

                    <label class="form-label">Poin Pelanggaran</label>
                    <input type="number" name="poin"
                        class="form-control mb-3"
                        min="0" value="0" required>

                    <label class="form-label">Keterangan</label>
                    <textarea name="keterangan"
                        class="form-control"
                        placeholder="Masukkan keterangan"></textarea>

                </div>

                <div class="modal-footer">
                    <button type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                        Batal
                    </button>

                    <button type="submit" name="tambah"
                        class="btn btn-primary">
                        Simpan
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
```