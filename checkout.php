<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Cek apakah pengguna sudah login
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Silakan login untuk melanjutkan checkout.";
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pageTitle = 'Checkout';

// Current time untuk display
$current_datetime = date('Y-m-d H:i:s');
$current_user = $_SESSION['username'] ?? 'MochSetiawan';

// Cek jika keranjang kosong
$cart_sql = "SELECT COUNT(*) as count FROM cart_items WHERE user_id = $user_id";
$cart_result = $conn->query($cart_sql);
$cart_count = 0;

if ($cart_result && $cart_result->num_rows > 0) {
    $cart_data = $cart_result->fetch_assoc();
    $cart_count = $cart_data['count'];
}

if ($cart_count === 0) {
    $_SESSION['error_message'] = "Keranjang belanja Anda kosong. Silakan tambahkan produk terlebih dahulu.";
    header('Location: ' . SITE_URL . '/cart.php');
    exit;
}

// Ambil data pengguna
$user_sql = "SELECT * FROM users WHERE id = $user_id";
$user_result = $conn->query($user_sql);
$user = $user_result->fetch_assoc();

// Buat tabel user_balance jika belum ada
$balance_check = $conn->query("SHOW TABLES LIKE 'user_balance'");
if ($balance_check->num_rows == 0) {
    $conn->query("CREATE TABLE user_balance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        balance DECIMAL(10,2) DEFAULT 0.00,
        last_topup_date TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}

// Buat tabel topup_history jika belum ada
$topup_history_check = $conn->query("SHOW TABLES LIKE 'topup_history'");
if ($topup_history_check->num_rows == 0) {
    $conn->query("CREATE TABLE topup_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        transaction_id VARCHAR(100) NOT NULL,
        status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}

// Ambil saldo pengguna
$balance_sql = "SELECT * FROM user_balance WHERE user_id = $user_id";
$balance_result = $conn->query($balance_sql);

if ($balance_result && $balance_result->num_rows > 0) {
    $balance_data = $balance_result->fetch_assoc();
    $user_balance = $balance_data['balance'];
} else {
    // Jika belum ada data balance, buat baru
    $conn->query("INSERT INTO user_balance (user_id, balance) VALUES ($user_id, 0)");
    $user_balance = 0;
}

// Ambil item keranjang
$cart_items_sql = "SELECT ci.id as cart_id, ci.quantity, 
                 p.id as product_id, p.name, p.price, p.sale_price, p.quantity as stock, p.vendor_id,
                 v.shop_name as vendor_name,
                 (SELECT pi.image FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as image
                 FROM cart_items ci
                 JOIN products p ON ci.product_id = p.id
                 JOIN vendors v ON p.vendor_id = v.id
                 WHERE ci.user_id = $user_id";
$cart_items_result = $conn->query($cart_items_sql);
$cart_items = [];

if ($cart_items_result && $cart_items_result->num_rows > 0) {
    while ($row = $cart_items_result->fetch_assoc()) {
        $cart_items[] = $row;
    }
}

// Hitung total
$subtotal = 0;
$shipping_fee = 10000; // Biaya pengiriman flat
$tax_rate = 0.10; // 10% pajak

foreach ($cart_items as $item) {
    $item_price = ($item['sale_price'] > 0) ? $item['sale_price'] : $item['price'];
    $subtotal += $item_price * $item['quantity'];
}

$tax_amount = $subtotal * $tax_rate;
$total_amount = $subtotal + $shipping_fee + $tax_amount;

// Cek stok produk
$stock_error = false;
$error_message = '';

foreach ($cart_items as $item) {
    if ($item['quantity'] > $item['stock']) {
        $stock_error = true;
        $error_message .= "Stok produk '{$item['name']}' tidak mencukupi. Tersedia: {$item['stock']}.<br>";
    }
}

// Proses checkout
$order_success = false;
$order_id = null;
$payment_error = null;
$order_number = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Cek stok sekali lagi
    if ($stock_error) {
        $payment_error = $error_message;
    } 
    // Cek saldo
    elseif ($user_balance < $total_amount) {
        $payment_error = "Saldo Anda tidak mencukupi. Silakan top up terlebih dahulu.";
    }
    else {
        // Mulai transaksi database
        $conn->begin_transaction();
        
        try {
            // Buat nomor pesanan
            $order_number = 'ORD' . date('Ymd') . rand(1000, 9999);
            
            // Simpan data pesanan
            $shipping_name = $conn->real_escape_string($_POST['shipping_name']);
            $shipping_phone = $conn->real_escape_string($_POST['shipping_phone']);
            $shipping_address = $conn->real_escape_string($_POST['shipping_address']);
            $shipping_city = $conn->real_escape_string($_POST['shipping_city']);
            $shipping_state = $conn->real_escape_string($_POST['shipping_state']);
            $shipping_postal_code = $conn->real_escape_string($_POST['shipping_postal_code']);
            $shipping_country = $conn->real_escape_string($_POST['shipping_country']);
            $notes = $conn->real_escape_string($_POST['notes'] ?? '');
            
            // Insert order
            $insert_order_sql = "INSERT INTO orders (
                user_id, order_number, status, total_amount, shipping_amount, tax_amount,
                shipping_address, shipping_city, shipping_state, shipping_postal_code, shipping_country,
                payment_method, payment_status, notes
            ) VALUES (
                $user_id, '$order_number', 'pending', $total_amount, $shipping_fee, $tax_amount,
                '$shipping_address', '$shipping_city', '$shipping_state', '$shipping_postal_code', '$shipping_country',
                'e_wallet', 'paid', '$notes'
            )";
            
            if ($conn->query($insert_order_sql)) {
                $order_id = $conn->insert_id;
                
                // Insert order items dan kurangi stok
                foreach ($cart_items as $item) {
                    $product_id = $item['product_id'];
                    $quantity = $item['quantity'];
                    $price = ($item['sale_price'] > 0) ? $item['sale_price'] : $item['price'];
                    $total = $price * $quantity;
                    $vendor_id = $item['vendor_id'];
                    
                    // Insert order item
                    $insert_item_sql = "INSERT INTO order_items (
                        order_id, product_id, vendor_id, quantity, price, total, status
                    ) VALUES (
                        $order_id, $product_id, $vendor_id, $quantity, $price, $total, 'pending'
                    )";
                    
                    $conn->query($insert_item_sql);
                    
                    // Kurangi stok
                    $conn->query("UPDATE products SET quantity = quantity - $quantity WHERE id = $product_id");
                }
                
                // Kurangi saldo pengguna
                $conn->query("UPDATE user_balance SET balance = balance - $total_amount WHERE user_id = $user_id");
                
                // Catat riwayat pembayaran
                $payment_id = 'PAY' . time() . rand(1000, 9999);
                $conn->query("INSERT INTO topup_history (
                    user_id, amount, payment_method, transaction_id, status, description
                ) VALUES (
                    $user_id, -$total_amount, 'e_wallet', '$payment_id', 'completed', 'Pembayaran pesanan #$order_number'
                )");
                
                // Kosongkan keranjang
                $conn->query("DELETE FROM cart_items WHERE user_id = $user_id");
                
                // Commit transaksi
                $conn->commit();
                
                $order_success = true;
            } else {
                throw new Exception("Gagal menyimpan pesanan: " . $conn->error);
            }
        } catch (Exception $e) {
            // Rollback jika terjadi error
            $conn->rollback();
            $payment_error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<?php if ($order_success): ?>
<!-- Halaman Sukses -->
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body text-center p-5">
                    <i class="fas fa-check-circle text-success fa-5x mb-3"></i>
                    <h2 class="mb-3">Pesanan Berhasil!</h2>
                    <p class="lead">Terima kasih telah berbelanja di ShopVerse. Pesanan Anda telah berhasil diproses.</p>
                    <div class="alert alert-info mb-4">
                        <p class="mb-0">Nomor Pesanan: <strong><?= $order_number ?></strong></p>
                        <p class="mb-0">Total Pembayaran: <strong><?= formatPrice($total_amount) ?></strong></p>
                        <p class="mb-0">Status Pembayaran: <span class="badge bg-success">Sudah Dibayar</span></p>
                    </div>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <a href="<?= SITE_URL ?>/order_detail.php?id=<?= $order_id ?>" class="btn btn-primary">
                            <i class="fas fa-list-alt me-2"></i> Detail Pesanan
                        </a>
                        <a href="<?= SITE_URL ?>" class="btn btn-outline-primary">
                            <i class="fas fa-shopping-bag me-2"></i> Lanjut Belanja
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Halaman Checkout -->
<div class="container py-4">
    <!-- Banner Info User -->
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

    <h1 class="h3 mb-4">Checkout</h1>
    
    <?php if ($payment_error): ?>
        <div class="alert alert-danger"><?= $payment_error ?></div>
    <?php endif; ?>
    
    <?php if ($stock_error): ?>
        <div class="alert alert-warning">
            <?= $error_message ?>
            <a href="<?= SITE_URL ?>/cart.php" class="alert-link">Kembali ke keranjang</a> untuk mengubah jumlah.
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Form Pengiriman -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Informasi Pengiriman</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="" id="checkout-form">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="shipping_name" class="form-label">Nama Penerima <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="shipping_name" name="shipping_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="shipping_phone" class="form-label">Telepon <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="shipping_phone" name="shipping_phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="shipping_address" class="form-label">Alamat <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="shipping_address" name="shipping_address" rows="3" required><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="shipping_city" class="form-label">Kota <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="shipping_city" name="shipping_city" value="<?= htmlspecialchars($user['city'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="shipping_state" class="form-label">Provinsi <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="shipping_state" name="shipping_state" value="<?= htmlspecialchars($user['state'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="shipping_postal_code" class="form-label">Kode Pos <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="shipping_postal_code" name="shipping_postal_code" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="shipping_country" class="form-label">Negara <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="shipping_country" name="shipping_country" value="<?= htmlspecialchars($user['country'] ?? 'Indonesia') ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Catatan Pesanan</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Tambahkan catatan untuk pesanan Anda (opsional)"></textarea>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Daftar Produk -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Produk yang Dibeli</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="row mb-3 pb-3 border-bottom">
                            <div class="col-2 col-md-1">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="<?= SITE_URL ?>/assets/img/products/<?= $item['image'] ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>" class="img-thumbnail" 
                                         style="width: 60px; height: 60px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light d-flex justify-content-center align-items-center" 
                                         style="width: 60px; height: 60px;">
                                        <i class="fas fa-image text-secondary"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-10 col-md-7">
                                <h6 class="mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                                <small class="text-muted">Penjual: <?= htmlspecialchars($item['vendor_name']) ?></small>
                                <div class="mt-1">
                                    <?php if ($item['sale_price'] > 0): ?>
                                        <span class="text-muted text-decoration-line-through me-2"><?= formatPrice($item['price']) ?></span>
                                        <span class="fw-bold"><?= formatPrice($item['sale_price']) ?></span>
                                    <?php else: ?>
                                        <span class="fw-bold"><?= formatPrice($item['price']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-12 col-md-2 text-md-end mt-2 mt-md-0">
                                <div class="text-muted">Jumlah: <?= $item['quantity'] ?></div>
                            </div>
                            <div class="col-12 col-md-2 text-md-end mt-2 mt-md-0">
                                <?php
                                    $item_price = ($item['sale_price'] > 0) ? $item['sale_price'] : $item['price'];
                                    $item_total = $item_price * $item['quantity'];
                                ?>
                                <div class="fw-bold"><?= formatPrice($item_total) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Ringkasan Pembayaran -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Ringkasan Pembayaran</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <span><?= formatPrice($subtotal) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Biaya Pengiriman</span>
                        <span><?= formatPrice($shipping_fee) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Pajak (10%)</span>
                        <span><?= formatPrice($tax_amount) ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3 fw-bold">
                        <span>Total</span>
                        <span class="text-primary"><?= formatPrice($total_amount) ?></span>
                    </div>
                    
                    <!-- Metode Pembayaran -->
                    <div class="mb-3">
                        <h6>Metode Pembayaran</h6>
                        <div class="card border mb-3">
                            <div class="card-body p-3">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio" name="payment_method" id="payment_e_wallet" checked>
                                    <label class="form-check-label d-flex align-items-center" for="payment_e_wallet">
                                        <i class="fas fa-wallet text-primary me-2"></i>
                                        <div>
                                            <strong>Saldo Elektronik</strong>
                                            <div class="small text-muted">Saldo tersedia: <?= formatPrice($user_balance) ?></div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($user_balance < $total_amount): ?>
                            <div class="alert alert-warning mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Saldo Anda tidak mencukupi. 
                                <a href="<?= SITE_URL ?>/profile.php" class="alert-link">Top up sekarang</a>.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" form="checkout-form" name="place_order" class="btn btn-primary btn-block w-100 mb-3"
                            <?= ($stock_error || $user_balance < $total_amount) ? 'disabled' : '' ?>>
                        <i class="fas fa-check me-2"></i> Bayar Sekarang
                    </button>
                    
                    <a href="<?= SITE_URL ?>/cart.php" class="btn btn-outline-secondary btn-block w-100">
                        <i class="fas fa-arrow-left me-2"></i> Kembali ke Keranjang
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Fungsi untuk format tanggal sebagai YYYY-MM-DD HH:MM:SS
function formatDateTime(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

// Update tampilan waktu
function updateDateTime() {
    const now = new Date();
    document.getElementById('live-datetime').textContent = formatDateTime(now);
}

// Jalankan segera dan perbarui setiap detik
updateDateTime();
setInterval(updateDateTime, 1000);
</script>

<?php include 'includes/footer.php'; ?>