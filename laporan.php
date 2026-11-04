<?php
require_once __DIR__ . '/cek_session.php';

$result = mysqli_query(
    $koneksi,
    "SELECT s.nis, s.nama,
            CASE
                WHEN k.id IS NULL THEN 'Belum ditempatkan'
                ELSE CONCAT_WS(' ', k.tingkat, k.nama)
            END AS kelas
     FROM t_siswa s
     LEFT JOIN t_kelas_siswa ks
       ON ks.id = (
           SELECT ks_terbaru.id
           FROM t_kelas_siswa ks_terbaru
           WHERE ks_terbaru.siswa_id = s.id
             AND ks_terbaru.status_aktif = 1
           ORDER BY ks_terbaru.tanggal_mulai DESC, ks_terbaru.id DESC
           LIMIT 1
       )
     LEFT JOIN t_kelas k ON k.id = ks.kelas_id
     ORDER BY s.nama ASC, s.nis ASC"
);
$dataSiswa = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $dataSiswa[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Data Siswa</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }

        .content {
            margin-left: 0;
            padding: 25px;
        }

        .panel {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 6px #00000010;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
        }

        input,
        select,
        button {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        input {
            flex: 1;
            min-width: 180px;
        }

        button {
            background: #0d6efd;
            color: white;
            border: none;
            cursor: pointer;
        }

        .print-btn {
            background: #198754;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #0d6efd;
            color: white;
        }

        tr:nth-child(even) {
            background: #f8f9fa;
        }

        .jumlah {
            margin-top: 15px;
            font-weight: bold;
        }

        @media print {

            .sidebar,
            .toolbar,
            .no-print {
                display: none !important;
            }

            .content {
                margin: 0;
                padding: 0;
            }

            .panel {
                box-shadow: none;
            }

            body {
                background: white;
            }
        }

        @media (max-width: 650px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>

<body>

    <main class="content">
        <h2>Laporan Data Siswa</h2>
        <p>Halaman laporan data siswa sekolah.</p>

        <section class="panel">
            <h3>Daftar Siswa</h3>

            <div class="toolbar no-print">
                <input
                    type="text"
                    id="cari"
                    placeholder="Cari NIS, nama, atau kelas...">

                <button type="button" class="print-btn"
                    onclick="window.print()">
                    Cetak Laporan
                </button>
            </div>

            <div class="table-wrapper">
                <table id="tabelSiswa">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($dataSiswa) > 0): ?>
                            <?php foreach ($dataSiswa as $i => $siswa): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars((string)$siswa['nis']) ?></td>
                                    <td><?= htmlspecialchars($siswa['nama']) ?></td>
                                    <td><?= htmlspecialchars($siswa['kelas']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">Belum ada data siswa.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <p class="jumlah" id="jumlahData">
                Total data: <?= count($dataSiswa) ?> siswa
            </p>
        </section>
    </main>

    <script>
        const inputCari = document.getElementById('cari');
        const tabel = document.getElementById('tabelSiswa');
        const jumlahData = document.getElementById('jumlahData');

        inputCari.addEventListener('input', function() {
            const kata = this.value.toLowerCase();
            const baris = tabel.querySelectorAll('tbody tr');
            let jumlah = 0;

            baris.forEach(function(tr) {
                const cocok = tr.textContent.toLowerCase().includes(kata);
                tr.style.display = cocok ? '' : 'none';

                if (cocok && tr.cells.length === 4) {
                    jumlah++;
                }
            });

            jumlahData.textContent = 'Total data: ' + jumlah + ' siswa';
        });
    </script>

</body>

</html>