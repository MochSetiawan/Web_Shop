<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check vendor authentication
if (!isLoggedIn() || $_SESSION['role'] !== ROLE_VENDOR) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pageTitle = 'My Products';
$currentPage = 'products';

// Get current date and time
$current_date = date('Y-m-d H:i:s');
$current_user = $_SESSION['username'] ?? 'MochSetiawan';

// Get the vendor ID from the vendors table (using user_id foreign key)
$vendor_result = $conn->query("SELECT id FROM vendors WHERE user_id = $user_id");
if ($vendor_result && $vendor_result->num_rows > 0) {
    $vendor_row = $vendor_result->fetch_assoc();
    $vendor_id = $vendor_row['id'];
} else {
    // If vendor record doesn't exist, create one
    $conn->query("INSERT INTO vendors (user_id, created_at) VALUES ($user_id, NOW())");
    $vendor_id = $conn->insert_id;
    
    if (!$vendor_id) {
        die("Error: Could not find or create vendor record. Please contact the administrator.");
    }
}

// Handle product deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $product_id = $_GET['delete'];
    
    // Check if product belongs to this vendor
    $check_query = "SELECT id FROM products WHERE id = $product_id AND vendor_id = $vendor_id";
    $check_result = $conn->query($check_query);
    
    if ($check_result && $check_result->num_rows > 0) {
        // Get product images to delete
        $images_result = $conn->query("SHOW TABLES LIKE 'product_images'");
        if ($images_result->num_rows > 0) {
            // If product_images table exists
            $images_query = "SELECT * FROM product_images WHERE product_id = $product_id";
            $images_result = $conn->query($images_query);
            
            if ($images_result) {
                while ($image = $images_result->fetch_assoc()) {
                    // Look for the column containing the image filename
                    $image_column = null;
                    foreach ($image as $key => $value) {
                        if (in_array($key, ['image_path', 'image', 'path', 'filename']) && !empty($value)) {
                            $image_column = $key;
                            break;
                        }
                    }
                    
                    if ($image_column) {
                        $image_path = '../assets/img/products/' . $image[$image_column];
                        if (file_exists($image_path)) {
                            unlink($image_path);
                        }
                    }
                }
            }
            
            // Delete image records
            $conn->query("DELETE FROM product_images WHERE product_id = $product_id");
        } else {
            // If no product_images table, check for image in products table
            $product_query = "SELECT * FROM products WHERE id = $product_id";
            $product_result = $conn->query($product_query);
            
            if ($product_result && $product_result->num_rows > 0) {
                $product = $product_result->fetch_assoc();
                
                // Look for the column containing the image filename
                $image_column = null;
                foreach ($product as $key => $value) {
                    if (in_array($key, ['image', 'thumbnail', 'featured_image']) && !empty($value)) {
                        $image_column = $key;
                        break;
                    }
                }
                
                if ($image_column) {
                    $image_path = '../assets/img/products/' . $product[$image_column];
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
            }
        }
        
        // Delete product
        $conn->query("DELETE FROM products WHERE id = $product_id AND vendor_id = $vendor_id");
        
        $_SESSION['success_message'] = "Product deleted successfully.";
    } else {
        $_SESSION['error_message'] = "You do not have permission to delete this product.";
    }
    
    // Redirect to remove the delete parameter from URL
    header('Location: ' . VENDOR_URL . '/products.php');
    exit;
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Search functionality
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_condition = " AND (p.name LIKE '%$search%' OR p.description LIKE '%$search%' OR p.sku LIKE '%$search%')";
}

// Filtering by category and status
$category_filter = isset($_GET['category']) && is_numeric($_GET['category']) ? (int)$_GET['category'] : 0;
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$category_condition = $category_filter > 0 ? " AND p.category_id = $category_filter" : "";
$status_condition = !empty($status_filter) ? " AND p.status = '$status_filter'" : "";

// Get products table structure to determine available columns
$products_columns = [];
$columns_result = $conn->query("SHOW COLUMNS FROM products");
if ($columns_result) {
    while ($column = $columns_result->fetch_assoc()) {
        $products_columns[] = $column['Field'];
    }
}

// Check if product_images table exists
$has_images_table = $conn->query("SHOW TABLES LIKE 'product_images'")->num_rows > 0;

