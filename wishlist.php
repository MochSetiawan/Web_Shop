<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = 'My Wishlist';

// Get current user info for display
$current_user = $_SESSION['username'] ?? 'Guest';

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!$user_id) {
    $_SESSION['error_message'] = "Please log in to view your wishlist.";
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Check if wishlist_items table exists and create it if needed
function ensure_wishlist_table_exists($conn) {
    $wishlist_table_exists = $conn->query("SHOW TABLES LIKE 'wishlist_items'")->num_rows > 0;
    if (!$wishlist_table_exists) {
        $create_wishlist_table = "
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
        $conn->query($create_wishlist_table);
    }
    return $wishlist_table_exists || $conn->query("SHOW TABLES LIKE 'wishlist_items'")->num_rows > 0;
}

// Ensure wishlist table exists
$wishlist_table_exists = ensure_wishlist_table_exists($conn);

// Process wishlist actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Remove item
    if (isset($_POST['remove_item'])) {
        $wishlist_id = (int)$_POST['remove_item'];
        $conn->query("DELETE FROM wishlist_items WHERE id = $wishlist_id AND user_id = $user_id");
        
        $_SESSION['success_message'] = "Item removed from wishlist!";
        header('Location: ' . SITE_URL . '/wishlist.php');
        exit;
    }
    
    // Clear wishlist
    if (isset($_POST['clear_wishlist'])) {
        $conn->query("DELETE FROM wishlist_items WHERE user_id = $user_id");
        
        $_SESSION['success_message'] = "Wishlist cleared successfully!";
        header('Location: ' . SITE_URL . '/wishlist.php');
        exit;
    }
    
    // Add to cart
    if (isset($_POST['add_to_cart'])) {
        $wishlist_id = (int)$_POST['add_to_cart'];
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        
        // Get product ID from wishlist
        $wishlist_query = "SELECT product_id FROM wishlist_items WHERE id = $wishlist_id AND user_id = $user_id";
        $wishlist_result = $conn->query($wishlist_query);
        
        if ($wishlist_result && $wishlist_result->num_rows > 0) {
            $product_id = $wishlist_result->fetch_assoc()['product_id'];
            
            // Check if cart_items table exists
            $cart_table_exists = $conn->query("SHOW TABLES LIKE 'cart_items'")->num_rows > 0;
            
            if (!$cart_table_exists) {
                // Create cart_items table
                $create_cart_table = "
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
                $conn->query($create_cart_table);
            }
            
            // Check if already in cart
            $cart_check = "SELECT id, quantity FROM cart_items WHERE user_id = $user_id AND product_id = $product_id";
            $cart_result = $conn->query($cart_check);
            
            if ($cart_result && $cart_result->num_rows > 0) {
                // Update quantity
                $cart_item = $cart_result->fetch_assoc();
                $new_quantity = $cart_item['quantity'] + $quantity;
                $conn->query("UPDATE cart_items SET quantity = $new_quantity, updated_at = NOW() WHERE id = {$cart_item['id']}");
            } else {
                // Add to cart
                $conn->query("INSERT INTO cart_items (user_id, product_id, quantity, created_at) VALUES ($user_id, $product_id, $quantity, NOW())");
            }
            
            // Remove from wishlist if requested
            if (isset($_POST['remove_after_add']) && $_POST['remove_after_add'] == 1) {
                $conn->query("DELETE FROM wishlist_items WHERE id = $wishlist_id AND user_id = $user_id");
            }
            
            $_SESSION['success_message'] = "Item added to cart!";
            header('Location: ' . SITE_URL . '/cart.php');
            exit;
        } else {
            $_SESSION['error_message'] = "Item not found in wishlist.";
            header('Location: ' . SITE_URL . '/wishlist.php');
            exit;
        }
    }
}

// Get wishlist items for the current user
$wishlist_items = [];

// Check if product_images table exists
$product_images_exist = $conn->query("SHOW TABLES LIKE 'product_images'")->num_rows > 0;

// Check if products table has image column
$products_has_image = false;
$product_columns_result = $conn->query("SHOW COLUMNS FROM products");
if ($product_columns_result) {
    while ($column = $product_columns_result->fetch_assoc()) {
        if ($column['Field'] == 'image') {
            $products_has_image = true;
            break;
        }
    }
}

