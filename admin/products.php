<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi admin
if (!isLoggedIn() || $_SESSION['role'] !== ROLE_ADMIN) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$pageTitle = 'Manage Products';
$currentPage = 'products';

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    // Delete product
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    
    if ($stmt->execute()) {
        // Delete product images
        $conn->query("DELETE FROM product_images WHERE product_id = $product_id");
        $success = "Product deleted successfully.";
    } else {
        $error = "Failed to delete product.";
    }
}

// Get all products with vendor and category info
$query = "SELECT p.*, v.shop_name, c.name as category_name, 
          (SELECT image FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image 
          FROM products p 
          LEFT JOIN vendors v ON p.vendor_id = v.id 
          LEFT JOIN categories c ON p.category_id = c.id 
          ORDER BY p.created_at DESC";
$result = $conn->query($query);
$products = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

include '../includes/admin-header.php';
?>

<div class="admin-content-header">
    <h1 class="h3 mb-0">Manage Products</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Products</li>
        </ol>
    </nav>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">All Products</h5>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Product</th>
                        <th>Vendor</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= $product['id'] ?></td>
                            <td>
                                <img src="<?= SITE_URL ?>/assets/img/products/<?= $product['main_image'] ?: 'default.jpg' ?>" alt="<?= $product['name'] ?>" class="product-thumbnail">
                            </td>
                            <td>
                                <div class="product-name"><?= $product['name'] ?></div>
                                <div class="product-sku text-muted small"><?= $product['sku'] ?: 'No SKU' ?></div>
                            </td>
                            <td><?= $product['shop_name'] ?></td>
                            <td><?= $product['category_name'] ?></td>
                            <td>
                                <?php if ($product['sale_price']): ?>
                                    <span class="text-decoration-line-through text-muted"><?= formatPrice($product['price']) ?></span>
                                    <span class="text-danger"><?= formatPrice($product['sale_price']) ?></span>
                                <?php else: ?>
                                    <?= formatPrice($product['price']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= getStatusBadgeClass($product['status']) ?>">
                                    <?= ucfirst($product['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= SITE_URL ?>/product.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?= ADMIN_URL ?>/product-edit.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?= ADMIN_URL ?>/products.php?action=delete&id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete" data-confirm="Are you sure you want to delete this product?">
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
        case 'inactive': return 'warning';
        case 'draft': return 'secondary';
        default: return 'primary';
    }
}
?>

<?php include '../includes/admin-footer.php'; ?>