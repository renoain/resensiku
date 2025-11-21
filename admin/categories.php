<?php
require_once '../config/constants.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? 'list';
$message = '';

// Create genres table if not exists
$db->exec("
    CREATE TABLE IF NOT EXISTS genres (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        color VARCHAR(7) DEFAULT '#6D6158',
        icon VARCHAR(50),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// Insert default genres 
$checkGenres = $db->query("SELECT COUNT(*) FROM genres")->fetchColumn();
if ($checkGenres == 0) {
    $defaultGenres = [
        ['Romance', 'Novel tentang kisah cinta dan hubungan', '#E91E63', 'heart'],
        ['Fantasy', 'Cerita dengan elemen magis dan dunia imajinatif', '#9C27B0', 'dragon'],
        ['Mystery', 'Cerita detektif dan teka-teki', '#3F51B5', 'search'],
        ['Horror', 'Cerita menegangkan dan menakutkan', '#F44336', 'ghost'],
        ['Comedy', 'Cerita lucu dan menghibur', '#FF9800', 'laugh'],
        ['Action', 'Cerita penuh aksi dan petualangan', '#4CAF50', 'bolt'],
        ['Drama', 'Cerita emosional dan kehidupan', '#2196F3', 'theater-masks'],
        ['Sci-Fi', 'Fiksi ilmiah dan teknologi futuristik', '#00BCD4', 'robot'],
        ['Historical', 'Cerita berdasarkan sejarah', '#795548', 'landmark'],
        ['Biography', 'Kisah hidup orang nyata', '#607D8B', 'user']
    ];
    
    $stmt = $db->prepare("INSERT INTO genres (name, description, color, icon) VALUES (?, ?, ?, ?)");
    foreach ($defaultGenres as $genre) {
        $stmt->execute($genre);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($action == 'add' || $action == 'edit') {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $color = $_POST['color'];
        $icon = $_POST['icon'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Add new genre
        if (empty($name)) {
            $message = '<div class="alert alert-error">Nama genre harus diisi</div>';
        } else {
            if ($action == 'add') {
                $query = "INSERT INTO genres (name, description, color, icon, is_active) VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$name, $description, $color, $icon, $is_active])) {
                    $message = '<div class="alert alert-success">Genre berhasil ditambahkan!</div>';
                    $action = 'list';
                } else {
                    $errorInfo = $stmt->errorInfo();
                    if ($errorInfo[1] == 1062) { // Duplicate entry
                        $message = '<div class="alert alert-error">Genre dengan nama tersebut sudah ada</div>';
                    } else {
                        $message = '<div class="alert alert-error">Gagal menambahkan genre: ' . $errorInfo[2] . '</div>';
                    }
                }
            } elseif ($action == 'edit') {
                $genre_id = $_POST['genre_id'];
                
                // Update genre
                $query = "UPDATE genres SET name=?, description=?, color=?, icon=?, is_active=? WHERE id=?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$name, $description, $color, $icon, $is_active, $genre_id])) {
                    $message = '<div class="alert alert-success">Genre berhasil diperbarui!</div>';
                    $action = 'list';
                } else {
                    $errorInfo = $stmt->errorInfo();
                    if ($errorInfo[1] == 1062) {
                        $message = '<div class="alert alert-error">Genre dengan nama tersebut sudah ada</div>';
                    } else {
                        $message = '<div class="alert alert-error">Gagal memperbarui genre: ' . $errorInfo[2] . '</div>';
                    }
                }
            }
        }
    }
}

//  delete 
if (isset($_GET['delete'])) {
    $genre_id = $_GET['delete'];
    
    // Check genre if used in books
    $checkUsage = $db->prepare("SELECT COUNT(*) FROM books WHERE genres LIKE ?");
    $checkUsage->execute(['%' . $genre_id . '%']);
    $usageCount = $checkUsage->fetchColumn();
    
    if ($usageCount > 0) {
        $message = '<div class="alert alert-error">Tidak bisa menghapus genre karena masih digunakan oleh ' . $usageCount . ' buku</div>';
    } else {
        $query = "DELETE FROM genres WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$genre_id])) {
            $message = '<div class="alert alert-success">Genre berhasil dihapus!</div>';
        } else {
            $message = '<div class="alert alert-error">Gagal menghapus genre</div>';
        }
    }
}

// toggle active status
if (isset($_GET['toggle'])) {
    $genre_id = $_GET['toggle'];
    
    $query = "UPDATE genres SET is_active = NOT is_active WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$genre_id])) {
        $message = '<div class="alert alert-success">Status genre berhasil diubah!</div>';
    } else {
        $message = '<div class="alert alert-error">Gagal mengubah status genre</div>';
    }
}

