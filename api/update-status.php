<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$book_id = $_POST['book_id'] ?? null;
$status = $_POST['status'] ?? null;

// Validation
if (!$book_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

if (!in_array($status, ['want_to_read', 'reading', 'read'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // Check if book exists
    $book_check = $db->prepare("SELECT id FROM books WHERE id = ?");
    $book_check->execute([$book_id]);
    
    if (!$book_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Book not found']);
        exit();
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
        $stats_stmt = $db->prepare("
            SELECT status, COUNT(*) as count 
            FROM bookshelf 
            WHERE user_id = ? 
            GROUP BY status
        ");
        $stats_stmt->execute([$user_id]);
        $stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
?>