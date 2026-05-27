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

// Ambil ID admin dari sesi untuk dicatat siapa yang mengupload
$admin_id = $_SESSION['admin_id'];

// Bagian ini mencari lokasi FFmpeg di sistem
// FFmpeg adalah alat untuk mengonversi video ke format yang kompatibel
function getFFmpegPath() {
    // Daftar lokasi umum tempat FFmpeg biasanya terinstall
    $possiblePaths = [
        'ffmpeg', // Jika ada di PATH sistem
        'C:\\ffmpeg\\bin\\ffmpeg.exe', // Windows drive C
        'D:\\ffmpeg\\bin\\ffmpeg.exe', // Windows drive D
        'E:\\ffmpeg\\bin\\ffmpeg.exe', // Windows drive E
        '/usr/bin/ffmpeg', // Linux/Unix umum
        '/usr/local/bin/ffmpeg', // Linux/Unix local
    ];
    
    // Coba setiap lokasi sampai menemukan FFmpeg yang berfungsi
    foreach ($possiblePaths as $path) {
        $command = escapeshellcmd($path) . ' -version 2>&1';
        exec($command, $output, $returnCode);
        
        // Jika perintah berjalan sukses, FFmpeg ditemukan
        if ($returnCode === 0) {
            return $path;
        }
    }
    
    // Jika tidak ditemukan di semua lokasi
    return null;
}

// Fungsi untuk mengonversi video ke format H.264 yang kompatibel
function convertVideoToH264($inputPath, $outputPath) {
    // Cek dulu apakah FFmpeg tersedia
    $ffmpegPath = getFFmpegPath();
    
    if (!$ffmpegPath) {
        return false; // Tidak bisa konversi tanpa FFmpeg
    }
    
    // Parameter konversi video untuk hasil optimal:
    // - libx264: codec video H.264
    // - preset fast: kecepatan konversi seimbang
    // - crf 23: kualitas video baik
    // - yuv420p: format warna yang kompatibel luas
    // - aac: codec audio standar
    // - movflags +faststart: memungkinkan streaming langsung
    $command = escapeshellcmd($ffmpegPath) . 
               " -i " . escapeshellarg($inputPath) . 
               " -c:v libx264 -preset fast -crf 23 -profile:v high -level 4.0 -pix_fmt yuv420p" .
               " -c:a aac -b:a 128k -movflags +faststart -y " . 
               escapeshellarg($outputPath) . " 2>&1";
    
    // Jalankan perintah konversi
    exec($command, $output, $returnCode);
    
    // Cek apakah konversi berhasil
    if ($returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
        return true;
    }
    
    // Catat error ke log sistem untuk debugging
    error_log("FFmpeg Error: " . implode("\n", $output));
    return false;
}

