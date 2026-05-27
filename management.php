<?php
// Mulai sesi untuk menyimpan data login pengguna
session_start();

// Hubungkan dengan file konfigurasi database
require_once 'config/database.php';

// Periksa apakah admin sudah login, jika tidak arahkan ke halaman login
if (empty($_SESSION['admin_id'])) {
    header('Location: ./login.php');
    exit;
}

// Hanya superadmin yang bisa akses halaman management admin
if ($_SESSION['admin_role'] !== 'superadmin') {
    header('Location: ./admin.php');
    exit;
}

$message = '';
$error = '';

// Proses pembuatan admin baru saat form dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'admin';
    
    // Validasi input
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak cocok';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } else {
        // Cek apakah username sudah digunakan
        $stmt = $mysqli->prepare('SELECT id FROM admin WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = 'Username sudah digunakan';
        } else {
            // Buat admin baru dengan password terenkripsi
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare('INSERT INTO admin (username, password, role) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $username, $hash, $role);
            
            if ($stmt->execute()) {
                $message = 'Admin berhasil dibuat';
            } else {
                $error = 'Gagal membuat admin';
            }
        }
        $stmt->close();
    }
}

// Proses penghapusan admin saat link delete diklik
if (isset($_GET['delete_admin']) && is_numeric($_GET['delete_admin'])) {
    $adminId = (int)$_GET['delete_admin'];
    $currentAdminId = $_SESSION['admin_id'];
    
    // Cegah admin menghapus akun sendiri
    if ($adminId == $currentAdminId) {
        $error = 'Tidak dapat menghapus akun sendiri';
    } else {
        $stmt = $mysqli->prepare('DELETE FROM admin WHERE id = ?');
        $stmt->bind_param('i', $adminId);
        
        if ($stmt->execute()) {
            $message = 'Admin berhasil dihapus';
        } else {
            $error = 'Gagal menghapus admin';
        }
        $stmt->close();
    }
}

// Proses update data admin saat form edit dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin'])) {
    $adminId = (int)$_POST['admin_id'];
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'admin';
    $currentAdminId = $_SESSION['admin_id'];
    $isSelfEdit = ($adminId == $currentAdminId);
    
    if ($isSelfEdit) {
        // Edit akun sendiri: username wajib, password opsional
        if (empty($username)) {
            $error = 'Username harus diisi';
        } else {
            // Cek apakah username sudah digunakan oleh admin lain
            $stmt = $mysqli->prepare('SELECT id FROM admin WHERE username = ? AND id != ?');
            $stmt->bind_param('si', $username, $adminId);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error = 'Username sudah digunakan oleh admin lain';
            } else {
                // Update admin dengan atau tanpa password baru
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        $error = 'Password minimal 6 karakter';
                    } else {
                        // Update dengan password baru
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $mysqli->prepare('UPDATE admin SET username = ?, password = ? WHERE id = ?');
                        $stmt->bind_param('ssi', $username, $hash, $adminId);
                    }
                } else {
                    // Update hanya username (tanpa ganti password)
                    $stmt = $mysqli->prepare('UPDATE admin SET username = ? WHERE id = ?');
                    $stmt->bind_param('si', $username, $adminId);
                }
                
                if (empty($error) && isset($stmt) && $stmt->execute()) {
                    // Update sesi dengan username baru
                    $_SESSION['admin_username'] = $username;
                    $message = 'Data Anda berhasil diperbarui';
                } elseif (empty($error)) {
                    $error = 'Gagal memperbarui data';
                }
            }
            if (isset($stmt)) $stmt->close();
        }
    } else {
        // Edit admin lain: username wajib, password opsional, bisa ganti role
        if (empty($username)) {
            $error = 'Username harus diisi';
        } else {
            // Cek apakah username sudah digunakan oleh admin lain
            $stmt = $mysqli->prepare('SELECT id FROM admin WHERE username = ? AND id != ?');
            $stmt->bind_param('si', $username, $adminId);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error = 'Username sudah digunakan oleh admin lain';
            } else {
                // Update admin lain dengan atau tanpa password baru
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        $error = 'Password minimal 6 karakter';
                    } else {
                        // Update dengan password baru
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $mysqli->prepare('UPDATE admin SET username = ?, password = ?, role = ? WHERE id = ?');
                        $stmt->bind_param('sssi', $username, $hash, $role, $adminId);
                    }
                } else {
                    // Update tanpa ganti password
                    $stmt = $mysqli->prepare('UPDATE admin SET username = ?, role = ? WHERE id = ?');
                    $stmt->bind_param('ssi', $username, $role, $adminId);
                }
                
                if (empty($error) && isset($stmt) && $stmt->execute()) {
                    $message = 'Data admin berhasil diperbarui';
                } elseif (empty($error)) {
                    $error = 'Gagal memperbarui data admin';
                }
            }
            if (isset($stmt)) $stmt->close();
        }
    }
}

