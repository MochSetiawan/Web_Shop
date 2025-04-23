<?php
// Sanitize input
function sanitize($data) {
    global $conn;
    return $conn->real_escape_string(trim($data));
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === ROLE_ADMIN;
}

// Check if user is vendor
function isVendor() {
    return isLoggedIn() && $_SESSION['role'] === ROLE_VENDOR;
}

// Check if user is customer
function isCustomer() {
    return isLoggedIn() && $_SESSION['role'] === ROLE_CUSTOMER;
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'Please log in to access this page';
        $_SESSION['redirect'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

// Require admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['error'] = 'Access denied. Admin privileges required';
        header('Location: ' . SITE_URL);
        exit;
    }
}

// Require vendor
function requireVendor() {
    requireLogin();
    if (!isVendor()) {
        $_SESSION['error'] = 'Access denied. Vendor privileges required';
        header('Location: ' . SITE_URL);
        exit;
    }
}

// Get user by ID
function getUserById($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Get vendor by user ID
function getVendorByUserId($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM vendors WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Get all categories
function getAllCategories() {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    return $categories;
}

// Get category by ID
function getCategoryById($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Get featured products
function getFeaturedProducts($limit = 8) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT p.*, pi.image, v.shop_name, c.name as category_name
        FROM products p
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
        JOIN vendors v ON p.vendor_id = v.id
        JOIN categories c ON p.category_id = c.id
        WHERE p.featured = 1 AND p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    return $products;
}

// Get latest products
function getLatestProducts($limit = 8) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT p.*, pi.image, v.shop_name, c.name as category_name
        FROM products p
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
        JOIN vendors v ON p.vendor_id = v.id
        JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    return $products;
}

// Get product by ID
function getProductById($id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT p.*, v.shop_name, v.user_id as vendor_user_id, c.name as category_name
        FROM products p
        JOIN vendors v ON p.vendor_id = v.id
        JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Get product images
function getProductImages($productId) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = [];
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
    return $images;
}

// Format price
function formatPrice($price) {
    return 'Rp ' . number_format($price, 0, ',', '.');
}

// Generate slug
function generateSlug($text) {
    // Replace non-alphanumeric characters with hyphens
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    // Transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    // Trim
    $text = trim($text, '-');
    // Remove duplicate hyphens
    $text = preg_replace('~-+~', '-', $text);
    // Convert to lowercase
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

// Get cart items
function getCartItems() {
    if (!isLoggedIn()) {
        return [];
    }
    
    global $conn;
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT c.*, p.name, p.price, p.sale_price, pi.image, v.shop_name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
        JOIN vendors v ON p.vendor_id = v.id
        WHERE c.user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    return $items;
}

// Get cart count
function getCartCount() {
    if (!isLoggedIn()) {
        return 0;
    }
    
    global $conn;
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

// Get cart total
function getCartTotal() {
    $items = getCartItems();
    $total = 0;
    
    foreach ($items as $item) {
        $price = $item['sale_price'] ? $item['sale_price'] : $item['price'];
        $total += $price * $item['quantity'];
    }
    
    return $total;
}

// Display messages
function displayMessages() {
    $output = '';
    
    if (isset($_SESSION['success'])) {
        $output .= '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
        unset($_SESSION['success']);
    }
    
    if (isset($_SESSION['error'])) {
        $output .= '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
        unset($_SESSION['error']);
    }
    
    if (isset($_SESSION['warning'])) {
        $output .= '<div class="alert alert-warning">' . $_SESSION['warning'] . '</div>';
        unset($_SESSION['warning']);
    }
    
    if (isset($_SESSION['info'])) {
        $output .= '<div class="alert alert-info">' . $_SESSION['info'] . '</div>';
        unset($_SESSION['info']);
    }
    
    return $output;
}

/**
 * Validasi voucher dan berikan informasi diskon
 * 
 * @param string $code Kode voucher
 * @param float $total_amount Total belanja
 * @param int $user_id ID user (opsional)
 * @return array Informasi voucher jika valid, atau array dengan error jika tidak valid
 */
function validateVoucher($code, $total_amount, $user_id = null) {
    global $conn;
    
    $code = trim($conn->real_escape_string($code));
    
    if (empty($code)) {
        return ['valid' => false, 'message' => 'Kode voucher tidak boleh kosong.'];
    }
    
    // Periksa apakah voucher ada dan masih valid
    $query = "SELECT * FROM vouchers 
              WHERE code = ? 
              AND is_active = 1 
              AND start_date <= NOW() 
              AND end_date >= NOW()";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return ['valid' => false, 'message' => 'Error sistem: ' . $conn->error];
    }
    
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['valid' => false, 'message' => 'Kode voucher tidak valid atau sudah kedaluwarsa.'];
    }
    
    $voucher = $result->fetch_assoc();
    
    // Periksa penggunaan maksimal
    if ($voucher['max_usage'] !== null && $voucher['usage_count'] >= $voucher['max_usage']) {
        return ['valid' => false, 'message' => 'Voucher ini sudah mencapai batas penggunaan maksimal.'];
    }
    
    // Periksa minimum pembelian
    if ($total_amount < $voucher['min_purchase']) {
        return [
            'valid' => false, 
            'message' => 'Minimum pembelian untuk voucher ini adalah ' . formatPrice($voucher['min_purchase']) . '.'
        ];
    }
    
    // Hitung diskon
    $discount_amount = 0;
    
    if ($voucher['discount_type'] === 'percentage') {
        $discount_amount = $total_amount * ($voucher['discount_value'] / 100);
    } else { // fixed
        $discount_amount = $voucher['discount_value'];
        
        // Pastikan diskon tidak melebihi total belanja
        if ($discount_amount > $total_amount) {
            $discount_amount = $total_amount;
        }
    }
    
    // Jika berhasil, siapkan data yang akan dikembalikan
    return [
        'valid' => true,
        'voucher_id' => $voucher['id'],
        'code' => $voucher['code'],
        'discount_type' => $voucher['discount_type'],
        'discount_value' => $voucher['discount_value'],
        'discount_amount' => $discount_amount,
        'message' => 'Voucher berhasil diterapkan. Anda mendapatkan potongan ' . 
                    ($voucher['discount_type'] === 'percentage' 
                        ? $voucher['discount_value'] . '%' 
                        : formatPrice($discount_amount)) . '.'
    ];
}

/**
 * Aplikasikan voucher ke pesanan
 * 
 * @param int $order_id ID order
 * @param int $voucher_id ID voucher
 * @param float $discount_amount Jumlah diskon
 * @return bool Berhasil atau tidak
 */
function applyVoucherToOrder($order_id, $voucher_id, $discount_amount) {
    global $conn;
    
    // Update pesanan dengan informasi voucher
    $query = "UPDATE orders SET 
              voucher_id = ?, 
              discount_amount = ?,
              final_amount = total_amount - ?
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('iddi', $voucher_id, $discount_amount, $discount_amount, $order_id);
    $result = $stmt->execute();
    
    if ($result) {
        // Tingkatkan counter penggunaan voucher
        $update_query = "UPDATE vouchers SET usage_count = usage_count + 1 WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('i', $voucher_id);
        $stmt->execute();
        
        return true;
    }
    
    return false;
}

// Upload image
function uploadImage($file, $directory) {
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Check if directory exists, if not create it
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    // Get file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Allowed extensions
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    
    // Check if extension is allowed
    if (!in_array($extension, $allowed)) {
        return false;
    }
    
    // Generate unique filename
    $filename = uniqid() . '.' . $extension;
    
    // Move file to directory
    if (move_uploaded_file($file['tmp_name'], $directory . $filename)) {
        return $filename;
    }
    
    return false;
}
?>