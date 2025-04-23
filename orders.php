<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Cek apakah pengguna sudah login
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Silakan login untuk melihat pesanan Anda.";
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pageTitle = 'Pesanan Saya';
$current_user = $_SESSION['username'] ?? 'MochSetiawan';

// Current time untuk display
$current_datetime = date('Y-m-d H:i:s');

// Set filter status jika ada
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

// Buat tabel orders jika belum ada
$orders_table_exists = $conn->query("SHOW TABLES LIKE 'orders'")->num_rows > 0;
if (!$orders_table_exists) {
    // Create orders table
    $create_orders_table = "
    CREATE TABLE IF NOT EXISTS `orders` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `order_number` varchar(50) NOT NULL,
      `status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
      `total_amount` decimal(10,2) NOT NULL,
      `shipping_amount` decimal(10,2) NOT NULL,
      `tax_amount` decimal(10,2) NOT NULL,
      `shipping_address` text NOT NULL,
      `shipping_city` varchar(100) NOT NULL,
      `shipping_state` varchar(100) NOT NULL,
      `shipping_postal_code` varchar(20) NOT NULL,
      `shipping_country` varchar(100) NOT NULL,
      `payment_method` varchar(50) NOT NULL,
      `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
      `notes` text,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      KEY `order_number` (`order_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->query($create_orders_table);
    
    // Create order_items table
    $create_order_items_table = "
    CREATE TABLE IF NOT EXISTS `order_items` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `order_id` int(11) NOT NULL,
      `product_id` int(11) NOT NULL,
      `vendor_id` int(11) NOT NULL,
      `quantity` int(11) NOT NULL,
      `price` decimal(10,2) NOT NULL,
      `total` decimal(10,2) NOT NULL,
      `status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `order_id` (`order_id`),
      KEY `product_id` (`product_id`),
      KEY `vendor_id` (`vendor_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->query($create_order_items_table);
}

// Ambil data pesanan
$orders_sql = "SELECT o.*, 
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
               FROM orders o 
               WHERE o.user_id = $user_id";

// Tambahkan filter status jika dipilih
if (!empty($status_filter)) {
    $orders_sql .= " AND o.status = '$status_filter'";
}

// Tambahkan urutan
$orders_sql .= " ORDER BY o.created_at DESC";

$orders_result = $conn->query($orders_sql);
$orders = [];

if ($orders_result && $orders_result->num_rows > 0) {
    while ($row = $orders_result->fetch_assoc()) {
        $orders[] = $row;
    }
}

include 'includes/header.php';
?>

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

    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/cart.php">Shopping Cart</a></li>
            <li class="breadcrumb-item active">Pesanan Saya</li>
        </ol>
    </nav>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">Pesanan Saya</h1>
        <a href="<?= SITE_URL ?>/cart.php" class="btn btn-outline-primary d-none d-sm-inline-block">
            <i class="fas fa-shopping-cart mr-1"></i> Kembali ke Keranjang
        </a>
    </div>
    
    <!-- Filter Status -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap">
                <a href="<?= SITE_URL ?>/orders.php" class="btn <?= empty($status_filter) ? 'btn-primary' : 'btn-outline-secondary' ?> mr-2 mb-2">
                    Semua
                </a>
                <a href="<?= SITE_URL ?>/orders.php?status=pending" class="btn <?= $status_filter === 'pending' ? 'btn-primary' : 'btn-outline-secondary' ?> mr-2 mb-2">
                    Tertunda
                </a>
                <a href="<?= SITE_URL ?>/orders.php?status=processing" class="btn <?= $status_filter === 'processing' ? 'btn-primary' : 'btn-outline-secondary' ?> mr-2 mb-2">
                    Diproses
                </a>
                <a href="<?= SITE_URL ?>/orders.php?status=shipped" class="btn <?= $status_filter === 'shipped' ? 'btn-primary' : 'btn-outline-secondary' ?> mr-2 mb-2">
                    Dikirim
                </a>
                <a href="<?= SITE_URL ?>/orders.php?status=delivered" class="btn <?= $status_filter === 'delivered' ? 'btn-primary' : 'btn-outline-secondary' ?> mr-2 mb-2">
                    Terkirim
                </a>
                <a href="<?= SITE_URL ?>/orders.php?status=cancelled" class="btn <?= $status_filter === 'cancelled' ? 'btn-primary' : 'btn-outline-secondary' ?> mr-2 mb-2">
                    Dibatalkan
                </a>
            </div>
        </div>
    </div>
    
    <?php if (empty($orders)): ?>
        <div class="card shadow-sm">
            <div class="card-body py-5 text-center">
                <i class="fas fa-shopping-bag fa-4x text-muted mb-3"></i>
                <h4>Belum ada pesanan</h4>
                <p class="text-muted">
                    <?php if (!empty($status_filter)): ?>
                        Anda tidak memiliki pesanan dengan status "<?= ucfirst($status_filter) ?>".
                    <?php else: ?>
                        Anda belum melakukan pemesanan apapun.
                    <?php endif; ?>
                </p>
                <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary mt-2">Mulai Belanja</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th scope="col">Nomor Pesanan</th>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Total</th>
                            <th scope="col">Status</th>
                            <th scope="col">Pembayaran</th>
                            <th scope="col">Jumlah Item</th>
                            <th scope="col" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= $order['order_number'] ?></td>
                                <td><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                                <td>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></td>
                                <td>
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
                                    echo $status_badge;
                                    ?>
                                </td>
                                <td>
                                    <?php if ($order['payment_status'] === 'paid'): ?>
                                        <span class="badge bg-success">Dibayar</span>
                                    <?php elseif ($order['payment_status'] === 'pending'): ?>
                                        <span class="badge bg-warning">Tertunda</span>
                                    <?php elseif ($order['payment_status'] === 'failed'): ?>
                                        <span class="badge bg-danger">Gagal</span>
                                    <?php elseif ($order['payment_status'] === 'refunded'): ?>
                                        <span class="badge bg-info">Dikembalikan</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $order['item_count'] ?> item</td>
                                <td class="text-center">
                                    <a href="<?= SITE_URL ?>/order_detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye mr-1"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
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