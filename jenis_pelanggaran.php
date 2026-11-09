<?php
require_once __DIR__ . '/cek_session.php';

if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header(($_SESSION['role'] ?? '') === 'guru'
        ? 'Location: guru_dashboard.php'
        : 'Location: login.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
mysqli_set_charset($koneksi, 'utf8mb4');

function aman($nilai)
{
    return htmlspecialchars((string)($nilai ?? ''), ENT_QUOTES, 'UTF-8');
}

$_SESSION['csrf_jenis_pelanggaran'] ??= bin2hex(random_bytes(32));
$error = '';
$dataEdit = null;
$totalData = 0;
$jumlahAktif = 0;
$jumlahNonaktif = 0;
$jenisList = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);

        if (!hash_equals($_SESSION['csrf_jenis_pelanggaran'], $_POST['csrf_token'] ?? '')) {
            $error = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
        } else {
            $kategoriId = (int)($_POST['kategori_id'] ?? 0);
            $kode = trim($_POST['kode'] ?? '');
            $nama = trim($_POST['nama'] ?? '');
            $poin = filter_var($_POST['poin'] ?? null, FILTER_VALIDATE_INT);
            $deskripsi = trim($_POST['deskripsi'] ?? '');
            $statusAktif = (int)($_POST['status_aktif'] ?? 1);

            if (
                $kategoriId <= 0 || $nama === '' || strlen($nama) > 150 ||
                strlen($kode) > 30 || $poin === false || $poin < 0 ||
                strlen($deskripsi) > 5000 ||
                !in_array($statusAktif, [0, 1], true) ||
                (isset($_POST['simpan']) && $id <= 0)
            ) {
                $error = 'Lengkapi nama, kategori, dan poin dengan data yang valid.';
            } else {
                if (isset($_POST['simpan'])) {
                    $stmt = $koneksi->prepare(
                        "UPDATE t_pelanggaran
                         SET pelanggaran_kategori_id=?, kode=?, nama=?, poin=?,
                             deskripsi=?, status_aktif=?, updated_at=NOW()
                         WHERE id=?"
                    );
                    $stmt->bind_param(
                        'issisii',
                        $kategoriId,
                        $kode,
                        $nama,
                        $poin,
                        $deskripsi,
                        $statusAktif,
                        $id
                    );
                    $stmt->execute();
                    header('Location: dashboard.php?page=jenis_pelanggaran&pesan=edit');
                } else {
                    $stmt = $koneksi->prepare(
                        "INSERT INTO t_pelanggaran
                            (pelanggaran_kategori_id, kode, nama, poin, deskripsi,
                             status_aktif, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
                    );
                    $stmt->bind_param(
                        'issisi',
                        $kategoriId,
                        $kode,
                        $nama,
                        $poin,
                        $deskripsi,
                        $statusAktif
                    );
                    $stmt->execute();
                    header('Location: dashboard.php?page=jenis_pelanggaran&pesan=tambah');
                }
                exit;
            }
        }
    }

    if (isset($_GET['edit'])) {
        $id = (int)$_GET['edit'];
        $stmt = $koneksi->prepare("SELECT * FROM t_pelanggaran WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $dataEdit = $stmt->get_result()->fetch_assoc();
    }

    $kategoriList = $koneksi->query(
        "SELECT id, nama FROM t_pelanggaran_kategori ORDER BY status_aktif DESC, nama"
    )->fetch_all(MYSQLI_ASSOC);
    $ringkasanJenis = $koneksi->query(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status_aktif = 1), 0) AS aktif,
                COALESCE(SUM(status_aktif = 0), 0) AS nonaktif
         FROM t_pelanggaran"
    )->fetch_assoc();
    $totalData = (int)$ringkasanJenis['total'];
    $jumlahAktif = (int)$ringkasanJenis['aktif'];
    $jumlahNonaktif = (int)$ringkasanJenis['nonaktif'];
    $jenisList = $koneksi->query(
        "SELECT p.id, p.kode, p.nama, p.poin, p.deskripsi, p.status_aktif,
                k.nama AS nama_kategori
         FROM t_pelanggaran p
         LEFT JOIN t_pelanggaran_kategori k ON k.id=p.pelanggaran_kategori_id
         ORDER BY p.id DESC"
    )->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) {
    error_log('jenis_pelanggaran.php: ' . $e->getMessage());
    $error = 'Data jenis pelanggaran gagal diproses. Periksa database dan coba lagi.';
    $kategoriList = $kategoriList ?? [];
}

