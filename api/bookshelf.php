<?php
require_once '../config/constants.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Anda harus login terlebih dahulu']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get POST data
$book_id = $_POST['book_id'] ?? null;
$status = $_POST['status'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$book_id) {
    echo json_encode(['success' => false, 'message' => 'Book ID tidak valid']);
    exit();
}

try {
    // Check if book exists
    $stmt = $db->prepare("SELECT id FROM books WHERE id = ?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch();
    
    if (!$book) {
        echo json_encode(['success' => false, 'message' => 'Buku tidak ditemukan']);
        exit();
    }
    
    // Check if book is already in bookshelf
    $stmt = $db->prepare("SELECT id FROM bookshelf WHERE user_id = ? AND book_id = ?");
    $stmt->execute([$user_id, $book_id]);
    $existing = $stmt->fetch();
    
    if ($status === 'remove') {
        // Remove from bookshelf
        if ($existing) {
            $stmt = $db->prepare("DELETE FROM bookshelf WHERE user_id = ? AND book_id = ?");
            $stmt->execute([$user_id, $book_id]);
            echo json_encode(['success' => true, 'message' => 'Buku berhasil dihapus dari bookshelf']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Buku tidak ada di bookshelf']);
        }
    } else {
        // Validate status
        $valid_statuses = ['want_to_read', 'reading', 'read'];
        if (!in_array($status, $valid_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
            exit();
        }
        
        if ($existing) {
            // Update existing bookshelf entry
            $stmt = $db->prepare("UPDATE bookshelf SET status = ?, updated_at = NOW() WHERE user_id = ? AND book_id = ?");
            $stmt->execute([$status, $user_id, $book_id]);
            echo json_encode(['success' => true, 'message' => 'Status bookshelf berhasil diupdate']);
        } else {
            // Add new bookshelf entry
            $stmt = $db->prepare("INSERT INTO bookshelf (user_id, book_id, status) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $book_id, $status]);
            echo json_encode(['success' => true, 'message' => 'Buku berhasil ditambahkan ke bookshelf']);
        }
    }
    
} catch (PDOException $e) {
    error_log("Bookshelf error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem']);
}