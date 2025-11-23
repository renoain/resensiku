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

// form actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($action == 'add' || $action == 'edit') {
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $synopsis = trim($_POST['synopsis']);
        $publication_year = $_POST['publication_year'] ?: NULL;
        $page_count = $_POST['page_count'] ?: NULL;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        //  genres - array dari checkbox
        $selected_genres = [];
        if (isset($_POST['genres']) && is_array($_POST['genres'])) {
            $selected_genres = $_POST['genres'];
        }
        $genres_text = implode(', ', $selected_genres);
        
        //  file upload
        $cover_image = null;
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = $_FILES['cover_image']['type'];
            $file_size = $_FILES['cover_image']['size'];
            
            if (in_array($file_type, $allowed_types)) {
                if ($file_size < 2097152) { 
                    $file_extension = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
                    $cover_image = uniqid('book_') . '.' . $file_extension;
                    $upload_path = '../assets/images/books/' . $cover_image;
                    
                    // Create directory if not exists
                    if (!is_dir('../assets/images/books/')) {
                        mkdir('../assets/images/books/', 0755, true);
                    }
                    
                    if (!move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_path)) {
                        $message = '<div class="alert alert-error">Gagal mengupload cover buku</div>';
                        $cover_image = null;
                    }
                } else {
                    $message = '<div class="alert alert-error">Ukuran file terlalu besar. Maksimal 2MB</div>';
                }
            } else {
                $message = '<div class="alert alert-error">Format file tidak didukung. Gunakan JPG, PNG, atau GIF</div>';
            }
        }
        
        // Add new book
        if (empty($message)) {
            if ($action == 'add') {
                $query = "INSERT INTO books (title, author, cover_image, synopsis, publication_year, page_count, genres, is_featured) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$title, $author, $cover_image, $synopsis, $publication_year, $page_count, $genres_text, $is_featured])) {
                    $message = '<div class="alert alert-success">Buku berhasil ditambahkan!</div>';
                    $action = 'list';
                } else {
                    $message = '<div class="alert alert-error">Gagal menambahkan buku</div>';
                }
            } elseif ($action == 'edit') {
                $book_id = $_POST['book_id'];
                
                // If new cover uploaded, update cover image, otherwise keep existing, Delete old cover if exists
                if ($cover_image) {
                    $old_cover = $db->prepare("SELECT cover_image FROM books WHERE id = ?");
                    $old_cover->execute([$book_id]);
                    $old_cover_data = $old_cover->fetch(PDO::FETCH_ASSOC);
                    
                    if ($old_cover_data['cover_image'] && file_exists('../assets/images/books/' . $old_cover_data['cover_image'])) {
                        unlink('../assets/images/books/' . $old_cover_data['cover_image']);
                    }
                    
                    $query = "UPDATE books SET title=?, author=?, cover_image=?, synopsis=?, publication_year=?, page_count=?, genres=?, is_featured=? WHERE id=?";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([$title, $author, $cover_image, $synopsis, $publication_year, $page_count, $genres_text, $is_featured, $book_id]);
                } else {
                    $query = "UPDATE books SET title=?, author=?, synopsis=?, publication_year=?, page_count=?, genres=?, is_featured=? WHERE id=?";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([$title, $author, $synopsis, $publication_year, $page_count, $genres_text, $is_featured, $book_id]);
                }
                
                if ($result) {
                    $message = '<div class="alert alert-success">Buku berhasil diperbarui!</div>';
                    $action = 'list';
                } else {
                    $message = '<div class="alert alert-error">Gagal memperbarui buku</div>';
                }
            }
        }
    }
}

//  delete 
if (isset($_GET['delete'])) {
    $book_id = $_GET['delete'];
    
    // Get cover image before deleting
    $cover_query = $db->prepare("SELECT cover_image FROM books WHERE id = ?");
    $cover_query->execute([$book_id]);
    $cover_data = $cover_query->fetch(PDO::FETCH_ASSOC);
    
    $query = "DELETE FROM books WHERE id = ?";
    $stmt = $db->prepare($query);
    
    // Delete cover file if exists
    if ($stmt->execute([$book_id])) {
        if ($cover_data['cover_image'] && file_exists('../assets/images/books/' . $cover_data['cover_image'])) {
            unlink('../assets/images/books/' . $cover_data['cover_image']);
        }
        
        $message = '<div class="alert alert-success">Buku berhasil dihapus!</div>';
    } else {
        $message = '<div class="alert alert-error">Gagal menghapus buku</div>';
    }
}

