<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$pageTitle = 'Vendor Details';
$currentPage = 'vendors';
$current_user = $_SESSION['username'] ?? 'MochSetiawan';
$current_datetime = date('Y-m-d H:i:s');

// Pastikan vendor_id ada
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID vendor tidak ditemukan.";
    header('Location: ' . SITE_URL . '/admin/vendors.php');
    exit;
}

$vendor_id = (int)$_GET['id'];

// DEBUG: Tampilkan vendor_id untuk debugging
// echo "Vendor ID: " . $vendor_id;

// Cek tabel vendors dan struktur tabel terlebih dahulu
try {
    // Ambil detail vendor dengan query yang lebih sederhana dulu
    $vendor_query = "SELECT v.*, u.username, u.email, u.full_name, u.phone, u.created_at as user_created_at
                    FROM vendors v
                    JOIN users u ON v.user_id = u.id
                    WHERE v.id = ?";
    $stmt = $conn->prepare($vendor_query);
    
    // Cek jika prepare gagal
    if (!$stmt) {
        throw new Exception("Error pada query vendor: " . $conn->error . "\nQuery: " . $vendor_query);
    }
    
    $stmt->bind_param('i', $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Vendor tidak ditemukan.";
        header('Location: ' . SITE_URL . '/admin/vendors.php');
        exit;
    }

    $vendor = $result->fetch_assoc();
    
    // Ambil statistik vendor secara terpisah untuk menghindari kesalahan
    $product_count_query = "SELECT COUNT(id) as total_products FROM products WHERE vendor_id = ?";
    $stmt_products = $conn->prepare($product_count_query);
    if ($stmt_products) {
        $stmt_products->bind_param('i', $vendor_id);
        $stmt_products->execute();
        $product_result = $stmt_products->get_result();
        $product_count = $product_result->fetch_assoc();
        $vendor['total_products'] = $product_count['total_products'];
    } else {
        $vendor['total_products'] = 0;
    }
    
    // Ambil jumlah pesanan
    $order_count_query = "SELECT COUNT(DISTINCT o.id) as total_orders 
                         FROM orders o 
                         JOIN order_items oi ON o.id = oi.order_id 
                         JOIN products p ON oi.product_id = p.id 
                         WHERE p.vendor_id = ?";
    $stmt_orders = $conn->prepare($order_count_query);
    if ($stmt_orders) {
        $stmt_orders->bind_param('i', $vendor_id);
        $stmt_orders->execute();
        $order_result = $stmt_orders->get_result();
        $order_count = $order_result->fetch_assoc();
        $vendor['total_orders'] = $order_count['total_orders'];
    } else {
        $vendor['total_orders'] = 0;
    }
    
} catch (Exception $e) {
    // Tampilkan error detail
    die("Fatal error: " . $e->getMessage());
}

// Cek apakah tabel vendor_documents ada
$check_table = $conn->query("SHOW TABLES LIKE 'vendor_documents'");
if ($check_table->num_rows > 0) {
    // Ambil dokumen vendor jika ada
    $documents_query = "SELECT * FROM vendor_documents WHERE vendor_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($documents_query);
    if ($stmt) {
        $stmt->bind_param('i', $vendor_id);
        $stmt->execute();
        $documents_result = $stmt->get_result();
        $documents = [];

        if ($documents_result->num_rows > 0) {
            while ($row = $documents_result->fetch_assoc()) {
                $documents[] = $row;
            }
        }
    } else {
        $documents = [];
    }
} else {
    $documents = [];
}

// Cek apakah tabel vendor_payments ada
$check_table = $conn->query("SHOW TABLES LIKE 'vendor_payments'");
if ($check_table->num_rows > 0) {
    // Ambil riwayat pembayaran ke vendor
    $payments_query = "SELECT vp.*, u.username as admin_username
                      FROM vendor_payments vp
                      LEFT JOIN users u ON vp.admin_id = u.id
                      WHERE vp.vendor_id = ?
                      ORDER BY vp.created_at DESC
                      LIMIT 10";
    $stmt = $conn->prepare($payments_query);
    if ($stmt) {
        $stmt->bind_param('i', $vendor_id);
        $stmt->execute();
        $payments_result = $stmt->get_result();
        $payments = [];

        if ($payments_result->num_rows > 0) {
            while ($row = $payments_result->fetch_assoc()) {
                $payments[] = $row;
            }
        }
    } else {
        $payments = [];
    }
} else {
    $payments = [];
}

// Ambil produk vendor
$products_query = "SELECT p.*, 
                  (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) as total_sold
                 FROM products p
                 WHERE p.vendor_id = ?
                 ORDER BY p.created_at DESC
                 LIMIT 10";
$stmt = $conn->prepare($products_query);
if ($stmt) {
    $stmt->bind_param('i', $vendor_id);
    $stmt->execute();
    $products_result = $stmt->get_result();
    $products = [];

    if ($products_result->num_rows > 0) {
        while ($row = $products_result->fetch_assoc()) {
            $products[] = $row;
        }
    }
} else {
    $products = [];
}

