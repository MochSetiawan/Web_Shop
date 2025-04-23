<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi vendor
if (!isLoggedIn() || $_SESSION['role'] !== 'vendor') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pageTitle = 'Detail Pesanan';
$currentPage = 'orders';
$current_user = $_SESSION['username'] ?? 'MochSetiawan';

// Dapatkan vendor_id
$vendor_result = $conn->query("SELECT id FROM vendors WHERE user_id = $user_id");
if ($vendor_result && $vendor_result->num_rows > 0) {
    $vendor_row = $vendor_result->fetch_assoc();
    $vendor_id = $vendor_row['id'];
} else {
    die("Error: Tidak dapat menemukan data vendor.");
}

// Cek apakah ada ID pesanan
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ' . SITE_URL . '/vendor/orders.php');
    exit;
}

$order_id = (int)$_GET['id'];

// Dapatkan data pesanan
$order_query = "SELECT * FROM orders WHERE id = $order_id";
$order_result = $conn->query($order_query);

// Dapatkan data customer
$customer_query = "SELECT username, email FROM users WHERE id = (SELECT user_id FROM orders WHERE id = $order_id)";
$customer_result = $conn->query($customer_query);

// Pastikan pesanan ditemukan
if (!$order_result || $order_result->num_rows === 0) {
    header('Location: ' . SITE_URL . '/vendor/orders.php');
    exit;
}

$order = $order_result->fetch_assoc();
$customer = ($customer_result && $customer_result->num_rows > 0) ? $customer_result->fetch_assoc() : ['username' => 'Unknown', 'email' => 'Unknown'];

// Ambil item pesanan dari vendor ini
$items_query = "SELECT oi.*, p.name as product_name, p.sku 
              FROM order_items oi 
              JOIN products p ON oi.product_id = p.id 
              WHERE oi.order_id = $order_id AND p.vendor_id = $vendor_id";
$items_result = $conn->query($items_query);

$items = [];
if ($items_result && $items_result->num_rows > 0) {
    while ($row = $items_result->fetch_assoc()) {
        $items[] = $row;
    }
}

// Hitung total penjualan vendor dalam pesanan ini
$total_vendor_sales = 0;
foreach ($items as $item) {
    $total_vendor_sales += $item['price'] * $item['quantity'];
}

// Update status item jika disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $item_id = (int)$_POST['item_id'];
    $new_status = $_POST['status'];
    
    // Verifikasi bahwa item ini dari vendor kita
    $verify_query = "SELECT oi.id, oi.order_id FROM order_items oi 
                   JOIN products p ON oi.product_id = p.id 
                   WHERE oi.id = $item_id AND p.vendor_id = $vendor_id";
    $verify_result = $conn->query($verify_query);
    
    if ($verify_result && $verify_result->num_rows > 0) {
        $item_info = $verify_result->fetch_assoc();
        
        // Update status item
        $update_item = "UPDATE order_items SET status = '$new_status', updated_at = NOW() WHERE id = $item_id";
        if ($conn->query($update_item)) {
            $_SESSION['success_message'] = "Status item berhasil diperbarui.";
            
            // Update semua item dari vendor ini ke status yang sama
            $update_all_vendor_items = "UPDATE order_items oi 
                                      JOIN products p ON oi.product_id = p.id 
                                      SET oi.status = '$new_status', oi.updated_at = NOW() 
                                      WHERE oi.order_id = $order_id AND p.vendor_id = $vendor_id";
            $conn->query($update_all_vendor_items);
            
            // Periksa apakah semua item dalam pesanan ini memiliki status yang sama
            $check_all_items = "SELECT COUNT(DISTINCT oi.status) as status_count 
                               FROM order_items oi 
                               WHERE oi.order_id = $order_id";
            $all_items_result = $conn->query($check_all_items);
            $status_info = $all_items_result->fetch_assoc();
            
            // Jika semua item memiliki status yang sama, update status order
            if ($status_info['status_count'] == 1) {
                // Ambil status yang seragam
                $status_query = "SELECT status FROM order_items WHERE order_id = $order_id LIMIT 1";
                $status_result = $conn->query($status_query);
                $uniform_status = $status_result->fetch_assoc()['status'];
                
                // Update status order
                $update_order = "UPDATE orders SET status = '$uniform_status', updated_at = NOW() WHERE id = $order_id";
                $conn->query($update_order);
            }
        } else {
            $_SESSION['error_message'] = "Gagal memperbarui status item.";
        }
    } else {
        $_SESSION['error_message'] = "Anda tidak memiliki akses untuk mengupdate item ini.";
    }
    
    // Refresh halaman untuk menampilkan perubahan
    header("Location: " . SITE_URL . "/vendor/order_detail.php?id=$order_id&t=" . time());
    exit;
}

