<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi admin
if (!isLoggedIn() || $_SESSION['role'] !== ROLE_ADMIN) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$pageTitle = 'Site Settings';
$currentPage = 'settings';

// Get current settings
$settings = [];
$result = $conn->query("SELECT * FROM settings");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // General Settings
    $site_name = sanitize($_POST['site_name']);
    $site_description = sanitize($_POST['site_description']);
    $contact_email = sanitize($_POST['contact_email']);
    $contact_phone = sanitize($_POST['contact_phone']);
    $address = sanitize($_POST['address']);
    
    // Payment Settings
    $currency = sanitize($_POST['currency']);
    $currency_symbol = sanitize($_POST['currency_symbol']);
    $enable_paypal = isset($_POST['enable_paypal']) ? 1 : 0;
    $enable_stripe = isset($_POST['enable_stripe']) ? 1 : 0;
    $enable_bank_transfer = isset($_POST['enable_bank_transfer']) ? 1 : 0;
    $enable_cod = isset($_POST['enable_cod']) ? 1 : 0;
    
    // Commission Settings
    $default_commission_rate = (float)$_POST['default_commission_rate'];
    
    // Validate input
    if (empty($site_name) || empty($contact_email)) {
        $error = 'Site name and contact email are required.';
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        // Upload site logo if provided
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $site_logo = uploadImage($_FILES['site_logo'], '../assets/img/');
            if ($site_logo) {
                updateSetting('site_logo', $site_logo);
            }
        }
        
        // Upload site favicon if provided
        if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
            $site_favicon = uploadImage($_FILES['site_favicon'], '../assets/img/');
            if ($site_favicon) {
                updateSetting('site_favicon', $site_favicon);
            }
        }
        
        // Update general settings
        updateSetting('site_name', $site_name);
        updateSetting('site_description', $site_description);
        updateSetting('contact_email', $contact_email);
        updateSetting('contact_phone', $contact_phone);
        updateSetting('address', $address);
        
        // Update payment settings
        updateSetting('currency', $currency);
        updateSetting('currency_symbol', $currency_symbol);
        updateSetting('enable_paypal', $enable_paypal);
        updateSetting('enable_stripe', $enable_stripe);
        updateSetting('enable_bank_transfer', $enable_bank_transfer);
        updateSetting('enable_cod', $enable_cod);
        
        // Update commission settings
        updateSetting('default_commission_rate', $default_commission_rate);
        
        $success = 'Settings updated successfully.';
        
        // Refresh settings
        $result = $conn->query("SELECT * FROM settings");
        $settings = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
}

include '../includes/admin-header.php';
?>

<div class="admin-content-header">
    <h1 class="h3 mb-0">Site Settings</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Settings</li>
        </ol>
    </nav>
</div>

