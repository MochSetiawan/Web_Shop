<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Become a Seller';

// Check if user is logged in
$registered = false;
$user_id = null;

if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    
    // Redirect if already a vendor or admin
    if ($user_role === ROLE_VENDOR) {
        header('Location: ' . VENDOR_URL . '/dashboard.php');
        exit;
    } elseif ($user_role === ROLE_ADMIN) {
        header('Location: ' . ADMIN_URL . '/dashboard.php');
        exit;
    }
    
    // Check if user already applied to be a vendor
    $stmt = $conn->prepare("SELECT * FROM vendors WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $vendor = $result->fetch_assoc();
        $registered = true;
    }
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is logged in, if not register new user
    if (!isLoggedIn()) {
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $full_name = sanitize($_POST['full_name']);
        
        // Validate user input
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($full_name)) {
            $error = 'All fields are required';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } else {
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $existing_user = $result->fetch_assoc();
                if ($existing_user['username'] === $username) {
                    $error = 'Username already taken';
                } else {
                    $error = 'Email already registered';
                }
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user into database
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, 'vendor', 'active')");
                $stmt->bind_param("ssss", $username, $email, $hashed_password, $full_name);
                
                if ($stmt->execute()) {
                    $user_id = $conn->insert_id;
                    
                    // Log in the new user
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['role'] = 'vendor';
                } else {
                    $error = 'Registration failed. Please try again later.';
                }
            }
        }
    }
    
    // If user is logged in or registration successful, process vendor registration
    if (empty($error) && $user_id) {
        $shop_name = sanitize($_POST['shop_name']);
        $description = sanitize($_POST['description']);
        
        // Validate vendor input
        if (empty($shop_name) || empty($description)) {
            $error = 'Shop name and description are required';
        } else {
            // Check if shop name already exists
            $stmt = $conn->prepare("SELECT * FROM vendors WHERE shop_name = ?");
            $stmt->bind_param("s", $shop_name);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Shop name already taken';
            } else {
                // Upload logo if provided
                $logo = 'default-shop.jpg';
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $uploaded_logo = uploadImage($_FILES['logo'], UPLOAD_DIR . 'vendors/');
                    if ($uploaded_logo) {
                        $logo = $uploaded_logo;
                    }
                }
                
                // Upload banner if provided
                $banner = 'default-banner.jpg';
                if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
                    $uploaded_banner = uploadImage($_FILES['banner'], UPLOAD_DIR . 'vendors/');
                    if ($uploaded_banner) {
                        $banner = $uploaded_banner;
                    }
                }
                
                // Insert vendor into database
                $stmt = $conn->prepare("INSERT INTO vendors (user_id, shop_name, description, logo, banner, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $stmt->bind_param("issss", $user_id, $shop_name, $description, $logo, $banner);
                
                if ($stmt->execute()) {
                    $success = 'Your vendor application has been submitted! Our team will review it and get back to you soon.';
                    $registered = true;
                } else {
                    $error = 'Vendor registration failed. Please try again later.';
                }
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4 p-md-5">
                    <h2 class="text-center mb-4">Become a Seller on ShopVerse</h2>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    
                    <?php if ($registered): ?>
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="fas fa-check-circle text-success fa-5x"></i>
                            </div>
                            <h3>Thank You for Registering!</h3>
                            <p class="text-muted mb-4">Your application to become a seller is being reviewed by our team. We'll notify you once it's approved.</p>
                            <a href="<?= SITE_URL ?>" class="btn btn-primary">Return to Home Page</a>
                        </div>
                    <?php else: ?>
                        <div class="vendor-benefits mb-5">
                            <h4 class="mb-3">Why Sell on ShopVerse?</h4>
                            <div class="row">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-globe fa-3x text-primary mb-3"></i>
                                            <h5>Reach Millions</h5>
                                            <p class="mb-0">Connect with customers nationwide through our platform</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-tools fa-3x text-primary mb-3"></i>
                                            <h5>Easy Tools</h5>
                                            <p class="mb-0">Simple dashboard to manage products, orders, and payments</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-hand-holding-usd fa-3x text-primary mb-3"></i>
                                            <h5>Grow Revenue</h5>
                                            <p class="mb-0">Increase your sales with our marketing tools and support</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <form method="post" action="" enctype="multipart/form-data">
                            <?php if (!isLoggedIn()): ?>
                                <h4 class="mb-3">Account Information</h4>
                                <div class="row mb-4">
                                    <div class="col-md-6 mb-3">
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?= isset($full_name) ? htmlspecialchars($full_name) : '' ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password" required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                                <i class="far fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                                <i class="far fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <h4 class="mb-3">Shop Information</h4>
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label for="shop_name" class="form-label">Shop Name</label>
                                    <input type="text" class="form-control" id="shop_name" name="shop_name" value="<?= isset($shop_name) ? htmlspecialchars($shop_name) : '' ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="logo" class="form-label">Shop Logo (Optional)</label>
                                    <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                    <div class="form-text">Recommended size: 200x200 pixels</div>
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="banner" class="form-label">Shop Banner (Optional)</label>
                                    <input type="file" class="form-control" id="banner" name="banner" accept="image/*">
                                    <div class="form-text">Recommended size: 1200x300 pixels</div>
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="description" class="form-label">Shop Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" required><?= isset($description) ? htmlspecialchars($description) : '' ?></textarea>
                                </div>
                            </div>
                            
                            <div class="mb-4 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#">Seller Terms of Service</a> and <a href="#">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <div class="d-grid mb-4">
                                <button type="submit" class="btn btn-primary btn-lg">Submit Application</button>
                            </div>
                            
                            <div class="text-center">
                                <p class="text-muted mb-0">
                                    Already have a seller account? <a href="<?= SITE_URL ?>/login.php">Login here</a>
                                </p>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const toggleButtons = document.querySelectorAll('.toggle-password');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const inputField = document.getElementById(targetId);
            
            const type = inputField.getAttribute('type') === 'password' ? 'text' : 'password';
            inputField.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>