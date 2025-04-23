<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Initialize valid tokens array if needed
if (!isset($_SESSION['valid_tokens']) || !is_array($_SESSION['valid_tokens'])) {
    $_SESSION['valid_tokens'] = [];
}

// Get parameters
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$token = isset($_GET['token']) ? $_GET['token'] : '';
$return_page = isset($_GET['return']) ? $_GET['return'] : 'dashboard.php';

// Validate token
if ($order_id > 0 && !empty($token)) {
    // Check if token is valid in database
    $token_query = "SELECT at.order_id, at.expires_at, v.shop_name, at.vendor_id, at.token
                  FROM access_tokens at
                  JOIN vendors v ON at.vendor_id = v.id
                  WHERE at.token = ? AND at.order_id = ? AND at.expires_at > NOW()";
    
    $stmt = $conn->prepare($token_query);
    $stmt->bind_param('si', $token, $order_id);
    $stmt->execute();
    $token_result = $stmt->get_result();
    
    if ($token_result && $token_result->num_rows > 0) {
        $token_data = $token_result->fetch_assoc();
        $shop_name = $token_data['shop_name'];
        $vendor_id = $token_data['vendor_id'];
        $expires_at = $token_data['expires_at'];
        
        // Add token to session
        $_SESSION['valid_tokens'][$order_id] = [
            'token' => $token,
            'vendor' => $shop_name,
            'expires_at' => $expires_at,
            'vendor_id' => $vendor_id
        ];
        
        $_SESSION['success_message'] = "Token untuk Order #$order_id dari vendor $shop_name telah ditambahkan ke session Anda.";
    } else {
        $_SESSION['error_message'] = "Token tidak valid atau sudah kedaluwarsa.";
    }
} else {
    $_SESSION['error_message'] = "Parameter tidak valid.";
}

// Redirect back
header("Location: " . ADMIN_URL . "/" . $return_page);
exit;