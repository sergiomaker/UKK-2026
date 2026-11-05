<?php
session_start();
require_once __DIR__ . '/koneksi.php';

// Pastikan form dikirim menggunakan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

// Terima email, username email, nama, atau NIP guru sebagai identitas login.
$identitas = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validasi input jika kosong
if ($identitas === '' || $password === '') {
    header('Location: login.php?error=kosong&username=' . rawurlencode($identitas));
    exit;
}

mysqli_set_charset($koneksi, 'utf8mb4');

$query = mysqli_prepare(
    $koneksi,
    "SELECT u.id, u.email, u.name, u.role, u.password
     FROM t_users u
     LEFT JOIN t_guru g ON g.user_id = u.id
     WHERE u.role IN ('admin', 'guru')
       AND (
           u.email = ?
           OR u.name = ?
           OR SUBSTRING_INDEX(u.email, '@', 1) = ?
           OR g.nip = ?
       )
     ORDER BY CASE
         WHEN u.email = ? THEN 0
         WHEN g.nip = ? THEN 1
         ELSE 2
     END, u.id
     LIMIT 1"
);

if (!$query) {
    error_log('proses_login.php: ' . mysqli_error($koneksi));
    http_response_code(500);
    exit('Login tidak dapat diproses. Silakan coba lagi.');
}

mysqli_stmt_bind_param(
    $query,
    'ssssss',
    $identitas,
    $identitas,
    $identitas,
    $identitas,
    $identitas,
    $identitas
);
mysqli_stmt_execute($query);

$result = mysqli_stmt_get_result($query);
$user = mysqli_fetch_assoc($result);

// Periksa apakah user ditemukan dan verifikasi password-nya
if ($user && password_verify($password, $user['password'])) {

    // Buat session baru untuk keamanan
    session_unset();
    session_regenerate_id(true);

    $_SESSION['id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['name'] = $user['name'] ?? '';
    $_SESSION['role'] = $user['role'] ?? 'admin';

    // Arahkan guru ke dashboard khusus, sementara peran lain ke dashboard utama.
    $tujuan = match ($_SESSION['role']) {
        'guru' => 'guru_dashboard.php',
        'admin' => 'dashboard.php',
        default => 'login.php?error=gagal',
    };
    header("Location: " . $tujuan);
    exit;

} else {
    // Jika login gagal (email atau password salah)
    header('Location: login.php?error=gagal&username=' . rawurlencode($identitas));
    exit;
}
?>