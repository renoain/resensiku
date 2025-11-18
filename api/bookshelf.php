<?php
include '../config/database.php';
include '../includes/Bookshelf.class.php';
include '../includes/Session.class.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$bookshelf = new Bookshelf($db);
$session = new Session();

if (!$session->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookshelf->user_id = $session->getUserId();
    $bookshelf->book_id = $_POST['book_id'];
    
    // Handle remove action
    if (isset($_POST['action']) && $_POST['action'] === 'remove') {
        if ($bookshelf->removeFromBookshelf($bookshelf->user_id, $bookshelf->book_id)) {
            echo json_encode(['success' => true, 'message' => 'Book removed from bookshelf']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove book']);
        }
    } 
    // Handle status update
    else {
        $bookshelf->status = $_POST['status'];
        
        if ($bookshelf->addToBookshelf()) {
            echo json_encode(['success' => true, 'message' => 'Book added to bookshelf']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add book']);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>