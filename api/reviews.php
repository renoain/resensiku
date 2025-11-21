<?php
require_once '../config/constants.php';
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'submit':
        submitReview();
        break;
    case 'list':
        getReviews();
        break;
    case 'like':
        toggleLike();
        break;
    case 'submit_reply':
        submitReply();
        break;
    case 'get_replies':
        getReplies();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function submitReview() {
    global $db;
    
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    $book_id = $_POST['book_id'] ?? null;
    $rating = $_POST['rating'] ?? null;
    $review_text = trim($_POST['review_text'] ?? '');
    
    // Validation
    $errors = [];
    if (!$book_id) $errors[] = 'Book ID is required';
    if (!$rating) $errors[] = 'Rating is required';
    if (empty($review_text)) $errors[] = 'Review text cannot be empty';
    if ($rating < 1 || $rating > 5) $errors[] = 'Rating must be between 1 and 5';
    if (strlen($review_text) > 2000) $errors[] = 'Review text too long (max 2000 characters)';

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        return;
    }

    try {
        // Check if user already reviewed this book
        $existing_review = $db->prepare("SELECT id FROM reviews WHERE user_id = ? AND book_id = ?");
        $existing_review->execute([$user_id, $book_id]);
        
        if ($existing_review->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You have already reviewed this book']);
            return;
        }

        // Check if book exists
        $book_check = $db->prepare("SELECT id, title FROM books WHERE id = ?");
        $book_check->execute([$book_id]);
        $book = $book_check->fetch();
        
        if (!$book) {
            echo json_encode(['success' => false, 'message' => 'Book not found']);
            return;
        }

        // Insert review
        $stmt = $db->prepare("
            INSERT INTO reviews (user_id, book_id, rating, review_text, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([$user_id, $book_id, $rating, $review_text]);

        if ($result) {
            // Get the new review with user info
            $new_review_stmt = $db->prepare("
                SELECT r.*, u.first_name, u.last_name 
                FROM reviews r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.id = ?
            ");
            $new_review_stmt->execute([$db->lastInsertId()]);
            $new_review = $new_review_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update book average rating
            updateBookRating($book_id);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Review submitted successfully',
                'review' => $new_review
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit review']);
        }

    } catch (PDOException $e) {
        error_log("Review submission error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function getReviews() {
    global $db;
    
    $book_id = $_GET['book_id'] ?? null;
    $user_id = $_GET['user_id'] ?? null;
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    try {
        // Build query
        $query = "
            SELECT r.*, 
                   u.first_name, 
                   u.last_name,
                   (SELECT COUNT(*) FROM likes WHERE review_id = r.id) as like_count,
                   (SELECT COUNT(*) FROM likes WHERE review_id = r.id AND user_id = ?) as user_liked
            FROM reviews r 
            JOIN users u ON r.user_id = u.id 
        ";
        
        $params = [$_SESSION['user_id'] ?? 0];
        
        if ($book_id) {
            $query .= " WHERE r.book_id = ?";
            $params[] = $book_id;
        } elseif ($user_id) {
            $query .= " WHERE r.user_id = ?";
            $params[] = $user_id;
        }
        
        $query .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM reviews";
        $count_params = [];
        
        if ($book_id) {
            $count_query .= " WHERE book_id = ?";
            $count_params[] = $book_id;
        } elseif ($user_id) {
            $count_query .= " WHERE user_id = ?";
            $count_params[] = $user_id;
        }
        
        $count_stmt = $db->prepare($count_query);
        $count_stmt->execute($count_params);
        $total_reviews = $count_stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'reviews' => $reviews,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total_reviews,
                    'pages' => ceil($total_reviews / $limit)
                ]
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Reviews list error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function toggleLike() {
    global $db;
    
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    $review_id = $_POST['review_id'] ?? null;
    $action = $_POST['action'] ?? 'like'; // like or unlike

    if (!$review_id) {
        echo json_encode(['success' => false, 'message' => 'Review ID is required']);
        return;
    }

    try {
        if ($action === 'like') {
            // Check if already liked
            $existing_like = $db->prepare("SELECT id FROM likes WHERE user_id = ? AND review_id = ?");
            $existing_like->execute([$user_id, $review_id]);
            
            if (!$existing_like->fetch()) {
                // Insert like
                $stmt = $db->prepare("INSERT INTO likes (user_id, review_id, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$user_id, $review_id]);
            }
        } else {
            // Remove like
            $stmt = $db->prepare("DELETE FROM likes WHERE user_id = ? AND review_id = ?");
            $stmt->execute([$user_id, $review_id]);
        }

        // Get updated like count
        $like_count_stmt = $db->prepare("SELECT COUNT(*) as like_count FROM likes WHERE review_id = ?");
        $like_count_stmt->execute([$review_id]);
        $like_count = $like_count_stmt->fetch(PDO::FETCH_COLUMN);

        echo json_encode([
            'success' => true,
            'likes_count' => $like_count,
            'action' => $action
        ]);

    } catch (PDOException $e) {
        error_log("Like action error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function submitReply() {
    global $db;
    
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    $review_id = $_POST['review_id'] ?? null;
    $reply_content = trim($_POST['reply_content'] ?? '');
    
    if (!$review_id || empty($reply_content)) {
        echo json_encode(['success' => false, 'message' => 'Review ID and reply content are required']);
        return;
    }
    
    if (strlen($reply_content) > 500) {
        echo json_encode(['success' => false, 'message' => 'Reply content too long (max 500 characters)']);
        return;
    }
    
    try {
        // Check if review exists
        $review_check = $db->prepare("SELECT id FROM reviews WHERE id = ?");
        $review_check->execute([$review_id]);
        
        if (!$review_check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Review not found']);
            return;
        }
        
        // Insert reply
        $stmt = $db->prepare("
            INSERT INTO review_replies (review_id, user_id, reply_content, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([$review_id, $user_id, $reply_content]);
        
        if ($result) {
            // Get the new reply with user info
            $new_reply_stmt = $db->prepare("
                SELECT rr.*, u.first_name, u.last_name 
                FROM review_replies rr 
                JOIN users u ON rr.user_id = u.id 
                WHERE rr.id = ?
            ");
            $new_reply_stmt->execute([$db->lastInsertId()]);
            $new_reply = $new_reply_stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => 'Reply submitted successfully',
                'reply' => $new_reply
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit reply']);
        }
    } catch (PDOException $e) {
        error_log("Reply submission error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function getReplies() {
    global $db;
    
    $review_id = $_GET['review_id'] ?? null;
    
    if (!$review_id) {
        echo json_encode(['success' => false, 'message' => 'Review ID is required']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT rr.*, u.first_name, u.last_name 
            FROM review_replies rr 
            JOIN users u ON rr.user_id = u.id 
            WHERE rr.review_id = ? 
            ORDER BY rr.created_at ASC
        ");
        $stmt->execute([$review_id]);
        $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $replies
        ]);
    } catch (PDOException $e) {
        error_log("Replies get error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

// Helper function to update book rating
function updateBookRating($book_id) {
    global $db;
    
    $stmt = $db->prepare("
        UPDATE books 
        SET average_rating = (
            SELECT AVG(rating) FROM reviews WHERE book_id = ?
        )
        WHERE id = ?
    ");
    $stmt->execute([$book_id, $book_id]);
}
?>