include '../includes/vendor-header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Detail Pesanan #<?= htmlspecialchars($order['order_number']) ?></h1>
        <div>
            <a href="<?= SITE_URL ?>/vendor/order_detail.php?id=<?= $order_id ?>&refresh=<?= time() ?>" class="btn btn-info mr-2">
                <i class="fas fa-sync-alt mr-1"></i> Refresh Data
            </a>
            <a href="<?= SITE_URL ?>/vendor/orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Pesanan
            </a>
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
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Informasi Pesanan -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Informasi Pesanan</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Nomor Pesanan:</strong> <?= htmlspecialchars($order['order_number']) ?></p>
                            <p><strong>Tanggal Pesanan:</strong> <?= date('d M Y H:i', strtotime($order['created_at'])) ?></p>
                            <p>
                                <strong>Status Pesanan:</strong> 
                                <?php 
                                $status = isset($order['status']) ? $order['status'] : 'pending';
                                $status_class = 'secondary';
                                switch($status) {
                                    case 'pending': $status_class = 'warning'; break;
                                    case 'processing': $status_class = 'info'; break;
                                    case 'shipped': $status_class = 'primary'; break;
                                    case 'delivered': $status_class = 'success'; break;
                                    case 'cancelled': $status_class = 'danger'; break;
                                }
                                ?>
                                <span class="badge badge-<?= $status_class ?>" id="order-main-status">
                                    <?= ucfirst($status) ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Pelanggan:</strong> <?= htmlspecialchars($customer['username']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($customer['email']) ?></p>
                            <p>
                                <strong>Status Pembayaran:</strong> 
                                <?php 
                                $payment_status = isset($order['payment_status']) ? $order['payment_status'] : 'pending';
                                $payment_class = 'secondary';
                                switch($payment_status) {
                                    case 'pending': $payment_class = 'warning'; break;
                                    case 'paid': $payment_class = 'success'; break;
                                    case 'failed': $payment_class = 'danger'; break;
                                    case 'refunded': $payment_class = 'info'; break;
                                }
                                ?>
                                <span class="badge badge-<?= $payment_class ?>">
                                    <?= ucfirst($payment_status) ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Alamat Pengiriman -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Alamat Pengiriman</h6>
                </div>
                <div class="card-body">
                    <address>
                        <?php 
                        $address = isset($order['shipping_address']) ? $order['shipping_address'] : 'Alamat tidak tersedia';
                        $city = isset($order['shipping_city']) ? $order['shipping_city'] : '';
                        $state = isset($order['shipping_state']) ? $order['shipping_state'] : '';
                        $postal = isset($order['shipping_postal_code']) ? $order['shipping_postal_code'] : '';
                        $country = isset($order['shipping_country']) ? $order['shipping_country'] : '';
                        ?>
                        <strong><?= htmlspecialchars($customer['username']) ?></strong><br>
                        <?= nl2br(htmlspecialchars($address)) ?><br>
                        <?= htmlspecialchars($city) ?>, 
                        <?= htmlspecialchars($state) ?> 
                        <?= htmlspecialchars($postal) ?><br>
                        <?= htmlspecialchars($country) ?>
                    </address>
                </div>
            </div>
            
            <!-- Produk yang Dibeli dari Vendor Ini -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Produk Saya yang Dibeli</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($items)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-1"></i> Tidak ada produk dari vendor ini dalam pesanan ini.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Harga</th>
                                        <th>Jumlah</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                                                <small class="text-muted">SKU: <?= htmlspecialchars($item['sku'] ?? 'N/A') ?></small>
                                            </td>
                                            <td>Rp <?= number_format($item['price'], 0, ',', '.') ?></td>
                                            <td><?= $item['quantity'] ?></td>
                                            <td>Rp <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?></td>
                                            <td>
                                                <?php 
                                                $item_status = isset($item['status']) ? $item['status'] : 'pending';
                                                $item_status_class = 'secondary';
                                                switch($item_status) {
                                                    case 'pending': $item_status_class = 'warning'; break;
                                                    case 'processing': $item_status_class = 'info'; break;
                                                    case 'shipped': $item_status_class = 'primary'; break;
                                                    case 'delivered': $item_status_class = 'success'; break;
                                                    case 'cancelled': $item_status_class = 'danger'; break;
                                                }
                                                ?>
                                                <span class="badge badge-<?= $item_status_class ?>" id="item-status-<?= $item['id'] ?>">
                                                    <?= ucfirst($item_status) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="post" action="" class="status-update-form">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <div class="input-group input-group-sm">
                                                        <select name="status" class="form-control">
                                                            <option value="pending" <?= $item_status === 'pending' ? 'selected' : '' ?>>Tertunda</option>
                                                            <option value="processing" <?= $item_status === 'processing' ? 'selected' : '' ?>>Diproses</option>
                                                            <option value="shipped" <?= $item_status === 'shipped' ? 'selected' : '' ?>>Dikirim</option>
                                                            <option value="delivered" <?= $item_status === 'delivered' ? 'selected' : '' ?>>Terkirim</option>
                                                            <option value="cancelled" <?= $item_status === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                                                        </select>
                                                        <div class="input-group-append">
                                                            <button type="submit" name="update_status" class="btn btn-primary">
                                                                <i class="fas fa-save"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
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
        
        <div class="col-lg-4">
            <!-- Ringkasan Penjualan -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Ringkasan Penjualan</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <p><strong>Total Pesanan:</strong> Rp <?= number_format($order['total_amount'] ?? 0, 0, ',', '.') ?></p>
                        <p><strong>Total Produk Saya:</strong> Rp <?= number_format($total_vendor_sales, 0, ',', '.') ?></p>
                        <p><strong>Jumlah Item:</strong> <?= count($items) ?> produk</p>
                        <p><strong>Jumlah Kuantitas:</strong> <?= array_sum(array_column($items, 'quantity')) ?> item</p>
                    </div>
                </div>
            </div>
            
            <!-- Status Pengiriman -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Update Status Semua Produk</h6>
                </div>
                <div class="card-body">
                    <p>Gunakan ini untuk mengubah status semua produk Anda dalam pesanan ini sekaligus:</p>
                    <form method="post" action="" id="update-all-form">
                        <input type="hidden" name="item_id" value="<?= $items[0]['id'] ?? 0 ?>">
                        <div class="form-group">
                            <select name="status" class="form-control">
                                <option value="pending">Tertunda</option>
                                <option value="processing">Diproses</option>
                                <option value="shipped">Dikirim</option>
                                <option value="delivered">Terkirim</option>
                                <option value="cancelled">Dibatalkan</option>
                            </select>
                        </div>
                        <button type="submit" name="update_status" class="btn btn-primary btn-block">
                            <i class="fas fa-sync-alt mr-1"></i> Update Semua Produk
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Tips Memproses Pesanan -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Tips Memproses Pesanan</h6>
                </div>
                <div class="card-body">
                    <ol class="mb-0">
                        <li class="mb-2">Periksa stok produk sebelum memproses pesanan.</li>
                        <li class="mb-2">Update status pesanan segera setelah Anda mulai memprosesnya.</li>
                        <li class="mb-2">Pastikan produk dikemas dengan aman untuk pengiriman.</li>
                        <li class="mb-2">Tambahkan nomor resi ketika status diubah menjadi "Dikirim".</li>
                        <li class="mb-2">Berikan layanan terbaik untuk mendapatkan ulasan positif.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Force refresh data setiap 60 detik
setTimeout(function() {
    window.location.href = window.location.href.split('?')[0] + '?id=<?= $order_id ?>&refresh=' + new Date().getTime();
}, 60000);

// Force browser untuk tidak menyimpan cache halaman ini
window.onpageshow = function(event) {
    if (event.persisted) {
        window.location.reload();
    }
};

// Konfirmasi sebelum update status
document.querySelectorAll('.status-update-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const status = this.querySelector('select[name="status"]').value;
        const currentStatus = this.parentNode.querySelector('[id^="item-status-"]').textContent.trim().toLowerCase();
        
        if (status !== currentStatus) {
            if (!confirm(`Anda yakin ingin mengubah status dari "${currentStatus}" menjadi "${status}"?`)) {
                e.preventDefault();
            }
        }
    });
});

// Konfirmasi untuk update semua produk
document.getElementById('update-all-form').addEventListener('submit', function(e) {
    const status = this.querySelector('select[name="status"]').value;
    if (!confirm(`Anda yakin ingin mengubah status SEMUA produk Anda dalam pesanan ini menjadi "${status}"?`)) {
        e.preventDefault();
    }
});
</script>

<?php include '../includes/vendor-footer.php'; ?>