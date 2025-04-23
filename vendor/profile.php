<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check vendor authentication
if (!isLoggedIn() || $_SESSION['role'] !== ROLE_VENDOR) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$vendor_id = $_SESSION['user_id'];
$pageTitle = 'My Profile';
$currentPage = 'profile';

// Check if profile_picture column exists, if not add it
$conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
if ($conn->affected_rows == 0) {
    // Add profile_picture column
    $conn->query("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL");
}

// Get user data
$sql = "SELECT * FROM users WHERE id = $vendor_id";
$result = $conn->query($sql);

if ($result->num_rows === 0) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$user = $result->fetch_assoc();

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Basic fields
    $username = isset($_POST['username']) ? sanitize($_POST['username']) : '';
    $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : '';
    
    // Build update query based on what we have
    $update_parts = [];
    
    if (!empty($username)) {
        $update_parts[] = "username = '$username'";
    }
    
    if (!empty($email)) {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if email is already in use
            $check_sql = "SELECT id FROM users WHERE email = '$email' AND id != $vendor_id";
            $check_result = $conn->query($check_sql);
            if ($check_result->num_rows > 0) {
                $error = "This email address is already in use.";
            } else {
                $update_parts[] = "email = '$email'";
            }
        }
    }
    
    if (isset($phone)) {
        $update_parts[] = "phone = '$phone'";
    }
    
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/img/users/';
        
        // Make sure the directory exists
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_name = $_FILES['profile_picture']['name'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file type
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $profile_pic = 'profile_' . $vendor_id . '_' . uniqid() . '.' . $file_extension;
            $profile_path = $upload_dir . $profile_pic;
            
            // Delete old picture if exists
            if (!empty($user['profile_picture'])) {
                $old_pic = $upload_dir . $user['profile_picture'];
                if (file_exists($old_pic)) {
                    unlink($old_pic);
                }
            }
            
            // Upload new picture
            if (move_uploaded_file($file_tmp, $profile_path)) {
                $update_parts[] = "profile_picture = '$profile_pic'";
            } else {
                $error = "Failed to upload profile picture. Make sure the upload directory is writable.";
            }
        } else {
            $error = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
        }
    }
    
    // Execute update if there are parts to update and no errors
    if (!empty($update_parts) && !isset($error)) {
        $update_sql = "UPDATE users SET " . implode(", ", $update_parts) . " WHERE id = $vendor_id";
        
        if ($conn->query($update_sql)) {
            $success = "Profile updated successfully.";
            
            // Update session data
            if (!empty($username)) {
                $_SESSION['username'] = $username;
            }
            if (!empty($email)) {
                $_SESSION['email'] = $email;
            }
            
            // Refresh user data
            $result = $conn->query($sql);
            $user = $result->fetch_assoc();
        } else {
            $error = "Failed to update profile: " . $conn->error;
        }
    }
}

// Process password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 8) {
        $password_error = "Password must be at least 8 characters long.";
    } else {
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $password_error = "Current password is incorrect.";
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = '$hashed_password' WHERE id = $vendor_id";
            
            if ($conn->query($update_sql)) {
                $password_success = "Password changed successfully.";
            } else {
                $password_error = "Failed to change password: " . $conn->error;
            }
        }
    }
}

