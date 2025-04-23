<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Product Reviews';
$current_user = $_SESSION['username'] ?? 'MochSetiawan';
$current_datetime = date('Y-m-d H:i:s');

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Please log in to submit reviews.";
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Initialize database structure
function ensureDatabaseStructure($conn) {
    // Check reviews table
    $reviews_table_exists = $conn->query("SHOW TABLES LIKE 'reviews'")->num_rows > 0;
    
    if (!$reviews_table_exists) {
        // Create reviews table
        $create_reviews_table = "CREATE TABLE `reviews` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `product_id` int(11) NOT NULL,
            `order_id` int(11) NOT NULL,
            `rating` int(1) NOT NULL,
            `comment` text DEFAULT NULL,
            `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            `created_at` datetime NOT NULL,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `product_id` (`product_id`),
            KEY `order_id` (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        if (!$conn->query($create_reviews_table)) {
            error_log("Failed to create reviews table: " . $conn->error);
        }
    } else {
        // Check columns in reviews table
        $columns_to_check = [
            'order_id' => 'int(11) NOT NULL',
            'status' => "enum('pending','approved','rejected') NOT NULL DEFAULT 'pending'"
        ];
        
        foreach ($columns_to_check as $column => $definition) {
            $column_exists = $conn->query("SHOW COLUMNS FROM reviews LIKE '$column'")->num_rows > 0;
            if (!$column_exists) {
                $alter_query = "ALTER TABLE reviews ADD COLUMN $column $definition";
                if (!$conn->query($alter_query)) {
                    error_log("Failed to add column $column to reviews table: " . $conn->error);
                }
            }
        }
    }
    
    // Check if products table has avg_rating column
    $avg_rating_exists = $conn->query("SHOW COLUMNS FROM products LIKE 'avg_rating'")->num_rows > 0;
    if (!$avg_rating_exists) {
        $alter_query = "ALTER TABLE products ADD COLUMN avg_rating DECIMAL(3,2) DEFAULT 0";
        if (!$conn->query($alter_query)) {
            error_log("Failed to add avg_rating column to products table: " . $conn->error);
        }
    }
    
    // Check if products table has review_count column
    $review_count_exists = $conn->query("SHOW COLUMNS FROM products LIKE 'review_count'")->num_rows > 0;
    if (!$review_count_exists) {
        $alter_query = "ALTER TABLE products ADD COLUMN review_count INT DEFAULT 0";
        if (!$conn->query($alter_query)) {
            error_log("Failed to add review_count column to products table: " . $conn->error);
        }
    }
}

// Check and set up database structure
ensureDatabaseStructure($conn);

// Find the image column in products table
function findImageColumn($conn) {
    $possible_columns = ['image', 'image_path', 'photo', 'thumbnail', 'picture', 'img'];
    
    foreach ($possible_columns as $column) {
        if ($conn->query("SHOW COLUMNS FROM products LIKE '$column'")->num_rows > 0) {
            return $column;
        }
    }
    
    return null;
}

$image_column = findImageColumn($conn);

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $product_id = (int)$_POST['product_id'];
    $order_id = (int)$_POST['order_id'];
    $rating = (int)$_POST['rating'];
    $comment = $conn->real_escape_string($_POST['comment']);
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error_message'] = "Please select a valid rating between 1 and 5 stars.";
    } else {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Check if review already exists
            $check_query = "SELECT id FROM reviews WHERE user_id = ? AND product_id = ? AND order_id = ?";
            $stmt = $conn->prepare($check_query);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param('iii', $user_id, $product_id, $order_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing review
                $review_id = $result->fetch_assoc()['id'];
                $update_query = "UPDATE reviews SET rating = ?, comment = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                
                if (!$stmt) {
                    throw new Exception("Update prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param('isi', $rating, $comment, $review_id);
                if (!$stmt->execute()) {
                    throw new Exception("Update execute failed: " . $stmt->error);
                }
                
                $_SESSION['success_message'] = "Your review has been updated successfully!";
            } else {
                // Insert new review
                $insert_query = "INSERT INTO reviews (user_id, product_id, order_id, rating, comment, status, created_at) 
                                VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
                $stmt = $conn->prepare($insert_query);
                
                if (!$stmt) {
                    throw new Exception("Insert prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param('iiiis', $user_id, $product_id, $order_id, $rating, $comment);
                if (!$stmt->execute()) {
                    throw new Exception("Insert execute failed: " . $stmt->error);
                }
                
                // Increment review count for the product
                $update_count_query = "UPDATE products SET review_count = review_count + 1 WHERE id = ?";
                $stmt = $conn->prepare($update_count_query);
                
                if ($stmt) {
                    $stmt->bind_param('i', $product_id);
                    $stmt->execute();
                }
                
                $_SESSION['success_message'] = "Thank you! Your review has been submitted successfully!";
            }
            
            // Update product rating
            $avg_query = "SELECT AVG(rating) as avg_rating FROM reviews WHERE product_id = ? AND status != 'rejected'";
            $stmt = $conn->prepare($avg_query);
            
            if (!$stmt) {
                throw new Exception("AVG prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $avg_result = $stmt->get_result();
            $avg_rating = $avg_result->fetch_assoc()['avg_rating'];
            
            $update_rating_query = "UPDATE products SET avg_rating = ? WHERE id = ?";
            $stmt = $conn->prepare($update_rating_query);
            
            if (!$stmt) {
                throw new Exception("Rating update prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param('di', $avg_rating, $product_id);
            if (!$stmt->execute()) {
                throw new Exception("Rating update execute failed: " . $stmt->error);
            }
            
            // Create notification for vendor (if notifications table exists)
            $notifications_exist = $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0;
            
            if ($notifications_exist) {
                // Get vendor ID from product
                $vendor_query = "SELECT vendor_id FROM products WHERE id = ?";
                $stmt = $conn->prepare($vendor_query);
                
                if ($stmt) {
                    $stmt->bind_param('i', $product_id);
                    $stmt->execute();
                    $vendor_result = $stmt->get_result();
                    
                    if ($vendor_result->num_rows > 0) {
                        $vendor_id = $vendor_result->fetch_assoc()['vendor_id'];
                        
                        // Get vendor's user ID
                        $vendor_user_query = "SELECT user_id FROM vendors WHERE id = ?";
                        $stmt = $conn->prepare($vendor_user_query);
                        
                        if ($stmt) {
                            $stmt->bind_param('i', $vendor_id);
                            $stmt->execute();
                            $vendor_user_result = $stmt->get_result();
                            
                            if ($vendor_user_result->num_rows > 0) {
                                $vendor_user_id = $vendor_user_result->fetch_assoc()['user_id'];
                                
                                // Create notification
                                $product_name_query = "SELECT name FROM products WHERE id = ?";
                                $stmt = $conn->prepare($product_name_query);
                                $stmt->bind_param('i', $product_id);
                                $stmt->execute();
                                $product_name_result = $stmt->get_result();
                                $product_name = $product_name_result->fetch_assoc()['name'];
                                
                                $message = "New $rating-star review for your product \"$product_name\"";
                                $insert_notification = "INSERT INTO notifications (user_id, message, type, created_at) 
                                                     VALUES (?, ?, 'review', NOW())";
                                $stmt = $conn->prepare($insert_notification);
                                
                                if ($stmt) {
                                    $stmt->bind_param('is', $vendor_user_id, $message);
                                    $stmt->execute();
                                }
                            }
                        }
                    }
                }
            }
            
            // Commit transaction
            $conn->commit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
            error_log("Review submission error: " . $e->getMessage());
        }
        
        // Redirect to prevent form resubmission
        header('Location: ' . SITE_URL . '/review.php?order_id=' . $order_id);
        exit;
    }
}

// Get order details if order_id is provided
$order_details = [];
$order_items = [];
$reviews_submitted = [];

if (isset($_GET['order_id'])) {
    $order_id = (int)$_GET['order_id'];
    
    try {
        // Check if order exists and belongs to user
        $check_order = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($check_order);
        
        if (!$stmt) {
            throw new Exception("Order check prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param('ii', $order_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $_SESSION['error_message'] = "Order not found or you don't have permission to view it.";
            header('Location: ' . SITE_URL . '/orders.php');
            exit;
        }
        
        $order_details = $result->fetch_assoc();
        
        // Get order items with dynamic image column
        $items_query = "SELECT oi.*, p.name as product_name";
        
        if ($image_column) {
            $items_query .= ", p.$image_column as product_image";
        } else {
            $items_query .= ", NULL as product_image";
        }
        
        $items_query .= " FROM order_items oi
                       JOIN products p ON oi.product_id = p.id
                       WHERE oi.order_id = ?";
        
        $stmt = $conn->prepare($items_query);
        
        if (!$stmt) {
            // Try simpler query if join fails
            $items_query = "SELECT * FROM order_items WHERE order_id = ?";
            $stmt = $conn->prepare($items_query);
            
            if (!$stmt) {
                throw new Exception("Items query prepare failed: " . $conn->error);
            }
        }
        
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $items_result = $stmt->get_result();
        
        while ($item = $items_result->fetch_assoc()) {
            // If the product_name isn't set (simple query was used), get product details separately
            if (!isset($item['product_name']) && isset($item['product_id'])) {
                $product_query = "SELECT name";
                
                if ($image_column) {
                    $product_query .= ", $image_column as product_image";
                }
                
                $product_query .= " FROM products WHERE id = ?";
                
                $product_stmt = $conn->prepare($product_query);
                if ($product_stmt) {
                    $product_stmt->bind_param('i', $item['product_id']);
                    $product_stmt->execute();
                    $product_result = $product_stmt->get_result();
                    
                    if ($product_result->num_rows > 0) {
                        $product_data = $product_result->fetch_assoc();
                        $item['product_name'] = $product_data['name'];
                        
                        if (isset($product_data['product_image'])) {
                            $item['product_image'] = $product_data['product_image'];
                        }
                    } else {
                        $item['product_name'] = "Product #" . $item['product_id'];
                    }
                }
            }
            
            // Get review data for this item
            $review_query = "SELECT * FROM reviews WHERE product_id = ? AND order_id = ? AND user_id = ?";
            $review_stmt = $conn->prepare($review_query);
            
            if ($review_stmt) {
                $review_stmt->bind_param('iii', $item['product_id'], $order_id, $user_id);
                $review_stmt->execute();
                $review_result = $review_stmt->get_result();
                
                if ($review_result->num_rows > 0) {
                    $review_data = $review_result->fetch_assoc();
                    $item['review_id'] = $review_data['id'];
                    $item['review_rating'] = $review_data['rating'];
                    $item['review_comment'] = $review_data['comment'];
                    $item['review_status'] = $review_data['status'];
                    $item['review_created_at'] = $review_data['created_at'];
                    
                    $reviews_submitted[] = $item['product_id'];
                }
            }
            
            $order_items[] = $item;
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error loading order details: " . $e->getMessage();
        error_log("Error in review.php order details: " . $e->getMessage());
    }
}

// Get orders eligible for review (delivered or shipped)
$eligible_orders = [];

try {
    $orders_query = "SELECT o.id, o.order_number, o.status, o.created_at,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as total_items,
                   (SELECT COUNT(*) FROM reviews WHERE order_id = o.id AND user_id = ?) as reviews_count
                   FROM orders o
                   WHERE o.user_id = ? AND (o.status = 'delivered' OR o.status = 'shipped')
                   ORDER BY o.created_at DESC";
    
    $stmt = $conn->prepare($orders_query);
    
    if (!$stmt) {
        // Try simpler version
        $orders_query = "SELECT id, order_number, status, created_at FROM orders 
                       WHERE user_id = ? AND (status = 'delivered' OR status = 'shipped')
                       ORDER BY created_at DESC";
        $stmt = $conn->prepare($orders_query);
        
        if (!$stmt) {
            throw new Exception("Orders query prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param('i', $user_id);
    } else {
        $stmt->bind_param('ii', $user_id, $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Add any missing information
        if (!isset($row['total_items'])) {
            $count_query = "SELECT COUNT(*) as count FROM order_items WHERE order_id = ?";
            $count_stmt = $conn->prepare($count_query);
            
            if ($count_stmt) {
                $count_stmt->bind_param('i', $row['id']);
                $count_stmt->execute();
                $count_result = $count_stmt->get_result();
                $row['total_items'] = $count_result->fetch_assoc()['count'];
            } else {
                $row['total_items'] = 0;
            }
        }
        
        if (!isset($row['reviews_count'])) {
            $reviews_query = "SELECT COUNT(*) as count FROM reviews WHERE order_id = ? AND user_id = ?";
            $reviews_stmt = $conn->prepare($reviews_query);
            
            if ($reviews_stmt) {
                $reviews_stmt->bind_param('ii', $row['id'], $user_id);
                $reviews_stmt->execute();
                $reviews_result = $reviews_stmt->get_result();
                $row['reviews_count'] = $reviews_result->fetch_assoc()['count'];
            } else {
                $row['reviews_count'] = 0;
            }
        }
        
        $eligible_orders[] = $row;
    }
} catch (Exception $e) {
    error_log("Error in review.php eligible orders: " . $e->getMessage());
}

// Get user's past reviews
$past_reviews = [];

try {
    $reviews_query = "SELECT r.*, p.name as product_name, o.order_number, o.created_at as order_date";
    
    if ($image_column) {
        $reviews_query .= ", p.$image_column as product_image";
    } else {
        $reviews_query .= ", NULL as product_image";
    }
    
    $reviews_query .= " FROM reviews r
                     JOIN products p ON r.product_id = p.id
                     JOIN orders o ON r.order_id = o.id
                     WHERE r.user_id = ?
                     ORDER BY r.created_at DESC
                     LIMIT 10";
    
    $stmt = $conn->prepare($reviews_query);
    
    if (!$stmt) {
        throw new Exception("Past reviews query prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $past_reviews[] = $row;
    }
} catch (Exception $e) {
    error_log("Error in review.php past reviews: " . $e->getMessage());
}

// Get summary statistics for user's reviews
$review_stats = [
    'total' => 0,
    'avg_rating' => 0,
    'ratings' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
];

try {
    $stats_query = "SELECT COUNT(*) as total, AVG(rating) as avg_rating FROM reviews WHERE user_id = ?";
    $stmt = $conn->prepare($stats_query);
    
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stats = $result->fetch_assoc();
            $review_stats['total'] = $stats['total'];
            $review_stats['avg_rating'] = $stats['avg_rating'];
        }
        
        // Get counts by rating
        $rating_query = "SELECT rating, COUNT(*) as count FROM reviews WHERE user_id = ? GROUP BY rating";
        $stmt = $conn->prepare($rating_query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $review_stats['ratings'][$row['rating']] = $row['count'];
        }
    }
} catch (Exception $e) {
    error_log("Error in review.php statistics: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <!-- User Welcome Banner -->
    <div class="card mb-4 border-left-primary">
        <div class="card-body py-3">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Current User's Login: <?= htmlspecialchars($current_user) ?>
                    </div>
                    <div class="text-xs text-gray-800">
                        Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 
                        <span id="live-datetime"><?= $current_datetime ?></span>
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-user-clock fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/orders.php">My Orders</a></li>
            <li class="breadcrumb-item active">Product Reviews</li>
        </ol>
    </nav>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?= $_SESSION['success_message'] ?>
            <?php unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?= $_SESSION['error_message'] ?>
            <?php unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Review Statistics Card (New Feature) -->
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold text-primary">Your Review Activity</h5>
                    <?php if ($review_stats['total'] > 0): ?>
                        <div class="d-flex align-items-center">
                            <div class="text-warning mr-2">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?= ($i <= round($review_stats['avg_rating'])) ? 'text-warning' : 'text-muted' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="font-weight-bold"><?= number_format($review_stats['avg_rating'], 1) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($review_stats['total'] > 0): ?>
                        <div class="row">
                            <div class="col-md-6 mb-4 mb-md-0">
                                <div class="text-center mb-3">
                                    <h6 class="mb-0">Total Reviews</h6>
                                    <div class="h1 mt-1 mb-0"><?= $review_stats['total'] ?></div>
                                </div>
                                
                                <div class="text-center">
                                    <div class="small text-muted mb-1">Your rating distribution</div>
                                    <div class="d-flex justify-content-center">
                                        <?php
                                        // Calculate percentage for each rating
                                        $max_height = 60; // Max bar height in pixels
                                        foreach ($review_stats['ratings'] as $rating => $count) {
                                            $percentage = ($review_stats['total'] > 0) ? ($count / $review_stats['total'] * 100) : 0;
                                            $height = ($review_stats['total'] > 0) ? ($count / $review_stats['total'] * $max_height) : 0;
                                        ?>
                                            <div class="mx-1 d-flex flex-column align-items-center" style="width: 30px;">
                                                <div class="small"><?= $count ?></div>
                                                <div class="bg-primary rounded" style="width: 15px; height: <?= $height ?>px;"></div>
                                                <div class="small mt-1"><?= $rating ?>★</div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-info mb-3">
                                    <i class="fas fa-medal mr-2"></i>
                                    <?php if ($review_stats['total'] < 5): ?>
                                        Review <?= 5 - $review_stats['total'] ?> more products to earn "Trusted Reviewer" badge!
                                    <?php elseif ($review_stats['total'] < 10): ?>
                                        Review <?= 10 - $review_stats['total'] ?> more products to earn "Expert Reviewer" badge!
                                    <?php else: ?>
                                        Congratulations! You've earned the "Expert Reviewer" badge.
                                    <?php endif; ?>
                                </div>
                                <div class="card bg-light">
                                    <div class="card-body py-2">
                                        <div class="small text-muted mb-1">Your review badges:</div>
                                        <div>
                                            <?php if ($review_stats['total'] >= 1): ?>
                                                <span class="badge badge-secondary mr-1"><i class="fas fa-star mr-1"></i> Reviewer</span>
                                            <?php endif; ?>
                                            <?php if ($review_stats['total'] >= 5): ?>
                                                <span class="badge badge-primary mr-1"><i class="fas fa-star mr-1"></i> Trusted Reviewer</span>
                                            <?php endif; ?>
                                            <?php if ($review_stats['total'] >= 10): ?>
                                                <span class="badge badge-success"><i class="fas fa-medal mr-1"></i> Expert Reviewer</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-star fa-3x text-muted mb-3"></i>
                            <p>You haven't written any reviews yet.</p>
                            <p class="text-muted">Start reviewing products from your completed orders to help other shoppers make better decisions!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        
            <?php if (!empty($order_details)): ?>
                <!-- Order Details for Review -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h5 class="m-0 font-weight-bold text-primary">Review Products from Order #<?= $order_details['order_number'] ?? $order_id ?></h5>
                        <span class="badge badge-<?= $order_details['status'] === 'delivered' ? 'success' : 'primary' ?> px-3 py-2">
                            <?= ucfirst($order_details['status']) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-calendar-alt text-primary mr-2"></i>
                                        <span>Order Date: <strong><?= date('M d, Y', strtotime($order_details['created_at'])) ?></strong></span>
                                    </div>
                                    
                                    <?php if (isset($order_details['total_amount'])): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-money-bill-wave text-success mr-2"></i>
                                        <span>Total Amount: <strong><?= formatPrice($order_details['total_amount'] ?? 0) ?></strong></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <div class="progress" style="height: 20px;">
                                        <?php 
                                        $progress = 0;
                                        if (!empty($order_items)) {
                                            $progress = count($reviews_submitted) / count($order_items) * 100;
                                        }
                                        ?>
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%"
                                            aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?= count($reviews_submitted) ?>/<?= count($order_items) ?> Reviewed
                                        </div>
                                    </div>
                                    <div class="small text-muted mt-1 text-center">
                                        <?php if ($progress < 100): ?>
                                            <?= count($order_items) - count($reviews_submitted) ?> products left to review
                                        <?php else: ?>
                                            All products have been reviewed! Thank you!
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (empty($order_items)): ?>
                            <div class="alert alert-info">No products found in this order.</div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($order_items as $item): ?>
                                    <div class="list-group-item <?= !empty($item['review_id']) ? 'border-left-success' : '' ?>">
                                        <div class="row align-items-center">
                                            <div class="col-md-2 mb-2 mb-md-0">
                                                <?php if (!empty($item['product_image'])): ?>
                                                    <img src="<?= SITE_URL ?>/assets/img/products/<?= $item['product_image'] ?>" alt="<?= htmlspecialchars($item['product_name'] ?? '') ?>" class="img-fluid rounded">
                                                <?php else: ?>
                                                    <div class="bg-light d-flex align-items-center justify-content-center rounded" style="width: 80px; height: 80px;">
                                                        <i class="fas fa-box text-muted fa-2x"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4 mb-2 mb-md-0">
                                                <h6 class="mb-1"><?= htmlspecialchars($item['product_name'] ?? 'Product #'.$item['product_id']) ?></h6>
                                                <div class="small text-muted mb-1">
                                                    <i class="fas fa-cubes mr-1"></i> Quantity: <?= $item['quantity'] ?? 1 ?>
                                                </div>
                                                <?php if (isset($item['price'])): ?>
                                                <div class="small text-muted">
                                                    <i class="fas fa-tag mr-1"></i> Price: <?= formatPrice($item['price']) ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <?php if (!empty($item['review_id'])): ?>
                                                    <!-- Already reviewed -->
                                                    <div class="card bg-light mb-2">
                                                        <div class="card-body py-2">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <div class="rating-display">
                                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                                        <i class="fas fa-star <?= ($i <= $item['review_rating']) ? 'text-warning' : 'text-muted' ?>"></i>
                                                                    <?php endfor; ?>
                                                                </div>
                                                                <small class="text-muted">
                                                                    <?= date('M d, Y', strtotime($item['review_created_at'] ?? $order_details['created_at'])) ?>
                                                                </small>
                                                            </div>
                                                            <p class="small mb-0"><?= nl2br(htmlspecialchars($item['review_comment'])) ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex">
                                                        <div class="mr-2">
                                                            <span class="badge badge-<?= $item['review_status'] === 'approved' ? 'success' : ($item['review_status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                                                <?= ucfirst($item['review_status'] ?? 'pending') ?>
                                                            </span>
                                                        </div>
                                                        <button type="button" class="btn btn-sm btn-outline-primary edit-review-btn"
                                                                data-product-id="<?= $item['product_id'] ?>"
                                                                data-product-name="<?= htmlspecialchars($item['product_name'] ?? 'Product #'.$item['product_id']) ?>"
                                                                data-rating="<?= $item['review_rating'] ?>"
                                                                data-comment="<?= htmlspecialchars($item['review_comment']) ?>"
                                                                data-order-id="<?= $order_id ?>">
                                                            <i class="fas fa-edit"></i> Edit Review
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- Not yet reviewed -->
                                                    <p class="small text-muted mb-2">Share your experience with this product to help other shoppers.</p>
                                                    <button type="button" class="btn btn-primary review-now-btn"
                                                            data-product-id="<?= $item['product_id'] ?>"
                                                            data-product-name="<?= htmlspecialchars($item['product_name'] ?? 'Product #'.$item['product_id']) ?>"
                                                            data-order-id="<?= $order_id ?>">
                                                        <i class="fas fa-star mr-1"></i> Write a Review
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- If no specific order is selected, show a message about reviews -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-star fa-3x text-warning mb-3"></i>
                        <h3>Share Your Experience</h3>
                        <p class="text-muted mb-4">Your reviews help other shoppers make better decisions and provide valuable feedback to our vendors.</p>
                        <?php if (empty($eligible_orders)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i>
                                You don't have any completed orders to review yet. Once your orders are delivered, you'll be able to leave reviews here.
                            </div>
                            <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary">
                                <i class="fas fa-shopping-bag mr-1"></i> Continue Shopping
                            </a>
                        <?php else: ?>
                            <p>Please select an order from the list to review the products.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Past Reviews -->
            <?php if (!empty($past_reviews)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3">
                        <h5 class="m-0 font-weight-bold text-primary">Your Recent Reviews</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php foreach($past_reviews as $review): ?>
                                <div class="list-group-item">
                                    <div class="row">
                                        <div class="col-md-2 mb-2 mb-md-0">
                                            <?php if (!empty($review['product_image'])): ?>
                                                <img src="<?= SITE_URL ?>/assets/img/products/<?= $review['product_image'] ?>" alt="<?= htmlspecialchars($review['product_name']) ?>" class="img-fluid rounded">
                                            <?php else: ?>
                                                <div class="bg-light d-flex align-items-center justify-content-center rounded" style="width: 80px; height: 80px;">
                                                    <i class="fas fa-box text-muted fa-2x"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-10">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="mb-0"><?= htmlspecialchars($review['product_name']) ?></h6>
                                                <div>
                                                    <small class="text-muted mr-2">Order #<?= $review['order_number'] ?></small>
                                                    <span class="badge badge-<?= $review['status'] === 'approved' ? 'success' : ($review['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                                        <?= ucfirst($review['status']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="rating-display mr-2">
                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?= ($i <= $review['rating']) ? 'text-warning' : 'text-muted' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?= date('M d, Y', strtotime($review['created_at'])) ?>
                                                </small>
                                            </div>
                                            <p class="mb-0 small"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($past_reviews) >= 10): ?>
                            <div class="text-center mt-3">
                                <a href="<?= SITE_URL ?>/user-profile.php?tab=reviews" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-list-alt mr-1"></i> View All Reviews
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-4">
            <!-- Orders eligible for review -->
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3">
                    <h5 class="m-0 font-weight-bold text-primary">Orders Available for Review</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($eligible_orders)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <p class="mb-0">You don't have any completed orders yet.</p>
                            <p class="text-muted small">Your orders will appear here when they are delivered.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($eligible_orders as $order): ?>
                                <a href="<?= SITE_URL ?>/review.php?order_id=<?= $order['id'] ?>" class="list-group-item list-group-item-action <?= (isset($_GET['order_id']) && $_GET['order_id'] == $order['id']) ? 'active' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>Order #<?= $order['order_number'] ?></strong>
                                        <span class="badge badge-<?= $order['status'] == 'delivered' ? 'success' : 'primary' ?> badge-pill">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small class="text-<?= (isset($_GET['order_id']) && $_GET['order_id'] == $order['id']) ? 'light' : 'muted' ?>">
                                            <?= date('M d, Y', strtotime($order['created_at'])) ?>
                                        </small>
                                        
                                        <?php
                                        $reviewed = isset($order['reviews_count']) ? $order['reviews_count'] : 0;
                                        $total = isset($order['total_items']) ? $order['total_items'] : 0;
                                        $percent = ($total > 0) ? ($reviewed / $total * 100) : 0;
                                        ?>
                                        
                                        <div class="progress" style="width: 100px; height: 10px;">
                                            <div class="progress-bar bg-<?= (isset($_GET['order_id']) && $_GET['order_id'] == $order['id']) ? 'light' : 'success' ?>" 
                                                role="progressbar" 
                                                style="width: <?= $percent ?>%" 
                                                aria-valuenow="<?= $percent ?>" 
                                                aria-valuemin="0" 
                                                aria-valuemax="100">
                                            </div>
                                        </div>
                                        
                                        <small class="text-<?= (isset($_GET['order_id']) && $_GET['order_id'] == $order['id']) ? 'light' : 'muted' ?>">
                                            <?= $reviewed ?>/<?= $total ?>
                                        </small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Why Reviews Matter -->
            <div class="card shadow-sm mb-4 border-left-info">
                <div class="card-header bg-info text-white py-3">
                    <h5 class="m-0 font-weight-bold">Why Your Reviews Matter</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex">
                            <div class="mr-3 text-info">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                            <div>
                                <strong>Help Other Shoppers</strong>
                                <p class="small text-muted">Your honest opinions help other customers make informed purchase decisions.</p>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex">
                            <div class="mr-3 text-info">
                                <i class="fas fa-store fa-2x"></i>
                            </div>
                            <div>
                                <strong>Support Vendors</strong>
                                <p class="small text-muted">Reviews provide valuable feedback to help vendors improve their products and services.</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex">
                            <div class="mr-3 text-info">
                                <i class="fas fa-award fa-2x"></i>
                            </div>
                            <div>
                                <strong>Earn Rewards</strong>
                                <p class="small text-muted">Active reviewers earn special badges and may receive exclusive offers!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Review Guidelines -->
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3">
                    <h5 class="m-0 font-weight-bold text-primary">Review Guidelines</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <div class="d-flex">
                                <div class="mr-3 text-primary">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                                <div>
                                    <strong>Be Specific</strong>
                                    <p class="small text-muted">Mention what you liked or disliked about the product and why.</p>
                                </div>
                            </div>
                        </li>
                        <li class="mb-3">
                            <div class="d-flex">
                                <div class="mr-3 text-primary">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                                <div>
                                    <strong>Be Honest</strong>
                                    <p class="small text-muted">Your review should reflect your genuine experience with the product.</p>
                                </div>
                            </div>
                        </li>
                        <li>
                            <div class="d-flex">
                                <div class="mr-3 text-primary">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                                <div>
                                    <strong>Be Respectful</strong>
                                    <p class="small text-muted">Keep your review constructive and respectful, even if your experience was negative.</p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" role="dialog" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="reviewModalLabel">Write a Review</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" id="closeModalX">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="reviewForm" method="post" action="">
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <h5 id="product-name-display" class="font-weight-bold"></h5>
                    </div>
                    
                    <input type="hidden" id="product_id" name="product_id">
                    <input type="hidden" id="order_id" name="order_id">
                    
                    <div class="form-group">
                        <label class="font-weight-bold">Your Rating:</label>
                        <div class="rating-input text-center">
                            <div class="star-rating">
                                <?php for($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" id="star<?=$i?>" name="rating" value="<?=$i?>" required />
                                    <label for="star<?=$i?>" title="<?=$i?> stars">
                                        <i class="fas fa-star"></i>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="comment" class="font-weight-bold">Your Review:</label>
                        <textarea class="form-control" id="comment" name="comment" rows="5" placeholder="Share your experience with this product..."></textarea>
                        <small class="form-text text-muted">
                            Your honest feedback helps other shoppers make better purchasing decisions.
                        </small>
                    </div>
                    
                    <div class="review-tips bg-light p-3 rounded">
                        <div class="small font-weight-bold mb-2">Tips for a Helpful Review:</div>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="small mb-0 pl-3">
                                    <li>Describe what you liked and didn't like</li>
                                    <li>Explain how you used the product</li>
                                    <li>Compare to similar products you've used</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="small mb-0 pl-3">
                                    <li>Mention quality, durability, and value</li>
                                    <li>Include pros and cons</li>
                                    <li>Be specific and concise</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" id="closeModalBtn">Cancel</button>
                    <button type="submit" name="submit_review" class="btn btn-primary">
                        <i class="fas fa-paper-plane mr-1"></i> Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Rating display style */
.rating-display .fa-star {
    font-size: 1rem;
}

/* Star Rating System */
.star-rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: center;
    padding: 20px 0;
}

.star-rating input {
    display: none;
}

.star-rating label {
    font-size: 40px;
    color: #ddd;
    margin: 0 5px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.star-rating label:hover,
.star-rating label:hover ~ label,
.star-rating input:checked ~ label {
    color: #ffc107;
}

/* Progress bars */
.progress {
    background-color: #e9ecef;
    border-radius: 0.25rem;
}

.progress-bar {
    border-radius: 0.25rem;
}

/* Card styling */
.card {
    border-radius: 0.5rem;
    overflow: hidden;
}

.list-group-item {
    border-left-width: 4px;
}

/* Make sure buttons are visible on small screens */
@media (max-width: 767.98px) {
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.76563rem;
    }
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Update datetime display
    function updateDateTime() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        
        const formattedDateTime = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        $('#live-datetime').text(formattedDateTime);
    }
    
    setInterval(updateDateTime, 1000);
    updateDateTime();
    
    // Open review modal with product info
    $('.review-now-btn').on('click', function() {
        const productId = $(this).data('product-id');
        const productName = $(this).data('product-name');
        const orderId = $(this).data('order-id');
        
        $('#product_id').val(productId);
        $('#order_id').val(orderId);
        $('#product-name-display').text(productName);
        
        // Reset form
        $('#reviewForm')[0].reset();
        
        $('#reviewModal').modal('show');
    });
    
    // Open edit review modal
    $('.edit-review-btn').on('click', function() {
        const productId = $(this).data('product-id');
        const productName = $(this).data('product-name');
        const orderId = $(this).data('order-id');
        const rating = $(this).data('rating');
        const comment = $(this).data('comment');
        
        $('#product_id').val(productId);
        $('#order_id').val(orderId);
        $('#product-name-display').text(productName);
        $('#comment').val(comment);
        
        // Set rating
        $(`#star${rating}`).prop('checked', true);
        
        $('#reviewModal').modal('show');
    });
    
    // Form validation
    $('#reviewForm').on('submit', function(e) {
        const rating = $('input[name=rating]:checked').val();
        
        if (!rating) {
            e.preventDefault();
            alert('Please select a rating for this product.');
            return false;
        }
    });
    
    // Multiple ways to close the modal
    $("#closeModalBtn, #closeModalX").on("click", function() {
        $("#reviewModal").modal('hide');
    });
    
    // Fix Bootstrap modal issues
    $("#reviewModal").on('hidden.bs.modal', function () {
        // Remove modal backdrop
        $('.modal-backdrop').remove();
        // Remove modal-open class
        $('body').removeClass('modal-open');
        // Reset overflow
        $('body').css({
            'overflow': '',
            'padding-right': ''
        });
    });
    
    // Fix touchscreen issues on mobile
    $('body').on('touchstart', '.modal', function(e) {
        if ($(e.target).closest('.modal-content').length === 0) {
            $('#reviewModal').modal('hide');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>