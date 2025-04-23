<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi vendor
if (!isLoggedIn() || $_SESSION['role'] !== 'vendor') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$pageTitle = 'Report Bug & Token Management';
$currentPage = 'report-bug';
$current_user = $_SESSION['username'] ?? 'VendorUser';
$current_datetime = date('Y-m-d H:i:s');

// Ambil data vendor
$vendor_id = 0;
$vendor_query = "SELECT v.id, v.shop_name FROM vendors v JOIN users u ON v.user_id = u.id WHERE u.id = ?";
$stmt = $conn->prepare($vendor_query);
if (!$stmt) {
    die("Error in vendor query: " . $conn->error);
}
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $vendor_data = $result->fetch_assoc();
    $vendor_id = $vendor_data['id'];
    $shop_name = $vendor_data['shop_name'];
} else {
    $_SESSION['error_message'] = "Anda tidak terdaftar sebagai vendor.";
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

// Pastikan tabel access_tokens ada
$check_tokens_table = "SHOW TABLES LIKE 'access_tokens'";
$tokens_table_exists = $conn->query($check_tokens_table);

if ($tokens_table_exists->num_rows == 0) {
    $create_tokens_table = "CREATE TABLE access_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vendor_id INT NOT NULL,
        order_id INT NOT NULL,
        token VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    )";
    $conn->query($create_tokens_table);
}

// Proses form submit laporan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $subject = $conn->real_escape_string($_POST['subject']);
    $message = $conn->real_escape_string($_POST['message']);
    $order_id = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    $token_validity = isset($_POST['token_validity']) ? (int)$_POST['token_validity'] : 48; // Default 48 jam
    
    // Validasi order ID jika disediakan
    $valid_order = true;
    if ($order_id) {
        // FIX: Changed from "id" to "o.id" to specify which table's id field we want
        $order_check = "SELECT o.id FROM orders o 
                       JOIN order_items oi ON o.id = oi.order_id 
                       JOIN products p ON oi.product_id = p.id 
                       WHERE p.vendor_id = ? AND o.id = ?";
        $stmt = $conn->prepare($order_check);
        if (!$stmt) {
            die("Error in order check query: " . $conn->error);
        }
        $stmt->bind_param('ii', $vendor_id, $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $valid_order = ($result && $result->num_rows > 0);
    }
    
    if (!$valid_order && $order_id) {
        $_SESSION['error_message'] = "Order ID tidak valid atau bukan milik Anda.";
    } else {
        // Generate token jika laporan terkait order
        $token = null;
        if ($order_id) {
            $token = bin2hex(random_bytes(16)); // 32 character token
            
            // Hapus token lama untuk order yang sama (jika ada)
            $delete_token = "DELETE FROM access_tokens WHERE vendor_id = ? AND order_id = ?";
            $stmt = $conn->prepare($delete_token);
            if (!$stmt) {
                die("Error in delete token query: " . $conn->error);
            }
            $stmt->bind_param('ii', $vendor_id, $order_id);
            $stmt->execute();
            
            // Simpan token baru dengan expiry date sesuai setting
            $expires_at = date('Y-m-d H:i:s', strtotime("+$token_validity hours"));
            $save_token = "INSERT INTO access_tokens (vendor_id, order_id, token, created_at, expires_at) 
                          VALUES (?, ?, ?, NOW(), ?)";
            $stmt = $conn->prepare($save_token);
            if (!$stmt) {
                die("Error in save token query: " . $conn->error);
            }
            $stmt->bind_param('iiss', $vendor_id, $order_id, $token, $expires_at);
            $stmt->execute();
        }
        
        // Simpan laporan
        $report_query = "INSERT INTO reports (vendor_id, order_id, subject, message, token, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($report_query);
        if (!$stmt) {
            die("Error in report save query: " . $conn->error);
        }
        $stmt->bind_param('iisss', $vendor_id, $order_id, $subject, $message, $token);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Laporan berhasil dikirim. Mohon menunggu 1 x 24 jam untuk mendapatkan balasan.";
            if ($token) {
                $_SESSION['success_message'] .= " Token akses untuk admin telah dibuat.";
            }
            header('Location: ' . SITE_URL . '/vendor/report-bug.php');
            exit;
        } else {
            $_SESSION['error_message'] = "Gagal mengirim laporan: " . $conn->error;
        }
    }
}

