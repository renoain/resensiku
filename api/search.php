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
    case 'books':
        searchBooks();
        break;
    case 'suggestions':
        getSearchSuggestions();
        break;
    case 'advanced':
        advancedSearch();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function searchBooks() {
    global $db;
    
    $query = trim($_GET['q'] ?? $_POST['q'] ?? '');
    $genre = $_GET['genre'] ?? $_POST['genre'] ?? '';
    $min_rating = floatval($_GET['min_rating'] ?? $_POST['min_rating'] ?? 0);
    $year_from = intval($_GET['year_from'] ?? $_POST['year_from'] ?? 0);
    $year_to = intval($_GET['year_to'] ?? $_POST['year_to'] ?? 0);
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 12)));
    $offset = ($page - 1) * $limit;
    
    if (empty($query) && empty($genre) && $min_rating == 0 && $year_from == 0 && $year_to == 0) {
        echo json_encode(['success' => false, 'message' => 'Please provide search criteria']);
        return;
    }
    
    try {
        // Build search query
        $sql = "
            SELECT b.*, 
                   AVG(r.rating) as avg_rating,
                   COUNT(r.id) as review_count
            FROM books b 
            LEFT JOIN reviews r ON b.id = r.book_id 
            WHERE 1=1
        ";
        
        $params = [];
        
        // Search query
        if (!empty($query)) {
            $sql .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.synopsis LIKE ?)";
            $search_term = "%$query%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // Genre filter
        if (!empty($genre)) {
            $sql .= " AND FIND_IN_SET(?, b.genres) > 0";
            $params[] = $genre;
        }
        
        // Rating filter
        if ($min_rating > 0) {
            $sql .= " AND (SELECT AVG(rating) FROM reviews WHERE book_id = b.id) >= ?";
            $params[] = $min_rating;
        }
        
        // Year range filter
        if ($year_from > 0) {
            $sql .= " AND b.publication_year >= ?";
            $params[] = $year_from;
        }
        
        if ($year_to > 0) {
            $sql .= " AND b.publication_year <= ?";
            $params[] = $year_to;
        }
        
        $sql .= " GROUP BY b.id ORDER BY b.title ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $count_sql = "SELECT COUNT(DISTINCT b.id) FROM books b WHERE 1=1";
        $count_params = [];
        
        if (!empty($query)) {
            $count_sql .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.synopsis LIKE ?)";
            $search_term = "%$query%";
            $count_params[] = $search_term;
            $count_params[] = $search_term;
            $count_params[] = $search_term;
        }
        
        if (!empty($genre)) {
            $count_sql .= " AND FIND_IN_SET(?, b.genres) > 0";
            $count_params[] = $genre;
        }
        
        if ($min_rating > 0) {
            $count_sql .= " AND (SELECT AVG(rating) FROM reviews WHERE book_id = b.id) >= ?";
            $count_params[] = $min_rating;
        }
        
        if ($year_from > 0) {
            $count_sql .= " AND b.publication_year >= ?";
            $count_params[] = $year_from;
        }
        
        if ($year_to > 0) {
            $count_sql .= " AND b.publication_year <= ?";
            $count_params[] = $year_to;
        }
        
        $count_stmt = $db->prepare($count_sql);
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
                ],
                'filters' => [
                    'query' => $query,
                    'genre' => $genre,
                    'min_rating' => $min_rating,
                    'year_from' => $year_from,
                    'year_to' => $year_to
                ]
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Search error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function getSearchSuggestions() {
    global $db;
    
    $query = trim($_GET['q'] ?? $_POST['q'] ?? '');
    
    if (empty($query) || strlen($query) < 2) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }
    
    try {
        $search_term = "%$query%";
        
        // Get book title suggestions
        $title_stmt = $db->prepare("
            SELECT title, 'book' as type 
            FROM books 
            WHERE title LIKE ? 
            ORDER BY title ASC 
            LIMIT 5
        ");
        $title_stmt->execute([$search_term]);
        $title_suggestions = $title_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get author suggestions
        $author_stmt = $db->prepare("
            SELECT DISTINCT author, 'author' as type 
            FROM books 
            WHERE author LIKE ? 
            ORDER BY author ASC 
            LIMIT 5
        ");
        $author_stmt->execute([$search_term]);
        $author_suggestions = $author_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get genre suggestions
        $genre_stmt = $db->prepare("
            SELECT DISTINCT name as genre, 'genre' as type 
            FROM genres 
            WHERE name LIKE ? AND is_active = 1 
            ORDER BY name ASC 
            LIMIT 5
        ");
        $genre_stmt->execute([$search_term]);
        $genre_suggestions = $genre_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $suggestions = array_merge($title_suggestions, $author_suggestions, $genre_suggestions);
        
        echo json_encode([
            'success' => true,
            'data' => $suggestions
        ]);
    } catch (PDOException $e) {
        error_log("Search suggestions error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function advancedSearch() {
    global $db;
    
    // This is similar to searchBooks but with more advanced filters
    // You can extend this based on your specific needs
    
    searchBooks(); // For now, use the same function
}
?>