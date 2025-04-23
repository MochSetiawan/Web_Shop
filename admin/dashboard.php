<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$pageTitle = 'Admin Dashboard';
$currentPage = 'dashboard';

// Get current user info
$current_user = $_SESSION['username'] ?? 'MochSetiawan';
$current_datetime = date('Y-m-d H:i:s');

// Initialize token system
if (!isset($_SESSION['valid_tokens']) || !is_array($_SESSION['valid_tokens'])) {
    $_SESSION['valid_tokens'] = [];
}

// Process token if provided in URL
$token_error = '';
$token_success = '';
$allowed_order_ids = array_keys($_SESSION['valid_tokens']);

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);
    $specific_order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    
    // Validate token against database
    $token_query = "SELECT at.order_id, at.expires_at, v.shop_name, at.vendor_id, at.token
                  FROM access_tokens at
                  JOIN vendors v ON at.vendor_id = v.id
                  WHERE at.token = ? AND at.expires_at > NOW()";
    
    if ($specific_order_id > 0) {
        $token_query .= " AND at.order_id = ?";
        $stmt = $conn->prepare($token_query);
        $stmt->bind_param('si', $token, $specific_order_id);
    } else {
        $stmt = $conn->prepare($token_query);
        $stmt->bind_param('s', $token);
    }
    
    $stmt->execute();
    $token_result = $stmt->get_result();
    
    if ($token_result && $token_result->num_rows > 0) {
        $token_data = $token_result->fetch_assoc();
        $allowed_order_id = $token_data['order_id'];
        $shop_name = $token_data['shop_name'];
        $vendor_id = $token_data['vendor_id'];
        $expires_at = $token_data['expires_at'];
        
        // Calculate hours left
        $hours_left = round((strtotime($expires_at) - time()) / 3600, 1);
        
        // Save valid token to session
        $_SESSION['valid_tokens'][$allowed_order_id] = [
            'token' => $token,
            'vendor' => $shop_name,
            'expires_at' => $expires_at,
            'vendor_id' => $vendor_id
        ];
        
        // Update array of allowed order IDs
        if (!in_array($allowed_order_id, $allowed_order_ids)) {
            $allowed_order_ids[] = $allowed_order_id;
        }
        
        // Success notification
        $token_success = "Token valid untuk mengakses pesanan #$allowed_order_id dari vendor: $shop_name. Token berlaku hingga " . 
                          date('d M Y H:i', strtotime($expires_at)) . " ($hours_left jam lagi)";
        
        // Redirect to order detail if specific order ID was provided
        if ($specific_order_id > 0) {
            header("Location: order_detail.php?id=$specific_order_id");
            exit;
        }
    } else {
        $token_error = 'Token tidak valid atau sudah kedaluwarsa';
    }
}

// Clean up expired tokens
foreach ($_SESSION['valid_tokens'] as $order_id => $token_info) {
    if (isset($token_info['expires_at']) && strtotime($token_info['expires_at']) < time()) {
        unset($_SESSION['valid_tokens'][$order_id]);
    }
}

// Update allowed_order_ids after cleaning
$allowed_order_ids = array_keys($_SESSION['valid_tokens']);

// Get count of recent orders (last 30 days)
$recent_orders_query = "SELECT COUNT(*) as count FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$recent_orders_result = $conn->query($recent_orders_query);
$recent_orders_count = $recent_orders_result->fetch_assoc()['count'];

// Get total revenue (last 30 days)
$revenue_query = "SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$revenue_result = $conn->query($revenue_query);
$total_revenue = $revenue_result->fetch_assoc()['total'] ?? 0;

// Get pending orders count
$pending_orders_query = "SELECT COUNT(*) as count FROM orders WHERE status = 'pending'";
$pending_orders_result = $conn->query($pending_orders_query);
$pending_orders_count = $pending_orders_result->fetch_assoc()['count'];

// Get total customers
$customers_query = "SELECT COUNT(DISTINCT user_id) as count FROM orders";
$customers_result = $conn->query($customers_query);
$total_customers = $customers_result->fetch_assoc()['count'];

// Get recent orders for table display
$orders_query = "SELECT o.*, u.username, u.full_name 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                ORDER BY o.created_at DESC 
                LIMIT 10";
$orders_result = $conn->query($orders_query);
$orders = [];