// Get genres for listing
$genres = [];
if ($action == 'list') {
    $genres = $db->query("
        SELECT g.*, 
               (SELECT COUNT(*) FROM books WHERE genres LIKE CONCAT('%', g.name, '%')) as book_count
        FROM genres g 
        ORDER BY g.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Get genre data for edit
$edit_genre = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $genre_id = $_GET['id'];
    $stmt = $db->prepare("SELECT * FROM genres WHERE id = ?");
    $stmt->execute([$genre_id]);
    $edit_genre = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$edit_genre) {
        $message = '<div class="alert alert-error">Genre tidak ditemukan!</div>';
        $action = 'list';
    }
}

// Available icons for genres
$available_icons = [
    'heart', 'dragon', 'search', 'ghost', 'laugh', 'bolt', 'theater-masks',
    'robot', 'landmark', 'user', 'book', 'star', 'film', 'music', 'gamepad',
    'palette', 'camera', 'code', 'shopping-bag', 'utensils', 'mountain', 'umbrella-beach'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Genre - Admin Resensiku</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="css/admin-main.css"> 
    <link rel="stylesheet" href="css/admin-genre.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Admin Header -->
    <div class="admin-container">
        <header class="admin-header">
            <div class="admin-header-content">
                <div class="admin-brand">
                    <img src="../assets/images/logo/Resensiku.png" alt="Resensiku" class="admin-logo">
                    <h1>Kelola Genre</h1>
                </div>
                <div class="admin-nav">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                    </a>
                    <a href="categories.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Tambah Genre
                    </a>
                    <a href="../logout.php" class="btn btn-outline">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </header>

        <div class="admin-main">
            <?php echo $message; ?>

            <!-- Genres Listing -->
            <?php if ($action == 'list'): ?>
                <section class="genres-listing">
                    <div class="section-header">
                        <h2>  Genre (<?php echo count($genres); ?>)</h2>
                        <div class="header-actions">
                            <a href="categories.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Tambah Genre Baru
                            </a>
                        </div>
                    </div>

                    <?php if (empty($genres)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tags fa-3x"></i>
                            <h3>Belum ada genre</h3>
                            <p>Mulai dengan menambahkan genre pertama Anda</p>
                            <a href="categories.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Tambah Genre Pertama
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="stats-cards">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-tags"></i>
                                </div>
                                <div class="stat-info">
                                    <span class="stat-number"><?php echo count($genres); ?></span>
                                    <span class="stat-label">Total Genre</span>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="stat-info">
                                    <span class="stat-number">
                                        <?php echo count(array_filter($genres, fn($g) => $g['is_active'])); ?>
                                    </span>
                                    <span class="stat-label">Genre Aktif</span>
                                </div>
                            </div>
                        </div>

                        <div class="genres-grid">
                            <?php foreach ($genres as $genre): ?>
                            <div class="genre-card <?php echo $genre['is_active'] ? 'active' : 'inactive'; ?>">
                                <div class="genre-header" style="background-color: <?php echo $genre['color']; ?>20; border-left: 4px solid <?php echo $genre['color']; ?>;">
                                    <div class="genre-icon">
                                        <i class="fas fa-<?php echo $genre['icon'] ?? 'tag'; ?>" style="color: <?php echo $genre['color']; ?>;"></i>
                                    </div>
                                    <div class="genre-info">
                                        <h3><?php echo htmlspecialchars($genre['name']); ?></h3>
                                        <span class="genre-book-count">
                                            <?php echo $genre['book_count']; ?> buku
                                        </span>
                                    </div>
                                    <div class="genre-status">
                                        <span class="status-badge <?php echo $genre['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $genre['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="genre-body">
                                    <?php if ($genre['description']): ?>
                                        <p class="genre-description"><?php echo htmlspecialchars($genre['description']); ?></p>
                                    <?php else: ?>
                                        <p class="genre-description empty">Tidak ada deskripsi</p>
                                    <?php endif; ?>
                                    
                                    <div class="genre-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-palette"></i>
                                            <?php echo $genre['color']; ?>
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('d M Y', strtotime($genre['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="genre-actions">
                                    <a href="categories.php?action=edit&id=<?php echo $genre['id']; ?>" 
                                       class="btn-action btn-edit" title="Edit Genre">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="categories.php?toggle=<?php echo $genre['id']; ?>" 
                                       class="btn-action btn-toggle" 
                                       title="<?php echo $genre['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                                        <i class="fas fa-<?php echo $genre['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                    </a>
                                    <a href="categories.php?delete=<?php echo $genre['id']; ?>" 
                                       class="btn-action btn-delete" 
                                       title="Hapus Genre"
                                       onclick="return confirm('Yakin ingin menghapus genre <?php echo addslashes($genre['name']); ?>?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Add/Edit Genre -->
            <?php elseif ($action == 'add' || $action == 'edit'): ?>
                <section class="genre-form-section">
                    <div class="section-header">
                        <h2>
                            <?php echo $action == 'add' ? 'Tambah Genre Baru' : '✏️ Edit Genre: ' . htmlspecialchars($edit_genre['name']); ?>
                        </h2>
                        <a href="categories.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>

                    <div class="form-container">
                        <form method="POST" class="genre-form" id="genreForm">
                            <?php if ($action == 'edit'): ?>
                                <input type="hidden" name="genre_id" value="<?php echo $edit_genre['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-section">
                                <h3 class="form-section-title">
                                    <i class="fas fa-info-circle"></i> Informasi Genre
                                </h3>
                                
                                <div class="form-group">
                                    <label for="name">Nama Genre *</label>
                                    <input type="text" id="name" name="name" required
                                           value="<?php echo $edit_genre['name'] ?? ''; ?>"
                                           placeholder="Masukkan nama genre">
                                    <small class="form-help">Nama genre harus unik</small>
                                </div>

                                <div class="form-group">
                                    <label for="description">Deskripsi</label>
                                    <textarea id="description" name="description" rows="3"
                                              placeholder="Deskripsi singkat tentang genre..."><?php echo $edit_genre['description'] ?? ''; ?></textarea>
                                    <div class="char-counter">0 karakter</div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3 class="form-section-title">
                                    <i class="fas fa-palette"></i> Tampilan
                                </h3>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="color">Warna</label>
                                        <div class="color-picker-container">
                                            <input type="color" id="color" name="color" 
                                                   value="<?php echo $edit_genre['color'] ?? '#6D6158'; ?>"
                                                   class="color-picker">
                                            <span class="color-value"><?php echo $edit_genre['color'] ?? '#6D6158'; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="icon">Ikon</label>
                                        <select id="icon" name="icon" class="icon-select">
                                            <option value="">Pilih Ikon</option>
                                            <?php foreach ($available_icons as $icon): ?>
                                                <option value="<?php echo $icon; ?>" 
                                                    <?php echo ($edit_genre['icon'] ?? '') == $icon ? 'selected' : ''; ?>>
                                                    <i class="fas fa-<?php echo $icon; ?>"></i> <?php echo ucfirst($icon); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="icon-preview">
                                            <span>Preview: </span>
                                            <i class="fas fa-<?php echo $edit_genre['icon'] ?? 'tag'; ?>" id="iconPreview"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3 class="form-section-title">
                                    <i class="fas fa-cog"></i> Pengaturan
                                </h3>
                                
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="is_active" 
                                               <?php echo ($edit_genre['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Genre Aktif
                                    </label>
                                    <small class="form-help">Genre nonaktif tidak akan muncul di pilihan untuk buku baru</small>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="fas fa-save"></i>
                                    <?php echo $action == 'add' ? 'Tambah Genre' : 'Update Genre'; ?>
                                </button>
                                <a href="categories.php" class="btn btn-secondary">Batal</a>
                            </div>
                        </form>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/admin-categories.js"></script>
</body>
</html>