// Menentukan lokasi folder uploads yang bisa ditulis
function getUploadsPath() {
    // Daftar kemungkinan lokasi folder uploads
    $possiblePaths = [
        dirname(__DIR__) . '/uploads/',
        __DIR__ . '/../uploads/',
        __DIR__ . '/uploads/',
        'uploads/',
        $_SERVER['DOCUMENT_ROOT'] . './uploads/',
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

// Mendapatkan path untuk file temporer
function getTempPath() {
    $tempPath = getUploadsPath() . 'temp_convert/';
    if (!is_dir($tempPath)) {
        mkdir($tempPath, 0755, true);
    }
    return $tempPath;
}

// Fungsi untuk menghapus file fisik dari folder uploads
function deletePhysicalFile($filename) {
    if (empty($filename)) {
        return false;
    }
    
    $uploadsPath = getUploadsPath();
    $filePath = $uploadsPath . $filename;
    
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}

// Fungsi untuk menghapus file temporer
function deleteTempFile($tempFilename) {
    if (empty($tempFilename)) {
        return false;
    }
    
    $tempPath = getTempPath();
    $filePath = $tempPath . $tempFilename;
    
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}

// Normalisasi teks: ubah ke huruf kecil, hilangkan karakter khusus
function norm($s) {
    $s = mb_strtolower(trim($s), 'UTF-8');
    return preg_replace('/[^\p{L}\p{N}\'\-\s]/u', '', $s);
}

// Menentukan apakah file adalah video atau gambar berdasarkan ekstensi
function getFileFormat($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $videoExt = ['mp4', 'mov', 'avi', 'webm', 'mkv'];
    return in_array($ext, $videoExt) ? 'video' : 'image';
}

// Fungsi untuk membuat nama file berdasarkan label
// Contoh: label = "makan" akan menghasilkan "makan.mp4"
// Jika label sudah ada, akan ditambahkan angka unik di belakang
function generateLabelBasedFilename($label, $originalName) {
    $uploadsPath = getUploadsPath();
    
    // Normalisasi label untuk nama file
    $cleanLabel = preg_replace('/[^\w\-]/', '_', $label);
    $cleanLabel = substr($cleanLabel, 0, 50); // Batasi panjang label
    
    // Ambil ekstensi dari file asli
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    
    // Cek apakah file sudah ada dengan nama label tersebut
    $baseFilename = $cleanLabel . '.' . $ext;
    $counter = 1;
    
    // Jika file sudah ada, tambahkan angka di belakang
    while (file_exists($uploadsPath . $baseFilename)) {
        $baseFilename = $cleanLabel . '_' . $counter . '.' . $ext;
        $counter++;
        
        // Batasi maksimal percobaan
        if ($counter > 100) {
            // Jika masih bentrok, gunakan timestamp
            $baseFilename = $cleanLabel . '_' . time() . '.' . $ext;
            break;
        }
    }
    
    return $baseFilename;
}

// Membuat nama file unik untuk file permanen berdasarkan label
function generateUniqueFilename($label, $originalName) {
    // Gunakan fungsi baru yang berdasarkan label
    return generateLabelBasedFilename($label, $originalName);
}

// Membuat nama file unik untuk file temporer (pertahankan ekstensi asli)
function generateTempFilename($label, $originalName) {
    $tempPath = getTempPath();
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    
    // Normalisasi label untuk nama file
    $cleanLabel = preg_replace('/[^\w\-]/', '_', $label);
    $cleanLabel = substr($cleanLabel, 0, 50);
    
    // Nama file temp: temp_label.ext
    $baseFilename = 'temp_' . $cleanLabel . '.' . $ext;
    $counter = 1;
    
    // Jika file sudah ada, tambahkan angka di belakang
    while (file_exists($tempPath . $baseFilename)) {
        $baseFilename = 'temp_' . $cleanLabel . '_' . $counter . '.' . $ext;
        $counter++;
    }
    
    return $baseFilename;
}

// Fungsi upload file langsung (tidak digunakan di kode ini)
function uploadFile($tmpPath, $originalName) {
    // Fungsi ini tidak digunakan, jadi biarkan kosong atau hapus
    return false;
}

// Simpan file sebagai temporer sebelum diproses
function saveTempFile($tmpPath, $label, $originalName) {
    $filename = generateTempFilename($label, $originalName);
    $destination = getTempPath() . $filename;
    
    if (move_uploaded_file($tmpPath, $destination)) {
        return $filename;
    }
    
    return false;
}

// Memindahkan file temporer ke lokasi permanen dengan konversi jika perlu
function moveTempToPermanent($tempFilename, $label, $originalName) {
    $tempPath = getTempPath();
    $uploadsPath = getUploadsPath();
    
    $source = $tempPath . $tempFilename;
    
    if (!file_exists($source)) {
        return false;
    }
    
    // Buat nama file akhir berdasarkan label
    $finalFilename = generateUniqueFilename($label, $originalName);
    $destination = $uploadsPath . $finalFilename;
    
    // Cek tipe file berdasarkan ekstensi
    $ext = strtolower(pathinfo($tempFilename, PATHINFO_EXTENSION));
    $videoExt = ['mp4', 'mov', 'avi', 'webm', 'mkv', 'hevc', 'h265'];
    
    // Jika file adalah video, coba konversi ke H.264
    if (in_array($ext, $videoExt)) {
        $ffmpegAvailable = getFFmpegPath() !== null;
        
        if ($ffmpegAvailable) {
            // Konversi video ke format H.264
            if (convertVideoToH264($source, $destination)) {
                // Hapus file temp setelah berhasil konversi
                unlink($source);
                return $finalFilename;
            } else {
                // Jika konversi gagal, catat di log dan coba upload langsung
                error_log("Konversi gagal, mencoba upload langsung: " . $tempFilename);
            }
        }
        
        // Cadangan: pindahkan langsung tanpa konversi jika FFmpeg tidak ada
        if (rename($source, $destination)) {
            return $finalFilename;
        }
    } else {
        // Untuk file non-video (gambar), langsung pindahkan
        if (rename($source, $destination)) {
            return $finalFilename;
        }
    }
    
    return false;
}

// Menentukan kategori berdasarkan label yang dimasukkan
function determineCategory($label) {
    $label = trim($label);
    
    // Jika ada spasi, berarti multi kata
    if (strpos($label, ' ') !== false) {
        return 'multi_word';
    }
    
    // Jika panjangnya 1 karakter dan berupa huruf/angka
    if (strlen($label) === 1 && preg_match('/^[a-z0-9]$/', $label)) {
        return 'character';
    }
    
    // Default: single word
    return 'single_word';
}

// Inisialisasi variabel yang akan digunakan
$sign = null;
$isEdit = false;
$error = '';
$existingLabelError = '';
$tempFile = '';
$selectedFileName = '';
$currentLabel = '';
$currentCategory = 'single_word';

// Membersihkan file temporer yang sudah lebih dari 1 jam
$tempPath = getTempPath();
if (is_dir($tempPath)) {
    $files = glob($tempPath . 'temp_*');
    foreach ($files as $file) {
        if (file_exists($file) && time() - filemtime($file) > 3600) {
            unlink($file);
        }
    }
}

// Cek apakah sedang mode edit
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $isEdit = true;
    $signId = (int)$_GET['edit'];
    
    // Ambil data sign dari database berdasarkan ID
    $stmt = $mysqli->prepare('SELECT * FROM sign WHERE id = ?');
    $stmt->bind_param('i', $signId);
    $stmt->execute();
    $result = $stmt->get_result();
    $sign = $result->fetch_assoc();
    $stmt->close();
    
    // Jika sign tidak ditemukan, kembali ke dashboard
    if (!$sign) {
        header('Location: ./admin.php');
        exit;
    }
    
    $currentCategory = $sign['category'];
}

// Proses form ketika dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $label = norm($_POST['label'] ?? '');
    $currentLabel = $label;
    $currentCategory = $_POST['category'] ?? 'single_word';
    $category = $_POST['category'] ?? determineCategory($label);
    
    $tempFile = $_POST['temp_file'] ?? '';
    $selectedFileName = $_POST['selected_file_name'] ?? '';
    
    // Jika ada file yang diupload melalui form
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        // Hapus file temp sebelumnya jika ada
        if (!empty($tempFile) && file_exists(getTempPath() . $tempFile)) {
            deleteTempFile($tempFile);
        }
        
        // Simpan sebagai file temporer DENGAN LABEL
        $tempFile = saveTempFile($_FILES['file']['tmp_name'], $label, $_FILES['file']['name']);
        $selectedFileName = $_FILES['file']['name'];
        
        if (!$tempFile) {
            $error = 'Gagal menyimpan file temporer';
        }
    }
    
    // Validasi input
    if (empty($label)) {
        $error = 'Label tidak boleh kosong';
    } elseif ($isEdit) {
        // Mode edit sign yang sudah ada
        $signId = (int)$_POST['sign_id'];
        $oldFile = $_POST['old_file'] ?? '';
        
        // Cek apakah label sudah digunakan oleh sign lain
        $checkLabelStmt = $mysqli->prepare('SELECT id FROM sign WHERE label = ? AND id != ?');
        $checkLabelStmt->bind_param('si', $label, $signId);
        $checkLabelStmt->execute();
        $checkLabelStmt->store_result();
        
        if ($checkLabelStmt->num_rows > 0) {
            $existingLabelError = 'Label "' . htmlspecialchars($label) . '" sudah ada di database!';
        } else {
            // Jika ada file baru yang diupload
            if (!empty($tempFile)) {
                if (!empty($oldFile)) {
                    deletePhysicalFile($oldFile);
                }
                
                // Pindahkan file temp ke permanen dengan nama berdasarkan label
                $fileName = moveTempToPermanent($tempFile, $label, $selectedFileName);
                $format = getFileFormat($selectedFileName);
                
                if ($fileName) {
                    // Update data sign di database
                    $stmt = $mysqli->prepare('UPDATE sign SET label = ?, file = ?, format = ?, category = ?, uploader = ? WHERE id = ?');
                    $stmt->bind_param('ssssii', $label, $fileName, $format, $category, $admin_id, $signId);
                    
                    if ($stmt->execute()) {
                        // Hapus file temp
                        if (!empty($tempFile) && file_exists(getTempPath() . $tempFile)) {
                            deleteTempFile($tempFile);
                        }
                        
                        $_SESSION['success_message'] = 'Sign berhasil diperbarui!';
                        header('Location: ./admin.php');
                        exit;
                    } else {
                        $error = 'Gagal memperbarui sign: ' . $stmt->error;
                        // Hapus file yang sudah dipindahkan jika gagal
                        if (!empty($fileName)) {
                            deletePhysicalFile($fileName);
                        }
                    }
                    $stmt->close();
                } else {
                    $error = 'Gagal memindahkan atau mengonversi file';
                }
            } else {
                // Tidak ada file baru, hanya update label
                // Jika label berubah, kita perlu rename file fisik juga
                if ($label !== $sign['label'] && !empty($sign['file'])) {
                    // Buat nama file baru berdasarkan label baru
                    $oldFilePath = getUploadsPath() . $sign['file'];
                    $ext = pathinfo($sign['file'], PATHINFO_EXTENSION);
                    $newFilename = generateLabelBasedFilename($label, $sign['file']);
                    $newFilePath = getUploadsPath() . $newFilename;
                    
                    // Rename file fisik jika file lama ada
                    if (file_exists($oldFilePath)) {
                        if (rename($oldFilePath, $newFilePath)) {
                            // Update database dengan nama file baru
                            $stmt = $mysqli->prepare('UPDATE sign SET label = ?, file = ?, category = ?, uploader = ? WHERE id = ?');
                            $stmt->bind_param('sssii', $label, $newFilename, $category, $admin_id, $signId);
                        } else {
                            $error = 'Gagal mengganti nama file';
                            $stmt = $mysqli->prepare('UPDATE sign SET label = ?, category = ?, uploader = ? WHERE id = ?');
                            $stmt->bind_param('ssii', $label, $category, $admin_id, $signId);
                        }
                    } else {
                        // File tidak ada, hanya update label
                        $stmt = $mysqli->prepare('UPDATE sign SET label = ?, category = ?, uploader = ? WHERE id = ?');
                        $stmt->bind_param('ssii', $label, $category, $admin_id, $signId);
                    }
                } else {
                    // Label tidak berubah, hanya update kategori jika perlu
                    $stmt = $mysqli->prepare('UPDATE sign SET label = ?, category = ?, uploader = ? WHERE id = ?');
                    $stmt->bind_param('ssii', $label, $category, $admin_id, $signId);
                }
                
                if (empty($error)) {
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = 'Sign berhasil diperbarui!';
                        header('Location: ./admin.php');
                        exit;
                    } else {
                        $error = 'Gagal memperbarui sign: ' . $stmt->error;
                    }
                }
                $stmt->close();
            }
        }
        $checkLabelStmt->close();
    } else {
        // Upload sign baru
        if (empty($tempFile) && !$isEdit) {
            $error = 'File harus diupload';
        } else {
            // Cek apakah label sudah ada di database
            $checkStmt = $mysqli->prepare('SELECT id FROM sign WHERE label = ?');
            $checkStmt->bind_param('s', $label);
            $checkStmt->execute();
            $checkStmt->store_result();
            
            if ($checkStmt->num_rows > 0) {
                $existingLabelError = 'Label "' . htmlspecialchars($label) . '" sudah ada di database!';
                // File temp tetap disimpan untuk digunakan kembali
            } else {
                // Pindahkan file temp ke permanen dengan nama berdasarkan label
                $fileName = moveTempToPermanent($tempFile, $label, $selectedFileName);
                $format = getFileFormat($selectedFileName);
                
                if ($fileName) {
                    // Simpan data sign baru ke database
                    $stmt = $mysqli->prepare('INSERT INTO sign (label, file, format, category, uploader) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bind_param('ssssi', $label, $fileName, $format, $category, $admin_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = 'Sign berhasil diupload!';
                        header('Location: ./admin.php');
                        exit;
                    } else {
                        $error = 'Gagal menyimpan ke database: ' . $stmt->error;
                        // Hapus file jika gagal simpan ke database
                        if (!empty($fileName)) {
                            deletePhysicalFile($fileName);
                        }
                    }
                    $stmt->close();
                } else {
                    $error = 'Gagal memindahkan atau mengonversi file';
                }
            }
            $checkStmt->close();
        }
    }
}
?>

