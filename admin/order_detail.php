<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$pageTitle = 'Detail Pesanan';
$currentPage = 'orders';

// Ambil ID pesanan dari URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    header('Location: ' . ADMIN_URL . '/dashboard.php');
    exit;
}

// Cek apakah admin memiliki akses ke pesanan ini
$show_order_details = false;
$token_error = '';
$token_success = '';
$vendor_name = '';
$token_from_session = false;

// Cek apakah sudah ada token valid di session untuk order ini
if (isset($_SESSION['valid_tokens'][$order_id])) {
    $token_info = $_SESSION['valid_tokens'][$order_id];
    $token = $token_info['token'];
    $vendor_name = $token_info['vendor'];
    
    // Token valid, izinkan akses
    $show_order_details = true;
    $token_from_session = true;
}
// Cek token dari URL jika belum ada di session
else if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);
    
    // Validasi token untuk pesanan ini
    $token_query = "SELECT at.vendor_id, v.shop_name, u.full_name, u.username, at.expires_at
                  FROM access_tokens at
                  JOIN vendors v ON at.vendor_id = v.id
                  JOIN users u ON v.user_id = u.id
                  WHERE at.token = ? AND at.order_id = ? AND at.expires_at > NOW()";
    
    $stmt = $conn->prepare($token_query);
    $stmt->bind_param('si', $token, $order_id);
    $stmt->execute();
    $token_result = $stmt->get_result();
    
    if ($token_result && $token_result->num_rows > 0) {
        $token_data = $token_result->fetch_assoc();
        $vendor_name = $token_data['shop_name'];
        $vendor_owner = $token_data['full_name'] ?: $token_data['username'];
        $expires_at = $token_data['expires_at'];
        
        // Token valid, izinkan akses
        $show_order_details = true;
        
        // Simpan token valid ke session
        $_SESSION['valid_tokens'][$order_id] = [
            'token' => $token,
            'vendor' => $vendor_name,
            'expires_at' => $expires_at
        ];
        
        // Tampilkan pesan token berhasil divalidasi
        $token_success = "Akses diberikan melalui token dari vendor: $vendor_name ($vendor_owner)";
    } else {
        $token_error = 'Token tidak valid atau sudah kedaluwarsa untuk pesanan ini';
    }
} else if ($_SESSION['role'] === 'superadmin') {
    // Superadmin selalu memiliki akses
    $show_order_details = true;
} else {
    $token_error = 'Anda tidak memiliki akses ke detail pesanan ini. Silakan minta token dari vendor.';
}

// Jika tidak memiliki akses, arahkan ke halaman forbidden
if (!$show_order_details) {
    header('Location: ' . ADMIN_URL . '/forbidden.php?order_id=' . $order_id);
    exit;
}

// Ambil data pesanan
$order_query = "SELECT o.*, u.username, u.full_name, u.email, u.phone 
               FROM orders o
               JOIN users u ON o.user_id = u.id
               WHERE o.id = ?";
$stmt = $conn->prepare($order_query);
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order_result = $stmt->get_result();

if (!$order_result || $order_result->num_rows === 0) {
    // Pesanan tidak ditemukan
    header('Location: ' . ADMIN_URL . '/dashboard.php');
    exit;
}

$order = $order_result->fetch_assoc();

// Ambil item pesanan
$items_query = "SELECT oi.*, p.name as product_name, p.sku, v.shop_name as vendor_name, 
               u.username as vendor_username
               FROM order_items oi
               JOIN products p ON oi.product_id = p.id
               JOIN vendors v ON p.vendor_id = v.id
               JOIN users u ON v.user_id = u.id
               WHERE oi.order_id = ?
               ORDER BY v.shop_name, p.name";

$stmt = $conn->prepare($items_query);
$stmt->bind_param('i', $order_id);
$stmt->execute();
$items_result = $stmt->get_result();

$order_items = [];
$vendors = [];

if ($items_result && $items_result->num_rows > 0) {
    while ($item = $items_result->fetch_assoc()) {
        $order_items[] = $item;
        $vendors[$item['vendor_name']] = true;
    }
}

