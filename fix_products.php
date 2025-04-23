<?php
require_once 'includes/config.php';

echo '<h1>Products Display Fix Tool</h1>';

// Check if there are any products in the database
$check_query = "SELECT COUNT(*) as total FROM products";
$check_result = $conn->query($check_query);
$total_in_db = ($check_result) ? $check_result->fetch_assoc()['total'] : 0;

echo "<p>Total products in database: $total_in_db</p>";

if ($total_in_db == 0) {
    echo '<div style="color: red; padding: 10px; background: #ffeeee; border: 1px solid red; margin: 10px 0;">
            No products found in database at all. You need to add products first.
          </div>';
    exit;
}

// Check for products without vendors
$no_vendor_query = "SELECT COUNT(*) as count FROM products WHERE vendor_id IS NULL OR vendor_id = 0";
$no_vendor_result = $conn->query($no_vendor_query);
$no_vendor_count = ($no_vendor_result) ? $no_vendor_result->fetch_assoc()['count'] : 0;

echo "<p>Products without vendor: $no_vendor_count</p>";

// Check for products without categories
$no_category_query = "SELECT COUNT(*) as count FROM products WHERE category_id IS NULL OR category_id = 0";
$no_category_result = $conn->query($no_category_query);
$no_category_count = ($no_category_result) ? $no_category_result->fetch_assoc()['count'] : 0;

echo "<p>Products without category: $no_category_count</p>";

// Get all vendors
$vendors_query = "SELECT id, name FROM vendors";
$vendors_result = $conn->query($vendors_query);
$vendors = [];

if ($vendors_result && $vendors_result->num_rows > 0) {
    while ($row = $vendors_result->fetch_assoc()) {
        $vendors[$row['id']] = $row['name'];
    }
}

// Get all categories
$categories_query = "SELECT id, name FROM categories";
$categories_result = $conn->query($categories_query);
$categories = [];

if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[$row['id']] = $row['name'];
    }
}

// List all products with their vendor and category
echo '<h2>All Products</h2>';

if (count($vendors) == 0) {
    echo '<div style="color: red; padding: 10px; background: #ffeeee; border: 1px solid red; margin: 10px 0;">
            No vendors found in database. You need to add at least one vendor.
          </div>';
    
    // Create a default vendor
    echo '<h3>Create Default Vendor</h3>';
    echo '<form method="post" action="">';
    echo '<input type="submit" name="create_vendor" value="Create Default Vendor">';
    echo '</form>';
    
    if (isset($_POST['create_vendor'])) {
        $insert_vendor = "INSERT INTO vendors (name, description, created_at) VALUES ('Default Vendor', 'Default vendor for products', NOW())";
        if ($conn->query($insert_vendor)) {
            $vendor_id = $conn->insert_id;
            echo "<div style='color: green; padding: 10px; background: #eeffee; border: 1px solid green; margin: 10px 0;'>
                    Default vendor created with ID: $vendor_id
                  </div>";
            
            // Refresh page
            echo "<meta http-equiv='refresh' content='2'>";
        } else {
            echo "<div style='color: red; padding: 10px; background: #ffeeee; border: 1px solid red; margin: 10px 0;'>
                    Failed to create default vendor: " . $conn->error . "
                  </div>";
        }
    }
    
    exit;
}

if (count($categories) == 0) {
    echo '<div style="color: red; padding: 10px; background: #ffeeee; border: 1px solid red; margin: 10px 0;">
            No categories found in database. You need to add at least one category.
          </div>';
    
    // Create a default category
    echo '<h3>Create Default Category</h3>';
    echo '<form method="post" action="">';
    echo '<input type="submit" name="create_category" value="Create Default Category">';
    echo '</form>';
    
    if (isset($_POST['create_category'])) {
        $insert_category = "INSERT INTO categories (name, created_at) VALUES ('Default Category', NOW())";
        if ($conn->query($insert_category)) {
            $category_id = $conn->insert_id;
            echo "<div style='color: green; padding: 10px; background: #eeffee; border: 1px solid green; margin: 10px 0;'>
                    Default category created with ID: $category_id
                  </div>";
            
            // Refresh page
            echo "<meta http-equiv='refresh' content='2'>";
        } else {
            echo "<div style='color: red; padding: 10px; background: #ffeeee; border: 1px solid red; margin: 10px 0;'>
                    Failed to create default category: " . $conn->error . "
                  </div>";
        }
    }
    
    exit;
}

