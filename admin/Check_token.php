<?php
require_once '../includes/config.php';

// Simple API endpoint to check token validity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $token = $conn->real_escape_string($_POST['token']);
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    
    // Build the query
    $token_query = "SELECT at.*, TIMESTAMPDIFF(HOUR, NOW(), at.expires_at) as hours_left,
                   v.shop_name
                   FROM access_tokens at
                   JOIN vendors v ON at.vendor_id = v.id
                   WHERE at.token = ?";
    
    if ($order_id > 0) {
        $token_query .= " AND at.order_id = ?";
        $stmt = $conn->prepare($token_query);
        $stmt->bind_param('si', $token, $order_id);
    } else {
        $stmt = $conn->prepare($token_query);
        $stmt->bind_param('s', $token);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $token_data = $result->fetch_assoc();
        $hours_left = $token_data['hours_left'];
        $expires_at = date('d M Y H:i', strtotime($token_data['expires_at']));
        
        echo json_encode([
            'valid' => $hours_left > 0,
            'expires_in' => $hours_left,
            'expires_at' => $expires_at,
            'vendor' => $token_data['shop_name'],
            'order_id' => $token_data['order_id']
        ]);
    } else {
        echo json_encode([
            'valid' => false,
            'message' => 'Token tidak ditemukan atau sudah kedaluwarsa'
        ]);
    }
} else {
    echo json_encode([
        'valid' => false,
        'message' => 'Permintaan tidak valid'
    ]);
}