// Proses perubahan status vendor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $conn->real_escape_string($_POST['status']);
    $status_note = $conn->real_escape_string($_POST['status_note']);
    
    $update_query = "UPDATE vendors SET status = ?, status_note = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    if ($stmt) {
        $stmt->bind_param('ssi', $new_status, $status_note, $vendor_id);
        
        if ($stmt->execute()) {
            // Periksa apakah tabel notifications ada
            $check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
            if ($check_table->num_rows > 0) {
                // Kirim notifikasi ke vendor
                $notification_query = "INSERT INTO notifications (user_id, message, type, created_at) 
                                      VALUES (?, ?, ?, NOW())";
                $user_id = $vendor['user_id'];
                $message = "Status vendor Anda telah diperbarui menjadi: " . ucfirst($new_status);
                $type = 'vendor_status';
                
                $stmt = $conn->prepare($notification_query);
                if ($stmt) {
                    $stmt->bind_param('iss', $user_id, $message, $type);
                    $stmt->execute();
                }
            }
            
            $_SESSION['success_message'] = "Status vendor berhasil diperbarui menjadi " . ucfirst($new_status);
            header('Location: ' . SITE_URL . '/admin/vendor-details.php?id=' . $vendor_id);
            exit;
        } else {
            $_SESSION['error_message'] = "Gagal memperbarui status vendor: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = "Error pada query update: " . $conn->error;
    }
}

