<?php
require_once '../config/constants.php';
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get statistics 
$stats = [
    'total_books' => $db->query("SELECT COUNT(*) FROM books")->fetchColumn(),
    'total_users' => $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn(),
    'total_reviews' => $db->query("SELECT COUNT(*) FROM reviews")->fetchColumn()
];

// Get recent activities
$recent_activities = $db->query("
    SELECT r.*, u.first_name, u.last_name, b.title, b.cover_image 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    JOIN books b ON r.book_id = b.id 
    ORDER BY r.created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get popular books 
$popular_books = $db->query("
    SELECT b.*, AVG(r.rating) as avg_rating, COUNT(r.id) as review_count
    FROM books b 
    LEFT JOIN reviews r ON b.id = r.book_id 
    GROUP BY b.id 
    ORDER BY review_count DESC, avg_rating DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Resensiku</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="css/admin-main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    
    <!-- Admin Header -->
    <div class="admin-container">
        <header class="admin-header">
            <div class="admin-header-content">
                <div class="admin-brand">
                    <img src="../assets/images/logo/Resensiku.png" alt="Resensiku" class="admin-logo">
                    <h1>Admin Dashboard</h1>
                </div>
                <div class="admin-nav">
                    <a href="books.php" class="btn btn-primary">
                        <i class="fas fa-book"></i> Kelola Buku
                    </a>
                    <a href="reviews.php" class="btn btn-primary">
                        <i class="fas fa-comment"></i> Pantau Review
                    </a>
                    <a href="../logout.php" class="btn btn-outline">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </header>

        <div class="admin-main">
            <section class="welcome-section">
                <div class="welcome-content">
                    <h2>Welcome, <?php echo $_SESSION['user_name']; ?>!</h2>
                    <p>Kelola platform Resensiku dengan mudah dari dashboard admin</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat-badge">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard Admin</span>
                    </div>
                </div>
            </section>

            <!-- Statistics Cards -->
            <section class="stats-section">
                <h2>Overview Platform</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <img src="../assets/images/logo/manga.png" alt="Books">
                        </div>
                        <div class="stat-info">
                            <span class="stat-number"><?php echo $stats['total_books']; ?></span>
                            <span class="stat-label">Total Buku</span>
                        </div>
                        <a href="books.php" class="stat-link">Lihat Semua →</a>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <img src="../assets/images/logo/akun.png" alt="Users">
                        </div>
                        <div class="stat-info">
                            <span class="stat-number"><?php echo $stats['total_users']; ?></span>
                            <span class="stat-label">Total User</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <img src="../assets/images/utility/comment.png" alt="Reviews">
                        </div>
                        <div class="stat-info">
                            <span class="stat-number"><?php echo $stats['total_reviews']; ?></span>
                            <span class="stat-label">Total Review</span>
                        </div>
                        <a href="reviews.php" class="stat-link">Monitor →</a>
                    </div>
                </div>
            </section>

            <!-- Recent Activities -->
            <div class="admin-content-grid">
                <section class="activities-section">
                    <div class="section-header">
                        <h2>Review Terbaru</h2>
                        <a href="reviews.php" class="view-all">Lihat Semua</a>
                    </div>
                    <div class="activities-list">
                        <?php if (empty($recent_activities)): ?>
                            <div class="empty-state" style="padding: var(--space-md);">
                                <i class="fas fa-comment-slash"></i>
                                <p>Belum ada review</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <img src="../assets/images/utility/comment.png" alt="Review">
                                    </div>
                                    <div class="activity-content">
                                        <p class="activity-text">
                                            <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                                            memberikan review pada <em>"<?php echo htmlspecialchars($activity['title']); ?>"</em>
                                        </p>
                                        <span class="activity-time">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('d M Y H:i', strtotime($activity['created_at'])); ?>
                                        </span>
                                    </div>
                                    <div class="activity-thumbnail">
                                        <img src="../assets/images/books/<?php echo $activity['cover_image']; ?>" 
                                             alt="Book Cover"
                                             onerror="this.src='../assets/images/books/default-cover.png'">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Popular Books-->
                <section class="popular-books-section">
                    <div class="section-header">
                        <h2>🔥Buku Populer</h2>
                        <a href="books.php" class="view-all">Lihat Semua</a>
                    </div>
                    <div class="popular-books-list">
                        <?php if (empty($popular_books)): ?>
                            <div class="empty-state" style="padding: var(--space-md);">
                                <i class="fas fa-book-open"></i>
                                <p>Belum ada buku</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($popular_books as $book): ?>
                                <div class="popular-book-item">
                                    <div class="book-cover">
                                        <img src="../assets/images/books/<?php echo $book['cover_image'] ?? 'default-cover.png'; ?>" 
                                             alt="<?php echo htmlspecialchars($book['title']); ?>"
                                             onerror="this.src='../assets/images/books/default-cover.png'">
                                    </div>
                                    <div class="book-details">
                                        <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                                        <p class="book-author"><?php echo htmlspecialchars($book['author']); ?></p>
                                        <div class="book-stats">
                                            <div class="reviews">
                                                <i class="fas fa-comment"></i>
                                                <span><?php echo $book['review_count']; ?> review</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <!-- Quick Actions -->
            <section class="quick-actions-section">
                <h2> Quick Actions</h2>
                <div class="actions-grid">
                    <a href="books.php?action=add" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-plus"></i>
                        </div>
                        <h3>Tambah Buku Baru</h3>
                        <p>Tambahkan buku baru ke katalog</p>
                    </a>
                    
                    <a href="books.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <h3>Kelola Buku</h3>
                        <p>Lihat dan edit semua buku</p>
                    </a>
                    
                    <a href="reviews.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-comment-dots"></i>
                        </div>
                        <h3>Pantau Review</h3>
                        <p>Lihat review dari pengguna</p>
                    </a>
                </div>
            </section>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>