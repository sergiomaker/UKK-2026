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

$exports = [
    'siswa' => [
        'label' => 'Data Siswa',
        'sql' => "SELECT nis, nisn, nama, jenis_kelamin, tanggal_lahir, alamat,
                         status_aktif, created_at
                  FROM t_siswa ORDER BY nama, nis",
    ],
    'guru' => [
        'label' => 'Data Guru',
        'sql' => "SELECT nip, nama, email, status_aktif, created_at
                  FROM t_guru ORDER BY nama, nip",
    ],
    'kelas' => [
        'label' => 'Data Kelas',
        'sql' => "SELECT tingkat, nama, jurusan, status_aktif, created_at
                  FROM t_kelas ORDER BY tingkat, jurusan, nama, id",
    ],
    'wali_kelas' => [
        'label' => 'Data Wali Kelas',
        'sql' => "SELECT ta.nama AS tahun_ajaran, k.tingkat, k.nama AS kelas,
                         g.nip, g.nama AS nama_guru, wk.tanggal_mulai,
                         wk.tanggal_selesai, wk.status_aktif
                  FROM t_wali_kelas wk
                  LEFT JOIN t_tahun_ajaran ta ON ta.id=wk.tahun_ajaran_id
                  LEFT JOIN t_kelas k ON k.id=wk.kelas_id
                  LEFT JOIN t_guru g ON g.id=wk.guru_id
                  ORDER BY ta.tanggal_mulai DESC, k.tingkat, k.nama",
    ],
    'jenis_pelanggaran' => [
        'label' => 'Jenis Pelanggaran',
        'sql' => "SELECT p.kode, p.nama, k.nama AS kategori, p.poin,
                         p.deskripsi, p.status_aktif
                  FROM t_pelanggaran p
                  LEFT JOIN t_pelanggaran_kategori k ON k.id=p.pelanggaran_kategori_id
                  ORDER BY p.nama",
    ],
    'riwayat_pelanggaran' => [
        'label' => 'Riwayat Pelanggaran',
        'sql' => "SELECT ta.nama AS tahun_ajaran, ps.tanggal, s.nis,
                         ps.nama_siswa, ps.nama_kelas, ps.nama_pelanggaran,
                         ps.poin, ps.nama_guru, ps.keterangan, ps.tindakan, ps.status
                  FROM t_pelanggaran_siswa ps
                  LEFT JOIN t_tahun_ajaran ta ON ta.id=ps.tahun_ajaran_id
                  LEFT JOIN t_siswa s ON s.id=ps.siswa_id
                  ORDER BY ps.tanggal DESC, ps.id DESC",
    ],
];

function aman($nilai)
{
    return htmlspecialchars((string)$nilai, ENT_QUOTES, 'UTF-8');
}

function nilaiCsvAman($nilai)
{
    $nilai = (string)($nilai ?? '');
    if (preg_match('/^[\s]*[=+\-@]/u', $nilai)) {
        return "'" . $nilai;
    }
    return $nilai;
}

$jenis = $_GET['unduh'] ?? '';
if ($jenis !== '') {
    if (!isset($exports[$jenis])) {
        http_response_code(400);
        exit('Jenis ekspor tidak dikenal.');
    }

    try {
        $result = $koneksi->query($exports[$jenis]['sql']);
    } catch (mysqli_sql_exception $e) {
        error_log('export_data.php: ' . $e->getMessage());
        http_response_code(500);
        exit('Data gagal diekspor. Periksa log server.');
    }

    $namaFile = $jenis . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $namaFile . '"');
    header('X-Content-Type-Options: nosniff');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        http_response_code(500);
        exit('File ekspor tidak dapat dibuat.');
    }

    fwrite($output, "\xEF\xBB\xBF");
    $fields = $result->fetch_fields();
    fputcsv($output, array_map(static fn($field) => $field->name, $fields));
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, array_map('nilaiCsvAman', array_values($row)));
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ekspor Data</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; background: #f1f5f9; color: #17232c; font-family: Arial, sans-serif; }
        main { max-width: 900px; margin: auto; }
        section { margin-bottom: 20px; padding: 22px; border-radius: 10px; background: white; box-shadow: 0 2px 8px #0000000d; }
        h1 { color: #1769aa; }
        .exports { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        a { display: block; padding: 14px; border-radius: 7px; background: #1769aa; color: white; text-align: center; text-decoration: none; }
        a:hover { background: #12568d; }
        @media (max-width: 600px) { body { padding: 12px; } .exports { grid-template-columns: 1fr; } }
    </style>
</head>

<body>
    <main>
        <section>
            <h1>Ekspor Data</h1>
            <p>Unduh data dalam format CSV. File dapat dibuka menggunakan Excel atau aplikasi spreadsheet.</p>
            <div class="exports">
                <?php foreach ($exports as $key => $export): ?>
                    <a href="export_data.php?unduh=<?= aman($key) ?>">
                        Unduh <?= aman($export['label']) ?> (CSV)
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</body>
</html>
