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
    case 'list':
        getBooksList();
        break;
    case 'detail':
        getBookDetail();
        break;
    case 'featured':
        getFeaturedBooks();
        break;
    case 'by_genre':
        getBooksByGenre();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getBooksList() {
    global $db;
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 12)));
    $offset = ($page - 1) * $limit;
    
    try {
        // Get total count
        $count_stmt = $db->query("SELECT COUNT(*) FROM books");
        $total_books = $count_stmt->fetchColumn();
        
        // Get books with ratings
        $stmt = $db->prepare("
            SELECT b.*, 
                   AVG(r.rating) as avg_rating,
                   COUNT(r.id) as review_count
            FROM books b 
            LEFT JOIN reviews r ON b.id = r.book_id 
            GROUP BY b.id 
            ORDER BY b.created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
        error_log("Books list error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function getBookDetail() {
    global $db;
    
    $book_id = $_GET['id'] ?? $_POST['id'] ?? null;
    
    if (!$book_id) {
        echo json_encode(['success' => false, 'message' => 'Book ID is required']);
        return;
    }
    
    try {
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
        
        if ($book) {
            echo json_encode([
                'success' => true,
                'data' => $book
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Book not found']);
        }
    } catch (PDOException $e) {
        error_log("Book detail error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function getFeaturedBooks() {
    global $db;
    
    $limit = min(10, max(1, intval($_GET['limit'] ?? 6)));
    
    try {
        $stmt = $db->prepare("
            SELECT b.*, 
                   AVG(r.rating) as avg_rating,
                   COUNT(r.id) as review_count
            FROM books b 
            LEFT JOIN reviews r ON b.id = r.book_id 
            GROUP BY b.id 
            ORDER BY avg_rating DESC, review_count DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $books
        ]);
    } catch (PDOException $e) {
        error_log("Featured books error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function getBooksByGenre() {
    global $db;
    
    $genre = $_GET['genre'] ?? $_POST['genre'] ?? '';
    
    if (empty($genre)) {
        echo json_encode(['success' => false, 'message' => 'Genre is required']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT b.*, 
                   AVG(r.rating) as avg_rating,
                   COUNT(r.id) as review_count
            FROM books b 
            LEFT JOIN reviews r ON b.id = r.book_id 
            WHERE FIND_IN_SET(?, b.genres) > 0
            GROUP BY b.id 
            ORDER BY b.title ASC
        ");
        $stmt->execute([$genre]);
        
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $books
        ]);
    } catch (PDOException $e) {
        error_log("Books by genre error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
?>