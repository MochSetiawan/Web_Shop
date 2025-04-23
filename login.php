<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Login';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . SITE_URL);
    exit;
}

$error = '';
$verification_message = '';

// Show verification success message if redirected from verification
if (isset($_GET['verified']) && $_GET['verified'] == 1) {
    $verification_message = 'Your account has been successfully verified. You can now login.';
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Check user credentials
        $username_safe = $conn->real_escape_string($username);
        $query = "SELECT * FROM users WHERE (username = '$username_safe' OR email = '$username_safe') AND status = 'active'";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Check if account is active
            if ($user['status'] !== 'active') {
                $error = 'Your account has been deactivated. Please contact support.';
            }
            elseif (isset($user['email_verified']) && $user['email_verified'] == 0) {
                // Account not verified, redirect to verification page
                $_SESSION['verification_user_id'] = $user['id'];
                header('Location: ' . SITE_URL . '/verify-account.php');
                exit;
            }
            // Verify password
            elseif (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_image'] = $user['profile_image'];
                
                // Set remember-me cookie if requested
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expire = time() + 30 * 24 * 60 * 60; // 30 days
                    
                    // Store token in database
                    $update_query = "UPDATE users SET remember_token = '$token' WHERE id = {$user['id']}";
                    $conn->query($update_query);
                    
                    // Set cookie
                    setcookie('remember_token', $token, $expire, '/');
                }
                
                // Redirect to requested page or dashboard based on role
                if (isset($_SESSION['redirect']) && !empty($_SESSION['redirect'])) {
                    $redirect = $_SESSION['redirect'];
                    unset($_SESSION['redirect']);
                    header("Location: $redirect");
                } else {
                    if ($user['role'] === ROLE_ADMIN) {
                        header('Location: ' . ADMIN_URL . '/dashboard.php');
                    } elseif ($user['role'] === ROLE_VENDOR) {
                        header('Location: ' . VENDOR_URL . '/dashboard.php');
                    } else {
                        header('Location: ' . SITE_URL);
                    }
                }
                exit;
            } else {
                $error = 'Invalid password';
            }
        } else {
            $error = 'Username or email not found';
        }
    }
}

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="login-card card border-0 shadow-sm rounded-3">
                <div class="card-body p-4 p-md-5">
                    <h2 class="text-center mb-4">Login</h2>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($verification_message)): ?>
                        <div class="alert alert-success"><?= $verification_message ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username or Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="far fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                            <a href="<?= SITE_URL ?>/forgot-password.php" class="float-end">Forgot password?</a>
                        </div>
                        
                        <div class="d-grid mb-4">
                            <button type="submit" class="btn btn-primary btn-lg">Login</button>
                        </div>
                        
                        <div class="text-center mb-3">
                            <p class="mb-0">Don't have an account? <a href="<?= SITE_URL ?>/register.php">Register here</a></p>
                        </div>
                        
                        <div class="divider text-center mb-3">
                            <span>OR</span>
                        </div>
                        
                        <div class="social-login">
                            <div class="d-grid gap-2">
                                <a href="#" class="btn btn-outline-danger">
                                    <i class="fab fa-google me-2"></i> Login with Google
                                </a>
                                <a href="#" class="btn btn-outline-primary">
                                    <i class="fab fa-facebook-f me-2"></i> Login with Facebook
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.divider {
    position: relative;
    text-align: center;
    margin: 20px 0;
}
.divider span {
    background-color: #fff;
    padding: 0 15px;
    position: relative;
    z-index: 1;
    color: #6c757d;
}
.divider:before {
    content: "";
    display: block;
    width: 100%;
    height: 1px;
    background-color: #e0e0e0;
    position: absolute;
    top: 50%;
    z-index: 0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const togglePassword = document.querySelector('.toggle-password');
    const passwordInput = document.querySelector('#password');
    
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.querySelector('i').classList.toggle('fa-eye');
        this.querySelector('i').classList.toggle('fa-eye-slash');
    });
});
</script>

<?php include 'includes/footer.php'; ?>