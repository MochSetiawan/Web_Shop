<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';


$pageTitle = 'Shop';
$current_user = $_SESSION['username'] ?? 'MochSetiawan';
$current_datetime = date('Y-m-d H:i:s');


$vendor_id = isset($_GET['vendor']) ? (int)$_GET['vendor'] : 0;
$viewing_vendor = $vendor_id > 0;
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'latest';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 10000000;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$user_id = $_SESSION['user_id'] ?? 0;

// Process vendor review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_vendor_review']) && $user_id) {
    $review_vendor_id = (int)$_POST['vendor_id'];
    $rating = (int)$_POST['rating'];
    $comment = sanitize($_POST['comment']);
    
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error_message'] = "Please provide a valid rating between 1 and 5.";
    } else {
        // Create vendor_reviews table if needed
        $reviews_table_exists = $conn->query("SHOW TABLES LIKE 'vendor_reviews'")->num_rows > 0;
        if (!$reviews_table_exists) {
            $create_reviews_table = "
            CREATE TABLE IF NOT EXISTS `vendor_reviews` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `vendor_id` int(11) NOT NULL,
                `user_id` int(11) NOT NULL,
                `rating` int(1) NOT NULL,
                `comment` text,
                `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
                `created_at` datetime NOT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `vendor_id` (`vendor_id`),
                KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            $conn->query($create_reviews_table);
        }
        
        // Check if user already reviewed this vendor
        $check_review = "SELECT id FROM vendor_reviews WHERE user_id = $user_id AND vendor_id = $review_vendor_id";
        $check_result = $conn->query($check_review);
        
        if ($check_result && $check_result->num_rows > 0) {
            // Update existing review
            $review_id = $check_result->fetch_assoc()['id'];
            $update_query = "UPDATE vendor_reviews SET rating = $rating, comment = '$comment', updated_at = NOW() WHERE id = $review_id";
            
            if ($conn->query($update_query)) {
                $_SESSION['success_message'] = "Your review has been updated successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to update your review: {$conn->error}";
            }
        } else {
            // Add new review
            $insert_query = "INSERT INTO vendor_reviews (vendor_id, user_id, rating, comment, created_at) 
                             VALUES ($review_vendor_id, $user_id, $rating, '$comment', NOW())";
            
            if ($conn->query($insert_query)) {
                $_SESSION['success_message'] = "Your review has been submitted successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to submit your review: {$conn->error}";
            }
        }
        
        // Update vendor average rating
        $avg_query = "SELECT AVG(rating) as avg_rating FROM vendor_reviews WHERE vendor_id = $review_vendor_id AND status != 'rejected'";
        $avg_result = $conn->query($avg_query);
        
        if ($avg_result && $avg_result->num_rows > 0) {
            $avg_rating = $avg_result->fetch_assoc()['avg_rating'];
            
            // Add rating column to vendors table if needed
            $has_rating_column = $conn->query("SHOW COLUMNS FROM vendors LIKE 'rating'")->num_rows > 0;
            if (!$has_rating_column) {
                $conn->query("ALTER TABLE vendors ADD COLUMN rating DECIMAL(3,2) DEFAULT 0");
            }
            
            // Update vendor rating
            $conn->query("UPDATE vendors SET rating = $avg_rating WHERE id = $review_vendor_id");
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Process wishlist actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wishlist_product_id']) && $user_id) {
    $product_id = (int)$_POST['wishlist_product_id'];
    
    // Check if product exists
    $product_check = $conn->query("SELECT id FROM products WHERE id = $product_id");
    if ($product_check && $product_check->num_rows > 0) {
        // Create wishlist_items table if needed
        $table_exists = $conn->query("SHOW TABLES LIKE 'wishlist_items'")->num_rows > 0;
        if (!$table_exists) {
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
            $conn->query($create_table);
        }
        
        // Check if product is already in wishlist
        $wishlist_check = "SELECT id FROM wishlist_items WHERE user_id = $user_id AND product_id = $product_id";
        $wishlist_result = $conn->query($wishlist_check);
        
        if ($wishlist_result && $wishlist_result->num_rows > 0) {
            // Remove from wishlist
            $wishlist_id = $wishlist_result->fetch_assoc()['id'];
            $delete_query = "DELETE FROM wishlist_items WHERE id = $wishlist_id";
            
            if ($conn->query($delete_query)) {
                $_SESSION['success_message'] = "Product removed from wishlist!";
            } else {
                $_SESSION['error_message'] = "Failed to remove product from wishlist.";
            }
        } else {
            // Add to wishlist
            $insert_query = "INSERT INTO wishlist_items (user_id, product_id, created_at) VALUES ($user_id, $product_id, NOW())";
            
            if ($conn->query($insert_query)) {
                $_SESSION['success_message'] = "Product added to wishlist!";
            } else {
                $_SESSION['error_message'] = "Failed to add product to wishlist.";
            }
        }
    } else {
        $_SESSION['error_message'] = "Product not found";
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Get vendor details if viewing vendor profile
$vendor_details = null;
$vendor_reviews = [];
$vendor_avg_rating = 0;
$vendor_review_count = 0;
$vendor_categories = []; 
$user_vendor_review = null;

if ($viewing_vendor) {
    // Get vendor details
    $vendor_query = "SELECT * FROM vendors WHERE id = $vendor_id";
    $vendor_result = $conn->query($vendor_query);
    
    if ($vendor_result && $vendor_result->num_rows > 0) {
        $vendor_details = $vendor_result->fetch_assoc();
        $pageTitle = $vendor_details['shop_name'] . ' - Vendor Profile';
        
        // Get vendor reviews
        if ($conn->query("SHOW TABLES LIKE 'vendor_reviews'")->num_rows > 0) {
            // Get average rating and count
            $avg_query = "SELECT COUNT(*) as review_count, AVG(rating) as avg_rating 
                          FROM vendor_reviews 
                          WHERE vendor_id = $vendor_id AND status != 'rejected'";
            $avg_result = $conn->query($avg_query);
            
            if ($avg_result && $avg_result->num_rows > 0) {
                $avg_data = $avg_result->fetch_assoc();
                $vendor_avg_rating = number_format($avg_data['avg_rating'] ?? 0, 1);
                $vendor_review_count = $avg_data['review_count'] ?? 0;
            }
            
            // Get reviews with user info
            $reviews_query = "SELECT vr.*, u.username 
                             FROM vendor_reviews vr 
                             LEFT JOIN users u ON vr.user_id = u.id 
                             WHERE vr.vendor_id = $vendor_id AND vr.status != 'rejected' 
                             ORDER BY vr.created_at DESC 
                             LIMIT 10";
            $reviews_result = $conn->query($reviews_query);
            
            if ($reviews_result && $reviews_result->num_rows > 0) {
                while ($row = $reviews_result->fetch_assoc()) {
                    $vendor_reviews[] = $row;
                }
            }
            
            // Get current user's review
            if ($user_id) {
                $user_review_query = "SELECT * FROM vendor_reviews WHERE user_id = $user_id AND vendor_id = $vendor_id";
                $user_review_result = $conn->query($user_review_query);
                
                if ($user_review_result && $user_review_result->num_rows > 0) {
                    $user_vendor_review = $user_review_result->fetch_assoc();
                }
            }
        }
        
        // Get categories the vendor sells in
        $vendor_categories_query = "SELECT DISTINCT c.id, c.name, COUNT(p.id) as product_count
                                   FROM products p
                                   JOIN categories c ON p.category_id = c.id
                                   WHERE p.vendor_id = $vendor_id
                                   GROUP BY c.id
                                   ORDER BY c.name ASC";
        $vendor_categories_result = $conn->query($vendor_categories_query);
        
        if ($vendor_categories_result && $vendor_categories_result->num_rows > 0) {
            while ($row = $vendor_categories_result->fetch_assoc()) {
                $vendor_categories[] = $row;
            }
        }
    } else {
        // Vendor not found
        $_SESSION['error_message'] = "Vendor not found";
        header('Location: ' . SITE_URL . '/shop.php');
        exit;
    }
}

// Get all categories for sidebar
$categories = [];
$selected_category = null;
$categories_query = "SELECT * FROM categories ORDER BY name ASC";
$categories_result = $conn->query($categories_query);

if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
        if ($row['id'] == $category_id) {
            $selected_category = $row;
            if (!$viewing_vendor) {
                $pageTitle = $row['name'] . ' - Shop';
            }
        }
    }
}

// Get user's wishlist items
$wishlist_products = [];
if ($user_id) {
    $table_exists = $conn->query("SHOW TABLES LIKE 'wishlist_items'")->num_rows > 0;
    
    if ($table_exists) {
        $wishlist_query = "SELECT product_id FROM wishlist_items WHERE user_id = $user_id";
        $wishlist_result = $conn->query($wishlist_query);
        
        if ($wishlist_result && $wishlist_result->num_rows > 0) {
            while ($row = $wishlist_result->fetch_assoc()) {
                $wishlist_products[] = $row['product_id'];
            }
        }
    }
}

// Get all vendors for sidebar
$all_vendors = [];
if (!$viewing_vendor) {
    $vendors_query = "SELECT v.id, v.shop_name, 
                      (SELECT COUNT(*) FROM products p WHERE p.vendor_id = v.id) as product_count
                      FROM vendors v 
                      ORDER BY v.shop_name ASC";
    $vendors_result = $conn->query($vendors_query);
    
    if ($vendors_result && $vendors_result->num_rows > 0) {
        while ($row = $vendors_result->fetch_assoc()) {
            $all_vendors[] = $row;
        }
    }
}

// SECTION 4: GET PRODUCTS WITH FIXED VENDOR FILTERING
// Build product query with improved vendor filtering
$query = "SELECT p.*, c.name as category_name, v.shop_name as vendor_name, v.id as vendor_id 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN vendors v ON p.vendor_id = v.id 
          WHERE 1=1";

// Critical fix: ensure vendor filtering is applied correctly
if ($viewing_vendor) {
    $query .= " AND p.vendor_id = $vendor_id";
}

// Add other filters
if ($category_id > 0) {
    $query .= " AND p.category_id = $category_id";
}

if (!empty($search)) {
    $query .= " AND (p.name LIKE '%$search%' OR p.description LIKE '%$search%')";
}

$query .= " AND p.price BETWEEN $min_price AND $max_price";

// Apply sorting
switch ($sort) {
    case 'price_low':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'name_asc':
        $query .= " ORDER BY p.name ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY p.name DESC";
        break;
    case 'oldest':
        $query .= " ORDER BY p.id ASC";
        break;
    default:
        $query .= " ORDER BY p.id DESC";
        break;
}

// Pagination setup
$count_query = str_replace("p.*, c.name as category_name, v.shop_name as vendor_name, v.id as vendor_id", "COUNT(*) as total", $query);
$count_query = preg_replace('/ORDER BY.*$/i', '', $count_query);
$count_result = $conn->query($count_query);
$total_products = 0;

if ($count_result && $count_result->num_rows > 0) {
    $total_products = $count_result->fetch_assoc()['total'];
}

$total_pages = ceil($total_products / $per_page);
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

// CRITICAL FIX: For vendor views with few products, don't use pagination
$original_query = $query; // Save the original query

// Apply pagination differently based on context
if ($viewing_vendor && $total_products <= $per_page) {
    // Don't use pagination for vendors with few products
} else {
    // Apply pagination for larger product lists
    $offset = ($page - 1) * $per_page;
    $query .= " LIMIT $offset, $per_page";
}

// Execute the query
$result = $conn->query($query);
$products = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
} else {
    // Initialize products as empty array to avoid null issues
    $products = [];
}

// Ensure products directory exists
$products_dir = __DIR__ . '/assets/img/products';
if (!is_dir($products_dir)) {
    mkdir($products_dir, 0755, true);
}

// Create ajax directory and add_to_cart.php file
$ajax_dir = __DIR__ . '/ajax';
if (!is_dir($ajax_dir)) {
    mkdir($ajax_dir, 0755, true);
}

$add_to_cart_file = "{$ajax_dir}/add_to_cart.php";
if (!file_exists($add_to_cart_file)) {
    $cart_code = <<<'EOT'
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to add items to cart'
    ]);
    exit;
}

// Get product details from POST
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

// Validate input
if ($product_id <= 0 || $quantity <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid product or quantity'
    ]);
    exit;
}

// Check if product exists - using prepared statement for security
$product_stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();
$product_stmt->close();

if (!$product_result || $product_result->num_rows == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Product not found'
    ]);
    exit;
}

// Create cart table if it doesn't exist
$table_exists = $conn->query("SHOW TABLES LIKE 'cart'")->num_rows > 0;
if (!$table_exists) {
    $create_table = "
    CREATE TABLE `cart` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `product_id` int(11) NOT NULL,
      `quantity` int(11) NOT NULL DEFAULT 1,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY (`user_id`,`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->query($create_table);
}

// Check if product is already in cart - using prepared statement
$check_stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
$check_stmt->bind_param("ii", $user_id, $product_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$check_stmt->close();

if ($check_result && $check_result->num_rows > 0) {
    // Update quantity
    $cart_item = $check_result->fetch_assoc();
    $new_quantity = $cart_item['quantity'] + $quantity;
    $cart_id = $cart_item['id'];
    
    $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
    $update_stmt->bind_param("ii", $new_quantity, $cart_id);
    
    if (!$update_stmt->execute()) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update cart'
        ]);
        $update_stmt->close();
        exit;
    }
    $update_stmt->close();
} else {
    // Add new item to cart
    $insert_stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
    $insert_stmt->bind_param("iii", $user_id, $product_id, $quantity);
    
    if (!$insert_stmt->execute()) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to add to cart'
        ]);
        $insert_stmt->close();
        exit;
    }
    $insert_stmt->close();
}

