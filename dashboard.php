<?php
require_once 'config/config.php';

// Redirect ke login jika belum login
if (!isLoggedIn()) {
    redirect('login.php');
}

require_once 'includes/functions.php';
$page_title = "Dashboard - Resensiku";

// Ambil data trending books dengan error handling
$trending_books = [];
try {
    $query = "SELECT b.*, 
              COALESCE(AVG(r.rating), 0) as avg_rating,
              COUNT(r.id) as review_count
              FROM books b
              LEFT JOIN reviews r ON b.id = r.book_id
              GROUP BY b.id
              ORDER BY avg_rating DESC, review_count DESC
              LIMIT 6";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $trending_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching trending books: " . $e->getMessage());
    $trending_books = [];
}

// Ambil statistik bookshelf user dengan error handling
$bookshelf_stats = ['want_to_read' => 0, 'reading' => 0, 'read' => 0];
try {
    $bookshelf_stats = getUserBookshelfStats($_SESSION['user_id']);
} catch (Exception $e) {
    error_log("Error getting bookshelf stats: " . $e->getMessage());
}

// Ambil review terbaru user dengan error handling
$recent_reviews = [];
try {
    $query = "SELECT r.*, b.title, b.author, b.cover_image
              FROM reviews r
              JOIN books b ON r.book_id = b.id
              WHERE r.user_id = ?
              ORDER BY r.created_at DESC
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $recent_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching recent reviews: " . $e->getMessage());
    $recent_reviews = [];
}

include 'includes/header.php';
?>

<div class="dashboard-container">
    <div class="welcome-section">
        <h1>Selamat datang, <?php echo $_SESSION['user_name']; ?>! 👋</h1>
        <p>Apa yang ingin Anda baca hari ini?</p>
    </div>

    <!-- Bookshelf Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #4CAF50;">
                <i class="fas fa-bookmark"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $bookshelf_stats['want_to_read']; ?></h3>
                <p>Ingin Dibaca</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #2196F3;">
                <i class="fas fa-book-open"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $bookshelf_stats['reading']; ?></h3>
                <p>Sedang Dibaca</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #FF9800;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $bookshelf_stats['read']; ?></h3>
                <p>Selesai Dibaca</p>
            </div>
        </div>
    </div>

    <!-- Trending Books -->
    <section class="section">
        <div class="section-header">
            <h2>Buku Trending</h2>
            <a href="browse.php" class="view-all">Lihat Semua</a>
        </div>
        
        <?php if (empty($trending_books)): ?>
            <div class="empty-state">
                <i class="fas fa-book-open fa-3x"></i>
                <h3>Belum ada buku</h3>
                <p>Silakan tambahkan buku terlebih dahulu</p>
            </div>
        <?php else: ?>
            <div class="books-grid">
                <?php foreach ($trending_books as $book): ?>
                    <div class="book-card">
                        <div class="book-cover">
                            <img src="<?php echo BASE_URL; ?>assets/images/books/<?php echo $book['cover_image'] ?: 'default.jpg'; ?>" 
                                 alt="<?php echo $book['title']; ?>"
                                 onerror="this.src='<?php echo BASE_URL; ?>assets/images/books/default.jpg'">
                        </div>
                        <div class="book-info">
                            <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                            <p class="book-author"><?php echo htmlspecialchars($book['author']); ?></p>
                            <div class="book-rating">
                                <?php echo generateStarRating($book['avg_rating'] ?? 0); ?>
                                <span class="rating-text">
                                    (<?php echo number_format($book['avg_rating'] ?? 0, 1); ?>)
                                </span>
                            </div>
                            <div class="book-actions">
                                <a href="book.php?id=<?php echo $book['id']; ?>" class="btn btn-outline">Detail</a>
                                <button class="btn btn-primary" onclick="addToBookshelf(<?php echo $book['id']; ?>, 'want_to_read')">
                                    + Rak
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Recent Reviews -->
    <?php if (!empty($recent_reviews)): ?>
    <section class="section">
        <div class="section-header">
            <h2>Review Terbaru Anda</h2>
        </div>
        
        <div class="reviews-list">
            <?php foreach ($recent_reviews as $review): ?>
                <div class="review-card">
                    <div class="review-book">
                        <img src="<?php echo BASE_URL; ?>assets/images/books/<?php echo $review['cover_image'] ?: 'default.jpg'; ?>" 
                             alt="<?php echo $review['title']; ?>"
                             onerror="this.src='<?php echo BASE_URL; ?>assets/images/books/default.jpg'">
                    </div>
                    <div class="review-content">
                        <h4><?php echo htmlspecialchars($review['title']); ?></h4>
                        <div class="review-meta">
                            <?php echo generateStarRating($review['rating']); ?>
                            <span class="review-date">
                                <?php echo date('d M Y', strtotime($review['created_at'])); ?>
                            </span>
                        </div>
                        <p class="review-text">
                            <?php 
                            $review_text = $review['review_text'] ?? '';
                            echo strlen($review_text) > 150 ? 
                                substr($review_text, 0, 150) . '...' : 
                                $review_text; 
                            ?>
                        </p>
                        <a href="review.php?id=<?php echo $review['id']; ?>" class="read-more">Baca selengkapnya</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php 
$page_js = 'bookshelf.js';
include 'includes/footer.php'; 
?>