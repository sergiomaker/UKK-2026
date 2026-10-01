<?php

include "../include/cek_session.php";
include "koneksi.php";


?>

<!DOCTYPE html>
<html>
<head>
    <title>Jenis Pelanggaran</title>
</head>

<body>

<h2>Jenis Pelanggaran</h2>

<a href="dashboard.php">Kembali ke Dashboard</a>

<br><br>

<form method="POST">

    <label>Kode Pelanggaran</label><br>
    <input type="text" name="kode_pelanggaran" required>

    <br><br>

    <label>Nama Pelanggaran</label><br>
    <input type="text" name="nama_pelanggaran" required>

    <br><br>

    <label>Poin</label><br>
    <input type="number" name="poin" required>

    <br><br>

    <button type="submit" name="simpan">
        Simpan
    </button>

</form>

<?php

if (isset($_POST['simpan'])) {

    $kode = $_POST['kode_pelanggaran'];
    $nama = $_POST['nama_pelanggaran'];
    $poin = $_POST['poin'];

    mysqli_query($koneksi, "INSERT INTO pelanggaran
        (kode_pelanggaran, nama_pelanggaran, poin)
        VALUES
        ('$kode', '$nama', '$poin')
    ");

    echo "<p>Data pelanggaran berhasil disimpan.</p>";

}

?>

<hr>

<h3>Daftar Jenis Pelanggaran</h3>

<table border="1" cellpadding="8">

<tr>
    <th>No</th>
    <th>Kode</th>
    <th>Nama Pelanggaran</th>
    <th>Poin</th>
</tr>

<?php

$no = 1;

$query = mysqli_query($koneksi, "SELECT * FROM pelanggaran");

while ($data = mysqli_fetch_assoc($query)) {

?>

<tr>
    <td><?php echo $no++; ?></td>
    <td><?php echo $data['kode_pelanggaran']; ?></td>
    <td><?php echo $data['nama_pelanggaran']; ?></td>
    <td><?php echo $data['poin']; ?></td>
</tr>

<?php } ?>

</table>

</body>
</html>