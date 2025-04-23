<?php
require_once 'includes/config.php';

echo "<h1>Shop.php Column Fix Tool</h1>";

// 1. Load the shop.php file
$shop_file_path = 'shop.php';
if (!file_exists($shop_file_path)) {
    die("<div style='color: red; padding: 15px; background: #ffeeee; border: 1px solid red;'>
         shop.php file not found!
         </div>");
}

$shop_content = file_get_contents($shop_file_path);
$backup_file = 'shop.php.backup_' . date('Y-m-d_H-i-s');
file_put_contents($backup_file, $shop_content);

echo "<div style='color: green; padding: 15px; background: #eeffee; border: 1px solid green;'>
      Created backup: $backup_file
      </div>";

// 2. Fix the column references
$replacements = [
    'v.name' => 'v.shop_name',
    'vendor_details[\'name\']' => 'vendor_details[\'shop_name\']',
    '= $vendor_details[\'name\']' => '= $vendor_details[\'shop_name\']',
    'htmlspecialchars($vendor_details[\'name\'])' => 'htmlspecialchars($vendor_details[\'shop_name\'])',
    'name as vendor_name' => 'shop_name as vendor_name',
    'v.description' => 'v.description', // Keep as is if no change needed
];

$changes_made = 0;
foreach ($replacements as $from => $to) {
    $count = 0;
    $new_content = str_replace($from, $to, $shop_content, $count);
    if ($count > 0) {
        $shop_content = $new_content;
        $changes_made += $count;
    }
}

// 3. Save the fixed file
if ($changes_made > 0) {
    file_put_contents($shop_file_path, $shop_content);
    echo "<div style='color: green; padding: 15px; background: #eeffee; border: 1px solid green;'>
          Updated shop.php file with $changes_made changes! The file now uses correct column names.
          </div>";
} else {
    echo "<div style='color: orange; padding: 15px; background: #fff9e6; border: 1px solid orange;'>
          No changes needed in shop.php file.
          </div>";
}

// 4. Add sample vendor if none exists
$check_vendors = "SELECT COUNT(*) as count FROM vendors";
$vendor_count = $conn->query($check_vendors)->fetch_assoc()['count'];

if ($vendor_count == 0) {
    echo "<h2>No vendors found. Creating sample vendor...</h2>";
    
    // First check if we have a user to link to
    $check_users = "SELECT id FROM users WHERE role = 'vendor' LIMIT 1";
    $user_result = $conn->query($check_users);
    
    $user_id = ($user_result && $user_result->num_rows > 0) 
        ? $user_result->fetch_assoc()['id'] 
        : null;
    
    if (!$user_id) {
        // Create vendor user
        $create_user = "INSERT INTO users (username, email, password, full_name, role, status) 
                       VALUES ('vendoruser', 'vendor@example.com', 
                       '$2y$10$sLPzqA6.0FpKn9Z7OwqhOOLURDSUVSPQKUZgeh3Wpt5JaJkkL1P2W', 
                       'Vendor User', 'vendor', 'active')";
        
        if ($conn->query($create_user)) {
            $user_id = $conn->insert_id;
            echo "<div style='color: green; padding: 10px; background: #eeffee; border: 1px solid green;'>
                  Created vendor user with ID: $user_id
                  </div>";
        } else {
            echo "<div style='color: red; padding: 10px; background: #ffeeee; border: 1px solid red;'>
                  Failed to create vendor user: " . $conn->error . "
                  </div>";
            exit;
        }
    }
    
    // Create vendor
    $create_vendor = "INSERT INTO vendors (user_id, shop_name, description, status) 
                     VALUES ($user_id, 'Sample Shop', 'This is a sample vendor shop for testing.', 'active')";
    
    if ($conn->query($create_vendor)) {
        $vendor_id = $conn->insert_id;
        echo "<div style='color: green; padding: 10px; background: #eeffee; border: 1px solid green;'>
              Created sample vendor with ID: $vendor_id
              </div>";
        
        // Now create a sample product
        $check_categories = "SELECT id FROM categories LIMIT 1";
        $category_result = $conn->query($check_categories);
        
        if ($category_result && $category_result->num_rows > 0) {
            $category_id = $category_result->fetch_assoc()['id'];
            
            $create_product = "INSERT INTO products 
                              (vendor_id, category_id, name, slug, description, 
                              short_description, price, quantity, status) 
                              VALUES 
                              ($vendor_id, $category_id, 'Sample Product', 
                              'sample-product-" . time() . "', 'This is a sample product.', 
                              'Sample product for testing', 99.99, 10, 'active')";
            
            if ($conn->query($create_product)) {
                $product_id = $conn->insert_id;
                echo "<div style='color: green; padding: 10px; background: #eeffee; border: 1px solid green;'>
                      Created sample product with ID: $product_id
                      </div>";
            } else {
                echo "<div style='color: red; padding: 10px; background: #ffeeee; border: 1px solid red;'>
                      Failed to create sample product: " . $conn->error . "
                      </div>";
            }
        } else {
            echo "<div style='color: red; padding: 10px; background: #ffeeee; border: 1px solid red;'>
                  No categories found to create sample product.
                  </div>";
        }
    } else {
        echo "<div style='color: red; padding: 10px; background: #ffeeee; border: 1px solid red;'>
              Failed to create sample vendor: " . $conn->error . "
              </div>";
    }
}

echo "<p><a href='" . SITE_URL . "/shop.php' style='display: inline-block; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Go to Shop</a></p>";
?>