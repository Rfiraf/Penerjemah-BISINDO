<?php
// Mulai sesi untuk menyimpan data login admin
session_start();

// Hubungkan dengan file konfigurasi database
require_once 'config/database.php';

// Fungsi untuk memproses input teks dari pengguna
// Membersihkan dan memecah teks menjadi kata-kata yang terpisah
function processInput($text) {
    // Ubah ke huruf kecil dan hilangkan spasi di awal/akhir
    $text = mb_strtolower(trim($text), 'UTF-8');
    // Hapus karakter khusus yang tidak diperlukan
    $text = preg_replace('/[^\p{L}\p{N}\'\-\s]/u', '', $text);
    // Pecah teks menjadi array kata-kata berdasarkan spasi
    return preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
}

// Inisialisasi variabel untuk input dan hasil
$input = '';
$results = [];

// Proses form jika ada data yang dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST['text'] ?? '';
    
    if ($input) {
        // Proses input: bersihkan dan pecah menjadi kata-kata
        $words = processInput($input);
        $wordCount = count($words);
        
        // Array untuk menyimpan hasil dengan urutan asli input
        $orderedResults = array_fill(0, $wordCount, null);
        
        // RULE 1: PENCARIAN MULTI_WORD (PRIORITAS TERTINGGI)
        // Cari frasa (rangkaian kata) terlebih dahulu sesuai kategori 'multi_word'
        // Mulai dari frasa terpanjang untuk prioritas tertinggi
        $maxMultiWordLength = $wordCount;
        
        for ($multiWordLength = $maxMultiWordLength; $multiWordLength >= 2; $multiWordLength--) {
            for ($i = 0; $i < $wordCount; $i++) {
                // Lewati jika posisi ini sudah diproses
                if ($orderedResults[$i] !== null) {
                    continue;
                }
                
                // Cek apakah bisa membentuk rangkaian kata dengan panjang tertentu
                if ($i + $multiWordLength - 1 < $wordCount) {
                    // Verifikasi: semua kata dalam rentang harus belum diproses
                    $canProcess = true;
                    for ($j = 0; $j < $multiWordLength; $j++) {
                        if ($orderedResults[$i + $j] !== null) {
                            $canProcess = false;
                            break;
                        }
                    }
                    
                    if ($canProcess) {
                        // Gabungkan kata-kata menjadi multi_word
                        $multiWordText = implode(' ', array_slice($words, $i, $multiWordLength));
                        
                        // Cari di database dengan kategori 'multi_word'
                        $query = "SELECT file, format, category, label FROM sign 
                                 WHERE label = ? AND category = 'multi_word' LIMIT 1";
                        $stmt = $mysqli->prepare($query);
                        $stmt->bind_param('s', $multiWordText);
                        $stmt->execute();
                        
                        // Jika multi_word ditemukan di database
                        if ($row = $stmt->get_result()->fetch_assoc()) {
                            $orderedResults[$i] = $row;
                            
                            // Tandai kata lain dalam multi_word sebagai 'sudah digunakan'
                            for ($j = 1; $j < $multiWordLength; $j++) {
                                $orderedResults[$i + $j] = 'used';
                            }
                            
                            // Lompati kata-kata yang sudah diproses dalam multi_word ini
                            $i += ($multiWordLength - 1);
                        }
                        $stmt->close();
                    }
                }
            }
        }
     
        // RULE 2: PENCARIAN SINGLE_WORD (KATA TUNGGAL)
        // Siapkan array kata-kata yang belum diproses
        $singleWordToProcess = [];
        
        foreach ($orderedResults as $index => $value) {
            if ($value === null) {
                $singleWordToProcess[$index] = $words[$index];
            }
        }
        
        // Cari kata tunggal jika masih ada kata yang belum diproses
        if (!empty($singleWordToProcess)) {
            $uniqueSingleWords = array_unique(array_values($singleWordToProcess));
            $placeholders = str_repeat('?,', count($uniqueSingleWords) - 1) . '?';
            
            // Query untuk mencari kata tunggal di database
            $singleWordQuery = "SELECT file, format, category, label FROM sign 
                         WHERE label IN ($placeholders) AND category = 'single_word'";
            
            $stmt = $mysqli->prepare($singleWordQuery);
            $stmt->bind_param(str_repeat('s', count($uniqueSingleWords)), ...$uniqueSingleWords);
            $stmt->execute();
            
            // Kumpulkan hasil pencarian single_word
            $singleWordMatches = [];
            $singleWordResult = $stmt->get_result();
            while ($row = $singleWordResult->fetch_assoc()) {
                $label = $row['label'];
                if (!isset($singleWordMatches[$label])) {
                    $singleWordMatches[$label] = [];
                }
                $singleWordMatches[$label][] = $row;
            }
            $stmt->close();
            
            // Siapkan array untuk kata-kata yang tidak ditemukan sebagai single_word
            $singleWordsForCharacterFallback = [];
            
            // Proses setiap kata yang perlu dicari
            foreach ($singleWordToProcess as $index => $singleWord) {
                if (isset($singleWordMatches[$singleWord]) && !empty($singleWordMatches[$singleWord])) {
                    // Pilih hasil acak jika ada beberapa hasil untuk single_word yang sama
                    $randomIndex = array_rand($singleWordMatches[$singleWord]);
                    $orderedResults[$index] = $singleWordMatches[$singleWord][$randomIndex];
                } else {
                    // Simpan kata untuk fallback ke pencarian character (per huruf)
                    $singleWordsForCharacterFallback[$index] = $singleWord;
                }
            }
   
            // RULE 3: FALLBACK KE CHARACTER (HURUF PER HURUF)
            // Jika kata tidak ditemukan sebagai single_word, coba pecah menjadi karakter
            if (!empty($singleWordsForCharacterFallback)) {
                $uniqueCharacters = [];
                $singleWordCharactersInfo = [];
                
                // Pecah setiap single_word menjadi karakter (huruf-huruf)
                foreach ($singleWordsForCharacterFallback as $index => $singleWord) {
                    $chars = preg_split('//u', $singleWord, -1, PREG_SPLIT_NO_EMPTY);
                    $singleWordCharactersInfo[$index] = $chars;
                    
                    // Kumpulkan karakter unik untuk pencarian di database
                    foreach ($chars as $char) {
                        $uniqueCharacters[$char] = true;
                    }
                }
                
                $uniqueCharacterKeys = array_keys($uniqueCharacters);
                
                // Cari karakter-karakter di database
                if (!empty($uniqueCharacterKeys)) {
                    $characterPlaceholders = str_repeat('?,', count($uniqueCharacterKeys) - 1) . '?';
                    $characterQuery = "SELECT file, format, category, label FROM sign 
                                   WHERE label IN ($characterPlaceholders) AND category = 'character'";
                    
                    $stmt = $mysqli->prepare($characterQuery);
                    $stmt->bind_param(str_repeat('s', count($uniqueCharacterKeys)), ...$uniqueCharacterKeys);
                    $stmt->execute();
                    
                    // Kumpulkan hasil pencarian character
                    $characterMatches = [];
                    $characterResult = $stmt->get_result();
                    while ($row = $characterResult->fetch_assoc()) {
                        $label = $row['label'];
                        if (!isset($characterMatches[$label])) {
                            $characterMatches[$label] = [];
                        }
                        $characterMatches[$label][] = $row;
                    }
                    $stmt->close();
                    
                    // Proses setiap single_word yang perlu dipecah menjadi karakter
                    foreach ($singleWordsForCharacterFallback as $index => $singleWord) {
                        $chars = $singleWordCharactersInfo[$index];
                        $characterResultsForSingleWord = [];
                        
                        // Cari setiap karakter (huruf) dalam single_word
                        foreach ($chars as $char) {
                            if (isset($characterMatches[$char]) && !empty($characterMatches[$char])) {
                                $randomIndex = array_rand($characterMatches[$char]);
                                $characterResultsForSingleWord[] = $characterMatches[$char][$randomIndex];
                            }
                        }
                        
                        // Simpan hasil jika ditemukan terjemahan per karakter
                        if (!empty($characterResultsForSingleWord)) {
                            $orderedResults[$index] = $characterResultsForSingleWord;
                        }
                    }
                }
            }
        }
        
        // RULE 4: PEMBENTUKAN OUTPUT UNTUK DITAMPILKAN
        // Siapkan hasil akhir untuk ditampilkan di halaman
        foreach ($orderedResults as $result) {
            if ($result !== null && $result !== 'used') {
                // Jika hasil adalah array (karakter per huruf)
                if (is_array($result) && isset($result[0]['label'])) {
                    foreach ($result as $characterResult) {
                        $results[] = $characterResult;
                    }
                } else {
                    // Jika hasil adalah single_word atau multi_word
                    $results[] = $result;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PENERJEMAH BISINDO</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="./assets/css/index.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="./index.php" class="nav-brand">PENERJEMAH BISINDO</a>
            <div class="nav-links">
                <?php if (!empty($_SESSION['admin_id'])): ?>
                    <a href="./admin.php" class="btn">Dashboard</a>
                    <a href="./logout.php" class="btn btn-logout">Logout</a>
                <?php else: ?>
                    <a href="./login.php" class="btn">Login</a>
                <?php endif; ?>
            </div>
            
            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>

    <div class="mobile-nav" id="mobileNav">
        <?php if (!empty($_SESSION['admin_id'])): ?>
            <a href="./admin.php" class="btn">Dashboard</a>
            <a href="./logout.php" class="btn btn-logout">Logout</a>
        <?php else: ?>
            <a href="./login.php" class="btn">Login</a>
        <?php endif; ?>
    </div>

    <div class="overlay" id="overlay"></div>

    <div class="container">
        <div class="translator-container">
            <div class="translator-input-section">
                <h3 class="section-title">Masukkan Teks</h3>
                <form method="post" id="translateForm">
                    <div class="form-group" style="flex-grow: 1; display: flex; flex-direction: column;">
                        <label class="form-label">Masukkan teks bahasa Indonesia :</label>
                        <textarea name="text" id="textInput" class="form-control" rows="10" placeholder="Contoh: halo apa kabar..." style="flex-grow: 1; resize: none;"><?= htmlspecialchars($input) ?></textarea>
                    </div>
                    <button type="submit" class="btn">Translate</button>
                </form>
            </div>

            <div class="translator-output-section">
                <h3 class="section-title">Hasil Terjemahan</h3>
                <?php if (!empty($results)): ?>
                    <?php
                    $resultCount = count($results);
                    $gridClass = 'translator-results-grid ';
                    
                    if ($resultCount === 1) {
                        $gridClass .= 'single-item';
                    } elseif ($resultCount === 2) {
                        $gridClass .= 'two-items';
                    } elseif ($resultCount === 3) {
                        $gridClass .= 'three-items';
                    } else {
                        $gridClass .= 'multiple-items';
                    }
                    ?>
                    <div class="<?= $gridClass ?>">
                        <?php foreach ($results as $r): 
                            $url = 'uploads/' . urlencode($r['file']);
                            $mime = $r['format'] === 'video' ? 'video/mp4' : 'image/jpeg';
                        ?>
                        <div class="translator-result-card">
                            <?php if ($r['format'] == 'video'): ?>
                                <div class="translator-media-container">
                                    <video class="translator-result-media" 
                                           autoplay 
                                           loop 
                                           muted 
                                           playsinline 
                                           preload="none" 
                                           data-src="<?= $url ?>"
                                           poster="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7">
                                    </video>
                                </div>
                            <?php else: ?>
                                <div class="translator-media-container">
                                    <img class="translator-result-media" 
                                         src="<?= $url ?>" 
                                         alt="<?= htmlspecialchars($r['label']) ?>"
                                         loading="lazy">
                                </div>
                            <?php endif; ?>
                            <div class="translator-result-label"><?= htmlspecialchars($r['label']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="placeholder-text">
                        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                            Tidak ada hasil terjemahan untuk teks tersebut.
                        <?php else: ?>
                            Hasil terjemahan akan muncul di sini
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="./assets/js/index.js"></script>
</body>
</html>