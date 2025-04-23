<?php
require_once 'includes/config.php';

echo "<h1>Perbaikan Shop.php</h1>";

// 1. Buat direktori untuk gambar produk jika belum ada
$img_dir = __DIR__ . '/assets/img/products';
if (!is_dir($img_dir)) {
    mkdir($img_dir, 0755, true);
    echo "<p>✓ Membuat direktori gambar produk</p>";
} else {
    echo "<p>✓ Direktori gambar produk sudah ada</p>";
}

// 2. Buat gambar default jika belum ada
$default_img = $img_dir . '/default.jpg';
if (!file_exists($default_img)) {
    // Coba ambil gambar placeholder dari internet
    $default_content = @file_get_contents('https://via.placeholder.com/600x600.jpg?text=No+Image');
    if ($default_content) {
        file_put_contents($default_img, $default_content);
        echo "<p>✓ Membuat gambar default.jpg</p>";
    } else {
        // Jika tidak bisa mengambil dari internet, buat gambar kosong
        $img = imagecreatetruecolor(600, 600);
        $bg = imagecolorallocate($img, 240, 240, 240);
        $text_color = imagecolorallocate($img, 100, 100, 100);
        imagefilledrectangle($img, 0, 0, 599, 599, $bg);
        imagestring($img, 5, 240, 290, 'No Image', $text_color);
        imagejpeg($img, $default_img);
        imagedestroy($img);
        echo "<p>✓ Membuat gambar default.jpg alternatif</p>";
    }
}

// 3. Pastikan direktori ajax ada
$ajax_dir = __DIR__ . '/ajax';
if (!is_dir($ajax_dir)) {
    mkdir($ajax_dir, 0755, true);
    echo "<p>✓ Membuat direktori ajax</p>";
} else {
    echo "<p>✓ Direktori ajax sudah ada</p>";
}

// 4. Buat file add_to_cart.php
$add_to_cart_file = $ajax_dir . '/add_to_cart.php';
$add_to_cart_code = <<<'EOT'
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set header untuk respons JSON
header('Content-Type: application/json');

// Periksa apakah user sudah login
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Silahkan login untuk menambahkan barang ke keranjang.'
    ]);
    exit;
}

// Ambil data dari POST
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

// Validasi data
if ($product_id <= 0 || $quantity <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID produk atau jumlah tidak valid.'
    ]);
    exit;
}

// Periksa apakah produk ada
$product_query = "SELECT * FROM products WHERE id = $product_id";
$product_result = $conn->query($product_query);

if (!$product_result || $product_result->num_rows == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Produk tidak ditemukan.'
    ]);
    exit;
}

