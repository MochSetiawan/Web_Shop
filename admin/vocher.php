<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$pageTitle = 'Manajemen Voucher';
$currentPage = 'voucher';
$current_user = $_SESSION['username'] ?? 'MochSetiawan';
$current_datetime = date('Y-m-d H:i:s');

// Cek atau buat tabel vouchers jika belum ada
$check_table = $conn->query("SHOW TABLES LIKE 'vouchers'");
if ($check_table->num_rows == 0) {
    $create_table_query = "CREATE TABLE vouchers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        discount_type ENUM('percentage', 'fixed') NOT NULL,
        discount_value DECIMAL(10,2) NOT NULL,
        min_purchase DECIMAL(10,2) DEFAULT 0,
        max_usage INT DEFAULT NULL,
        usage_count INT DEFAULT 0,
        start_date DATETIME NOT NULL,
        end_date DATETIME NOT NULL,
        is_active BOOLEAN DEFAULT 1,
        created_by INT NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )";
    
    if ($conn->query($create_table_query)) {
        $_SESSION['success_message'] = "Tabel vouchers berhasil dibuat.";
    } else {
        $_SESSION['error_message'] = "Gagal membuat tabel vouchers: " . $conn->error;
    }
}

// Hapus voucher
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $voucher_id = (int)$_GET['delete'];
    
    // Cek apakah voucher sudah digunakan
    $check_query = "SELECT usage_count FROM vouchers WHERE id = ?";
    $stmt = $conn->prepare($check_query);
    
    if ($stmt) {
        $stmt->bind_param('i', $voucher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $voucher = $result->fetch_assoc();
            
            if ($voucher['usage_count'] > 0) {
                $_SESSION['error_message'] = "Tidak dapat menghapus voucher yang sudah digunakan.";
            } else {
                // Voucher belum digunakan, bisa dihapus
                $delete_query = "DELETE FROM vouchers WHERE id = ?";
                $stmt = $conn->prepare($delete_query);
                
                if ($stmt) {
                    $stmt->bind_param('i', $voucher_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = "Voucher berhasil dihapus.";
                    } else {
                        $_SESSION['error_message'] = "Gagal menghapus voucher: " . $conn->error;
                    }
                } else {
                    $_SESSION['error_message'] = "Error pada query: " . $conn->error;
                }
            }
        } else {
            $_SESSION['error_message'] = "Voucher tidak ditemukan.";
        }
    } else {
        $_SESSION['error_message'] = "Error pada query: " . $conn->error;
    }
    
    header('Location: ' . SITE_URL . '/admin/vocher.php');
    exit;
}

