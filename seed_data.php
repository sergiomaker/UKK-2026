<?php
$mysqli = new mysqli('localhost', 'root', '', 'db_ukk_2026');
if ($mysqli->connect_error) {
    die('DB_ERR: ' . $mysqli->connect_error);
}

$mysqli->query("INSERT INTO t_siswa (nis, nisn, nama, jenis_kelamin, tanggal_lahir, alamat, status_aktif, created_at, updated_at) VALUES
('20260001', '0001', 'Ahmad Fauzan', 'L', '2009-04-15', 'Bogor', 1, NOW(), NOW()),
('20260002', '0002', 'Ahmad Setiawan', 'L', '2009-05-20', 'Depok', 1, NOW(), NOW()),
('20260003', '0003', 'Alya Aditya', 'P', '2009-02-12', 'Bekasi', 1, NOW(), NOW()),
('20260004', '0004', 'Alya Kurniawan', 'P', '2009-01-18', 'Bandung', 1, NOW(), NOW()),
('20260005', '0005', 'Bagas Nugraha', 'L', '2009-09-10', 'Cirebon', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE nama = VALUES(nama), updated_at = NOW();");

$mysqli->query("INSERT INTO t_guru (nip, nama, email, status_aktif, user_id, created_at, updated_at) VALUES
('19890001', 'Siti Nurhayati', 'siti.nurhayati@gmail.com', 1, 1, NOW(), NOW()),
('19890002', 'Andi Prasetyo', 'andi.prasetyo@gmail.com', 1, 2, NOW(), NOW()),
('19890003', 'Nia Rahmawati', 'nia.rahmawati@gmail.com', 1, 3, NOW(), NOW())
ON DUPLICATE KEY UPDATE nama = VALUES(nama), updated_at = NOW();");

$mysqli->query("INSERT INTO wali_kelas (nip, nama_wali_kelas, jenis_kelamin, kelas, no_hp, alamat, created_at, updated_at) VALUES
('13133', 'Edo', 'Laki-laki', 'XI RPL 2', '23441414', 'Bogor', NOW(), NOW()),
('13134', 'Rina', 'Perempuan', 'XI RPL 1', '08123456789', 'Jakarta', NOW(), NOW())
ON DUPLICATE KEY UPDATE nama_wali_kelas = VALUES(nama_wali_kelas), updated_at = NOW();");

foreach (['t_siswa', 't_guru', 'wali_kelas'] as $table) {
    $res = $mysqli->query("SELECT COUNT(*) AS c FROM `$table`");
    $row = $res->fetch_assoc();
    echo $table . ': ' . $row['c'] . PHP_EOL;
}

$mysqli->close();
