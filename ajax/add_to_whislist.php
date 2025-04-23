<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to add items to your wishlist'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if wishlist_items table exists
    $table_exists = $conn->query("SHOW TABLES LIKE 'wishlist_items'")->num_rows > 0;
    
    if (!$table_exists) {
        // Create wishlist_items table
        $create_table = "
        CREATE TABLE IF NOT EXISTS `wishlist_items` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `product_id` int(11) NOT NULL,
          `created_at` datetime NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `user_product` (`user_id`,`product_id`),
          KEY `product_id` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        if (!$conn->query($create_table)) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create wishlist table: ' . $conn->error
            ]);
            exit;
        }
    }
    
    // Get product ID
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    
    // Validate inputs
    if ($product_id <= 0) {
        $response['message'] = 'Invalid product ID';
        echo json_encode($response);
        exit;
    }
    
    // Check if product exists
    $product_query = "SELECT id FROM products WHERE id = $product_id";
    $product_result = $conn->query($product_query);
    
    if (!$product_result || $product_result->num_rows === 0) {
        $response['message'] = 'Product not found';
        echo json_encode($response);
        exit;
    }
    
    // Check if product is already in wishlist
    $wishlist_check = "SELECT id FROM wishlist_items WHERE user_id = $user_id AND product_id = $product_id";
    $wishlist_result = $conn->query($wishlist_check);
    
    if ($wishlist_result && $wishlist_result->num_rows > 0) {
        // Already in wishlist - remove it
        $wishlist_id = $wishlist_result->fetch_assoc()['id'];
        $delete_query = "DELETE FROM wishlist_items WHERE id = $wishlist_id";
        
        if ($conn->query($delete_query)) {
            $response['success'] = true;
            $response['message'] = 'Product removed from wishlist';
            $response['removed'] = true;
        } else {
            $response['message'] = 'Failed to remove product from wishlist: ' . $conn->error;
        }
    } else {
        // Add to wishlist
        $insert_query = "INSERT INTO wishlist_items (user_id, product_id, created_at) VALUES ($user_id, $product_id, NOW())";
        
        if ($conn->query($insert_query)) {
            $response['success'] = true;
            $response['message'] = 'Product added to wishlist';
            $response['wishlist_item_id'] = $conn->insert_id;
            $response['removed'] = false;
            
            // Get product name for response
            $product_name_query = "SELECT name FROM products WHERE id = $product_id";
            $product_name_result = $conn->query($product_name_query);
            if ($product_name_result && $product_name_result->num_rows > 0) {
                $response['product_name'] = $product_name_result->fetch_assoc()['name'];
            }
        } else {
            $response['message'] = 'Failed to add product to wishlist: ' . $conn->error;
        }
    }
    
    // Get wishlist count
    $count_query = "SELECT COUNT(*) as total FROM wishlist_items WHERE user_id = $user_id";
    $count_result = $conn->query($count_query);
    
    if ($count_result && $count_result->num_rows > 0) {
        $response['wishlist_count'] = (int)$count_result->fetch_assoc()['total'];
    } else {
        $response['wishlist_count'] = 0;
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);