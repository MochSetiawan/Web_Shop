<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Cek apakah pengguna sudah login
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Silakan login untuk melihat detail pesanan.";
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pageTitle = 'Detail Pesanan';
$current_user = $_SESSION['username'] ?? 'MochSetiawan';

// Cek parameter ID pesanan
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID pesanan tidak valid.";
    header('Location: ' . SITE_URL . '/orders.php');
    exit;
}

$order_id = (int)$_GET['id'];

// Ambil data pesanan
$order_sql = "SELECT o.*, u.username, u.email 
             FROM orders o 
             JOIN users u ON o.user_id = u.id 
             WHERE o.id = $order_id AND o.user_id = $user_id";
             
// Jika admin, izinkan melihat semua pesanan
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $order_sql = "SELECT o.*, u.username, u.email 
                 FROM orders o 
                 JOIN users u ON o.user_id = u.id 
                 WHERE o.id = $order_id";
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'vendor') {
    // Vendor hanya bisa melihat pesanan yang berisi produk mereka
    $vendor_sql = "SELECT id FROM vendors WHERE user_id = $user_id";
    $vendor_result = $conn->query($vendor_sql);
    
    if ($vendor_result && $vendor_result->num_rows > 0) {
        $vendor_data = $vendor_result->fetch_assoc();
        $vendor_id = $vendor_data['id'];
        
        $order_sql = "SELECT o.*, u.username, u.email 
                     FROM orders o 
                     JOIN users u ON o.user_id = u.id 
                     JOIN order_items oi ON o.id = oi.order_id 
                     WHERE o.id = $order_id AND oi.vendor_id = $vendor_id 
                     GROUP BY o.id";
    }
}

$order_result = $conn->query($order_sql);

if (!$order_result || $order_result->num_rows === 0) {
    $_SESSION['error_message'] = "Pesanan tidak ditemukan atau Anda tidak memiliki akses.";
    header('Location: ' . SITE_URL . '/orders.php');
    exit;
}

$order = $order_result->fetch_assoc();

// Ambil item pesanan
$items_sql = "SELECT oi.*, 
             p.name as product_name, p.slug as product_slug, 
             v.shop_name as vendor_name,
             (SELECT pi.image FROM product_images pi WHERE pi.product_id = oi.product_id AND pi.is_main = 1 LIMIT 1) as product_image
             FROM order_items oi
             JOIN products p ON oi.product_id = p.id
             JOIN vendors v ON oi.vendor_id = v.id
             WHERE oi.order_id = $order_id";
             
// Jika vendor, hanya tampilkan produk mereka
if (isset($_SESSION['role']) && $_SESSION['role'] === 'vendor' && isset($vendor_id)) {
    $items_sql .= " AND oi.vendor_id = $vendor_id";
}

$items_result = $conn->query($items_sql);
$order_items = [];

if ($items_result && $items_result->num_rows > 0) {
    while ($row = $items_result->fetch_assoc()) {
        $order_items[] = $row;
    }
}

// Current time untuk display
$current_datetime = date('Y-m-d H:i:s');

// Proses pembatalan pesanan (hanya untuk customer dan status pesanan 'pending')
$cancel_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    if ((!isset($_SESSION['role']) || $_SESSION['role'] === 'customer') && $order['status'] === 'pending') {
        // Update status pesanan
        $update_sql = "UPDATE orders SET status = 'cancelled', payment_status = 'refunded' WHERE id = $order_id AND user_id = $user_id";
        
        if ($conn->query($update_sql)) {
            // Update status item pesanan
            $conn->query("UPDATE order_items SET status = 'cancelled' WHERE order_id = $order_id");
            
            // Kembalikan stok produk
            foreach ($order_items as $item) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];
                $conn->query("UPDATE products SET quantity = quantity + $quantity WHERE id = $product_id");
            }
            
            // Kembalikan uang ke saldo
            $refund_amount = $order['total_amount'];
            $conn->query("UPDATE user_balance SET balance = balance + $refund_amount WHERE user_id = $user_id");
            
            // Catat pengembalian dana
            $refund_id = 'REF' . time() . rand(1000, 9999);
            $conn->query("INSERT INTO topup_history (
                user_id, amount, payment_method, transaction_id, status, description
            ) VALUES (
                $user_id, $refund_amount, 'refund', '$refund_id', 'completed', 'Pengembalian dana pesanan #" . $order['order_number'] . "'
            )");
            
            // Refresh data pesanan
            $order_result = $conn->query($order_sql);
            $order = $order_result->fetch_assoc();
            
            $cancel_message = 'success';
        } else {
            $cancel_message = 'error';
        }
    } else {
        $cancel_message = 'invalid';
    }
}

