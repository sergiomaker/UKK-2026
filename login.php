
<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Aplikasi Sekolah</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #0d47a1, #42a5f5);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-box {
            background: white;
            padding: 35px;
            border-radius: 15px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 10px 30px #0003;
        }

        h2 { text-align: center; color: #1565c0; }

        p {
            text-align: center;
            color: #666;
            font-size: 14px;
        }

        label {
            display: block;
            margin-top: 18px;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-top: 7px;
            border: 1px solid #ccc;
            border-radius: 7px;
        }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 12px;
            background: #1565c0;
            color: white;
            border: none;
            border-radius: 7px;
            cursor: pointer;
        }

        button:hover { background: #0d47a1; }

        .error {
            background: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <h2>Login Admin</h2>
        <p>Aplikasi Sistem Informasi Sekolah</p>

        <?php if ($error === 'gagal'): ?>
            <div class="error">Username atau password salah.</div>
        <?php elseif ($error === 'kosong'): ?>
            <div class="error">Username dan password wajib diisi.</div>
        <?php endif; ?>

        <form action="proses_login.php" method="POST">
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   autocomplete="username" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   autocomplete="current-password" required>

            <button type="submit">Masuk</button>
        </form>
    </div>
</body>
</html>