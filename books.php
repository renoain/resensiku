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
$genres = isset($_GET['genres']) ? (array)$_GET['genres'] : [];
$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query based on filters
$whereConditions = [];
$params = [];

// Handle multiple genres filter
if (!empty($genres)) {
    $genreConditions = [];
    foreach ($genres as $selectedGenre) {
        $genreConditions[] = "b.genres LIKE ?";
        $params[] = "%" . trim($selectedGenre) . "%";
    }
    if (!empty($genreConditions)) {
        $whereConditions[] = "(" . implode(" OR ", $genreConditions) . ")";
    }
} 
// Handle single genre filter (for backward compatibility)
elseif (!empty($genre)) {
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
$booksQuery = "
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
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($booksQuery);
$stmt->execute($params);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all genres for filter
$genresStmt = $db->query("
    SELECT g.*, 
           (SELECT COUNT(*) FROM books WHERE genres LIKE CONCAT('%', g.name, '%')) as book_count
    FROM genres g 
    WHERE g.is_active = TRUE 
    ORDER BY g.name
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
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
</head>
<body>
<!-- Header -->
<header class="user-header">
    <div class="header-content">
        <!-- Logo - Pojok Kiri -->
        <div class="logo-section">
            <a href="index.php">
                <img src="assets/images/logo/Resensiku.png" alt="Resensiku" class="logo">
            </a>
        </div>

        <!-- Search Bar - Tengah -->
        <div class="search-section">
            <form class="search-form" method="GET" action="index.php">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" name="search" placeholder="Cari buku, penulis, atau genre..." value="<?php echo htmlspecialchars($search); ?>">
            </form>
        </div>

        <!-- Navigation Menu & User - Kanan -->
        <div class="right-section">
            <!-- Navigation Menu -->
            <nav class="main-nav">
                <a href="index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="books.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'books.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i>
                    <span>Genre</span>
                </a>
                <a href="bookshelf.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'bookshelf.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bookmark"></i>
                    <span>Bookshelf</span>
                </a>
                <a href="communities.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'communities.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Community</span>
                </a>
            </nav>

            <!-- User Navigation -->
            <div class="user-nav">
                <div class="user-dropdown">
                    <button class="user-trigger">
                        <div class="user-avatar"><?php echo $user_initials; ?></div>
                        <span><?php echo explode(' ', $user_name)[0]; ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="books.php" class="dropdown-item">
                            <i class="fas fa-tags"></i>
                            <span>Jelajahi Genre</span>
                        </a>
                        <a href="bookshelf.php" class="dropdown-item">
                            <i class="fas fa-bookmark"></i>
                            <span>Bookshelf Saya</span>
                        </a>
                        <a href="communities.php" class="dropdown-item">
                            <i class="fas fa-users"></i>
                             <span>Bookshelf Saya</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title-section">
                    <h1 class="page-title">
                        <?php if (!empty($genres)): ?>
                           <?php echo htmlspecialchars(implode(', ', $genres)); ?>
                        <?php elseif (!empty($genre)): ?>
                            <?php echo htmlspecialchars($genre); ?>
                        <?php elseif (!empty($search)): ?>
                             Hasil Pencarian: "<?php echo htmlspecialchars($search); ?>"
                        <?php else: ?>
                            Semua Buku
                        <?php endif; ?>
                    </h1>
                    <p class="page-subtitle">
                        <?php 
                        if (!empty($genres)) {
                            echo "Menampilkan buku dengan genre " . htmlspecialchars(implode(', ', $genres));
                        } elseif (!empty($genre)) {
                            echo "Menampilkan buku dengan genre " . htmlspecialchars($genre);
                        } elseif (!empty($search)) {
                            echo "Ditemukan $totalBooks buku untuk \"" . htmlspecialchars($search) . "\"";
                        } else {
                            echo "Jelajahi koleksi buku terbaik kami";
                        }
                        ?>
                    </p>
                    
                    <!-- Selected Genres Tags -->
                    <?php if (!empty($genres)): ?>
                    <div class="selected-genres-tags">
                        <strong>Genre terpilih:</strong>
                        <?php foreach ($genres as $selectedGenre): ?>
                            <span class="selected-genre-tag">
                                <?php echo htmlspecialchars($selectedGenre); ?>
                                <button type="button" class="remove-genre-btn" data-genre="<?php echo htmlspecialchars($selectedGenre); ?>">
                                    <i class="fas fa-times"></i>
                                </button>
                            </span>
                        <?php endforeach; ?>
                        <button type="button" class="clear-all-genres-btn">
                            <i class="fas fa-times-circle"></i>
                            Hapus Semua
                        </button>
                    </div>
                    <?php endif; ?>
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
                        
                        <!-- Multiple Genre Filter -->
                        <div class="filter-group">
                            <div class="filter-group-header">
                                <h4 class="filter-group-title">Pilih Genre</h4>
                                <small class="filter-subtitle">Pilih lebih dari satu genre</small>
                            </div>
                            <div class="genre-filters">
                                <?php foreach ($allGenres as $genreItem): ?>
                                    <label class="genre-filter-checkbox <?php echo in_array($genreItem['name'], $genres) ? 'active' : ''; ?>">
                                        <input type="checkbox" 
                                               name="genres[]" 
                                               value="<?php echo htmlspecialchars($genreItem['name']); ?>" 
                                               <?php echo in_array($genreItem['name'], $genres) ? 'checked' : ''; ?>
                                               class="genre-checkbox">
                                        <div class="genre-filter-icon">
                                            <i class="fas fa-<?php echo $genreItem['icon'] ?? 'book'; ?>"></i>
                                        </div>
                                        <div class="genre-filter-info">
                                            <span class="genre-name"><?php echo htmlspecialchars($genreItem['name']); ?></span>
                                            <span class="genre-count"><?php echo $genreItem['book_count']; ?> buku</span>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Apply Filters Button -->
                            <div class="filter-actions">
                                <button type="button" id="applyGenreFilter" class="btn-primary btn-full">
                                    <i class="fas fa-check"></i>
                                    Terapkan Filter
                                </button>
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
                            <?php if (!empty($genres)): ?>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo count($genres); ?></span>
                                <span class="stat-label">Genre Dipilih</span>
                            </div>
                            <?php endif; ?>
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
                                <?php if (!empty($genres)): ?>
                                    Tidak ada buku dengan genre "<?php echo htmlspecialchars(implode(', ', $genres)); ?>".
                                <?php elseif (!empty($genre)): ?>
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
                                            foreach (array_slice($book_genres, 0, 3) as $book_genre): 
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
                <p class="footer-text">Platform review buku  untuk pembaca.</p>
            </div>
    </footer>

    <script src="assets/js/books.js"></script>
</body>
</html>
