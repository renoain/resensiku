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

// Check authentication for all bookshelf actions
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'update_status':
        updateBookStatus();
        break;
    case 'get_stats':
        getBookshelfStats();
        break;
    case 'get_books':
        getBookshelfBooks();
        break;
    case 'update_progress':
        updateReadingProgress();
        break;
    case 'get_progress':
        getReadingProgress();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function updateBookStatus() {
    global $db, $user_id;
    
    $book_id = $_POST['book_id'] ?? null;
    $status = $_POST['status'] ?? null;
    
    // Validation
    if (!$book_id || !$status) {
        echo json_encode(['success' => false, 'message' => 'Book ID and status are required']);
        return;
    }
    
    if (!in_array($status, ['want_to_read', 'reading', 'read'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    try {
        // Check if book exists
        $book_check = $db->prepare("SELECT id FROM books WHERE id = ?");
        $book_check->execute([$book_id]);
        
        if (!$book_check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Book not found']);
            return;
        }

        // Check if already in bookshelf
        $existing = $db->prepare("SELECT id FROM bookshelf WHERE user_id = ? AND book_id = ?");
        $existing->execute([$user_id, $book_id]);
        $existing_record = $existing->fetch();

        if ($existing_record) {
            // Update existing record
            $stmt = $db->prepare("UPDATE bookshelf SET status = ?, updated_at = NOW() WHERE user_id = ? AND book_id = ?");
            $result = $stmt->execute([$status, $user_id, $book_id]);
        } else {
            // Insert new record
            $stmt = $db->prepare("INSERT INTO bookshelf (user_id, book_id, status, created_at) VALUES (?, ?, ?, NOW())");
            $result = $stmt->execute([$user_id, $book_id, $status]);
        }

        if ($result) {
            // Get updated stats
            $stats = getCurrentStats($user_id);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Bookshelf updated successfully',
                'stats' => $stats
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update bookshelf']);
        }

    } catch (PDOException $e) {
        error_log("Bookshelf update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function getBookshelfStats() {
    global $db, $user_id;
    
    try {
        $stats = getCurrentStats($user_id);
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
    } catch (PDOException $e) {
        error_log("Bookshelf stats error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function getBookshelfBooks() {
    global $db, $user_id;
    
    $status = $_GET['status'] ?? 'all';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 12)));
    $offset = ($page - 1) * $limit;
    
    try {
        // Build query based on status
        $query = "
            SELECT b.*, bs.status, bs.created_at as added_date,
                   AVG(r.rating) as avg_rating,
                   COUNT(r.id) as review_count
            FROM bookshelf bs
            JOIN books b ON bs.book_id = b.id
            LEFT JOIN reviews r ON b.id = r.book_id
            WHERE bs.user_id = ?
        ";
        
        $params = [$user_id];
        
        if ($status !== 'all') {
            $query .= " AND bs.status = ?";
            $params[] = $status;
        }
        
        $query .= " GROUP BY b.id, bs.status ORDER BY bs.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM bookshelf WHERE user_id = ?";
        $count_params = [$user_id];
        
        if ($status !== 'all') {
            $count_query .= " AND status = ?";
            $count_params[] = $status;
        }
        
        $count_stmt = $db->prepare($count_query);
        $count_stmt->execute($count_params);
        $total_books = $count_stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'books' => $books,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total_books,
                    'pages' => ceil($total_books / $limit)
                ]
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Bookshelf books error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function updateReadingProgress() {
    global $db, $user_id;
    
    $book_id = $_POST['book_id'] ?? null;
    $current_page = intval($_POST['current_page'] ?? 0);
    $total_pages = intval($_POST['total_pages'] ?? 0);
    
    if (!$book_id || $current_page < 0 || $total_pages < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid progress data']);
        return;
    }
    
    try {
        // Check if progress record exists
        $existing = $db->prepare("SELECT id FROM book_progress WHERE user_id = ? AND book_id = ?");
        $existing->execute([$user_id, $book_id]);
        
        if ($existing->fetch()) {
            // Update existing
            $stmt = $db->prepare("UPDATE book_progress SET current_page = ?, total_pages = ?, updated_at = NOW() WHERE user_id = ? AND book_id = ?");
            $result = $stmt->execute([$current_page, $total_pages, $user_id, $book_id]);
        } else {
            // Insert new
            $stmt = $db->prepare("INSERT INTO book_progress (user_id, book_id, current_page, total_pages, created_at) VALUES (?, ?, ?, ?, NOW())");
            $result = $stmt->execute([$user_id, $book_id, $current_page, $total_pages]);
        }
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Progress updated successfully',
                'progress' => [
                    'current_page' => $current_page,
                    'total_pages' => $total_pages,
                    'percentage' => $total_pages > 0 ? round(($current_page / $total_pages) * 100) : 0
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update progress']);
        }
    } catch (PDOException $e) {
        error_log("Progress update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function getReadingProgress() {
    global $db, $user_id;
    
    $book_id = $_GET['book_id'] ?? null;
    
    try {
        if ($book_id) {
            // Get progress for specific book
            $stmt = $db->prepare("SELECT * FROM book_progress WHERE user_id = ? AND book_id = ?");
            $stmt->execute([$user_id, $book_id]);
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Get all reading progress
            $stmt = $db->prepare("
                SELECT bp.*, b.title, b.author 
                FROM book_progress bp
                JOIN books b ON bp.book_id = b.id
                WHERE bp.user_id = ?
            ");
            $stmt->execute([$user_id]);
            $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $progress
        ]);
    } catch (PDOException $e) {
        error_log("Progress get error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

// Helper function to get current stats
function getCurrentStats($user_id) {
    global $db;
    
    $stats_stmt = $db->prepare("SELECT status, COUNT(*) as count FROM bookshelf WHERE user_id = ? GROUP BY status");
    $stats_stmt->execute([$user_id]);
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
    
    return $stats;
}
?>