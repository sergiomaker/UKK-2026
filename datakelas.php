<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Kelas</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 10px #ccc;
        }

        h1 {
            text-align: center;
            color: #1565c0;
        }

        input, select, button {
            width: 100%;
            padding: 10px;
            margin: 7px 0;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            background: #1565c0;
            color: white;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background: #0d47a1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background: #1565c0;
            color: white;
        }

        .hapus {
            background: #e53935;
        }

        .hapus:hover {
            background: #b71c1c;
        }

        .tabel-wrapper {
            overflow-x: auto;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Data Kelas</h1>

        <h3>Tambah Data Kelas</h3>

        <form id="formKelas">
            <input type="text" id="kode"
                placeholder="Kode kelas, contoh: K001" required>

            <input type="text" id="namaKelas"
                placeholder="Nama kelas, contoh: X RPL 1" required>

            <input type="text" id="wali"
                placeholder="Nama wali kelas" required>

            <input type="number" id="jumlah"
                placeholder="Jumlah siswa" min="0" required>

            <select id="jurusan" required>
                <option value="">Pilih jurusan</option>
                <option value="RPL">Rekayasa Perangkat Lunak</option>
                <option value="TKJ">Teknik Komputer dan Jaringan</option>
                <option value="AKL">Akuntansi</option>
                <option value="MPLB">Manajemen Perkantoran</option>
                <option value="Umum">Umum</option>
            </select>

            <button type="submit">Tambah Kelas</button>
        </form>

        <h3>Daftar Kelas</h3>

        <div class="tabel-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode</th>
                        <th>Nama Kelas</th>
                        <th>Wali Kelas</th>
                        <th>Jumlah Siswa</th>
                        <th>Jurusan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody id="daftarKelas"></tbody>
            </table>
        </div>
    </div>

    <script>
        const form = document.getElementById("formKelas");
        const daftar = document.getElementById("daftarKelas");

        let kelas = [];

        function tampilkanKelas() {
            daftar.innerHTML = "";

            kelas.forEach((data, index) => {
                const baris = document.createElement("tr");

                [
                    index + 1,
                    data.kode,
                    data.namaKelas,
                    data.wali,
                    data.jumlah,
                    data.jurusan
                ].forEach(nilai => {
                    const kolom = document.createElement("td");
                    kolom.textContent = nilai;
                    baris.appendChild(kolom);
                });

                const kolomAksi = document.createElement("td");
                const tombol = document.createElement("button");

                tombol.textContent = "Hapus";
                tombol.className = "hapus";

                tombol.addEventListener("click", () => {
                    kelas.splice(index, 1);
                    tampilkanKelas();
                });

                kolomAksi.appendChild(tombol);
                baris.appendChild(kolomAksi);
                daftar.appendChild(baris);
            });
        }

        form.addEventListener("submit", function(event) {
            event.preventDefault();

            const dataBaru = {
                kode: document.getElementById("kode").value.trim(),
                namaKelas: document.getElementById("namaKelas").value.trim(),
                wali: document.getElementById("wali").value.trim(),
                jumlah: document.getElementById("jumlah").value,
                jurusan: document.getElementById("jurusan").value
            };

            if (kelas.some(data =>
                data.kode.toLowerCase() === dataBaru.kode.toLowerCase()
            )) {
                alert("Kode kelas sudah terdaftar!");
                return;
            }

            kelas.push(dataBaru);
            tampilkanKelas();
            form.reset();
        });
    </script>
</body>
</html>