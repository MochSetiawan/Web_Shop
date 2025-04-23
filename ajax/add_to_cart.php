<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to add items to your cart'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if cart_items table exists
    $table_exists = $conn->query("SHOW TABLES LIKE 'cart_items'")->num_rows > 0;
    
    if (!$table_exists) {
        // Create cart_items table
        $create_table = "
        CREATE TABLE IF NOT EXISTS `cart_items` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `product_id` int(11) NOT NULL,
          `quantity` int(11) NOT NULL DEFAULT 1,
          `created_at` datetime NOT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `product_id` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        if (!$conn->query($create_table)) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create cart table: ' . $conn->error
            ]);
            exit;
        }
    }

    // Get product ID and quantity
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    // Validate inputs
    if ($product_id <= 0) {
        $response['message'] = 'Invalid product ID';
        echo json_encode($response);
        exit;
    }
    
    if ($quantity <= 0) {
        $response['message'] = 'Quantity must be greater than zero';
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
    
    // Check if product is already in cart
    $cart_check = "SELECT id, quantity FROM cart_items WHERE user_id = $user_id AND product_id = $product_id";
    $cart_result = $conn->query($cart_check);
    
    if ($cart_result && $cart_result->num_rows > 0) {
        // Update quantity
        $cart_item = $cart_result->fetch_assoc();
        $new_quantity = $cart_item['quantity'] + $quantity;
        
        $update_query = "UPDATE cart_items SET quantity = $new_quantity, updated_at = NOW() WHERE id = {$cart_item['id']}";
        
        if ($conn->query($update_query)) {
            $response['success'] = true;
            $response['message'] = 'Cart updated successfully';
            $response['cart_item_id'] = $cart_item['id'];
            $response['updated_quantity'] = $new_quantity;
        } else {
            $response['message'] = 'Failed to update cart: ' . $conn->error;
        }
    } else {
        // Add new cart item
        $insert_query = "INSERT INTO cart_items (user_id, product_id, quantity, created_at) VALUES ($user_id, $product_id, $quantity, NOW())";
        
        if ($conn->query($insert_query)) {
            $response['success'] = true;
            $response['message'] = 'Product added to cart';
            $response['cart_item_id'] = $conn->insert_id;
            $response['quantity'] = $quantity;
            
            // Get product name for response
            $product_name_query = "SELECT name FROM products WHERE id = $product_id";
            $product_name_result = $conn->query($product_name_query);
            if ($product_name_result && $product_name_result->num_rows > 0) {
                $response['product_name'] = $product_name_result->fetch_assoc()['name'];
            }
        } else {
            $response['message'] = 'Failed to add product to cart: ' . $conn->error;
        }
    }
    
    // Get updated cart count
    $count_query = "SELECT SUM(quantity) as total FROM cart_items WHERE user_id = $user_id";
    $count_result = $conn->query($count_query);
    
    if ($count_result && $count_result->num_rows > 0) {
        $response['cart_count'] = (int)$count_result->fetch_assoc()['total'];
    } else {
        $response['cart_count'] = 0;
    }
    
    // Get all cart items for debugging
    $all_items_query = "SELECT product_id, quantity FROM cart_items WHERE user_id = $user_id";
    $all_items_result = $conn->query($all_items_query);
    $response['all_cart_items'] = [];
    
    if ($all_items_result && $all_items_result->num_rows > 0) {
        while ($item = $all_items_result->fetch_assoc()) {
            $response['all_cart_items'][] = $item;
        }
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);