<?php
// Database connection
$mysqli = new mysqli('localhost', 'root', '', 'bisindo');
if ($mysqli->connect_error) {
    die('Database connection failed: ' . $mysqli->connect_error);
}

// Create admin table if not exists dengan struktur yang sesuai
$mysqli->query("
    CREATE TABLE IF NOT EXISTS admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        role ENUM('superadmin', 'admin') DEFAULT 'admin'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Check if admin already exists
$result = $mysqli->query("SELECT COUNT(*) as count FROM admin");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Create default SUPERADMIN (bukan admin biasa)
    $username = 'admin';
    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'superadmin'; // Role sebagai superadmin
    
    $stmt = $mysqli->prepare("INSERT INTO admin (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $username, $hash, $role);
    
    if ($stmt->execute()) {
        echo "<h2>Superadmin Default Berhasil Dibuat</h2>";
        echo "<p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>";
        echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>";
        echo "<p><strong>Role:</strong> " . htmlspecialchars($role) . " (hak akses penuh)</p>";
        echo "<p style='color: red;'><strong>⚠️ PERINGATAN: Segera ganti password setelah login pertama!</strong></p>";
        echo "<p style='color: orange;'>⚠️ <strong>Hapus file ini setelah superadmin berhasil dibuat!</strong></p>";
        echo "<br><a href='/bisindo/login.php'>Login Sekarang</a>";
    } else {
        echo "Gagal membuat superadmin default: " . $mysqli->error;
    }
    $stmt->close();
} else {
    echo "<h2>Admin/Superadmin Sudah Ada</h2>";
    echo "<p>Gunakan form management admin untuk membuat admin baru.</p>";
    
    // Show existing admins with their roles
    $result = $mysqli->query("SELECT username, role, created_at FROM admin ORDER BY 
                              CASE role 
                                  WHEN 'superadmin' THEN 1 
                                  WHEN 'admin' THEN 2 
                                  ELSE 3 
                              END, created_at");
    
    echo "<h3>Daftar Admin/Superadmin yang Ada:</h3>";
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f2f2f2;'>
            <th>Username</th>
            <th>Role</th>
            <th>Dibuat Pada</th>
          </tr>";
    
    while ($row = $result->fetch_assoc()) {
        $roleColor = ($row['role'] == 'superadmin') ? 'color: #d32f2f; font-weight: bold;' : 'color: #1976d2;';
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td style='$roleColor'>" . htmlspecialchars($row['role']) . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><p><strong>Total admin:</strong> " . $row['count'] . "</p>";
    echo "<br><a href='/bisindo/login.php'>Login</a> | <a href='/bisindo/index.php'>Home</a>";
}

// Tambahkan info keamanan
echo "<hr style='margin: 30px 0;'>";
echo "<div style='background-color: #fff3cd; padding: 15px; border-left: 5px solid #ffc107;'>";
echo "<h3 style='color: #856404; margin-top: 0;'>🔒 Informasi Keamanan:</h3>";
echo "<ul style='color: #856404;'>";
echo "<li>File ini hanya untuk membuat superadmin pertama</li>";
echo "<li>Hapus file ini dari server setelah superadmin berhasil dibuat</li>";
echo "<li>Ganti password default segera setelah login pertama</li>";
echo "<li>Gunakan password yang kuat dan unik</li>";
echo "</ul>";
echo "</div>";

$mysqli->close();
?>