<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check vendor authentication
if (!isLoggedIn() || $_SESSION['role'] !== ROLE_VENDOR) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pageTitle = 'Account Settings';
$currentPage = 'settings';

// Get current date and time
$current_date = date('Y-m-d H:i:s');
$current_user = $_SESSION['username'] ?? 'MochSetiawan';

// Get the vendor ID from the vendors table (using user_id foreign key)
$vendor_result = $conn->query("SELECT id FROM vendors WHERE user_id = $user_id");
if ($vendor_result && $vendor_result->num_rows > 0) {
    $vendor_row = $vendor_result->fetch_assoc();
    $vendor_id = $vendor_row['id'];
} else {
    // If vendor record doesn't exist, create one
    $conn->query("INSERT INTO vendors (user_id, created_at) VALUES ($user_id, NOW())");
    $vendor_id = $conn->insert_id;
    
    if (!$vendor_id) {
        die("Error: Could not find or create vendor record. Please contact the administrator.");
    }
}

// Get current user and vendor data
$user_query = "SELECT u.*, v.* 
               FROM users u 
               LEFT JOIN vendors v ON u.id = v.user_id 
               WHERE u.id = $user_id";
$user_result = $conn->query($user_query);

if (!$user_result || $user_result->num_rows === 0) {
    die("Error: User data not found.");
}

$user_data = $user_result->fetch_assoc();

