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

if (!$post_id) {
    echo json_encode(['success' => false, 'message' => 'Post ID required']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Check if post exists and user has permission to delete
    $stmt = $db->prepare("
        SELECT p.*, 
               cm.role as user_role,
               cm.community_id
        FROM community_posts p
        LEFT JOIN community_members cm ON p.community_id = cm.community_id AND cm.user_id = ?
        WHERE p.id = ? AND p.is_deleted = FALSE
    ");
    $stmt->execute([$_SESSION['user_id'], $post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post tidak ditemukan atau sudah dihapus']);
        exit();
    }

    // Check if user can delete the post
    $canDelete = false;
    $userRole = $post['user_role'];
    
    if ($post['user_id'] == $_SESSION['user_id']) {
        $canDelete = true; // Author can delete their own post
    } elseif (in_array($userRole, ['admin', 'moderator'])) {
        $canDelete = true; // Admin/Moderator can delete any post in their community
    }

    if (!$canDelete) {
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk menghapus post ini']);
        exit();
    }

    // Soft delete the post (set is_deleted = TRUE)
    $stmt = $db->prepare("UPDATE community_posts SET is_deleted = TRUE WHERE id = ?");
    $stmt->execute([$post_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Post berhasil dihapus'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menghapus post'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Delete post error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat menghapus post'
    ]);
}