<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">General</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab" aria-controls="payment" aria-selected="false">Payment</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="commission-tab" data-bs-toggle="tab" data-bs-target="#commission" type="button" role="tab" aria-controls="commission" aria-selected="false">Commission</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab" aria-controls="email" aria-selected="false">Email</button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="post" action="" enctype="multipart/form-data">
            <div class="tab-content" id="settingsTabsContent">
                <!-- General Settings -->
                <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                    <h5 class="mb-3">General Settings</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label for="site_name" class="form-label">Site Name</label>
                            <input type="text" class="form-control" id="site_name" name="site_name" value="<?= $settings['site_name'] ?? 'ShopVerse' ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="site_description" class="form-label">Site Description</label>
                            <input type="text" class="form-control" id="site_description" name="site_description" value="<?= $settings['site_description'] ?? 'Multi-vendor E-commerce Platform' ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label for="contact_email" class="form-label">Contact Email</label>
                            <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?= $settings['contact_email'] ?? 'contact@shopverse.com' ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="contact_phone" class="form-label">Contact Phone</label>
                            <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?= $settings['contact_phone'] ?? '+62 123 456 7890' ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?= $settings['address'] ?? 'Jl. Sudirman No. 123, Jakarta, Indonesia' ?></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label for="site_logo" class="form-label">Site Logo</label>
                            <input type="file" class="form-control form-file-input" id="site_logo" name="site_logo" accept="image/*" data-preview="#logo-preview">
                            
                            <div class="mt-2">
                                <img src="<?= SITE_URL ?>/assets/img/<?= $settings['site_logo'] ?? 'logo.png' ?>" alt="Site Logo" id="logo-preview" class="img-thumbnail" style="max-width: 150px;">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="site_favicon" class="form-label">Site Favicon</label>
                            <input type="file" class="form-control form-file-input" id="site_favicon" name="site_favicon" accept="image/*" data-preview="#favicon-preview">
                            
                            <div class="mt-2">
                                <img src="<?= SITE_URL ?>/assets/img/<?= $settings['site_favicon'] ?? 'favicon.ico' ?>" alt="Site Favicon" id="favicon-preview" class="img-thumbnail" style="max-width: 32px;">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Settings -->
                <div class="tab-pane fade" id="payment" role="tabpanel" aria-labelledby="payment-tab">
                    <h5 class="mb-3">Payment Settings</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label for="currency" class="form-label">Currency</label>
                            <select class="form-select" id="currency" name="currency">
                                <option value="IDR" <?= ($settings['currency'] ?? 'IDR') === 'IDR' ? 'selected' : '' ?>>Indonesian Rupiah (IDR)</option>
                                <option value="USD" <?= ($settings['currency'] ?? 'IDR') === 'USD' ? 'selected' : '' ?>>US Dollar (USD)</option>
                                <option value="EUR" <?= ($settings['currency'] ?? 'IDR') === 'EUR' ? 'selected' : '' ?>>Euro (EUR)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="currency_symbol" class="form-label">Currency Symbol</label>
                            <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" value="<?= $settings['currency_symbol'] ?? 'Rp' ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Available Payment Methods</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="enable_paypal" name="enable_paypal" value="1" <?= ($settings['enable_paypal'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="enable_paypal">
                                PayPal
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="enable_stripe" name="enable_stripe" value="1" <?= ($settings['enable_stripe'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="enable_stripe">
                                Stripe
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="enable_bank_transfer" name="enable_bank_transfer" value="1" <?= ($settings['enable_bank_transfer'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="enable_bank_transfer">
                                Bank Transfer
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="enable_cod" name="enable_cod" value="1" <?= ($settings['enable_cod'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="enable_cod">
                                Cash on Delivery
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Commission Settings -->
                <div class="tab-pane fade" id="commission" role="tabpanel" aria-labelledby="commission-tab">
                    <h5 class="mb-3">Commission Settings</h5>
                    
                    <div class="mb-3">
                        <label for="default_commission_rate" class="form-label">Default Commission Rate (%)</label>
                        <input type="number" class="form-control" id="default_commission_rate" name="default_commission_rate" value="<?= $settings['default_commission_rate'] ?? '10' ?>" min="0" max="100" step="0.1">
                        <div class="form-text">This is the percentage of each sale that the platform will earn.</div>
                    </div>
                </div>
                
                <!-- Email Settings -->
                <div class="tab-pane fade" id="email" role="tabpanel" aria-labelledby="email-tab">
                    <h5 class="mb-3">Email Settings</h5>
                    
                    <div class="alert alert-info">
                        Email settings will be implemented in a future update.
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save Settings</button>
                <a href="<?= ADMIN_URL ?>/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
// Helper function to update settings
function updateSetting($key, $value) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
    } else {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->bind_param("ss", $key, $value);
    }
    
    return $stmt->execute();
}

// MENGHAPUS FUNGSI uploadImage() YANG DUPLIKAT - GUNAKAN YANG ADA DI functions.php
?>

<?php include '../includes/admin-footer.php'; ?>