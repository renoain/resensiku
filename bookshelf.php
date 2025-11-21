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

// Get active tab from URL
$active_tab = $_GET['tab'] ?? 'all';

// Get user's bookshelf
$bookshelf_stmt = $db->prepare("
    SELECT b.*, bs.status, bs.created_at as added_date,
           AVG(r.rating) as avg_rating,
           COUNT(r.id) as review_count
    FROM bookshelf bs
    JOIN books b ON bs.book_id = b.id
    LEFT JOIN reviews r ON b.id = r.book_id
    WHERE bs.user_id = ?
    GROUP BY b.id, bs.status
    ORDER BY bs.created_at DESC
");
$bookshelf_stmt->execute([$_SESSION['user_id']]);
$bookshelf = $bookshelf_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group books by status
$books_by_status = [
    'want_to_read' => [],
    'reading' => [],
    'read' => []
];

foreach ($bookshelf as $book) {
    $books_by_status[$book['status']][] = $book;
}

// Get bookshelf statistics
$stats_stmt = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM bookshelf 
    WHERE user_id = ? 
    GROUP BY status
");
$stats_stmt->execute([$_SESSION['user_id']]);
$stats_data = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'want_to_read' => 0,
    'reading' => 0,
    'read' => 0,
    'total' => 0
];

foreach ($stats_data as $stat) {
    $stats[$stat['status']] = $stat['count'];
    $stats['total'] += $stat['count'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookshelf Saya - Resensiku</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/bookshelf.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <header style="text-align: center; margin-bottom: var(--space-xl);">
            <h1>📚 Bookshelf Saya</h1>
            <p>Kelola koleksi buku dan track progress membaca Anda</p>
        </header>

        <!-- Bookshelf Statistics -->
        <section class="bookshelf-stats">
            <div class="stat-card-bookshelf stat-want_to_read">
                <div class="stat-icon-bookshelf">
                    <i class="fas fa-bookmark"></i>
                </div>
                <span class="stat-number-bookshelf"><?php echo $stats['want_to_read']; ?></span>
                <span class="stat-label-bookshelf">Ingin Dibaca</span>
            </div>
            
            <div class="stat-card-bookshelf stat-reading">
                <div class="stat-icon-bookshelf">
                    <i class="fas fa-book-open"></i>
                </div>
                <span class="stat-number-bookshelf"><?php echo $stats['reading']; ?></span>
                <span class="stat-label-bookshelf">Sedang Dibaca</span>
            </div>
            
            <div class="stat-card-bookshelf stat-read">
                <div class="stat-icon-bookshelf">
                    <i class="fas fa-check-circle"></i>
                </div>
                <span class="stat-number-bookshelf"><?php echo $stats['read']; ?></span>
                <span class="stat-label-bookshelf">Selesai Dibaca</span>
            </div>
        </section>

        <!-- Bookshelf Tabs -->
        <section class="bookshelf-container">
            <div class="bookshelf-tabs">
                <button class="bookshelf-tab <?php echo $active_tab === 'all' ? 'active' : ''; ?>" data-status="all">
                    Semua Buku
                    <span class="tab-badge"><?php echo $stats['total']; ?></span>
                </button>
                <button class="bookshelf-tab <?php echo $active_tab === 'want_to_read' ? 'active' : ''; ?>" data-status="want_to_read">
                    Ingin Dibaca
                    <span class="tab-badge"><?php echo $stats['want_to_read']; ?></span>
                </button>
                <button class="bookshelf-tab <?php echo $active_tab === 'reading' ? 'active' : ''; ?>" data-status="reading">
                    Sedang Dibaca
                    <span class="tab-badge"><?php echo $stats['reading']; ?></span>
                </button>
                <button class="bookshelf-tab <?php echo $active_tab === 'read' ? 'active' : ''; ?>" data-status="read">
                    Selesai Dibaca
                    <span class="tab-badge"><?php echo $stats['read']; ?></span>
                </button>
            </div>

            <div class="bookshelf-content">
                <?php if (empty($bookshelf)): ?>
                    <div class="bookshelf-empty">
                        <i class="fas fa-books"></i>
                        <h3>Bookshelf Anda Masih Kosong</h3>
                        <p>Mulai dengan menambahkan buku pertama ke bookshelf Anda</p>
                        <a href="books.php" class="btn btn-primary" style="margin-top: var(--space-md);">
                            <i class="fas fa-plus"></i> Jelajahi Buku
                        </a>
                    </div>
                <?php else: ?>
                    <div class="bookshelf-grid">
                        <?php foreach ($bookshelf as $book): ?>
                            <?php if ($active_tab === 'all' || $book['status'] === $active_tab): ?>
                            <div class="bookshelf-card" data-status="<?php echo $book['status']; ?>">
                                <div class="bookshelf-card-cover">
                                    <img src="assets/images/books/<?php echo $book['cover_image'] ?? 'default-cover.png'; ?>" 
                                         alt="<?php echo htmlspecialchars($book['title']); ?>"
                                         onerror="this.src='assets/images/books/default-cover.png'">
                                    
                                    <span class="bookshelf-status status-<?php echo $book['status']; ?>">
                                        <?php 
                                        switch($book['status']) {
                                            case 'want_to_read': echo 'Ingin Dibaca'; break;
                                            case 'reading': echo 'Sedang Dibaca'; break;
                                            case 'read': echo 'Selesai Dibaca'; break;
                                        }
                                        ?>
                                    </span>
                                    
                                    <div class="bookshelf-actions">
                                        <div class="status-dropdown" data-book-id="<?php echo $book['id']; ?>">
                                            <button class="status-toggle" title="Ubah Status">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="status-menu">
                                                <button class="status-option option-want_to_read" data-status="want_to_read">
                                                    <i class="fas fa-bookmark"></i> Ingin Dibaca
                                                </button>
                                                <button class="status-option option-reading" data-status="reading">
                                                    <i class="fas fa-book-open"></i> Sedang Dibaca
                                                </button>
                                                <button class="status-option option-read" data-status="read">
                                                    <i class="fas fa-check-circle"></i> Selesai Dibaca
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bookshelf-card-info">
                                    <h3 class="bookshelf-card-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                                    <p class="bookshelf-card-author"><?php echo htmlspecialchars($book['author']); ?></p>
                                    
                                    <?php if ($book['avg_rating']): ?>
                                    <div class="bookshelf-card-rating">
                                        <span class="rating-stars">
                                            <?php 
                                            $rating = round($book['avg_rating']);
                                            echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                                            ?>
                                        </span>
                                        <span class="rating-value"><?php echo number_format($book['avg_rating'], 1); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <p class="book-added" style="font-size: 0.8rem; color: var(--text-medium);">
                                        Ditambahkan: <?php echo date('d M Y', strtotime($book['added_date'])); ?>
                                    </p>
                                    
                                    <div style="margin-top: var(--space-sm);">
                                        <a href="book-detail.php?id=<?php echo $book['id']; ?>" 
                                           class="btn btn-secondary btn-sm btn-full">
                                            <i class="fas fa-eye"></i> Lihat Detail
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script src="assets/js/bookshelf.js"></script>
</body>
</html>