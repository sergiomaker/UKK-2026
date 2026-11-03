<?php
//buat_user_admin.php
// jalankan file ini satukali saja lewat browser untuk membuat user awal
include 'koneksi.php';

$name = 'Administator';
$email = 'admin@gmail.com';
$password = password_hash('admin123', PASSWORD_DEFAULT);
$role = 'admin';

$sql ="INSERT INTO t_users(name, email, password, role) VALUES('$name', '$email', '$password', '$role')";

if (mysqli_query($koneksi, $sql)) {
    echo 'User admin berhasil di buat, silahkan hapus file ini';
} else{
    echo 'Gagal membuat user: '. mysqli_error($koneksi);
}
?>