include 'includes/header.php';
?>

<!-- Tambahkan setelah informasi vendor -->
<?php
// Kode ini untuk mengganti bagian yang error di order_detail.php
// Tempatkan setelah detail pesanan dan sebelum detail produk

// PERBAIKAN: Dapatkan vendor info dari order_id, bukan dari $product
$vendor_info_query = "SELECT DISTINCT v.id as vendor_id, v.shop_name, u.username 
                    FROM vendors v 
                    JOIN users u ON v.user_id = u.id
                    JOIN products p ON p.vendor_id = v.id
                    JOIN order_items oi ON oi.product_id = p.id
                    WHERE oi.order_id = " . $order_id;

$vendor_info_result = $conn->query($vendor_info_query);

// Pastikan query berhasil dan ada data
if ($vendor_info_result && $vendor_info_result->num_rows > 0) {
    $vendor_info = $vendor_info_result->fetch_assoc();
    $vendor_id = $vendor_info['vendor_id'];
    $vendor_name = $vendor_info['shop_name'] ?: $vendor_info['username'];
?>
    <!-- Tampilkan informasi vendor dan tombol chat jika user adalah customer -->
    <?php if (isLoggedIn() && $_SESSION['role'] === 'customer'): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="m-0 font-weight-bold">Informasi Penjual</h6>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5><?= htmlspecialchars($vendor_name) ?></h5>
                        <p class="text-muted mb-0">
                            <i class="fas fa-store mr-1"></i> Penjual Produk
                        </p>
                    </div>
                    <div class="col-md-4 text-right">
                        <a href="<?= SITE_URL ?>/chat.php?partner_id=<?= $vendor_id ?>" 
                           class="btn btn-primary btn-block">
                            <i class="fas fa-comments mr-2"></i> Chat dengan Penjual
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php } ?>
    </div>
