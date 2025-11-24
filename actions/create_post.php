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

$database = new Database();
$db = $database->getConnection();

// Get form data
$community_id = $_POST['community_id'] ?? null;
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');

// Validate
if (!$community_id) {
    echo json_encode(['success' => false, 'message' => 'Community ID required']);
    exit();
}

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Konten post harus diisi']);
    exit();
}

// Check if user is member of the community
$stmt = $db->prepare("
    SELECT 1 FROM community_members 
    WHERE community_id = ? AND user_id = ?
");
$stmt->execute([$community_id, $_SESSION['user_id']]);

if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Anda bukan anggota community ini']);
    exit();
}

// Handle file upload
$image = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../assets/images/posts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_type = $_FILES['image']['type'];
    
    if (!in_array($file_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Format file tidak didukung. Gunakan JPG, PNG, atau GIF.']);
        exit();
    }
    
    // Validate file size (max 2MB)
    if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 2MB.']);
        exit();
    }
    
    $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $file_extension;
    $target_path = $upload_dir . $filename;
    
    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
        $image = $filename;
    }
}

try {
    // Insert post
    $stmt = $db->prepare("
        INSERT INTO community_posts (community_id, user_id, title, content, image) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$community_id, $_SESSION['user_id'], $title, $content, $image]);
    
    $post_id = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Post berhasil dibuat',
        'post_id' => $post_id
    ]);
    
} catch (PDOException $e) {
    error_log("Post creation error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat membuat post'
    ]);
}