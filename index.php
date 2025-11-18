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

// Get featured books
$featured_books = $db->query("
    SELECT b.*, AVG(r.rating) as avg_rating 
    FROM books b 
    LEFT JOIN reviews r ON b.id = r.book_id 
    GROUP BY b.id 
    ORDER BY avg_rating DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get user's bookshelf stats
$user_id = $_SESSION['user_id'];
$bookshelf_stats = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM bookshelf 
    WHERE user_id = ? 
    GROUP BY status
");
$bookshelf_stats->execute([$user_id]);
$stats = $bookshelf_stats->fetchAll(PDO::FETCH_ASSOC);

// Get recent reviews
$recent_reviews = $db->query("
    SELECT r.*, u.first_name, u.last_name, b.title, b.cover_image 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    JOIN books b ON r.book_id = b.id 
    ORDER BY r.created_at DESC 
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda - Resensiku</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <!-- Header akan ditambahkan nanti -->
    <div class="container">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-content">
                <h1>Selamat Datang di Resensiku</h1>
                <p>Bagikan pengalaman membaca Anda dengan komunitas pembaca Indonesia</p>
                <div class="hero-stats">
                    <?php foreach ($stats as $stat): ?>
                        <div class="stat-item">
                            <span class="stat-count"><?php echo $stat['count']; ?></span>
                            <span class="stat-label">
                                <?php 
                                switch($stat['status']) {
                                    case 'want_to_read': echo 'Ingin Dibaca'; break;
                                    case 'reading': echo 'Sedang Dibaca'; break;
                                    case 'read': echo 'Selesai Dibaca'; break;
                                }
                                ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Featured Books -->
        <section class="section">
            <h2>Buku Terpopuler</h2>
            <div class="books-grid">
                <?php foreach ($featured_books as $book): ?>
                    <div class="book-card">
                        <div class="book-cover">
                            <img src="assets/images/books/<?php echo $book['cover_image'] ?? 'default-cover.png'; ?>" 
                                 alt="<?php echo htmlspecialchars($book['title']); ?>">
                        </div>
                        <div class="book-info">
                            <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                            <p class="book-author"><?php echo htmlspecialchars($book['author']); ?></p>
                            <?php if ($book['avg_rating']): ?>
                                <div class="book-rating">
                                    <span class="rating-stars">★★★★★</span>
                                    <span class="rating-value"><?php echo number_format($book['avg_rating'], 1); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Recent Reviews -->
        <section class="section">
            <h2>Review Terbaru</h2>
            <div class="reviews-grid">
                <?php foreach ($recent_reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="reviewer-info">
                                <span class="reviewer-name">
                                    <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
                                </span>
                                <span class="review-date">
                                    <?php echo date('d M Y', strtotime($review['created_at'])); ?>
                                </span>
                            </div>
                            <div class="review-rating">
                                <?php echo str_repeat('★', $review['rating']); ?>
                            </div>
                        </div>
                        <h4 class="review-book-title"><?php echo htmlspecialchars($review['title']); ?></h4>
                        <p class="review-text"><?php echo htmlspecialchars(substr($review['review_text'], 0, 150)); ?>...</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <style>
        /* HOMEPAGE SPECIFIC STYLES */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--space-lg);
        }

        .hero-section {
            background: linear-gradient(135deg, var(--white) 0%, var(--bg-primary) 100%);
            padding: var(--space-xl);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-xl);
            text-align: center;
        }

        .hero-content h1 {
            font-size: 3rem;
            margin-bottom: var(--space-sm);
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: var(--space-xl);
            margin-top: var(--space-lg);
        }

        .stat-item {
            text-align: center;
        }

        .stat-count {
            display: block;
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--text-dark);
            font-family: var(--font-serif);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-medium);
        }

        .section {
            margin-bottom: var(--space-xl);
        }

        .section h2 {
            border-bottom: 2px solid var(--text-light);
            padding-bottom: var(--space-sm);
            margin-bottom: var(--space-lg);
        }

        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-lg);
        }

        .book-card {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            box-shadow: 0 4px 15px rgba(109, 97, 88, 0.1);
            transition: transform 0.3s ease;
        }

        .book-card:hover {
            transform: translateY(-5px);
        }

        .book-cover {
            width: 100%;
            height: 250px;
            margin-bottom: var(--space-sm);
            border-radius: var(--radius-sm);
            overflow: hidden;
        }

        .book-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .book-info h3 {
            font-size: 1.1rem;
            margin-bottom: var(--space-xs);
        }

        .book-author {
            font-size: 0.9rem;
            color: var(--text-medium);
            margin-bottom: var(--space-xs);
        }

        .book-rating {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .rating-stars {
            color: #FFD700;
        }

        .rating-value {
            font-size: 0.9rem;
            color: var(--text-medium);
        }

        .reviews-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--space-lg);
        }

        .review-card {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            box-shadow: 0 4px 15px rgba(109, 97, 88, 0.1);
        }

        .review-header {
            display: flex;
            justify-content: between;
            align-items: start;
            margin-bottom: var(--space-sm);
        }

        .reviewer-info {
            flex: 1;
        }

        .reviewer-name {
            display: block;
            font-weight: 500;
            color: var(--text-dark);
        }

        .review-date {
            font-size: 0.8rem;
            color: var(--text-medium);
        }

        .review-rating {
            color: #FFD700;
        }

        .review-book-title {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: var(--space-sm);
        }

        .review-text {
            color: var(--text-medium);
            line-height: 1.5;
        }

        @media (max-width: 768px) {
            .hero-stats {
                flex-direction: column;
                gap: var(--space-md);
            }
            
            .hero-content h1 {
                font-size: 2rem;
            }
            
            .books-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
    </style>
</body>
</html>