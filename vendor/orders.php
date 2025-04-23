<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi vendor
if (!isLoggedIn() || $_SESSION['role'] !== 'vendor') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pageTitle = 'Pesanan Saya';
$currentPage = 'orders';
$current_user = $_SESSION['username'] ?? 'MochSetiawan';

// Dapatkan vendor_id
$vendor_sql = "SELECT id FROM vendors WHERE user_id = $user_id";
$vendor_result = $conn->query($vendor_sql);

if (!$vendor_result || $vendor_result->num_rows === 0) {
    // Buat vendor jika belum ada
    $conn->query("INSERT INTO vendors (user_id, shop_name, created_at) VALUES ($user_id, 'Toko Saya', NOW())");
    $vendor_id = $conn->insert_id;
} else {
    $vendor_row = $vendor_result->fetch_assoc();
    $vendor_id = $vendor_row['id'];
}

// PENTING: Reset koneksi untuk memastikan data terbaru
$conn->close();
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Filter status jika ada
$status_filter = '';
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = $conn->real_escape_string($_GET['status']);
    $status_filter = " AND o.status = '$status'";
}

// Tampilkan pesanan yang memiliki produk dari vendor ini
$orders_query = "SELECT o.id, o.order_number, o.status, o.total_amount, 
                o.created_at, o.payment_status, u.username, u.email,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id AND 
                 oi.product_id IN (SELECT id FROM products WHERE vendor_id = $vendor_id)) as item_count
                FROM orders o
                JOIN users u ON o.user_id = u.id
                WHERE o.id IN (
                    SELECT DISTINCT oi.order_id FROM order_items oi 
                    JOIN products p ON oi.product_id = p.id 
                    WHERE p.vendor_id = $vendor_id
                )
                $status_filter
                ORDER BY o.created_at DESC";

$orders_result = $conn->query($orders_query);
$orders = [];

if ($orders_result && $orders_result->num_rows > 0) {
    while ($row = $orders_result->fetch_assoc()) {
        $orders[] = $row;
    }
}