<!-- Halaman HTML untuk form upload/edit sign -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Edit' : 'Upload' ?> Sign - BISINDO</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="./assets/css/upload.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Efek saat menyeret file ke area upload */
        .file-upload-container.drag-over {
            border-color: var(--primary-color);
            background-color: rgba(37, 99, 235, 0.05);
        }
        
        /* Badge untuk menampilkan tipe file */
        .file-type-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            background: var(--primary-color);
            color: white;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        /* Tampilan file saat ini (untuk mode edit) */
        .current-file {
            margin-top: 1rem;
            padding: 1rem;
            background: var(--surface-color);
            border-radius: 6px;
            border-left: 4px solid var(--primary-color);
        }
        
        /* Pesan error */
        .message-error {
            background: #ef4444;
            color: white;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        /* Pesan peringatan */
        .message-warning {
            background: #f59e0b;
            color: white;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        /* Area preview file */
        .file-preview {
            margin-top: 1rem;
            padding: 1rem;
            background: #f0f9ff;
            border-radius: 6px;
            border-left: 4px solid #3b82f6;
        }
        
        /* Indikator file siap */
        .file-ready {
            color: #10b981;
        }
        
        /* Warna hint untuk kategori berbeda */
        .category-hint-single {
            color: var(--success-color) !important;
        }
        
        .category-hint-multi {
            color: var(--warning-color) !important;
        }
        
        .category-hint-character {
            color: var(--purple-color) !important;
        }
    </style>
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
        <div class="upload-card">
            <h1><?= $isEdit ? 'EDIT ISYARAT' : 'UPLOAD ISYARAT' ?></h1>

            <!-- Tampilkan pesan peringatan jika label sudah ada -->
            <?php if ($existingLabelError): ?>
                <div class="message-warning">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($existingLabelError) ?>
                </div>
            <?php endif; ?>
            
            <!-- Tampilkan pesan error jika ada -->
            <?php if ($error): ?>
                <div class="message-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <!-- Form upload/edit -->
            <form method="post" enctype="multipart/form-data" class="upload-form" id="uploadForm">
                <?php if ($isEdit): ?>
                    <!-- Data tersembunyi untuk mode edit -->
                    <input type="hidden" name="sign_id" value="<?= $sign['id'] ?>">
                    <input type="hidden" name="old_file" value="<?= htmlspecialchars($sign['file']) ?>">
                <?php endif; ?>
                
                <!-- Simpan data file temporer (tidak terlihat oleh user) -->
                <input type="hidden" name="temp_file" id="tempFileInput" value="<?= htmlspecialchars($tempFile) ?>">
                <input type="hidden" name="selected_file_name" id="selectedFileNameInput" value="<?= htmlspecialchars($selectedFileName) ?>">
                <!-- Input tersembunyi untuk kategori -->
                <input type="hidden" name="category" id="categoryHiddenInput" value="<?= htmlspecialchars($currentCategory) ?>">
                
                <!-- Input untuk nama sign -->
                <div class="form-group">
                    <label class="form-label">Nama Isyarat :</label>
                    <input type="text" name="label" class="form-control" required 
                           value="<?= htmlspecialchars($isEdit ? (empty($existingLabelError) ? $sign['label'] : $currentLabel) : ($currentLabel ?? '')) ?>"
                           placeholder="Contoh: halo, terima kasih, apa kabar"
                           id="labelInput"
                           <?php if ($existingLabelError): ?>style="border-color: #f59e0b;"<?php endif; ?>>
                    <?php if ($existingLabelError): ?>
                        <div style="color: #f59e0b; font-size: 0.9rem; margin-top: 0.25rem;">
                            <i class="fas fa-info-circle"></i> Label ini sudah ada di database
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Dropdown pilihan kategori -->
                <div class="form-group">
                    <label class="form-label">Kategori :</label>
                    
                    <!-- UI dropdown kustom untuk kategori -->
                    <div class="upload-custom-select-wrapper">
                        <div class="upload-custom-select" id="uploadCustomCategorySelect">
                            <div class="upload-custom-select-trigger">
                                <span id="uploadCategoryText">
                                    <?php 
                                    // Teks yang ditampilkan untuk setiap kategori
                                    $categoryDisplay = [
                                        'single_word' => 'Single Word',
                                        'multi_word' => 'Multi Word', 
                                        'character' => 'Character'
                                    ];
                                    echo htmlspecialchars($categoryDisplay[$currentCategory] ?? 'Single Word (Satu kata)');
                                    ?>
                                </span>
                                <div class="upload-custom-select-arrow">▼</div>
                            </div>
                            <div class="upload-custom-options">
                                <div class="upload-custom-option" data-value="single_word">Single Word</div>
                                <div class="upload-custom-option" data-value="multi_word">Multi Word</div>
                                <div class="upload-custom-option" data-value="character">Character</div>
                            </div>
                        </div>
                    </div>
                    <!-- Area untuk menampilkan hint kategori -->
                    <div id="categoryHint" style="margin-top: 0.5rem; font-size: 0.9rem; color: #666;"></div>
                </div>
                
                <!-- Input untuk mengupload file -->
                <div class="form-group">
                    <label class="form-label">File <?= $isEdit ? '(Biarkan kosong jika tidak ingin mengganti)' : '' ?>:</label>
                    <div class="file-upload-container">
                        <input type="file" name="file" class="file-input" id="fileInput" 
                               <?= (!$isEdit && empty($tempFile)) ? 'required' : '' ?> 
                               accept="image/*,video/*">
                        <label for="fileInput" class="file-upload-label">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text" id="uploadText">
                                <?php if (!empty($tempFile) && !$isEdit): ?>
                                    <!-- Tampilkan jika file sudah dipilih (upload baru) -->
                                    <i class="fas fa-check-circle file-ready"></i>
                                    File siap: <?= htmlspecialchars($selectedFileName) ?>
                                    <br>
                                    <small>Klik untuk mengganti file</small>
                                <?php elseif ($isEdit && $sign['file']): ?>
                                    <!-- Tampilkan file saat ini (mode edit) -->
                                    File saat ini: <?= htmlspecialchars($sign['file']) ?>
                                    <br>
                                    <small>Klik untuk mengganti file</small>
                                <?php else: ?>
                                    <!-- Tampilkan default (belum ada file) -->
                                    Klik untuk upload file
                                <?php endif; ?>
                            </div>
                            <div class="file-requirements">
                                Maksimal 10MB. Format: JPG, PNG, GIF, MP4, WebM
                            </div>
                        </label>
                    </div>
                    
                    <?php if ($isEdit && $sign['file']): ?>
                        <!-- Tampilkan informasi file saat ini (hanya mode edit) -->
                        <div class="current-file">
                            <i class="fas fa-file"></i> 
                            File saat ini: <?= htmlspecialchars($sign['file']) ?>
                            <br>
                            <a href="./uploads/<?= htmlspecialchars($sign['file']) ?>" target="_blank" 
                               style="color: var(--primary-color); font-size: 0.9rem;">
                                <i class="fas fa-external-link-alt"></i> Lihat file
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Tombol aksi -->
                <div class="form-group">
                    <button type="submit" class="btn" style="width: 100%;">
                        <i class="fas fa-<?= $isEdit ? 'save' : 'upload' ?>"></i>
                        <?= $isEdit ? 'Simpan Perubahan' : 'Upload Isyarat' ?>
                    </button>
                    <a href="./admin.php" class="btn-dashboard">
                        <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript untuk interaksi di halaman -->
    <script>
        // Bagian ini mengatur dropdown kategori kustom
        document.addEventListener("DOMContentLoaded", function () {
            const uploadCategorySelect = document.getElementById("uploadCustomCategorySelect");
            if (uploadCategorySelect) {
                const trigger = uploadCategorySelect.querySelector(".upload-custom-select-trigger");
                const options = uploadCategorySelect.querySelector(".upload-custom-options");
                const optionItems = uploadCategorySelect.querySelectorAll(".upload-custom-option");
                const hiddenInput = document.getElementById("categoryHiddenInput");
                const categoryText = document.getElementById("uploadCategoryText");
                const categoryHint = document.getElementById("categoryHint");
                
                if (!trigger || !options) return;
                
                // Clone elemen untuk menghapus event listener yang ada
                const newTrigger = trigger.cloneNode(true);
                trigger.parentNode.replaceChild(newTrigger, trigger);
                
                const newOptions = options.cloneNode(true);
                options.parentNode.replaceChild(newOptions, options);
                
                // Ambil elemen yang baru di-clone
                const freshTrigger = uploadCategorySelect.querySelector(".upload-custom-select-trigger");
                const freshOptions = uploadCategorySelect.querySelector(".upload-custom-options");
                const freshOptionItems = uploadCategorySelect.querySelectorAll(".upload-custom-option");
                
                // Set status terpilih berdasarkan nilai awal
                const currentValue = hiddenInput.value;
                freshOptionItems.forEach((option) => {
                    if (option.getAttribute("data-value") === currentValue) {
                        option.classList.add("selected");
                    }
                });
                
                // Toggle dropdown saat diklik
                freshTrigger.addEventListener("click", function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    
                    const isActive = freshOptions.classList.contains("active");
                    
                    // Tutup dropdown lain yang terbuka
                    document.querySelectorAll(".upload-custom-options").forEach((opt) => {
                        if (opt !== freshOptions) {
                            opt.classList.remove("active");
                            opt.previousElementSibling?.classList.remove("active");
                        }
                    });
                    
                    // Buka/tutup dropdown saat ini
                    if (!isActive) {
                        freshOptions.classList.add("active");
                        freshTrigger.classList.add("active");
                    } else {
                        freshOptions.classList.remove("active");
                        freshTrigger.classList.remove("active");
                    }
                });
                
                // Tangani pemilihan opsi kategori
                freshOptionItems.forEach((option) => {
                    option.addEventListener("click", function (e) {
                        e.stopPropagation();
                        e.preventDefault();
                        
                        const value = this.getAttribute("data-value");
                        const text = this.textContent;
                        
                        // Update teks yang ditampilkan
                        if (categoryText) {
                            categoryText.textContent = text;
                        }
                        
                        // Update input tersembunyi
                        if (hiddenInput) {
                            hiddenInput.value = value;
                        }
                        
                        // Update status terpilih
                        freshOptionItems.forEach((opt) => opt.classList.remove("selected"));
                        this.classList.add("selected");
                        
                        // Update hint kategori
                        updateCategoryHint(value);
                        
                        // Tutup dropdown
                        freshOptions.classList.remove("active");
                        freshTrigger.classList.remove("active");
                    });
                });
                
                // Tutup dropdown saat klik di luar
                document.addEventListener("click", function (e) {
                    if (!e.target.closest(".upload-custom-select")) {
                        document.querySelectorAll(".upload-custom-options").forEach((options) => {
                            options.classList.remove("active");
                        });
                        document.querySelectorAll(".upload-custom-select-trigger").forEach((trigger) => {
                            trigger.classList.remove("active");
                        });
                    }
                });
                
                // Fungsi untuk memperbarui hint kategori
                function updateCategoryHint(value) {
                    if (!categoryHint) return;
                    
                    const hints = {
                        'single_word': '<strong>Terdeteksi :</strong> SINGLE WORD (1 kata)',
                        'multi_word': '<strong>Terdeteksi :</strong> MULTI WORD (lebih dari 1 kata)',
                        'character': '<strong>Terdeteksi :</strong> CHARACTER (1 huruf/angka)'
                    };
                    
                    const colors = {
                        'single_word': 'var(--success-color)',
                        'multi_word': 'var(--warning-color)',
                        'character': 'var(--purple-color)'
                    };
                    
                    if (hints[value]) {
                        categoryHint.innerHTML = hints[value];
                        categoryHint.style.color = colors[value];
                    }
                }
                
                // Inisialisasi hint pertama kali
                updateCategoryHint(currentValue);
            }
        });
        
        // Deteksi otomatis kategori berdasarkan input label
        const labelInput = document.getElementById('labelInput');
        const categoryHiddenInput = document.getElementById('categoryHiddenInput');
        const categoryHint = document.getElementById('categoryHint');
        
        if (labelInput && categoryHiddenInput && categoryHint) {
            labelInput.addEventListener('input', function() {
                const label = this.value.trim().toLowerCase();
                
                if (!label) {
                    categoryHint.innerHTML = '';
                    return;
                }
                
                // Hitung jumlah kata
                const words = label.split(/\s+/).filter(w => w.length > 0);
                const wordCount = words.length;
                
                let detectedCategory = 'single_word';
                let hintText = '';
                let hintColor = '';
                
                if (wordCount >= 2) {
                    detectedCategory = 'multi_word';
                    hintText = '<strong>Terdeteksi :</strong> MULTI WORD (' + wordCount + ' kata)';
                    hintColor = 'var(--warning-color)';
                } else if (wordCount === 1) {
                    if (label.length === 1 && /^[a-z0-9]$/.test(label)) {
                        detectedCategory = 'character';
                        hintText = '<strong>Terdeteksi :</strong> CHARACTER (1 huruf/angka)';
                        hintColor = 'var(--purple-color)';
                    } else {
                        detectedCategory = 'single_word';
                        hintText = '<strong>Terdeteksi :</strong> SINGLE WORD (1 kata)';
                        hintColor = 'var(--success-color)';
                    }
                }
                
                // Update input tersembunyi
                categoryHiddenInput.value = detectedCategory;
                
                // Update teks dropdown
                const categoryDisplay = {
                    'single_word': 'Single Word',
                    'multi_word': 'Multi Word',
                    'character': 'Character'
                };
                
                const categoryText = document.getElementById('uploadCategoryText');
                if (categoryText) {
                    categoryText.textContent = categoryDisplay[detectedCategory];
                }
                
                // Update status terpilih di dropdown
                const optionItems = document.querySelectorAll('.upload-custom-option');
                optionItems.forEach((option) => {
                    option.classList.remove('selected');
                    if (option.getAttribute('data-value') === detectedCategory) {
                        option.classList.add('selected');
                    }
                });
                
                // Update hint
                categoryHint.innerHTML = hintText;
                categoryHint.style.color = hintColor;
            });
        }
        
        // Submit form dengan tombol Enter
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                const form = document.getElementById('uploadForm');
                if (form) {
                    form.submit();
                }
            }
        });
        
        // Fitur drag & drop untuk upload file
        const fileUploadContainer = document.querySelector('.file-upload-container');
        const fileInput = document.getElementById('fileInput');
        const tempFileInput = document.getElementById('tempFileInput');
        const selectedFileNameInput = document.getElementById('selectedFileNameInput');
        
        if (fileUploadContainer && fileInput) {
            // Saat file diseret ke area upload
            fileUploadContainer.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.add('drag-over');
            });
            
            // Saat file keluar dari area upload
            fileUploadContainer.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.remove('drag-over');
            });
            
            // Saat file di-drop di area upload
            fileUploadContainer.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.remove('drag-over');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(files[0]);
                    fileInput.files = dataTransfer.files;
                    
                    // Tampilkan preview file
                    updateFilePreview(files[0]);
                }
            });
        }

        // Fungsi untuk menampilkan preview file
        function updateFilePreview(file) {
            const uploadText = document.getElementById('uploadText');
            
            if (file) {
                const fileSize = (file.size / (1024 * 1024)).toFixed(2);
                const fileName = file.name.length > 30 ? 
                    file.name.substring(0, 30) + '...' : file.name;
                
                uploadText.innerHTML = `
                    <i class="fas fa-file"></i>
                    ${fileName}
                    <br>
                    <small>${fileSize} MB - Menunggu upload...</small>
                `;
            }
        }
        
        // Tampilkan preview saat file dipilih
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    updateFilePreview(file);
                }
            });
        }
        
        // Validasi form sebelum dikirim
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('fileInput');
            const maxSize = 10 * 1024 * 1024; // 10MB
            
            <?php if (!$isEdit && empty($tempFile)): ?>
                // Untuk upload baru, file wajib diisi
                if (!fileInput.files || fileInput.files.length === 0) {
                    e.preventDefault();
                    alert('Silakan pilih file untuk diupload');
                    return;
                }
            <?php endif; ?>
            
            // Cek ukuran file
            if (fileInput.files && fileInput.files[0]) {
                const file = fileInput.files[0];
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('File terlalu besar! Maksimal 10MB.');
                    fileInput.value = '';
                    
                    // Reset preview
                    const uploadText = document.getElementById('uploadText');
                    if (uploadText) {
                        <?php if (!empty($tempFile) && !$isEdit): ?>
                            uploadText.innerHTML = `
                                <i class="fas fa-check-circle file-ready"></i>
                                File siap: <?= htmlspecialchars($selectedFileName) ?>
                                <br>
                                <small>Klik untuk mengganti file</small>
                            `;
                        <?php elseif ($isEdit && isset($sign['file'])): ?>
                            uploadText.innerHTML = `
                                File saat ini: <?= htmlspecialchars($sign['file']) ?>
                                <br>
                                <small>Klik untuk mengganti file</small>
                            `;
                        <?php else: ?>
                            uploadText.innerHTML = `Klik untuk upload file`;
                        <?php endif; ?>
                    }
                }
            }
            
            // Validasi label tidak boleh kosong
            const labelValue = document.getElementById('labelInput').value.trim();
            if (!labelValue) {
                e.preventDefault();
                alert('Label tidak boleh kosong');
                document.getElementById('labelInput').focus();
                return;
            }
        });
        
        // Mengatur menu hamburger untuk perangkat mobile
        const hamburger = document.getElementById("hamburger");
        const mobileNav = document.getElementById("mobileNav");
        const overlay = document.getElementById("overlay");

        function toggleMenu() {
            hamburger.classList.toggle("active");
            mobileNav.classList.toggle("active");
            overlay.classList.toggle("active");
            document.body.style.overflow = mobileNav.classList.contains("active") ? "hidden" : "";
        }

        if (hamburger) hamburger.addEventListener("click", toggleMenu);
        if (overlay) overlay.addEventListener("click", toggleMenu);

        // Tutup menu saat link di mobile nav diklik
        const mobileLinks = mobileNav.querySelectorAll("a");
        mobileLinks.forEach((link) => {
            link.addEventListener("click", toggleMenu);
        });
        
        // Inisialisasi saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            // Trigger deteksi kategori jika label sudah ada isinya
            if (labelInput && labelInput.value) {
                labelInput.dispatchEvent(new Event('input'));
            }
            
            // Fokus ke input label jika tidak ada error
            <?php if (!$existingLabelError): ?>
                labelInput.focus();
            <?php endif; ?>
            
            // Update preview jika sudah ada file temp
            <?php if (!empty($tempFile) && !$isEdit): ?>
                const uploadText = document.getElementById('uploadText');
                if (uploadText) {
                    uploadText.innerHTML = `
                        <i class="fas fa-check-circle file-ready"></i>
                        File siap: <?= htmlspecialchars($selectedFileName) ?>
                        <br>
                        <small>Klik untuk mengganti file</small>
                    `;
                }
            <?php endif; ?>
            
            // Inisialisasi dropdown kategori
            const currentCategory = '<?= $currentCategory ?>';
            const categoryOptions = document.querySelectorAll('.upload-custom-option');
            categoryOptions.forEach(option => {
                if (option.getAttribute('data-value') === currentCategory) {
                    option.classList.add('selected');
                }
            });
        });
    </script>
</body> 
</html>