// Toggle status voucher
if (isset($_GET['toggle']) && !empty($_GET['toggle'])) {
    $voucher_id = (int)$_GET['toggle'];
    
    $toggle_query = "UPDATE vouchers SET is_active = NOT is_active WHERE id = ?";
    $stmt = $conn->prepare($toggle_query);
    
    if ($stmt) {
        $stmt->bind_param('i', $voucher_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Status voucher berhasil diperbarui.";
        } else {
            $_SESSION['error_message'] = "Gagal memperbarui status voucher: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = "Error pada query: " . $conn->error;
    }
    
    header('Location: ' . SITE_URL . '/admin/vocher.php');
    exit;
}

// Tambah atau Edit voucher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_voucher'])) {
    $code = strtoupper($conn->real_escape_string($_POST['code']));
    $discount_type = $conn->real_escape_string($_POST['discount_type']);
    $discount_value = (float)$_POST['discount_value'];
    $min_purchase = (float)$_POST['min_purchase'];
    $max_usage = !empty($_POST['max_usage']) ? (int)$_POST['max_usage'] : NULL;
    $start_date = $conn->real_escape_string($_POST['start_date']);
    $end_date = $conn->real_escape_string($_POST['end_date']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $description = $conn->real_escape_string($_POST['description']);
    $created_by = $_SESSION['user_id'];
    
    // Validasi nilai diskon
    if ($discount_type === 'percentage' && ($discount_value <= 0 || $discount_value > 100)) {
        $_SESSION['error_message'] = "Nilai diskon persentase harus antara 1-100%.";
        header('Location: ' . SITE_URL . '/admin/vocher.php');
        exit;
    }
    
    if ($discount_type === 'fixed' && $discount_value <= 0) {
        $_SESSION['error_message'] = "Nilai diskon tetap harus lebih dari 0.";
        header('Location: ' . SITE_URL . '/admin/vocher.php');
        exit;
    }
    
    // Validasi tanggal
    if (strtotime($start_date) >= strtotime($end_date)) {
        $_SESSION['error_message'] = "Tanggal berakhir harus setelah tanggal mulai.";
        header('Location: ' . SITE_URL . '/admin/vocher.php');
        exit;
    }
    
    if (isset($_POST['voucher_id']) && !empty($_POST['voucher_id'])) {
        // Update voucher yang ada
        $voucher_id = (int)$_POST['voucher_id'];
        
        $update_query = "UPDATE vouchers SET 
                        code = ?, 
                        discount_type = ?, 
                        discount_value = ?, 
                        min_purchase = ?, 
                        max_usage = ?, 
                        start_date = ?, 
                        end_date = ?, 
                        is_active = ?, 
                        description = ?, 
                        updated_at = NOW() 
                        WHERE id = ?";
        
        $stmt = $conn->prepare($update_query);
        
        if ($stmt) {
            $stmt->bind_param('ssddissiis', $code, $discount_type, $discount_value, $min_purchase, $max_usage, $start_date, $end_date, $is_active, $description, $voucher_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Voucher berhasil diperbarui.";
            } else {
                $_SESSION['error_message'] = "Gagal memperbarui voucher: " . $conn->error;
            }
        } else {
            $_SESSION['error_message'] = "Error pada query: " . $conn->error;
        }
    } else {
        // Tambah voucher baru
        
        // Cek apakah kode voucher sudah ada
        $check_code_query = "SELECT id FROM vouchers WHERE code = ?";
        $stmt = $conn->prepare($check_code_query);
        
        if (!$stmt) {
            $_SESSION['error_message'] = "Error pada query: " . $conn->error;
            header('Location: ' . SITE_URL . '/admin/vocher.php');
            exit;
        }
        
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['error_message'] = "Kode voucher $code sudah digunakan. Silakan gunakan kode lain.";
            header('Location: ' . SITE_URL . '/admin/vocher.php');
            exit;
        }
        
        // Tambah voucher baru
        $insert_query = "INSERT INTO vouchers (code, discount_type, discount_value, min_purchase, max_usage, start_date, end_date, is_active, created_by, description)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($insert_query);
        
        if ($stmt) {
            $stmt->bind_param('ssddissiis', $code, $discount_type, $discount_value, $min_purchase, $max_usage, $start_date, $end_date, $is_active, $created_by, $description);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Voucher baru berhasil ditambahkan.";
            } else {
                $_SESSION['error_message'] = "Gagal menambahkan voucher: " . $conn->error;
            }
        } else {
            $_SESSION['error_message'] = "Error pada query: " . $conn->error;
        }
    }
    
    header('Location: ' . SITE_URL . '/admin/vocher.php');
    exit;
}

// Filter status
$status_filter = '';
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filter = $_GET['status'];
    
    switch ($filter) {
        case 'active':
            $status_filter = " WHERE is_active = 1 AND end_date >= NOW() ";
            break;
        case 'inactive':
            $status_filter = " WHERE is_active = 0 ";
            break;
        case 'expired':
            $status_filter = " WHERE end_date < NOW() ";
            break;
        case 'upcoming':
            $status_filter = " WHERE start_date > NOW() ";
            break;
    }
}

// Ambil data voucher untuk edit jika ada parameter id
$voucher_to_edit = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $voucher_id = (int)$_GET['edit'];
    
    $edit_query = "SELECT * FROM vouchers WHERE id = ?";
    $stmt = $conn->prepare($edit_query);
    
    if ($stmt) {
        $stmt->bind_param('i', $voucher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $voucher_to_edit = $result->fetch_assoc();
        }
    }
}

// Ambil semua voucher
$vouchers_query = "SELECT v.*, u.username as created_by_username,
                  CASE 
                      WHEN v.end_date < NOW() THEN 'Kedaluwarsa'
                      WHEN v.start_date > NOW() THEN 'Akan Datang'
                      WHEN v.is_active = 0 THEN 'Tidak Aktif'
                      ELSE 'Aktif'
                  END as status
                 FROM vouchers v
                 JOIN users u ON v.created_by = u.id
                 $status_filter
                 ORDER BY v.created_at DESC";

