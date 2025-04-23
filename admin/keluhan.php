<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$pageTitle = 'Keluhan Vendor';
$currentPage = 'Complaint';
$current_user = $_SESSION['username'] ?? 'MochSetiawan';
$current_datetime = date('Y-m-d H:i:s');

// Pastikan tabel reports dan access_tokens ada
$check_reports_table = "SHOW TABLES LIKE 'reports'";
$reports_table_exists = $conn->query($check_reports_table);

if ($reports_table_exists->num_rows == 0) {
    $create_reports_table = "CREATE TABLE reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vendor_id INT NOT NULL,
        order_id INT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('pending', 'in_progress', 'resolved', 'rejected') DEFAULT 'pending',
        token VARCHAR(100) NULL,
        response TEXT NULL,
        is_read BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
    )";
    $conn->query($create_reports_table);
}

// Check if access_tokens table exists
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

// Proses update status & jawaban laporan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_report'])) {
    $report_id = (int)$_POST['report_id'];
    $status = $conn->real_escape_string($_POST['status']);
    $response = $conn->real_escape_string($_POST['response']);
    
    $update_query = "UPDATE reports 
                    SET status = '$status', response = '$response', updated_at = NOW() 
                    WHERE id = $report_id";
    
    if ($conn->query($update_query)) {
        $_SESSION['success_message'] = "Laporan berhasil diperbarui";
    } else {
        $_SESSION['error_message'] = "Gagal memperbarui laporan: " . $conn->error;
    }
    
    // Redirect untuk menghindari resubmission saat refresh
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Filter laporan
$status_filter = '';
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = $conn->real_escape_string($_GET['status']);
    $status_filter = " WHERE r.status = '$status'";
}

// Ambil semua laporan dari vendor
$reports_query = "SELECT r.*, v.shop_name, u.username as vendor_username, o.order_number,
                 (SELECT at.token FROM access_tokens at WHERE at.order_id = r.order_id AND at.vendor_id = r.vendor_id AND at.expires_at > NOW() LIMIT 1) as active_token,
                 (SELECT at.expires_at FROM access_tokens at WHERE at.order_id = r.order_id AND at.vendor_id = r.vendor_id AND at.expires_at > NOW() LIMIT 1) as token_expires_at
                 FROM reports r
                 JOIN vendors v ON r.vendor_id = v.id
                 JOIN users u ON v.user_id = u.id
                 LEFT JOIN orders o ON r.order_id = o.id
                 $status_filter
                 ORDER BY 
                    CASE 
                        WHEN r.status = 'pending' THEN 1
                        WHEN r.status = 'in_progress' THEN 2
                        WHEN r.status = 'resolved' THEN 3
                        WHEN r.status = 'rejected' THEN 4
                    END,
                    r.created_at DESC";
$reports_result = $conn->query($reports_query);
$reports = [];

if ($reports_result && $reports_result->num_rows > 0) {
    while ($row = $reports_result->fetch_assoc()) {
        $reports[] = $row;
    }
}

// Tandai laporan yang belum dibaca sebagai sudah dibaca
if (!empty($reports)) {
    $unread_ids = [];
    foreach ($reports as &$report) {
        if (!$report['is_read']) {
            $unread_ids[] = $report['id'];
            $report['is_read'] = 1; // Update di array untuk tampilan
        }
        
        // Add token status information
        if (!empty($report['active_token']) && !empty($report['token_expires_at'])) {
            $report['token_valid'] = true;
            $report['token_expires_in_hours'] = round((strtotime($report['token_expires_at']) - time()) / 3600, 1);
        } else {
            $report['token_valid'] = false;
            $report['token_expires_in_hours'] = 0;
        }
    }
    
    if (!empty($unread_ids)) {
        $ids_str = implode(',', $unread_ids);
        $mark_read_query = "UPDATE reports SET is_read = 1 WHERE id IN ($ids_str)";
        $conn->query($mark_read_query);
    }
}

// Hitung jumlah laporan per status
$status_counts = [
    'all' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'rejected' => 0
];

$count_query = "SELECT status, COUNT(*) as count FROM reports GROUP BY status";
$count_result = $conn->query($count_query);

if ($count_result && $count_result->num_rows > 0) {
    while ($row = $count_result->fetch_assoc()) {
        $status_counts[$row['status']] = $row['count'];
        $status_counts['all'] += $row['count'];
    }
}

