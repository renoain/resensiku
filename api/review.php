<?php
require_once '../config/constants.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Anda harus login terlebih dahulu']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get POST data
$book_id = $_POST['book_id'] ?? null;
$rating = $_POST['rating'] ?? null;
$review_text = $_POST['review_text'] ?? '';
$action = $_POST['action'] ?? 'review';
$user_id = $_SESSION['user_id'];

if (!$book_id) {
    echo json_encode(['success' => false, 'message' => 'Book ID tidak valid']);
    exit();
}

try {
    // Check if book exists
    $stmt = $db->prepare("SELECT id FROM books WHERE id = ?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch();
    
    if (!$book) {
        echo json_encode(['success' => false, 'message' => 'Buku tidak ditemukan']);
        exit();
    }
    
    if ($action === 'delete_review') {
        // Delete user's review
        $stmt = $db->prepare("DELETE FROM reviews WHERE user_id = ? AND book_id = ?");
        $stmt->execute([$user_id, $book_id]);
        
        if ($stmt->rowCount() > 0) {
            updateBookStats($db, $book_id);
            echo json_encode(['success' => true, 'message' => 'Review berhasil dihapus']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Review tidak ditemukan']);
        }
        exit();
    }
    
    if ($action === 'quick_rating') {
        // Quick rating without review text
        if (!$rating || $rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'message' => 'Rating harus antara 1-5']);
            exit();
        }
        
        // Check if user already reviewed this book
        $stmt = $db->prepare("SELECT id, review_text FROM reviews WHERE user_id = ? AND book_id = ?");
        $stmt->execute([$user_id, $book_id]);
        $existing_review = $stmt->fetch();
        
        if ($existing_review) {
            // Update existing review - keep existing review text if any
            $update_text = $existing_review['review_text'] ? ", review_text = ?" : "";
            $params = [$rating, $user_id, $book_id];
            if ($existing_review['review_text']) {
                $params[] = $existing_review['review_text'];
            }
            
            $stmt = $db->prepare("UPDATE reviews SET rating = ?, updated_at = NOW() $update_text WHERE user_id = ? AND book_id = ?");
            $stmt->execute($params);
            echo json_encode(['success' => true, 'message' => 'Rating berhasil diupdate']);
        } else {
            // Create new review with only rating
            $stmt = $db->prepare("INSERT INTO reviews (user_id, book_id, rating) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $book_id, $rating]);
            echo json_encode(['success' => true, 'message' => 'Rating berhasil disimpan']);
        }
        
    } else {
        // Full review with text
        if (!$rating || $rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'message' => 'Rating harus antara 1-5']);
            exit();
        }
        
        if (empty(trim($review_text))) {
            echo json_encode(['success' => false, 'message' => 'Review text tidak boleh kosong']);
            exit();
        }
        
        // Validate review text length
        if (strlen($review_text) > 1000) {
            echo json_encode(['success' => false, 'message' => 'Review text maksimal 1000 karakter']);
            exit();
        }
        
        // Check if user already reviewed this book
        $stmt = $db->prepare("SELECT id FROM reviews WHERE user_id = ? AND book_id = ?");
        $stmt->execute([$user_id, $book_id]);
        $existing_review = $stmt->fetch();
        
        if ($existing_review) {
            // Update existing review
            $stmt = $db->prepare("UPDATE reviews SET rating = ?, review_text = ?, updated_at = NOW() WHERE user_id = ? AND book_id = ?");
            $stmt->execute([$rating, $review_text, $user_id, $book_id]);
            echo json_encode(['success' => true, 'message' => 'Review berhasil diupdate']);
        } else {
            // Create new review
            $stmt = $db->prepare("INSERT INTO reviews (user_id, book_id, rating, review_text) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $book_id, $rating, $review_text]);
            echo json_encode(['success' => true, 'message' => 'Review berhasil disimpan']);
        }
    }
    
    // Update book's average rating and review count
    updateBookStats($db, $book_id);
    
} catch (PDOException $e) {
    error_log("Review error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem']);
}

function updateBookStats($db, $book_id) {
    // Calculate new average rating and review count
    $stmt = $db->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
        FROM reviews 
        WHERE book_id = ?
    ");
    $stmt->execute([$book_id]);
    $stats = $stmt->fetch();
    
    // Update book record
    $stmt = $db->prepare("
        UPDATE books 
        SET average_rating = ?, total_reviews = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([
        $stats['avg_rating'] ?? 0,
        $stats['review_count'] ?? 0,
        $book_id
    ]);
}