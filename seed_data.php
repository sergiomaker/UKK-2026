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

$mysqli->query("INSERT INTO t_guru
    (nip, nama, email, status_aktif, user_id, created_at, updated_at)
SELECT CONCAT('1989', LPAD(u.id, 6, '0')), u.name, u.email, 1, u.id, NOW(), NOW()
FROM t_users u
LEFT JOIN t_guru g ON g.user_id = u.id
WHERE u.role = 'guru'
  AND u.email IN (
      'guruu@gmail.com',
      'user007@sekolah.test',
      'user008@sekolah.test'
  )
  AND g.id IS NULL;");

$mysqli->query("UPDATE t_users u
    INNER JOIN t_guru g ON g.user_id = u.id
    SET u.name = 'Reihan Muzaki',
        g.nama = 'Reihan Muzaki',
        u.updated_at = NOW(),
        g.updated_at = NOW()
    WHERE u.email = 'guruu@gmail.com'
      AND u.role = 'guru';");

$mysqli->query("INSERT INTO t_kelas
    (nama, tingkat, jurusan, status_aktif, created_at, updated_at)
SELECT 'RPL 2', 'XII', 'RPL', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM t_kelas
    WHERE nama = 'RPL 2' AND tingkat = 'XII' AND jurusan = 'RPL'
);");

$waliKelas = [
    ['guruu@gmail.com', 'XII', 'RPL 2'],
    ['user007@sekolah.test', 'XI', 'RPL 2'],
    ['user008@sekolah.test', 'XI', 'TKJ 1']
];

$stmtWaliKelas = $mysqli->prepare(
    "INSERT INTO t_wali_kelas
        (tahun_ajaran_id, kelas_id, guru_id, tanggal_mulai, tanggal_selesai,
         status_aktif, created_at, updated_at)
     SELECT ta.id, k.id, g.id, ta.tanggal_mulai, ta.tanggal_selesai, 1, NOW(), NOW()
     FROM t_guru g
     JOIN t_kelas k ON k.id = (
         SELECT MAX(k2.id) FROM t_kelas k2
         WHERE k2.nama = ? AND k2.tingkat = ? AND k2.jurusan = ?
     )
     JOIN t_tahun_ajaran ta ON ta.id = (
         SELECT MAX(ta2.id) FROM t_tahun_ajaran ta2 WHERE ta2.nama = ?
     )
     WHERE g.email = ?
       AND NOT EXISTS (
           SELECT 1 FROM t_wali_kelas wk
           WHERE wk.tahun_ajaran_id = ta.id
             AND wk.kelas_id = k.id
             AND wk.guru_id = g.id
       )"
);

foreach ($waliKelas as [$emailGuru, $tingkat, $namaKelas]) {
    $tahunAjaran = '2026/2027';
    $jurusan = 'RPL';
    if ($namaKelas === 'TKJ 1') {
        $jurusan = 'TKJ';
    }
    $stmtWaliKelas->bind_param(
        'sssss',
        $namaKelas,
        $tingkat,
        $jurusan,
        $tahunAjaran,
        $emailGuru
    );
    $stmtWaliKelas->execute();
}
$stmtWaliKelas->close();

$mysqli->query("INSERT INTO t_kelas_siswa
    (siswa_id, tahun_ajaran_id, kelas_id, tanggal_mulai, tanggal_selesai,
     status_aktif, created_at, updated_at)
SELECT s.id, ta.id, k.id, GREATEST(CURDATE(), ta.tanggal_mulai),
       ta.tanggal_selesai, 1, NOW(), NOW()
FROM t_siswa s
JOIN (SELECT id, tanggal_mulai, tanggal_selesai
      FROM t_tahun_ajaran
      WHERE status_aktif = 1
        AND CURDATE() BETWEEN tanggal_mulai AND tanggal_selesai
      ORDER BY id DESC LIMIT 1) ta
JOIN (SELECT id FROM t_kelas
      WHERE nama = 'RPL 2' AND tingkat = 'XII' AND jurusan = 'RPL'
        AND status_aktif = 1
      ORDER BY id DESC LIMIT 1) k
WHERE s.status_aktif = 1
  AND NOT EXISTS (
      SELECT 1 FROM t_kelas_siswa ks
      WHERE ks.siswa_id = s.id
        AND ks.tahun_ajaran_id = ta.id
        AND ks.status_aktif = 1
  );");

foreach (['t_siswa', 't_guru', 't_wali_kelas'] as $table) {
    $res = $mysqli->query("SELECT COUNT(*) AS c FROM `$table`");
    $row = $res->fetch_assoc();
    echo $table . ': ' . $row['c'] . PHP_EOL;
}

$mysqli->close();
