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
$community_id = $input['community_id'] ?? null;

if (!$community_id) {
    echo json_encode(['success' => false, 'message' => 'Community ID required']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Check if community exists and is public
    $stmt = $db->prepare("SELECT is_public FROM communities WHERE id = ?");
    $stmt->execute([$community_id]);
    $community = $stmt->fetch();
    
    if (!$community) {
        echo json_encode(['success' => false, 'message' => 'Community tidak ditemukan']);
        exit();
    }
    
    if (!$community['is_public']) {
        echo json_encode(['success' => false, 'message' => 'Community ini bersifat private']);
        exit();
    }
    
    // Check if already member
    $stmt = $db->prepare("SELECT id FROM community_members WHERE community_id = ? AND user_id = ?");
    $stmt->execute([$community_id, $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Anda sudah menjadi anggota']);
        exit();
    }
    
    // Join community
    $stmt = $db->prepare("INSERT INTO community_members (community_id, user_id) VALUES (?, ?)");
    $stmt->execute([$community_id, $_SESSION['user_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Berhasil bergabung dengan community'
    ]);
    
} catch (PDOException $e) {
    error_log("Join community error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat bergabung'
    ]);
}