<?php
require_once 'config/constants.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get bookshelf status from URL
$status = $_GET['status'] ?? 'all';
$valid_statuses = ['all', 'want_to_read', 'reading', 'read'];
if (!in_array($status, $valid_statuses)) {
    $status = 'all';
}

// Build query based on status
if ($status === 'all') {
    $stmt = $db->prepare("
        SELECT b.*, bs.status, bs.updated_at as shelf_updated,
               AVG(r.rating) as avg_rating,
               COUNT(r.id) as review_count,
               (SELECT rating FROM reviews WHERE user_id = ? AND book_id = b.id) as user_rating
        FROM bookshelf bs
        JOIN books b ON bs.book_id = b.id
        LEFT JOIN reviews r ON b.id = r.book_id
        WHERE bs.user_id = ?
        GROUP BY b.id
        ORDER BY bs.updated_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
} else {
    $stmt = $db->prepare("
        SELECT b.*, bs.status, bs.updated_at as shelf_updated,
               AVG(r.rating) as avg_rating,
               COUNT(r.id) as review_count,
               (SELECT rating FROM reviews WHERE user_id = ? AND book_id = b.id) as user_rating
        FROM bookshelf bs
        JOIN books b ON bs.book_id = b.id
        LEFT JOIN reviews r ON b.id = r.book_id
        WHERE bs.user_id = ? AND bs.status = ?
        GROUP BY b.id
        ORDER BY bs.updated_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $status]);
}

$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get bookshelf statistics
$stats_stmt = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM bookshelf 
    WHERE user_id = ? 
    GROUP BY status
");
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

$stats_count = [
    'want_to_read' => 0,
    'reading' => 0,
    'read' => 0,
    'total' => 0
];

foreach ($stats as $stat) {
    $stats_count[$stat['status']] = $stat['count'];
    $stats_count['total'] += $stat['count'];
}