// Process profile update form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Get form data
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $store_name = sanitize($_POST['store_name']);
    $store_description = sanitize($_POST['store_description']);
    
    // Get address data
    $address_line1 = sanitize($_POST['address_line1']);
    $address_line2 = sanitize($_POST['address_line2']);
    $city = sanitize($_POST['city']);
    $state = sanitize($_POST['state']);
    $postal_code = sanitize($_POST['postal_code']);
    $country = sanitize($_POST['country']);
    
    // Validation
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    } else {
        // Check if email is already in use by another user
        $email_check = $conn->query("SELECT id FROM users WHERE email = '$email' AND id != $user_id");
        if ($email_check && $email_check->num_rows > 0) {
            $errors[] = "Email address is already in use by another account";
        }
    }
    
    if (empty($store_name)) {
        $errors[] = "Store name is required";
    }
    
    // If no errors, update the profile
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update user data
            $update_user = "UPDATE users SET 
                            first_name = '$first_name', 
                            last_name = '$last_name', 
                            email = '$email', 
                            phone = '$phone',
                            updated_at = NOW() 
                            WHERE id = $user_id";
            
            if (!$conn->query($update_user)) {
                throw new Exception("Failed to update user profile: " . $conn->error);
            }
            
            // Update vendor data
            $update_vendor = "UPDATE vendors SET 
                             store_name = '$store_name', 
                             description = '$store_description',
                             updated_at = NOW() 
                             WHERE user_id = $user_id";
            
            if (!$conn->query($update_vendor)) {
                throw new Exception("Failed to update vendor profile: " . $conn->error);
            }
            
            // Check if vendor address exists and update or insert
            $address_check = $conn->query("SELECT id FROM vendor_addresses WHERE vendor_id = $vendor_id");
            
            if ($address_check && $address_check->num_rows > 0) {
                // Update existing address
                $address_id = $address_check->fetch_assoc()['id'];
                $update_address = "UPDATE vendor_addresses SET 
                                  address_line1 = '$address_line1', 
                                  address_line2 = '$address_line2', 
                                  city = '$city', 
                                  state = '$state', 
                                  postal_code = '$postal_code', 
                                  country = '$country',
                                  updated_at = NOW() 
                                  WHERE id = $address_id";
                
                if (!$conn->query($update_address)) {
                    throw new Exception("Failed to update vendor address: " . $conn->error);
                }
            } else {
                // Insert new address
                $insert_address = "INSERT INTO vendor_addresses 
                                  (vendor_id, address_line1, address_line2, city, state, postal_code, country, created_at) 
                                  VALUES 
                                  ($vendor_id, '$address_line1', '$address_line2', '$city', '$state', '$postal_code', '$country', NOW())";
                
                if (!$conn->query($insert_address)) {
                    throw new Exception("Failed to add vendor address: " . $conn->error);
                }
            }
            
            // Process store logo upload if provided
            if (isset($_FILES['store_logo']) && $_FILES['store_logo']['error'] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['store_logo']['tmp_name'];
                $name = $_FILES['store_logo']['name'];
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                
                // Validate file type
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($extension, $allowed_extensions)) {
                    throw new Exception("Invalid logo file type. Allowed types: " . implode(', ', $allowed_extensions));
                }
                
                // Validate file size (max 2MB)
                if ($_FILES['store_logo']['size'] > 2 * 1024 * 1024) {
                    throw new Exception("Logo file size exceeds the 2MB limit");
                }
                
                // Generate unique filename
                $new_filename = 'store_' . $vendor_id . '_' . uniqid() . '.' . $extension;
                $destination = '../assets/img/vendors/' . $new_filename;
                
                // Create directory if it doesn't exist
                if (!file_exists('../assets/img/vendors/')) {
                    mkdir('../assets/img/vendors/', 0755, true);
                }
                
                // Move uploaded file
                if (move_uploaded_file($tmp_name, $destination)) {
                    // Update vendor with new logo
                    $update_logo = "UPDATE vendors SET logo = '$new_filename' WHERE id = $vendor_id";
                    
                    if (!$conn->query($update_logo)) {
                        throw new Exception("Failed to update store logo: " . $conn->error);
                    }
                    
                    // Delete old logo if exists
                    if (!empty($user_data['logo']) && $user_data['logo'] !== $new_filename) {
                        $old_logo = '../assets/img/vendors/' . $user_data['logo'];
                        if (file_exists($old_logo)) {
                            unlink($old_logo);
                        }
                    }
                } else {
                    throw new Exception("Failed to upload store logo");
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Set success message
            $_SESSION['success_message'] = "Your profile has been updated successfully!";
            
            // Refresh page to show updated data
            header('Location: ' . VENDOR_URL . '/settings.php');
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Process password update form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate passwords
    if (empty($current_password)) {
        $errors[] = "Current password is required";
    }
    
    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters long";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    // Verify current password
    if (empty($errors)) {
        // Get the hashed password from database
        $password_query = "SELECT password FROM users WHERE id = $user_id";
        $password_result = $conn->query($password_query);
        
        if ($password_result && $password_result->num_rows > 0) {
            $stored_hash = $password_result->fetch_assoc()['password'];
            
            // Verify current password
            if (!password_verify($current_password, $stored_hash)) {
                $errors[] = "Current password is incorrect";
            }
        } else {
            $errors[] = "User data not found";
        }
    }
    
    // If no errors, update the password
    if (empty($errors)) {
        // Hash the new password
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update the password in the database
        $update_password = "UPDATE users SET password = '$new_hash', updated_at = NOW() WHERE id = $user_id";
        
        if ($conn->query($update_password)) {
            $_SESSION['success_message'] = "Your password has been updated successfully!";
            header('Location: ' . VENDOR_URL . '/settings.php');
            exit;
        } else {
            $password_error = "Failed to update password: " . $conn->error;
        }
    } else {
        $password_error = implode('<br>', $errors);
    }
}

// Get vendor address
$address_query = "SELECT * FROM vendor_addresses WHERE vendor_id = $vendor_id LIMIT 1";
$address_result = $conn->query($address_query);
$address_data = ($address_result && $address_result->num_rows > 0) ? $address_result->fetch_assoc() : [];

