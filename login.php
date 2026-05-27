<?php
// Memulai sesi agar sistem bisa mengingat status login admin
session_start();

// Menghubungkan halaman ini dengan database aplikasi
require_once 'config/database.php';

// Variabel untuk menyimpan pesan kesalahan login
$error = '';

// Mengecek apakah form login sudah dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Mengambil username yang dimasukkan admin
    $username = $_POST['username'] ?? '';

    // Mengambil password yang dimasukkan admin
    $password = $_POST['password'] ?? '';
    
    // Mengecek apakah username atau password kosong
    if (!$username || !$password) {
        // Pesan error jika ada data yang belum diisi
        $error = 'Isi username & password';
    } else {

        // Mengambil data admin berdasarkan username
        // Data yang diambil: ID admin, password terenkripsi, dan peran admin
        $stmt = $mysqli->prepare('SELECT id, password, role FROM admin WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();

        // Menyimpan hasil query ke variabel
        $stmt->bind_result($id, $hash, $role);
        
        // Mengecek apakah data admin ditemukan dan password cocok
        if ($stmt->fetch() && password_verify($password, $hash)) {

            // Menyimpan ID admin ke dalam session sebagai tanda admin sudah login
            $_SESSION['admin_id'] = $id;

            // Menyimpan peran admin (admin / superadmin) ke dalam session
            $_SESSION['admin_role'] = $role;

            // Mengarahkan admin ke halaman dashboard admin
            header('Location: ./admin.php');
            exit;
        } else {
            // Pesan error jika username atau password salah
            $error = 'Login gagal';
        }

        // Menutup koneksi statement database
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <!-- Pengaturan karakter agar teks tampil dengan benar -->
    <meta charset="UTF-8">

    <!-- Pengaturan agar tampilan responsif di perangkat mobile -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Judul halaman login admin -->
    <title>Admin Login - BISINDO</title>

    <!-- File CSS utama aplikasi -->
    <link rel="stylesheet" href="./assets/css/style.css">

    <!-- File CSS khusus halaman login -->
    <link rel="stylesheet" href="./assets/css/login.css">
</head>
<body class="login-body">
    <!-- Navigasi bagian atas halaman -->
    <nav class="navbar">
        <div class="container">
            <!-- Logo dan link ke halaman utama -->
            <a href="./index.php" class="nav-brand">BISINDO TRANSLATOR</a>

            <!-- Tombol kembali ke halaman utama (desktop) -->
            <div class="nav-links">
                <a href="./index.php" class="btn">Kembali</a>
            </div>
            
            <!-- Tombol menu hamburger untuk tampilan mobile -->
            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>

    <!-- Menu navigasi versi mobile -->
    <div class="mobile-nav" id="mobileNav">
        <a href="./index.php" class="btn">Kembali</a>
    </div>

    <!-- Lapisan gelap untuk menutup layar saat menu mobile aktif -->
    <div class="overlay" id="overlay"></div>

    <!-- Kontainer utama form login -->
    <div class="login-container">
        <div class="login-card">
            <!-- Judul form login -->
            <h2 class="login-title">Admin Login</h2>
            
            <!-- Menampilkan pesan error jika login gagal -->
            <?php if (!empty($error)): ?>
                <div class="login-message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <!-- Form login admin -->
            <form method="post">
                <!-- Input username -->
                <div class="form-group">
                    <label class="form-label">Username :</label>
                    <input type="text" name="username" placeholder="Masukkan username anda" class="form-control" required>
                </div>
                
                <!-- Input password -->
                <div class="form-group">
                    <label class="form-label">Password :</label>
                    <input type="password" name="password" placeholder="Masukkan password anda" class="form-control" required>
                </div>
                
                <!-- Tombol login -->
                <button type="submit" class="btn">Login</button>
            </form>
            
            <!-- Link kembali ke halaman utama -->
            <div class="login-links">
                <a href="./index.php">← Kembali</a>
            </div>
        </div>
    </div>

    <script>
        // Mengambil elemen menu hamburger
        const hamburger = document.getElementById("hamburger");

        // Mengambil elemen navigasi mobile
        const mobileNav = document.getElementById("mobileNav");

        // Mengambil elemen overlay
        const overlay = document.getElementById("overlay");

        // Fungsi untuk membuka dan menutup menu mobile
        function toggleMenu() {
            hamburger.classList.toggle("active");
            mobileNav.classList.toggle("active");
            overlay.classList.toggle("active");

            // Mengunci scroll halaman saat menu mobile terbuka
            document.body.style.overflow = mobileNav.classList.contains("active") ? "hidden" : "";
        }

        // Event saat tombol hamburger ditekan
        hamburger.addEventListener("click", toggleMenu);

        // Event saat overlay ditekan
        overlay.addEventListener("click", toggleMenu);

        // Menutup menu saat salah satu link mobile ditekan
        const mobileLinks = mobileNav.querySelectorAll("a");
        mobileLinks.forEach((link) => {
            link.addEventListener("click", toggleMenu);
        });
    </script>
</body>
</html>
