<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check vendor authentication
if (!isLoggedIn() || $_SESSION['role'] !== 'vendor') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pageTitle = 'Vendor Dashboard';
$currentPage = 'dashboard';

// Initialize vendor stats
$stats = [
    'products' => 0,
    'orders' => 0,
    'sales' => 0
];

// Debug database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get vendor ID from users table
$vendor_data_sql = "SELECT v.id AS vendor_id, v.shop_name, v.balance 
                    FROM vendors v 
                    JOIN users u ON v.user_id = u.id 
                    WHERE u.id = $user_id";
$vendor_result = $conn->query($vendor_data_sql);

if (!$vendor_result || $vendor_result->num_rows == 0) {
    // Create vendor record if it doesn't exist
    $conn->query("INSERT INTO vendors (user_id, shop_name, status) VALUES 
                 ($user_id, 'My Shop', 'active')");
    
    // Get the newly created vendor ID
    $vendor_result = $conn->query($vendor_data_sql);
}

$vendor_data = $vendor_result->fetch_assoc();
$vendor_id = $vendor_data['vendor_id'];
$vendor_balance = $vendor_data['balance'] ?? 0;

// Force refresh database connection to get fresh data
$conn->query("SELECT 1");

// --- COUNT PRODUCTS ---
$products_sql = "SELECT COUNT(*) as count FROM products WHERE vendor_id = $vendor_id";
$products_result = $conn->query($products_sql);
if ($products_result && $products_result->num_rows > 0) {
    $row = $products_result->fetch_assoc();
    $stats['products'] = $row['count'];
}

// --- GET RECENT PRODUCTS ---
$recent_products = [];
$recent_products_sql = "SELECT p.*, c.name as category_name 
                      FROM products p 
                      LEFT JOIN categories c ON p.category_id = c.id
                      WHERE p.vendor_id = $vendor_id 
                      ORDER BY p.created_at DESC LIMIT 5";
$recent_products_result = $conn->query($recent_products_sql);
if ($recent_products_result && $recent_products_result->num_rows > 0) {
    while ($row = $recent_products_result->fetch_assoc()) {
        $recent_products[] = $row;
    }
}

// --- COUNT ORDERS ---
$orders_sql = "SELECT COUNT(DISTINCT o.id) as count
              FROM orders o
              JOIN order_items oi ON o.id = oi.order_id
              JOIN products p ON oi.product_id = p.id
              WHERE p.vendor_id = $vendor_id";
$orders_result = $conn->query($orders_sql);
if ($orders_result && $orders_result->num_rows > 0) {
    $row = $orders_result->fetch_assoc();
    $stats['orders'] = $row['count'];
}

// --- CALCULATE SALES ---
$sales_sql = "SELECT SUM(oi.price * oi.quantity) as total
             FROM order_items oi
             JOIN products p ON oi.product_id = p.id
             WHERE p.vendor_id = $vendor_id
             AND oi.status IN ('shipped', 'delivered')";
$sales_result = $conn->query($sales_sql);
if ($sales_result && $sales_result->num_rows > 0) {
    $row = $sales_result->fetch_assoc();
    $stats['sales'] = $row['total'] ?: 0;
}

// --- GET RECENT ORDERS ---
$recent_orders = [];
$recent_orders_sql = "SELECT o.id, o.order_number, o.total_amount, o.status, o.payment_status, o.created_at, o.updated_at,
                     u.username as customer_name
                     FROM orders o
                     JOIN users u ON o.user_id = u.id
                     JOIN order_items oi ON o.id = oi.order_id
                     JOIN products p ON oi.product_id = p.id
                     WHERE p.vendor_id = $vendor_id
                     GROUP BY o.id
                     ORDER BY o.created_at DESC
                     LIMIT 5";
$recent_orders_result = $conn->query($recent_orders_sql);
if ($recent_orders_result && $recent_orders_result->num_rows > 0) {
    while ($row = $recent_orders_result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}

// Get user data
$user_sql = "SELECT * FROM users WHERE id = $user_id";
$user_result = $conn->query($user_sql);
$user = $user_result->fetch_assoc();

// Get display name for profile
$display_name = $user['username'] ?? $user['email'] ?? 'MochSetiawan';
$shop_name = $vendor_data['shop_name'] ?? 'My Shop';

// Current date for display
$current_datetime = date('Y-m-d H:i:s');

// Add quick product function for empty shop
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sample_product'])) {
    // Get a random category
    $category_result = $conn->query("SELECT id FROM categories ORDER BY RAND() LIMIT 1");
    $category = $category_result->fetch_assoc();
    $category_id = $category['id'];
    
    // Create a sample product
    $product_name = "Sample Product " . rand(100, 999);
    $product_slug = strtolower(str_replace(' ', '-', $product_name));
    $product_price = rand(50, 500) * 1000; // Random price between 50k-500k
    $product_sale_price = $product_price * 0.9; // 10% discount
    $product_quantity = rand(10, 100);
    
    $insert_product_sql = "INSERT INTO products (
        vendor_id, category_id, name, slug, description, short_description, 
        price, sale_price, quantity, status
    ) VALUES (
        $vendor_id, $category_id, '$product_name', '$product_slug', 
        'This is a sample product description for testing.', 'Sample product short description',
        $product_price, $product_sale_price, $product_quantity, 'active'
    )";
    
    if ($conn->query($insert_product_sql)) {
        // Refresh stats after adding product
        $products_result = $conn->query($products_sql);
        $row = $products_result->fetch_assoc();
        $stats['products'] = $row['count'];
        
        // Reload page to show new product
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

include '../includes/vendor-header.php';
?>

<div class="container-fluid py-4">
    <!-- User Welcome Banner -->
    <div class="card mb-4 border-left-primary">
        <div class="card-body py-3">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Welcome back, <?= htmlspecialchars($display_name) ?>!
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        <?= htmlspecialchars($shop_name) ?>
                    </div>
                    <div class="text-xs text-gray-800 mt-2">
                        Current Date and Time (UTC): 
                        <span id="live-datetime"><?= $current_datetime ?></span>
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-store fa-2x text-primary"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
        <a href="<?= SITE_URL ?>/vendor/add-product.php" class="d-none d-sm-inline-block btn btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50 me-2"></i> Add New Product
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <!-- Products Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Products</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['products'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-cubes fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Orders</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['orders'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Total Sales</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?= number_format($stats['sales'], 0, ',', '.') ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Balance Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Account Balance</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?= number_format($vendor_balance, 0, ',', '.') ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-wallet fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Products Column -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Products</h6>
                    <a href="<?= SITE_URL ?>/vendor/products.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_products)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-box fa-3x text-gray-300 mb-3"></i>
                            <p class="mb-0">You haven't added any products yet.</p>
                            <div class="mt-3">
                                <a href="<?= SITE_URL ?>/vendor/add-product.php" class="btn btn-primary me-2">
                                    <i class="fas fa-plus me-2"></i> Add Product Manually
                                </a>
                                <form method="post" action="" class="d-inline">
                                    <button type="submit" name="add_sample_product" class="btn btn-success">
                                        <i class="fas fa-magic me-2"></i> Add Sample Product
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_products as $product): ?>
                                        <tr>
                                            <td><?= $product['id'] ?></td>
                                            <td>
                                                <a href="<?= SITE_URL ?>/vendor/edit_product.php?id=<?= $product['id'] ?>">
                                                    <?= htmlspecialchars($product['name']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if (isset($product['sale_price']) && $product['sale_price'] > 0): ?>
                                                    <del class="text-muted small">Rp <?= number_format($product['price'], 0, ',', '.') ?></del>
                                                    Rp <?= number_format($product['sale_price'], 0, ',', '.') ?>
                                                <?php else: ?>
                                                    Rp <?= number_format($product['price'], 0, ',', '.') ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($product['quantity'] > 10): ?>
                                                    <span class="text-success"><?= $product['quantity'] ?></span>
                                                <?php elseif ($product['quantity'] > 0): ?>
                                                    <span class="text-warning"><?= $product['quantity'] ?></span>
                                                <?php else: ?>
                                                    <span class="text-danger">Out of stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($product['status'] === 'active'): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Orders Column -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Orders</h6>
                    <a href="<?= SITE_URL ?>/vendor/orders.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_orders)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-shopping-cart fa-3x text-gray-300 mb-3"></i>
                            <p class="mb-0">No orders received yet.</p>
                            <p class="text-muted mt-2">Orders will appear here once customers place them.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= SITE_URL ?>/vendor/order_detail.php?id=<?= $order['id'] ?>">
                                                    #<?= $order['order_number'] ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                            <td>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></td>
                                            <td>
                                                <?php 
                                                $status_class = '';
                                                switch ($order['status']) {
                                                    case 'pending':
                                                        $status_class = 'badge-warning';
                                                        break;
                                                    case 'processing':
                                                        $status_class = 'badge-info';
                                                        break;
                                                    case 'shipped':
                                                        $status_class = 'badge-primary';
                                                        break;
                                                    case 'delivered':
                                                        $status_class = 'badge-success';
                                                        break;
                                                    case 'cancelled':
                                                        $status_class = 'badge-danger';
                                                        break;
                                                    default:
                                                        $status_class = 'badge-secondary';
                                                }
                                                ?>
                                                <span class="badge <?= $status_class ?>">
                                                    <?= ucfirst($order['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $payment_class = '';
                                                switch ($order['payment_status']) {
                                                    case 'pending':
                                                        $payment_class = 'badge-warning';
                                                        break;
                                                    case 'paid':
                                                        $payment_class = 'badge-success';
                                                        break;
                                                    case 'failed':
                                                        $payment_class = 'badge-danger';
                                                        break;
                                                    case 'refunded':
                                                        $payment_class = 'badge-info';
                                                        break;
                                                    default:
                                                        $payment_class = 'badge-secondary';
                                                }
                                                ?>
                                                <span class="badge <?= $payment_class ?>">
                                                    <?= ucfirst($order['payment_status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-xl-3 col-md-6 mb-3">
                            <a href="<?= SITE_URL ?>/vendor/add-product.php" class="card bg-primary text-white shadow h-100 py-2 text-decoration-none">
                                <div class="card-body">
                                    <div class="text-center">
                                        <i class="fas fa-plus-circle fa-2x mb-2"></i>
                                        <div>Add New Product</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-3">
                            <a href="<?= SITE_URL ?>/vendor/products.php" class="card bg-success text-white shadow h-100 py-2 text-decoration-none">
                                <div class="card-body">
                                    <div class="text-center">
                                        <i class="fas fa-edit fa-2x mb-2"></i>
                                        <div>Manage Products</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-3">
                            <a href="<?= SITE_URL ?>/vendor/orders.php" class="card bg-info text-white shadow h-100 py-2 text-decoration-none">
                                <div class="card-body">
                                    <div class="text-center">
                                        <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                        <div>View Orders</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-3">
                            <a href="<?= SITE_URL ?>/vendor/profile.php" class="card bg-secondary text-white shadow h-100 py-2 text-decoration-none">
                                <div class="card-body">
                                    <div class="text-center">
                                        <i class="fas fa-user fa-2x mb-2"></i>
                                        <div>Edit Profile</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
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

// Auto refresh dashboard setiap 2 menit
setTimeout(function() {
    window.location.href = window.location.href.split('?')[0] + '?refresh=' + new Date().getTime();
}, 120000);
</script>

<?php include '../includes/vendor-footer.php'; ?>