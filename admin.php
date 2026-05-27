<?php
// Mulai sesi untuk menyimpan data login admin
session_start();

// Hubungkan dengan file konfigurasi database
require_once 'config/database.php';

// Periksa apakah admin sudah login, jika tidak arahkan ke halaman login
if (empty($_SESSION['admin_id'])) {
    header('Location: ./login.php');
    exit;
}

// Fungsi untuk mendapatkan lokasi folder uploads yang dinamis
// Mencari folder uploads di beberapa lokasi yang mungkin
function getUploadsPath() {
    // Daftar lokasi kemungkinan folder uploads
    $possiblePaths = [
        dirname(__DIR__) . '/uploads/', // Satu level di atas direktori saat ini
        __DIR__ . '/../uploads/', // Satu level naik dari direktori saat ini
        __DIR__ . '/uploads/', // Dalam direktori saat ini
        'uploads/', // Lokasi relatif
        $_SERVER['DOCUMENT_ROOT'] . './uploads/', // Lokasi absolut dari root server
    ];
    
    // Cari folder yang ada dan bisa ditulis
    foreach ($possiblePaths as $path) {
        if (is_dir($path) && is_writable($path)) {
            return rtrim($path, '/') . '/';
        }
    }
    
    // Jika tidak ditemukan, buat folder baru
    $defaultPath = dirname(__DIR__) . '/uploads/';
    if (!is_dir($defaultPath)) {
        mkdir($defaultPath, 0755, true);
    }
    return $defaultPath;
}

// Fungsi untuk menghapus file fisik dari server
function deletePhysicalFile($filename) {
    if (empty($filename)) {
        return false;
    }
    
    $uploadsPath = getUploadsPath();
    $filePath = $uploadsPath . $filename;
    
    if (file_exists($filePath)) {
        if (unlink($filePath)) {
            return true;
        } else {
            // Catat error jika gagal menghapus
            error_log("Gagal menghapus file: $filePath");
            return false;
        }
    } else {
        // Catat peringatan jika file tidak ditemukan
        error_log("File tidak ditemukan: $filePath");
        return false;
    }
}