$vouchers_result = $conn->query($vouchers_query);
$vouchers = [];

if ($vouchers_result && $vouchers_result->num_rows > 0) {
    while ($row = $vouchers_result->fetch_assoc()) {
        $vouchers[] = $row;
    }
}

// Ambil statistik
$stats_query = "SELECT 
                COUNT(*) as total_vouchers,
                SUM(CASE WHEN is_active = 1 AND end_date >= NOW() AND start_date <= NOW() THEN 1 ELSE 0 END) as active_vouchers,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_vouchers,
                SUM(CASE WHEN end_date < NOW() THEN 1 ELSE 0 END) as expired_vouchers,
                SUM(CASE WHEN start_date > NOW() THEN 1 ELSE 0 END) as upcoming_vouchers,
                SUM(usage_count) as total_usage
                FROM vouchers";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

include '../includes/admin-header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manajemen Voucher</h1>
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addVoucherModal">
            <i class="fas fa-plus-circle fa-sm text-white-50 mr-1"></i> Tambah Voucher
        </button>
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
    
    <!-- Voucher Statistics Cards -->
    <div class="row">
        <div class="col-xl-2 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Voucher</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['total_vouchers'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-ticket-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Aktif</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['active_vouchers'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Akan Datang</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['upcoming_vouchers'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-plus fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Kedaluwarsa</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['expired_vouchers'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-times fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-6 mb-4">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Tidak Aktif</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['inactive_vouchers'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-ban fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Digunakan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['total_usage'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
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
                <a href="<?= SITE_URL ?>/admin/vocher.php" class="btn <?= !isset($_GET['status']) ? 'btn-primary' : 'btn-outline-secondary' ?> mr-2 mb-2">
                    Semua <span class="badge badge-light"><?= $stats['total_vouchers'] ?></span>
                </a>
                <a href="<?= SITE_URL ?>/admin/vocher.php?status=active" class="btn <?= isset($_GET['status']) && $_GET['status'] === 'active' ? 'btn-primary' : 'btn-outline-success' ?> mr-2 mb-2">
                    Aktif <span class="badge badge-light"><?= $stats['active_vouchers'] ?></span>
                </a>
                <a href="<?= SITE_URL ?>/admin/vocher.php?status=upcoming" class="btn <?= isset($_GET['status']) && $_GET['status'] === 'upcoming' ? 'btn-primary' : 'btn-outline-warning' ?> mr-2 mb-2">
                    Akan Datang <span class="badge badge-light"><?= $stats['upcoming_vouchers'] ?></span>
                </a>
                <a href="<?= SITE_URL ?>/admin/vocher.php?status=expired" class="btn <?= isset($_GET['status']) && $_GET['status'] === 'expired' ? 'btn-primary' : 'btn-outline-danger' ?> mr-2 mb-2">
                    Kedaluwarsa <span class="badge badge-light"><?= $stats['expired_vouchers'] ?></span>
                </a>
                <a href="<?= SITE_URL ?>/admin/vocher.php?status=inactive" class="btn <?= isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'btn-primary' : 'btn-outline-secondary' ?> mr-2 mb-2">
                    Tidak Aktif <span class="badge badge-light"><?= $stats['inactive_vouchers'] ?></span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Voucher Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Voucher</h6>
        </div>
        <div class="card-body">
            <?php if (empty($vouchers)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-ticket-alt fa-3x text-gray-300 mb-3"></i>
                    <p class="mb-0">Belum ada voucher yang dibuat.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="voucherTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Jenis</th>
                                <th>Nilai</th>
                                <th>Min. Pembelian</th>
                                <th>Periode</th>
                                <th>Penggunaan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vouchers as $voucher): ?>
                                <tr>
                                    <td>
                                        <span class="text-monospace font-weight-bold"><?= htmlspecialchars($voucher['code']) ?></span>
                                        <?php if (!empty($voucher['description'])): ?>
                                            <i class="fas fa-info-circle text-info ml-1" data-toggle="tooltip" title="<?= htmlspecialchars($voucher['description']) ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($voucher['discount_type'] === 'percentage'): ?>
                                            <span class="badge badge-info">Persentase</span>
                                        <?php else: ?>
                                            <span class="badge badge-primary">Jumlah Tetap</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($voucher['discount_type'] === 'percentage'): ?>
                                            <span class="text-info"><?= $voucher['discount_value'] ?>%</span>
                                        <?php else: ?>
                                            <span class="text-primary"><?= formatPrice($voucher['discount_value']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= formatPrice($voucher['min_purchase']) ?></td>
                                    <td>
                                        <small>
                                            <?= date('d M Y', strtotime($voucher['start_date'])) ?> -<br>
                                            <?= date('d M Y', strtotime($voucher['end_date'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?= $voucher['usage_count'] ?> kali
                                        <?php if ($voucher['max_usage']): ?>
                                            <br><small class="text-muted">Maks: <?= $voucher['max_usage'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = 'secondary';
                                        
                                        switch ($voucher['status']) {
                                            case 'Aktif':
                                                $status_class = 'success';
                                                break;
                                            case 'Kedaluwarsa':
                                                $status_class = 'danger';
                                                break;
                                            case 'Akan Datang':
                                                $status_class = 'warning';
                                                break;
                                            case 'Tidak Aktif':
                                                $status_class = 'secondary';
                                                break;
                                        }
                                        ?>
                                        <span class="badge badge-<?= $status_class ?>"><?= $voucher['status'] ?></span>
                                    </td>
                                    <td>
                                        <!-- Button to toggle status (active/inactive) -->
                                        <?php if ($voucher['status'] !== 'Kedaluwarsa'): ?>
                                            <a href="vocher.php?toggle=<?= $voucher['id'] ?>" 
                                               class="btn btn-sm <?= $voucher['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                                               data-toggle="tooltip" 
                                               title="<?= $voucher['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                                <i class="fas <?= $voucher['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Button to edit -->
                                        <a href="vocher.php?edit=<?= $voucher['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <!-- Button to delete (only if never used) -->
                                        <?php if ($voucher['usage_count'] == 0): ?>
                                            <a href="vocher.php?delete=<?= $voucher['id'] ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Yakin ingin menghapus voucher ini?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
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

<!-- Add/Edit Voucher Modal -->
<div class="modal fade" id="addVoucherModal" tabindex="-1" role="dialog" aria-labelledby="addVoucherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addVoucherModalLabel">
                    <?= $voucher_to_edit ? 'Edit Voucher' : 'Tambah Voucher Baru' ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <!-- Hidden input for voucher ID when editing -->
                    <?php if ($voucher_to_edit): ?>
                        <input type="hidden" name="voucher_id" value="<?= $voucher_to_edit['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="code">Kode Voucher*</label>
                                <input type="text" class="form-control" id="code" name="code" 
                                       value="<?= $voucher_to_edit ? htmlspecialchars($voucher_to_edit['code']) : '' ?>" 
                                       placeholder="mis. DISKON50" required
                                       <?= $voucher_to_edit && $voucher_to_edit['usage_count'] > 0 ? 'readonly' : '' ?>>
                                <small class="form-text text-muted">
                                    Kode yang akan diinput pelanggan saat checkout.
                                    <?php if ($voucher_to_edit && $voucher_to_edit['usage_count'] > 0): ?>
                                        <span class="text-danger">Kode tidak dapat diubah karena sudah digunakan.</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label for="discount_type">Jenis Diskon*</label>
                                <select class="form-control" id="discount_type" name="discount_type" required>
                                    <option value="percentage" <?= $voucher_to_edit && $voucher_to_edit['discount_type'] === 'percentage' ? 'selected' : '' ?>>
                                        Persentase (%)
                                    </option>
                                    <option value="fixed" <?= $voucher_to_edit && $voucher_to_edit['discount_type'] === 'fixed' ? 'selected' : '' ?>>
                                        Jumlah Tetap (Rp)
                                    </option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="discount_value">Nilai Diskon*</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control" id="discount_value" name="discount_value" 
                                           value="<?= $voucher_to_edit ? $voucher_to_edit['discount_value'] : '' ?>" 
                                           required>
                                    <div class="input-group-append">
                                        <span class="input-group-text" id="discount-symbol">
                                            <?= $voucher_to_edit && $voucher_to_edit['discount_type'] === 'percentage' ? '%' : 'Rp' ?>
                                        </span>
                                    </div>
                                </div>
                                <small class="form-text text-muted" id="discount-hint">
                                    <?= $voucher_to_edit && $voucher_to_edit['discount_type'] === 'percentage' ? 'Masukkan nilai 1-100 untuk persentase diskon.' : 'Masukkan nilai dalam Rupiah tanpa tanda titik atau koma.' ?>
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label for="min_purchase">Minimum Pembelian (Rp)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="min_purchase" name="min_purchase" 
                                       value="<?= $voucher_to_edit ? $voucher_to_edit['min_purchase'] : '0' ?>">
                                <small class="form-text text-muted">Nilai minimum pembelian untuk dapat menggunakan voucher. Isi 0 jika tidak ada minimum.</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="max_usage">Maksimum Penggunaan</label>
                                <input type="number" min="1" class="form-control" id="max_usage" name="max_usage" 
                                       value="<?= $voucher_to_edit && $voucher_to_edit['max_usage'] ? $voucher_to_edit['max_usage'] : '' ?>" 
                                       placeholder="Tidak terbatas">
                                <small class="form-text text-muted">Kosongkan jika tidak ada batasan jumlah penggunaan.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="start_date">Tanggal Mulai*</label>
                                <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                       value="<?= $voucher_to_edit ? date('Y-m-d\TH:i', strtotime($voucher_to_edit['start_date'])) : date('Y-m-d\TH:i') ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date">Tanggal Berakhir*</label>
                                <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                       value="<?= $voucher_to_edit ? date('Y-m-d\TH:i', strtotime($voucher_to_edit['end_date'])) : date('Y-m-d\TH:i', strtotime('+30 days')) ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" 
                                           <?= !$voucher_to_edit || $voucher_to_edit['is_active'] ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="is_active">Aktif</label>
                                </div>
                                <small class="form-text text-muted">Voucher hanya dapat digunakan jika status aktif.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Deskripsi</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= $voucher_to_edit ? htmlspecialchars($voucher_to_edit['description']) : '' ?></textarea>
                                <small class="form-text text-muted">Detail tambahan tentang voucher (opsional).</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="save_voucher" class="btn btn-primary">
                        <?= $voucher_to_edit ? 'Perbarui Voucher' : 'Simpan Voucher' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#voucherTable').DataTable({
        "order": [[ 0, "asc" ]],
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
    
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Auto-open modal if edit parameter is present
    <?php if ($voucher_to_edit): ?>
        $('#addVoucherModal').modal('show');
    <?php endif; ?>
    
    // Update discount symbol and hint based on selected discount type
    $('#discount_type').on('change', function() {
        const discountType = $(this).val();
        
        if (discountType === 'percentage') {
            $('#discount-symbol').text('%');
            $('#discount-hint').text('Masukkan nilai 1-100 untuk persentase diskon.');
        } else {
            $('#discount-symbol').text('Rp');
            $('#discount-hint').text('Masukkan nilai dalam Rupiah tanpa tanda titik atau koma.');
        }
    });
    
    // Generate random voucher code
    $('#generateCode').on('click', function() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let code = '';
        
        for (let i = 0; i < 8; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        
        $('#code').val(code);
    });
    
    // Validate discount value based on type
    $('form').on('submit', function(e) {
        const discountType = $('#discount_type').val();
        const discountValue = parseFloat($('#discount_value').val());
        
        if (discountType === 'percentage' && (discountValue <= 0 || discountValue > 100)) {
            alert('Nilai diskon persentase harus antara 1-100%.');
            e.preventDefault();
            return false;
        }
        
        if (discountType === 'fixed' && discountValue <= 0) {
            alert('Nilai diskon tetap harus lebih dari 0.');
            e.preventDefault();
            return false;
        }
        
        const startDate = new Date($('#start_date').val());
        const endDate = new Date($('#end_date').val());
        
        if (startDate >= endDate) {
            alert('Tanggal berakhir harus setelah tanggal mulai.');
            e.preventDefault();
            return false;
        }
    });
});
</script>

<?php include '../includes/admin-footer.php'; ?>