// Hapus token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_token'])) {
    $token_id = (int)$_POST['token_id'];
    
    // Verifikasi token milik vendor ini
    $check_token = "SELECT id FROM access_tokens WHERE id = ? AND vendor_id = ?";
    $stmt = $conn->prepare($check_token);
    if (!$stmt) {
        die("Error in check token query: " . $conn->error);
    }
    $stmt->bind_param('ii', $token_id, $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        // Hapus token
        $delete_query = "DELETE FROM access_tokens WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        if (!$stmt) {
            die("Error in delete token query: " . $conn->error);
        }
        $stmt->bind_param('i', $token_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Token berhasil dihapus.";
        } else {
            $_SESSION['error_message'] = "Gagal menghapus token: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = "Token tidak ditemukan atau bukan milik Anda.";
    }
    
    header('Location: ' . SITE_URL . '/vendor/report-bug.php');
    exit;
}

// Generate token baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_token'])) {
    $order_id = (int)$_POST['order_id'];
    $token_validity = (int)$_POST['token_validity'];
    
    // Validasi order ID
    $order_check = "SELECT o.id FROM orders o 
                   JOIN order_items oi ON o.id = oi.order_id 
                   JOIN products p ON oi.product_id = p.id 
                   WHERE p.vendor_id = ? AND o.id = ?";
    $stmt = $conn->prepare($order_check);
    if (!$stmt) {
        die("Error in order check query: " . $conn->error);
    }
    $stmt->bind_param('ii', $vendor_id, $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        // Hapus token lama
        $delete_token = "DELETE FROM access_tokens WHERE vendor_id = ? AND order_id = ?";
        $stmt = $conn->prepare($delete_token);
        if (!$stmt) {
            die("Error in delete token query: " . $conn->error);
        }
        $stmt->bind_param('ii', $vendor_id, $order_id);
        $stmt->execute();
        
        // Generate dan simpan token baru
        $token = bin2hex(random_bytes(16));
        $expires_at = date('Y-m-d H:i:s', strtotime("+$token_validity hours"));
        
        $save_token = "INSERT INTO access_tokens (vendor_id, order_id, token, created_at, expires_at) 
                      VALUES (?, ?, ?, NOW(), ?)";
        $stmt = $conn->prepare($save_token);
        if (!$stmt) {
            die("Error in save token query: " . $conn->error);
        }
        $stmt->bind_param('iiss', $vendor_id, $order_id, $token, $expires_at);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Token baru berhasil dibuat dan berlaku hingga $expires_at.";
        } else {
            $_SESSION['error_message'] = "Gagal membuat token: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = "Order ID tidak valid atau bukan milik Anda.";
    }
    
    header('Location: ' . SITE_URL . '/vendor/report-bug.php');
    exit;
}

// Ambil laporan vendor
$reports_query = "SELECT r.*, o.order_number, 
                 CASE 
                    WHEN r.status = 'pending' THEN 'Menunggu'
                    WHEN r.status = 'in_progress' THEN 'Diproses'
                    WHEN r.status = 'resolved' THEN 'Selesai'
                    WHEN r.status = 'rejected' THEN 'Ditolak'
                    ELSE 'Unknown'
                 END as status_text
                 FROM reports r 
                 LEFT JOIN orders o ON r.order_id = o.id 
                 WHERE r.vendor_id = ? 
                 ORDER BY r.created_at DESC";
$stmt = $conn->prepare($reports_query);
if (!$stmt) {
    die("Error in reports query: " . $conn->error);
}
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$reports_result = $stmt->get_result();
$reports = [];

if ($reports_result && $reports_result->num_rows > 0) {
    while ($row = $reports_result->fetch_assoc()) {
        $reports[] = $row;
    }
}

