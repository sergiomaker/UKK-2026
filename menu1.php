<?php
// Panggil file session dan koneksi dengan jalur relative yang benar dari folder 'proses'
include "../include/cek_session.php";
include "../config/koneksi.php";

// Pengecekan role admin
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) != "admin") {
    header("Location: ../dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Data Siswa</title>
    <!-- Framework Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<!-- Background utama halaman menggunakan utility bg-success-subtle (Hijau Lembut) -->
<body class="bg-success-subtle">

<div class="container-fluid">
    <div class="row min-vh-100">

        <!-- SIDEBAR HIJAU (Murni Utility Bootstrap 5) -->
        <div class="col-md-3 col-lg-2 bg-success text-white p-3">
            
            <!-- HEADER LOGO + TEKS SMK MUHAMADIYAH -->
            <div class="d-flex align-items-center gap-2 mb-4 px-1">
                <!-- Path mengarah keluar dari folder 'proses' ke folder 'Ukk-2026' -->
                <img src="../logo smk muhamadiyah.png" alt="Logo SMK" class="img-fluid" style="width: 42px; height: 42px; object-fit: contain;">
                <h5 class="fw-bold fs-6 mb-0 text-white lh-sm">
                    SMK<br>Muhamadiyah
                </h5>
            </div>

            <!-- Navigasi Menu Bootstrap (nav-pills) -->
            <div class="nav nav-pills flex-column gap-2">
                <a href="../dashboard.php" class="nav-link text-white bg-success-emphasis">Dashboard</a>
                <a href="menu1.php" class="nav-link active bg-dark fw-bold">Data Siswa</a>
                <a href="#" class="nav-link text-white bg-success-emphasis">Data Guru</a>
                <a href="#" class="nav-link text-white bg-success-emphasis">Kelas</a>
                <a href="#" class="nav-link text-white bg-success-emphasis">Laporan</a>
                
                <!-- Line Separator & Logout Tepat di Bawah Laporan -->
                <hr class="text-white my-2">
                <a href="logout.php" class="nav-link text-primary fw-semibold px-2">Logout</a>
            </div>
        </div>

        <!-- KONTEN FORM (Tambah Data Siswa) -->
        <div class="col-md-9 col-lg-10 p-4 p-md-5">
            <div class="card border-0 shadow-sm bg-white rounded-4">
                <div class="card-body p-4 p-md-5">
                    
                    <h3 class="fw-bold mb-4 text-success">Tambah Data Siswa</h3>

                    <form method="POST">

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">NISN</label>
                            <input type="text" name="nisn" class="form-control bg-light" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">NIS</label>
                            <input type="text" name="nis" class="form-control bg-light" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nama</label>
                            <input type="text" name="nama" class="form-control bg-light" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Jenis Kelamin</label>
                            <select name="jenis_kelamin" class="form-select bg-light" required>
                                <option value="" disabled selected>-- Pilih Jenis Kelamin --</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Tanggal Lahir</label>
                            <input type="date" name="tanggal_lahir" class="form-control bg-light" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Alamat</label>
                            <textarea name="alamat" class="form-control bg-light" rows="3" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Status Aktif</label>
                            <select name="status_aktif" class="form-select bg-light" required>
                                <option value="Aktif" selected>Aktif</option>
                                <option value="Tidak Aktif">Tidak Aktif</option>
                            </select>
                        </div>

                        <!-- TOMBOL AKSI -->
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="../dashboard.php" class="btn btn-danger px-4">Batal</a>
                            <button type="submit" name="tambah" class="btn btn-success px-4">Tambah</button>
                        </div>

                    </form>

                    <?php
                    if (isset($_POST['tambah'])) {
                        $nisn          = mysqli_real_escape_string($koneksi, $_POST['nisn']);
                        $nis           = mysqli_real_escape_string($koneksi, $_POST['nis']);
                        $nama          = mysqli_real_escape_string($koneksi, $_POST['nama']);
                        $jenis_kelamin = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
                        $tanggal_lahir = mysqli_real_escape_string($koneksi, $_POST['tanggal_lahir']);
                        $alamat        = mysqli_real_escape_string($koneksi, $_POST['alamat']);
                        $status_aktif  = mysqli_real_escape_string($koneksi, $_POST['status_aktif']);

                        $query = "INSERT INTO siswa (nisn, nis, nama, jenis_kelamin, tanggal_lahir, alamat, status_aktif, created_at) 
                                  VALUES ('$nisn', '$nis', '$nama', '$jenis_kelamin', '$tanggal_lahir', '$alamat', '$status_aktif', NOW())";

                        $simpan = mysqli_query($koneksi, $query);

                        if ($simpan) {
                            echo "<div class='alert alert-success mt-3 mb-0'>Data berhasil disimpan!</div>";
                        } else {
                            echo "<div class='alert alert-danger mt-3 mb-0'>Gagal menyimpan: " . mysqli_error($koneksi) . "</div>";
                        }
                    }
                    ?>

                </div>
            </div>
        </div>

    </div>
</div>

<!-- Framework Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>