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

// Get filter parameters
$genre = $_GET['genre'] ?? '';
$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query based on filters
$whereConditions = [];
$params = [];

if (!empty($genre)) {
    $whereConditions[] = "b.genres LIKE ?";
    $params[] = "%$genre%";
}

if (!empty($search)) {
    $whereConditions[] = "(b.title LIKE ? OR b.author LIKE ? OR b.genres LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Get total count for pagination
$countStmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM books b 
    $whereClause
");
$countStmt->execute($params);
$totalBooks = $countStmt->fetch()['total'];
$totalPages = ceil($totalBooks / $limit);

// Get books with pagination
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare("
    SELECT b.*, 
           AVG(r.rating) as avg_rating,
           COUNT(r.id) as review_count,
           bs.status as bookshelf_status
    FROM books b 
    LEFT JOIN reviews r ON b.id = r.book_id 
    LEFT JOIN bookshelf bs ON b.id = bs.book_id AND bs.user_id = " . $_SESSION['user_id'] . "
    $whereClause
    GROUP BY b.id 
    ORDER BY b.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all genres for filter
$genresStmt = $db->query("
    SELECT DISTINCT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(genres, ',', numbers.n), ',', -1)) as genre_name,
           COUNT(*) as book_count
    FROM books 
    CROSS JOIN (
        SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
    ) numbers
    WHERE CHAR_LENGTH(genres) - CHAR_LENGTH(REPLACE(genres, ',', '')) >= numbers.n - 1
    GROUP BY genre_name
    ORDER BY book_count DESC
");
$allGenres = $genresStmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's name for dropdown
$user_name = $_SESSION['user_name'];
$user_initials = strtoupper(substr(explode(' ', $user_name)[0], 0, 1));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semua Buku - Resensiku</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/books.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="user-header">
        <div class="header-content">
            <!-- Logo & Title -->
            <div class="logo-section">
                <a href="index.php">
                    <img src="assets/images/logo/Resensiku.png" alt="Resensiku" class="logo">
                </a>
            </div>

            <!-- Search Bar -->
            <div class="search-section">
                <form class="search-form" method="GET" action="books.php">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" name="search" placeholder="Cari buku, penulis, atau genre..." value="<?php echo htmlspecialchars($search); ?>">
                    <?php if (!empty($genre)): ?>
                        <input type="hidden" name="genre" value="<?php echo htmlspecialchars($genre); ?>">
                    <?php endif; ?>
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
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title-section">
                    <h1 class="page-title">
                        <?php if (!empty($genre)): ?>
                            📚 Buku Genre: <?php echo htmlspecialchars($genre); ?>
                        <?php elseif (!empty($search)): ?>
                            🔍 Hasil Pencarian: "<?php echo htmlspecialchars($search); ?>"
                        <?php else: ?>
                            📖 Semua Buku
                        <?php endif; ?>
                    </h1>
                    <p class="page-subtitle">
                        <?php 
                        if (!empty($genre)) {
                            echo "Menampilkan buku dengan genre " . htmlspecialchars($genre);
                        } elseif (!empty($search)) {
                            echo "Ditemukan $totalBooks buku untuk \"" . htmlspecialchars($search) . "\"";
                        } else {
                            echo "Jelajahi koleksi buku terbaik kami";
                        }
                        ?>
                    </p>
                </div>
                <div class="page-actions">
                    <a href="books.php" class="btn-secondary">
                        <i class="fas fa-refresh"></i>
                        Reset Filter
                    </a>
                </div>
            </div>

            <!-- Books Content -->
            <div class="books-content">
                <!-- Sidebar Filters -->
                <aside class="filters-sidebar">
                    <div class="filter-section">
                        <h3 class="filter-title">
                            <i class="fas fa-filter"></i>
                            Filter Buku
                        </h3>
                        
                        <!-- Genre Filter -->
                        <div class="filter-group">
                            <h4 class="filter-group-title">Genre</h4>
                            <div class="genre-filters">
                                <?php foreach ($allGenres as $genreItem): ?>
                                    <a href="books.php?genre=<?php echo urlencode($genreItem['genre_name']); ?>" 
                                       class="genre-filter <?php echo $genre === $genreItem['genre_name'] ? 'active' : ''; ?>">
                                        <span class="genre-name"><?php echo htmlspecialchars($genreItem['genre_name']); ?></span>
                                        <span class="genre-count"><?php echo $genreItem['book_count']; ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="filter-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $totalBooks; ?></span>
                                <span class="stat-label">Total Buku</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo count($allGenres); ?></span>
                                <span class="stat-label">Genre</span>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- Books Grid -->
                <div class="books-main">
                    <?php if (empty($books)): ?>
                        <div class="books-empty">
                            <div class="empty-icon">
                                <i class="fas fa-book-open"></i>
                            </div>
                            <h3>Buku Tidak Ditemukan</h3>
                            <p>
                                <?php if (!empty($genre)): ?>
                                    Tidak ada buku dengan genre "<?php echo htmlspecialchars($genre); ?>".
                                <?php elseif (!empty($search)): ?>
                                    Tidak ada buku yang cocok dengan pencarian "<?php echo htmlspecialchars($search); ?>".
                                <?php else: ?>
                                    Belum ada buku yang tersedia.
                                <?php endif; ?>
                            </p>
                            <a href="books.php" class="btn btn-primary">
                                <i class="fas fa-refresh"></i>
                                Lihat Semua Buku
                            </a>
                        </div>
                    <?php else: ?>
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
                                            foreach (array_slice($book_genres, 0, 2) as $book_genre): 
                                                if (!empty(trim($book_genre))):
                                            ?>
                                                <span class="genre-tag"><?php echo trim($book_genre); ?></span>
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

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="books.php?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i>
                                    Sebelumnya
                                </a>
                            <?php endif; ?>

                            <div class="pagination-numbers">
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                    <a href="books.php?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                       class="pagination-number <?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>

                            <?php if ($page < $totalPages): ?>
                                <a href="books.php?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn">
                                    Selanjutnya
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="user-footer">
        <div class="footer-content">
            <div class="footer-section">
                <img src="assets/images/logo/Resensiku.png" alt="Resensiku" class="footer-logo">
                <p class="footer-text">Platform review buku terbaik untuk pembaca Indonesia.</p>
            </div>
            <div class="footer-section">
                <h4>Tautan Cepat</h4>
                <a href="index.php">Beranda</a>
                <a href="books.php">Semua Buku</a>
                <a href="bookshelf.php">Bookshelf</a>
                <a href="about.php">Tentang Kami</a>
            </div>
            <div class="footer-section">
                <h4>Kontak</h4>
                <p>Email: info@resensiku.com</p>
                <p>Telepon: (021) 1234-5678</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 Resensiku. All rights reserved.</p>
        </div>
    </footer>

    <script src="assets/js/books.js"></script>
</body>
</html>