// Get updated cart count
$count_stmt = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$cart_count = 0;

if ($count_result && $count_result->num_rows > 0) {
    $cart_count = (int)$count_result->fetch_assoc()['count'];
}
$count_stmt->close();

// Return success
echo json_encode([
    'success' => true,
    'message' => 'Product added to cart successfully',
    'cart_count' => $cart_count
]);
EOT;

    file_put_contents($add_to_cart_file, $cart_code);
}

// Include header
include 'includes/header.php';
?>

<!-- Custom CSS -->
<style>
/* Product Card Styles */
.product-card {
    border: 1px solid #e9ecef;
    border-radius: 0.25rem;
    transition: all 0.2s ease-in-out;
    height: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.product-image {
    width: 100%;
    height: 200px;
    overflow: hidden;
    background-color: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-image img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.product-info {
    padding: 15px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.product-title {
    font-size: 16px;
    margin-bottom: 10px;
    font-weight: 500;
    height: 45px;
    overflow: hidden;
}

.product-price {
    font-size: 18px;
    font-weight: bold;
    color: #dc3545;
    margin-bottom: 10px;
}

.product-actions {
    margin-top: auto;
}

.vendor-profile {
    background-color: #f8f9fa;
    border-radius: 0.5rem;
    padding: 25px;
    margin-bottom: 30px;
}

.vendor-logo {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 2px 15px rgba(0,0,0,0.1);
}

.vendor-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
}

.vendor-tab {
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s ease;
}

.vendor-tab.active {
    background-color: #007bff;
    color: white;
}

.vendor-tab-content {
    display: none;
}

.filter-card {
    margin-bottom: 20px;
    border: 1px solid #e9ecef;
    border-radius: 0.25rem;
    overflow: hidden;
}

.filter-header {
    background-color: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid #e9ecef;
    font-weight: 500;
}

.filter-body {
    padding: 15px;
}

.category-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.category-list li {
    padding: 8px 0;
    border-bottom: 1px solid #f8f9fa;
}

.category-list a {
    text-decoration: none;
    color: #495057;
}

.category-list a:hover {
    color: #007bff;
}

.rating-stars .fas {
    color: #ffc107;
}
.rating-stars .far {
    color: #e9ecef;
}

.btn-outline-primary {
    color: #007bff;
    border-color: #007bff;
}

.btn-outline-primary:hover {
    color: #fff;
    background-color: #007bff;
    border-color: #007bff;
}

.btn-outline-danger {
    color: #dc3545;
    border-color: #dc3545;
}

.btn-outline-danger:hover {
    color: #fff;
    background-color: #dc3545;
    border-color: #dc3545;
}

/* Fix for the toast container */
.toast-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1050;
}

/* Custom Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.product-card {
    animation: fadeIn 0.5s ease-in-out;
}

/* Product image fallback text */
.no-image-text {
    color: #6c757d;
    font-size: 14px;
    text-align: center;
    padding: 20px;
}
</style>

<div class="container py-4">
    <!-- User Welcome Banner with Static Clock (as requested) -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="row align-items-center">
                <div class="col">
                    <div class="fw-bold text-primary text-uppercase small">
                        Current User's Login: <?= htmlspecialchars($current_user) ?>
                    </div>
                    <div class="small text-muted">
                        Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 
                        <span id="live-datetime"><?= $current_datetime ?></span>
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-user-clock fa-2x text-secondary"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Display success/error messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if ($viewing_vendor && $vendor_details): ?>
        <!-- Vendor Profile -->
        <div class="vendor-profile">
            <div class="row">
                <div class="col-md-2 text-center mb-3 mb-md-0">
                    <?php if (!empty($vendor_details['logo'])): ?>
                        <img src="<?= SITE_URL ?>/assets/img/vendors/<?= $vendor_details['logo'] ?>" alt="<?= htmlspecialchars($vendor_details['shop_name']) ?>" class="vendor-logo">
                    <?php else: ?>
                        <div class="vendor-logo d-flex align-items-center justify-content-center bg-light">
                            <i class="fas fa-store fa-3x text-secondary"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-10">
                    <div class="vendor-details">
                        <h1 class="h3 mb-2"><?= htmlspecialchars($vendor_details['shop_name']) ?></h1>
                        
                        <div class="d-flex align-items-center mb-2 rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa<?= ($i <= round($vendor_avg_rating)) ? 's' : 'r' ?> fa-star"></i>
                            <?php endfor; ?>
                            <span class="ms-2"><?= $vendor_avg_rating ?> (<?= $vendor_review_count ?> reviews)</span>
                        </div>
                        
                        <?php if (!empty($vendor_details['location'])): ?>
                            <p class="mb-2"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($vendor_details['location']) ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($vendor_details['description'])): ?>
                            <p class="text-muted mb-3"><?= htmlspecialchars($vendor_details['description']) ?></p>
                        <?php endif; ?>
                        
                        <div class="d-flex mb-3">
                            <div class="me-3">
                                <i class="fas fa-cubes text-primary me-1"></i>
                                <span><?= $total_products ?> Products</span>
                            </div>
                            <?php if (!empty($vendor_details['joined_date'])): ?>
                                <div>
                                    <i class="fas fa-calendar-alt text-primary me-1"></i>
                                    <span>Joined <?= date('M Y', strtotime($vendor_details['joined_date'])) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Display vendor categories -->
                        <?php if (!empty($vendor_categories)): ?>
                            <div class="vendor-categories mb-2">
                                <h6 class="mb-2">Categories This Vendor Sells:</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($vendor_categories as $cat): ?>
                                        <a href="<?= SITE_URL ?>/shop.php?vendor=<?= $vendor_id ?>&category=<?= $cat['id'] ?>" 
                                           class="badge bg-primary text-decoration-none">
                                            <?= htmlspecialchars($cat['name']) ?> (<?= $cat['product_count'] ?>)
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Vendor Tabs -->
        <div class="vendor-tabs">
            <div class="vendor-tab active" id="tab-products" onclick="showVendorTab('products')">Products</div>
            <div class="vendor-tab" id="tab-reviews" onclick="showVendorTab('reviews')">Reviews</div>
            <div class="vendor-tab" id="tab-info" onclick="showVendorTab('info')">Information</div>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3">
            <!-- Categories Filter -->
            <div class="filter-card">
                <div class="filter-header">
                    <i class="fas fa-th-large me-2"></i> Categories
                </div>
                <div class="filter-body">
                    <ul class="category-list">
                        <li class="<?= $category_id === 0 ? 'fw-bold' : '' ?>">
                            <a href="<?= SITE_URL ?>/shop.php<?= $viewing_vendor ? "?vendor={$vendor_id}" : '' ?>">
                                All Categories
                            </a>
                        </li>
                        <?php foreach ($categories as $category): ?>
                            <li class="<?= $category_id === (int)$category['id'] ? 'fw-bold' : '' ?>">
                                <a href="<?= SITE_URL ?>/shop.php?<?= $viewing_vendor ? "vendor={$vendor_id}&" : '' ?>category=<?= $category['id'] ?>">
                                    <?= htmlspecialchars($category['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Price Filter -->
            <div class="filter-card">
                <div class="filter-header">
                    <i class="fas fa-dollar-sign me-2"></i> Price Range
                </div>
                <div class="filter-body">
                    <form action="<?= SITE_URL ?>/shop.php" method="get">
                        <?php if ($viewing_vendor): ?>
                            <input type="hidden" name="vendor" value="<?= $vendor_id ?>">
                        <?php endif; ?>
                        
                        <?php if ($category_id): ?>
                            <input type="hidden" name="category" value="<?= $category_id ?>">
                        <?php endif; ?>
                        
                        <?php if ($search): ?>
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <?php endif; ?>
                        
                        <?php if ($sort): ?>
                            <input type="hidden" name="sort" value="<?= $sort ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="min_price" class="form-label">Min Price:</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" id="min_price" name="min_price" value="<?= $min_price ?>" min="0">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="max_price" class="form-label">Max Price:</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" id="max_price" name="max_price" value="<?= $max_price ?>" min="0">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                    </form>
                </div>
            </div>
            
            <!-- Vendors Filter (only in normal shop view) -->
            <?php if (!$viewing_vendor && !empty($all_vendors)): ?>
                <div class="filter-card">
                    <div class="filter-header">
                        <i class="fas fa-store me-2"></i> Vendors
                    </div>
                    <div class="filter-body">
                        <ul class="category-list">
                            <?php foreach ($all_vendors as $vendor): ?>
                                <li>
                                    <a href="<?= SITE_URL ?>/shop.php?vendor=<?= $vendor['id'] ?>">
                                        <?= htmlspecialchars($vendor['shop_name']) ?>
                                        <span class="badge bg-secondary float-end"><?= $vendor['product_count'] ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Products Section -->
            <div id="vendor-products-tab" class="vendor-tab-content" style="display: block;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="h4 mb-1">
                            <?php if ($selected_category): ?>
                                <?= htmlspecialchars($selected_category['name']) ?>
                            <?php elseif ($viewing_vendor): ?>
                                <?= htmlspecialchars($vendor_details['shop_name']) ?>'s Products
                            <?php elseif ($search): ?>
                                Search Results for "<?= htmlspecialchars($search) ?>"
                            <?php else: ?>
                                All Products
                            <?php endif; ?>
                        </h2>
                        <p class="text-muted mb-0"><?= count($products) ?> of <?= $total_products ?> products</p>
                    </div>
                    
                    <div class="d-flex align-items-center">
                        <form method="get" action="<?= SITE_URL ?>/shop.php">
                            <?php if ($viewing_vendor): ?>
                                <input type="hidden" name="vendor" value="<?= $vendor_id ?>">
                            <?php endif; ?>
                            
                            <?php if ($category_id): ?>
                                <input type="hidden" name="category" value="<?= $category_id ?>">
                            <?php endif; ?>
                            
                            <?php if ($min_price): ?>
                                <input type="hidden" name="min_price" value="<?= $min_price ?>">
                            <?php endif; ?>
                            
                            <?php if ($max_price): ?>
                                <input type="hidden" name="max_price" value="<?= $max_price ?>">
                            <?php endif; ?>
                            
                            <select class="form-select" name="sort" onchange="this.form.submit()">
                                <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
                                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                                <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                                <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A-Z</option>
                                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name: Z-A</option>
                            </select>
                        </form>
                    </div>
                </div>

                <?php if (empty($products)): ?>
                    <!-- No products found -->
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-4x text-muted mb-3"></i>
                        <h3>No Products Found</h3>
                    </div>
                <?php else: ?>
                    <!-- Show products grid -->
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                        <?php foreach ($products as $product): ?>
                            <div class="col">
                                <div class="product-card">
                                    <div class="product-image">
                                        <!-- Improved image handling with fallback -->
                                        <?php if (!empty($product['image']) && file_exists(__DIR__ . '/assets/img/products/' . $product['image'])): ?>
                                            <img src="<?= SITE_URL ?>/assets/img/products/<?= $product['image'] ?>" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                                 onerror="this.outerHTML='<div class=\'no-image-text\'>No Image Available</div>'">
                                        <?php else: ?>
                                            <div class="no-image-text">
                                                <i class="fas fa-image fa-3x mb-2 text-muted"></i><br>
                                                No Image Available
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-info">
                                        <h3 class="product-title">
                                            <a href="<?= SITE_URL ?>/product.php?id=<?= $product['id'] ?>" class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($product['name']) ?>
                                            </a>
                                        </h3>
                                        
                                        <div class="product-price">
                                            <?php if (isset($product['sale_price']) && $product['sale_price'] > 0): ?>
                                                <span class="text-decoration-line-through text-muted me-2">
                                                    Rp <?= number_format($product['price'], 0, ',', '.') ?>
                                                </span>
                                                <span>
                                                    Rp <?= number_format($product['sale_price'], 0, ',', '.') ?>
                                                </span>
                                            <?php else: ?>
                                                <span>
                                                    Rp <?= number_format($product['price'], 0, ',', '.') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (!$viewing_vendor && !empty($product['vendor_name'])): ?>
                                            <div class="small text-muted mb-2">
                                                <i class="fas fa-store me-1"></i>
                                                <a href="<?= SITE_URL ?>/shop.php?vendor=<?= $product['vendor_id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($product['vendor_name']) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Vertically stacked buttons -->
                                        <div class="mt-2">
                                            <a href="<?= SITE_URL ?>/product.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-primary w-100 mb-2">
                                                <i class="fas fa-info-circle me-1"></i> View Details
                                            </a>
                                            
                                            <button type="button" class="btn btn-sm btn-outline-primary w-100 mb-2 add-to-cart" data-id="<?= $product['id'] ?>">
                                                <i class="fas fa-shopping-cart me-1"></i> Add to Cart
                                            </button>
                                            
                                            <form method="post" action="" class="w-100">
                                                <input type="hidden" name="wishlist_product_id" value="<?= $product['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger w-100" title="<?= in_array($product['id'], $wishlist_products) ? 'Remove from Wishlist' : 'Add to Wishlist' ?>">
                                                    <i class="<?= in_array($product['id'], $wishlist_products) ? 'fas' : 'far' ?> fa-heart me-1"></i> 
                                                    <?= in_array($product['id'], $wishlist_products) ? 'Remove from Wishlist' : 'Add to Wishlist' ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= SITE_URL ?>/shop.php?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= SITE_URL ?>/shop.php?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= SITE_URL ?>/shop.php?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($viewing_vendor): ?>
                <!-- Reviews Tab (initially hidden) -->
                <div id="vendor-reviews-tab" class="vendor-tab-content" style="display: none;">
                    <div class="row">
                        <div class="col-md-8">
                            <h2 class="h4 mb-4">Customer Reviews (<?= $vendor_review_count ?>)</h2>
                            
                            <?php if (empty($vendor_reviews)): ?>
                                <div class="alert alert-info">
                                    This vendor has no reviews yet. Be the first to write a review!
                                </div>
                            <?php else: ?>
                                <div class="reviews-container">
                                    <?php foreach ($vendor_reviews as $review): ?>
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <div class="fw-bold">
                                                        <?= htmlspecialchars($review['username']) ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <?= date('M d, Y', strtotime($review['created_at'])) ?>
                                                    </div>
                                                </div>
                                                <div class="rating-stars mb-2">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fa<?= ($i <= $review['rating']) ? 's' : 'r' ?> fa-star"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <div>
                                                    <?= nl2br(htmlspecialchars($review['comment'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Write a Review</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!$user_id): ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Please <a href="<?= SITE_URL ?>/login.php">login</a> to submit a review.
                                        </div>
                                    <?php else: ?>
                                        <form method="post" action="">
                                            <input type="hidden" name="vendor_id" value="<?= $vendor_id ?>">
                                            <input type="hidden" name="submit_vendor_review" value="1">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Your Rating:</label>
                                                <div class="rating-stars">
                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="rating" id="rating<?= $i ?>" value="<?= $i ?>" <?= ($user_vendor_review && $user_vendor_review['rating'] == $i) ? 'checked' : '' ?> required>
                                                            <label class="form-check-label" for="rating<?= $i ?>">
                                                                <?= $i ?>
                                                            </label>
                                                        </div>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="comment" class="form-label">Your Review:</label>
                                                <textarea class="form-control" id="comment" name="comment" rows="4" placeholder="Share your experience with this vendor..."><?= $user_vendor_review ? htmlspecialchars($user_vendor_review['comment']) : '' ?></textarea>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary w-100">
                                                <?= $user_vendor_review ? 'Update Review' : 'Submit Review' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Information Tab (initially hidden) -->
                <div id="vendor-info-tab" class="vendor-tab-content" style="display: none;">
                    <div class="row">
                        <div class="col-md-8">
                            <h2 class="h4 mb-4">About <?= htmlspecialchars($vendor_details['shop_name']) ?></h2>
                            
                            <div class="card mb-4">
                                <div class="card-body">
                                    <?php if (!empty($vendor_details['description'])): ?>
                                        <p><?= nl2br(htmlspecialchars($vendor_details['description'])) ?></p>
                                    <?php else: ?>
                                        <p class="text-muted">No detailed information available about this vendor.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <h3 class="h5 mb-3">Contact Information</h3>
                            <div class="card mb-4">
                                <ul class="list-group list-group-flush">
                                    <?php if (!empty($vendor_details['email'])): ?>
                                        <li class="list-group-item">
                                            <i class="fas fa-envelope text-primary me-2"></i>
                                            <?= htmlspecialchars($vendor_details['email']) ?>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($vendor_details['phone'])): ?>
                                        <li class="list-group-item">
                                            <i class="fas fa-phone text-primary me-2"></i>
                                            <?= htmlspecialchars($vendor_details['phone']) ?>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($vendor_details['location'])): ?>
                                        <li class="list-group-item">
                                            <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                            <?= htmlspecialchars($vendor_details['location']) ?>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($vendor_details['website'])): ?>
                                        <li class="list-group-item">
                                            <i class="fas fa-globe text-primary me-2"></i>
                                            <a href="<?= htmlspecialchars($vendor_details['website']) ?>" target="_blank">
                                                <?= htmlspecialchars($vendor_details['website']) ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Vendor Statistics</h5>
                                </div>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Products
                                        <span class="badge bg-primary rounded-pill"><?= $total_products ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Reviews
                                        <span class="badge bg-primary rounded-pill"><?= $vendor_review_count ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Average Rating
                                        <span class="badge bg-warning text-dark rounded-pill">
                                            <?= $vendor_avg_rating ?> <i class="fas fa-star"></i>
                                        </span>
                                    </li>
                                    <?php if (!empty($vendor_details['joined_date'])): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Joined
                                            <span><?= date('F d, Y', strtotime($vendor_details['joined_date'])) ?></span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            
                            <!-- Display categories the vendor sells in -->
                            <?php if (!empty($vendor_categories)): ?>
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Categories</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($vendor_categories as $cat): ?>
                                                <a href="<?= SITE_URL ?>/shop.php?vendor=<?= $vendor_id ?>&category=<?= $cat['id'] ?>" 
                                                   class="badge bg-primary py-2 px-3 text-decoration-none mb-2">
                                                    <?= htmlspecialchars($cat['name']) ?> (<?= $cat['product_count'] ?>)
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="loginModalLabel">Login Required</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Please login to add items to your cart or wishlist.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="<?= SITE_URL ?>/login.php" class="btn btn-primary">Login</a>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notifications -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050">
    <!-- Success Toast -->
    <div id="cartToast" class="toast align-items-center text-white bg-success" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle me-2"></i> Product added to cart!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
    
    <!-- Error Toast -->
    <div id="errorToast" class="toast align-items-center text-white bg-danger" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="errorToastMessage">
                <i class="fas fa-exclamation-circle me-2"></i> An error occurred.
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
// Define site URL for JavaScript to use in AJAX calls and image paths
const SITE_URL = "<?= SITE_URL ?>";

// Function to show vendor tabs
function showVendorTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.vendor-tab-content');
    tabContents.forEach(tab => {
        tab.style.display = 'none';
    });
    
    // Show the selected tab
    document.getElementById('vendor-' + tabName + '-tab').style.display = 'block';
    
    // Update tab buttons
    const tabButtons = document.querySelectorAll('.vendor-tab');
    tabButtons.forEach(button => {
        button.classList.remove('active');
    });
    
    document.getElementById('tab-' + tabName).classList.add('active');
}