// Ambil order yang dimiliki vendor
$order_query = "SELECT DISTINCT o.id, o.order_number 
               FROM orders o 
               JOIN order_items oi ON o.id = oi.order_id 
               JOIN products p ON oi.product_id = p.id 
               WHERE p.vendor_id = ? AND o.status != 'cancelled' 
               ORDER BY o.created_at DESC
               LIMIT 50"; // Only get the 50 most recent orders
$stmt = $conn->prepare($order_query);
if (!$stmt) {
    die("Error in orders query: " . $conn->error);
}
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$orders_result = $stmt->get_result();
$orders = [];

if ($orders_result && $orders_result->num_rows > 0) {
    while ($row = $orders_result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Ambil active tokens
$tokens_query = "SELECT at.*, o.order_number 
                FROM access_tokens at
                JOIN orders o ON at.order_id = o.id
                WHERE at.vendor_id = ?
                ORDER BY at.created_at DESC";
$stmt = $conn->prepare($tokens_query);
if (!$stmt) {
    die("Error in tokens query: " . $conn->error);
}
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$tokens_result = $stmt->get_result();
$tokens = [];

if ($tokens_result && $tokens_result->num_rows > 0) {
    while ($row = $tokens_result->fetch_assoc()) {
        $tokens[] = $row;
    }
}

include '../includes/vendor-header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Report Bug & Token Management</h1>
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
        <!-- Submit Report Form -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Laporkan Masalah</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="subject">Subjek Laporan:</label>
                            <input type="text" class="form-control" id="subject" name="subject" required placeholder="Masukkan subjek laporan...">
                        </div>
                        
                        <div class="form-group">
                            <label for="order_id">Terkait Pesanan (Opsional):</label>
                            <select class="form-control" id="order_id" name="order_id">
                                <option value="">-- Pilih Pesanan (Opsional) --</option>
                                <?php foreach ($orders as $order): ?>
                                    <option value="<?= $order['id'] ?>">Order #<?= $order['order_number'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Jika dipilih, admin akan mendapatkan token akses ke pesanan ini.</small>
                        </div>
                        
                        <div class="form-group" id="token_validity_group" style="display: none;">
                            <label for="token_validity">Masa Berlaku Token:</label>
                            <select class="form-control" id="token_validity" name="token_validity">
                                <option value="24">24 Jam</option>
                                <option value="48" selected>48 Jam (2 Hari)</option>
                                <option value="72">72 Jam (3 Hari)</option>
                                <option value="168">1 Minggu</option>
                            </select>
                            <small class="form-text text-muted">Token hanya berlaku selama periode ini dan akan kedaluwarsa setelahnya.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Pesan Laporan:</label>
                            <textarea class="form-control" id="message" name="message" rows="6" required placeholder="Jelaskan detail masalah yang Anda alami..."></textarea>
                        </div>
                        
                        <button type="submit" name="submit_report" class="btn btn-primary btn-block">
                            <i class="fas fa-paper-plane mr-1"></i> Kirim Laporan
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Token Management -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Manajemen Token</h6>
                    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#newTokenModal">
                        <i class="fas fa-plus-circle mr-1"></i> Buat Token Baru
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($tokens)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-key fa-3x text-gray-300 mb-3"></i>
                            <p class="mb-0">Belum ada token akses yang dibuat.</p>
                            <p class="text-muted small">Token akses akan memungkinkan admin untuk melihat detail pesanan Anda.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Token</th>
                                        <th>Dibuat</th>
                                        <th>Berlaku Hingga</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tokens as $token): ?>
                                        <?php 
                                        $is_expired = strtotime($token['expires_at']) < time();
                                        $expires_in = round((strtotime($token['expires_at']) - time()) / 3600, 1); // in hours
                                        ?>
                                        <tr class="<?= $is_expired ? 'table-secondary' : '' ?>">
                                            <td>
                                                <a href="<?= SITE_URL ?>/vendor/orders.php?id=<?= $token['order_id'] ?>" target="_blank">
                                                    #<?= $token['order_number'] ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm token-field" 
                                                           value="<?= $token['token'] ?>" readonly>
                                                    <div class="input-group-append">
                                                        <button class="btn btn-outline-secondary btn-sm copy-token" 
                                                                type="button" data-token="<?= $token['token'] ?>"
                                                                <?= $is_expired ? 'disabled' : '' ?>>
                                                            <i class="fas fa-copy"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= date('d/m/y H:i', strtotime($token['created_at'])) ?></td>
                                            <td><?= date('d/m/y H:i', strtotime($token['expires_at'])) ?></td>
                                            <td>
                                                <?php if ($is_expired): ?>
                                                    <span class="badge badge-danger">Kedaluwarsa</span>
                                                <?php elseif ($expires_in <= 12): ?>
                                                    <span class="badge badge-warning">
                                                        <?= $expires_in ?> jam lagi
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-success">
                                                        Aktif (<?= $expires_in ?> jam)
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" action="" style="display:inline;" 
                                                      onsubmit="return confirm('Yakin ingin menghapus token ini?');">
                                                    <input type="hidden" name="token_id" value="<?= $token['id'] ?>">
                                                    <button type="submit" name="delete_token" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                                
                                                <?php if ($is_expired): ?>
                                                    <button type="button" class="btn btn-sm btn-primary regenerate-token"
                                                            data-toggle="modal" data-target="#regenerateTokenModal"
                                                            data-order-id="<?= $token['order_id'] ?>"
                                                            data-order-number="<?= $token['order_number'] ?>">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-muted small mt-2">
                            <i class="fas fa-info-circle mr-1"></i> Token yang kedaluwarsa tidak bisa digunakan admin untuk mengakses detail pesanan.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reports List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Riwayat Laporan</h6>
            <span class="badge badge-pill badge-primary"><?= count($reports) ?> Laporan</span>
        </div>
        <div class="card-body">
            <?php if (empty($reports)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-comments fa-3x text-gray-300 mb-3"></i>
                    <p class="mb-0">Belum ada laporan yang dibuat.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tanggal</th>
                                <th>Subjek</th>
                                <th>Pesanan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td><?= $report['id'] ?></td>
                                    <td><?= date('d/m/y H:i', strtotime($report['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($report['subject']) ?></td>
                                    <td>
                                        <?php if ($report['order_id']): ?>
                                            <a href="<?= SITE_URL ?>/vendor/orders.php?id=<?= $report['order_id'] ?>" target="_blank">
                                                Order #<?= $report['order_number'] ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = 'secondary';
                                        
                                        switch ($report['status']) {
                                            case 'pending':
                                                $status_class = 'warning';
                                                break;
                                            case 'in_progress':
                                                $status_class = 'info';
                                                break;
                                            case 'resolved':
                                                $status_class = 'success';
                                                break;
                                            case 'rejected':
                                                $status_class = 'danger';
                                                break;
                                        }
                                        ?>
                                        <span class="badge badge-<?= $status_class ?>"><?= $report['status_text'] ?></span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info view-report-btn" 
                                                data-id="<?= $report['id'] ?>"
                                                data-subject="<?= htmlspecialchars($report['subject']) ?>"
                                                data-message="<?= htmlspecialchars($report['message']) ?>"
                                                data-order="<?= $report['order_number'] ? 'Order #'.$report['order_number'] : '-' ?>"
                                                data-status="<?= $report['status_text'] ?>"
                                                data-created="<?= date('d M Y H:i', strtotime($report['created_at'])) ?>"
                                                data-response="<?= htmlspecialchars($report['response'] ?? '') ?>"
                                                data-token="<?= $report['token'] ?>">
                                            <i class="fas fa-eye"></i> Lihat
                                        </button>
                                        
                                        <?php if ($report['token']): 
                                            // Check if token is still valid
                                            $token_query = "SELECT * FROM access_tokens WHERE token = ? AND expires_at > NOW()";
                                            $stmt = $conn->prepare($token_query);
                                            if (!$stmt) {
                                                echo "Error checking token: " . $conn->error;
                                            } else {
                                                $stmt->bind_param('s', $report['token']);
                                                $stmt->execute();
                                                $token_result = $stmt->get_result();
                                                $is_valid = ($token_result && $token_result->num_rows > 0);
                                        ?>
                                            <button type="button" class="btn btn-sm btn-<?= $is_valid ? 'primary' : 'secondary' ?> copy-token-btn" 
                                                    data-token="<?= $report['token'] ?>"
                                                    <?= !$is_valid ? 'disabled' : '' ?>>
                                                <i class="fas fa-key"></i> <?= $is_valid ? 'Token' : 'Expired' ?>
                                            </button>
                                        <?php 
                                            }
                                        endif; 
                                        ?>
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

<!-- Report Detail Modal -->
<div class="modal fade" id="reportDetailModal" tabindex="-1" role="dialog" aria-labelledby="reportDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportDetailModalLabel">Detail Laporan #<span id="modal-report-id"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>ID:</strong> <span id="modal-report-id-text"></span></p>
                        <p><strong>Tanggal:</strong> <span id="modal-report-created"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> <span id="modal-report-status" class="badge"></span></p>
                        <p><strong>Pesanan:</strong> <span id="modal-report-order"></span></p>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">Subjek</h6>
                    </div>
                    <div class="card-body">
                        <p id="modal-report-subject"></p>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">Pesan Anda</h6>
                    </div>
                    <div class="card-body">
                        <p id="modal-report-message"></p>
                    </div>
                </div>
                
                <div class="card" id="response-card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-success">Balasan Admin</h6>
                    </div>
                    <div class="card-body">
                        <p id="modal-report-response">Belum ada balasan dari admin.</p>
                    </div>
                </div>
                
                <div id="token-info" class="mt-3 alert alert-info" style="display: none;">
                    <h5 class="alert-heading"><i class="fas fa-key mr-2"></i> Token Akses</h5>
                    <p>Token ini dapat digunakan admin untuk mengakses detail pesanan:</p>
                    <div class="input-group mb-2">
                        <input type="text" id="modal-token-value" class="form-control" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary copy-modal-token" type="button">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                    <div id="token-status-message"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-link text-danger refresh-page-btn">
                    <small><i class="fas fa-sync-alt"></i> Refresh jika terkunci</small>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- New Token Modal -->
<div class="modal fade" id="newTokenModal" tabindex="-1" role="dialog" aria-labelledby="newTokenModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newTokenModalLabel">Buat Token Baru</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_token_order_id">Pilih Pesanan:</label>
                        <select class="form-control" id="new_token_order_id" name="order_id" required>
                            <option value="">-- Pilih Pesanan --</option>
                            <?php foreach ($orders as $order): ?>
                                <option value="<?= $order['id'] ?>">Order #<?= $order['order_number'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Token ini akan memberi admin akses ke detail pesanan yang dipilih.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_token_validity">Masa Berlaku Token:</label>
                        <select class="form-control" id="new_token_validity" name="token_validity">
                            <option value="24">24 Jam</option>
                            <option value="48" selected>48 Jam (2 Hari)</option>
                            <option value="72">72 Jam (3 Hari)</option>
                            <option value="168">1 Minggu</option>
                        </select>
                        <small class="form-text text-muted">Token hanya berlaku selama periode ini dan akan kedaluwarsa setelahnya.</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Jika sudah ada token untuk pesanan ini, token lama akan dihapus dan diganti dengan token baru.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="generate_token" class="btn btn-primary">Generate Token</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Regenerate Token Modal -->
<div class="modal fade" id="regenerateTokenModal" tabindex="-1" role="dialog" aria-labelledby="regenerateTokenModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="regenerateTokenModalLabel">Regenerate Token</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <p>Anda akan membuat ulang token untuk <strong>Order #<span id="regenerate-order-number"></span></strong>.</p>
                    
                    <input type="hidden" id="regenerate-order-id" name="order_id">
                    
                    <div class="form-group">
                        <label for="regen_token_validity">Masa Berlaku Token Baru:</label>
                        <select class="form-control" id="regen_token_validity" name="token_validity">
                            <option value="24">24 Jam</option>
                            <option value="48" selected>48 Jam (2 Hari)</option>
                            <option value="72">72 Jam (3 Hari)</option>
                            <option value="168">1 Minggu</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-1"></i> Token baru akan dibuat dan token lama akan dihapus.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="generate_token" class="btn btn-primary">Generate Token Baru</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create check_token.php script for AJAX calls if doesn't exist -->
<?php
$check_token_path = __DIR__ . '/check_token.php';
if (!file_exists($check_token_path)) {
    $check_token_content = <<<EOT
<?php
require_once '../includes/config.php';

if (\$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_POST['token'])) {
    \$token = \$conn->real_escape_string(\$_POST['token']);
    
    // Check if token exists and is valid
    \$token_query = "SELECT *, TIMESTAMPDIFF(HOUR, NOW(), expires_at) as hours_left 
                   FROM access_tokens 
                   WHERE token = ?";
    \$stmt = \$conn->prepare(\$token_query);
    if (\$stmt) {
        \$stmt->bind_param('s', \$token);
        \$stmt->execute();
        \$result = \$stmt->get_result();
        
        if (\$result && \$result->num_rows > 0) {
            \$token_data = \$result->fetch_assoc();
            \$hours_left = \$token_data['hours_left'];
            \$expires_at = date('d M Y H:i', strtotime(\$token_data['expires_at']));
            
            echo json_encode([
                'valid' => \$hours_left > 0,
                'expires_in' => \$hours_left,
                'expires_at' => \$expires_at
            ]);
        } else {
            echo json_encode([
                'valid' => false,
                'message' => 'Token tidak ditemukan atau sudah kedaluwarsa'
            ]);
        }
    } else {
        echo json_encode([
            'valid' => false,
            'message' => 'Permintaan tidak valid'
        ]);
    }
} else {
    echo json_encode([
        'valid' => false,
        'message' => 'Permintaan tidak valid'
    ]);
}
EOT;
    file_put_contents($check_token_path, $check_token_content);
}
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    console.log("Document ready for report-bug.php");
    
    // Force cleanup any lingering modals on page load
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css({
        'overflow': '',
        'padding-right': ''
    });
    
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
    
    // Show token validity options when order is selected
    $('#order_id').on('change', function() {
        if ($(this).val()) {
            $('#token_validity_group').slideDown();
        } else {
            $('#token_validity_group').slideUp();
        }
    });
    
    // Copy token buttons
    $('.copy-token').on('click', function() {
        const token = $(this).data('token');
        const btn = $(this);
        const originalHtml = btn.html();
        
        navigator.clipboard.writeText(token).then(function() {
            btn.html('<i class="fas fa-check"></i>');
            setTimeout(function() {
                btn.html(originalHtml);
            }, 1500);
        }).catch(function(err) {
            console.error('Failed to copy: ', err);
            alert('Gagal menyalin token. Silakan salin secara manual.');
        });
    });
    
    // Copy token from report buttons
    $('.copy-token-btn').on('click', function() {
        const token = $(this).data('token');
        const btn = $(this);
        const originalHtml = btn.html();
        
        navigator.clipboard.writeText(token).then(function() {
            btn.html('<i class="fas fa-check"></i> Disalin!');
            setTimeout(function() {
                btn.html(originalHtml);
            }, 1500);
        }).catch(function(err) {
            console.error('Failed to copy: ', err);
            alert('Gagal menyalin token. Silakan salin secara manual.');
        });
    });
    
    // Setup regenerate token buttons
    $('.regenerate-token').on('click', function() {
        const orderId = $(this).data('order-id');
        const orderNumber = $(this).data('order-number');
        
        $('#regenerate-order-id').val(orderId);
        $('#regenerate-order-number').text(orderNumber);
    });
    
    // View report details
    $('.view-report-btn').on('click', function(e) {
        e.preventDefault();
        console.log("View report button clicked");
        
        // Clean up any existing modals
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css({
            'overflow': '',
            'padding-right': ''
        });
        
        // Get report data
        const id = $(this).data('id');
        const subject = $(this).data('subject');
        const message = $(this).data('message');
        const order = $(this).data('order');
        const status = $(this).data('status');
        const created = $(this).data('created');
        const response = $(this).data('response');
        const token = $(this).data('token');
        
        console.log("Loading report ID:", id);
        
        // Set modal data
        $('#modal-report-id, #modal-report-id-text').text(id);
        $('#modal-report-subject').text(subject);
        $('#modal-report-message').text(message);
        $('#modal-report-order').text(order);
        $('#modal-report-created').text(created);
        
        // Set status with badge
        const statusEl = $('#modal-report-status');
        statusEl.removeClass().addClass('badge');
        
        if (status === 'Menunggu') {
            statusEl.addClass('badge-warning');
        } else if (status === 'Diproses') {
            statusEl.addClass('badge-info');
        } else if (status === 'Selesai') {
            statusEl.addClass('badge-success');
        } else if (status === 'Ditolak') {
            statusEl.addClass('badge-danger');
        } else {
            statusEl.addClass('badge-secondary');
        }
        statusEl.text(status);
        
        // Set response if exists
        if (response && response.trim() !== '') {
            $('#modal-report-response').text(response);
            $('#response-card').show();
        } else {
            $('#modal-report-response').text('Belum ada balasan dari admin.');
            $('#response-card').show();
        }
        
        // Check if token exists and if it's still valid
        if (token) {
            $('#modal-token-value').val(token);
            
            // Make AJAX call to check token validity
            $.ajax({
                url: '<?= SITE_URL ?>/vendor/check_token.php',
                method: 'POST',
                data: {token: token},
                dataType: 'json',
                success: function(response) {
                    if (response.valid) {
                        const expiresIn = response.expires_in;
                        let statusHtml = '';
                        
                        if (expiresIn <= 0) {
                            statusHtml = '<div class="text-danger"><i class="fas fa-exclamation-circle"></i> Token telah kedaluwarsa.</div>';
                            $('.copy-modal-token').prop('disabled', true);
                        } else if (expiresIn <= 12) {
                            statusHtml = '<div class="text-warning"><i class="fas fa-exclamation-triangle"></i> Token akan kedaluwarsa dalam ' + expiresIn.toFixed(1) + ' jam.</div>';
                        } else {
                            statusHtml = '<div class="text-success"><i class="fas fa-check-circle"></i> Token berlaku hingga ' + response.expires_at + ' (' + expiresIn.toFixed(1) + ' jam lagi).</div>';
                        }
                        
                        $('#token-status-message').html(statusHtml);
                        $('#token-info').show();
                    } else {
                        $('#token-status-message').html('<div class="text-danger"><i class="fas fa-exclamation-circle"></i> Token telah kedaluwarsa.</div>');
                        $('.copy-modal-token').prop('disabled', true);
                        $('#token-info').show();
                    }
                },
                error: function() {
                    $('#token-status-message').html('<div class="text-danger">Gagal memeriksa status token.</div>');
                    $('#token-info').show();
                }
            });
        } else {
            $('#token-info').hide();
        }
        
        // Show modal
        $('#reportDetailModal').modal('show');
    });
    
    // Copy token from modal
    $('.copy-modal-token').on('click', function() {
        const token = $('#modal-token-value').val();
        const btn = $(this);
        const originalHtml = btn.html();
        
        navigator.clipboard.writeText(token).then(function() {
            btn.html('<i class="fas fa-check"></i> Disalin!');
            setTimeout(function() {
                btn.html(originalHtml);
            }, 1500);
        }).catch(function(err) {
            console.error('Failed to copy: ', err);
            alert('Gagal menyalin token. Silakan salin secara manual.');
        });
    });
    
    // Modal cleanup handlers
    $(document).on('hidden.bs.modal', '.modal', function() {
        console.log("Modal hidden - cleaning up");
        
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css({
            'overflow': '',
            'padding-right': ''
        });
    });
    
    $('[data-dismiss="modal"]').on('click', function(e) {
        e.preventDefault();
        
        const modal = $(this).closest('.modal');
        modal.modal('hide');
        
        setTimeout(function() {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css({
                'overflow': '',
                'padding-right': ''
            });
        }, 200);
    });
    
    // Refresh page button
    $('.refresh-page-btn').on('click', function() {
        location.reload();
    });
});
</script>

<?php include '../includes/vendor-footer.php'; ?>