include '../includes/admin-header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Detail Pesanan #<?= htmlspecialchars($order['order_number']) ?></h1>
        <a href="<?= ADMIN_URL ?>/dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Kembali ke Dashboard
        </a>
    </div>
    
    <?php if (!empty($token_success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> <?= $token_success ?>
        </div>
    <?php endif; ?>
    
    <?php if ($token_from_session): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-info-circle mr-1"></i> Menggunakan token dari vendor: <?= htmlspecialchars($vendor_name) ?>
        </div>
    <?php endif; ?>
    
    <!-- Order Information -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Informasi Pesanan</h6>
                    <span class="badge badge-<?= getStatusBadgeClass($order['status']) ?>">
                        <?= ucfirst($order['status']) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h5 class="border-bottom pb-2">Detail Pesanan</h5>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Nomor Pesanan:</strong></td>
                                    <td><?= htmlspecialchars($order['order_number']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Tanggal Pesanan:</strong></td>
                                    <td><?= date('d M Y H:i', strtotime($order['created_at'])) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status Pesanan:</strong></td>
                                    <td>
                                        <span class="badge badge-<?= getStatusBadgeClass($order['status']) ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Status Pembayaran:</strong></td>
                                    <td>
                                        <span class="badge badge-<?= getPaymentStatusBadgeClass($order['payment_status']) ?>">
                                            <?= ucfirst($order['payment_status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Metode Pembayaran:</strong></td>
                                    <td><?= getPaymentMethodName($order['payment_method']) ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h5 class="border-bottom pb-2">Informasi Pelanggan</h5>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Nama:</strong></td>
                                    <td><?= htmlspecialchars($order['full_name'] ?: $order['username']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?= htmlspecialchars($order['email']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Telepon:</strong></td>
                                    <td><?= htmlspecialchars($order['phone'] ?: 'Tidak tersedia') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Username:</strong></td>
                                    <td><?= htmlspecialchars($order['username']) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="border-bottom pb-2">Alamat Pengiriman</h5>
                            <address>
                                <?= htmlspecialchars($order['shipping_address'] ?: 'Tidak tersedia') ?><br>
                                <?= htmlspecialchars($order['shipping_city'] ?: '') ?>, 
                                <?= htmlspecialchars($order['shipping_state'] ?: '') ?><br>
                                <?= htmlspecialchars($order['shipping_postal_code'] ?: '') ?><br>
                                <?= htmlspecialchars($order['shipping_country'] ?: '') ?>
                            </address>
                        </div>
                        <div class="col-md-6">
                            <h5 class="border-bottom pb-2">Ringkasan Biaya</h5>
                            <table class="table table-sm">
                                <tr>
                                    <td>Subtotal Produk:</td>
                                    <td class="text-right">
                                        <?= formatPrice(($order['total_amount'] ?? 0) - ($order['shipping_amount'] ?? 0) - ($order['tax_amount'] ?? 0) + ($order['discount_amount'] ?? 0)) ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Biaya Pengiriman:</td>
                                    <td class="text-right"><?= formatPrice($order['shipping_amount'] ?? 0) ?></td>
                                </tr>
                                <tr>
                                    <td>Pajak:</td>
                                    <td class="text-right"><?= formatPrice($order['tax_amount'] ?? 0) ?></td>
                                </tr>
                                <?php if (($order['discount_amount'] ?? 0) > 0): ?>
                                <tr>
                                    <td>Diskon:</td>
                                    <td class="text-right text-danger">-<?= formatPrice($order['discount_amount']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr class="font-weight-bold">
                                    <td>Total:</td>
                                    <td class="text-right"><?= formatPrice($order['total_amount'] ?? 0) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if (!empty($order['notes'])): ?>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <strong>Catatan Pesanan:</strong><br>
                                <?= nl2br(htmlspecialchars($order['notes'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Informasi Akses</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-1"></i> Anda melihat pesanan ini dengan izin dari vendor: <strong><?= htmlspecialchars($vendor_name ?: 'Administrator') ?></strong>
                    </div>
                    
                    <h5 class="border-bottom pb-2">Detail Vendor</h5>
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach (array_keys($vendors) as $vendor): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($vendor) ?>
                                <?php if ($vendor === $vendor_name): ?>
                                    <span class="badge badge-success">Akses Diberikan</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Informasi pada halaman ini bersifat rahasia. Jangan dibagikan tanpa izin.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order Items -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Item Pesanan</h6>
        </div>
        <div class="card-body">
            <?php if (empty($order_items)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-1"></i> Tidak ada item dalam pesanan ini.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Vendor</th>
                                <th>Harga</th>
                                <th>Jumlah</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                        <?php if (!empty($item['sku'])): ?>
                                            <br><small class="text-muted">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($item['vendor_name']) ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($item['vendor_username']) ?></small>
                                    </td>
                                    <td><?= formatPrice($item['price']) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td><?= formatPrice($item['price'] * $item['quantity']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= getStatusBadgeClass($item['status'] ?? 'pending') ?>">
                                            <?= ucfirst($item['status'] ?? 'pending') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 2rem;
    margin-bottom: 1rem;
}
.timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
}
.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #e3e6ec;
    transform: translateX(-50%);
}
.timeline-item-marker {
    position: absolute;
    left: -2rem;
    top: 0;
}
.timeline-item-marker-indicator {
    width: 1rem;
    height: 1rem;
    border-radius: 100%;
    margin-top: 0.25rem;
}
.timeline-item-content {
    padding-left: 0.5rem;
}
</style>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Auto-dismiss alerts after 8 seconds
    setTimeout(function() {
        $('.alert-dismissible').each(function() {
            $(this).find('.close').click();
        });
    }, 8000);
});
</script>

<?php
// Helper functions
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'processing': return 'info';
        case 'shipped': return 'primary';
        case 'delivered': return 'success';
        case 'cancelled': return 'danger';
        default: return 'secondary';
    }
}

function getPaymentStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'paid': return 'success';
        case 'failed': return 'danger';
        case 'refunded': return 'info';
        default: return 'secondary';
    }
}

function getPaymentMethodName($method) {
    switch ($method) {
        case 'credit_card': return 'Kartu Kredit';
        case 'bank_transfer': return 'Transfer Bank';
        case 'paypal': return 'PayPal';
        case 'cash_on_delivery': return 'COD (Bayar di Tempat)';
        default: return ucfirst(str_replace('_', ' ', $method));
    }
}
?>

<?php include '../includes/admin-footer.php'; ?>