if ($orders_result && $orders_result->num_rows > 0) {
    while ($row = $orders_result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Get recent reports/complaints
$reports_query = "SELECT r.*, v.shop_name 
                 FROM reports r 
                 JOIN vendors v ON r.vendor_id = v.id 
                 ORDER BY r.created_at DESC 
                 LIMIT 5";
$reports_result = $conn->query($reports_query);
$reports = [];

if ($reports_result && $reports_result->num_rows > 0) {
    while ($row = $reports_result->fetch_assoc()) {
        $reports[] = $row;
    }
}

// Get sales data by date for chart (last 7 days)
$sales_data_query = "SELECT 
                        DATE(created_at) as date, 
                        COUNT(*) as orders_count,
                        SUM(total_amount) as total_sales
                    FROM orders 
                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date";
$sales_data_result = $conn->query($sales_data_query);
$sales_data = [];
$sales_labels = [];
$sales_values = [];

if ($sales_data_result && $sales_data_result->num_rows > 0) {
    while ($row = $sales_data_result->fetch_assoc()) {
        $sales_data[] = $row;
        $sales_labels[] = date('d M', strtotime($row['date']));
        $sales_values[] = (float)$row['total_sales'];
    }
}

include '../includes/admin-header.php';
?>

<div class="container-fluid">

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
        <a href="<?= ADMIN_URL ?>/dashboard.php?refresh=<?= time() ?>" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-sync-alt fa-sm text-white-50"></i> Refresh Data
        </a>
    </div>
    
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
    
    <!-- Notifications -->
    <?php if (!empty($token_success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> <?= $token_success ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($token_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-exclamation-circle mr-1"></i> <?= $token_error ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> <?= $_SESSION['success_message'] ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <!-- Active Tokens Summary -->
    <?php if (!empty($_SESSION['valid_tokens'])): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-key mr-1"></i> Anda memiliki <strong><?= count($_SESSION['valid_tokens']) ?> token</strong> aktif yang memberikan akses ke pesanan.
            <button type="button" class="btn btn-sm btn-info ml-2" data-toggle="modal" data-target="#tokenManagerModal">
                Kelola Token
            </button>
        </div>
    <?php endif; ?>

    <!-- Content Row -->
    <div class="row">

        <!-- Total Orders (30 days) Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Orders (Last 30 Days)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $recent_orders_count ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Revenue (30 days) Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Revenue (Last 30 Days)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= formatPrice($total_revenue) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Orders Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Orders</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $pending_orders_count ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Customers Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Customers
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_customers ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Row -->
    <div class="row">

        <!-- Sales Chart -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <!-- Card Header - Dropdown -->
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Sales Overview (Last 7 Days)</h6>
                </div>
                <!-- Card Body -->
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Tokens -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <!-- Card Header - Dropdown -->
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Active Tokens</h6>
                    <?php if (!empty($_SESSION['valid_tokens'])): ?>
                        <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#tokenManagerModal">
                            <i class="fas fa-key mr-1"></i> Manage Tokens
                        </button>
                    <?php else: ?>
                        <button class="btn btn-sm btn-secondary" data-toggle="modal" data-target="#tokenModal">
                            <i class="fas fa-key mr-1"></i> Enter Token
                        </button>
                    <?php endif; ?>
                </div>
                <!-- Card Body -->
                <div class="card-body">
                    <?php if (empty($_SESSION['valid_tokens'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-key fa-3x text-gray-300 mb-3"></i>
                            <p class="mb-0">No active tokens found.</p>
                            <p class="text-muted">Enter a token provided by a vendor to access order details.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Vendor</th>
                                        <th>Expires</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($_SESSION['valid_tokens'] as $order_id => $token_info): 
                                        $remaining = strtotime($token_info['expires_at']) - time();
                                        $hours_left = round($remaining / 3600, 1);
                                        $is_expired = $remaining <= 0;
                                    ?>
                                        <tr class="<?= $is_expired ? 'table-danger' : '' ?>">
                                            <td><a href="order_detail.php?id=<?= $order_id ?>">#<?= $order_id ?></a></td>
                                            <td><?= htmlspecialchars($token_info['vendor']) ?></td>
                                            <td>
                                                <?php if ($is_expired): ?>
                                                    <span class="badge badge-danger">Expired</span>
                                                <?php elseif ($hours_left < 12): ?>
                                                    <span class="badge badge-warning"><?= $hours_left ?>h left</span>
                                                <?php else: ?>
                                                    <span class="badge badge-success"><?= $hours_left ?>h left</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$is_expired): ?>
                                                    <a href="order_detail.php?id=<?= $order_id ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="remove_token.php?order_id=<?= $order_id ?>&return=dashboard.php" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Remove this token?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($_SESSION['valid_tokens']) > 5): ?>
                            <div class="text-center mt-2">
                                <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#tokenManagerModal">
                                    View All Tokens
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Row -->
    <div class="row">

        <!-- Recent Orders -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Orders</h6>
                    <a href="<?= ADMIN_URL ?>/orders.php" class="btn btn-sm btn-primary">
                        View All Orders
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-shopping-cart fa-3x text-gray-300 mb-3"></i>
                            <p class="mb-0">No orders found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($order['order_number']) ?></td>
                                            <td><?= htmlspecialchars($order['full_name'] ?: $order['username']) ?></td>
                                            <td><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                                            <td><?= formatPrice($order['total_amount']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= getStatusBadgeClass($order['status']) ?>">
                                                    <?= ucfirst($order['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($_SESSION['role'] == 'superadmin' || in_array($order['id'], $allowed_order_ids)): ?>
                                                    <!-- Full access to order details -->
                                                    <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                <?php else: ?>
                                                    <!-- Restricted access -->
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#tokenModal" data-order-id="<?= $order['id'] ?>">
                                                        <i class="fas fa-lock"></i> Locked
                                                    </button>
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

        <!-- Recent Reports -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Reports</h6>
                    <a href="<?= ADMIN_URL ?>/keluhan.php" class="btn btn-sm btn-primary">
                        View All Reports
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($reports)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-comments fa-3x text-gray-300 mb-3"></i>
                            <p class="mb-0">No reports found.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($reports as $report): 
                                $status_class = '';
                                switch ($report['status']) {
                                    case 'pending': $status_class = 'warning'; break;
                                    case 'in_progress': $status_class = 'info'; break;
                                    case 'resolved': $status_class = 'success'; break;
                                    case 'rejected': $status_class = 'danger'; break;
                                    default: $status_class = 'secondary';
                                }
                            ?>
                                <a href="<?= ADMIN_URL ?>/keluhan.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($report['subject']) ?></h6>
                                        <small class="text-muted"><?= date('d M Y', strtotime($report['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-1 text-muted"><?= htmlspecialchars(substr($report['message'], 0, 50)) ?>...</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small>From: <?= htmlspecialchars($report['shop_name']) ?></small>
                                        <span class="badge badge-<?= $status_class ?>">
                                            <?= ucfirst($report['status']) ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Token Modal -->
<div class="modal fade" id="tokenModal" tabindex="-1" role="dialog" aria-labelledby="tokenModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tokenModalLabel">Access Restricted</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="get" action="dashboard.php" id="tokenForm">
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-lock fa-4x text-warning mb-3"></i>
                        <h4>Access Restricted</h4>
                        <p>You need a valid token from the vendor to access this order's details.</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="access_token">Enter Access Token:</label>
                        <input type="text" class="form-control" id="access_token" name="token" placeholder="Enter token provided by vendor" required>
                        <input type="hidden" id="order_id_input" name="order_id">
                        <small class="form-text text-muted">The token is provided by the vendor to grant you temporary access.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Token</button>
                    <button type="button" class="btn btn-link text-danger refresh-page-btn">
                        <small><i class="fas fa-sync-alt"></i> Refresh if stuck</small>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Token Manager Modal -->
<div class="modal fade" id="tokenManagerModal" tabindex="-1" role="dialog" aria-labelledby="tokenManagerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tokenManagerModalLabel">Manage Access Tokens</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php if (!empty($_SESSION['valid_tokens'])): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Vendor</th>
                                    <th>Expires</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['valid_tokens'] as $order_id => $token_info): ?>
                                    <?php 
                                    $remaining = strtotime($token_info['expires_at']) - time();
                                    $hours_left = round($remaining / 3600, 1);
                                    $is_expired = $remaining <= 0;
                                    ?>
                                    <tr class="<?= $is_expired ? 'table-danger' : '' ?>">
                                        <td>
                                            <a href="<?= ADMIN_URL ?>/order_detail.php?id=<?= $order_id ?>">
                                                Order #<?= $order_id ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($token_info['vendor']) ?></td>
                                        <td><?= date('d M Y H:i', strtotime($token_info['expires_at'])) ?></td>
                                        <td>
                                            <?php if ($is_expired): ?>
                                                <span class="badge badge-danger">Expired</span>
                                            <?php elseif ($hours_left < 12): ?>
                                                <span class="badge badge-warning"><?= $hours_left ?> hours left</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Valid (<?= $hours_left ?> hours)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$is_expired): ?>
                                                <a href="<?= ADMIN_URL ?>/order_detail.php?id=<?= $order_id ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php endif; ?>
                                            <a href="<?= ADMIN_URL ?>/remove_token.php?order_id=<?= $order_id ?>&return=dashboard.php" 
                                               class="btn btn-sm btn-danger ml-1"
                                               onclick="return confirm('Are you sure you want to remove this token?')">
                                                <i class="fas fa-trash"></i> Remove
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle mr-1"></i> Expired tokens are automatically removed from your session when you refresh the page.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-1"></i> You currently don't have any active tokens.
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <?php if (!empty($_SESSION['valid_tokens'])): ?>
                    <a href="<?= ADMIN_URL ?>/remove_token.php?all=1&return=dashboard.php" class="btn btn-danger" 
                       onclick="return confirm('Are you sure you want to remove all tokens?')">
                        Remove All Tokens
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
<script>
$(document).ready(function() {
    console.log("Document ready for dashboard.php");
    
    // Force cleanup any lingering modals on page load
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css({
        'overflow': '',
        'padding-right': ''
    });
    
    // Update real-time datetime
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
    
    // Update time every second
    setInterval(updateDateTime, 1000);
    updateDateTime(); // Call once immediately
    
    // Auto-dismiss alerts after 8 seconds
    setTimeout(function() {
        $('.alert-dismissible').each(function() {
            $(this).find('.close').click();
        });
    }, 8000);
    
    // Sales Chart
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($sales_labels) ?>,
            datasets: [{
                label: 'Sales (Rp)',
                data: <?= json_encode($sales_values) ?>,
                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                borderColor: 'rgba(78, 115, 223, 1)',
                pointRadius: 3,
                pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                pointBorderColor: 'rgba(78, 115, 223, 1)',
                pointHoverRadius: 5,
                pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                pointHitRadius: 10,
                pointBorderWidth: 2,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: 10,
                    right: 25,
                    top: 25,
                    bottom: 0
                }
            },
            scales: {
                xAxes: [{
                    gridLines: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        maxTicksLimit: 7
                    }
                }],
                yAxes: [{
                    ticks: {
                        maxTicksLimit: 5,
                        padding: 10,
                        callback: function(value, index, values) {
                            return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                        }
                    },
                    gridLines: {
                        color: "rgb(234, 236, 244)",
                        zeroLineColor: "rgb(234, 236, 244)",
                        drawBorder: false,
                        borderDash: [2],
                        zeroLineBorderDash: [2]
                    }
                }]
            },
            legend: {
                display: false
            },
            tooltips: {
                backgroundColor: "rgb(255,255,255)",
                bodyFontColor: "#858796",
                titleMarginBottom: 10,
                titleFontColor: '#6e707e',
                titleFontSize: 14,
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                intersect: false,
                mode: 'index',
                caretPadding: 10,
                callbacks: {
                    label: function(tooltipItem, chart) {
                        var datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
                        return datasetLabel + ': Rp ' + tooltipItem.yLabel.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                    }
                }
            }
        }
    });
    
    // Token Modal
    $('#tokenModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const orderId = button.data('order-id');
        $('#order_id_input').val(orderId);
    });
    
    // Fix modals
    $('.btn-info[data-target="#tokenManagerModal"], .btn-outline-primary[data-target="#tokenManagerModal"]').on('click', function(e) {
        e.preventDefault();
        
        // Clean up any existing modals
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css({
            'overflow': '',
            'padding-right': ''
        });
        
        // Show the modal
        $('#tokenManagerModal').modal('show');
    });
    
    $('.btn-outline-secondary[data-target="#tokenModal"], .btn-secondary[data-target="#tokenModal"]').on('click', function(e) {
        e.preventDefault();
        
        // Clean up any existing modals
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css({
            'overflow': '',
            'padding-right': ''
        });
        
        const orderId = $(this).data('order-id');
        $('#order_id_input').val(orderId);
        $('#tokenModal').modal('show');
    });
    
    // Refresh page button
    $('.refresh-page-btn').on('click', function() {
        location.reload();
    });
    
    // PERBAIKAN: Handle modal cleanup on modal hidden
    $(document).on('hidden.bs.modal', '.modal', function() {
        console.log("Modal hidden - cleaning up");
        
        // Force remove all backdrops and reset body
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css({
            'overflow': '',
            'padding-right': ''
        });
    });
    
    // PERBAIKAN: Manually handle modal dismiss
    $('[data-dismiss="modal"]').on('click', function(e) {
        e.preventDefault();
        console.log("Modal dismiss clicked");
        
        const modal = $(this).closest('.modal');
        modal.modal('hide');
        
        // Force cleanup after a short delay
        setTimeout(function() {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css({
                'overflow': '',
                'padding-right': ''
            });
        }, 200);
    });
});
</script>

<?php
// Helper functions
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'processing': return 'info';
        case 'shipped': return 'primary';
        case 'delivered': return 'success';
        case 'cancelled': return 'danger';
        default: return 'secondary';
    }
}

function getPaymentStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'paid': return 'success';
        case 'failed': return 'danger';
        case 'refunded': return 'info';
        default: return 'secondary';
    }
}
?>

<?php include '../includes/admin-footer.php'; ?>