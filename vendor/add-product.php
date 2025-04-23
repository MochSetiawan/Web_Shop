<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check vendor authentication
if (!isLoggedIn() || $_SESSION['role'] !== ROLE_VENDOR) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pageTitle = 'Add New Product';
$currentPage = 'products';

// Get current date and time
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

// Get database table structure
$products_columns = [];
$columns_result = $conn->query("SHOW COLUMNS FROM products");
if ($columns_result) {
    while ($column = $columns_result->fetch_assoc()) {
        $products_columns[] = $column['Field'];
    }
}

// Get product_images table structure to identify correct column names
$image_columns = [];
$image_columns_result = $conn->query("SHOW COLUMNS FROM product_images");
if ($image_columns_result) {
    while ($column = $image_columns_result->fetch_assoc()) {
        $image_columns[] = $column['Field'];
    }
}

// Determine correct column name for image path
$image_path_column = 'image'; // default
if (in_array('image_path', $image_columns)) {
    $image_path_column = 'image_path';
} else if (in_array('path', $image_columns)) {
    $image_path_column = 'path';
} else if (in_array('filename', $image_columns)) {
    $image_path_column = 'filename';
} else if (in_array('image', $image_columns)) {
    $image_path_column = 'image';
}

// Determine correct column name for primary image flag
$is_primary_column = 'is_primary'; // default
if (in_array('is_primary', $image_columns)) {
    $is_primary_column = 'is_primary';
} else if (in_array('primary', $image_columns)) {
    $is_primary_column = 'primary';
} else if (in_array('is_main', $image_columns)) {
    $is_primary_column = 'is_main';
} else if (in_array('main_image', $image_columns)) {
    $is_primary_column = 'main_image';
}

// Check if the stock/quantity column exists and determine its name
$stock_column_name = 'quantity'; // Default to "quantity"
if (in_array('stock', $products_columns)) {
    $stock_column_name = 'stock';
} elseif (in_array('inventory', $products_columns)) {
    $stock_column_name = 'inventory';
}