if ($user_id && $wishlist_table_exists) {
    // Build the query based on available columns and tables
    $wishlist_query = "SELECT wi.id, wi.product_id, wi.created_at, 
                   p.name, p.price";
    
    if ($conn->query("SHOW COLUMNS FROM products LIKE 'sale_price'")->num_rows > 0) {
        $wishlist_query .= ", p.sale_price";
    } else {
        $wishlist_query .= ", NULL as sale_price";
    }
    
    if ($conn->query("SHOW COLUMNS FROM products LIKE 'sku'")->num_rows > 0) {
        $wishlist_query .= ", p.sku";
    } else {
        $wishlist_query .= ", NULL as sku";
    }
    
    if ($conn->query("SHOW COLUMNS FROM products LIKE 'slug'")->num_rows > 0) {
        $wishlist_query .= ", p.slug";
    } else {
        $wishlist_query .= ", NULL as slug";
    }
    
    // Add image selection based on available tables/columns
    if ($products_has_image) {
        $wishlist_query .= ", p.image";
    } else {
        $wishlist_query .= ", NULL as image";
    }
    
    // Add product_images subquery if the table exists
    if ($product_images_exist) {
        // First check which columns exist in product_images
        $pi_columns_result = $conn->query("SHOW COLUMNS FROM product_images");
        $has_is_primary = false;
        $has_is_main = false;
        $image_column = 'image';
        
        if ($pi_columns_result) {
            while ($column = $pi_columns_result->fetch_assoc()) {
                if ($column['Field'] == 'is_primary') {
                    $has_is_primary = true;
                }
                if ($column['Field'] == 'is_main') {
                    $has_is_main = true;
                }
                if (in_array($column['Field'], ['image', 'image_path', 'path', 'filename'])) {
                    $image_column = $column['Field'];
                }
            }
        }
        
        // Build the condition for primary image
        $primary_condition = "";
        if ($has_is_primary) {
            $primary_condition .= "pi.is_primary = 1";
        }
        if ($has_is_main) {
            $primary_condition .= $primary_condition ? " OR pi.is_main = 1" : "pi.is_main = 1";
        }
        if (!$primary_condition) {
            $primary_condition = "1=1"; // Always true if no primary flag
        }
        
        $wishlist_query .= ", (SELECT pi.$image_column FROM product_images pi WHERE pi.product_id = p.id AND ($primary_condition) LIMIT 1) as image_from_table";
    } else {
        $wishlist_query .= ", NULL as image_from_table";
    }
    
    // Complete the query
    $wishlist_query .= " FROM wishlist_items wi
                   JOIN products p ON wi.product_id = p.id
                   WHERE wi.user_id = $user_id
                   ORDER BY wi.created_at DESC";
    
    $wishlist_result = $conn->query($wishlist_query);
    
    if ($wishlist_result && $wishlist_result->num_rows > 0) {
        while ($item = $wishlist_result->fetch_assoc()) {
            // Use either the image from product_images or from products table
            $item['image'] = !empty($item['image_from_table']) ? $item['image_from_table'] : $item['image'];
            
            $wishlist_items[] = $item;
        }
    }
}

// Add extra CSS for wishlist
$extraCSS = '
<style>
.wishlist-item {
    transition: all 0.3s ease;
}
.wishlist-item:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.wishlist-item .card-img-top {
    height: 200px;
    object-fit: contain;
    padding: 15px;
}
.wishlist-actions {
    display: flex;
    gap: 10px;
}
.wishlist-price .old-price {
    text-decoration: line-through;
    color: #6c757d;
    font-size: 0.9rem;
}
.wishlist-price .current-price {
    font-weight: bold;
    color: #dc3545;
}
</style>
';

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
                        Current Date and Time (Indonesia): 
                        <span id="live-datetime">2025-03-22 12:31:11</span>
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-user-clock fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>My Wishlist</h1>
        <?php if (!empty($wishlist_items)): ?>
            <form method="post" action="">
                <button type="submit" name="clear_wishlist" class="btn btn-outline-danger" 
                        onclick="return confirm('Are you sure you want to clear your wishlist?')">
                    <i class="fas fa-trash-alt mr-1"></i> Clear Wishlist
                </button>
            </form>
        <?php endif; ?>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?= $_SESSION['success_message'] ?>
            <?php unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?= $_SESSION['error_message'] ?>
            <?php unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($wishlist_items)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body text-center py-5">
                <i class="far fa-heart fa-4x text-muted mb-3"></i>
                <h3>Your wishlist is empty</h3>
                <p class="text-muted">Looks like you haven't added anything to your wishlist yet.</p>
                <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary">Explore Products</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($wishlist_items as $item): ?>
                <div class="col-md-4 col-sm-6 mb-4">
                    <div class="card wishlist-item h-100">
                        <a href="<?= SITE_URL ?>/product.php?<?= isset($item['slug']) ? 'slug=' . $item['slug'] : 'id=' . $item['product_id'] ?>">
                            <?php if (!empty($item['image'])): ?>
                                <img src="<?= SITE_URL ?>/assets/img/products/<?= $item['image'] ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="card-img-top">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-image text-muted fa-3x"></i>
                                </div>
                            <?php endif; ?>
                        </a>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">
                                <a href="<?= SITE_URL ?>/product.php?<?= isset($item['slug']) ? 'slug=' . $item['slug'] : 'id=' . $item['product_id'] ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($item['name']) ?>
                                </a>
                            </h5>
                            <div class="wishlist-price mb-3">
                                <?php if (!empty($item['sale_price'])): ?>
                                    <span class="old-price d-block">Rp <?= number_format($item['price'], 0, ',', '.') ?></span>
                                    <span class="current-price">Rp <?= number_format($item['sale_price'], 0, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="current-price">Rp <?= number_format($item['price'], 0, ',', '.') ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-auto">
                                <form method="post" action="" class="wishlist-actions">
                                    <button type="submit" name="add_to_cart" value="<?= $item['id'] ?>" class="btn btn-primary">
                                        <i class="fas fa-shopping-cart mr-1"></i> Add to Cart
                                    </button>
                                    <button type="submit" name="remove_item" value="<?= $item['id'] ?>" class="btn btn-outline-danger">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="card-footer text-muted small">
                            Added on <?= date('M d, Y', strtotime($item['created_at'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Function to format date as YYYY-MM-DD HH:MM:SS
function formatDateTime(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

// Update the datetime display
function updateDateTime() {
    const now = new Date();
    document.getElementById('live-datetime').textContent = formatDateTime(now);
}

// Run immediately and then update every second
updateDateTime();
setInterval(updateDateTime, 1000);
</script>

<?php include 'includes/footer.php'; ?>