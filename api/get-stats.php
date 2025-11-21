<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

try {
    // Get bookshelf statistics
    $stats_stmt = $db->prepare("
        SELECT status, COUNT(*) as count 
        FROM bookshelf 
        WHERE user_id = ? 
        GROUP BY status
    ");
    $stats_stmt->execute([$user_id]);
    $stats_data = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'want_to_read' => 0,
        'reading' => 0,
        'read' => 0,
        'total' => 0
    ];

    foreach ($stats_data as $stat) {
        $stats[$stat['status']] = $stat['count'];
        $stats['total'] += $stat['count'];
    }

    // Get reading progress for books in progress
    $progress_stmt = $db->prepare("
        SELECT b.id, b.title, bp.current_page, b.page_count as total_pages
        FROM bookshelf bs
        JOIN books b ON bs.book_id = b.id
        LEFT JOIN book_progress bp ON bs.book_id = bp.book_id AND bp.user_id = ?
        WHERE bs.user_id = ? AND bs.status = 'reading'
    ");
    $progress_stmt->execute([$user_id, $user_id]);
    $progress = $progress_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'progress' => $progress
        ]
    ]);

} catch (PDOException $e) {
    error_log("Bookshelf stats error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>