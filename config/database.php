<?php
// Membuat koneksi ke database MySQL
// Parameter koneksi: localhost (server), root (username), '' (password), bisindo (nama database)

//HOSTING bisindotranslator.infinityfree.me:
// $mysqli = new mysqli('sql303.infinityfree.com', 'if0_40699089', 'rafisarosa13', 'if0_40699089_bisindo');

//LOCALHOST:
$mysqli = new mysqli('localhost', 'root', '', 'bisindo');
// Memeriksa apakah koneksi berhasil atau gagal
if ($mysqli->connect_error) {
    // Jika gagal, tampilkan pesan error dan hentikan eksekusi script
    die('Database connection failed: ' . $mysqli->connect_error);
}
?>