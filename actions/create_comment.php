<?php
require_once '../config/constants.php';
require_once '../config/database.php';

 if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$post_id = $input['post_id'] ?? null;
$content = trim($input['content'] ?? '');

if (!$post_id) {
    echo json_encode(['success' => false, 'message' => 'Post ID required']);
    exit();
}

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Komentar tidak boleh kosong']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Check if user can comment (is member of the community)
    $stmt = $db->prepare("
        SELECT 1 FROM community_members cm
        JOIN community_posts cp ON cm.community_id = cp.community_id
        WHERE cp.id = ? AND cm.user_id = ?
    ");
    $stmt->execute([$post_id, $_SESSION['user_id']]);

    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Anda tidak dapat berkomentar di post ini']);
        exit();
    }

    // Insert comment
    $stmt = $db->prepare("
        INSERT INTO post_comments (post_id, user_id, content) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$post_id, $_SESSION['user_id'], $content]);
    
    $comment_id = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Komentar berhasil ditambahkan',
        'comment_id' => $comment_id
    ]);
    
} catch (PDOException $e) {
    error_log("Comment creation error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat menambahkan komentar'
    ]);
}