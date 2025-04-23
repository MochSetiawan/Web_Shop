<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Pastikan user sudah login
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Anda harus login terlebih dahulu'
    ]);
    exit;
}

header('Content-Type: application/json');

// Mendapatkan daftar vendor
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = "SELECT v.id, v.shop_name, u.username 
             FROM vendors v 
             JOIN users u ON v.user_id = u.id 
             WHERE v.status = 'active'
             ORDER BY v.shop_name ASC";
    
    $result = $conn->query($query);
    $vendors = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $vendors[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'vendors' => $vendors
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mendapatkan daftar vendor'
        ]);
    }
    exit;
}

// Jika tidak ada action yang cocok
echo json_encode([
    'success' => false,
    'message' => 'Method tidak valid'
]);
?>