// Get user's name for dropdown
$user_name = $_SESSION['user_name'];
$user_initials = strtoupper(substr(explode(' ', $user_name)[0], 0, 1));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookshelf Saya - Resensiku</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/bookshelf.css">
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
                        <a href="bookshelf.php" class="dropdown-item active">
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
                    <h1 class="page-title">Bookshelf</h1>
                    <p class="page-subtitle">Kelola koleksi buku pribadi Anda</p>
                </div>
                <div class="page-actions">
                    <a href="index.php" class="btn-secondary">
                        <i class="fas fa-plus"></i>
                        Tambah Buku Baru
                    </a>
                </div>
            </div>

            <!-- Bookshelf Stats -->
            <div class="bookshelf-stats">
                <div class="stat-card-bookshelf stat-all">
                    <div class="stat-icon-bookshelf">
                        <i class="fas fa-book"></i>
                    </div>
                    <span class="stat-number-bookshelf"><?php echo $stats_count['total']; ?></span>
                    <span class="stat-label-bookshelf">Total Buku</span>
                </div>
                <div class="stat-card-bookshelf stat-want_to_read">
                    <div class="stat-icon-bookshelf">
                        <i class="fas fa-bookmark"></i>
                    </div>
                    <span class="stat-number-bookshelf"><?php echo $stats_count['want_to_read']; ?></span>
                    <span class="stat-label-bookshelf">Ingin Dibaca</span>
                </div>
                <div class="stat-card-bookshelf stat-reading">
                    <div class="stat-icon-bookshelf">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <span class="stat-number-bookshelf"><?php echo $stats_count['reading']; ?></span>
                    <span class="stat-label-bookshelf">Sedang Dibaca</span>
                </div>
                <div class="stat-card-bookshelf stat-read">
                    <div class="stat-icon-bookshelf">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <span class="stat-number-bookshelf"><?php echo $stats_count['read']; ?></span>
                    <span class="stat-label-bookshelf">Sudah Dibaca</span>
                </div>
            </div>

            <!-- Bookshelf Navigation -->
            <div class="bookshelf-navigation">
                <div class="bookshelf-tabs">
                    <a href="bookshelf.php" class="bookshelf-tab <?php echo $status === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-th-list"></i>
                        <span>Semua Buku</span>
                        <span class="tab-badge"><?php echo $stats_count['total']; ?></span>
                    </a>
                    <a href="bookshelf.php?status=want_to_read" class="bookshelf-tab <?php echo $status === 'want_to_read' ? 'active' : ''; ?>">
                        <i class="fas fa-bookmark"></i>
                        <span>Ingin Dibaca</span>
                        <span class="tab-badge"><?php echo $stats_count['want_to_read']; ?></span>
                    </a>
                    <a href="bookshelf.php?status=reading" class="bookshelf-tab <?php echo $status === 'reading' ? 'active' : ''; ?>">
                        <i class="fas fa-book-open"></i>
                        <span>Sedang Dibaca</span>
                        <span class="tab-badge"><?php echo $stats_count['reading']; ?></span>
                    </a>
                    <a href="bookshelf.php?status=read" class="bookshelf-tab <?php echo $status === 'read' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle"></i>
                        <span>Sudah Dibaca</span>
                        <span class="tab-badge"><?php echo $stats_count['read']; ?></span>
                    </a>
                </div>
                
                <div class="bookshelf-actions">
                    <div class="sort-dropdown">
                        <button class="sort-button">
                            <i class="fas fa-sort"></i>
                            Urutkan
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bookshelf Content -->
            <div class="bookshelf-content">
                <?php if (empty($books)): ?>
                    <div class="bookshelf-empty">
                        <div class="empty-icon">
                            <i class="fas fa-books"></i>
                        </div>
                        <h3>Bookshelf Masih Kosong</h3>
                        <p>Belum ada buku di bookshelf <?php 
                            $status_text = [
                                'all' => 'Anda',
                                'want_to_read' => 'Ingin Dibaca',
                                'reading' => 'Sedang Dibaca', 
                                'read' => 'Sudah Dibaca'
                            ];
                            echo $status_text[$status];
                        ?>.</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Jelajahi Buku
                        </a>
                    </div>
                <?php else: ?>
                    <div class="bookshelf-grid">
                        <?php foreach ($books as $book): ?>
                        <div class="bookshelf-card">
                            <div class="book-cover-container">
                                <img src="assets/images/books/<?php echo $book['cover_image'] ?? 'default-cover.png'; ?>" 
                                     alt="<?php echo htmlspecialchars($book['title']); ?>"
                                     class="book-cover"
                                     onerror="this.src='assets/images/books/default-cover.png'"
                                     onclick="window.location.href='book-detail.php?id=<?php echo $book['id']; ?>'">
                                
                                <!-- Status Badge -->
                                <div class="status-badge status-<?php echo $book['status']; ?>">
                                    <i class="fas fa-<?php 
                                        $status_icons = [
                                            'want_to_read' => 'bookmark',
                                            'reading' => 'book-open',
                                            'read' => 'check-circle'
                                        ];
                                        echo $status_icons[$book['status']];
                                    ?>"></i>
                                    <?php 
                                    $status_text = [
                                        'want_to_read' => 'Ingin Dibaca',
                                        'reading' => 'Sedang Dibaca',
                                        'read' => 'Sudah Dibaca'
                                    ];
                                    echo $status_text[$book['status']];
                                    ?>
                                </div>

                                <!-- User Rating Badge -->
                                <?php if ($book['user_rating']): ?>
                                <div class="rating-badge">
                                    <i class="fas fa-star"></i>
                                    <?php echo $book['user_rating']; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="book-info">
                                <h3 class="book-title" onclick="window.location.href='book-detail.php?id=<?php echo $book['id']; ?>'">
                                    <?php echo htmlspecialchars($book['title']); ?>
                                </h3>
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
                                        <span class="rating-value"><?php echo number_format($book['avg_rating'] ?? 0, 1); ?></span>
                                        <span class="review-count">(<?php echo $book['review_count']; ?>)</span>
                                    </div>
                                </div>

                                <!-- Quick Status Buttons -->
                                <div class="quick-status-buttons">
                                    <button class="status-btn <?php echo $book['status'] === 'want_to_read' ? 'active' : ''; ?>" 
                                            onclick="updateBookStatus(<?php echo $book['id']; ?>, 'want_to_read')"
                                            title="Ingin Dibaca">
                                        <i class="fas fa-bookmark"></i>
                                    </button>
                                    <button class="status-btn <?php echo $book['status'] === 'reading' ? 'active' : ''; ?>" 
                                            onclick="updateBookStatus(<?php echo $book['id']; ?>, 'reading')"
                                            title="Sedang Dibaca">
                                        <i class="fas fa-book-open"></i>
                                    </button>
                                    <button class="status-btn <?php echo $book['status'] === 'read' ? 'active' : ''; ?>" 
                                            onclick="updateBookStatus(<?php echo $book['id']; ?>, 'read')"
                                            title="Sudah Dibaca">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                    <button class="status-btn remove-btn" 
                                            onclick="updateBookStatus(<?php echo $book['id']; ?>, 'remove')"
                                            title="Hapus dari Bookshelf">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>

                                <div class="book-footer">
                                    <span class="shelf-date">
                                        <i class="far fa-clock"></i>
                                        Diupdate: <?php echo date('d M Y', strtotime($book['shelf_updated'])); ?>
                                    </span>
                                    <a href="book-detail.php?id=<?php echo $book['id']; ?>" class="detail-link">
                                        Lihat Detail
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
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

    <script src="assets/js/bookshelf.js"></script>
</body>
</html>