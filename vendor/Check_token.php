<?php
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $token = $conn->real_escape_string($_POST['token']);
    
    // Check if token exists and is valid
    $token_query = "SELECT *, TIMESTAMPDIFF(HOUR, NOW(), expires_at) as hours_left 
                   FROM access_tokens 
                   WHERE token = ?";
    $stmt = $conn->prepare($token_query);
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $token_data = $result->fetch_assoc();
        $hours_left = $token_data['hours_left'];
        $expires_at = date('d M Y H:i', strtotime($token_data['expires_at']));
        
        echo json_encode([
            'valid' => true,
            'expires_in' => $hours_left,
            'expires_at' => $expires_at
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
?>