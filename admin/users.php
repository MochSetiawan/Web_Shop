<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi admin
if (!isLoggedIn() || $_SESSION['role'] !== ROLE_ADMIN) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$pageTitle = 'Manage Users';
$currentPage = 'users';

// Handle action
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $user_id = (int)$_GET['id'];
    
    // Handle status toggle
    if ($action === 'activate' || $action === 'deactivate') {
        $status = ($action === 'activate') ? 'active' : 'inactive';
        
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $user_id);
        
        if ($stmt->execute()) {
            $success = "User " . ucfirst($status);
        } else {
            $error = "Failed to update user status.";
        }
    }
    
    // Handle delete
    if ($action === 'delete') {
        // Check if user is admin
        $checkAdmin = $conn->query("SELECT role FROM users WHERE id = $user_id");
        $isAdmin = $checkAdmin->fetch_assoc()['role'] === ROLE_ADMIN;
        
        if ($isAdmin) {
            $error = "Cannot delete admin user.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $success = "User deleted successfully.";
            } else {
                $error = "Failed to delete user.";
            }
        }
    }
}

// Filter parameters
$role = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$query = "SELECT * FROM users WHERE 1=1";

if (!empty($role)) {
    $query .= " AND role = '$role'";
}

if (!empty($status)) {
    $query .= " AND status = '$status'";
}

if (!empty($search)) {
    $query .= " AND (username LIKE '%$search%' OR email LIKE '%$search%' OR full_name LIKE '%$search%')";
}

$query .= " ORDER BY created_at DESC";

// Execute query
$result = $conn->query($query);
$users = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

include '../includes/admin-header.php';
?>

<div class="admin-content-header">
    <h1 class="h3 mb-0">Manage Users</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Users</li>
        </ol>
    </nav>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Filter Users</h5>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="get" action="" class="row g-3">
            <div class="col-md-4">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="">All Roles</option>
                    <option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customer</option>
                    <option value="vendor" <?= $role === 'vendor' ? 'selected' : '' ?>>Vendor</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Username, Email, Name">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="<?= ADMIN_URL ?>/users.php" class="btn btn-outline-secondary">Reset</a>
                <a href="<?= ADMIN_URL ?>/user-add.php" class="btn btn-success float-end">Add New User</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">All Users</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="<?= SITE_URL ?>/assets/img/<?= $user['profile_image'] ?: DEFAULT_IMG ?>" alt="<?= $user['username'] ?>" class="user-avatar me-2">
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($user['full_name']) ?></div>
                                        <div class="small text-muted">@<?= htmlspecialchars($user['username']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <span class="badge bg-<?= getRoleBadgeClass($user['role']) ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= getStatusBadgeClass($user['status']) ?>">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                            </td>
                            <td><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= ADMIN_URL ?>/user-edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($user['role'] !== ROLE_ADMIN || $_SESSION['user_id'] !== $user['id']): ?>
                                        <?php if ($user['status'] === 'active'): ?>
                                            <a href="<?= ADMIN_URL ?>/users.php?action=deactivate&id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= ADMIN_URL ?>/users.php?action=activate&id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= ADMIN_URL ?>/users.php?action=delete&id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete" data-confirm="Are you sure you want to delete this user?">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
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
function getRoleBadgeClass($role) {
    switch ($role) {
        case 'admin': return 'danger';
        case 'vendor': return 'primary';
        case 'customer': return 'success';
        default: return 'secondary';
    }
}

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