document.addEventListener('DOMContentLoaded', function() {
    try {
        // Check if Bootstrap is loaded
        if (typeof bootstrap === 'undefined') {
            console.error('Bootstrap JavaScript is not loaded!');
            return;
        }
        
        // Initialize Bootstrap components
        let loginModal;
        let cartToast;
        
        try {
            loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
            cartToast = new bootstrap.Toast(document.getElementById('cartToast'));
        } catch(e) {
            console.error("Error initializing Bootstrap components:", e);
        }
        
        // Add to Cart functionality
        const addToCartButtons = document.querySelectorAll('.add-to-cart');
        
        if (addToCartButtons.length > 0) {
            addToCartButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const productId = this.getAttribute('data-id');
                    // Save original button text
                    const originalText = this.innerHTML;
                    
                    // Update button to show loading state
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                    this.disabled = true;
                    
                    // Add to cart via AJAX
                    fetch(`${SITE_URL}/ajax/add_to_cart.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `product_id=${productId}&quantity=1`
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`Server responded with status ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Reset button state
                        this.innerHTML = originalText;
                        this.disabled = false;
                        
                        if (data.success) {
                            // Show success message
                            cartToast.show();
                            
                            // Update cart count in header if it exists
                            const cartCountElement = document.querySelector('.cart-count');
                            if (cartCountElement) {
                                cartCountElement.textContent = data.cart_count || 0;
                            }
                        } else {
                            // Use toast for error instead of alert
                            const errorToast = document.getElementById('errorToast');
                            if (errorToast) {
                                document.getElementById('errorToastMessage').textContent = data.message || 'Failed to add product to cart.';
                                const bsErrorToast = new bootstrap.Toast(errorToast);
                                bsErrorToast.show();
                            } else {
                                console.error('Error adding to cart:', data.message);
                                alert(data.message || 'Failed to add product to cart.');
                            }
                        }
                    })
                    .catch(error => {
                        // Reset button state
                        this.innerHTML = originalText;
                        this.disabled = false;
                        
                        console.error('Error adding to cart:', error);
                        
                        // Use toast for error instead of alert
                        const errorToast = document.getElementById('errorToast');
                        if (errorToast) {
                            document.getElementById('errorToastMessage').textContent = 'Failed to add product to cart.';
                            const bsErrorToast = new bootstrap.Toast(errorToast);
                            bsErrorToast.show();
                        } else {
                            alert('An error occurred while adding the product to cart: ' + error.message);
                        }
                    });
                });
            });
        }
        
        // Fixed datetime display (don't update it)
        const datetimeElement = document.getElementById('live-datetime');
        if (datetimeElement) {
            datetimeElement.textContent = "<?= $current_datetime ?>";
        }
        
    } catch (error) {
        console.error('Error initializing shop features:', error);
    }
});
</script>

<?php include 'includes/footer.php'; ?>