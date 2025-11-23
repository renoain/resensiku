<?php
require_once 'config/constants.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get all books with ratings
$books = $db->query("
    SELECT b.*, 
           AVG(r.rating) as avg_rating,
           COUNT(r.id) as review_count,
           bs.status as bookshelf_status
    FROM books b 
    LEFT JOIN reviews r ON b.id = r.book_id 
    LEFT JOIN bookshelf bs ON b.id = bs.book_id AND bs.user_id = " . $_SESSION['user_id'] . "
    GROUP BY b.id 
    ORDER BY b.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get all active genres with book counts
$genres = $db->query("
    SELECT g.*, 
           (SELECT COUNT(*) FROM books WHERE genres LIKE CONCAT('%', g.name, '%')) as book_count
    FROM genres g 
    WHERE g.is_active = TRUE 
    ORDER BY g.name
")->fetchAll(PDO::FETCH_ASSOC);

// Get featured books
$featured_books = $db->query("
    SELECT b.*, 
           AVG(r.rating) as avg_rating,
           COUNT(r.id) as review_count
    FROM books b 
    LEFT JOIN reviews r ON b.id = r.book_id 
    WHERE b.is_featured = TRUE 
    GROUP BY b.id 
    ORDER BY b.created_at DESC 
    LIMIT 8  -- Tampilkan lebih banyak buku featured
")->fetchAll(PDO::FETCH_ASSOC);

// Get user's name for dropdown
$user_name = $_SESSION['user_name'];
$user_initials = strtoupper(substr(explode(' ', $user_name)[0], 0, 1));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resensiku - Platform Review Buku</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="user-header">
        <div class="header-content">
            <!-- Logo & Title -->
            <div class="logo-section">
                <img src="assets/images/logo/Resensiku.png" alt="Resensiku" class="logo">
            </div>

            <!-- Search Bar -->
            <div class="search-section">
                <form class="search-form">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" placeholder="Cari buku, penulis, atau genre...">
                </form>
            </div>

            <!-- User Navigation -->
            <nav class="user-nav">
                <div class="user-dropdown">
                    <button class="user-trigger">
                        <div class="user-avatar"><?php echo $user_initials; ?></div>
                        <span><?php echo explode(' ', $user_name)[0]; ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="bookshelf.php" class="dropdown-item">
                            <i class="fas fa-bookmark"></i>
                            <span>Bookshelf Saya</span>
                        </a>
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            <span>Profil Saya</span>
                        </a>
                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Hero Section -->
            <section class="hero-section">
                <h1 class="hero-title">Temukan Buku Favorit Anda</h1>
                <p class="hero-subtitle">Jelajahi koleksi buku terbaik, berikan review, dan kelola bookshelf pribadi Anda</p>
            </section>

            <!-- Featured Books -->
            <?php if (!empty($featured_books)): ?>
            <section class="books-section">
                <div class="section-header">
                    <h2 class="section-title">Buku Unggulan</h2>
                    <a href="books.php" class="view-all">Lihat Semua <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="books-grid">
                    <?php foreach ($featured_books as $book): ?>
                    <div class="book-card" onclick="window.location.href='book-detail.php?id=<?php echo $book['id']; ?>'">
                        <div class="book-cover">
                            <img src="assets/images/books/<?php echo $book['cover_image'] ?? 'default-cover.png'; ?>" 
                                 alt="<?php echo htmlspecialchars($book['title']); ?>"
                                 onerror="this.src='assets/images/books/default-cover.png'">
                            <?php if ($book['bookshelf_status']): ?>
                                <div class="book-status">
                                    <?php 
                                    $status_text = [
                                        'want_to_read' => 'Ingin Dibaca',
                                        'reading' => 'Sedang Dibaca', 
                                        'read' => 'Sudah Dibaca'
                                    ];
                                    echo $status_text[$book['bookshelf_status']];
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="book-info">
                            <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                            <p class="book-author"><?php echo htmlspecialchars($book['author']); ?></p>
                            <div class="book-meta">
                                <div class="book-rating">
                                    <div class="stars">
                                        <?php
                                        $rating = round($book['avg_rating'] ?? 0);
                                        for ($i = 1; $i <= 5; $i++):
                                            if ($i <= $rating):
                                        ?>
                                            <i class="fas fa-star"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif; endfor; ?>
                                    </div>
                                    <span><?php echo number_format($book['avg_rating'] ?? 0, 1); ?></span>
                                </div>
                                <?php if ($book['publication_year']): ?>
                                    <span class="book-year"><?php echo $book['publication_year']; ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($book['genres']): ?>
                                <div class="book-genres">
                                    <?php 
                                    $book_genres = explode(',', $book['genres']);
                                    foreach (array_slice($book_genres, 0, 2) as $genre): 
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
            </section>
            <?php endif; ?>

            <!-- All Books -->
            <section class="books-section">
                <div class="section-header">
                    <h2 class="section-title">Semua Buku</h2>
                    <span class="total-books"><?php echo count($books); ?> buku tersedia</span>
                </div>
                <div class="books-grid">
                    <?php foreach ($books as $book): ?>
                    <div class="book-card" onclick="window.location.href='book-detail.php?id=<?php echo $book['id']; ?>'">
                        <div class="book-cover">
                            <img src="assets/images/books/<?php echo $book['cover_image'] ?? 'default-cover.png'; ?>" 
                                 alt="<?php echo htmlspecialchars($book['title']); ?>"
                                 onerror="this.src='assets/images/books/default-cover.png'">
                            <?php if ($book['bookshelf_status']): ?>
                                <div class="book-status">
                                    <?php 
                                    $status_text = [
                                        'want_to_read' => 'Ingin Dibaca',
                                        'reading' => 'Sedang Dibaca', 
                                        'read' => 'Sudah Dibaca'
                                    ];
                                    echo $status_text[$book['bookshelf_status']];
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="book-info">
                            <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                            <p class="book-author"><?php echo htmlspecialchars($book['author']); ?></p>
                            <div class="book-meta">
                                <div class="book-rating">
                                    <div class="stars">
                                        <?php
                                        $rating = round($book['avg_rating'] ?? 0);
                                        for ($i = 1; $i <= 5; $i++):
                                            if ($i <= $rating):
                                        ?>
                                            <i class="fas fa-star"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif; endfor; ?>
                                    </div>
                                    <span><?php echo number_format($book['avg_rating'] ?? 0, 1); ?></span>
                                </div>
                                <?php if ($book['publication_year']): ?>
                                    <span class="book-year"><?php echo $book['publication_year']; ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($book['genres']): ?>
                                <div class="book-genres">
                                    <?php 
                                    $book_genres = explode(',', $book['genres']);
                                    foreach (array_slice($book_genres, 0, 2) as $genre): 
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
            </section>

            <!-- Genres Section -->
            <section class="genres-section">
                <div class="section-header">
                    <h2 class="section-title">Jelajahi Berdasarkan Genre</h2>
                </div>
                <div class="genres-grid">
                    <?php foreach ($genres as $genre): ?>
                    <a href="books.php?genre=<?php echo urlencode($genre['name']); ?>" class="genre-card">
                        <div class="genre-icon">
                            <i class="fas fa-<?php echo $genre['icon'] ?? 'book'; ?>"></i>
                        </div>
                        <h3 class="genre-name"><?php echo htmlspecialchars($genre['name']); ?></h3>
                        <span class="genre-count"><?php echo $genre['book_count']; ?> buku</span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </main>

            <!-- Footer -->
    <footer class="user-footer">
        <div class="footer-content">
            <div class="footer-section">
                <img src="assets/images/logo/Resensiku.png" alt="Resensiku" class="footer-logo">
                <p class="footer-text">Platform review buku untuk pembaca.</p>
            </div>
    </footer>

    <script>
        // Dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const dropdowns = document.querySelectorAll('.user-dropdown');
            
            dropdowns.forEach(dropdown => {
                const trigger = dropdown.querySelector('.user-trigger');
                
                trigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dropdown.classList.toggle('active');
                });
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function() {
                dropdowns.forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            });

            // Search functionality
            const searchInput = document.querySelector('.search-input');
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const query = this.value.trim();
                    if (query) {
                        window.location.href = `books.php?search=${encodeURIComponent(query)}`;
                    }
                }
            });

            // Add loading state to book cards
            const bookCards = document.querySelectorAll('.book-card');
            bookCards.forEach(card => {
                card.addEventListener('click', function() {
                    this.style.opacity = '0.7';
                    this.style.cursor = 'wait';
                });
            });
        });
    </script>
</body>
</html>