// Periksa apakah tabel cart ada
$table_exists = $conn->query("SHOW TABLES LIKE 'cart'")->num_rows > 0;
if (!$table_exists) {
    $create_table = "
    CREATE TABLE `cart` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `product_id` int(11) NOT NULL,
      `quantity` int(11) NOT NULL DEFAULT 1,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `user_product` (`user_id`,`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if (!$conn->query($create_table)) {
        echo json_encode([
            'success' => false,
            'message' => 'Tidak dapat membuat tabel cart: ' . $conn->error
        ]);
        exit;
    }
}

// Periksa apakah produk sudah ada di keranjang
$check_query = "SELECT id, quantity FROM cart WHERE user_id = $user_id AND product_id = $product_id";
$check_result = $conn->query($check_query);

if ($check_result && $check_result->num_rows > 0) {
    // Update jumlah
    $cart_item = $check_result->fetch_assoc();
    $new_quantity = $cart_item['quantity'] + $quantity;
    
    $update_query = "UPDATE cart SET quantity = $new_quantity, updated_at = NOW() WHERE id = {$cart_item['id']}";
    
    if (!$conn->query($update_query)) {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal memperbarui keranjang: ' . $conn->error
        ]);
        exit;
    }
} else {
    // Tambahkan item baru
    $insert_query = "INSERT INTO cart (user_id, product_id, quantity, created_at) VALUES ($user_id, $product_id, $quantity, NOW())";
    
    if (!$conn->query($insert_query)) {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menambahkan item ke keranjang: ' . $conn->error
        ]);
        exit;
    }
}

// Hitung total item di keranjang
$count_query = "SELECT SUM(quantity) as count FROM cart WHERE user_id = $user_id";
$count_result = $conn->query($count_query);
$cart_count = ($count_result && $count_result->num_rows > 0) ? $count_result->fetch_assoc()['count'] : 0;

// Return sukses
echo json_encode([
    'success' => true,
    'message' => 'Produk berhasil ditambahkan ke keranjang',
    'cart_count' => $cart_count
]);
EOT;

file_put_contents($add_to_cart_file, $add_to_cart_code);
echo "<p>✓ Membuat file add_to_cart.php</p>";

// 5. Perbaiki gambar produk di database
echo "<h2>Memeriksa Database</h2>";

$check_images = $conn->query("SELECT id, image FROM products WHERE image IS NULL OR image = ''");
if ($check_images && $check_images->num_rows > 0) {
    $update_count = 0;
    while ($row = $check_images->fetch_assoc()) {
        $update = $conn->query("UPDATE products SET image = 'default.jpg' WHERE id = {$row['id']}");
        if ($update) {
            $update_count++;
        }
    }
    echo "<p>✓ Memperbaiki {$update_count} produk yang tidak memiliki gambar</p>";
} else {
    echo "<p>✓ Semua produk sudah memiliki gambar</p>";
}

// 6. Perbaiki file shop.php
$shop_file = __DIR__ . '/shop.php';
if (file_exists($shop_file)) {
    // Backup file asli
    copy($shop_file, $shop_file . '.backup_' . date('Y-m-d_H-i-s'));
    
    // Baca konten file
    $content = file_get_contents($shop_file);
    
    // Buat script baru untuk menggantikan script yang rusak
    $new_script = <<<'NEWSCRIPT'
<script>
// Define site URL for JavaScript to use in AJAX calls and image paths
const SITE_URL = "<?= SITE_URL ?>";

// Live datetime update
function updateDateTime() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    
    document.getElementById('live-datetime').textContent = 
        `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

// Run immediately and then update every second
updateDateTime();
setInterval(updateDateTime, 1000);

// Function to show vendor tabs
function showVendorTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.vendor-tab-content');
    tabContents.forEach(tab => {
        tab.style.display = 'none';
    });
    
    // Show the selected tab
    document.getElementById('vendor-' + tabName + '-tab').style.display = 'block';
    
    // Update tab buttons
    const tabButtons = document.querySelectorAll('.vendor-tab');
    tabButtons.forEach(button => {
        button.classList.remove('active');
    });
    
    document.getElementById('tab-' + tabName).classList.add('active');
}

// Main document ready function
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - initializing shop features');
    
    // Check if Bootstrap is loaded
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap JavaScript is not loaded!');
        return;
    }
    
    // Initialize Bootstrap components
    let quickViewModal = null;
    let loginModal = null;
    let cartToast = null;
    
    const quickViewModalEl = document.getElementById('quickViewModal');
    const loginModalEl = document.getElementById('loginModal');
    const cartToastEl = document.getElementById('cartToast');
    
    if (quickViewModalEl) {
        quickViewModal = new bootstrap.Modal(quickViewModalEl);
    }
    
    if (loginModalEl) {
        loginModal = new bootstrap.Modal(loginModalEl);
    }
    
    if (cartToastEl) {
        cartToast = new bootstrap.Toast(cartToastEl);
    }
    
    // Quantity buttons in quick view modal
    const quantityMinus = document.getElementById('quantity-minus');
    const quantityPlus = document.getElementById('quantity-plus');
    const quickViewQuantity = document.getElementById('quick-view-quantity');
    
    if (quantityMinus && quantityPlus && quickViewQuantity) {
        quantityMinus.addEventListener('click', function() {
            const value = parseInt(quickViewQuantity.value);
            if (value > 1) {
                quickViewQuantity.value = value - 1;
            }
        });
        
        quantityPlus.addEventListener('click', function() {
            const value = parseInt(quickViewQuantity.value);
            quickViewQuantity.value = value + 1;
        });
    }
    
    // Quick View functionality
    const quickViewButtons = document.querySelectorAll('.quick-view');
    
    if (quickViewButtons.length > 0) {
        quickViewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-id');
                console.log('Quick view clicked for product ID:', productId);
                
                // Show loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                this.disabled = true;
                
                // Fetch product details
                fetch(`${SITE_URL}/shop.php?quick_view=${productId}`)
                    .then(response => {
                        // Check for HTTP errors
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Reset button state
                        this.innerHTML = originalText;
                        this.disabled = false;
                        
                        console.log('Product data received:', data);
                        
                        if (data.success && data.product) {
                            const product = data.product;
                            
                            // Set product data in modal
                            document.getElementById('quick-view-title').textContent = product.name;
                            document.getElementById('quick-view-category').textContent = product.category_name || 'Unknown Category';
                            document.getElementById('quick-view-vendor').textContent = product.vendor_name || 'Unknown Vendor';
                            
                            // Set price with proper formatting
                            if (product.sale_price && product.sale_price > 0) {
                                document.getElementById('quick-view-price').innerHTML = `
                                    <del class="text-muted">Rp ${Number(product.price).toLocaleString('id-ID')}</del>
                                    <span>Rp ${Number(product.sale_price).toLocaleString('id-ID')}</span>
                                `;
                            } else {
                                document.getElementById('quick-view-price').innerHTML = `
                                    <span>Rp ${Number(product.price).toLocaleString('id-ID')}</span>
                                `;
                            }
                            
                            // Set description if available
                            document.getElementById('quick-view-description').innerHTML = product.description || 'No description available.';
                            
                            // FIXED: Set image with error handling
                            const imageElement = document.getElementById('quick-view-image');
                            if (product.image) {
                                console.log('Setting image path:', `${SITE_URL}/assets/img/products/${product.image}`);
                                imageElement.src = `${SITE_URL}/assets/img/products/${product.image}`;
                                
                                // Add error handler if image fails to load
                                imageElement.onerror = function() {
                                    console.log('Image failed to load, using default');
                                    this.src = `${SITE_URL}/assets/img/products/default.jpg`;
                                };
                            } else {
                                console.log('No image, using default');
                                imageElement.src = `${SITE_URL}/assets/img/products/default.jpg`;
                            }
                            
                            // Set view details link
                            document.getElementById('quick-view-details').href = `${SITE_URL}/product.php?id=${product.id}`;
                            
                            // Set product ID for add to cart button
                            const addToCartBtn = document.getElementById('quick-view-add-to-cart');
                            addToCartBtn.setAttribute('data-product-id', product.id);
                            
                            // Reset quantity
                            document.getElementById('quick-view-quantity').value = 1;
                            
                            // Show modal
                            if (quickViewModal) {
                                quickViewModal.show();
                            }
                        } else {
                            console.error('Failed to load product details:', data.message);
                            alert('Failed to load product details: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        // Reset button state
                        this.innerHTML = originalText;
                        this.disabled = false;
                        
                        console.error('Error loading product details:', error);
                        alert('Error loading product details: ' + error.message);
                    });
            });
        });
    }
    
    // FIXED: Add to cart from quick view modal
    const quickViewAddToCartBtn = document.getElementById('quick-view-add-to-cart');
    if (quickViewAddToCartBtn) {
        quickViewAddToCartBtn.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            console.log('Add to cart clicked for product ID:', productId);
            
            if (!productId) {
                console.error('No product ID found for add to cart button');
                alert('Error: No product selected');
                return;
            }
            
            const quantity = document.getElementById('quick-view-quantity').value || 1;
            const isLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
            
            if (!isLoggedIn) {
                console.log('User not logged in, showing login modal');
                if (quickViewModal) quickViewModal.hide();
                if (loginModal) loginModal.show();
                return;
            }
            
            // Show loading state
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            this.disabled = true;
            
            // Add to cart via AJAX
            fetch(`${SITE_URL}/ajax/add_to_cart.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=${quantity}`
            })
            .then(response => {
                console.log('Server response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Reset button state
                this.innerHTML = originalText;
                this.disabled = false;
                
                console.log('Add to cart response:', data);
                
                if (data.success) {
                    // Close modal and show success message
                    if (quickViewModal) quickViewModal.hide();
                    if (cartToast) cartToast.show();
                    
                    // Update cart count in header if it exists
                    const cartCountElement = document.querySelector('.cart-count');
                    if (cartCountElement) {
                        cartCountElement.textContent = data.cart_count || 0;
                    }
                } else {
                    alert(data.message || 'Failed to add product to cart.');
                }
            })
            .catch(error => {
                // Reset button state
                this.innerHTML = originalText;
                this.disabled = false;
                
                console.error('Error adding to cart:', error);
                alert('An error occurred while adding the product to cart.');
            });
        });
    }
    
    // FIXED: Add to Cart functionality from product cards
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    
    if (addToCartButtons.length > 0) {
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const productId = this.getAttribute('data-id');
                console.log('Add to cart clicked from product card, ID:', productId);
                
                if (!productId) {
                    console.error('No product ID found for add to cart button');
                    return;
                }
                
                const isLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
                
                if (!isLoggedIn) {
                    if (loginModal) loginModal.show();
                    return;
                }
                
                // Show loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                this.disabled = true;
                
                // Add to cart via AJAX
                fetch(`${SITE_URL}/ajax/add_to_cart.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}&quantity=1`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Reset button state
                    this.innerHTML = originalText;
                    this.disabled = false;
                    
                    console.log('Add to cart response:', data);
                    
                    if (data.success) {
                        // Show success message
                        if (cartToast) cartToast.show();
                        
                        // Update cart count in header if it exists
                        const cartCountElement = document.querySelector('.cart-count');
                        if (cartCountElement) {
                            cartCountElement.textContent = data.cart_count || 0;
                        }
                    } else {
                        alert(data.message || 'Failed to add product to cart.');
                    }
                })
                .catch(error => {
                    // Reset button state
                    this.innerHTML = originalText;
                    this.disabled = false;
                    
                    console.error('Error adding to cart:', error);
                    alert('An error occurred while adding the product to cart.');
                });
            });
        });
    }
});
</script>
NEWSCRIPT;

    // Find the <script> tag at the end of the file and replace it
    $script_pattern = '/<script>\s*\/\/ Define site URL for JavaScript.*?<\/script>/s';
    
    if (preg_match($script_pattern, $content)) {
        $content = preg_replace($script_pattern, $new_script, $content);
        
        // Simpan perubahan ke file
        file_put_contents($shop_file, $content);
        echo "<p>✓ Memperbaiki script JavaScript di shop.php</p>";
    } else {
        echo "<p>⚠️ Tidak bisa menemukan script tag di shop.php untuk diganti. Manual fix diperlukan.</p>";
    }
} else {
    echo "<p>❌ File shop.php tidak ditemukan</p>";
}

echo "<div style='margin-top: 20px; padding: 15px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;'>
    <h3 style='color: #155724;'>Perbaikan Selesai!</h3>
    <p>Berikut adalah apa yang telah diperbaiki:</p>
    <ul>
        <li>Membuat direktori gambar produk jika belum ada</li>
        <li>Membuat gambar default.jpg untuk produk tanpa gambar</li>
        <li>Memperbaiki produk di database yang tidak memiliki gambar</li>
        <li>Membuat file add_to_cart.php di folder ajax</li>
        <li>Memperbaiki kode JavaScript yang rusak di shop.php</li>
    </ul>
    <p>Silakan kembali ke halaman shop dan coba lagi fitur quick view dan add to cart.</p>
    <p><a href='" . SITE_URL . "/shop.php' style='display: inline-block; background-color: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Kembali ke Shop</a></p>
</div>";
?>