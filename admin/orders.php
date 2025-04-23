<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$pageTitle = 'Manage Orders';
$currentPage = 'orders';
$current_user = $_SESSION['username'] ?? 'MochSetiawan';
$current_datetime = date('Y-m-d H:i:s');

// Inisialisasi token system
if (!isset($_SESSION['valid_tokens']) || !is_array($_SESSION['valid_tokens'])) {
    $_SESSION['valid_tokens'] = [];
}

// Cek token baru jika disediakan
$token_error = '';
$token_success = '';
$allowed_order_ids = array_keys($_SESSION['valid_tokens']);

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);
    $specific_order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    
    // Validasi token
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
        
        // Simpan token valid ke session
        $_SESSION['valid_tokens'][$allowed_order_id] = [
            'token' => $token,
            'vendor' => $shop_name,
            'expires_at' => $expires_at,
            'vendor_id' => $vendor_id
        ];
        
        // Update array
        if (!in_array($allowed_order_id, $allowed_order_ids)) {
            $allowed_order_ids[] = $allowed_order_id;
        }
        
        // Calculate expiration time in hours
        $hours_left = round((strtotime($expires_at) - time()) / 3600, 1);
        
        // Notifikasi token valid
        $token_success = "Token valid untuk mengakses pesanan #$allowed_order_id dari vendor: $shop_name. Token berlaku hingga " . 
                        date('d M Y H:i', strtotime($expires_at)) . " ($hours_left jam lagi)";
        
        // Redirect ke detail order jika spesifik
        if ($specific_order_id > 0) {
            header("Location: order_detail.php?id=$specific_order_id");
            exit;
        }
    } else {
        $token_error = 'Token tidak valid atau sudah kedaluwarsa';
    }
}

// Bersihkan token kedaluwarsa
foreach ($_SESSION['valid_tokens'] as $order_id => $token_info) {
    if (isset($token_info['expires_at']) && strtotime($token_info['expires_at']) < time()) {
        unset($_SESSION['valid_tokens'][$order_id]);
    }
}

// Update allowed_order_ids setelah membersihkan
$allowed_order_ids = array_keys($_SESSION['valid_tokens']);

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filter parameters
$status_filter = isset($_GET['status']) && !empty($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$search = isset($_GET['search']) && !empty($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Build query with filters
$where_clauses = [];
$query_params = [];
$param_types = '';

if (!empty($status_filter)) {
    $where_clauses[] = "o.status = ?";
    $query_params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($search)) {
    $where_clauses[] = "(o.order_number LIKE ? OR u.full_name LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search%";
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $param_types .= 'sss';
}

$where_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";

// Count total orders with filters
$count_sql = "SELECT COUNT(*) as total FROM orders o JOIN users u ON o.user_id = u.id $where_sql";

if (!empty($param_types)) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($param_types, ...$query_params);
    $stmt->execute();
    $count_result = $stmt->get_result();
} else {
    $count_result = $conn->query($count_sql);
}

$total_orders = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $per_page);

// Ensure page is within valid range
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

// Get orders with pagination and filters
$orders_sql = "SELECT o.*, u.username, u.full_name 
               FROM orders o 
               JOIN users u ON o.user_id = u.id 
               $where_sql 
               ORDER BY o.created_at DESC 
               LIMIT ? OFFSET ?";

$stmt = $conn->prepare($orders_sql);
if (!empty($param_types)) {
    $param_types .= 'ii';
    $query_params[] = $per_page;
    $query_params[] = $offset;
    $stmt->bind_param($param_types, ...$query_params);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}

$stmt->execute();
$orders_result = $stmt->get_result();
$orders = [];

while ($row = $orders_result->fetch_assoc()) {
    $orders[] = $row;
}

