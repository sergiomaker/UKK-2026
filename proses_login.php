<?php
session_start();
require_once __DIR__ . '/koneksi.php';

// Pastikan form dikirim menggunakan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

// Ambil data email dan password dari form login
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validasi input jika kosong
if ($email === '' || $password === '') {
    header("Location: login.php?error=kosong");
    exit;
}

// Cari berdasarkan kolom 'email' di tabel t_users menggunakan prepared statement yang aman
$query = mysqli_prepare($koneksi,
    "SELECT * FROM t_users WHERE email = ? LIMIT 1"
);

if (!$query) {
    die("Query error: " . mysqli_error($koneksi));
}

mysqli_stmt_bind_param($query, "s", $email);
mysqli_stmt_execute($query);

$result = mysqli_stmt_get_result($query);
$user = mysqli_fetch_assoc($result);

// Periksa apakah user ditemukan dan verifikasi password-nya
if ($user && password_verify($password, $user['password'])) {

    // Buat session baru untuk keamanan
    session_regenerate_id(true);

    $_SESSION['id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['name'] = $user['name'] ?? '';
    $_SESSION['role'] = $user['role'] ?? 'admin';

    // Jika berhasil, masuk ke dashboard
    header("Location: dashboard.php");
    exit;

} else {
    // Jika login gagal (email atau password salah)
    header("Location: login.php?error=gagal");
    exit;
}
?>