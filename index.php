<?php
require_once 'config/constants.php';
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user stats
$user_id = $_SESSION['user_id'];

// Fix: Use proper PDO execution for prepared statements
$stmt_bookshelf = $db->prepare("SELECT COUNT(*) FROM bookshelf WHERE user_id = ?");
$stmt_bookshelf->execute([$user_id]);
$my_bookshelf_count = $stmt_bookshelf->fetchColumn();

$stmt_reviews = $db->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = ?");
$stmt_reviews->execute([$user_id]);
$my_reviews_count = $stmt_reviews->fetchColumn();

$user_stats = [
    'total_books' => $db->query("SELECT COUNT(*) FROM books")->fetchColumn(),
    'my_bookshelf' => $my_bookshelf_count,
    'my_reviews' => $my_reviews_count
];

// Get featured books (most reviewed)
$featured_books = $db->query("
    SELECT b.*, 
           AVG(r.rating) as avg_rating,
           COUNT(r.id) as review_count
    FROM books b 
    LEFT JOIN reviews r ON b.id = r.book_id 
    GROUP BY b.id 
    ORDER BY review_count DESC, avg_rating DESC 
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent books
$recent_books = $db->query("
    SELECT b.*, 
           AVG(r.rating) as avg_rating,
           COUNT(r.id) as review_count
    FROM books b 
    LEFT JOIN reviews r ON b.id = r.book_id 
    GROUP BY b.id 
    ORDER BY b.created_at DESC 
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Get popular genres
$popular_genres = $db->query("
    SELECT genres, COUNT(*) as book_count 
    FROM books 
    WHERE genres IS NOT NULL AND genres != ''
    GROUP BY genres 
    ORDER BY book_count DESC 
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Get user's reading progress
$stmt_progress = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM bookshelf 
    WHERE user_id = ? 
    GROUP BY status
");
$stmt_progress->execute([$user_id]);
$reading_progress = $stmt_progress->fetchAll(PDO::FETCH_ASSOC);

// Initialize progress counts
$progress_counts = [
    'want_to_read' => 0,
    'reading' => 0,
    'read' => 0
];

foreach ($reading_progress as $progress) {
    $progress_counts[$progress['status']] = $progress['count'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda - Resensiku</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="home-page">
    <!-- Header -->
    <header class="main-header">
        <div class="header-container">
            <div class="header-brand">
                <img src="assets/images/logo/Resensiku.png" alt="Resensiku" class="header-logo">
                <span class="brand-name">Resensiku</span>
            </div>
            
            <nav class="header-nav">
                <a href="index.php" class="nav-link active">
                    <i class="fas fa-home"></i> Beranda
                </a>
                <a href="books.php" class="nav-link">
                    <i class="fas fa-book"></i> Jelajah Buku
                </a>
                <a href="bookshelf.php" class="nav-link">
                    <i class="fas fa-bookmark"></i> Bookshelf Saya
                </a>
            </nav>
            
            <div class="header-actions">
                <div class="user-menu">
                    <button class="user-btn">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                        </div>
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="user-dropdown">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> Profil Saya
                        </a>
                        <a href="bookshelf.php" class="dropdown-item">
                            <i class="fas fa-bookmark"></i> Bookshelf
                        </a>
                        <a href="reviews.php" class="dropdown-item">
                            <i class="fas fa-star"></i> Review Saya
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Welcome Section -->
        <section class="welcome-section">
            <div class="welcome-content">
                <h1>Selamat Datang, <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?>! 👋</h1>
                <p>Jelajahi dunia literasi dan bagikan pengalaman membaca Anda</p>
            </div>
            <div class="welcome-stats">
                <div class="stat-badge">
                    <i class="fas fa-book-open"></i>
                    <span><?php echo $user_stats['total_books']; ?>+ Buku Tersedia</span>
                </div>
            </div>
        </section>

        <!-- Quick Stats -->
        <section class="stats-section">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $user_stats['total_books']; ?></span>
                        <span class="stat-label">Total Buku</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-bookmark"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $user_stats['my_bookshelf']; ?></span>
                        <span class="stat-label">Di Bookshelf</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $user_stats['my_reviews']; ?></span>
                        <span class="stat-label">Review Saya</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Reading Progress -->
        <section class="progress-section">
            <div class="section-header">
                <h2>📊 Progress Membaca</h2>
                <a href="bookshelf.php" class="view-all">Lihat Semua</a>
            </div>
            <div class="progress-grid">
                <div class="progress-item">
                    <div class="progress-icon want-to-read">
                        <i class="fas fa-bookmark"></i>
                    </div>
                    <div class="progress-info">
                        <span class="progress-count"><?php echo $progress_counts['want_to_read']; ?></span>
                        <span class="progress-label">Ingin Dibaca</span>
                    </div>
                </div>
                
                <div class="progress-item">
                    <div class="progress-icon reading">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="progress-info">
                        <span class="progress-count"><?php echo $progress_counts['reading']; ?></span>
                        <span class="progress-label">Sedang Dibaca</span>
                    </div>
                </div>
                
                <div class="progress-item">
                    <div class="progress-icon read">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="progress-info">
                        <span class="progress-count"><?php echo $progress_counts['read']; ?></span>
                        <span class="progress-label">Selesai Dibaca</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured Books -->
        <section class="featured-section">
            <div class="section-header">
                <h2>🔥 Buku Populer</h2>
                <a href="books.php" class="view-all">Lihat Semua</a>
            </div>
            
            <?php if (empty($featured_books)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open fa-3x"></i>
                    <h3>Belum ada buku</h3>
                    <p>Buku populer akan muncul di sini</p>
                    <a href="admin/books.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Tambah Buku Pertama
                    </a>
                </div>
            <?php else: ?>
                <div class="books-grid">
                    <?php foreach ($featured_books as $book): ?>
                    <div class="book-card">
                        <div class="book-cover">
                            <img src="assets/images/books/<?php echo $book['cover_image'] ?? 'default-cover.png'; ?>" 
                                 alt="<?php echo htmlspecialchars($book['title']); ?>"
                                 onerror="this.src='assets/images/books/default-cover.png'">
                            <div class="book-overlay">
                                <div class="book-actions">
                                    <button class="btn-action btn-shelf" data-book-id="<?php echo $book['id']; ?>" title="Tambah ke Bookshelf">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <a href="book-detail.php?id=<?php echo $book['id']; ?>" class="btn-action btn-view" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="book-info">
                            <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                            <p class="book-author"><?php echo htmlspecialchars($book['author']); ?></p>
                            
                            <div class="book-rating">
                                <div class="stars">
                                    <?php
                                    $rating = $book['avg_rating'] ?? 0;
                                    $fullStars = floor($rating);
                                    $hasHalfStar = ($rating - $fullStars) >= 0.5;
                                    
                                    for ($i = 1; $i <= 5; $i++):
                                        if ($i <= $fullStars): ?>
                                            <i class="fas fa-star"></i>
                                        <?php elseif ($i == $fullStars + 1 && $hasHalfStar): ?>
                                            <i class="fas fa-star-half-alt"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif;
                                    endfor; ?>
                                </div>
                                <span class="rating-value"><?php echo number_format($rating, 1); ?></span>
                            </div>
                            
                            <div class="book-meta">
                                <?php if ($book['publication_year']): ?>
                                    <span class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo $book['publication_year']; ?>
                                    </span>
                                <?php endif; ?>
                                
                                <span class="meta-item">
                                    <i class="fas fa-comment"></i>
                                    <?php echo $book['review_count']; ?> review
                                </span>
                            </div>
                            
                            <?php if ($book['genres']): ?>
                                <div class="book-genres">
                                    <?php 
                                    $genres = explode(',', $book['genres']);
                                    foreach (array_slice($genres, 0, 2) as $genre): 
                                        if (!empty(trim($genre))):
                                    ?>
                                        <span class="genre-tag"><?php echo trim($genre); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Recent Books & Popular Genres -->
        <div class="content-grid">
            <!-- Recent Books -->
            <section class="recent-section">
                <div class="section-header">
                    <h2>📚 Buku Terbaru</h2>
                    <a href="books.php" class="view-all">Lihat Semua</a>
                </div>
                
                <?php if (empty($recent_books)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book fa-2x"></i>
                        <p>Belum ada buku terbaru</p>
                    </div>
                <?php else: ?>
                    <div class="recent-books-list">
                        <?php foreach ($recent_books as $book): ?>
                        <div class="recent-book-item">
                            <div class="recent-book-cover">
                                <img src="assets/images/books/<?php echo $book['cover_image'] ?? 'default-cover.png'; ?>" 
                                     alt="<?php echo htmlspecialchars($book['title']); ?>"
                                     onerror="this.src='assets/images/books/default-cover.png'">
                            </div>
                            <div class="recent-book-info">
                                <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                                <p class="book-author"><?php echo htmlspecialchars($book['author']); ?></p>
                                <div class="book-stats">
                                    <div class="rating">
                                        <i class="fas fa-star"></i>
                                        <span><?php echo number_format($book['avg_rating'] ?? 0, 1); ?></span>
                                    </div>
                                    <div class="reviews">
                                        <i class="fas fa-comment"></i>
                                        <span><?php echo $book['review_count']; ?></span>
                                    </div>
                                </div>
                            </div>
                            <a href="book-detail.php?id=<?php echo $book['id']; ?>" class="recent-book-link">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Popular Genres -->
            <section class="genres-section">
                <div class="section-header">
                    <h2>🏷️ Genre Populer</h2>
                </div>
                
                <?php if (empty($popular_genres)): ?>
                    <div class="empty-state">
                        <i class="fas fa-tags fa-2x"></i>
                        <p>Belum ada genre</p>
                    </div>
                <?php else: ?>
                    <div class="genres-list">
                        <?php foreach ($popular_genres as $genre): ?>
                        <a href="books.php?genre=<?php echo urlencode($genre['genres']); ?>" class="genre-item">
                            <span class="genre-name"><?php echo htmlspecialchars($genre['genres']); ?></span>
                            <span class="genre-count"><?php echo $genre['book_count']; ?> buku</span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="genres-actions">
                    <a href="books.php" class="btn btn-outline btn-full">
                        <i class="fas fa-search"></i> Jelajah Semua Genre
                    </a>
                </div>
            </section>
        </div>

        <!-- Quick Actions -->
        <section class="actions-section">
            <h2>🚀 Mulai Membaca</h2>
            <div class="actions-grid">
                <a href="books.php" class="action-card explore">
                    <div class="action-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Jelajah Buku</h3>
                    <p>Temukan buku baru untuk dibaca</p>
                </a>
                
                <a href="bookshelf.php" class="action-card bookshelf">
                    <div class="action-icon">
                        <i class="fas fa-bookmark"></i>
                    </div>
                    <h3>Bookshelf Saya</h3>
                    <p>Kelola koleksi bacaan Anda</p>
                </a>
                
                <a href="books.php?sort=popular" class="action-card popular">
                    <div class="action-icon">
                        <i class="fas fa-fire"></i>
                    </div>
                    <h3>Buku Populer</h3>
                    <p>Lihat yang sedang trending</p>
                </a>
                
                <a href="profile.php" class="action-card profile">
                    <div class="action-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3>Profil Saya</h3>
                    <p>Kelola akun dan preferensi</p>
                </a>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-brand">
                    <img src="assets/images/logo/Resensiku.png" alt="Resensiku" class="footer-logo">
                    <p>Platform berbagi review buku untuk komunitas pembaca Indonesia</p>
                </div>
                <div class="footer-links">
                    <a href="about.php">Tentang</a>
                    <a href="contact.php">Kontak</a>
                    <a href="privacy.php">Privasi</a>
                    <a href="terms.php">Syarat & Ketentuan</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Resensiku. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="assets/js/main.js"></script>
    <script src="assets/js/home.js"></script>
    
    <script>
        // Bookshelf functionality
        document.addEventListener('DOMContentLoaded', function() {
            const shelfButtons = document.querySelectorAll('.btn-shelf');
            
            shelfButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const bookId = this.getAttribute('data-book-id');
                    addToBookshelf(bookId);
                });
            });
            
            async function addToBookshelf(bookId) {
                try {
                    const response = await fetch('api/bookshelf.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=add&book_id=${bookId}&status=want_to_read`
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('Buku berhasil ditambahkan ke bookshelf!');
                    } else {
                        alert('Gagal menambahkan buku: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat menambahkan buku');
                }
            }
        });
    </script>
</body>
</html>