</div>

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

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">Detail Pesanan #<?= $order['order_number'] ?></h1>
        <a href="<?= SITE_URL ?>/orders.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar Pesanan
        </a>
    </div>
    
    <?php if ($cancel_message === 'success'): ?>
        <div class="alert alert-success">Pesanan berhasil dibatalkan dan dana telah dikembalikan ke saldo Anda.</div>
    <?php elseif ($cancel_message === 'error'): ?>
        <div class="alert alert-danger">Gagal membatalkan pesanan. Silakan coba lagi.</div>
    <?php elseif ($cancel_message === 'invalid'): ?>
        <div class="alert alert-warning">Pesanan tidak dapat dibatalkan karena sudah diproses.</div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Informasi Pesanan -->
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Informasi Pesanan</h5>
                    <?php
                    // Tampilkan badge status
                    $status_badge = '';
                    switch ($order['status']) {
                        case 'pending':
                            $status_badge = '<span class="badge bg-warning">Tertunda</span>';
                            break;
                        case 'processing':
                            $status_badge = '<span class="badge bg-info">Diproses</span>';
                            break;
                        case 'shipped':
                            $status_badge = '<span class="badge bg-primary">Dikirim</span>';
                            break;
                        case 'delivered':
                            $status_badge = '<span class="badge bg-success">Terkirim</span>';
                            break;
                        case 'cancelled':
                            $status_badge = '<span class="badge bg-danger">Dibatalkan</span>';
                            break;
                        default:
                            $status_badge = '<span class="badge bg-secondary">' . ucfirst($order['status']) . '</span>';
                    }
                    ?>
                    <div>Status: <?= $status_badge ?></div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Nomor Pesanan:</strong></p>
                            <p><?= $order['order_number'] ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Tanggal Pesanan:</strong></p>
                            <p><?= date('d M Y H:i', strtotime($order['created_at'])) ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Pelanggan:</strong></p>
                            <p><?= htmlspecialchars($order['username']) ?> (<?= htmlspecialchars($order['email']) ?>)</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Metode Pembayaran:</strong></p>
                            <p>
                                <?php if ($order['payment_method'] === 'e_wallet'): ?>
                                    Saldo Elektronik
                                <?php else: ?>
                                    <?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Status Pembayaran:</strong></p>
                            <p>
                                <?php if ($order['payment_status'] === 'paid'): ?>
                                    <span class="badge bg-success">Sudah Dibayar</span>
                                <?php elseif ($order['payment_status'] === 'pending'): ?>
                                    <span class="badge bg-warning">Tertunda</span>
                                <?php elseif ($order['payment_status'] === 'failed'): ?>
                                    <span class="badge bg-danger">Gagal</span>
                                <?php elseif ($order['payment_status'] === 'refunded'): ?>
                                    <span class="badge bg-info">Dikembalikan</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Total Pembayaran:</strong></p>
                            <p class="text-primary fw-bold"><?= formatPrice($order['total_amount']) ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($order['notes'])): ?>
                        <div class="alert alert-light mb-0">
                            <p class="mb-1"><strong>Catatan Pesanan:</strong></p>
                            <p class="mb-0"><?= htmlspecialchars($order['notes']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Alamat Pengiriman -->
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3">
                    <h5 class="mb-0">Alamat Pengiriman</h5>
                </div>
                <div class="card-body">
                    <address>
                        <strong><?= htmlspecialchars($order['username']) ?></strong><br>
                        <?= nl2br(htmlspecialchars($order['shipping_address'])) ?><br>
                        <?= htmlspecialchars($order['shipping_city']) ?>, <?= htmlspecialchars($order['shipping_state']) ?> <?= htmlspecialchars($order['shipping_postal_code']) ?><br>
                        <?= htmlspecialchars($order['shipping_country']) ?>
                    </address>
                </div>
            </div>
            
            <!-- Produk yang Dibeli -->
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3">
                    <h5 class="mb-0">Produk yang Dibeli</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col">Produk</th>
                                    <th scope="col">Harga</th>
                                    <th scope="col">Jumlah</th>
                                    <th scope="col">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($item['product_image'])): ?>
                                                    <img src="<?= SITE_URL ?>/assets/img/products/<?= $item['product_image'] ?>" 
                                                         alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                                         style="width: 50px; height: 50px; object-fit: cover;" 
                                                         class="me-3">
                                                <?php else: ?>
                                                    <div class="bg-light d-flex justify-content-center align-items-center me-3" 
                                                         style="width: 50px; height: 50px;">
                                                        <i class="fas fa-image text-secondary"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <p class="mb-0 fw-semibold"><?= htmlspecialchars($item['product_name']) ?></p>
                                                    <small class="text-muted">Penjual: <?= htmlspecialchars($item['vendor_name']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= formatPrice($item['price']) ?></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td><?= formatPrice($item['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Ringkasan Pembayaran -->
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3">
                    <h5 class="mb-0">Ringkasan Pembayaran</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <span><?= formatPrice($order['total_amount'] - $order['shipping_amount'] - $order['tax_amount']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Biaya Pengiriman</span>
                        <span><?= formatPrice($order['shipping_amount']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Pajak</span>
                        <span><?= formatPrice($order['tax_amount']) ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3 fw-bold">
                        <span>Total</span>
                        <span class="text-primary"><?= formatPrice($order['total_amount']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Detail Status -->
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3">
                    <h5 class="mb-0">Status Pesanan</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span>
                                <i class="fas fa-shopping-cart text-success me-2"></i> Pesanan Dibuat
                            </span>
                            <small><?= date('d M Y H:i', strtotime($order['created_at'])) ?></small>
                        </li>
                        
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span>
                                <i class="fas fa-wallet <?= $order['payment_status'] === 'paid' || $order['payment_status'] === 'refunded' ? 'text-success' : 'text-warning' ?> me-2"></i> 
                                Pembayaran
                            </span>
                            <small>
                                <?php if ($order['payment_status'] === 'paid'): ?>
                                    <span class="text-success">Berhasil</span>
                                <?php elseif ($order['payment_status'] === 'pending'): ?>
                                    <span class="text-warning">Tertunda</span>
                                <?php elseif ($order['payment_status'] === 'refunded'): ?>
                                    <span class="text-info">Dikembalikan</span>
                                <?php else: ?>
                                    <span class="text-danger">Gagal</span>
                                <?php endif; ?>
                            </small>
                        </li>
                        
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span>
                                <i class="fas fa-box <?= in_array($order['status'], ['processing', 'shipped', 'delivered']) ? 'text-success' : 'text-secondary' ?> me-2"></i> 
                                Pesanan Diproses
                            </span>
                            <small>
                                <?php if ($order['status'] === 'cancelled'): ?>
                                    <span class="text-danger">Dibatalkan</span>
                                <?php elseif ($order['status'] === 'pending'): ?>
                                    <span class="text-secondary">Menunggu</span>
                                <?php else: ?>
                                    <span class="text-success">Diproses</span>
                                <?php endif; ?>
                            </small>
                        </li>
                        
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span>
                                <i class="fas fa-shipping-fast <?= in_array($order['status'], ['shipped', 'delivered']) ? 'text-success' : 'text-secondary' ?> me-2"></i> 
                                Pesanan Dikirim
                            </span>
                            <small>
                                <?php if ($order['status'] === 'cancelled'): ?>
                                    <span class="text-danger">Dibatalkan</span>
                                <?php elseif (in_array($order['status'], ['pending', 'processing'])): ?>
                                    <span class="text-secondary">Menunggu</span>
                                <?php else: ?>
                                    <span class="text-success">Dikirim</span>
                                <?php endif; ?>
                            </small>
                        </li>
                        
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span>
                                <i class="fas fa-home <?= $order['status'] === 'delivered' ? 'text-success' : 'text-secondary' ?> me-2"></i> 
                                Pesanan Diterima
                            </span>
                            <small>
                                <?php if ($order['status'] === 'cancelled'): ?>
                                    <span class="text-danger">Dibatalkan</span>
                                <?php elseif ($order['status'] === 'delivered'): ?>
                                    <span class="text-success">Diterima</span>
                                <?php else: ?>
                                    <span class="text-secondary">Menunggu</span>
                                <?php endif; ?>
                            </small>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Aksi Pesanan -->
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3">
                    <h5 class="mb-0">Aksi</h5>
                </div>
                <div class="card-body">
                    <?php if ((!isset($_SESSION['role']) || $_SESSION['role'] === 'customer') && $order['status'] === 'pending'): ?>
                        <!-- Opsi pembatalan untuk customer jika pesanan masih pending -->
                        <form method="post" action="" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini? Dana akan dikembalikan ke saldo Anda.');">
                            <button type="submit" name="cancel_order" class="btn btn-danger w-100 mb-3">
                                <i class="fas fa-times me-2"></i> Batalkan Pesanan
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="<?= SITE_URL ?>/orders.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-list me-2"></i> Daftar Pesanan
                    </a>
                    
                    <?php if ($order['status'] === 'delivered'): ?>
                    <a href="<?= SITE_URL ?>/review.php?order_id=<?= $order_id ?>" class="btn btn-primary w-100 mt-3">
                        <i class="fas fa-star me-2"></i> Beri Ulasan
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?= SITE_URL ?>" class="btn btn-outline-primary w-100 mt-3">
                        <i class="fas fa-shopping-bag me-2"></i> Belanja Lagi
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
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