include '../includes/vendor-header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Account Settings</h1>
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
                        Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?= $current_date ?>
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-user-clock fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?= $_SESSION['success_message'] ?>
            <?php unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Profile Settings -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Profile Settings</h6>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <?= $error_message ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($user_data['first_name'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($user_data['last_name'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user_data['phone'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="store_name" class="form-label">Store Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="store_name" name="store_name" value="<?= htmlspecialchars($user_data['store_name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="store_description" class="form-label">Store Description</label>
                            <textarea class="form-control" id="store_description" name="store_description" rows="4"><?= htmlspecialchars($user_data['description'] ?? '') ?></textarea>
                            <small class="text-muted">Tell customers about your store, products, and services</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="store_logo" class="form-label">Store Logo</label>
                            <input type="file" class="form-control" id="store_logo" name="store_logo" accept="image/*">
                            <small class="text-muted">Recommended size: 200x200px. Max file size: 2MB</small>
                            
                            <?php if (!empty($user_data['logo'])): ?>
                                <div class="mt-2">
                                    <img src="<?= SITE_URL ?>/assets/img/vendors/<?= $user_data['logo'] ?>" alt="Store Logo" class="img-thumbnail" style="max-width: 100px;">
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="font-weight-bold">Store Address</h5>
                        
                        <div class="mb-3">
                            <label for="address_line1" class="form-label">Address Line 1</label>
                            <input type="text" class="form-control" id="address_line1" name="address_line1" value="<?= htmlspecialchars($address_data['address_line1'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="address_line2" class="form-label">Address Line 2</label>
                            <input type="text" class="form-control" id="address_line2" name="address_line2" value="<?= htmlspecialchars($address_data['address_line2'] ?? '') ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" value="<?= htmlspecialchars($address_data['city'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="state" class="form-label">State/Province</label>
                                <input type="text" class="form-control" id="state" name="state" value="<?= htmlspecialchars($address_data['state'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="postal_code" class="form-label">Postal/ZIP Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?= htmlspecialchars($address_data['postal_code'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="country" name="country" value="<?= htmlspecialchars($address_data['country'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Password Settings -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Change Password</h6>
                </div>
                <div class="card-body">
                    <?php if (isset($password_error)): ?>
                        <div class="alert alert-danger">
                            <?= $password_error ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" name="update_password" class="btn btn-warning">
                            <i class="fas fa-key mr-1"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Account Info -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Account Info</h6>
                </div>
                <div class="card-body">
                    <p><strong>Account Type:</strong> Vendor</p>
                    <p><strong>Joined Date:</strong> <?= date('M d, Y', strtotime($user_data['created_at'])) ?></p>
                    <p><strong>Last Login:</strong> 
                        <?= !empty($user_data['last_login']) ? date('M d, Y H:i', strtotime($user_data['last_login'])) : 'N/A' ?>
                    </p>
                    
                    <?php if (!empty($user_data['status']) && $user_data['status'] !== 'active'): ?>
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Your account status is <strong><?= ucfirst($user_data['status']) ?></strong>
                            <?php if ($user_data['status'] === 'pending'): ?>
                                <p class="mb-0 mt-1">Your account is pending approval by administrators.</p>
                            <?php elseif ($user_data['status'] === 'suspended'): ?>
                                <p class="mb-0 mt-1">Your account has been suspended. Please contact customer support.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Help & Support -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Help & Support</h6>
                </div>
                <div class="card-body">
                    <p>Need help with your account?</p>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-envelope text-primary mr-2"></i> 
                            <a href="mailto:support@shopverse.com">support@shopverse.com</a>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-phone text-primary mr-2"></i> 
                            <a href="tel:+1234567890">+1 (234) 567-890</a>
                        </li>
                        <li>
                            <i class="fas fa-question-circle text-primary mr-2"></i> 
                            <a href="<?= SITE_URL ?>/help" target="_blank">Help Center</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password confirmation validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    confirmPassword.addEventListener('input', function() {
        if (this.value !== newPassword.value) {
            this.setCustomValidity('Passwords do not match');
        } else {
            this.setCustomValidity('');
        }
    });
    
    newPassword.addEventListener('input', function() {
        if (confirmPassword.value !== '' && confirmPassword.value !== this.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    });
    
    // Image preview
    const storeLogoInput = document.getElementById('store_logo');
    
    storeLogoInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            // Validate file size
            if (this.files[0].size > 2 * 1024 * 1024) {
                alert('File size exceeds 2MB limit');
                this.value = '';
                return;
            }
            
            // Validate file type
            const fileType = this.files[0].type;
            if (!fileType.match('image/jpeg') && !fileType.match('image/png') && !fileType.match('image/gif')) {
                alert('Please select a valid image file (JPG, PNG, or GIF)');
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                // Create or update image preview
                let imgPreview = storeLogoInput.nextElementSibling.nextElementSibling;
                
                if (!imgPreview || !imgPreview.classList.contains('mt-2')) {
                    imgPreview = document.createElement('div');
                    imgPreview.className = 'mt-2';
                    storeLogoInput.parentNode.appendChild(imgPreview);
                }
                
                imgPreview.innerHTML = `<img src="${e.target.result}" alt="Store Logo" class="img-thumbnail" style="max-width: 100px;">`;
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
});
</script>

<?php include '../includes/vendor-footer.php'; ?>