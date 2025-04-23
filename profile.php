<?php
require_once 'includes/config.php'; // Ubah path
require_once 'includes/functions.php'; // Ubah path

// Cek apakah pengguna sudah login
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Arahkan vendor dan admin ke panel masing-masing
if ($_SESSION['role'] === 'vendor') {
    header('Location: ' . VENDOR_URL . '/dashboard.php');
    exit;
} elseif ($_SESSION['role'] === 'admin') {
    header('Location: ' . ADMIN_URL . '/dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pageTitle = 'Profil Saya';

// Ambil data pengguna
$user_sql = "SELECT * FROM users WHERE id = $user_id";
$user_result = $conn->query($user_sql);

if (!$user_result || $user_result->num_rows === 0) {
    header('Location: ' . SITE_URL . '/logout.php');
    exit;
}

$user = $user_result->fetch_assoc();

// Ambil data saldo
// Tambahkan tabel balance jika belum ada
$balance_check = $conn->query("SHOW TABLES LIKE 'user_balance'");
if ($balance_check->num_rows == 0) {
    // Buat tabel balance
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

// Cek saldo pengguna
$balance_sql = "SELECT * FROM user_balance WHERE user_id = $user_id";
$balance_result = $conn->query($balance_sql);

if ($balance_result && $balance_result->num_rows > 0) {
    $balance_data = $balance_result->fetch_assoc();
    $balance = $balance_data['balance'];
    $last_topup = $balance_data['last_topup_date'];
} else {
    // Buat data saldo baru
    $conn->query("INSERT INTO user_balance (user_id, balance) VALUES ($user_id, 0)");
    $balance = 0;
    $last_topup = null;
}

// Buat tabel riwayat topup jika belum ada
$topup_history_check = $conn->query("SHOW TABLES LIKE 'topup_history'");
if ($topup_history_check->num_rows == 0) {
    $conn->query("CREATE TABLE topup_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        transaction_id VARCHAR(100) NOT NULL,
        status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}

// Ambil riwayat topup
$topup_history_sql = "SELECT * FROM topup_history WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 10";
$topup_history_result = $conn->query($topup_history_sql);
$topup_history = [];

if ($topup_history_result && $topup_history_result->num_rows > 0) {
    while ($row = $topup_history_result->fetch_assoc()) {
        $topup_history[] = $row;
    }
}

// Proses topup
$topup_success = null;
$topup_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Proses update profil
    if (isset($_POST['update_profile'])) {
        $full_name = $conn->real_escape_string($_POST['full_name'] ?? '');
        $phone = $conn->real_escape_string($_POST['phone'] ?? '');
        $address = $conn->real_escape_string($_POST['address'] ?? '');
        $city = $conn->real_escape_string($_POST['city'] ?? '');
        $state = $conn->real_escape_string($_POST['state'] ?? '');
        $postal_code = $conn->real_escape_string($_POST['postal_code'] ?? '');
        $country = $conn->real_escape_string($_POST['country'] ?? 'Indonesia');
        
        // Update profil
        $update_sql = "UPDATE users SET 
                      full_name = '$full_name',
                      phone = '$phone',
                      address = '$address',
                      city = '$city',
                      state = '$state',
                      postal_code = '$postal_code',
                      country = '$country'
                      WHERE id = $user_id";
                      
        if ($conn->query($update_sql)) {
            // Upload foto profil jika ada
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 2 * 1024 * 1024; // 2MB
                
                $file = $_FILES['profile_image'];
                $file_name = $file['name'];
                $file_size = $file['size'];
                $file_tmp = $file['tmp_name'];
                $file_type = $file['type'];
                
                // Validasi file
                if ($file_size > $max_size) {
                    $topup_error = "Ukuran file terlalu besar. Maksimal 2MB.";
                } elseif (!in_array($file_type, $allowed_types)) {
                    $topup_error = "Jenis file tidak diizinkan. Hanya JPG, PNG, dan GIF yang diperbolehkan.";
                } else {
                    // Buat nama file unik
                    $upload_dir = 'assets/img/users/'; // Ubah path
                    
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                    $new_file_name = 'user_' . $user_id . '_' . uniqid() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        // Hapus foto lama jika bukan default
                        if ($user['profile_image'] !== 'default.jpg' && file_exists($upload_dir . $user['profile_image'])) {
                            @unlink($upload_dir . $user['profile_image']);
                        }
                        
                        // Update nama file foto di database
                        $conn->query("UPDATE users SET profile_image = '$new_file_name' WHERE id = $user_id");
                    }
                }
            }
            
            $topup_success = "Profil berhasil diperbarui.";
            
            // Reload data user
            $user_result = $conn->query($user_sql);
            $user = $user_result->fetch_assoc();
        } else {
            $topup_error = "Gagal memperbarui profil: " . $conn->error;
        }
    }
    
    // Proses topup saldo
    if (isset($_POST['topup_balance'])) {
        $amount = (float)$_POST['amount'];
        $payment_method = $conn->real_escape_string($_POST['payment_method']);
        
        if ($amount < 10000) {
            $topup_error = "Minimum topup adalah Rp 10.000.";
        } elseif ($amount > 10000000) {
            $topup_error = "Maksimum topup adalah Rp 10.000.000.";
        } else {
            // Proses topup (simulasi)
            $transaction_id = 'TRX' . time() . rand(1000, 9999);
            
            // Tambahkan ke riwayat topup
            $topup_sql = "INSERT INTO topup_history (user_id, amount, payment_method, transaction_id, status) 
                         VALUES ($user_id, $amount, '$payment_method', '$transaction_id', 'completed')";
            
            if ($conn->query($topup_sql)) {
                // Update saldo
                $conn->query("UPDATE user_balance SET 
                             balance = balance + $amount,
                             last_topup_date = NOW() 
                             WHERE user_id = $user_id");
                
                $topup_success = "Topup sebesar " . formatPrice($amount) . " berhasil.";
                
                // Reload saldo
                $balance_result = $conn->query($balance_sql);
                $balance_data = $balance_result->fetch_assoc();
                $balance = $balance_data['balance'];
                $last_topup = $balance_data['last_topup_date'];
                
                // Reload riwayat topup
                $topup_history_result = $conn->query($topup_history_sql);
                $topup_history = [];
                
                if ($topup_history_result && $topup_history_result->num_rows > 0) {
                    while ($row = $topup_history_result->fetch_assoc()) {
                        $topup_history[] = $row;
                    }
                }
            } else {
                $topup_error = "Gagal melakukan topup: " . $conn->error;
            }
        }
    }
    
    // Proses ganti password
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verifikasi password saat ini
        if (!password_verify($current_password, $user['password'])) {
            $topup_error = "Password saat ini tidak sesuai.";
        } elseif ($new_password !== $confirm_password) {
            $topup_error = "Password baru dan konfirmasi password tidak sama.";
        } elseif (strlen($new_password) < 6) {
            $topup_error = "Password baru minimal 6 karakter.";
        } else {
            // Hash password baru
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $update_password_sql = "UPDATE users SET password = '$hashed_password' WHERE id = $user_id";
            
            if ($conn->query($update_password_sql)) {
                $topup_success = "Password berhasil diubah.";
            } else {
                $topup_error = "Gagal mengubah password: " . $conn->error;
            }
        }
    }
}

// Current time untuk display
$current_datetime = date('Y-m-d H:i:s');

include 'includes/header.php'; // Ubah path
?>

<div class="container py-5">
    <!-- Banner Info User -->
    <div class="card mb-4 border-left-primary">
        <div class="card-body py-3">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Current User's Login: <?= htmlspecialchars($_SESSION['username'] ?? 'MochSetiawan') ?>
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
        <h1 class="h3 mb-0">Profil Saya</h1>
        <a href="<?= SITE_URL ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Kembali ke Beranda
        </a>
    </div>
    
    <?php if ($topup_success): ?>
        <div class="alert alert-success"><?= $topup_success ?></div>
    <?php endif; ?>
    
    <?php if ($topup_error): ?>
        <div class="alert alert-danger"><?= $topup_error ?></div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Sidebar / Profile Info -->
        <div class="col-lg-4 mb-4">
            <!-- Profil Pengguna -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Informasi Pengguna</h6>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($user['profile_image']) && $user['profile_image'] !== 'default.jpg'): ?>
                        <img src="<?= SITE_URL ?>/assets/img/users/<?= $user['profile_image'] ?>" 
                             alt="Profile Picture" class="rounded-circle img-thumbnail mb-3" 
                             style="width: 150px; height: 150px; object-fit: cover;">
                    <?php else: ?>
                        <div class="mx-auto rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mb-3" 
                            style="width: 150px; height: 150px; font-size: 4rem;">
                            <?= strtoupper(substr($user['username'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    
                    <h4 class="mb-0"><?= htmlspecialchars($user['username']) ?></h4>
                    <p class="text-muted"><?= htmlspecialchars($user['email']) ?></p>
                    
                    <hr>
                    
                    <div class="text-left">
                        <p><strong>Nama Lengkap:</strong> <?= htmlspecialchars($user['full_name'] ?: '-') ?></p>
                        <p><strong>Telepon:</strong> <?= htmlspecialchars($user['phone'] ?: '-') ?></p>
                        <p><strong>Status:</strong> 
                            <?php if ($user['status'] === 'active'): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php elseif ($user['status'] === 'inactive'): ?>
                                <span class="badge bg-secondary">Nonaktif</span>
                            <?php elseif ($user['status'] === 'pending'): ?>
                                <span class="badge bg-warning">Pending</span>
                            <?php endif; ?>
                        </p>
                        <p><strong>Tanggal Bergabung:</strong> <?= date('d M Y', strtotime($user['created_at'])) ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Saldo Pengguna -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Saldo Akun</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <h2 class="text-primary font-weight-bold"><?= formatPrice($balance) ?></h2>
                        <?php if ($last_topup): ?>
                            <small class="text-muted">Terakhir diisi: <?= date('d M Y H:i', strtotime($last_topup)) ?></small>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" class="btn btn-success btn-block" data-bs-toggle="modal" data-bs-target="#topupModal">
                        <i class="fas fa-plus-circle me-1"></i> Top Up Saldo
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Tab Navigation -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="edit-profile-tab" data-bs-toggle="tab" data-bs-target="#edit-profile" type="button" role="tab" aria-controls="edit-profile" aria-selected="true">Edit Profil</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="topup-history-tab" data-bs-toggle="tab" data-bs-target="#topup-history" type="button" role="tab" aria-controls="topup-history" aria-selected="false">Riwayat Top Up</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="change-password-tab" data-bs-toggle="tab" data-bs-target="#change-password" type="button" role="tab" aria-controls="change-password" aria-selected="false">Ganti Password</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="profileTabsContent">
                        <!-- Tab Edit Profil -->
                        <div class="tab-pane fade show active" id="edit-profile" role="tabpanel" aria-labelledby="edit-profile-tab">
                            <form method="post" action="" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="profile_image" class="form-label">Foto Profil</label>
                                    <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                                    <small class="text-muted">Format: JPG, PNG, GIF. Maks: 2MB.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Nama Lengkap</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Nomor Telepon</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Alamat</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="city" class="form-label">Kota</label>
                                        <input type="text" class="form-control" id="city" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="state" class="form-label">Provinsi</label>
                                        <input type="text" class="form-control" id="state" name="state" value="<?= htmlspecialchars($user['state'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="postal_code" class="form-label">Kode Pos</label>
                                        <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="country" class="form-label">Negara</label>
                                        <input type="text" class="form-control" id="country" name="country" value="<?= htmlspecialchars($user['country'] ?? 'Indonesia') ?>">
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Simpan Perubahan
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Tab Riwayat Top Up -->
                        <div class="tab-pane fade" id="topup-history" role="tabpanel" aria-labelledby="topup-history-tab">
                            <?php if (empty($topup_history)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-history fa-3x text-gray-300 mb-3"></i>
                                    <p class="mb-0">Belum ada riwayat top up.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Jumlah</th>
                                                <th>Metode Pembayaran</th>
                                                <th>ID Transaksi</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topup_history as $history): ?>
                                                <tr>
                                                    <td><?= date('d M Y H:i', strtotime($history['created_at'])) ?></td>
                                                    <td><?= formatPrice($history['amount']) ?></td>
                                                    <td>
                                                        <?php 
                                                            switch ($history['payment_method']) {
                                                                case 'gopay':
                                                                    echo '<span class="badge bg-primary">GoPay</span>';
                                                                    break;
                                                                case 'ovo':
                                                                    echo '<span class="badge bg-purple">OVO</span>';
                                                                    break;
                                                                case 'dana':
                                                                    echo '<span class="badge bg-info">DANA</span>';
                                                                    break;
                                                                case 'bank_transfer':
                                                                    echo '<span class="badge bg-secondary">Transfer Bank</span>';
                                                                    break;
                                                                default:
                                                                    echo htmlspecialchars($history['payment_method']);
                                                            }
                                                        ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($history['transaction_id']) ?></td>
                                                    <td>
                                                        <?php 
                                                            switch ($history['status']) {
                                                                case 'completed':
                                                                    echo '<span class="badge bg-success">Berhasil</span>';
                                                                    break;
                                                                case 'pending':
                                                                    echo '<span class="badge bg-warning">Pending</span>';
                                                                    break;
                                                                case 'failed':
                                                                    echo '<span class="badge bg-danger">Gagal</span>';
                                                                    break;
                                                                default:
                                                                    echo htmlspecialchars($history['status']);
                                                            }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Tab Ganti Password -->
                        <div class="tab-pane fade" id="change-password" role="tabpanel" aria-labelledby="change-password-tab">
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Password Saat Ini</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Password Baru</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <small class="text-muted">Minimal 6 karakter.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        <i class="fas fa-key me-1"></i> Ganti Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Top Up -->
<div class="modal fade" id="topupModal" tabindex="-1" aria-labelledby="topupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="topupModalLabel">Top Up Saldo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="amount" class="form-label">Jumlah Top Up (Rp)</label>
                        <input type="number" class="form-control" id="amount" name="amount" min="10000" step="10000" value="50000" required>
                        <small class="text-muted">Minimum Rp 10.000, maksimum Rp 10.000.000</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Pilih Metode Pembayaran</label>
                        
                        <div class="row mb-2">
                            <div class="col-6">
                                <div class="form-check payment-method-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="method_gopay" value="gopay" checked>
                                    <label class="form-check-label payment-method-label d-flex align-items-center" for="method_gopay">
                                        <img src="<?= SITE_URL ?>/assets/img/payment/gopay.png" alt="GoPay" width="60">
                                        <span class="ms-2">GoPay</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check payment-method-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="method_ovo" value="ovo">
                                    <label class="form-check-label payment-method-label d-flex align-items-center" for="method_ovo">
                                        <img src="<?= SITE_URL ?>/assets/img/payment/ovo.png" alt="OVO" width="60">
                                        <span class="ms-2">OVO</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-2">
                            <div class="col-6">
                                <div class="form-check payment-method-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="method_dana" value="dana">
                                    <label class="form-check-label payment-method-label d-flex align-items-center" for="method_dana">
                                        <img src="<?= SITE_URL ?>/assets/img/payment/dana.png" alt="DANA" width="60">
                                        <span class="ms-2">DANA</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check payment-method-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="method_bank_transfer" value="bank_transfer">
                                    <label class="form-check-label payment-method-label d-flex align-items-center" for="method_bank_transfer">
                                        <img src="<?= SITE_URL ?>/assets/img/payment/bank.png" alt="Bank Transfer" width="60">
                                        <span class="ms-2">Transfer Bank</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i> Ini adalah simulasi top up. Pada implementasi sebenarnya, Anda akan diarahkan ke halaman pembayaran.
                        </small>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="topup_balance" class="btn btn-success">Bayar</button>
                    </div>
                </form>
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

// Preview gambar profil saat dipilih
document.addEventListener('DOMContentLoaded', function() {
    const profileImageInput = document.getElementById('profile_image');
    
    if (profileImageInput) {
        profileImageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const profileImages = document.querySelectorAll('.rounded-circle.img-thumbnail, .rounded-circle.bg-primary');
                    
                    profileImages.forEach(function(img) {
                        if (img.tagName === 'IMG') {
                            img.src = e.target.result;
                        } else {
                            // Ini adalah div placeholder
                            const parentNode = img.parentNode;
                            
                            // Buat elemen gambar
                            const newImg = document.createElement('img');
                            newImg.src = e.target.result;
                            newImg.alt = 'Profile Preview';
                            newImg.className = 'rounded-circle img-thumbnail mb-3';
                            newImg.style.width = '150px';
                            newImg.style.height = '150px';
                            newImg.style.objectFit = 'cover';
                            
                            // Ganti elemen div dengan elemen img
                            parentNode.replaceChild(newImg, img);
                        }
                    });
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
});
</script>

<style>
.payment-method-check {
    margin-bottom: 10px;
}

.payment-method-label {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.payment-method-check input:checked + .payment-method-label {
    border-color: #2e59d9;
    background-color: #f8f9fc;
    box-shadow: 0 0 0 1px #2e59d9;
}
</style>

<?php include 'includes/footer.php'; // Ubah path ?>