// Determine the correct column name for stock/quantity
$stock_column = 'quantity';
if (in_array('stock', $products_columns)) {
    $stock_column = 'stock';
} elseif (in_array('inventory', $products_columns)) {
    $stock_column = 'inventory';
}

// Count total products - use vendor_id to filter
$count_sql = "SELECT COUNT(*) as total FROM products p WHERE p.vendor_id = $vendor_id$search_condition$category_condition$status_condition";
$count_result = $conn->query($count_sql);
$total_products = 0;
if ($count_result && $count_result->num_rows > 0) {
    $total_products = $count_result->fetch_assoc()['total'];
}
$total_pages = ceil($total_products / $items_per_page);

// Build the SQL query for products based on the available database structure
if ($has_images_table) {
    // Try to determine the image column in product_images table
    $image_columns = [];
    $image_columns_result = $conn->query("SHOW COLUMNS FROM product_images");
    if ($image_columns_result) {
        while ($column = $image_columns_result->fetch_assoc()) {
            $image_columns[] = $column['Field'];
        }
    }
    
    // Determine correct column name for image path
    $image_path_column = 'image';
    if (in_array('image_path', $image_columns)) {
        $image_path_column = 'image_path';
    } elseif (in_array('path', $image_columns)) {
        $image_path_column = 'path';
    } elseif (in_array('filename', $image_columns)) {
        $image_path_column = 'filename';
    }
    
    // Determine correct column name for primary image flag
    $is_primary_column = 'is_primary';
    if (in_array('is_primary', $image_columns)) {
        $is_primary_column = 'is_primary';
    } elseif (in_array('primary', $image_columns)) {
        $is_primary_column = 'primary';
    } elseif (in_array('is_main', $image_columns)) {
        $is_primary_column = 'is_main';
    } elseif (in_array('main_image', $image_columns)) {
        $is_primary_column = 'main_image';
    }
    
    // Get products with primary image
    $products_sql = "SELECT p.*, c.name as category_name, 
                    (SELECT pi.$image_path_column FROM product_images pi 
                     WHERE pi.product_id = p.id AND pi.$is_primary_column = 1 
                     LIMIT 1) as primary_image 
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.vendor_id = $vendor_id$search_condition$category_condition$status_condition
                    ORDER BY p.created_at DESC
                    LIMIT $offset, $items_per_page";
} else {
    // If no product_images table, try to find an image column in products table
    $image_column = 'image';
    if (in_array('image', $products_columns)) {
        $image_column = 'image';
    } elseif (in_array('thumbnail', $products_columns)) {
        $image_column = 'thumbnail';
    } elseif (in_array('featured_image', $products_columns)) {
        $image_column = 'featured_image';
    }
    
    // Get products with image from products table
    $products_sql = "SELECT p.*, c.name as category_name, p.$image_column as primary_image 
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.vendor_id = $vendor_id$search_condition$category_condition$status_condition
                    ORDER BY p.created_at DESC
                    LIMIT $offset, $items_per_page";
}

// Store debug info but only show if DEBUG is defined
$debug_info = [
    'User ID' => $user_id,
    'Vendor ID' => $vendor_id,
    'Stock Column' => $stock_column,
    'Has Images Table' => $has_images_table ? 'Yes' : 'No',
    'Total Products' => $total_products,
    'SQL Query' => $products_sql
];