include '../includes/vendor-header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Profile</h1>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-4 mb-4">
            <!-- Profile Card -->
            <div class="card shadow mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <?php if (!empty($user['profile_picture'])): ?>
                            <img src="<?= SITE_URL ?>/assets/img/users/<?= $user['profile_picture'] ?>" 
                                alt="Profile Picture" 
                                class="rounded-circle img-thumbnail"
                                style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto" 
                                style="width: 150px; height: 150px; font-size: 4rem;">
                                <?= strtoupper(substr($user['username'] ?? $user['email'] ?? 'U', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <h4 class="mb-1">
                        <?= htmlspecialchars($user['username'] ?? $user['email'] ?? 'Vendor') ?>
                    </h4>
                    <p class="text-muted mb-3">Vendor</p>
                    
                    <div class="text-start">
                        <div class="mb-2">
                            <i class="fas fa-envelope text-primary me-2"></i> <?= htmlspecialchars($user['email']) ?>
                        </div>
                        
                        <?php if (!empty($user['phone'])): ?>
                            <div class="mb-2">
                                <i class="fas fa-phone text-primary me-2"></i> <?= htmlspecialchars($user['phone']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($user['created_at'])): ?>
                            <div class="mb-2">
                                <i class="fas fa-calendar text-primary me-2"></i> Member since <?= date('M d, Y', strtotime($user['created_at'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8 mb-4">
            <!-- Edit Profile Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Edit Profile</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-4">
                            <label for="profile_picture" class="form-label">Profile Picture</label>
                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                            <small class="form-text text-muted">Max 2MB. Allowed: JPG, JPEG, PNG, GIF</small>
                            
                            <?php if (!empty($user['profile_picture'])): ?>
                                <div class="mt-2">
                                    <p class="mb-1">Current Profile Picture:</p>
                                    <img src="<?= SITE_URL ?>/assets/img/users/<?= $user['profile_picture'] ?>" 
                                         alt="Profile Picture" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Preview uploaded profile picture
    const profileInput = document.getElementById('profile_picture');
    if (profileInput) {
        profileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Find existing preview or create a new one
                    let previewContainer = document.querySelector('#profile_picture + small + div');
                    
                    if (!previewContainer) {
                        previewContainer = document.createElement('div');
                        previewContainer.className = 'mt-2';
                        
                        const smallElement = document.querySelector('#profile_picture + small');
                        smallElement.parentNode.insertBefore(previewContainer, smallElement.nextSibling);
                    }
                    
                    // Clear the container
                    previewContainer.innerHTML = '';
                    
                    // Add new preview
                    const previewText = document.createElement('p');
                    previewText.className = 'mb-1';
                    previewText.textContent = 'New Profile Picture:';
                    
                    const previewImg = document.createElement('img');
                    previewImg.className = 'img-thumbnail';
                    previewImg.style.maxWidth = '100px';
                    previewImg.style.maxHeight = '100px';
                    previewImg.alt = 'Profile Picture Preview';
                    previewImg.src = e.target.result;
                    
                    previewContainer.appendChild(previewText);
                    previewContainer.appendChild(previewImg);
                };
                
                reader.readAsDataURL(this.files[0]);
                
                // Validate file size
                const fileSize = this.files[0].size;
                const maxSize = 2 * 1024 * 1024; // 2MB
                
                if (fileSize > maxSize) {
                    alert('File size is too large. Maximum size is 2MB.');
                    this.value = ''; // Clear the file input
                }
            }
        });
    }
    
    // Password strength meter
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const strengthBar = document.getElementById('password-strength-bar');
    const strengthText = document.getElementById('password-strength-text');
    
    if (newPassword && strengthBar && strengthText) {
        newPassword.addEventListener('input', function() {
            const value = this.value;
            let strength = 0;
            let status = '';
            
            if (value.length >= 8) strength += 25;
            if (value.match(/[A-Z]/)) strength += 25;
            if (value.match(/[0-9]/)) strength += 25;
            if (value.match(/[^A-Za-z0-9]/)) strength += 25;
            
            // Update strength bar
            strengthBar.style.width = strength + '%';
            
            // Remove existing classes
            strengthBar.classList.remove('bg-danger', 'bg-warning', 'bg-info', 'bg-success');
            
            // Add appropriate class and set text based on strength
            if (strength <= 25) {
                strengthBar.classList.add('bg-danger');
                status = 'Too weak';
            } else if (strength <= 50) {
                strengthBar.classList.add('bg-warning');
                status = 'Weak';
            } else if (strength <= 75) {
                strengthBar.classList.add('bg-info');
                status = 'Good';
            } else {
                strengthBar.classList.add('bg-success');
                status = 'Strong';
            }
            
            strengthText.textContent = 'Password strength: ' + status;
        });
        
        if (confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                if (this.value !== newPassword.value) {
                    this.setCustomValidity("Passwords don't match");
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    }
});
</script>

<?php include '../includes/vendor-footer.php'; ?>