// Proses penghapusan data saat parameter delete ada di URL
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // 1. Ambil informasi file dari database sebelum dihapus
    $stmt = $mysqli->prepare('SELECT file FROM sign WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($file);
    $stmt->fetch();
    $stmt->close();
    
    // 2. Hapus data dari database TERLEBIH DAHULU
    $stmt = $mysqli->prepare('DELETE FROM sign WHERE id = ?');
    $stmt->bind_param('i', $id);
    $dbDeleted = $stmt->execute();
    $stmt->close();
    
    // 3. Hapus file fisik jika data berhasil dihapus dari database
    $fileDeleted = false;
    if ($dbDeleted && !empty($file)) {
        $fileDeleted = deletePhysicalFile($file);
    }
    
    // 4. Redirect kembali dengan pesan hasil operasi
    if ($dbDeleted) {
        if ($fileDeleted) {
            $_SESSION['message'] = "Data dan file berhasil dihapus";
        } else {
            $_SESSION['message'] = "Data dihapus dari database, tapi file fisik tidak ditemukan";
        }
    } else {
        $_SESSION['error'] = "Gagal menghapus dari database";
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Proses parameter filter untuk pencarian data
$search = isset($_GET['search']) ? ($_GET['search']) : '';
$imageFilter = isset($_GET['image']) ? $_GET['image'] === '1' : false;
$videoFilter = isset($_GET['video']) ? $_GET['video'] === '1' : false;
$multiWordFilter = isset($_GET['multi_word']) ? $_GET['multi_word'] === '1' : false;
$singleWordFilter = isset($_GET['single_word']) ? $_GET['single_word'] === '1' : false;
$characterFilter = isset($_GET['character']) ? $_GET['character'] === '1' : false;

// Cek apakah ada filter yang aktif
$hasActiveFilters = $imageFilter || $videoFilter || $multiWordFilter || $singleWordFilter || $characterFilter;

// Bangun query database dengan filter
$query = 'SELECT * FROM sign WHERE 1=1';
$params = [];
$types = '';

// Tambahkan filter pencarian teks
if (!empty($search)) {
    $query .= ' AND (label LIKE ? OR file LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $types .= 'ss';
}

// Filter format file (image/video)
if ($imageFilter && !$videoFilter) {
    $query .= ' AND format = ?';
    $params[] = 'image';
    $types .= 's';
} elseif ($videoFilter && !$imageFilter) {
    $query .= ' AND format = ?';
    $params[] = 'video';
    $types .= 's';
}

// Filter kategori (multi_word/single_word/character)
$categoryFilters = [];
if ($multiWordFilter) $categoryFilters[] = 'multi_word';
if ($singleWordFilter) $categoryFilters[] = 'single_word';
if ($characterFilter) $categoryFilters[] = 'character';

if (!empty($categoryFilters)) {
    $placeholders = str_repeat('?,', count($categoryFilters) - 1) . '?';
    $query .= " AND category IN ($placeholders)";
    $params = array_merge($params, $categoryFilters);
    $types .= str_repeat('s', count($categoryFilters));
}

// Urutkan data berdasarkan tanggal upload terbaru
$query .= ' ORDER BY created_at DESC';

// Eksekusi query dengan parameter filter
if (!empty($params)) {
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $signs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $signs = $mysqli->query($query)->fetch_all(MYSQLI_ASSOC);
}

// Hitung jumlah data berdasarkan format dan kategori untuk ditampilkan di filter
$imageCount = $videoCount = $multiWordCount = $singleWordCount = $characterCount = 0;
foreach ($signs as $sign) {
    if ($sign['format'] === 'image') $imageCount++;
    if ($sign['format'] === 'video') $videoCount++;
    if ($sign['category'] === 'multi_word') $multiWordCount++;
    if ($sign['category'] === 'single_word') $singleWordCount++;
    if ($sign['category'] === 'character') $characterCount++;
}

// Tampilkan pesan dari sesi (setelah redirect)
$message = '';
$error = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - BISINDO</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="./assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navigasi utama -->
    <nav class="navbar">
        <div class="container">
            <a href="./index.php" class="nav-brand">BISINDO TRANSLATOR</a>
            <div class="nav-links">
                <a href="./upload.php" class="btn">Upload Isyarat Baru</a>
                <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
                <a href="./management.php" class="btn">Manajemen Admin</a>
                <?php endif; ?>
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
        <span class="text-muted" style="text-align: center; margin-bottom: 1rem;">Admin Menu</span>
        <a href="./upload.php" class="btn">Upload Isyarat Baru</a>
        <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
        <a href="./management.php" class="btn">Manajemen Admin</a>
        <?php endif; ?>
        <a href="./logout.php" class="btn btn-logout">Logout</a>
    </div>

    <!-- Overlay untuk menutup menu mobile -->
    <div class="overlay" id="overlay"></div>

    <!-- Konten utama -->
    <div class="container">
        <!-- Header dashboard -->
        <div class="dashboard-header">
            <h1>Dashboard Admin</h1>
            <div class="text-muted">Kelola Data Bahasa Isyarat Indonesia</div>
        </div>

        <!-- Tampilkan pesan sukses -->
        <?php if (!empty($message)): ?>
            <div class="alert-message <?= strpos($message, 'tidak ditemukan') ? 'warning' : 'success' ?>">
                <i class="fas <?= strpos($message, 'tidak ditemukan') ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Tampilkan pesan error -->
        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Area pencarian dan filter -->
        <div class="search-filter-container">
            <!-- Kotak pencarian -->
            <div class="search-box">
                <form method="GET" action="" id="searchForm" style="margin: 0; position: relative;">
                    <input type="text" 
                           name="search" 
                           class="search-input" 
                           placeholder="Cari label atau nama isyarat" 
                           value="<?= htmlspecialchars($search) ?>"
                           autocomplete="off"
                           id="searchInput">
                    <i class="fas fa-search search-icon"></i>
                    <button type="button" class="clear-search" id="clearSearch" title="Hapus pencarian">×</button>

                    <!-- Input tersembunyi untuk menyimpan status filter saat pencarian -->
                    <input type="hidden" name="image" value="<?= $imageFilter ? '1' : '' ?>">
                    <input type="hidden" name="video" value="<?= $videoFilter ? '1' : '' ?>">
                    <input type="hidden" name="multi_word" value="<?= $multiWordFilter ? '1' : '' ?>">
                    <input type="hidden" name="single_word" value="<?= $singleWordFilter ? '1' : '' ?>">
                    <input type="hidden" name="character" value="<?= $characterFilter ? '1' : '' ?>">
                </form>
            </div>
            
            <!-- Dropdown filter -->
            <div class="filter-dropdown">
                <div class="filter-dropdown-toggle" id="filterToggle">
                    Filter
                    <?php if ($hasActiveFilters): ?>
                        <!-- Badge menunjukkan jumlah filter aktif -->
                        <span class="filter-badge-container">
                            <span class="filter-badge" id="filterBadge">
                                <?= ($imageFilter ? 1 : 0) + ($videoFilter ? 1 : 0) + ($multiWordFilter ? 1 : 0) + ($singleWordFilter ? 1 : 0) + ($characterFilter ? 1 : 0) ?>
                            </span>
                        </span>
                    <?php endif; ?>
                    <span class="filter-arrow">▼</span>
                </div>
                
                <!-- Menu dropdown filter -->
                <div class="filter-dropdown-menu" id="filterMenu">
                    <!-- Filter berdasarkan format file -->
                    <div class="filter-group">
                        <span class="filter-title">Format</span>
                        <div class="filter-options">
                            <label class="checkbox-container">
                                <input type="checkbox" 
                                       class="checkbox-input filter-checkbox" 
                                       name="image" 
                                       value="1" 
                                       data-param="image"
                                       <?= $imageFilter ? 'checked' : '' ?>>
                                <span class="checkbox-label image">Gambar</span>
                            </label>
                            
                            <label class="checkbox-container">
                                <input type="checkbox" 
                                       class="checkbox-input filter-checkbox" 
                                       name="video" 
                                       value="1" 
                                       data-param="video"
                                       <?= $videoFilter ? 'checked' : '' ?>>
                                <span class="checkbox-label video">Video</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Filter berdasarkan kategori sign -->
                    <div class="filter-group">
                        <span class="filter-title">Kategori</span>
                        <div class="filter-options">
                            <label class="checkbox-container">
                                <input type="checkbox" 
                                       class="checkbox-input filter-checkbox" 
                                       name="multi_word" 
                                       value="1" 
                                       data-param="multi_word"
                                       <?= $multiWordFilter ? 'checked' : '' ?>>
                                <span class="checkbox-label multi_word">Multi Word</span>
                            </label>
                            
                            <label class="checkbox-container">
                                <input type="checkbox" 
                                       class="checkbox-input filter-checkbox" 
                                       name="single_word" 
                                       value="1" 
                                       data-param="single_word"
                                       <?= $singleWordFilter ? 'checked' : '' ?>>
                                <span class="checkbox-label single_word">Single Word</span>
                            </label>
                            
                            <label class="checkbox-container">
                                <input type="checkbox" 
                                       class="checkbox-input filter-checkbox" 
                                       name="character" 
                                       value="1" 
                                       data-param="character"
                                       <?= $characterFilter ? 'checked' : '' ?>>
                                <span class="checkbox-label character">Character</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Tombol aksi filter -->
                    <div class="filter-actions">
                        <button type="button" id="applyFilter" class="btn">Terapkan Filter</button>
                        <?php if ($hasActiveFilters): ?>
                            <button type="button" id="clearFilter" class="btn btn-clear-filter">Reset Filter</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kartu utama untuk menampilkan data -->
        <div class="card">
            <div class="card-header">
                <h2>Daftar Bahasa Isyarat</h2>
                <div class="text-muted card-total">
                    <div>Total: <strong><?= count($signs) ?></strong> data ditemukan</div>
                </div>
            </div>
            
            <?php if (empty($signs)): ?>
                <!-- Tampilan jika tidak ada data -->
                <div class="empty-state">
                    <p>
                        <?php if (!empty($search) || $hasActiveFilters): ?>
                            Tidak ada data yang sesuai dengan pencarian atau filter Anda.
                        <?php else: ?>
                            Belum ada data isyarat.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <!-- Tabel untuk tampilan desktop -->
                <div class="desktop-table">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Label</th>
                                    <th>File</th>
                                    <th>Format</th>
                                    <th>Kategori</th>
                                    <th>Tanggal Upload</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($signs as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['id']) ?></td>
                                    <td><?= htmlspecialchars($s['label']) ?></td>
                                    <td><?= htmlspecialchars($s['file']) ?></td>
                                    <td>
                                        <!-- Badge warna untuk format -->
                                        <span class="format-badge <?= $s['format'] ?>">
                                            <?= strtoupper($s['format']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- Badge warna untuk kategori -->
                                        <span class="category-badge <?= $s['category'] ?>">
                                            <?= strtoupper(str_replace('_', ' ', $s['category'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
                                    <td>
                                        <!-- Tombol aksi untuk setiap data -->
                                        <div class="action-buttons">
                                            <a href="./upload.php?edit=<?= $s['id'] ?>" class="btn btn-edit">Edit</a>
                                            <a href="?delete=<?= $s['id'] ?>" class="btn btn-delete" 
                                            onclick="return confirm('Yakin ingin menghapus isyarat ini? File fisik juga akan dihapus.')">Hapus</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Tampilan accordion untuk perangkat mobile -->
                <div class="mobile-accordion">
                    <div class="accordion-list">
                        <?php foreach ($signs as $s): ?>
                        <div class="accordion-item" data-id="<?= $s['id'] ?>">
                            <!-- Header accordion (bisa diklik untuk membuka) -->
                            <div class="accordion-header">
                                <div class="accordion-header-content">
                                    <span class="accordion-label"><?= htmlspecialchars($s['label']) ?></span>
                                    <div class="accordion-badges">
                                        <span class="badge format-badge <?= $s['format'] ?>">
                                            <?= strtoupper($s['format']) ?>
                                        </span>
                                        <span class="badge category-badge <?= $s['category'] ?>">
                                            <?= strtoupper(str_replace('_', ' ', $s['category'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <span class="accordion-toggle">▼</span>
                            </div>
                            <!-- Konten accordion (tampil saat header diklik) -->
                            <div class="accordion-content">
                                <!-- Detail data dalam format vertikal -->
                                <div class="accordion-details">
                                    <div class="detail-row">
                                        <span class="detail-label">ID</span>
                                        <span class="detail-value"><?= htmlspecialchars($s['id']) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">File</span>
                                        <span class="detail-value"><?= htmlspecialchars($s['file']) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Format</span>
                                        <span class="detail-value">
                                            <span class="badge format-badge <?= $s['format'] ?>">
                                                <?= strtoupper($s['format']) ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Kategori</span>
                                        <span class="detail-value">
                                            <span class="badge category-badge <?= $s['category'] ?>">
                                                <?= strtoupper(str_replace('_', ' ', $s['category'])) ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Tanggal Upload</span>
                                        <span class="detail-value"><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></span>
                                    </div>
                                </div>
                                <!-- Tombol aksi untuk mobile -->
                                <div class="accordion-actions">
                                    <a href="./upload.php?edit=<?= $s['id'] ?>" class="btn btn-edit">Edit</a>
                                    <a href="?delete=<?= $s['id'] ?>" class="btn btn-delete" 
                                       onclick="return confirm('Yakin ingin menghapus isyarat ini? File fisik juga akan dihapus.')">Hapus</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- File JavaScript untuk interaksi di halaman -->
    <script src="./assets/js/admin.js"></script>
    <script>
        // Data state filter untuk JavaScript
        const filterState = {
            image: <?= $imageFilter ? 'true' : 'false' ?>,
            video: <?= $videoFilter ? 'true' : 'false' ?>,
            multi_word: <?= $multiWordFilter ? 'true' : 'false' ?>,
            single_word: <?= $singleWordFilter ? 'true' : 'false' ?>,
            character: <?= $characterFilter ? 'true' : 'false' ?>
        };
        
        // Auto-hide pesan setelah 5 detik
        setTimeout(() => {
            const messages = document.querySelectorAll('.alert-message, .alert-error');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s';
                msg.style.opacity = '0';
                setTimeout(() => {
                    if (msg.parentNode) {
                        msg.parentNode.removeChild(msg);
                    }
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>