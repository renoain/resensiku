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

$message = '';

// Handle delete review action
if (isset($_GET['delete'])) {
    $review_id = $_GET['delete'];
    
    $query = "DELETE FROM reviews WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$review_id])) {
        $message = '<div class="alert alert-success">Review berhasil dihapus!</div>';
    } else {
        $message = '<div class="alert alert-error">Gagal menghapus review</div>';
    }
}

// Get all reviews with user and book info
$reviews = $db->query("
    SELECT r.*, 
           u.first_name, 
           u.last_name, 
           u.email,
           b.title as book_title,
           b.author as book_author,
           b.cover_image
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    JOIN books b ON r.book_id = b.id 
    ORDER BY r.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pantau Review - Admin Resensiku</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="css/admin-main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<!-- Admin Header -->
<body>
    <div class="admin-container">
        <header class="admin-header">
            <div class="admin-header-content">
                <div class="admin-brand">
                    <img src="../assets/images/logo/Resensiku.png" alt="Resensiku" class="admin-logo">
                    <h1>Pantau Review</h1>
                </div>
                <div class="admin-nav">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                    </a>
                    <a href="books.php" class="btn btn-primary">
                        <i class="fas fa-book"></i> Kelola Buku
                    </a>
                    <a href="../logout.php" class="btn btn-outline">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </header>

        <div class="admin-main">
            <?php echo $message; ?>

            <section class="reviews-section">
                <div class="section-header">
                    <h2>Semua Review (<?php echo count($reviews); ?>)</h2>
                    <div class="header-actions">
                        <span class="total-reviews">Total: <?php echo count($reviews); ?> review</span>
                    </div>
                </div>

                <?php if (empty($reviews)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-slash fa-3x"></i>
                        <h3>Belum ada review</h3>
                        <p>Review dari pengguna akan muncul di sini</p>
                    </div>
                <?php else: ?>
                    <div class="reviews-table">
                        <?php foreach ($reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <div class="reviewer-info">
                                    <div class="reviewer-avatar">
                                        <?php echo strtoupper(substr($review['first_name'], 0, 1)); ?>
                                    </div>
                                    <div class="reviewer-details">
                                        <h4><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></h4>
                                        <span class="review-date">
                                            <?php echo date('d M Y H:i', strtotime($review['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="review-rating">
                                    <?php echo str_repeat('⭐', $review['rating']); ?>
                                    <span style="color: var(--text-medium); font-size: 0.9rem; margin-left: 5px;">
                                        (<?php echo $review['rating']; ?>/5)
                                    </span>
                                </div>
                            </div>
                            
                            <div class="review-book">
                                <strong>Buku: </strong>
                                <a href="../books.php?id=<?php echo $review['book_id']; ?>" target="_blank">
                                    "<?php echo htmlspecialchars($review['book_title']); ?>" oleh <?php echo htmlspecialchars($review['book_author']); ?>
                                </a>
                            </div>
                            
                            <div class="review-content">
                                <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                            </div>
                            
                            <div class="review-actions">
                                <a href="../books.php?id=<?php echo $review['book_id']; ?>" 
                                   class="btn btn-secondary btn-sm" target="_blank">
                                    <i class="fas fa-external-link-alt"></i> Lihat Buku
                                </a>
                                <a href="reviews.php?delete=<?php echo $review['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Yakin ingin menghapus review ini?')">
                                    <i class="fas fa-trash"></i> Hapus
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>