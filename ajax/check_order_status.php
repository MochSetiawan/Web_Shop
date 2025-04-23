<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Memastikan user sudah login
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Validasi parameter
if (!isset($_GET['order_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

$order_id = (int)$_GET['order_id'];
$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

// Force clear any db caching
$conn->query("SELECT 1");

// Query berbeda untuk vendor dan customer
if ($role === 'vendor') {
    // Dapatkan vendor_id
    $vendor_query = "SELECT id FROM vendors WHERE user_id = $user_id";
    $vendor_result = $conn->query($vendor_query);
    
    if ($vendor_result && $vendor_result->num_rows > 0) {
        $vendor_row = $vendor_result->fetch_assoc();
        $vendor_id = $vendor_row['id'];
        
        $query = "SELECT o.id, o.status, o.payment_status 
                 FROM orders o 
                 JOIN order_items oi ON o.id = oi.order_id
                 JOIN products p ON oi.product_id = p.id
                 WHERE o.id = $order_id AND p.vendor_id = $vendor_id
                 LIMIT 1";
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Vendor tidak ditemukan']);
        exit;
    }
} else {
    $query = "SELECT id, status, payment_status 
             FROM orders 
             WHERE id = $order_id AND user_id = $user_id";
}

$result = $conn->query($query);

if (!$result || $result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$order = $result->fetch_assoc();

// Return current status
header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'status' => $order['status'],
    'payment_status' => $order['payment_status'],
    'timestamp' => date('Y-m-d H:i:s')
]);
?>