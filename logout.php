<?php
// Mulai sesi untuk mengakses data login yang tersimpan
session_start();

// Hancurkan semua data sesi yang ada
// Ini akan menghapus semua variabel sesi seperti admin_id, admin_username, admin_role
session_destroy();

// Arahkan pengguna kembali ke halaman utama website
header('Location: ./index.php');

// Pastikan tidak ada kode yang dieksekusi setelah redirect
exit;
?>