// Get categories
$categories_sql = "SELECT * FROM categories ORDER BY name ASC";
$categories_result = $conn->query($categories_sql);
$categories = [];
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Create uploads directory if it doesn't exist
$upload_dir = '../assets/img/products/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    // Get form data
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $price = floatval($_POST['price']);
    $sale_price = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : NULL;
    $category_id = intval($_POST['category_id']);
    $stock = intval($_POST['stock']);
    $$sku = sanitize($_POST['sku'] ?? '');
    if (empty($sku) || $sku === '-') {
        // Generate a unique SKU based on product name and timestamp
        $base_sku = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 5));
        $timestamp = date('mdHis'); // month, day, hour, minute, second
        $sku = $base_sku . '-' . $timestamp;
    }
    $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : NULL;
    $dimensions = sanitize($_POST['dimensions']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $status = sanitize($_POST['status']);
    
    // Handle package options
    $package_options = [];
    if (isset($_POST['package_name']) && is_array($_POST['package_name'])) {
        $package_names = $_POST['package_name'];
        $package_prices = $_POST['package_price'] ?? [];
        $package_stocks = $_POST['package_stock'] ?? [];
        
        foreach ($package_names as $key => $package_name) {
            if (!empty($package_name)) {
                $package_price = isset($package_prices[$key]) ? floatval($package_prices[$key]) : $price;
                $package_stock = isset($package_stocks[$key]) ? intval($package_stocks[$key]) : $stock;
                
                $package_options[] = [
                    'name' => sanitize($package_name),
                    'price' => $package_price,
                    'stock' => $package_stock
                ];
            }
        }
    }
    
    // Store package options as JSON
    $package_options_json = !empty($package_options) ? json_encode($package_options) : NULL;
    
    // Generate unique slug
    $slug = generateSlug($name);
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Product name is required";
    }
    
    if (empty($description)) {
        $errors[] = "Product description is required";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than zero";
    }
    
    if ($sale_price !== NULL && $sale_price >= $price) {
        $errors[] = "Sale price must be lower than regular price";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Please select a category";
    }
    
    if ($stock < 0) {
        $errors[] = "Stock quantity cannot be negative";
    }
    
    // Process product images
    $product_images = [];
    $has_primary = false;
    
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $file_count = count($_FILES['images']['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['images']['tmp_name'][$i];
                $name_file = $_FILES['images']['name'][$i];
                $extension = strtolower(pathinfo($name_file, PATHINFO_EXTENSION));
                
                // Validate file type
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($extension, $allowed_extensions)) {
                    $errors[] = "File '$name_file' has an invalid extension. Allowed: " . implode(', ', $allowed_extensions);
                    continue;
                }
                
                // Validate file size (max 5MB)
                if ($_FILES['images']['size'][$i] > 5 * 1024 * 1024) {
                    $errors[] = "File '$name_file' exceeds the maximum file size of 5MB";
                    continue;
                }
                
                // Generate unique filename
                $new_filename = uniqid('product_') . '.' . $extension;
                $destination = $upload_dir . $new_filename;
                
                // Is this the primary image?
                $is_primary = isset($_POST['primary_image']) && $_POST['primary_image'] == $i;
                if ($is_primary) {
                    $has_primary = true;
                }
                
                $product_images[] = [
                    'tmp_name' => $tmp_name,
                    'filename' => $new_filename,
                    'destination' => $destination,
                    'is_primary' => $is_primary ? 1 : 0
                ];
            }
        }
    }
    
    // If no images are uploaded or no primary image selected
    if (empty($product_images)) {
        $errors[] = "Please upload at least one product image";
    } elseif (!$has_primary) {
        // Make the first image primary if none was selected
        $product_images[0]['is_primary'] = 1;
    }
    
    // Debug information
    $debug_info = [
        'Image path column' => $image_path_column,
        'Is primary column' => $is_primary_column,
        'Stock column' => $stock_column_name,
        'Image columns' => implode(', ', $image_columns)
    ];
    
    // If there are no errors, save the product
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Add package options to product data if the column exists
            $has_package_column = in_array('package_options', $products_columns);
            
            // Create insert SQL with or without package options
            if ($has_package_column) {
                $product_sql = "INSERT INTO products (vendor_id, category_id, name, description, price, 
                                             sale_price, $stock_column_name, sku, slug, package_options, status, created_at)
                             VALUES ($vendor_id, $category_id, '$name', '$description', $price, 
                                    " . ($sale_price === NULL ? "NULL" : $sale_price) . ", 
                                    $stock, '$sku', '$slug', " . ($package_options_json === NULL ? "NULL" : "'$package_options_json'") . ", '$status', NOW())";
            } else {
                $product_sql = "INSERT INTO products (vendor_id, category_id, name, description, price, 
                                             sale_price, $stock_column_name, sku, slug, status, created_at)
                             VALUES ($vendor_id, $category_id, '$name', '$description', $price, 
                                    " . ($sale_price === NULL ? "NULL" : $sale_price) . ", 
                                    $stock, '$sku', '$slug', '$status', NOW())";
            }
            
            if (!$conn->query($product_sql)) {
                throw new Exception("Failed to add product: " . $conn->error . " (SQL: $product_sql)");
            }
            
            $product_id = $conn->insert_id;
            
            // Upload images and insert image records
            foreach ($product_images as $image) {
                if (move_uploaded_file($image['tmp_name'], $image['destination'])) {
                    $is_primary_value = $image['is_primary'];
                    $filename = $image['filename'];
                    
                    // Build a dynamic SQL query based on the actual columns
                    $image_sql = "INSERT INTO product_images (product_id, $image_path_column";
                    
                    if (in_array($is_primary_column, $image_columns)) {
                        $image_sql .= ", $is_primary_column";
                    }
                    
                    if (in_array('created_at', $image_columns)) {
                        $image_sql .= ", created_at";
                    }
                    
                    $image_sql .= ") VALUES ($product_id, '$filename'";
                    
                    if (in_array($is_primary_column, $image_columns)) {
                        $image_sql .= ", $is_primary_value";
                    }
                    
                    if (in_array('created_at', $image_columns)) {
                        $image_sql .= ", NOW()";
                    }
                    
                    $image_sql .= ")";
                    
                    if (!$conn->query($image_sql)) {
                        throw new Exception("Failed to add product image: " . $conn->error . " (SQL: $image_sql)");
                    }
                    
                    // If this is the primary image, also update it in the products table if needed
                    if ($is_primary_value == 1 && in_array('image', $products_columns)) {
                        $update_sql = "UPDATE products SET image = '$filename' WHERE id = $product_id";
                        $conn->query($update_sql);
                    }
                } else {
                    throw new Exception("Failed to upload image: " . $image['filename']);
                }
            }
            
            // If package options are provided but no package_options column exists in the database,
            // create product variants as separate products
            if (!$has_package_column && !empty($package_options)) {
                foreach ($package_options as $option) {
                    $variant_name = $name . ' - ' . $option['name'];
                    $variant_slug = generateSlug($variant_name);
                    $variant_price = $option['price'];
                    $variant_stock = $option['stock'];
                    
                    $variant_sql = "INSERT INTO products (vendor_id, category_id, name, description, price, 
                                             sale_price, $stock_column_name, sku, slug, status, parent_id, created_at)
                                 VALUES ($vendor_id, $category_id, '$variant_name', '$description', $variant_price, 
                                        " . ($sale_price === NULL ? "NULL" : $sale_price) . ", 
                                        $variant_stock, '$sku-{$option['name']}', '$variant_slug', '$status', $product_id, NOW())";
                    
                    $conn->query($variant_sql);
                    $variant_id = $conn->insert_id;
                    
                    // Copy the primary image to the variant
                    foreach ($product_images as $image) {
                        if ($image['is_primary'] == 1) {
                            $variant_image_sql = "INSERT INTO product_images (product_id, $image_path_column";
                            
                            if (in_array($is_primary_column, $image_columns)) {
                                $variant_image_sql .= ", $is_primary_column";
                            }
                            
                            if (in_array('created_at', $image_columns)) {
                                $variant_image_sql .= ", created_at";
                            }
                            
                            $variant_image_sql .= ") VALUES ($variant_id, '{$image['filename']}'";
                            
                            if (in_array($is_primary_column, $image_columns)) {
                                $variant_image_sql .= ", 1";
                            }
                            
                            if (in_array('created_at', $image_columns)) {
                                $variant_image_sql .= ", NOW()";
                            }
                            
                            $variant_image_sql .= ")";
                            
                            $conn->query($variant_image_sql);
                            
                            // Update the image in the products table if needed
                            if (in_array('image', $products_columns)) {
                                $conn->query("UPDATE products SET image = '{$image['filename']}' WHERE id = $variant_id");
                            }
                            
                            break;
                        }
                    }
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Set success message and redirect
            $_SESSION['success_message'] = "Product added successfully!";
            header('Location: ' . VENDOR_URL . '/products.php');
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction if something went wrong
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

include '../includes/vendor-header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Add New Product</h1>
        <a href="<?= VENDOR_URL ?>/products.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i> Back to Products
        </a>
    </div>
    
    <!-- User Welcome Banner with Live Clock -->
    <div class="card mb-4 border-left-primary">
        <div class="card-body py-3">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Current User's Login: <?= htmlspecialchars($current_user) ?>
                    </div>
                    <div class="text-xs text-gray-800">
                        Current Date and Time (Indonesia): 
                        <span id="live-datetime">2025-03-22 10:52:13</span>
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-user-clock fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>
    
    <?php if (isset($debug_info) && defined('DEBUG') && DEBUG): ?>
    <div class="alert alert-info">
        <h6 class="font-weight-bold">Debug Information:</h6>
        <ul class="mb-0">
            <?php foreach ($debug_info as $key => $value): ?>
                <li><?= $key ?>: <?= $value ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Product Information</h6>
        </div>
        <div class="card-body">
            <form method="post" action="" enctype="multipart/form-data" id="productForm">
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Basic Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Basic Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?= $_POST['name'] ?? '' ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="6" required><?= $_POST['description'] ?? '' ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                        <select class="form-control" id="category_id" name="category_id" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= $category['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="sku" class="form-label">SKU</label>
                                        <input type="text" class="form-control" id="sku" name="sku" value="<?= $_POST['sku'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pricing and Inventory -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Pricing & Inventory</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="price" class="form-label">Regular Price <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">Rp</span>
                                            </div>
                                            <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" value="<?= $_POST['price'] ?? '' ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="sale_price" class="form-label">Sale Price</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">Rp</span>
                                            </div>
                                            <input type="number" class="form-control" id="sale_price" name="sale_price" min="0" step="0.01" value="<?= $_POST['sale_price'] ?? '' ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="stock" class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="stock" name="stock" min="0" value="<?= $_POST['stock'] ?? '1' ?>" required>
                                    <small class="form-text text-muted">This will be saved as "<?= $stock_column_name ?>" in the database</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Package Options -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Package Options</h6>
                                <button type="button" class="btn btn-sm btn-primary" id="addPackageOption">
                                    <i class="fas fa-plus"></i> Add Option
                                </button>
                            </div>
                            <div class="card-body">
                                <p class="mb-3">Add different package options or variants for this product (e.g., sizes, colors, bundles).</p>
                                
                                <div id="packageOptionsContainer">
                                    <!-- Package options will be added here dynamically -->
                                </div>
                                
                                <div class="text-muted small mt-2">
                                    <i class="fas fa-info-circle mr-1"></i> Each package option can have its own price and stock level.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Shipping -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Shipping</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="weight" class="form-label">Weight (kg)</label>
                                        <input type="number" class="form-control" id="weight" name="weight" min="0" step="0.01" value="<?= $_POST['weight'] ?? '' ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="dimensions" class="form-label">Dimensions (L × W × H)</label>
                                        <input type="text" class="form-control" id="dimensions" name="dimensions" placeholder="Example: 25 × 15 × 5 cm" value="<?= $_POST['dimensions'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Product Status -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Status</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Product Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="active" <?= (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                                        <option value="draft" <?= (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : '' ?>>Draft</option>
                                        <option value="inactive" <?= (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" <?= isset($_POST['is_featured']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_featured">
                                        Featured Product
                                    </label>
                                    <small class="form-text text-muted">Featured products are displayed prominently on the homepage</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Product Images -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Product Images</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Upload Images <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" id="productImages" name="images[]" accept="image/*" multiple>
                                    <small class="form-text text-muted">You can upload multiple images (max 5 images, 5MB each)</small>
                                </div>
                                
                                <div id="imagePreviewContainer" class="mb-3 d-none">
                                    <label class="form-label">Image Preview</label>
                                    <div id="imagePreview" class="row"></div>
                                </div>
                                
                                <div id="primaryImageContainer" class="mb-3 d-none">
                                    <label class="form-label">Primary Image <span class="text-danger">*</span></label>
                                    <p class="small text-muted">Select which image to use as the primary product image</p>
                                    <div id="primaryImageOptions"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-between">
                    <a href="<?= VENDOR_URL ?>/products.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="add_product" class="btn btn-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Add Product
                    </button>
                </div>
            </form>
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

document.addEventListener('DOMContentLoaded', function() {
    // Package Options Management
    const packageOptionsContainer = document.getElementById('packageOptionsContainer');
    const addPackageOptionBtn = document.getElementById('addPackageOption');
    let optionCount = 0;

    // Add a new package option row
    function addPackageOptionRow() {
        const optionRow = document.createElement('div');
        optionRow.className = 'package-option-row border rounded p-3 mb-3';
        optionRow.dataset.index = optionCount;

        optionRow.innerHTML = `
            <div class="d-flex justify-content-between mb-2">
                <h6 class="font-weight-bold mb-0">Package Option ${optionCount + 1}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger remove-option" data-index="${optionCount}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="row">
                <div class="col-md-12 mb-2">
                    <label for="package_name_${optionCount}" class="form-label">Option Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="package_name_${optionCount}" name="package_name[${optionCount}]" placeholder="e.g., Basic, Premium, Small, Medium, etc." required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-2">
                    <label for="package_price_${optionCount}" class="form-label">Price (Rp)</label>
                    <input type="number" class="form-control" id="package_price_${optionCount}" name="package_price[${optionCount}]" min="0" step="0.01" placeholder="Leave blank to use default price">
                </div>
                <div class="col-md-6 mb-2">
                    <label for="package_stock_${optionCount}" class="form-label">Stock</label>
                    <input type="number" class="form-control" id="package_stock_${optionCount}" name="package_stock[${optionCount}]" min="0" placeholder="Leave blank to use default stock">
                </div>
            </div>
        `;

        packageOptionsContainer.appendChild(optionRow);

        // Add event listener to remove button
        const removeBtn = optionRow.querySelector('.remove-option');
        removeBtn.addEventListener('click', function() {
            const index = this.dataset.index;
            const row = document.querySelector(`.package-option-row[data-index="${index}"]`);
            row.remove();
            updatePackageOptionLabels();
        });

        optionCount++;
    }

    // Update package option labels after removal
    function updatePackageOptionLabels() {
        const optionRows = document.querySelectorAll('.package-option-row');
        optionRows.forEach((row, index) => {
            const label = row.querySelector('h6');
            label.textContent = `Package Option ${index + 1}`;
        });
    }

    // Add package option button click handler
    addPackageOptionBtn.addEventListener('click', addPackageOptionRow);

    // Add at least one package option by default
    addPackageOptionRow();

    // Product Images Preview
    const productImagesInput = document.getElementById('productImages');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const imagePreview = document.getElementById('imagePreview');
    const primaryImageContainer = document.getElementById('primaryImageContainer');
    const primaryImageOptions = document.getElementById('primaryImageOptions');
    const MAX_FILES = 5;
    
    productImagesInput.addEventListener('change', function() {
        // Clear previous previews
        imagePreview.innerHTML = '';
        primaryImageOptions.innerHTML = '';
        
        // Check number of files
        if (this.files.length > MAX_FILES) {
            alert(`You can only upload a maximum of ${MAX_FILES} images`);
            this.value = '';
            imagePreviewContainer.classList.add('d-none');
            primaryImageContainer.classList.add('d-none');
            return;
        }
        
        if (this.files.length === 0) {
            imagePreviewContainer.classList.add('d-none');
            primaryImageContainer.classList.add('d-none');
            return;
        }
        
        imagePreviewContainer.classList.remove('d-none');
        primaryImageContainer.classList.remove('d-none');
        
        // Create previews
        for (let i = 0; i < this.files.length; i++) {
            const file = this.files[i];
            
            // Validate file size
            if (file.size > 5 * 1024 * 1024) {
                alert(`File "${file.name}" exceeds the 5MB size limit`);
                continue;
            }
            
            // Create preview element
            const col = document.createElement('div');
            col.className = 'col-md-6 mb-3';
            
            const card = document.createElement('div');
            card.className = 'card h-100';
            
            const img = document.createElement('img');
            img.className = 'card-img-top';
            img.style.height = '150px';
            img.style.objectFit = 'cover';
            
            const cardBody = document.createElement('div');
            cardBody.className = 'card-body p-2 text-center';
            
            const fileName = document.createElement('small');
            fileName.textContent = file.name;
            
            // Load image preview
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
            
            // Add elements to card
            cardBody.appendChild(fileName);
            card.appendChild(img);
            card.appendChild(cardBody);
            col.appendChild(card);
            imagePreview.appendChild(col);
            
            // Create primary image radio button option
            const radioDiv = document.createElement('div');
            radioDiv.className = 'form-check mb-2';
            
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.className = 'form-check-input';
            radio.name = 'primary_image';
            radio.id = `primary_image_${i}`;
            radio.value = i;
            
            // Make first image selected by default
            if (i === 0) {
                radio.checked = true;
            }
            
            const radioLabel = document.createElement('label');
            radioLabel.className = 'form-check-label';
            radioLabel.htmlFor = `primary_image_${i}`;
            radioLabel.textContent = `Image ${i + 1} - ${file.name}`;
            
            radioDiv.appendChild(radio);
            radioDiv.appendChild(radioLabel);
            primaryImageOptions.appendChild(radioDiv);
        }
    });
    
    // Form validation before submit
    document.getElementById('productForm').addEventListener('submit', function(event) {
        const name = document.getElementById('name').value.trim();
        const description = document.getElementById('description').value.trim();
        const price = parseFloat(document.getElementById('price').value);
        const salePrice = document.getElementById('sale_price').value.trim() !== '' ? 
            parseFloat(document.getElementById('sale_price').value) : null;
        const categoryId = document.getElementById('category_id').value;
        
        let hasErrors = false;
        
        if (!name) {
            alert('Please enter a product name');
            hasErrors = true;
        } else if (!description) {
            alert('Please enter a product description');
            hasErrors = true;
        } else if (isNaN(price) || price <= 0) {
            alert('Please enter a valid price greater than zero');
            hasErrors = true;
        } else if (salePrice !== null && salePrice >= price) {
            alert('Sale price must be lower than regular price');
            hasErrors = true;
        } else if (!categoryId) {
            alert('Please select a category');
            hasErrors = true;
        } else if (productImagesInput.files.length === 0) {
            alert('Please upload at least one product image');
            hasErrors = true;
        }
        
        // Validate all package option names are filled
        const packageNameInputs = document.querySelectorAll('input[name^="package_name"]');
        for (let i = 0; i < packageNameInputs.length; i++) {
            if (!packageNameInputs[i].value.trim()) {
                alert('Please fill in all package option names');
                hasErrors = true;
                break;
            }
        }
        
        // Validate package prices are valid
        const packagePriceInputs = document.querySelectorAll('input[name^="package_price"]');
        for (let i = 0; i < packagePriceInputs.length; i++) {
            const packagePrice = packagePriceInputs[i].value.trim();
            if (packagePrice && (isNaN(parseFloat(packagePrice)) || parseFloat(packagePrice) < 0)) {
                alert('Please enter valid package prices');
                hasErrors = true;
                break;
            }
        }
        
        // Validate package stocks are valid
        const packageStockInputs = document.querySelectorAll('input[name^="package_stock"]');
        for (let i = 0; i < packageStockInputs.length; i++) {
            const packageStock = packageStockInputs[i].value.trim();
            if (packageStock && (isNaN(parseInt(packageStock)) || parseInt(packageStock) < 0)) {
                alert('Please enter valid package stocks');
                hasErrors = true;
                break;
            }
        }
        
        if (hasErrors) {
            event.preventDefault();
        }
    });
    
    // Price and sale price validation
    const priceInput = document.getElementById('price');
    const salePriceInput = document.getElementById('sale_price');
    
    salePriceInput.addEventListener('input', function() {
        if (this.value.trim() !== '') {
            const price = parseFloat(priceInput.value);
            const salePrice = parseFloat(this.value);
            
            if (salePrice >= price) {
                this.setCustomValidity('Sale price must be lower than regular price');
            } else {
                this.setCustomValidity('');
            }
        } else {
            this.setCustomValidity('');
        }
    });
    
    priceInput.addEventListener('input', function() {
        if (salePriceInput.value.trim() !== '') {
            const price = parseFloat(this.value);
            const salePrice = parseFloat(salePriceInput.value);
            
            if (salePrice >= price) {
                salePriceInput.setCustomValidity('Sale price must be lower than regular price');
            } else {
                salePriceInput.setCustomValidity('');
            }
        }
    });
    
    // Auto-fill package price and stock when base price/stock changes
    priceInput.addEventListener('change', function() {
        const basePrice = parseFloat(this.value) || 0;
        const packagePriceInputs = document.querySelectorAll('input[name^="package_price"]');
        
        packagePriceInputs.forEach(input => {
            if (!input.value.trim()) {
                input.setAttribute('placeholder', `Leave blank to use default price (${basePrice})`);
            }
        });
    });
    
    const stockInput = document.getElementById('stock');
    stockInput.addEventListener('change', function() {
        const baseStock = parseInt(this.value) || 0;
        const packageStockInputs = document.querySelectorAll('input[name^="package_stock"]');
        
        packageStockInputs.forEach(input => {
            if (!input.value.trim()) {
                input.setAttribute('placeholder', `Leave blank to use default stock (${baseStock})`);
            }
        });
    });
});
</script>

<?php include '../includes/vendor-footer.php'; ?>