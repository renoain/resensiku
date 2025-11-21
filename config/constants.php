<?php
// config/constants.php

// Session must start at the very beginning
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Site Configuration
define('SITE_NAME', 'Resensiku');
define('SITE_URL', 'http://localhost/resensiku');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/resensiku/uploads/');

// User Roles
define('ROLE_USER', 'user');
define('ROLE_ADMIN', 'admin');

// Book Status
define('STATUS_WANT_TO_READ', 'want_to_read');
define('STATUS_READING', 'reading');
define('STATUS_READ', 'read');

// File upload settings
define('MAX_FILE_SIZE', 2097152); // 2MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif']);

// Debug mode
define('DEBUG', true);

// Error reporting
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} 