<?php
require_once __DIR__ . '/includes/cek_session.php';
require_once __DIR__ . '/koneksi.php';

// Mengambil data siswa dari database
$stmt = $pdo->query(
    "SELECT id, nis, nama, kelas FROM siswa ORDER BY nama ASC"
);
$dataSiswa = $stmt->fetchAll();
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

        .sidebar {
            width: 230px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: #212529;
            color: white;
            padding: 20px 12px;
        }

        .sidebar h3 {
            margin: 0 0 25px;
            padding-left: 10px;
        }

        .sidebar a {
            display: block;
            padding: 13px 12px;
            margin-bottom: 5px;
            text-decoration: none;
            color: white;
            border-radius: 5px;
            font-size: 14px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #0d6efd;
        }

        .sidebar .logout {
            color: #ff6b75;
            border-top: 1px solid #444;
            margin-top: 25px;
            border-radius: 0;
        }

        .content {
            margin-left: 230px;
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

        input, select, button {
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

        th, td {
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
            .sidebar, .toolbar, .no-print {
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

<aside class="sidebar">
    <h3>My Website</h3>

    <a href="dashboard.php">Dashboard</a>
    <a href="kelas.php">Kelas</a>
    <a href="laporan.php" class="active">Laporan</a>
    <a href="about.php">About Me</a>
    <a href="logout.php" class="logout">Logout</a>
</aside>

<main class="content">
    <h2>Laporan Data Siswa</h2>
    <p>Halaman laporan data siswa sekolah.</p>

    <section class="panel">
        <h3>Daftar Siswa</h3>

        <div class="toolbar no-print">
            <input
                type="text"
                id="cari"
                placeholder="Cari NIS, nama, atau kelas..."
            >

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

    inputCari.addEventListener('input', function () {
        const kata = this.value.toLowerCase();
        const baris = tabel.querySelectorAll('tbody tr');
        let jumlah = 0;

        baris.forEach(function (tr) {
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