include '../includes/vendor-header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Pesanan Saya</h1>
        
        <!-- Refresh Button -->
        <a href="<?= SITE_URL ?>/vendor/orders.php?<?= isset($_GET['status']) ? 'status='.$_GET['status'].'&' : '' ?>refresh=<?= time() ?>" class="btn btn-info">
            <i class="fas fa-sync-alt mr-1"></i> Refresh Data
        </a>
    </div>
    
    <!-- User Welcome Banner -->
    <div class="card mb-4 border-left-primary">
        <div class="card-body py-3">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Current User's Login: <?= htmlspecialchars($current_user) ?>
                    </div>
                    <div class="text-xs text-gray-800">
                        Current Date and Time (UTC): <span id="current-datetime"><?= date('Y-m-d H:i:s') ?></span>
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-user-clock fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Filter -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap">
                <a href="<?= SITE_URL ?>/vendor/orders.php" class="btn <?= !isset($_GET['status']) ? 'btn-primary' : 'btn-outline-secondary' ?> mr-2 mb-2">
                    Semua Pesanan
                </a>
                <a href="<?= SITE_URL ?>/vendor/orders.php?status=pending" class="btn <?= isset($_GET['status']) && $_GET['status'] === 'pending' ? 'btn-primary' : 'btn-outline-secondary' ?> mr-2 mb-2">
                    Tertunda
                </a>
                <a href="<?= SITE_URL ?>/vendor/orders.php?status=processing" class="btn <?= isset($_GET['status']) && $_GET['status'] === 'processing' ? 'btn-primary' : 'btn-outline-secondary' ?> mr-2 mb-2">
                    Diproses
                </a>
                <a href="<?= SITE_URL ?>/vendor/orders.php?status=shipped" class="btn <?= isset($_GET['status']) && $_GET['status'] === 'shipped' ? 'btn-primary' : 'btn-outline-secondary' ?> mr-2 mb-2">
                    Dikirim
                </a>
                <a href="<?= SITE_URL ?>/vendor/orders.php?status=delivered" class="btn <?= isset($_GET['status']) && $_GET['status'] === 'delivered' ? 'btn-primary' : 'btn-outline-secondary' ?> mr-2 mb-2">
                    Terkirim
                </a>
                <a href="<?= SITE_URL ?>/vendor/orders.php?status=cancelled" class="btn <?= isset($_GET['status']) && $_GET['status'] === 'cancelled' ? 'btn-primary' : 'btn-outline-secondary' ?> mr-2 mb-2">
                    Dibatalkan
                </a>
            </div>
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
    
    <!-- Orders List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Pesanan (<?= count($orders) ?>)</h6>
            <div>
                <span class="badge badge-info">Terakhir diperbarui: <?= date('H:i:s') ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($orders)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-1"></i> Belum ada pesanan untuk produk Anda.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" width="100%" cellspacing="0" id="orderTable">
                        <thead>
                            <tr>
                                <th>No. Pesanan</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Jumlah Item</th>
                                <th>Total</th>
                                <th>Status Pesanan</th>
                                <th>Status Pembayaran</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr id="order-row-<?= $order['id'] ?>">
                                    <td><?= htmlspecialchars($order['order_number']) ?></td>
                                    <td><?= date('d M Y H:i', strtotime($order['created_at'])) ?></td>
                                    <td>
                                        <?= htmlspecialchars($order['username']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($order['email']) ?></small>
                                    </td>
                                    <td class="text-center"><?= $order['item_count'] ?> item</td>
                                    <td>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $status = isset($order['status']) ? $order['status'] : 'pending';
                                        $status_class = 'secondary';
                                        $status_text = ucfirst($status);
                                        
                                        switch($status) {
                                            case 'pending':
                                                $status_class = 'warning';
                                                $status_text = 'Tertunda';
                                                break;
                                            case 'processing':
                                                $status_class = 'info';
                                                $status_text = 'Diproses';
                                                break;
                                            case 'shipped':
                                                $status_class = 'primary';
                                                $status_text = 'Dikirim';
                                                break;
                                            case 'delivered':
                                                $status_class = 'success';
                                                $status_text = 'Terkirim';
                                                break;
                                            case 'cancelled':
                                                $status_class = 'danger';
                                                $status_text = 'Dibatalkan';
                                                break;
                                        }
                                        ?>
                                        <span class="badge badge-<?= $status_class ?>" id="status-badge-<?= $order['id'] ?>" data-status="<?= $status ?>">
                                            <?= $status_text ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                        $payment_status = isset($order['payment_status']) ? $order['payment_status'] : 'pending';
                                        $payment_class = 'secondary';
                                        $payment_text = ucfirst($payment_status);
                                        
                                        switch($payment_status) {
                                            case 'pending':
                                                $payment_class = 'warning';
                                                $payment_text = 'Tertunda';
                                                break;
                                            case 'paid':
                                                $payment_class = 'success';
                                                $payment_text = 'Dibayar';
                                                break;
                                            case 'failed':
                                                $payment_class = 'danger';
                                                $payment_text = 'Gagal';
                                                break;
                                            case 'refunded':
                                                $payment_class = 'info';
                                                $payment_text = 'Dikembalikan';
                                                break;
                                        }
                                        ?>
                                        <span class="badge badge-<?= $payment_class ?>" id="payment-badge-<?= $order['id'] ?>" data-status="<?= $payment_status ?>">
                                            <?= $payment_text ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> Detail
                                        </a>
                                        
                                        <!-- Quick Status Update Dropdown -->
                                        <div class="btn-group ml-1">
                                            <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <h6 class="dropdown-header">Update Status:</h6>
                                                <a class="dropdown-item quick-status-update" href="#" data-order-id="<?= $order['id'] ?>" data-status="processing">Ubah ke Diproses</a>
                                                <a class="dropdown-item quick-status-update" href="#" data-order-id="<?= $order['id'] ?>" data-status="shipped">Ubah ke Dikirim</a>
                                                <a class="dropdown-item quick-status-update" href="#" data-order-id="<?= $order['id'] ?>" data-status="delivered">Ubah ke Terkirim</a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item quick-status-update text-danger" href="#" data-order-id="<?= $order['id'] ?>" data-status="cancelled">Batalkan Pesanan</a>
                                            </div>
                                        </div>
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

<!-- Modal for Quick Status Update -->
<div class="modal fade" id="statusUpdateModal" tabindex="-1" role="dialog" aria-labelledby="statusUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusUpdateModalLabel">Update Status Pesanan</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Anda yakin ingin mengubah status pesanan ini?</p>
                <p>Status akan diubah menjadi: <strong id="new-status-text">Loading...</strong></p>
                
                <form id="quick-update-form" action="update_order_status.php" method="post">
                    <input type="hidden" name="order_id" id="update-order-id">
                    <input type="hidden" name="status" id="update-status">
                </form>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Perubahan status ini akan mempengaruhi semua produk Anda dalam pesanan ini.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="confirm-status-update">Update Status</button>
            </div>
        </div>
    </div>
</div>

<script>
// Update waktu secara real-time
function updateDateTime() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    
    const formattedDateTime = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    document.getElementById('current-datetime').textContent = formattedDateTime;
}