$pesan = [
    'tambah' => 'Jenis pelanggaran berhasil ditambahkan.',
    'edit' => 'Jenis pelanggaran berhasil diperbarui.',
][$_GET['pesan'] ?? ''] ?? '';
$formData = $dataEdit ?? $_POST;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Jenis Pelanggaran</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #f1f5f9; color: #17232c; font-family: Arial, sans-serif; }
        .container { width: 100%; max-width: none; margin: 0; }
        .panel { margin-bottom: 20px; padding: 22px; border-radius: 10px; background: #fff; box-shadow: 0 2px 8px #0000000d; }
        h1, h2 { color: #1769aa; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        input, select, textarea, button { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; }
        textarea { min-height: 80px; }
        button { border: 0; background: #1769aa; color: #fff; cursor: pointer; }
        .wide { grid-column: 1 / -1; }
        .alert { padding: 12px; border-radius: 6px; background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #dce4e8; text-align: left; }
        th { background: #1769aa; color: #fff; }
        a { color: #1769aa; }
        a.btn-edit {
            display: inline-block;
            padding: 8px 10px;
            border-radius: 4px;
            background: #ffc107;
            color: #212529;
            font-size: 13px;
            line-height: 1.2;
            text-decoration: none;
        }
        a.btn-edit:hover { background: #e0a800; color: #212529; }
        .aksi-buttons { white-space: nowrap; }
        .table-empty { padding: 24px; text-align: center; color: #64748b; }
        @media (max-width: 650px) {
            .panel { padding: 16px; }
            .grid { grid-template-columns: 1fr; }
            .wide { grid-column: auto; }
            th, td { padding: 8px; font-size: 13px; }
        }
    </style>
</head>

<body>
    <main class="container">
        <section class="panel">
            <h1>Kelola Jenis Pelanggaran</h1>
            <p>Atur jenis pelanggaran dan poin. Jenis yang sudah tercatat dapat dinonaktifkan tanpa menghapus riwayatnya.</p>
            <?php if ($error !== ''): ?><p class="alert error"><?= aman($error) ?></p><?php endif; ?>
            <?php if ($pesan !== ''): ?><p class="alert"><?= aman($pesan) ?></p><?php endif; ?>

            <h2><?= $dataEdit ? 'Edit Jenis Pelanggaran' : 'Tambah Jenis Pelanggaran' ?></h2>
            <?php if (!$kategoriList): ?>
                <p class="alert error">Tambahkan kategori pelanggaran terlebih dahulu melalui menu Kategori Pelanggaran.</p>
            <?php endif; ?>
            <form method="POST" action="jenis_pelanggaran.php<?= $dataEdit ? '?edit=' . (int)$dataEdit['id'] : '' ?>">
                <input type="hidden" name="csrf_token" value="<?= aman($_SESSION['csrf_jenis_pelanggaran']) ?>">
                <?php if ($dataEdit): ?>
                    <input type="hidden" name="id" value="<?= (int)$dataEdit['id'] ?>">
                <?php endif; ?>
                <div class="grid">
                    <input name="kode" maxlength="30" placeholder="Kode, contoh: PG-001"
                        value="<?= aman($formData['kode'] ?? '') ?>">
                    <input name="nama" maxlength="150" placeholder="Nama jenis pelanggaran"
                        value="<?= aman($formData['nama'] ?? '') ?>" required>
                    <select name="kategori_id" required>
                        <option value="">Pilih kategori</option>
                        <?php foreach ($kategoriList as $kategori): ?>
                            <option value="<?= (int)$kategori['id'] ?>"
                                <?= (int)($formData['pelanggaran_kategori_id'] ?? $formData['kategori_id'] ?? 0) === (int)$kategori['id'] ? 'selected' : '' ?>>
                                <?= aman($kategori['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="poin" min="0" placeholder="Poin"
                        value="<?= aman($formData['poin'] ?? '') ?>" required>
                    <textarea class="wide" name="deskripsi" maxlength="5000"
                        placeholder="Deskripsi"><?= aman($formData['deskripsi'] ?? '') ?></textarea>
                    <select name="status_aktif">
                        <option value="1" <?= (string)($formData['status_aktif'] ?? '1') === '1' ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= (string)($formData['status_aktif'] ?? '') === '0' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                    <button class="wide" type="submit" name="<?= $dataEdit ? 'simpan' : 'tambah' ?>"
                        <?= !$kategoriList ? 'disabled' : '' ?>>
                        <?= $dataEdit ? 'Simpan Perubahan' : 'Tambah Jenis Pelanggaran' ?>
                    </button>
                </div>
                <?php if ($dataEdit): ?><p><a href="dashboard.php?page=jenis_pelanggaran">Batal edit</a></p><?php endif; ?>
            </form>
        </section>

        <section class="panel">
            <h2>Daftar Jenis Pelanggaran</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>No</th><th>Kode</th><th>Nama</th><th>Kategori</th><th>Poin</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php if ($jenisList): ?>
                            <?php foreach ($jenisList as $index => $jenis): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= aman($jenis['kode']) ?></td>
                                    <td><?= aman($jenis['nama']) ?></td>
                                    <td><?= aman($jenis['nama_kategori'] ?? '-') ?></td>
                                    <td><?= (int)$jenis['poin'] ?></td>
                                    <td><?= (int)$jenis['status_aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></td>
                                    <td>
                                        <div class="aksi-buttons">
                                            <a class="btn-edit" href="jenis_pelanggaran.php?edit=<?= (int)$jenis['id'] ?>">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td class="table-empty" colspan="7">Belum ada data jenis pelanggaran.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
