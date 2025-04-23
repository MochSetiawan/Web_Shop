<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Shopping Cart';

// Get current user info for display
$current_user = $_SESSION['username'] ?? 'MochSetiawan';
$current_datetime = date('Y-m-d H:i:s');

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!$user_id) {
    $_SESSION['error_message'] = "Please log in to view your cart.";
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Ensure Voucher table exists
function ensure_voucher_table_exists($conn) {
    $voucher_table_exists = $conn->query("SHOW TABLES LIKE 'vouchers'")->num_rows > 0;
    if (!$voucher_table_exists) {
        $create_voucher_table = "
        CREATE TABLE IF NOT EXISTS `vouchers` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `code` varchar(50) NOT NULL UNIQUE,
          `discount_type` ENUM('percentage', 'fixed') NOT NULL,
          `discount_value` decimal(10,2) NOT NULL,
          `min_purchase` decimal(10,2) DEFAULT 0,
          `max_usage` int(11) DEFAULT NULL,
          `usage_count` int(11) DEFAULT 0,
          `start_date` datetime NOT NULL,
          `end_date` datetime NOT NULL,
          `is_active` tinyint(1) DEFAULT 1,
          `created_by` int(11) NOT NULL,
          `description` text DEFAULT NULL,
          `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->query($create_voucher_table);
    }
    return $voucher_table_exists || $conn->query("SHOW TABLES LIKE 'vouchers'")->num_rows > 0;
}

// Check if cart_items table exists and create it if needed
function ensure_cart_table_exists($conn) {
    $cart_table_exists = $conn->query("SHOW TABLES LIKE 'cart_items'")->num_rows > 0;
    if (!$cart_table_exists) {
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
    return $cart_table_exists || $conn->query("SHOW TABLES LIKE 'cart_items'")->num_rows > 0;
}

// Ensure order table has voucher columns
function ensure_orders_table_has_voucher_columns($conn) {
    $orders_table_exists = $conn->query("SHOW TABLES LIKE 'orders'")->num_rows > 0;
    
    if ($orders_table_exists) {
        // Check if voucher_id column exists
        $voucher_id_exists = $conn->query("SHOW COLUMNS FROM orders LIKE 'voucher_id'")->num_rows > 0;
        $discount_amount_exists = $conn->query("SHOW COLUMNS FROM orders LIKE 'discount_amount'")->num_rows > 0;
        $final_amount_exists = $conn->query("SHOW COLUMNS FROM orders LIKE 'final_amount'")->num_rows > 0;
        
        // Add voucher columns if they don't exist
        if (!$voucher_id_exists) {
            $conn->query("ALTER TABLE orders ADD COLUMN voucher_id INT NULL");
        }
        
        if (!$discount_amount_exists) {
            $conn->query("ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0");
        }
        
        if (!$final_amount_exists) {
            $conn->query("ALTER TABLE orders ADD COLUMN final_amount DECIMAL(10,2) DEFAULT 0");
        }
    }
}

// Ensure tables exist
$cart_table_exists = ensure_cart_table_exists($conn);
$voucher_table_exists = ensure_voucher_table_exists($conn);
ensure_orders_table_has_voucher_columns($conn);

// Process cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update quantity
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['quantity'] as $cart_id => $quantity) {
            $cart_id = (int)$cart_id;
            $quantity = (int)$quantity;
            
            if ($quantity <= 0) {
                // Remove item if quantity is 0 or negative
                $conn->query("DELETE FROM cart_items WHERE id = $cart_id AND user_id = $user_id");
            } else {
                // Update quantity
                $conn->query("UPDATE cart_items SET quantity = $quantity, updated_at = NOW() WHERE id = $cart_id AND user_id = $user_id");
            }
        }
        
        $_SESSION['success_message'] = "Cart updated successfully!";
        header('Location: ' . SITE_URL . '/cart.php');
        exit;
    }
    
    // Remove item
    if (isset($_POST['remove_item'])) {
        $cart_id = (int)$_POST['remove_item'];
        $conn->query("DELETE FROM cart_items WHERE id = $cart_id AND user_id = $user_id");
        
        $_SESSION['success_message'] = "Item removed from cart!";
        header('Location: ' . SITE_URL . '/cart.php');
        exit;
    }
    
    // Clear cart
    if (isset($_POST['clear_cart'])) {
        $conn->query("DELETE FROM cart_items WHERE user_id = $user_id");
        
        $_SESSION['success_message'] = "Cart cleared successfully!";
        header('Location: ' . SITE_URL . '/cart.php');
        exit;
    }
    
    // Move to wishlist
    if (isset($_POST['move_to_wishlist'])) {
        $cart_id = (int)$_POST['move_to_wishlist'];
        
        // Get product ID from cart
        $cart_query = "SELECT product_id FROM cart_items WHERE id = $cart_id AND user_id = $user_id";
        $cart_result = $conn->query($cart_query);
        
        if ($cart_result && $cart_result->num_rows > 0) {
            $product_id = $cart_result->fetch_assoc()['product_id'];
            
            // Check if wishlist_items table exists
            $wishlist_table_exists = $conn->query("SHOW TABLES LIKE 'wishlist_items'")->num_rows > 0;
            
            if (!$wishlist_table_exists) {
                // Create wishlist_items table
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
            
            // Check if already in wishlist
            $wishlist_check = "SELECT id FROM wishlist_items WHERE user_id = $user_id AND product_id = $product_id";
            $wishlist_result = $conn->query($wishlist_check);
            
            if ($wishlist_result && $wishlist_result->num_rows == 0) {
                // Add to wishlist
                $conn->query("INSERT INTO wishlist_items (user_id, product_id, created_at) VALUES ($user_id, $product_id, NOW())");
            }
            
            // Remove from cart
            $conn->query("DELETE FROM cart_items WHERE id = $cart_id AND user_id = $user_id");
            
            $_SESSION['success_message'] = "Item moved to wishlist!";
        } else {
            $_SESSION['error_message'] = "Item not found in cart.";
        }
        
        header('Location: ' . SITE_URL . '/cart.php');
        exit;
    }
    
    // Apply voucher
    if (isset($_POST['apply_voucher'])) {
        $voucher_code = trim($_POST['voucher_code']);
        
        if (empty($voucher_code)) {
            $_SESSION['error_message'] = "Please enter a voucher code.";
        } else {
            // We'll validate the voucher later when we have the cart total
            $_SESSION['temp_voucher_code'] = $voucher_code;
        }
        
        header('Location: ' . SITE_URL . '/cart.php');
        exit;
    }
    
    // Remove voucher
    if (isset($_POST['remove_voucher'])) {
        unset($_SESSION['voucher']);
        $_SESSION['success_message'] = "Voucher removed successfully.";
        
        header('Location: ' . SITE_URL . '/cart.php');
        exit;
    }
}

// Get cart items for the current user
$cart_items = [];
$subtotal = 0;
$total_items = 0;

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

if ($user_id && $cart_table_exists) {
    // Build the query based on available columns and tables
    $cart_query = "SELECT ci.id, ci.product_id, ci.quantity, ci.created_at, 
                   p.name, p.price";
    
    if ($conn->query("SHOW COLUMNS FROM products LIKE 'sale_price'")->num_rows > 0) {
        $cart_query .= ", p.sale_price";
    } else {
        $cart_query .= ", NULL as sale_price";
    }
    
    if ($conn->query("SHOW COLUMNS FROM products LIKE 'sku'")->num_rows > 0) {
        $cart_query .= ", p.sku";
    } else {
        $cart_query .= ", NULL as sku";
    }
    
    if ($conn->query("SHOW COLUMNS FROM products LIKE 'slug'")->num_rows > 0) {
        $cart_query .= ", p.slug";
    } else {
        $cart_query .= ", NULL as slug";
    }
    
    // Add image selection based on available tables/columns
    if ($products_has_image) {
        $cart_query .= ", p.image";
    } else {
        $cart_query .= ", NULL as image";
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
        
        $cart_query .= ", (SELECT pi.$image_column FROM product_images pi WHERE pi.product_id = p.id AND ($primary_condition) LIMIT 1) as image_from_table";
    } else {
        $cart_query .= ", NULL as image_from_table";
    }
    
    // Complete the query
    $cart_query .= " FROM cart_items ci
                   JOIN products p ON ci.product_id = p.id
                   WHERE ci.user_id = $user_id
                   ORDER BY ci.created_at DESC";
    
    $cart_result = $conn->query($cart_query);
    
    if ($cart_result && $cart_result->num_rows > 0) {
        while ($item = $cart_result->fetch_assoc()) {
            // Use either the image from product_images or from products table
            $item['image'] = !empty($item['image_from_table']) ? $item['image_from_table'] : $item['image'];
            
            $item_price = !empty($item['sale_price']) ? $item['sale_price'] : $item['price'];
            $item['item_price'] = $item_price;
            $item['item_total'] = $item_price * $item['quantity'];
            
            $cart_items[] = $item;
            $subtotal += $item['item_total'];
            $total_items += $item['quantity'];
        }
    }
}

// Calculate taxes and shipping (simplified)
$tax_rate = 0.1; // 10% tax rate
$tax_amount = $subtotal * $tax_rate;
$shipping_fee = $subtotal > 0 ? 10000 : 0; // Rp 10,000 flat shipping fee

// Initialize voucher variables
$voucher_code = '';
$voucher_error = '';
$voucher_success = '';
$voucher_data = null;
$discount_amount = 0;

// Process voucher if cart is not empty
if ($subtotal > 0) {
    if (isset($_SESSION['voucher'])) {
        // Use existing voucher from session
        $voucher_data = $_SESSION['voucher'];
        $voucher_code = $voucher_data['code'];
        
        // Revalidate with current cart total
        $validation_result = validateVoucher($voucher_code, $subtotal, $user_id);
        
        if (!$validation_result['valid']) {
            // Voucher no longer valid with current cart
            $voucher_error = $validation_result['message'];
            unset($_SESSION['voucher']);
            $voucher_data = null;
        } else {
            // Update discount amount based on current cart
            $voucher_data = $validation_result;
            $discount_amount = $validation_result['discount_amount'];
            $voucher_success = $validation_result['message'];
            $_SESSION['voucher'] = $validation_result;
        }
    } elseif (isset($_SESSION['temp_voucher_code'])) {
        // Validate the voucher that was just submitted
        $voucher_code = $_SESSION['temp_voucher_code'];
        unset($_SESSION['temp_voucher_code']);
        
        $validation_result = validateVoucher($voucher_code, $subtotal, $user_id);
        
        if ($validation_result['valid']) {
            // Valid voucher
            $voucher_data = $validation_result;
            $discount_amount = $validation_result['discount_amount'];
            $voucher_success = $validation_result['message'];
            $_SESSION['voucher'] = $validation_result;
        } else {
            // Invalid voucher
            $voucher_error = $validation_result['message'];
        }
    }
}

// Calculate final total after discount
$final_total = $subtotal + $tax_amount + $shipping_fee - $discount_amount;
if ($final_total < 0) $final_total = 0;

// Get recent orders for order history section
$recent_orders_sql = "SELECT id, order_number, status, created_at, total_amount FROM orders WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 3";
$recent_orders_result = $conn->query($recent_orders_sql);
$recent_orders = [];

if ($recent_orders_result && $recent_orders_result->num_rows > 0) {
    while ($row = $recent_orders_result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
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
                        Current Date and Time (Indonesia): 
                        <span id="live-datetime"><?= $current_datetime ?></span>
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-user-clock fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Breadcrumb Navigation with Order History Link -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>">Home</a></li>
            <li class="breadcrumb-item active">Shopping Cart</li>
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/orders.php">My Orders</a></li>
        </ol>
    </nav>
    
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
    
    <?php if (empty($cart_items)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body text-center py-5">
                <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                <h3>Your cart is empty</h3>
                <p class="text-muted">Looks like you haven't added anything to your cart yet.</p>
                <div class="mt-4">
                    <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary mr-2">
                        <i class="fas fa-shopping-bag mr-1"></i> Continue Shopping
                    </a>
                    <a href="<?= SITE_URL ?>/orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-list-alt mr-1"></i> View My Orders
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Cart Items (<?= $total_items ?>)</h5>
                            <form method="post" action="">
                                <button type="submit" name="clear_cart" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to clear your cart?')">
                                    <i class="fas fa-trash-alt mr-1"></i> Clear Cart
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="table-responsive">
                                <table class="table table-borderless">
                                    <thead class="thead-light">
                                        <tr>
                                            <th scope="col">Product</th>
                                            <th scope="col">Price</th>
                                            <th scope="col" width="150">Quantity</th>
                                            <th scope="col" class="text-right">Total</th>
                                            <th scope="col" width="100">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cart_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="cart-item-image mr-3">
                                                            <?php if (!empty($item['image'])): ?>
                                                                <img src="<?= SITE_URL ?>/assets/img/products/<?= $item['image'] ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="img-fluid" style="max-width: 80px; max-height: 80px;">
                                                            <?php else: ?>
                                                                <div class="bg-light d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                                                    <i class="fas fa-image text-muted fa-2x"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                                                            <?php if (!empty($item['sku'])): ?>
                                                                <small class="text-muted">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($item['sale_price'])): ?>
                                                        <span class="text-danger">Rp <?= number_format($item['sale_price'], 0, ',', '.') ?></span>
                                                        <small class="text-muted d-block"><del>Rp <?= number_format($item['price'], 0, ',', '.') ?></del></small>
                                                    <?php else: ?>
                                                        <span>Rp <?= number_format($item['price'], 0, ',', '.') ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <div class="input-group-prepend">
                                                            <button type="button" class="btn btn-outline-secondary quantity-btn" data-action="decrease" data-input="quantity-<?= $item['id'] ?>">
                                                                <i class="fas fa-minus"></i>
                                                            </button>
                                                        </div>
                                                        <input type="number" class="form-control text-center" name="quantity[<?= $item['id'] ?>]" id="quantity-<?= $item['id'] ?>" value="<?= $item['quantity'] ?>" min="1">
                                                        <div class="input-group-append">
                                                            <button type="button" class="btn btn-outline-secondary quantity-btn" data-action="increase" data-input="quantity-<?= $item['id'] ?>">
                                                                <i class="fas fa-plus"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-right">Rp <?= number_format($item['item_total'], 0, ',', '.') ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="submit" class="btn btn-outline-danger" name="remove_item" value="<?= $item['id'] ?>" title="Remove">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                        <button type="submit" class="btn btn-outline-primary" name="move_to_wishlist" value="<?= $item['id'] ?>" title="Move to Wishlist">
                                                            <i class="fas fa-heart"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="<?= SITE_URL ?>/shop.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left mr-1"></i> Continue Shopping
                                </a>
                                <button type="submit" name="update_cart" class="btn btn-primary">
                                    <i class="fas fa-sync-alt mr-1"></i> Update Cart
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Voucher Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Voucher Discount</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$voucher_data): ?>
                            <form method="post" action="">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="voucher_code" placeholder="Enter voucher code" value="<?= htmlspecialchars($voucher_code) ?>">
                                    <div class="input-group-append">
                                        <button type="submit" name="apply_voucher" class="btn btn-primary">Apply Voucher</button>
                                    </div>
                                </div>
                                <?php if ($voucher_error): ?>
                                    <div class="text-danger mt-2"><small><?= $voucher_error ?></small></div>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-success d-flex justify-content-between align-items-center mb-0">
                                <div>
                                    <strong>Voucher Applied: <?= htmlspecialchars($voucher_data['code']) ?></strong>
                                    <p class="mb-0">
                                        <?php if ($voucher_data['discount_type'] === 'percentage'): ?>
                                            <?= $voucher_data['discount_value'] ?>% discount (Rp <?= number_format($discount_amount, 0, ',', '.') ?>)
                                        <?php else: ?>
                                            Discount: Rp <?= number_format($discount_amount, 0, ',', '.') ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <form method="post" action="">
                                    <button type="submit" name="remove_voucher" class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Order Summary Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Subtotal</span>
                            <span>Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                        </div>
                        
                        <?php if ($discount_amount > 0): ?>
                            <div class="d-flex justify-content-between mb-3 text-success">
                                <span>Discount</span>
                                <span>- Rp <?= number_format($discount_amount, 0, ',', '.') ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span>Tax (10%)</span>
                            <span>Rp <?= number_format($tax_amount, 0, ',', '.') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Shipping</span>
                            <span>Rp <?= number_format($shipping_fee, 0, ',', '.') ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total</strong>
                            <strong>Rp <?= number_format($final_total, 0, ',', '.') ?></strong>
                        </div>
                        
                        <a href="<?= SITE_URL ?>/checkout.php" class="btn btn-success btn-block">
                            <i class="fas fa-shopping-basket mr-1"></i> Proceed to Checkout
                        </a>
                        
                        <?php if ($voucher_success): ?>
                            <div class="alert alert-success mt-3 mb-0 py-2 small">
                                <i class="fas fa-check-circle mr-1"></i> <?= $voucher_success ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Order History Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">My Orders</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Track your recent orders or view your order history</p>
                        
                        <?php if (empty($recent_orders)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-shopping-bag text-muted fa-2x mb-2"></i>
                                <p class="small text-muted mb-2">You haven't placed any orders yet.</p>
                                <a href="<?= SITE_URL ?>/shop.php" class="btn btn-sm btn-outline-primary">Start Shopping</a>
                            </div>
                        <?php else: ?>
                            <div class="list-group small mb-3">
                                <?php foreach ($recent_orders as $order): ?>
                                    <a href="<?= SITE_URL ?>/order_detail.php?id=<?= $order['id'] ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="fas fa-receipt text-primary mr-2"></i>
                                                #<?= $order['order_number'] ?>
                                            </span>
                                            <span>
                                                <?php
                                                switch ($order['status']) {
                                                    case 'pending': echo '<span class="badge bg-warning">Pending</span>'; break;
                                                    case 'processing': echo '<span class="badge bg-info">Processing</span>'; break;
                                                    case 'shipped': echo '<span class="badge bg-primary">Shipped</span>'; break;
                                                    case 'delivered': echo '<span class="badge bg-success">Delivered</span>'; break;
                                                    case 'cancelled': echo '<span class="badge bg-danger">Cancelled</span>'; break;
                                                    default: echo '<span class="badge bg-secondary">'.ucfirst($order['status']).'</span>';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-1">
                                            <small class="text-muted"><?= date('d M Y', strtotime($order['created_at'])) ?></small>
                                            <span class="font-weight-bold">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            
                            <a href="<?= SITE_URL ?>/orders.php" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-list-alt mr-1"></i> View All Orders
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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

document.addEventListener('DOMContentLoaded', function() {
    // Quantity increase/decrease buttons
    const quantityBtns = document.querySelectorAll('.quantity-btn');
    
    quantityBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const action = this.getAttribute('data-action');
            const inputId = this.getAttribute('data-input');
            const input = document.getElementById(inputId);
            let value = parseInt(input.value, 10);
            
            if (action === 'decrease') {
                value = Math.max(1, value - 1);
            } else if (action === 'increase') {
                value += 1;
            }
            
            input.value = value;
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>