include '../includes/admin-header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Detail Vendor</h1>
        <a href="<?= SITE_URL ?>/admin/vendors.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i> Kembali
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
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?= $_SESSION['success_message'] ?>
            <?php unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?= $_SESSION['error_message'] ?>
            <?php unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Vendor Information Card -->
        <div class="col-xl-4 col-md-12 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Informasi Vendor</h6>
                    <div>
                        <?php
                        $status_badge_class = 'secondary';
                        switch($vendor['status']) {
                            case 'pending':
                                $status_badge_class = 'warning';
                                break;
                            case 'approved':
                                $status_badge_class = 'success';
                                break;
                            case 'rejected':
                                $status_badge_class = 'danger';
                                break;
                            case 'suspended':
                                $status_badge_class = 'dark';
                                break;
                        }
                        ?>
                        <span class="badge badge-<?= $status_badge_class ?>">
                            <?= ucfirst($vendor['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <?php if (!empty($vendor['logo']) && file_exists('../uploads/vendor_logos/' . $vendor['logo'])): ?>
                            <img class="img-profile rounded-circle mb-3" src="<?= SITE_URL ?>/uploads/vendor_logos/<?= $vendor['logo'] ?>" width="100">
                        <?php else: ?>
                            <div class="mb-3 rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px;">
                                <span class="h1"><?= strtoupper(substr($vendor['shop_name'], 0, 1)) ?></span>
                            </div>
                        <?php endif; ?>
                        <h4 class="h4 mb-0"><?= htmlspecialchars($vendor['shop_name']) ?></h4>
                        <p class="text-muted small">
                            Terdaftar: <?= date('d M Y', strtotime($vendor['created_at'])) ?>
                        </p>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <strong>Deskripsi:</strong>
                        <p><?= nl2br(htmlspecialchars($vendor['description'] ?? 'Tidak ada deskripsi')) ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Kategori:</strong>
                        <p><?= htmlspecialchars($vendor['category'] ?? 'Tidak ditentukan') ?></p>
                    </div>
                    
                    <hr>
                    
                    <h6 class="font-weight-bold">Informasi Kontak</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-user mr-2"></i> <?= htmlspecialchars($vendor['full_name']) ?>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-envelope mr-2"></i> <?= htmlspecialchars($vendor['email']) ?>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-phone mr-2"></i> <?= htmlspecialchars($vendor['phone']) ?>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-map-marker-alt mr-2"></i> <?= htmlspecialchars($vendor['address'] ?? 'Alamat tidak tersedia') ?>
                        </li>
                    </ul>
                    
                    <hr>
                    
                    <h6 class="font-weight-bold">Data Rekening</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <strong>Bank:</strong> <?= htmlspecialchars($vendor['bank_name'] ?? 'Belum diisi') ?>
                        </li>
                        <li class="mb-2">
                            <strong>No. Rekening:</strong> <?= htmlspecialchars($vendor['bank_account_number'] ?? 'Belum diisi') ?>
                        </li>
                        <li class="mb-2">
                            <strong>Atas Nama:</strong> <?= htmlspecialchars($vendor['bank_account_name'] ?? 'Belum diisi') ?>
                        </li>
                    </ul>
                    
                    <hr>
                    
                    <h6 class="font-weight-bold">Statistik</h6>
                    <div class="row">
                        <div class="col-6">
                            <div class="card bg-light mb-3">
                                <div class="card-body py-2 text-center">
                                    <div class="h2 mb-0"><?= $vendor['total_products'] ?? 0 ?></div>
                                    <div class="small text-muted">Produk</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-light mb-3">
                                <div class="card-body py-2 text-center">
                                    <div class="h2 mb-0"><?= $vendor['total_orders'] ?? 0 ?></div>
                                    <div class="small text-muted">Pesanan</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="col-xl-8 col-md-12">
            <!-- Status Update Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Update Status Vendor</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="status">Status:</label>
                            <select class="form-control" id="status" name="status">
                                <option value="pending" <?= ($vendor['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                                <option value="approved" <?= ($vendor['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= ($vendor['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                <option value="suspended" <?= ($vendor['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status_note">Catatan Status (akan dilihat oleh vendor):</label>
                            <textarea class="form-control" id="status_note" name="status_note" rows="3"><?= htmlspecialchars($vendor['status_note'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </form>
                    
                    <?php if (!empty($vendor['status_note'])): ?>
                        <div class="mt-3">
                            <strong>Catatan Status Saat Ini:</strong>
                            <div class="card bg-light mt-2">
                                <div class="card-body py-2">
                                    <?= nl2br(htmlspecialchars($vendor['status_note'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Documents Card -->
            <?php if (!empty($documents)): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Dokumen Vendor</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Jenis</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($doc['name']) ?></td>
                                        <td><?= htmlspecialchars($doc['type']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?></td>
                                        <td>
                                            <a href="<?= SITE_URL ?>/uploads/vendor_docs/<?= $doc['file_path'] ?>" class="btn btn-sm btn-info" target="_blank">
                                                <i class="fas fa-eye"></i> Lihat
                                            </a>
                                            <a href="<?= SITE_URL ?>/uploads/vendor_docs/<?= $doc['file_path'] ?>" class="btn btn-sm btn-primary" download>
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Products Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Produk Vendor</h6>
                    <a href="<?= SITE_URL ?>/admin/products.php?vendor_id=<?= $vendor_id ?>" class="btn btn-sm btn-primary">
                        Lihat Semua
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($products)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-box fa-3x text-gray-300 mb-3"></i>
                            <p class="mb-0">Vendor belum menambahkan produk.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Harga</th>
                                        <th>Stok</th>
                                        <th>Terjual</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($product['name']) ?></td>
                                            <td><?= formatPrice($product['price']) ?></td>
                                            <td><?= $product['stock'] ?></td>
                                            <td><?= $product['total_sold'] ?></td>
                                            <td>
                                                <?php if (isset($product['is_active']) && $product['is_active']): ?>
                                                    <span class="badge badge-success">Aktif</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Non-aktif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?= SITE_URL ?>/admin/product-detail.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> Detail
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Payment History Card -->
            <?php if (!empty($payments)): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Riwayat Pembayaran</h6>
                    <a href="<?= SITE_URL ?>/admin/vendor-payments.php?vendor_id=<?= $vendor_id ?>" class="btn btn-sm btn-primary">
                        Kelola Pembayaran
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Jumlah</th>
                                    <th>Metode</th>
                                    <th>Status</th>
                                    <th>Admin</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($payment['created_at'])) ?></td>
                                        <td><?= formatPrice($payment['amount']) ?></td>
                                        <td><?= htmlspecialchars($payment['payment_method']) ?></td>
                                        <td>
                                            <?php 
                                            $status_class = 'secondary';
                                            switch($payment['status']) {
                                                case 'pending': $status_class = 'warning'; break;
                                                case 'completed': $status_class = 'success'; break;
                                                case 'failed': $status_class = 'danger'; break;
                                            }
                                            ?>
                                            <span class="badge badge-<?= $status_class ?>">
                                                <?= ucfirst($payment['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($payment['admin_username'] ?? 'System') ?></td>
                                        <td><?= htmlspecialchars($payment['notes'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Update real-time datetime
    function updateDateTime() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        
        const formattedDateTime = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        $('#live-datetime').text(formattedDateTime);
    }
    
    // Update time every second
    setInterval(updateDateTime, 1000);
    updateDateTime(); // Call once immediately
    
    // Status change confirmation
    $('#status').on('change', function() {
        const newStatus = $(this).val();
        const oldStatus = '<?= $vendor['status'] ?? "" ?>';
        
        if (newStatus !== oldStatus) {
            if (newStatus === 'approved' && oldStatus === 'pending') {
                if (!$('#status_note').val().trim()) {
                    $('#status_note').val('Selamat! Aplikasi vendor Anda telah disetujui. Anda sekarang dapat menambahkan produk dan mulai berjualan.');
                }
            } else if (newStatus === 'rejected' && (oldStatus === 'pending' || oldStatus === 'approved')) {
                if (!$('#status_note').val().trim()) {
                    $('#status_note').val('Mohon maaf, aplikasi vendor Anda ditolak. Silakan hubungi admin untuk informasi lebih lanjut.');
                }
            } else if (newStatus === 'suspended' && oldStatus === 'approved') {
                if (!$('#status_note').val().trim()) {
                    $('#status_note').val('Akun vendor Anda telah ditangguhkan sementara. Silakan hubungi admin untuk informasi lebih lanjut.');
                }
            }
        }
    });
});
</script>

<?php include '../includes/admin-footer.php'; ?>