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
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$is_public = isset($_POST['is_public']) ? 1 : 0;

// Validate
if (empty($name) || empty($description)) {
    echo json_encode(['success' => false, 'message' => 'Nama dan deskripsi community harus diisi']);
    exit();
}

if (strlen($name) > 255) {
    echo json_encode(['success' => false, 'message' => 'Nama community terlalu panjang']);
    exit();
}

// Handle file upload
$cover_image = null;
if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../assets/images/communities/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_type = $_FILES['cover_image']['type'];
    
    if (!in_array($file_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Format file tidak didukung. Gunakan JPG, PNG, atau GIF.']);
        exit();
    }
    
    // Validate file size (max 2MB)
    if ($_FILES['cover_image']['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 2MB.']);
        exit();
    }
    
    $file_extension = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $file_extension;
    $target_path = $upload_dir . $filename;
    
    if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $target_path)) {
        $cover_image = $filename;
    }
}

try {
    // Insert community
    $stmt = $db->prepare("
        INSERT INTO communities (name, description, cover_image, created_by, is_public) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $description, $cover_image, $_SESSION['user_id'], $is_public]);
    
    $community_id = $db->lastInsertId();
    
    // Add creator as admin member
    $stmt = $db->prepare("
        INSERT INTO community_members (community_id, user_id, role) 
        VALUES (?, ?, 'admin')
    ");
    $stmt->execute([$community_id, $_SESSION['user_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Community berhasil dibuat',
        'community_id' => $community_id
    ]);
    
} catch (PDOException $e) {
    error_log("Community creation error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat membuat community'
    ]);
}