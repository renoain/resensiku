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
    // Check if user is the creator
    $stmt = $db->prepare("SELECT created_by FROM communities WHERE id = ?");
    $stmt->execute([$community_id]);
    $community = $stmt->fetch();
    
    if ($community && $community['created_by'] == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Creator tidak bisa keluar dari community. Hapus community jika ingin keluar.']);
        exit();
    }
    
    // Leave community
    $stmt = $db->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
    $stmt->execute([$community_id, $_SESSION['user_id']]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Berhasil keluar dari community'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Anda bukan anggota community ini'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Leave community error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat keluar'
    ]);
}