// Ambil informasi admin yang sedang login
$currentAdmin = null;
$stmt = $mysqli->prepare('SELECT id, username, role FROM admin WHERE id = ?');
$stmt->bind_param('i', $_SESSION['admin_id']);
$stmt->execute();
$result = $stmt->get_result();
$currentAdmin = $result->fetch_assoc();
$stmt->close();

// Ambil semua data admin dari database untuk ditampilkan
$admins = $mysqli->query('SELECT id, username, role, created_at FROM admin ORDER BY role DESC, created_at DESC')->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Admin - BISINDO</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="./assets/css/management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navigasi utama -->
    <nav class="navbar">
        <div class="container">
            <a href="./index.php" class="nav-brand">BISINDO TRANSLATOR</a>
            <div class="nav-links">
                <a href="./admin.php" class="btn">Dashboard</a>
                <a href="./logout.php" class="btn btn-logout">Logout</a>
            </div>
            
            <!-- Tombol menu untuk perangkat mobile -->
            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>

    <!-- Navigasi mobile (muncul saat menu diklik) -->
    <div class="mobile-nav" id="mobileNav">
        <a href="./admin.php" class="btn">Dashboard</a>
        <a href="./logout.php" class="btn btn-logout">Logout</a>
    </div>

    <!-- Overlay untuk menutup menu mobile -->
    <div class="overlay" id="overlay"></div>

    <!-- Konten utama -->
    <div class="container">
        <!-- Header dashboard -->
        <div class="management-dashboard-header">
            <h1>Manajemen Admin</h1>
            <div class="management-text-muted">Selamat datang, <?= htmlspecialchars($currentAdmin['username'] ?? 'Admin') ?>
                <span class="management-role-badge <?= $currentAdmin['role'] === 'superadmin' ? 'management-role-superadmin' : 'management-role-admin' ?>">
                    <?= strtoupper($currentAdmin['role']) ?>
                </span>
            </div>
        </div>

        <!-- Tampilkan pesan sukses atau error -->
        <?php if (!empty($message)): ?>
            <div class="card text-success mb-3">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="card text-error mb-3">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Statistik jumlah admin -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($admins) ?></div>
                <div class="stat-label">Total Admin</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?= count(array_filter($admins, function($admin) { return $admin['role'] === 'superadmin'; })) ?>
                </div>
                <div class="stat-label">Superadmin</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?= count(array_filter($admins, function($admin) { return $admin['role'] === 'admin'; })) ?>
                </div>
                <div class="stat-label">Admin</div>
            </div>
        </div>

        <!-- Dropdown form untuk membuat admin baru -->
        <div class="management-form-dropdown">
            <button type="button" class="management-form-dropdown-toggle" id="createAdminToggle">
                <span>Buat Admin Baru</span>
                <span class="management-form-dropdown-arrow">▼</span>
            </button>
            <div class="management-form-dropdown-content" id="createAdminContent">
                <div class="management-form-dropdown-inner">
                    <form method="post" autocomplete="off" id="createAdminForm">
                        <div class="form-group">
                            <label class="form-label">Username:</label>
                            <input type="text" name="username" class="form-control" required 
                                    placeholder="Masukkan username baru" autocomplete="new-username">
                        </div>
                        
                        <!-- Dropdown pilihan role -->
                        <div class="form-group">
                            <label class="form-label">Role:</label>
                            
                            <!-- Select tersembunyi untuk pengiriman form -->
                            <select name="role" id="createRoleSelect" style="display: none;" required>
                                <option value="admin">Admin (Hanya bisa mengelola data)</option>
                                <option value="superadmin">Superadmin (Bisa mengelola admin & data)</option>
                            </select>
                            
                            <!-- UI dropdown kustom untuk role -->
                            <div class="custom-select-wrapper">
                                <div class="custom-select" id="createCustomRoleSelect">
                                    <div class="custom-select-trigger">
                                        <span id="createCustomRoleText">Admin (Hanya bisa mengelola data)</span>
                                        <div class="custom-select-arrow">▼</div>
                                    </div>
                                    <div class="custom-options">
                                        <div class="custom-option" data-value="admin">Admin (Hanya bisa mengelola data)</div>
                                        <div class="custom-option" data-value="superadmin">Superadmin (Bisa mengelola admin & data)</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Input password -->
                        <div class="form-group">
                            <label class="form-label">Password:</label>
                            <input type="password" name="password" class="form-control" required 
                                    placeholder="Minimal 6 karakter" autocomplete="new-password">
                        </div>
                        
                        <!-- Konfirmasi password -->
                        <div class="form-group">
                            <label class="form-label">Konfirmasi Password:</label>
                            <input type="password" name="confirm_password" class="form-control" required 
                                    placeholder="Ulangi password" autocomplete="new-password">
                        </div>
                        
                        <!-- Input tersembunyi untuk identifikasi proses -->
                        <input type="hidden" name="create_admin" value="1">
                        <button type="submit" class="btn">Buat Admin Baru</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tabel daftar admin -->
        <div class="card">
            <h2 class="mb-3">Daftar Admin</h2>
            
            <?php if (empty($admins)): ?>
                <p class="text-center management-text-muted">Belum ada admin.</p>
            <?php else: ?>
                <!-- Tabel untuk tampilan desktop -->
                <div class="management-desktop-table">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Tanggal Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><?= htmlspecialchars($admin['id']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($admin['username']) ?>
                                        <?php if ($admin['id'] == $_SESSION['admin_id']): ?>
                                            <span class="management-current-user-label">(Anda)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <!-- Badge warna berbeda untuk setiap role -->
                                        <span class="management-role-badge <?= $admin['role'] === 'superadmin' ? 'management-role-superadmin' : 'management-role-admin' ?>">
                                            <?= strtoupper($admin['role']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($admin['created_at'])) ?></td>
                                    <td>
                                        <!-- Tombol aksi untuk setiap admin -->
                                        <div class="management-action-buttons">
                                            <button type="button" onclick="showEditModal(<?= $admin['id'] ?>, '<?= htmlspecialchars(addslashes($admin['username'])) ?>', '<?= htmlspecialchars(addslashes($admin['role'])) ?>', <?= $admin['id'] == $_SESSION['admin_id'] ? 'true' : 'false' ?>)" 
                                                    class="management-action-btn management-action-btn-primary">
                                                Edit
                                            </button>
                                            
                                            <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                                <!-- Tombol hapus (tidak tersedia untuk akun sendiri) -->
                                                <a href="?delete_admin=<?= $admin['id'] ?>" class="management-action-btn management-action-btn-danger"
                                                onclick="return confirm('Yakin ingin menghapus admin <?= htmlspecialchars(addslashes($admin['username'])) ?>?')">
                                                    Hapus
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Tampilan accordion untuk perangkat mobile -->
                <div class="management-mobile-accordion">
                    <div class="management-accordion-list">
                        <?php foreach ($admins as $admin): ?>
                        <div class="management-accordion-item">
                            <!-- Header accordion untuk mobile -->
                            <div class="management-accordion-header">
                                <div class="management-accordion-header-content">
                                    <span class="management-accordion-label"><?= htmlspecialchars($admin['username']) ?>
                                        <?php if ($admin['id'] == $_SESSION['admin_id']): ?>
                                            <span class="management-current-user-label">(Anda)</span>
                                        <?php endif; ?>
                                    </span>
                                    <div class="management-accordion-badges">
                                        <span class="management-role-badge <?= $admin['role'] === 'superadmin' ? 'management-role-superadmin' : 'management-role-admin' ?>">
                                            <?= strtoupper($admin['role']) ?>
                                        </span>
                                    </div>
                                </div>
                                <span class="management-accordion-toggle">▼</span>
                            </div>
                            <!-- Konten accordion yang bisa dibuka -->
                            <div class="management-accordion-content">
                                <div class="management-accordion-details">
                                    <!-- Detail admin dalam format vertikal -->
                                    <div class="management-detail-row">
                                        <span class="management-detail-label">ID</span>
                                        <span class="management-detail-value"><?= htmlspecialchars($admin['id']) ?></span>
                                    </div>
                                    <div class="management-detail-row">
                                        <span class="management-detail-label">Role</span>
                                        <span class="management-detail-value">
                                            <span class="management-role-badge <?= $admin['role'] === 'superadmin' ? 'management-role-superadmin' : 'management-role-admin' ?>">
                                                <?= strtoupper($admin['role']) ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="management-detail-row">
                                        <span class="management-detail-label">Tanggal Dibuat</span>
                                        <span class="management-detail-value"><?= date('d/m/Y H:i', strtotime($admin['created_at'])) ?></span>
                                    </div>
                                </div>
                                <!-- Tombol aksi untuk mobile -->
                                <div class="management-accordion-actions">
                                    <div class="management-action-buttons">
                                        <button type="button" onclick="showEditModal(<?= $admin['id'] ?>, '<?= htmlspecialchars(addslashes($admin['username'])) ?>', '<?= htmlspecialchars(addslashes($admin['role'])) ?>', <?= $admin['id'] == $_SESSION['admin_id'] ? 'true' : 'false' ?>)" 
                                                class="management-action-btn management-action-btn-primary">
                                            Edit
                                        </button>
                                        
                                        <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                            <a href="?delete_admin=<?= $admin['id'] ?>" class="management-action-btn management-action-btn-danger"
                                            onclick="return confirm('Yakin ingin menghapus admin <?= htmlspecialchars(addslashes($admin['username'])) ?>?')">
                                                Hapus
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal popup untuk mengedit data admin -->
    <div id="editModal" class="management-modal-overlay" data-current-admin-id="<?= $_SESSION['admin_id'] ?>">
        <div class="management-modal-content">
            <!-- Header modal -->
            <div class="management-modal-header">
                <h3 class="management-modal-title" id="editModalTitle">Edit Admin</h3>
                <button type="button" class="management-modal-close" onclick="closeEditModal()">×</button>
            </div>
            
            <!-- Body modal berisi form edit -->
            <div class="management-modal-body">
                <form method="post" id="editAdminForm" autocomplete="off">
                    <input type="hidden" name="admin_id" id="editAdminId">
                    <input type="hidden" name="update_admin" value="1">
                    
                    <!-- Input username -->
                    <div class="form-group">
                        <label class="form-label">Username:</label>
                        <input type="text" name="username" id="editUsername" class="form-control" 
                                placeholder="Masukkan username" autocomplete="off" required>
                    </div>
                    
                    <!-- Input role (hanya untuk edit admin lain) -->
                    <div class="form-group" id="roleFieldContainer">
                        <label class="form-label">Role:</label>
                        
                        <!-- Select tersembunyi untuk pengiriman form -->
                        <select name="role" id="editRole" style="display: none;">
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                        
                        <!-- UI dropdown kustom untuk role -->
                        <div class="custom-select-wrapper">
                            <div class="custom-select" id="customRoleSelect">
                                <div class="custom-select-trigger">
                                    <span id="customRoleText">Admin</span>
                                    <div class="custom-select-arrow">▼</div>
                                </div>
                                <div class="custom-options">
                                    <div class="custom-option" data-value="admin">Admin</div>
                                    <div class="custom-option" data-value="superadmin">Superadmin</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Input password baru (opsional) -->
                    <div class="form-group">
                        <label class="form-label">Password Baru (opsional):</label>
                        <input type="password" name="password" id="editPassword" class="form-control" 
                                placeholder="Minimal 6 karakter" autocomplete="new-password">
                        <small class="management-text-muted">Kosongkan jika tidak ingin mengubah password</small>
                    </div>
                    
                    <!-- Konfirmasi password baru -->
                    <div class="form-group">
                        <label class="form-label">Konfirmasi Password:</label>
                        <input type="password" name="confirm_password" id="editConfirmPassword" class="form-control" 
                                placeholder="Ulangi password baru" autocomplete="new-password">
                    </div>
                </form>
            </div>
            
            <!-- Footer modal dengan tombol aksi -->
            <div class="management-modal-footer">
                <div class="management-modal-actions">
                    <button type="button" onclick="closeEditModal()" class="management-action-btn management-action-btn-danger">
                        Batal
                    </button>
                    <button type="submit" form="editAdminForm" class="management-action-btn management-action-btn-primary">
                        Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- File JavaScript untuk interaksi di halaman -->
    <script src="./assets/js/management.js"></script>
</body>
</html>