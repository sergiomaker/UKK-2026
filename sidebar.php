
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modern Sidebar</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background: #f1f5f9;
}

/* Sidebar */
.sidebar {
    width: 250px;
    height: 100vh;
    background: #111827;
    color: white;
    position: fixed;
    left: 0;
    top: 0;
    padding: 20px;
    transition: 0.3s;
}

.sidebar h2 {
    text-align: center;
    margin-bottom: 35px;
    color: #38bdf8;
}

.sidebar ul {
    list-style: none;
}

.sidebar ul li {
    margin-bottom: 10px;
}

.sidebar ul li a {
    display: block;
    color: #cbd5e1;
    text-decoration: none;
    padding: 13px;
    border-radius: 8px;
    transition: 0.3s;
}

.sidebar ul li a:hover,
.sidebar ul li a.active {
    background: #2563eb;
    color: white;
}

/* Main content */
.main {
    margin-left: 250px;
    padding: 25px;
    transition: 0.3s;
}

.navbar {
    background: white;
    padding: 15px 20px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 10px #00000010;
}

.toggle {
    border: none;
    background: #2563eb;
    color: white;
    padding: 10px 15px;
    border-radius: 6px;
    cursor: pointer;
}

.content {
    background: white;
    margin-top: 25px;
    padding: 25px;
    border-radius: 10px;
}

/* Sidebar collapse */
.sidebar.hide {
    left: -250px;
}

.main.full {
    margin-left: 0;
}

@media(max-width: 768px) {
    .sidebar {
        left: -250px;
        z-index: 10;
    }

    .sidebar.show {
        left: 0;
    }

    .main {
        margin-left: 0;
    }
}
</style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <h2>My Dashboard</h2>

    <ul>
        <li><a href="#" class="active">🏠 Dashboard</a></li>
        <li><a href="#">👤 Profile</a></li>
        <li><a href="#">📊 Analytics</a></li>
        <li><a href="#">📁 Projects</a></li>
        <li><a href="#">⚙️ Settings</a></li>
        <li><a href="#">🚪 Logout</a></li>
    </ul>
</div>

<div class="main" id="main">
    <div class="navbar">
        <button class="toggle" onclick="toggleSidebar()">
            ☰
        </button>
        <h3>Dashboard</h3>
    </div>

    <div class="content">
        <h2>Selamat Datang!</h2>
        <p>Ini adalah halaman utama dashboard kamu.</p>
    </div>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    const main = document.getElementById("main");

    if (window.innerWidth <= 768) {
        sidebar.classList.toggle("show");
    } else {
        sidebar.classList.toggle("hide");
        main.classList.toggle("full");
    }
}
</script>

</body>
</html>
