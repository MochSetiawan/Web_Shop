<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'shopverse');

// Site configuration
define('SITE_NAME', 'KrakenV2');
define('SITE_URL', 'http://localhost/shopverse');
define('ADMIN_URL', SITE_URL . '/admin');
define('VENDOR_URL', SITE_URL . '/vendor');
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/shopverse/assets/img/');
define('PRODUCT_IMG_DIR', UPLOAD_DIR . 'products/');
define('DEFAULT_IMG', 'default.jpg');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Set character set
$conn->set_charset('utf8mb4');

// Define user roles
define('ROLE_CUSTOMER', 'customer');
define('ROLE_VENDOR', 'vendor');
define('ROLE_ADMIN', 'admin');

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('Asia/Jakarta');
?>