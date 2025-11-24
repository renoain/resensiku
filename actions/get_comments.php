<?php
require_once '../config/constants.php';
require_once '../config/database.php';

 if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$post_id = $_GET['post_id'] ?? null;

if (!$post_id) {
    echo json_encode(['success' => false, 'message' => 'Post ID required']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Get comments with user info
    $stmt = $db->prepare("
        SELECT pc.*, 
               CONCAT(u.first_name, ' ', u.last_name) as author_name,
               UPPER(SUBSTRING(u.first_name, 1, 1)) as author_initials
        FROM post_comments pc
        LEFT JOIN users u ON pc.user_id = u.id
        WHERE pc.post_id = ?
        ORDER BY pc.created_at ASC
    ");
    $stmt->execute([$post_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'comments' => $comments
    ]);
    
} catch (PDOException $e) {
    error_log("Get comments error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat memuat komentar'
    ]);
}