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

// Get book ID from URL
$book_id = $_GET['id'] ?? null;
if (!$book_id) {
    header("Location: books.php");
    exit();
}

// Get book details
$stmt = $db->prepare("
    SELECT b.*, 
           AVG(r.rating) as avg_rating,
           COUNT(r.id) as review_count
    FROM books b 
    LEFT JOIN reviews r ON b.id = r.book_id 
    WHERE b.id = ?
    GROUP BY b.id
");
$stmt->execute([$book_id]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    header("Location: books.php");
    exit();
}

// Get user's current bookshelf status for this book
$bookshelf_stmt = $db->prepare("
    SELECT status FROM bookshelf 
    WHERE user_id = ? AND book_id = ?
");
$bookshelf_stmt->execute([$_SESSION['user_id'], $book_id]);
$bookshelf_status = $bookshelf_stmt->fetch(PDO::FETCH_COLUMN);

// Get reviews for this book
$reviews_stmt = $db->prepare("
    SELECT r.*, 
           u.first_name, 
           u.last_name,
           (SELECT COUNT(*) FROM likes WHERE review_id = r.id) as like_count,
           (SELECT COUNT(*) FROM likes WHERE review_id = r.id AND user_id = ?) as user_liked
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.book_id = ? 
    ORDER BY r.created_at DESC
");
$reviews_stmt->execute([$_SESSION['user_id'], $book_id]);
$reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get replies for reviews
$reviews_with_replies = [];
foreach ($reviews as $review) {
    $reply_stmt = $db->prepare("
        SELECT rr.*, u.first_name, u.last_name
        FROM review_replies rr
        JOIN users u ON rr.user_id = u.id
        WHERE rr.review_id = ?
        ORDER BY rr.created_at ASC
    ");
    $reply_stmt->execute([$review['id']]);
    $review['replies'] = $reply_stmt->fetchAll(PDO::FETCH_ASSOC);
    $reviews_with_replies[] = $review;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Resensiku</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/reviews.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Book Hero Section -->
        <section class="book-hero">
            <div class="book-cover-container">
                <img src="assets/images/books/<?php echo $book['cover_image'] ?? 'default-cover.png'; ?>" 
                     alt="<?php echo htmlspecialchars($book['title']); ?>" 
                     class="book-cover-large"
                     onerror="this.src='assets/images/books/default-cover.png'">
            </div>
            
            <div class="book-info-detailed">
                <h1 class="book-title-large"><?php echo htmlspecialchars($book['title']); ?></h1>
                <p class="book-author-large">oleh <?php echo htmlspecialchars($book['author']); ?></p>
                
                <div class="book-meta-grid">
                    <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div>
                            <div class="meta-value">
                                <?php echo number_format($book['avg_rating'] ?? 0, 1); ?>/5
                            </div>
                            <div class="meta-label">Rating Rata-rata</div>
                        </div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-comment"></i>
                        </div>
                        <div>
                            <div class="meta-value"><?php echo $book['review_count']; ?></div>
                            <div class="meta-label">Total Review</div>
                        </div>
                    </div>
                    
                    <?php if ($book['publication_year']): ?>
                    <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div>
                            <div class="meta-value"><?php echo $book['publication_year']; ?></div>
                            <div class="meta-label">Tahun Terbit</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($book['page_count']): ?>
                    <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div>
                            <div class="meta-value"><?php echo $book['page_count']; ?></div>
                            <div class="meta-label">Halaman</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($book['genres']): ?>
                <div class="genre-tags">
                    <?php 
                    $genres = explode(',', $book['genres']);
                    foreach ($genres as $genre): 
                        if (!empty(trim($genre))):
                    ?>
                        <span class="genre-tag"><?php echo trim($genre); ?></span>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                <?php endif; ?>
                
                <!-- Bookshelf Status Dropdown -->
                <div class="bookshelf-action" style="margin-bottom: var(--space-lg);">
                    <label style="display: block; margin-bottom: var(--space-sm); font-weight: 500;">
                        Status Buku Saya:
                    </label>
                    <select id="bookshelfStatus" class="status-select" style="padding: var(--space-sm); border-radius: var(--radius-md); border: 2px solid var(--text-light);">
                        <option value="">Pilih Status</option>
                        <option value="want_to_read" <?php echo $bookshelf_status == 'want_to_read' ? 'selected' : ''; ?>>
                            📚 Ingin Dibaca
                        </option>
                        <option value="reading" <?php echo $bookshelf_status == 'reading' ? 'selected' : ''; ?>>
                            📖 Sedang Dibaca
                        </option>
                        <option value="read" <?php echo $bookshelf_status == 'read' ? 'selected' : ''; ?>>
                            ✅ Selesai Dibaca
                        </option>
                    </select>
                </div>
                
                <?php if ($book['synopsis']): ?>
                <div class="book-synopsis">
                    <h3 style="margin-bottom: var(--space-sm);">Sinopsis</h3>
                    <p><?php echo nl2br(htmlspecialchars($book['synopsis'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Review System -->
        <section class="review-system">
            <h2 style="margin-bottom: var(--space-lg);">Berikan Review Anda</h2>
            
            <!-- Review Form -->
            <form id="reviewForm" class="review-form">
                <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                
                <div class="rating-input">
                    <label style="display: block; margin-bottom: var(--space-sm); font-weight: 500;">
                        Rating Buku:
                    </label>
                    <input type="hidden" id="rating" name="rating" value="0">
                    
                    <div class="star-rating">
                        <span class="star" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                    </div>
                    
                    <div class="rating-labels">
                        <span>Tidak suka</span>
                        <span>Sangat suka</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="review_text" style="display: block; margin-bottom: var(--space-sm); font-weight: 500;">
                        Ulasan Anda:
                    </label>
                    <textarea id="review_text" name="review_text" class="review-textarea" 
                              placeholder="Bagikan pengalaman membaca Anda..."></textarea>
                    <div class="char-counter">0 karakter</div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane"></i> Kirim Review
                </button>
            </form>

            <!-- Reviews List -->
            <div class="reviews-list">
                <h3 style="margin-bottom: var(--space-lg);">
                    Review Komunitas (<?php echo count($reviews_with_replies); ?>)
                </h3>
                
                <?php if (empty($reviews_with_replies)): ?>
                    <div class="empty-state" style="text-align: center; padding: var(--space-xl); color: var(--text-medium);">
                        <i class="fas fa-comment-slash fa-3x" style="margin-bottom: var(--space-md);"></i>
                        <h3>Belum ada review</h3>
                        <p>Jadilah yang pertama memberikan review untuk buku ini!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews_with_replies as $review): ?>
                    <div class="review-item" data-review-id="<?php echo $review['id']; ?>">
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
                                <?php echo str_repeat('★', $review['rating']); ?>
                            </div>
                        </div>
                        
                        <div class="review-content">
                            <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                        </div>
                        
                        <div class="review-actions">
                            <button class="like-btn <?php echo $review['user_liked'] ? 'liked' : ''; ?>" 
                                    data-review-id="<?php echo $review['id']; ?>">
                                <i class="<?php echo $review['user_liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                <span class="like-count"><?php echo $review['like_count']; ?></span>
                            </button>
                            
                            <button class="reply-btn" data-review-id="<?php echo $review['id']; ?>">
                                <i class="fas fa-reply"></i> Balas
                            </button>
                        </div>

                        <!-- Reply Form -->
                        <div class="reply-form" id="reply-form-<?php echo $review['id']; ?>">
                            <textarea class="reply-textarea" placeholder="Tulis balasan Anda..."></textarea>
                            <div style="display: flex; gap: var(--space-sm); margin-top: var(--space-sm);">
                                <button type="button" class="btn btn-primary btn-sm submit-reply">
                                    <i class="fas fa-paper-plane"></i> Kirim Balasan
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm cancel-reply">
                                    Batal
                                </button>
                            </div>
                        </div>

                        <!-- Replies List -->
                        <?php if (!empty($review['replies'])): ?>
                        <div class="replies-list" id="replies-<?php echo $review['id']; ?>">
                            <?php foreach ($review['replies'] as $reply): ?>
                            <div class="reply-item">
                                <div class="reply-header">
                                    <span class="reply-author">
                                        <?php echo htmlspecialchars($reply['first_name'] . ' ' . $reply['last_name']); ?>
                                    </span>
                                    <span class="reply-date">
                                        <?php echo date('d M Y H:i', strtotime($reply['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="reply-content">
                                    <?php echo nl2br(htmlspecialchars($reply['reply_content'])); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script src="assets/js/reviews.js"></script>
    <script>
        // Bookshelf status change handler
        document.getElementById('bookshelfStatus').addEventListener('change', function() {
            const status = this.value;
            const bookId = <?php echo $book_id; ?>;
            
            if (status) {
                const formData = new FormData();
                formData.append('book_id', bookId);
                formData.append('status', status);
                
                fetch('api/bookshelf.php?action=update_status', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        // Show success message
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-success';
                        alert.style.cssText = 'margin-top: 10px;';
                        alert.textContent = 'Status buku berhasil diperbarui!';
                        this.parentNode.appendChild(alert);
                        
                        setTimeout(() => alert.remove(), 3000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        });
    </script>
</body>
</html><?php
require_once 'config/constants.php';
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get book ID from URL
$book_id = $_GET['id'] ?? null;
if (!$book_id) {
    header("Location: books.php");
    exit();
}

// Get book details
$stmt = $db->prepare("
    SELECT b.*, 
           AVG(r.rating) as avg_rating,
           COUNT(r.id) as review_count
    FROM books b 
    LEFT JOIN reviews r ON b.id = r.book_id 
    WHERE b.id = ?
    GROUP BY b.id
");
$stmt->execute([$book_id]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    header("Location: books.php");
    exit();
}

// Get user's current bookshelf status for this book
$bookshelf_stmt = $db->prepare("
    SELECT status FROM bookshelf 
    WHERE user_id = ? AND book_id = ?
");
$bookshelf_stmt->execute([$_SESSION['user_id'], $book_id]);
$bookshelf_status = $bookshelf_stmt->fetch(PDO::FETCH_COLUMN);

// Get reviews for this book
$reviews_stmt = $db->prepare("
    SELECT r.*, 
           u.first_name, 
           u.last_name,
           (SELECT COUNT(*) FROM likes WHERE review_id = r.id) as like_count,
           (SELECT COUNT(*) FROM likes WHERE review_id = r.id AND user_id = ?) as user_liked
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.book_id = ? 
    ORDER BY r.created_at DESC
");
$reviews_stmt->execute([$_SESSION['user_id'], $book_id]);
$reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get replies for reviews
$reviews_with_replies = [];
foreach ($reviews as $review) {
    $reply_stmt = $db->prepare("
        SELECT rr.*, u.first_name, u.last_name
        FROM review_replies rr
        JOIN users u ON rr.user_id = u.id
        WHERE rr.review_id = ?
        ORDER BY rr.created_at ASC
    ");
    $reply_stmt->execute([$review['id']]);
    $review['replies'] = $reply_stmt->fetchAll(PDO::FETCH_ASSOC);
    $reviews_with_replies[] = $review;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Resensiku</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/reviews.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Book Hero Section -->
        <section class="book-hero">
            <div class="book-cover-container">
                <img src="assets/images/books/<?php echo $book['cover_image'] ?? 'default-cover.png'; ?>" 
                     alt="<?php echo htmlspecialchars($book['title']); ?>" 
                     class="book-cover-large"
                     onerror="this.src='assets/images/books/default-cover.png'">
            </div>
            
            <div class="book-info-detailed">
                <h1 class="book-title-large"><?php echo htmlspecialchars($book['title']); ?></h1>
                <p class="book-author-large">oleh <?php echo htmlspecialchars($book['author']); ?></p>
                
                <div class="book-meta-grid">
                    <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div>
                            <div class="meta-value">
                                <?php echo number_format($book['avg_rating'] ?? 0, 1); ?>/5
                            </div>
                            <div class="meta-label">Rating Rata-rata</div>
                        </div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-comment"></i>
                        </div>
                        <div>
                            <div class="meta-value"><?php echo $book['review_count']; ?></div>
                            <div class="meta-label">Total Review</div>
                        </div>
                    </div>
                    
                    <?php if ($book['publication_year']): ?>
                    <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div>
                            <div class="meta-value"><?php echo $book['publication_year']; ?></div>
                            <div class="meta-label">Tahun Terbit</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($book['page_count']): ?>
                    <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div>
                            <div class="meta-value"><?php echo $book['page_count']; ?></div>
                            <div class="meta-label">Halaman</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($book['genres']): ?>
                <div class="genre-tags">
                    <?php 
                    $genres = explode(',', $book['genres']);
                    foreach ($genres as $genre): 
                        if (!empty(trim($genre))):
                    ?>
                        <span class="genre-tag"><?php echo trim($genre); ?></span>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                <?php endif; ?>
                
                <!-- Bookshelf Status Dropdown -->
                <div class="bookshelf-action" style="margin-bottom: var(--space-lg);">
                    <label style="display: block; margin-bottom: var(--space-sm); font-weight: 500;">
                        Status Buku Saya:
                    </label>
                    <select id="bookshelfStatus" class="status-select" style="padding: var(--space-sm); border-radius: var(--radius-md); border: 2px solid var(--text-light);">
                        <option value="">Pilih Status</option>
                        <option value="want_to_read" <?php echo $bookshelf_status == 'want_to_read' ? 'selected' : ''; ?>>
                            📚 Ingin Dibaca
                        </option>
                        <option value="reading" <?php echo $bookshelf_status == 'reading' ? 'selected' : ''; ?>>
                            📖 Sedang Dibaca
                        </option>
                        <option value="read" <?php echo $bookshelf_status == 'read' ? 'selected' : ''; ?>>
                            ✅ Selesai Dibaca
                        </option>
                    </select>
                </div>
                
                <?php if ($book['synopsis']): ?>
                <div class="book-synopsis">
                    <h3 style="margin-bottom: var(--space-sm);">Sinopsis</h3>
                    <p><?php echo nl2br(htmlspecialchars($book['synopsis'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Review System -->
        <section class="review-system">
            <h2 style="margin-bottom: var(--space-lg);">Berikan Review Anda</h2>
            
            <!-- Review Form -->
            <form id="reviewForm" class="review-form">
                <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                
                <div class="rating-input">
                    <label style="display: block; margin-bottom: var(--space-sm); font-weight: 500;">
                        Rating Buku:
                    </label>
                    <input type="hidden" id="rating" name="rating" value="0">
                    
                    <div class="star-rating">
                        <span class="star" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                    </div>
                    
                    <div class="rating-labels">
                        <span>Tidak suka</span>
                        <span>Sangat suka</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="review_text" style="display: block; margin-bottom: var(--space-sm); font-weight: 500;">
                        Ulasan Anda:
                    </label>
                    <textarea id="review_text" name="review_text" class="review-textarea" 
                              placeholder="Bagikan pengalaman membaca Anda..."></textarea>
                    <div class="char-counter">0 karakter</div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane"></i> Kirim Review
                </button>
            </form>

            <!-- Reviews List -->
            <div class="reviews-list">
                <h3 style="margin-bottom: var(--space-lg);">
                    Review Komunitas (<?php echo count($reviews_with_replies); ?>)
                </h3>
                
                <?php if (empty($reviews_with_replies)): ?>
                    <div class="empty-state" style="text-align: center; padding: var(--space-xl); color: var(--text-medium);">
                        <i class="fas fa-comment-slash fa-3x" style="margin-bottom: var(--space-md);"></i>
                        <h3>Belum ada review</h3>
                        <p>Jadilah yang pertama memberikan review untuk buku ini!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews_with_replies as $review): ?>
                    <div class="review-item" data-review-id="<?php echo $review['id']; ?>">
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
                                <?php echo str_repeat('★', $review['rating']); ?>
                            </div>
                        </div>
                        
                        <div class="review-content">
                            <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                        </div>
                        
                        <div class="review-actions">
                            <button class="like-btn <?php echo $review['user_liked'] ? 'liked' : ''; ?>" 
                                    data-review-id="<?php echo $review['id']; ?>">
                                <i class="<?php echo $review['user_liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                <span class="like-count"><?php echo $review['like_count']; ?></span>
                            </button>
                            
                            <button class="reply-btn" data-review-id="<?php echo $review['id']; ?>">
                                <i class="fas fa-reply"></i> Balas
                            </button>
                        </div>

                        <!-- Reply Form -->
                        <div class="reply-form" id="reply-form-<?php echo $review['id']; ?>">
                            <textarea class="reply-textarea" placeholder="Tulis balasan Anda..."></textarea>
                            <div style="display: flex; gap: var(--space-sm); margin-top: var(--space-sm);">
                                <button type="button" class="btn btn-primary btn-sm submit-reply">
                                    <i class="fas fa-paper-plane"></i> Kirim Balasan
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm cancel-reply">
                                    Batal
                                </button>
                            </div>
                        </div>

                        <!-- Replies List -->
                        <?php if (!empty($review['replies'])): ?>
                        <div class="replies-list" id="replies-<?php echo $review['id']; ?>">
                            <?php foreach ($review['replies'] as $reply): ?>
                            <div class="reply-item">
                                <div class="reply-header">
                                    <span class="reply-author">
                                        <?php echo htmlspecialchars($reply['first_name'] . ' ' . $reply['last_name']); ?>
                                    </span>
                                    <span class="reply-date">
                                        <?php echo date('d M Y H:i', strtotime($reply['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="reply-content">
                                    <?php echo nl2br(htmlspecialchars($reply['reply_content'])); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script src="assets/js/reviews.js"></script>
    <script>
        // Bookshelf status change handler
        document.getElementById('bookshelfStatus').addEventListener('change', function() {
            const status = this.value;
            const bookId = <?php echo $book_id; ?>;
            
            if (status) {
                const formData = new FormData();
                formData.append('book_id', bookId);
                formData.append('status', status);
                
                fetch('api/bookshelf.php?action=update_status', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        // Show success message
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-success';
                        alert.style.cssText = 'margin-top: 10px;';
                        alert.textContent = 'Status buku berhasil diperbarui!';
                        this.parentNode.appendChild(alert);
                        
                        setTimeout(() => alert.remove(), 3000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        });
    </script>
</body>
</html>