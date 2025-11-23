<?php
require_once 'config/constants.php';
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get book ID from URL
$book_id = $_GET['id'] ?? null;
if (!$book_id) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get book details
$stmt = $db->prepare("
    SELECT b.*, 
           AVG(r.rating) as avg_rating,
           COUNT(r.id) as total_reviews
    FROM books b 
    LEFT JOIN reviews r ON b.id = r.book_id 
    WHERE b.id = ?
    GROUP BY b.id
");
$stmt->execute([$book_id]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    header("Location: index.php");
    exit();
}

// Get user's bookshelf status for this book
$stmt = $db->prepare("SELECT status FROM bookshelf WHERE user_id = ? AND book_id = ?");
$stmt->execute([$_SESSION['user_id'], $book_id]);
$bookshelf_status = $stmt->fetchColumn();

// Get user's review for this book
$stmt = $db->prepare("SELECT * FROM reviews WHERE user_id = ? AND book_id = ?");
$stmt->execute([$_SESSION['user_id'], $book_id]);
$user_review = $stmt->fetch(PDO::FETCH_ASSOC);

// Get other reviews - FIXED: Include all reviews from other users
$stmt = $db->prepare("
    SELECT r.*, u.first_name, u.last_name 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.book_id = ? AND r.user_id != ? 
    ORDER BY r.created_at DESC 
    LIMIT 10
");
$stmt->execute([$book_id, $_SESSION['user_id']]);
$other_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's name for dropdown
$user_name = $_SESSION['user_name'];
$user_initials = strtoupper(substr(explode(' ', $user_name)[0], 0, 1));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Resensiku</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/book-detail.css">
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
                        <a href="books.php" class="dropdown-item active">
                            <i class="fas fa-tags"></i>
                            <span>Jelajahi Genre</span>
                        </a>
                        <a href="bookshelf.php" class="dropdown-item">
                            <i class="fas fa-bookmark"></i>
                            <span>Bookshelf Saya</span>
                        </a>
                        <!-- <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            <span>Profil Saya</span>
                        </a> -->
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="book-detail-container">
            <!-- Back Button -->
            <div class="back-navigation">
                <a href="index.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    <span>Kembali ke Beranda</span>
                </a>
            </div>

            <!-- Book Header Section -->
            <div class="book-detail-header">
                <!-- Book Cover & Actions -->
                <div class="book-cover-section">
                    <img src="assets/images/books/<?php echo $book['cover_image'] ?? 'default-cover.png'; ?>" 
                         alt="<?php echo htmlspecialchars($book['title']); ?>" 
                         class="book-cover-large"
                         onerror="this.src='assets/images/books/default-cover.png'">
                    
                    <div class="book-actions">
                        <!-- Bookshelf Status Dropdown -->
                        <div class="status-dropdown">
                            <button class="status-button" onclick="toggleBookshelfDropdown()">
                                <i class="fas fa-bookmark"></i>
                                <?php 
                                $status_text = [
                                    'want_to_read' => 'Ingin Dibaca',
                                    'reading' => 'Sedang Dibaca',
                                    'read' => 'Sudah Dibaca'
                                ];
                                echo $bookshelf_status ? $status_text[$bookshelf_status] : 'Tambahkan ke Bookshelf';
                                ?>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="status-dropdown-content" id="bookshelfDropdown">
                                <form id="bookshelfForm">
                                    <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                                    <button type="button" name="status" value="want_to_read" class="status-option" onclick="updateBookshelf('want_to_read')">
                                        <span>Ingin Dibaca</span>
                                        <input type="radio" name="status_radio" value="want_to_read" <?php echo $bookshelf_status == 'want_to_read' ? 'checked' : ''; ?>>
                                    </button>
                                    <button type="button" name="status" value="reading" class="status-option" onclick="updateBookshelf('reading')">
                                        <span>Sedang Dibaca</span>
                                        <input type="radio" name="status_radio" value="reading" <?php echo $bookshelf_status == 'reading' ? 'checked' : ''; ?>>
                                    </button>
                                    <button type="button" name="status" value="read" class="status-option" onclick="updateBookshelf('read')">
                                        <span>Sudah Dibaca</span>
                                        <input type="radio" name="status_radio" value="read" <?php echo $bookshelf_status == 'read' ? 'checked' : ''; ?>>
                                    </button>
                                    <?php if ($bookshelf_status): ?>
                                    <div class="status-divider"></div>
                                    <button type="button" name="status" value="remove" class="status-option option-remove" onclick="updateBookshelf('remove')">
                                        <span>Hapus dari Bookshelf</span>
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>

                        <!-- Quick Rating  -->
                        <!-- <div class="quick-rating">
                            <div class="rating-title">Rating Saya:</div>
                            <div class="rating-stars-large" id="quickRating" data-current-rating="
                            <?php echo $user_review['rating'] ?? 0; ?>">
                                <?php 
                                $user_rating = $user_review['rating'] ?? 0;
                                for ($i = 1; $i <= 5; $i++): 
                                    $is_filled = $i <= $user_rating;
                                ?>
                                    <span class="star-icon-large <?php echo $is_filled ? 'filled' : ''; ?>" 
                                          data-rating="<?php echo $i; ?>">
                                        <i class="fas fa-star"></i>
                                    </span>
                                <?php endfor; ?>
                            </div>
                            <div class="rating-text">
                                <?php if ($user_rating > 0): ?>
                                    Anda memberi rating <?php echo $user_rating; ?> bintang
                                <?php else: ?>
                                    Klik bintang untuk memberi rating
                                <?php endif; ?>
                            </div>
                        </div> -->
                    </div>
                </div>

                <!-- Book Information -->
                <div class="book-info-section">
                    <h1 class="book-title-large"><?php echo htmlspecialchars($book['title']); ?></h1>
                    <p class="book-author-large">oleh <?php echo htmlspecialchars($book['author']); ?></p>

                    <!-- Book Metadata -->
                    <div class="book-meta-grid">
                        <div class="meta-item">
                            <div class="meta-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="meta-content">
                                <div class="meta-label">Rating Rata-rata</div>
                                <div class="meta-value">
                                    <span id="averageRating"><?php echo number_format($book['avg_rating'] ?? 0, 1); ?></span>
                                    (<span id="totalReviews"><?php echo $book['total_reviews']; ?></span> review)
                                </div>
                            </div>
                        </div>

                        <?php if ($book['publication_year']): ?>
                        <div class="meta-item">
                            <div class="meta-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="meta-content">
                                <div class="meta-label">Tahun Terbit</div>
                                <div class="meta-value"><?php echo $book['publication_year']; ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($book['page_count']): ?>
                        <div class="meta-item">
                            <div class="meta-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="meta-content">
                                <div class="meta-label">Jumlah Halaman</div>
                                <div class="meta-value"><?php echo $book['page_count']; ?> halaman</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                 <!-- Genres -->
                    <?php if ($book['genres']): ?>
                    <div class="genre-tags">
                        <?php 
                        $book_genres = explode(',', $book['genres']);
                        foreach ($book_genres as $genre): 
                            if (!empty(trim($genre))):
                        ?>
                            <a href="books.php?genre=<?php echo urlencode(trim($genre)); ?>" class="genre-tag-large">
                                <?php echo trim($genre); ?>
                            </a>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                    <?php endif; ?>

                    <!-- Synopsis -->
                    <?php if ($book['synopsis']): ?>
                    <div class="book-synopsis">
                        <h3 class="section-title">Sinopsis</h3>
                        <p><?php echo nl2br(htmlspecialchars($book['synopsis'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Review Section -->
            <section class="review-section">
                <h2 class="section-title">Review Buku</h2>

                <!-- Review Form (if user hasn't reviewed) -->
                <?php if (!$user_review): ?>
                <div class="review-form">
                    <form id="reviewForm">
                        <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                        
                        <div class="rating-input">
                            <div class="form-group">
                                <label>Rating Anda:</label>
                                <div class="star-rating" id="reviewRating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star" data-rating="<?php echo $i; ?>">
                                            <i class="far fa-star"></i>
                                        </span>
                                    <?php endfor; ?>
                                </div>
                                <div class="rating-labels">
                                    <span>Tidak suka</span>
                                    <span>Sangat suka</span>
                                </div>
                                <input type="hidden" name="rating" id="selectedRating" value="0">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reviewTextarea">Review Anda:</label>
                            <textarea 
                                name="review_text" 
                                class="review-textarea" 
                                placeholder="Bagikan pendapat Anda tentang buku ini..."
                                maxlength="1000"
                                id="reviewTextarea"
                            ></textarea>
                            <div class="char-counter" id="charCounter">0/1000 karakter</div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-cancel" id="cancelReview">Batal</button>
                            <button type="submit" class="btn-submit" id="submitReview" disabled>Kirim Review</button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <!-- User's Review Display -->
                <div class="user-review-section">
                    <div class="user-review-header">
                        <div class="user-avatar-small">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <div class="user-review-info">
                            <h4>Review Anda</h4>
                            <div class="user-review-date">
                                <?php echo date('d M Y', strtotime($user_review['created_at'])); ?>
                            </div>
                        </div>
                    </div>

                    <div class="user-rating-display">
                        <div class="stars">
                            <?php
                            $user_rating = $user_review['rating'];
                            for ($i = 1; $i <= 5; $i++):
                                if ($i <= $user_rating):
                            ?>
                                <i class="fas fa-star"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; endfor; ?>
                        </div>
                        <span><?php echo number_format($user_rating, 1); ?> bintang</span>
                    </div>

                    <div class="user-review-content">
                        <?php echo nl2br(htmlspecialchars($user_review['review_text'])); ?>
                    </div>

                    <div class="user-review-actions">
                        <button class="edit-review-btn" id="editReviewBtn">
                            <i class="fas fa-edit"></i>
                            <span>Edit Review</span>
                        </button>
                        <button class="delete-review-btn" id="deleteReviewBtn">
                            <i class="fas fa-trash"></i>
                            <span>Hapus Review</span>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Other Reviews -->
                <?php if (!empty($other_reviews)): ?>
                <div class="other-reviews-section">
                    <h3 class="section-title">Review dari Pembaca Lain (<?php echo count($other_reviews); ?>)</h3>
                    <div class="reviews-list">
                        <?php foreach ($other_reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <div class="reviewer-info">
                                    <div class="reviewer-avatar">
                                        <?php echo strtoupper(substr($review['first_name'], 0, 1)); ?>
                                    </div>
                                    <div class="reviewer-details">
                                        <h4><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></h4>
                                        <div class="review-date">
                                            <?php echo date('d M Y', strtotime($review['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="review-rating">
                                    <?php
                                    for ($i = 1; $i <= 5; $i++):
                                        if ($i <= $review['rating']):
                                    ?>
                                        <i class="fas fa-star"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; endfor; ?>
                                </div>
                            </div>
                            <div class="review-content">
                                <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
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

    <script src="assets/js/book-detail.js"></script>
</body>
</html>