include '../includes/admin-header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Keluhan & Laporan Vendor</h1>
        <a href="<?= SITE_URL ?>/admin/keluhan.php?refresh=<?= time() ?>" class="btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-sync-alt fa-sm text-white-50 mr-1"></i> Refresh Data
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
    
    <!-- Status Overview Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Menunggu</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $status_counts['pending'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Diproses</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $status_counts['in_progress'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-spinner fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Selesai</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $status_counts['resolved'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Ditolak</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $status_counts['rejected'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Buttons -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap">
                <a href="<?= SITE_URL ?>/admin/keluhan.php" class="btn <?= !isset($_GET['status']) ? 'btn-primary' : 'btn-outline-secondary' ?> mr-2 mb-2">
                    Semua <span class="badge badge-light"><?= $status_counts['all'] ?></span>
                </a>
                <a href="<?= SITE_URL ?>/admin/keluhan.php?status=pending" class="btn <?= isset($_GET['status']) && $_GET['status'] === 'pending' ? 'btn-primary' : 'btn-outline-warning' ?> mr-2 mb-2">
                    Menunggu <span class="badge badge-light"><?= $status_counts['pending'] ?></span>
                </a>
                <a href="<?= SITE_URL ?>/admin/keluhan.php?status=in_progress" class="btn <?= isset($_GET['status']) && $_GET['status'] === 'in_progress' ? 'btn-primary' : 'btn-outline-info' ?> mr-2 mb-2">
                    Diproses <span class="badge badge-light"><?= $status_counts['in_progress'] ?></span>
                </a>
                <a href="<?= SITE_URL ?>/admin/keluhan.php?status=resolved" class="btn <?= isset($_GET['status']) && $_GET['status'] === 'resolved' ? 'btn-primary' : 'btn-outline-success' ?> mr-2 mb-2">
                    Selesai <span class="badge badge-light"><?= $status_counts['resolved'] ?></span>
                </a>
                <a href="<?= SITE_URL ?>/admin/keluhan.php?status=rejected" class="btn <?= isset($_GET['status']) && $_GET['status'] === 'rejected' ? 'btn-primary' : 'btn-outline-danger' ?> mr-2 mb-2">
                    Ditolak <span class="badge badge-light"><?= $status_counts['rejected'] ?></span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Reports List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Laporan</h6>
            <div>
                <span class="text-xs text-gray-500">Total: <?= count($reports) ?> laporan</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($reports)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-comments fa-3x text-gray-300 mb-3"></i>
                    <p class="mb-0">Tidak ada laporan yang ditemukan.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="dataReports">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="15%">Tanggal</th>
                                <th width="15%">Vendor</th>
                                <th width="25%">Subjek</th>
                                <th width="10%">Pesanan</th>
                                <th width="10%">Status</th>
                                <th width="20%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr class="<?= $report['is_read'] ? '' : 'table-warning' ?>">
                                    <td><?= $report['id'] ?></td>
                                    <td><?= date('d M Y H:i', strtotime($report['created_at'])) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($report['shop_name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($report['vendor_username']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($report['subject']) ?></td>
                                    <td>
                                        <?php if ($report['order_id']): ?>
                                            <?php if ($report['token_valid']): ?>
                                                <a href="order_detail.php?id=<?= $report['order_id'] ?>&token=<?= $report['active_token'] ?>" target="_blank">
                                                    Order #<?= $report['order_number'] ?>
                                                    <span class="badge badge-success">Token Valid</span>
                                                </a>
                                            <?php else: ?>
                                                Order #<?= $report['order_number'] ?>
                                                <?php if ($report['token']): ?>
                                                    <span class="badge badge-danger">Token Expired</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = 'secondary';
                                        $status_text = 'Unknown';
                                        
                                        switch ($report['status']) {
                                            case 'pending':
                                                $status_class = 'warning';
                                                $status_text = 'Menunggu';
                                                break;
                                            case 'in_progress':
                                                $status_class = 'info';
                                                $status_text = 'Diproses';
                                                break;
                                            case 'resolved':
                                                $status_class = 'success';
                                                $status_text = 'Selesai';
                                                break;
                                            case 'rejected':
                                                $status_class = 'danger';
                                                $status_text = 'Ditolak';
                                                break;
                                        }
                                        ?>
                                        <span class="badge badge-<?= $status_class ?>"><?= $status_text ?></span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info view-report-btn" 
                                                data-id="<?= $report['id'] ?>"
                                                data-vendor="<?= htmlspecialchars($report['shop_name']) ?>"
                                                data-subject="<?= htmlspecialchars($report['subject']) ?>"
                                                data-message="<?= htmlspecialchars($report['message']) ?>"
                                                data-order="<?= $report['order_number'] ? '#'.$report['order_number'] : '-' ?>"
                                                data-order-id="<?= $report['order_id'] ?? '' ?>"
                                                data-token="<?= $report['active_token'] ?? '' ?>"
                                                data-token-valid="<?= $report['token_valid'] ? '1' : '0' ?>"
                                                data-token-expires="<?= $report['token_expires_at'] ?? '' ?>"
                                                data-token-expires-hours="<?= $report['token_expires_in_hours'] ?? '0' ?>"
                                                data-status="<?= $report['status'] ?>"
                                                data-created="<?= date('d M Y H:i', strtotime($report['created_at'])) ?>"
                                                data-response="<?= htmlspecialchars($report['response'] ?? '') ?>">
                                            <i class="fas fa-eye"></i> Lihat
                                        </button>
                                        
                                        <button type="button" class="btn btn-sm btn-primary reply-btn"
                                                data-id="<?= $report['id'] ?>"
                                                data-subject="<?= htmlspecialchars($report['subject']) ?>"
                                                data-status="<?= $report['status'] ?>"
                                                data-response="<?= htmlspecialchars($report['response'] ?? '') ?>">
                                            <i class="fas fa-reply"></i> Balas
                                        </button>
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
                        <p><strong>Tanggal:</strong> <span id="modal-report-created"></span></p>
                        <p><strong>Vendor:</strong> <span id="modal-report-vendor"></span></p>
                        <p><strong>Status:</strong> <span id="modal-report-status" class="badge"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Subjek:</strong> <span id="modal-report-subject"></span></p>
                        <p><strong>Pesanan:</strong> <span id="modal-report-order"></span></p>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">Pesan Dari Vendor</h6>
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
                        <p id="modal-report-response">Belum ada balasan.</p>
                    </div>
                </div>
                
                <div id="order-token-section" class="mt-3">
                    <div class="alert alert-info">
                        <div class="d-flex align-items-center">
                            <div class="mr-3">
                                <i class="fas fa-key fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="alert-heading">Token Akses Pesanan</h5>
                                <p class="mb-0">Token untuk mengakses detail pesanan: <code id="modal-report-token"></code></p>
                                <div id="token-status" class="mt-1"></div>
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-outline-primary" id="copy-token-btn">
                                        <i class="fas fa-copy"></i> Copy Token
                                    </button>
                                    <a href="#" class="btn btn-sm btn-outline-info ml-2" id="view-order-btn" target="_blank">
                                        <i class="fas fa-eye"></i> Lihat Pesanan dengan Token
                                    </a>
                                    <button class="btn btn-sm btn-outline-secondary ml-2" id="add-token-session-btn">
                                        <i class="fas fa-plus-circle"></i> Tambahkan ke Session
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="order-token-expired" class="mt-3" style="display:none;">
                    <div class="alert alert-warning">
                        <div class="d-flex align-items-center">
                            <div class="mr-3">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="alert-heading">Token Telah Kedaluwarsa</h5>
                                <p class="mb-0">Token akses untuk pesanan ini telah kedaluwarsa. Vendor perlu membuat token baru.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary respond-from-detail">
                    <i class="fas fa-reply"></i> Balas Laporan
                </button>
                <button type="button" class="btn btn-link text-danger refresh-page-btn">
                    <small><i class="fas fa-sync-alt"></i> Refresh jika terkunci</small>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Response Modal -->
<div class="modal fade" id="responseModal" tabindex="-1" role="dialog" aria-labelledby="responseModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="responseModalLabel">Balas Laporan #<span id="response-report-id-display"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="report_id" id="response-report-id">
                    
                    <div class="form-group">
                        <label>Subjek Laporan:</label>
                        <p id="response-subject" class="font-weight-bold"></p>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Update Status:</label>
                        <select class="form-control" id="response-status" name="status" required>
                            <option value="pending">Menunggu</option>
                            <option value="in_progress">Sedang Diproses</option>
                            <option value="resolved">Selesai</option>
                            <option value="rejected">Ditolak</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="response">Balasan:</label>
                        <textarea class="form-control" id="response-text" name="response" rows="6" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="update_report" class="btn btn-primary">Kirim Balasan</button>
                    <button type="button" class="btn btn-link text-danger refresh-page-btn">
                        <small><i class="fas fa-sync-alt"></i> Refresh jika terkunci</small>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add token to session Modal -->
<div class="modal fade" id="addTokenModal" tabindex="-1" role="dialog" aria-labelledby="addTokenModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTokenModalLabel">Tambahkan Token ke Session</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <p>Token akan ditambahkan ke session Anda. Anda akan dapat mengakses detail pesanan ini sampai token kedaluwarsa atau Anda menghapusnya dari session.</p>
                </div>
                <div id="token-session-details">
                    <p><strong>Order ID:</strong> <span id="session-order-id"></span></p>
                    <p><strong>Vendor:</strong> <span id="session-vendor"></span></p>
                    <p><strong>Token:</strong> <code id="session-token"></code></p>
                    <p><strong>Berlaku Hingga:</strong> <span id="session-expires"></span></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <a href="#" id="confirm-add-token" class="btn btn-primary">Tambahkan Token</a>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    console.log("Document ready for keluhan.php");
    
    // Initialize DataTables
    $('#dataReports').DataTable({
        "order": [],
        "language": {
            "search": "Cari:",
            "lengthMenu": "Tampilkan _MENU_ data per halaman",
            "zeroRecords": "Tidak ditemukan data yang sesuai",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "infoEmpty": "Tidak ada data yang tersedia",
            "infoFiltered": "(disaring dari _MAX_ data keseluruhan)",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            }
        }
    });
    
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
    
    // PERBAIKAN: View Report Detail Functionality
    $('.view-report-btn').on('click', function(e) {
        e.preventDefault();
        console.log("View report button clicked");
        
        // Hapus semua modal yang mungkin masih tertinggal
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css({
            'overflow': '',
            'padding-right': ''
        });
        
        // Ambil data dari atribut data-*
        const id = $(this).data('id');
        const subject = $(this).data('subject');
        const message = $(this).data('message');
        const vendor = $(this).data('vendor');
        const status = $(this).data('status');
        const order = $(this).data('order');
        const orderId = $(this).data('order-id');
        const token = $(this).data('token');
        const tokenValid = $(this).data('token-valid') === 1;
        const tokenExpires = $(this).data('token-expires');
        const tokenExpiresHours = $(this).data('token-expires-hours');
        const created = $(this).data('created');
        const response = $(this).data('response');
        
        console.log("Loading report ID:", id, "Token valid:", tokenValid);
        
        // Set nilai ke dalam modal
        $('#modal-report-id').text(id);
        $('#reportDetailModalLabel').text('Detail Laporan #' + id);
        $('#modal-report-subject').text(subject);
        $('#modal-report-message').text(message);
        $('#modal-report-vendor').text(vendor);
        $('#modal-report-order').text(order || 'Tidak ada');
        $('#modal-report-created').text(created);
        
        // Set data untuk respond-from-detail button
        $('.respond-from-detail').data('id', id).data('subject', subject).data('status', status).data('response', response || '');
        
        // Set status dengan styling yang tepat
        const statusElement = $('#modal-report-status');
        statusElement.text('');
        statusElement.removeClass().addClass('badge');
        
        if (status === 'pending') {
            statusElement.addClass('badge-warning').text('Menunggu');
        } else if (status === 'in_progress') {
            statusElement.addClass('badge-info').text('Diproses');
        } else if (status === 'resolved') {
            statusElement.addClass('badge-success').text('Selesai');
        } else if (status === 'rejected') {
            statusElement.addClass('badge-danger').text('Ditolak');
        } else {
            statusElement.addClass('badge-secondary').text(status);
        }
        
        // Show or hide token section based on validity
        if (token && orderId) {
            $('#modal-report-token').text(token);
            
            // Setup order link with token
            $('#view-order-btn').attr('href', 'order_detail.php?id=' + orderId + '&token=' + token);
            
            // Setup token session data
            $('#session-order-id').text('Order #' + order);
            $('#session-vendor').text(vendor);
            $('#session-token').text(token);
            $('#session-expires').text(tokenExpires);
            $('#confirm-add-token').attr('href', 'add_token_to_session.php?order_id=' + orderId + '&token=' + token + '&return=keluhan.php');
            
            if (tokenValid) {
                // Display token status
                let statusHtml = '';
                if (tokenExpiresHours <= 1) {
                    statusHtml = '<div class="text-danger"><i class="fas fa-exclamation-circle"></i> Token akan kedaluwarsa dalam kurang dari 1 jam!</div>';
                } else if (tokenExpiresHours <= 12) {
                    statusHtml = '<div class="text-warning"><i class="fas fa-exclamation-triangle"></i> Token akan kedaluwarsa dalam ' + tokenExpiresHours.toFixed(1) + ' jam.</div>';
                } else {
                    statusHtml = '<div class="text-success"><i class="fas fa-check-circle"></i> Token berlaku hingga ' + tokenExpires + ' (' + tokenExpiresHours.toFixed(1) + ' jam lagi).</div>';
                }
                
                $('#token-status').html(statusHtml);
                $('#order-token-section').show();
                $('#order-token-expired').hide();
                
                // Enable token buttons
                $('#copy-token-btn, #add-token-session-btn').prop('disabled', false);
                $('#view-order-btn').removeClass('disabled');
            } else {
                // Show expired token message
                $('#order-token-section').hide();
                $('#order-token-expired').show();
            }
        } else {
            // No token available
            $('#order-token-section').hide();
            $('#order-token-expired').hide();
        }
        
        // Tampilkan response jika ada
        if (response && response.trim() !== '') {
            $('#modal-report-response').text(response);
            $('#response-card').show();
        } else {
            $('#modal-report-response').text('Belum ada balasan.');
            $('#response-card').show();
        }
        
        // Tampilkan modal
        $('#reportDetailModal').modal('show');
    });
    
    // PERBAIKAN: Reply to Report functionality
    $('.reply-btn, .respond-from-detail').on('click', function(e) {
        e.preventDefault();
        console.log("Reply button clicked");
        
        // Close any open modals
        $('#reportDetailModal').modal('hide');
        
        // Clean up modal backdrop
        setTimeout(function() {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css({
                'overflow': '',
                'padding-right': ''
            });
        }, 200);
        
        const id = $(this).data('id');
        const subject = $(this).data('subject');
        const status = $(this).data('status');
        const response = $(this).data('response');
        
        console.log("Replying to report ID:", id, "Status:", status);
        
        $('#response-report-id').val(id);
        $('#response-report-id-display').text(id);
        $('#response-subject').text(subject);
        
        // Set current status in dropdown
        $('#response-status option').each(function() {
            if ($(this).val() === status) {
                $(this).prop('selected', true);
            } else {
                $(this).prop('selected', false);
            }
        });
        
        // Set current response text if exists
        if (response && response.trim() !== '') {
            $('#response-text').val(response);
        } else {
            $('#response-text').val('');
        }
        
        // Show response modal after a short delay to avoid modal conflicts
        setTimeout(function() {
            $('#responseModal').modal('show');
        }, 300);
    });
    
    // Copy token button functionality
    $('#copy-token-btn').on('click', function() {
        const token = $('#modal-report-token').text();
        navigator.clipboard.writeText(token).then(function() {
            const originalText = $('#copy-token-btn').html();
            $('#copy-token-btn').html('<i class="fas fa-check"></i> Token Disalin!');
            
            setTimeout(function() {
                $('#copy-token-btn').html(originalText);
            }, 2000);
        }).catch(function(err) {
            console.error('Failed to copy token: ', err);
            alert('Gagal menyalin token. Silakan salin secara manual.');
        });
    });
    
    // Add token to session button
    $('#add-token-session-btn').on('click', function(e) {
        e.preventDefault();
        $('#reportDetailModal').modal('hide');
        
        // Clean up and show add token modal
        setTimeout(function() {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css({
                'overflow': '',
                'padding-right': ''
            });
            $('#addTokenModal').modal('show');
        }, 300);
    });
    
    // Refresh page button
    $('.refresh-page-btn').on('click', function() {
        location.reload();
    });
    
    // PERBAIKAN: Handle modal cleanup on modal hidden
    $(document).on('hidden.bs.modal', '.modal', function() {
        console.log("Modal hidden - cleaning up");
        
        // Force remove all backdrops and reset body
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css({
            'overflow': '',
            'padding-right': ''
        });
    });
    
    // PERBAIKAN: Manually handle modal dismiss
    $('[data-dismiss="modal"]').on('click', function(e) {
        e.preventDefault();
        console.log("Modal dismiss clicked");
        
        const modal = $(this).closest('.modal');
        modal.modal('hide');
        
        // Force cleanup after a short delay
        setTimeout(function() {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css({
                'overflow': '',
                'padding-right': ''
            });
        }, 200);
    });
});
</script>

<?php include '../includes/admin-footer.php'; ?>