// Update waktu setiap detik
setInterval(updateDateTime, 1000);
updateDateTime(); // Panggil sekali untuk menampilkan waktu terbaru

// Auto refresh halaman setiap 30 detik untuk mendapatkan data terbaru
setTimeout(function() {
    // Simpan scroll position sekarang
    localStorage.setItem('orderScrollPos', window.scrollY);
    
    // Reload dengan parameter refresh baru
    window.location.href = window.location.href.split('?')[0] + 
        '<?= isset($_GET["status"]) ? "?status=" . $_GET["status"] . "&" : "?" ?>refresh=' + new Date().getTime();
}, 30000);

// Restore scroll position setelah refresh
window.onload = function() {
    const scrollPos = localStorage.getItem('orderScrollPos');
    if (scrollPos) {
        window.scrollTo(0, parseInt(scrollPos));
        localStorage.removeItem('orderScrollPos');
    }
};

// Fungsi Quick Status Update
document.addEventListener('DOMContentLoaded', function() {
    // Setup event listeners for quick status update links
    document.querySelectorAll('.quick-status-update').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const orderId = this.dataset.orderId;
            const newStatus = this.dataset.status;
            
            // Set form values
            document.getElementById('update-order-id').value = orderId;
            document.getElementById('update-status').value = newStatus;
            
            // Set display text for status
            let statusText = '';
            switch(newStatus) {
                case 'pending': statusText = 'Tertunda'; break;
                case 'processing': statusText = 'Diproses'; break;
                case 'shipped': statusText = 'Dikirim'; break;
                case 'delivered': statusText = 'Terkirim'; break;
                case 'cancelled': statusText = 'Dibatalkan'; break;
                default: statusText = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            }
            document.getElementById('new-status-text').textContent = statusText;
            
            // Show modal
            $('#statusUpdateModal').modal('show');
        });
    });
    
    // Handle confirm button click
    document.getElementById('confirm-status-update').addEventListener('click', function() {
        const form = document.getElementById('quick-update-form');
        const orderId = document.getElementById('update-order-id').value;
        const newStatus = document.getElementById('update-status').value;
        
        // Submit the form with fetch
        fetch(form.action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(new FormData(form))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI
                const statusBadge = document.getElementById(`status-badge-${orderId}`);
                if (statusBadge) {
                    // Update class
                    statusBadge.className = 'badge';
                    
                    let statusClass = '';
                    let statusText = '';
                    switch(newStatus) {
                        case 'pending':
                            statusClass = 'badge-warning';
                            statusText = 'Tertunda';
                            break;
                        case 'processing':
                            statusClass = 'badge-info';
                            statusText = 'Diproses';
                            break;
                        case 'shipped':
                            statusClass = 'badge-primary';
                            statusText = 'Dikirim';
                            break;
                        case 'delivered':
                            statusClass = 'badge-success';
                            statusText = 'Terkirim';
                            break;
                        case 'cancelled':
                            statusClass = 'badge-danger';
                            statusText = 'Dibatalkan';
                            break;
                        default:
                            statusClass = 'badge-secondary';
                            statusText = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    }
                    
                    statusBadge.classList.add(statusClass);
                    statusBadge.dataset.status = newStatus;
                    statusBadge.textContent = statusText;
                }
                
                // Highlight updated row
                const row = document.getElementById(`order-row-${orderId}`);
                if (row) {
                    row.style.backgroundColor = '#f8f9cb';
                    setTimeout(() => {
                        row.style.backgroundColor = '';
                        row.style.transition = 'background-color 1s';
                    }, 100);
                }
                
                // Close modal
                $('#statusUpdateModal').modal('hide');
                
                // Show success message
                const successAlert = document.createElement('div');
                successAlert.className = 'alert alert-success alert-dismissible fade show mt-3';
                successAlert.innerHTML = `
                    <strong>Sukses!</strong> Status pesanan berhasil diperbarui menjadi ${statusText}.
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                `;
                
                const cardBody = document.querySelector('.card-body');
                if (cardBody) {
                    cardBody.insertBefore(successAlert, cardBody.firstChild);
                }
                
                // Auto dismiss after 3 seconds
                setTimeout(() => {
                    successAlert.remove();
                }, 3000);
            } else {
                // Show error
                alert('Error: ' + data.message);
                $('#statusUpdateModal').modal('hide');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat memperbarui status.');
            $('#statusUpdateModal').modal('hide');
        });
    });
});
</script>

<?php include '../includes/vendor-footer.php'; ?>