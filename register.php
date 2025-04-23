<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Register';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . SITE_URL);
    exit;
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitize($_POST['full_name']);
    
    // Validate input
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
        $username_safe = $conn->real_escape_string($username);
        $email_safe = $conn->real_escape_string($email);
        $check_query = "SELECT * FROM users WHERE username = '$username_safe' OR email = '$email_safe'";
        $check_result = $conn->query($check_query);
        
        if ($check_result && $check_result->num_rows > 0) {
            $existing_user = $check_result->fetch_assoc();
            if ($existing_user['username'] === $username) {
                $error = 'Username already taken';
            } else {
                $error = 'Email already registered';
            }
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $full_name_safe = $conn->real_escape_string($full_name);
            
            // Generate random math question for verification
            $num1 = mt_rand(1, 20);
            $num2 = mt_rand(1, 20);
            $answer = $num1 + $num2;
            
            // Set email_verified to 1 to skip verification (temporary solution)
            // or add a session-based verification later
            $sql = "INSERT INTO users (username, email, password, full_name, role, status, email_verified) 
                   VALUES ('$username_safe', '$email_safe', '$hashed_password', '$full_name_safe', 'customer', 'active', 1)";
            
            if ($conn->query($sql)) {
                $success = 'Registration successful! You can now login.';
                
                // Redirect after showing success message
                header('refresh:3;url=' . SITE_URL . '/login.php');
            } else {
                $error = 'Registration failed: ' . $conn->error;
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="register-card card border-0 shadow-sm rounded-3">
                <div class="card-body p-4 p-md-5">
                    <h2 class="text-center mb-4">Create Account</h2>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <?= $success ?>
                            <a href="<?= SITE_URL ?>/login.php" class="alert-link">Click here to login</a>.
                        </div>
                    <?php else: ?>
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?= isset($full_name) ? htmlspecialchars($full_name) : '' ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" required>
                                </div>
                                <div class="form-text">Username must be unique and will be used for login.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                        <i class="far fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Password must be at least 6 characters long.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                        <i class="far fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <div class="d-grid mb-4">
                                <button type="submit" class="btn btn-primary btn-lg">Register</button>
                            </div>
                            
                            <div class="text-center mb-3">
                                <p class="mb-0">Already have an account? <a href="<?= SITE_URL ?>/login.php">Login here</a></p>
                            </div>
                            
                            <div class="divider text-center mb-3">
                                <span>OR</span>
                            </div>
                            
                            <div class="social-register">
                                <div class="d-grid gap-2">
                                    <a href="#" class="btn btn-outline-danger">
                                        <i class="fab fa-google me-2"></i> Register with Google
                                    </a>
                                    <a href="#" class="btn btn-outline-primary">
                                        <i class="fab fa-facebook-f me-2"></i> Register with Facebook
                                    </a>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
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