// Execute the query
$products_result = $conn->query($products_sql);
$products = [];
if ($products_result) {
    while ($row = $products_result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Get categories for filter dropdown
$categories_sql = "SELECT * FROM categories ORDER BY name ASC";
$categories_result = $conn->query($categories_sql);
$categories = [];
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

include '../includes/vendor-header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">My Products</h1>
        <a href="<?= VENDOR_URL ?>/add-product.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50 mr-1"></i> Add New Product
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
                        Current Date and Time (Indonesia): <?= $current_date ?>
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-user-clock fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
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
    
    <!-- Debug Info - Only shown if DEBUG constant is defined -->
    <?php if (defined('DEBUG') && DEBUG): ?>
    <div class="alert alert-info mb-4">
        <h6 class="font-weight-bold">Debug Information:</h6>
        <ul class="mb-0">
            <?php foreach ($debug_info as $key => $value): ?>
                <li><strong><?= $key ?>:</strong> <?= $value ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Filters and Search -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Search & Filter Products</h6>
        </div>
        <div class="card-body">
            <form method="get" action="" class="row">
                <div class="col-md-4 mb-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, description or SKU">
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-control" id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= $category_filter == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2">Filter</button>
                    <a href="<?= VENDOR_URL ?>/products.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Products List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Products (<?= $total_products ?>)</h6>
            <a href="<?= VENDOR_URL ?>/add-product.php" class="btn btn-sm btn-primary">
                <i class="fas fa-plus fa-sm mr-1"></i> Add New
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($products)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-1"></i> No products found. 
                    <?php if (!empty($search) || $category_filter > 0 || !empty($status_filter)): ?>
                        Try changing your search or filter criteria.
                    <?php else: ?>
                        <a href="<?= VENDOR_URL ?>/add-product.php" class="alert-link">Add your first product</a>.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Date Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td class="text-center">
                                        <?php if (!empty($product['primary_image'])): ?>
                                            <img src="<?= SITE_URL ?>/assets/img/products/<?= $product['primary_image'] ?>" 
                                                alt="<?= htmlspecialchars($product['name']) ?>" 
                                                class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center" 
                                                style="width: 50px; height: 50px;">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="font-weight-bold"><?= htmlspecialchars($product['name']) ?></div>
                                        <small class="text-muted">SKU: <?= htmlspecialchars($product['sku'] ?? '-') ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></td>
                                    <td>
                                        <?php if (!empty($product['sale_price'])): ?>
                                            <span class="text-danger">Rp <?= number_format($product['sale_price'], 0, ',', '.') ?></span>
                                            <small class="text-muted"><del>Rp <?= number_format($product['price'], 0, ',', '.') ?></del></small>
                                        <?php else: ?>
                                            Rp <?= number_format($product['price'], 0, ',', '.') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        // Display stock using the correct column name
                                        $stock_value = 0;
                                        if (isset($product[$stock_column])) {
                                            $stock_value = $product[$stock_column];
                                        } elseif (isset($product['stock'])) {
                                            $stock_value = $product['stock'];
                                        } elseif (isset($product['quantity'])) {
                                            $stock_value = $product['quantity'];
                                        } elseif (isset($product['inventory'])) {
                                            $stock_value = $product['inventory'];
                                        }
                                        echo $stock_value;
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_badge = 'secondary';
                                        if ($product['status'] === 'active') $status_badge = 'success';
                                        if ($product['status'] === 'inactive') $status_badge = 'warning';
                                        if ($product['status'] === 'draft') $status_badge = 'info';
                                        ?>
                                        <span class="badge badge-<?= $status_badge ?>">
                                            <?= ucfirst($product['status']) ?>
                                        </span>
                                        <?php if (isset($product['is_featured']) && $product['is_featured']): ?>
                                            <span class="badge badge-primary ml-1">Featured</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($product['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="<?= VENDOR_URL ?>/edit_product.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" class="btn btn-sm btn-danger delete-product" data-id="<?= $product['id'] ?>" data-name="<?= htmlspecialchars($product['name']) ?>">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $category_filter > 0 ? '&category=' . $category_filter : '' ?><?= !empty($status_filter) ? '&status=' . $status_filter : '' ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">Previous</span>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $category_filter > 0 ? '&category=' . $category_filter : '' ?><?= !empty($status_filter) ? '&status=' . $status_filter : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $category_filter > 0 ? '&category=' . $category_filter : '' ?><?= !empty($status_filter) ? '&status=' . $status_filter : '' ?>">
                                        Next
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">Next</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Product Modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1" role="dialog" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteProductModalLabel">Confirm Delete</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete the product "<span id="deleteProductName"></span>"? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                <a class="btn btn-danger" href="#" id="confirmDelete">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Product delete confirmation
    const deleteButtons = document.querySelectorAll('.delete-product');
    const deleteModal = document.getElementById('deleteProductModal');
    const deleteProductName = document.getElementById('deleteProductName');
    const confirmDeleteButton = document.getElementById('confirmDelete');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.getAttribute('data-id');
            const productName = this.getAttribute('data-name');
            
            deleteProductName.textContent = productName;
            confirmDeleteButton.href = '<?= VENDOR_URL ?>/products.php?delete=' + productId;
            
            $(deleteModal).modal('show');
        });
    });
});
</script>

<?php include '../includes/vendor-footer.php'; ?>