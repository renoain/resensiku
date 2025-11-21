<?php
require_once 'config/constants.php';
require_once 'config/database.php';

// Handle logout via API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logout successful']);
    exit();
}

// Traditional logout
session_destroy();
header("Location: login.php");
exit();
?>