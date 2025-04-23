<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi admin
if (!isLoggedIn() || $_SESSION['role'] !== ROLE_ADMIN) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$pageTitle = 'Manage Vendors';
$currentPage = 'vendors';

// Handle action
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $vendor_id = (int)$_GET['id'];
    
    if ($action === 'approve' || $action === 'activate' || $action === 'deactivate') {
        $status = ($action === 'approve' || $action === 'activate') ? 'active' : 'inactive';
        
        // Update vendor status
        $stmt = $conn->prepare("UPDATE vendors SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $vendor_id);
        
        if ($stmt->execute()) {
            // If approving, update user role to vendor
            if ($action === 'approve') {
                // Get user ID associated with vendor
                $stmt = $conn->prepare("SELECT user_id FROM vendors WHERE id = ?");
                $stmt->bind_param("i", $vendor_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $user_id = $result->fetch_assoc()['user_id'];
                    
                    // Update user role
                    $stmt = $conn->prepare("UPDATE users SET role = 'vendor' WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                }
            }
            
            $success = "Vendor " . ($action === 'approve' ? 'approved' : ($action === 'activate' ? 'activated' : 'deactivated')) . " successfully.";
        } else {
            $error = "Failed to update vendor status.";
        }
    } elseif ($action === 'delete') {
        // Delete vendor
        $stmt = $conn->prepare("DELETE FROM vendors WHERE id = ?");
        $stmt->bind_param("i", $vendor_id);
        
        if ($stmt->execute()) {
            $success = "Vendor deleted successfully.";
        } else {
            $error = "Failed to delete vendor.";
        }
    }
}

// Filter parameters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$query = "SELECT v.*, u.username, u.full_name, u.email 
          FROM vendors v 
          JOIN users u ON v.user_id = u.id 
          WHERE 1=1";

if (!empty($status)) {
    $query .= " AND v.status = '$status'";
}

if (!empty($search)) {
    $query .= " AND (v.shop_name LIKE '%$search%' OR u.username LIKE '%$search%' OR u.email LIKE '%$search%' OR u.full_name LIKE '%$search%')";
}

$query .= " ORDER BY v.created_at DESC";

// Execute query
$result = $conn->query($query);
$vendors = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $vendors[] = $row;
    }
}

include '../includes/admin-header.php';
?>

<div class="admin-content-header">
    <h1 class="h3 mb-0">Manage Vendors</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Vendors</li>
        </ol>
    </nav>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Filter Vendors</h5>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="get" action="" class="row g-3">
            <div class="col-md-6">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Shop Name, Vendor">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="<?= ADMIN_URL ?>/vendors.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">All Vendors</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Shop</th>
                        <th>Owner</th>
                        <th>Commission</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $vendor): ?>
                        <tr>
                            <td><?= $vendor['id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="<?= SITE_URL ?>/assets/img/vendors/<?= $vendor['logo'] ?: 'default-shop.jpg' ?>" alt="<?= $vendor['shop_name'] ?>" class="vendor-logo me-2">
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($vendor['shop_name']) ?></div>
                                        <div class="small text-muted"><?= substr($vendor['description'], 0, 50) . (strlen($vendor['description']) > 50 ? '...' : '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($vendor['full_name']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($vendor['email']) ?></div>
                            </td>
                            <td><?= $vendor['commission_rate'] ?>%</td>
                            <td><?= formatPrice($vendor['balance']) ?></td>
                            <td>
                                <span class="badge bg-<?= getStatusBadgeClass($vendor['status']) ?>">
                                    <?= ucfirst($vendor['status']) ?>
                                </span>
                            </td>
                            <td><?= date('d M Y', strtotime($vendor['created_at'])) ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= ADMIN_URL ?>/vendor-details.php?id=<?= $vendor['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($vendor['status'] === 'pending'): ?>
                                        <a href="<?= ADMIN_URL ?>/vendors.php?action=approve&id=<?= $vendor['id'] ?>" class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                    <?php elseif ($vendor['status'] === 'active'): ?>
                                        <a href="<?= ADMIN_URL ?>/vendors.php?action=deactivate&id=<?= $vendor['id'] ?>" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-ban"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= ADMIN_URL ?>/vendors.php?action=activate&id=<?= $vendor['id'] ?>" class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= ADMIN_URL ?>/vendors.php?action=delete&id=<?= $vendor['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete" data-confirm="Are you sure you want to delete this vendor?">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Helper functions
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'active': return 'success';
        case 'inactive': return 'danger';
        case 'pending': return 'warning';
        default: return 'secondary';
    }
}
?>

<?php include '../includes/admin-footer.php'; ?>