// Display products table
$products_query = "SELECT p.*, v.name as vendor_name, c.name as category_name 
                  FROM products p 
                  LEFT JOIN vendors v ON p.vendor_id = v.id 
                  LEFT JOIN categories c ON p.category_id = c.id";
$products_result = $conn->query($products_query);

echo '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
echo '<tr style="background-color: #f2f2f2;">
        <th>ID</th>
        <th>Name</th>
        <th>Price</th>
        <th>Vendor</th>
        <th>Category</th>
        <th>Actions</th>
      </tr>';

if ($products_result && $products_result->num_rows > 0) {
    while ($product = $products_result->fetch_assoc()) {
        $vendor_name = $product['vendor_name'] ?? 'No Vendor';
        $category_name = $product['category_name'] ?? 'No Category';
        
        echo '<tr>';
        echo '<td>' . $product['id'] . '</td>';
        echo '<td>' . htmlspecialchars($product['name']) . '</td>';
        echo '<td>Rp ' . number_format($product['price'], 0, ',', '.') . '</td>';
        echo '<td>' . htmlspecialchars($vendor_name) . '</td>';
        echo '<td>' . htmlspecialchars($category_name) . '</td>';
        echo '<td><a href="?fix_product=' . $product['id'] . '">Fix Relationships</a></td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="6">No products found or error in query.</td></tr>';
}

echo '</table>';

// Handle fixing a specific product
if (isset($_GET['fix_product'])) {
    $product_id = (int)$_GET['fix_product'];
    
    // Get product details
    $product_query = "SELECT * FROM products WHERE id = $product_id";
    $product_result = $conn->query($product_query);
    
    if ($product_result && $product_result->num_rows > 0) {
        $product = $product_result->fetch_assoc();
        
        echo '<h2>Fix Product: ' . htmlspecialchars($product['name']) . '</h2>';
        
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="product_id" value="' . $product_id . '">';
        
        echo '<div style="margin-bottom: 15px;">';
        echo '<label>Vendor:</label>';
        echo '<select name="vendor_id" style="width: 300px; padding: 5px;">';
        foreach ($vendors as $id => $name) {
            $selected = ($id == $product['vendor_id']) ? 'selected' : '';
            echo '<option value="' . $id . '" ' . $selected . '>' . htmlspecialchars($name) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '<div style="margin-bottom: 15px;">';
        echo '<label>Category:</label>';
        echo '<select name="category_id" style="width: 300px; padding: 5px;">';
        foreach ($categories as $id => $name) {
            $selected = ($id == $product['category_id']) ? 'selected' : '';
            echo '<option value="' . $id . '" ' . $selected . '>' . htmlspecialchars($name) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '<input type="submit" name="update_product" value="Update Product" style="padding: 8px 15px;">';
        echo '</form>';
    }
}

// Handle updating a product
if (isset($_POST['update_product'])) {
    $product_id = (int)$_POST['product_id'];
    $vendor_id = (int)$_POST['vendor_id'];
    $category_id = (int)$_POST['category_id'];
    
    $update_query = "UPDATE products SET vendor_id = $vendor_id, category_id = $category_id WHERE id = $product_id";
    
    if ($conn->query($update_query)) {
        echo "<div style='color: green; padding: 10px; background: #eeffee; border: 1px solid green; margin: 10px 0;'>
                Product updated successfully!
              </div>";
    } else {
        echo "<div style='color: red; padding: 10px; background: #ffeeee; border: 1px solid red; margin: 10px 0;'>
                Failed to update product: " . $conn->error . "
              </div>";
    }
}

// Add a "Fix All Products" option
echo '<h2>Quick Fix Options</h2>';

echo '<form method="post" action="">';
echo '<div style="margin-bottom: 15px;">';
echo '<h3>Assign All Products to Default Vendor and Category</h3>';
echo '<label>Default Vendor:</label>';
echo '<select name="default_vendor_id" style="width: 300px; padding: 5px; margin-right: 10px;">';
foreach ($vendors as $id => $name) {
    echo '<option value="' . $id . '">' . htmlspecialchars($name) . '</option>';
}
echo '</select>';

echo '<label>Default Category:</label>';
echo '<select name="default_category_id" style="width: 300px; padding: 5px;">';
foreach ($categories as $id => $name) {
    echo '<option value="' . $id . '">' . htmlspecialchars($name) . '</option>';
}
echo '</select>';
echo '</div>';

echo '<input type="submit" name="fix_all_products" value="Fix All Products" style="padding: 8px 15px;">';
echo '</form>';

// Handle fix all products
if (isset($_POST['fix_all_products'])) {
    $default_vendor_id = (int)$_POST['default_vendor_id'];
    $default_category_id = (int)$_POST['default_category_id'];
    
    // Update products with missing vendor or category
    $update_query = "UPDATE products SET 
                    vendor_id = CASE WHEN vendor_id IS NULL OR vendor_id = 0 THEN $default_vendor_id ELSE vendor_id END,
                    category_id = CASE WHEN category_id IS NULL OR category_id = 0 THEN $default_category_id ELSE category_id END";
    
    if ($conn->query($update_query)) {
        echo "<div style='color: green; padding: 10px; background: #eeffee; border: 1px solid green; margin: 10px 0;'>
                All products updated successfully!
              </div>";
        
        // Refresh page
        echo "<meta http-equiv='refresh' content='2'>";
    } else {
        echo "<div style='color: red; padding: 10px; background: #ffeeee; border: 1px solid red; margin: 10px 0;'>
                Failed to update all products: " . $conn->error . "
              </div>";
    }
}

// CRITICAL FIX: Test shop query with the exact query from shop.php
echo '<h2>TEST: Main Shop Query</h2>';
echo '<p>This tests the exact query used in shop.php to verify it returns products correctly.</p>';

// Simplified version of the same query used in shop.php
$test_query = "SELECT p.*, c.name as category_name, v.name as vendor_name, v.id as vendor_id 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN vendors v ON p.vendor_id = v.id 
              WHERE 1=1";

// Try to debug the query execution
try {
    $test_result = $conn->query($test_query);
    
    if ($test_result) {
        $num_rows = $test_result->num_rows;
        echo "<p>Query executed successfully. Products found: $num_rows</p>";
        
        if ($num_rows > 0) {
            echo '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
            echo '<tr style="background-color: #f2f2f2;">
                    <th>ID</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Vendor</th>
                    <th>Category</th>
                  </tr>';
            
            while ($row = $test_result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . $row['id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>Rp ' . number_format($row['price'], 0, ',', '.') . '</td>';
                echo '<td>' . htmlspecialchars($row['vendor_name'] ?? 'No Vendor') . '</td>';
                echo '<td>' . htmlspecialchars($row['category_name'] ?? 'No Category') . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
        } else {
            echo '<div style="color: red; padding: 10px; background: #ffeeee; border: 1px solid red; margin: 10px 0;">
                    The main query returns 0 rows despite products existing in the database. This indicates a JOIN problem.
                  </div>';
        }
    } else {
        echo '<div style="color: red; padding: 10px; background: #ffeeee; border: 1px solid red; margin: 10px 0;">
                Query error: ' . $conn->error . '
              </div>';
    }
} catch (Exception $e) {
    echo '<div style="color: red; padding: 10px; background: #ffeeee; border: 1px solid red; margin: 10px 0;">
            Exception: ' . $e->getMessage() . '
          </div>';
}

// Add a button to go back to the shop
echo '<p><a href="' . SITE_URL . '/shop.php" style="padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;">Return to Shop</a></p>';
?>