include '../includes/admin-header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage Orders</h1>
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
    
    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Orders</h6>
        </div>
        <div class="card-body">
            <form method="get" action="" class="form-inline">
                <div class="form-group mr-3 mb-2">
                    <label for="status" class="mr-2">Status:</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="processing" <?= $status_filter === 'processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="shipped" <?= $status_filter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="form-group mr-3 mb-2">
                    <label for="search" class="mr-2">Search:</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Order # or Customer">
                </div>
                <button type="submit" class="btn btn-primary mb-2">Apply Filters</button>
                <a href="<?= ADMIN_URL ?>/orders.php" class="btn btn-secondary mb-2 ml-2">Reset</a>
            </form>
        </div>
    </div>
    
    <!-- Orders Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Orders List</h6>
            <?php if (!empty($_SESSION['valid_tokens'])): ?>
            <button class="btn btn-info btn-sm" data-toggle="modal" data-target="#tokenManagerModal">
                <i class="fas fa-key mr-1"></i> Manage Tokens
            </button>
            <?php else: ?>
            <button class="btn btn-secondary btn-sm" data-toggle="modal" data-target="#tokenModal">
                <i class="fas fa-key mr-1"></i> Enter Token
            </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($orders)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-1"></i> No orders found.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataOrders" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Payment Status</th>
                                <th>Order Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?= htmlspecialchars($order['order_number']) ?></td>
                                    <td><?= htmlspecialchars($order['full_name'] ?: $order['username']) ?></td>
                                    <td><?= date('d M Y H:i', strtotime($order['created_at'])) ?></td>
                                    <td><?= formatPrice($order['total_amount']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= getPaymentStatusBadgeClass($order['payment_status']) ?>">
                                            <?= ucfirst($order['payment_status']) ?>
                                        </span>
                                    </td>
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
                                            <?php if (in_array($order['id'], $allowed_order_ids)): ?>
                                                <?php 
                                                $token_info = $_SESSION['valid_tokens'][$order['id']];
                                                $time_left = strtotime($token_info['expires_at']) - time();
                                                $hours_left = round($time_left / 3600, 1);
                                                if ($time_left > 0): 
                                                ?>
                                                    <span class="badge badge-<?= $hours_left < 12 ? 'warning' : 'success' ?> ml-1" 
                                                          data-toggle="tooltip" 
                                                          title="Token expires: <?= date('d M Y H:i', strtotime($token_info['expires_at'])) ?>">
                                                        <?= $hours_left ?> hours left
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
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
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-4">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?= !empty($status_filter) ? '&status='.$status_filter : '' ?><?= !empty($search) ? '&search='.$search : '' ?>" aria-label="First">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($status_filter) ? '&status='.$status_filter : '' ?><?= !empty($search) ? '&search='.$search : '' ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            // Display page numbers with ellipsis if needed
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                        <a class="page-link" href="?page=' . $i . 
                                            (!empty($status_filter) ? '&status='.$status_filter : '') . 
                                            (!empty($search) ? '&search='.$search : '') . 
                                        '">' . $i . '</a>
                                      </li>';
                            }
                            
                            if ($end_page < $total_pages) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($status_filter) ? '&status='.$status_filter : '' ?><?= !empty($search) ? '&search='.$search : '' ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($status_filter) ? '&status='.$status_filter : '' ?><?= !empty($search) ? '&search='.$search : '' ?>" aria-label="Last">
                                        <span aria-hidden="true">&raquo;&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
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
            <form method="get" action="orders.php" id="tokenForm">
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
                                            <a href="<?= ADMIN_URL ?>/remove_token.php?order_id=<?= $order_id ?>&return=orders.php" 
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
                    <a href="<?= ADMIN_URL ?>/remove_token.php?all=1&return=orders.php" class="btn btn-danger" 
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
<script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    console.log("Document ready for orders.php");
    
    // Initialize DataTables
    $('#dataOrders').DataTable({
        "order": [[2, "desc"]], // Sort by date by default
        "pageLength": 25,
        "language": {
            "search": "Cari:",
            "lengthMenu": "Tampilkan _MENU_ data per halaman",
            "zeroRecords": "Tidak ditemukan data yang sesuai",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "infoEmpty": "Tidak ada data yang tersedia",
            "infoFiltered": "(disaring dari _MAX_ data keseluruhan)",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            }
        }
    });
    
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
    
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Auto-dismiss alerts after 8 seconds
    setTimeout(function() {
        $('.alert-dismissible').each(function() {
            $(this).find('.close').click();
        });
    }, 8000);
    
    // Token Modal
    $('#tokenModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const orderId = button.data('order-id');
        $('#order_id_input').val(orderId);
    });
    
    // Fix modals
    $('.btn-info[data-target="#tokenManagerModal"]').on('click', function(e) {
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
    
    $('.btn-outline-secondary[data-target="#tokenModal"]').on('click', function(e) {
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