<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi admin
if (!isLoggedIn() || $_SESSION['role'] !== ROLE_ADMIN) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$pageTitle = 'My Profile';
$currentPage = 'profile';

// Aktifkan error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ' . ADMIN_URL . '/dashboard.php');
    exit;
}

$user = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Debug info for file upload
    if (isset($_FILES['profile_image'])) {
        error_log("Profile image upload attempt: " . print_r($_FILES['profile_image'], true));
    }
    
    // Process avatar change
    if ($action === 'change_avatar' && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        // Upload profile image
        $uploadDir = '../assets/img/';
        $profile_image = uploadImage($_FILES['profile_image'], $uploadDir);
        
        if ($profile_image) {
            // Update profile image in database
            $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $stmt->bind_param("si", $profile_image, $user_id);
            
            if ($stmt->execute()) {
                // Update session profile image
                $_SESSION['profile_image'] = $profile_image;
                $avatar_success = 'Profile picture updated successfully.';
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $avatar_error = 'Failed to update profile picture in database.';
            }
        } else {
            $avatar_error = 'Failed to upload image. Please try again.';
        }
    }
    
    // Process profile update
    if ($action === 'update_profile') {
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $state = sanitize($_POST['state'] ?? '');
        $postal_code = sanitize($_POST['postal_code'] ?? '');
        $country = sanitize($_POST['country'] ?? 'Indonesia');
        
        // Validate input
        if (empty($full_name) || empty($email)) {
            $error = 'Name and email are required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check if email is already used by another user
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Email is already used by another account.';
            } else {
                // Update profile
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, postal_code = ?, country = ? WHERE id = ?");
                $stmt->bind_param("ssssssssi", $full_name, $email, $phone, $address, $city, $state, $postal_code, $country, $user_id);
                
                if ($stmt->execute()) {
                    // Update session data
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    
                    $success = 'Profile updated successfully.';
                    
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                } else {
                    $error = 'Failed to update profile. Please try again.';
                }
            }
        }
    }
    
    // Process password change
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate input
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $pwd_error = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $pwd_error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $pwd_error = 'New password must be at least 6 characters long.';
        } else {
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $pwd_success = 'Password changed successfully.';
                } else {
                    $pwd_error = 'Failed to change password. Please try again.';
                }
            } else {
                $pwd_error = 'Current password is incorrect.';
            }
        }
    }
}

include '../includes/admin-header.php';
?>

<div class="admin-content-header">
    <h1 class="h3 mb-0">My Profile</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">My Profile</li>
        </ol>
    </nav>
</div>

<div class="row">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <img src="<?= SITE_URL ?>/assets/img/<?= $user['profile_image'] ?: DEFAULT_IMG ?>" alt="<?= $user['username'] ?>" class="rounded-circle img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;">
                <h5 class="my-3"><?= htmlspecialchars($user['full_name']) ?></h5>
                <p class="text-muted mb-1">Administrator</p>
                <p class="text-muted mb-4"><?= htmlspecialchars($user['city'] ? $user['city'] . ', ' . $user['country'] : $user['country']) ?></p>
                
                <?php if (isset($avatar_success)): ?>
                    <div class="alert alert-success"><?= $avatar_success ?></div>
                <?php endif; ?>
                
                <?php if (isset($avatar_error)): ?>
                    <div class="alert alert-danger"><?= $avatar_error ?></div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-center mb-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#changeAvatarModal">
                        <i class="fas fa-camera me-2"></i> Change Avatar
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Account Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-sm-4">
                        <p class="mb-0 text-muted">Username</p>
                    </div>
                    <div class="col-sm-8">
                        <p class="mb-0"><?= htmlspecialchars($user['username']) ?></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4">
                        <p class="mb-0 text-muted">Email</p>
                    </div>
                    <div class="col-sm-8">
                        <p class="mb-0"><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4">
                        <p class="mb-0 text-muted">Phone</p>
                    </div>
                    <div class="col-sm-8">
                        <p class="mb-0"><?= htmlspecialchars($user['phone'] ?: 'Not set') ?></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4">
                        <p class="mb-0 text-muted">Role</p>
                    </div>
                    <div class="col-sm-8">
                        <p class="mb-0">
                            <span class="badge bg-danger">Administrator</span>
                        </p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4">
                        <p class="mb-0 text-muted">Joined</p>
                    </div>
                    <div class="col-sm-8">
                        <p class="mb-0"><?= date('d M Y', strtotime($user['created_at'])) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <!-- Edit Profile -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Edit Profile</h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="country" class="form-label">Country</label>
                            <input type="text" class="form-control" id="country" name="country" value="<?= htmlspecialchars($user['country'] ?: 'Indonesia') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($user['address']) ?></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="city" class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?= htmlspecialchars($user['city']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="state" class="form-label">State/Province</label>
                            <input type="text" class="form-control" id="state" name="state" value="<?= htmlspecialchars($user['state']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="postal_code" class="form-label">Postal Code</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?= htmlspecialchars($user['postal_code']) ?>">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Change Password</h5>
            </div>
            <div class="card-body">
                <?php if (isset($pwd_success)): ?>
                    <div class="alert alert-success"><?= $pwd_success ?></div>
                <?php endif; ?>
                
                <?php if (isset($pwd_error)): ?>
                    <div class="alert alert-danger"><?= $pwd_error ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <ul class="password-requirements text-muted small">
                            <li>Password must be at least 6 characters long</li>
                            <li>Include at least one uppercase letter</li>
                            <li>Include at least one number</li>
                            <li>Include at least one special character</li>
                        </ul>
                    </div>
                    
                    <div class="mt-2">
                        <button type="submit" class="btn btn-danger">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Change Avatar Modal - Ini adalah kunci perbaikan! -->
<div class="modal fade" id="changeAvatarModal" tabindex="-1" aria-labelledby="changeAvatarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeAvatarModalLabel">Change Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Form khusus untuk upload gambar -->
                <form method="post" action="" enctype="multipart/form-data" id="avatar-form">
                    <input type="hidden" name="action" value="change_avatar">
                    
                    <div class="mb-3">
                        <label for="profile_image" class="form-label">Upload New Image</label>
                        <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*" required>
                        <div class="form-text">Recommended size: 300x300 pixels. Max file size: 5MB.</div>
                    </div>
                    
                    <div class="mt-3">
                        <img id="image-preview" src="#" alt="Preview" class="img-thumbnail d-none" style="max-width: 100%; max-height: 300px;">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="avatar-form" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image preview
    document.getElementById('profile_image').addEventListener('change', function(e) {
        const preview = document.getElementById('image-preview');
        const file = e.target.files[0];
        
        if (file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('d-none');
            }
            
            reader.readAsDataURL(file);
        }
    });
});
</script>

<?php include '../includes/admin-footer.php'; ?>