// Get books for listing
$books = [];
if ($action == 'list') {
    $books = $db->query("
        SELECT b.*, 
               AVG(r.rating) as avg_rating,
               COUNT(r.id) as review_count
        FROM books b 
        LEFT JOIN reviews r ON b.id = r.book_id 
        GROUP BY b.id 
        ORDER BY b.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Get all active genres for form
$genres = $db->query("SELECT * FROM genres WHERE is_active = TRUE ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get book data for edit
$edit_book = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $book_id = $_GET['id'];
    $stmt = $db->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->execute([$book_id]);
    $edit_book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$edit_book) {
        $message = '<div class="alert alert-error">Buku tidak ditemukan!</div>';
        $action = 'list';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Buku - Admin Resensiku</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="css/admin-main.css"> 
    <link rel="stylesheet" href="css/admin-books.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <!-- Admin Header -->
    <div class="admin-container">
        <header class="admin-header">
            <div class="admin-header-content">
                <div class="admin-brand">
                    <img src="../assets/images/logo/Resensiku.png" alt="Resensiku" class="admin-logo">
                    <h1>Kelola Buku</h1>
                </div>
                <div class="admin-nav">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                    </a>
                    <a href="books.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Tambah Buku
                    </a>
                    <a href="../logout.php" class="btn btn-outline">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </header>

        <div class="admin-main">
            <?php echo $message; ?>

            <!-- Books Listing -->
            <?php if ($action == 'list'): ?>
                <section class="books-listing">
                    <div class="section-header">
                        <h2>Daftar Buku (<?php echo count($books); ?>)</h2>
                        <div class="header-actions">
                            <a href="books.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Tambah Buku Baru
                            </a>
                        </div>
                    </div>

                    <?php if (empty($books)): ?>
                        <div class="empty-state">
                            <i class="fas fa-book-open fa-3x"></i>
                            <h3>Belum ada buku</h3>
                            <p>Mulai dengan menambahkan buku pertama Anda</p>
                            <a href="books.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Tambah Buku Pertama
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="books-grid">
                            <?php foreach ($books as $book): ?>
                            <div class="book-card-admin">
                                <div class="book-cover-admin">
                                    <img src="../assets/images/books/<?php echo $book['cover_image'] ?? 'default-cover.png'; ?>" 
                                         alt="<?php echo htmlspecialchars($book['title']); ?>"
                                         onerror="this.src='../assets/images/books/default-cover.png'">
                                    <div class="book-actions-overlay">
                                        <a href="books.php?action=edit&id=<?php echo $book['id']; ?>" 
                                           class="btn-action btn-edit" title="Edit Buku">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="books.php?delete=<?php echo $book['id']; ?>" 
                                           class="btn-action btn-delete" 
                                           title="Hapus Buku"
                                           onclick="return confirm('Yakin ingin menghapus buku <?php echo addslashes($book['title']); ?>?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="book-info-admin">
                                    <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                                    <p class="book-author"><?php echo htmlspecialchars($book['author']); ?></p>
                                    
                                    <!-- RATING -->
                                    <div class="book-stats" style="margin-bottom: var(--space-sm);">
                                        <div class="rating">
                                            <i class="fas fa-star"></i>
                                            <span><?php echo number_format($book['avg_rating'] ?? 0, 1); ?></span>
                                        </div>
                                        <div class="reviews">
                                            <i class="fas fa-comment"></i>
                                            <span><?php echo $book['review_count']; ?> review</span>
                                        </div>
                                        <?php if ($book['is_featured']): ?>
                                            <div class="featured-badge">
                                                <i class="fas fa-star"></i> Featured
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($book['publication_year']): ?>
                                        <p class="book-year">Tahun: <?php echo $book['publication_year']; ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($book['genres']): ?>
                                        <div class="book-genres">
                                            <?php 
                                            $book_genres = explode(',', $book['genres']);
                                            foreach (array_slice($book_genres, 0, 3) as $genre): 
                                                if (!empty(trim($genre))):
                                            ?>
                                                <span class="genre-tag"><?php echo trim($genre); ?></span>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <p class="book-added">
                                        Ditambah: <?php echo date('d M Y', strtotime($book['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Add/Edit Book Form -->
            <?php elseif ($action == 'add' || $action == 'edit'): ?>
                <section class="book-form-section">
                    <div class="section-header">
                        <h2>
                            <?php echo $action == 'add' ? ' Tambah Buku Baru' : ' Edit Buku: ' . htmlspecialchars($edit_book['title']); ?>
                        </h2>
                        <a href="books.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                        </a>
                    </div>

                    <div class="form-container">
                        <form method="POST" enctype="multipart/form-data" class="book-form" id="bookForm">
                            <?php if ($action == 'edit'): ?>
                                <input type="hidden" name="book_id" value="<?php echo $edit_book['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-section">
                                <h3 class="form-section-title">
                                    <i class="fas fa-info-circle"></i> Informasi Buku
                                </h3>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="title">Judul Buku *</label>
                                        <input type="text" id="title" name="title" required
                                               value="<?php echo $edit_book['title'] ?? ''; ?>"
                                               placeholder="Masukkan judul buku">
                                    </div>
                                    <div class="form-group">
                                        <label for="author">Penulis *</label>
                                        <input type="text" id="author" name="author" required
                                               value="<?php echo $edit_book['author'] ?? ''; ?>"
                                               placeholder="Nama penulis">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="publication_year">Tahun Terbit</label>
                                        <input type="number" id="publication_year" name="publication_year"
                                               value="<?php echo $edit_book['publication_year'] ?? date('Y'); ?>"
                                               min="1900" max="<?php echo date('Y'); ?>"
                                               placeholder="Tahun terbit">
                                    </div>
                                    <div class="form-group">
                                        <label for="page_count">Jumlah Halaman</label>
                                        <input type="number" id="page_count" name="page_count"
                                               value="<?php echo $edit_book['page_count'] ?? ''; ?>"
                                               min="1" placeholder="Jumlah halaman">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Genre</label>
                                    <div class="genres-checkbox-grid">
                                        <?php foreach ($genres as $genre): ?>
                                            <label class="checkbox-label genre-checkbox">
                                                <input type="checkbox" name="genres[]" value="<?php echo htmlspecialchars($genre['name']); ?>"
                                                    <?php 
                                                    if (isset($edit_book['genres'])) {
                                                        $book_genres = explode(',', $edit_book['genres']);
                                                        if (in_array(trim($genre['name']), array_map('trim', $book_genres))) {
                                                            echo 'checked';
                                                        }
                                                    }
                                                    ?>>
                                                <span class="checkmark"></span>
                                                <i class="fas fa-<?php echo $genre['icon']; ?>" style="color: <?php echo $genre['color']; ?>;"></i>
                                                <?php echo htmlspecialchars($genre['name']); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="form-help">Pilih satu atau lebih genre</small>
                                </div>

                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="is_featured" 
                                               <?php echo ($edit_book['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Jadikan buku featured
                                    </label>
                                    <small class="form-help">Buku featured akan ditampilkan di halaman utama</small>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3 class="form-section-title">
                                    <i class="fas fa-image"></i> Cover Buku
                                </h3>
                                
                                <div class="form-group">
                                    <div class="file-upload-container">
                                        <input type="file" id="cover_image" name="cover_image" 
                                               accept="image/jpeg, image/jpg, image/png, image/gif"
                                               class="file-upload">
                                        <label for="cover_image" class="file-upload-label">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <span>Pilih Cover Buku</span>
                                            <small>Format: JPG, PNG, GIF (Maks. 2MB)</small>
                                        </label>
                                    </div>
                                    <?php if ($action == 'edit' && $edit_book['cover_image']): ?>
                                    <div class="current-cover">
                                        <p>Cover Saat Ini:</p>
                                        <img src="../assets/images/books/<?php echo $edit_book['cover_image']; ?>" 
                                             alt="Current Cover" class="current-cover-image"
                                             onerror="this.src='../assets/images/books/default-cover.png'">
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3 class="form-section-title">
                                    <i class="fas fa-file-alt"></i> Sinopsis
                                </h3>
                                
                                <div class="form-group">
                                    <textarea id="synopsis" name="synopsis" rows="6"
                                              placeholder="Tulis sinopsis buku..."><?php echo $edit_book['synopsis'] ?? ''; ?></textarea>
                                    <div class="char-counter">0 karakter</div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="fas fa-save"></i>
                                    <?php echo $action == 'add' ? 'Tambah Buku' : 'Update Buku'; ?>
                                </button>
                                <a href="books.php" class="btn btn-secondary">Batal</a>
                            </div>
                        </form>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Character counter for synopsis
        const synopsisTextarea = document.getElementById('synopsis');
        const charCounter = document.querySelector('.char-counter');

        if (synopsisTextarea && charCounter) {
            synopsisTextarea.addEventListener('input', function() {
                const length = this.value.length;
                charCounter.textContent = length + ' karakter';
                
                if (length > 1000) {
                    charCounter.classList.add('warning');
                } else {
                    charCounter.classList.remove('warning');
                }
            });

            // Initialize counter
            synopsisTextarea.dispatchEvent(new Event('input'));
        }

        // File upload preview
        const coverInput = document.getElementById('cover_image');
        if (coverInput) {
            coverInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Create preview if doesn't exist
                        let preview = document.querySelector('.current-cover');
                        if (!preview) {
                            preview = document.createElement('div');
                            preview.className = 'current-cover';
                            preview.innerHTML = '<p>Preview:</p>';
                            coverInput.parentNode.appendChild(preview);
                        }
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'current-cover-image';
                        preview.innerHTML = '<p>Preview:</p>';
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>
</html>