<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi admin
if (!isLoggedIn() || $_SESSION['role'] !== ROLE_ADMIN) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$pageTitle = 'Categories';
$currentPage = 'categories';

// Inisialisasi variabel
$name = '';
$slug = '';
$description = '';
$parent_id = null;
$status = 'active';
$edit_id = 0;

// Fungsi untuk mendapatkan kategori dropdown untuk select
function getCategoryOptions($categories, $parent_id = null, $indent = '', $selected = null) {
    $html = '';
    
    foreach ($categories as $category) {
        if (($category['parent_id'] === $parent_id) || 
            ($category['parent_id'] === null && $parent_id === null)) {
            $select_status = ($selected == $category['id']) ? 'selected' : '';
            $html .= '<option value="' . $category['id'] . '" ' . $select_status . '>' . $indent . $category['name'] . '</option>';
            
            // Rekursif untuk child categories
            $html .= getCategoryOptions($categories, $category['id'], $indent . '— ', $selected);
        }
    }
    
    return $html;
}

// PENTING: Pastikan direktori upload ada dan memiliki izin yang benar
$upload_dir = '../assets/img/categories/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Fungsi upload gambar khusus untuk categories.php
function uploadCategoryImage($file, $uploadDir) {
    // Pastikan direktori ada
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return false;
        }
    }
    
    // Validasi file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Validasi ukuran file (5MB max)
    if ($file['size'] <= 0 || $file['size'] > 5242880) {
        return false;
    }
    
    // Validasi tipe file dengan extension
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return false;
    }
    
    // Generate nama file unik
    $filename = 'cat_' . uniqid() . '.' . $file_extension;
    $targetFile = $uploadDir . $filename;
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return $filename;
    }
    
    return false;
}

// Fungsi untuk menghapus gambar kategori
function deleteCategoryImage($conn, $category_id) {
    // Dapatkan informasi gambar
    $stmt = $conn->prepare("SELECT image FROM categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return [false, "Kategori tidak ditemukan."];
    }
    
    $category = $result->fetch_assoc();
    
    if (empty($category['image'])) {
        return [false, "Tidak ada gambar untuk dihapus."];
    }
    
    // Path gambar
    $image_path = '../assets/img/categories/' . $category['image'];
    
    // Hapus file fisik
    if (file_exists($image_path)) {
        if (!unlink($image_path)) {
            return [false, "Gagal menghapus file gambar."];
        }
    }
    
    // Update database, set image ke NULL
    $stmt = $conn->prepare("UPDATE categories SET image = NULL, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    
    if ($stmt->execute()) {
        return [true, "Gambar berhasil dihapus."];
    } else {
        return [false, "Gagal memperbarui database: " . $conn->error];
    }
}

// Hapus kategori
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Cek apakah ada produk yang menggunakan kategori ini
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $error = "Cannot delete this category. There are " . $row['count'] . " products assigned to it.";
    } else {
        // Cek apakah ada sub-kategori
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM categories WHERE parent_id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $error = "Cannot delete this category. It has " . $row['count'] . " subcategories.";
        } else {
            // Hapus gambar kategori jika ada
            $stmt = $conn->prepare("SELECT image FROM categories WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $category = $result->fetch_assoc();
                if (!empty($category['image'])) {
                    $image_path = '../assets/img/categories/' . $category['image'];
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
            }
            
            // Hapus kategori
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            
            if ($stmt->execute()) {
                $success = "Category deleted successfully.";
            } else {
                $error = "Failed to delete category.";
            }
        }
    }
}

// Handler untuk menghapus gambar kategori
if (isset($_GET['delete_image']) && !empty($_GET['delete_image'])) {
    $category_id = (int)$_GET['delete_image'];
    
    // Panggil fungsi delete image
    list($success, $message) = deleteCategoryImage($conn, $category_id);
    
    if ($success) {
        $_SESSION['success_message'] = $message;
    } else {
        $_SESSION['error_message'] = $message;
    }
    
    // Redirect ke halaman edit kategori
    header("Location: categories.php?edit=" . $category_id);
    exit;
}

// Edit kategori
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $category = $result->fetch_assoc();
        $name = $category['name'];
        $slug = $category['slug'];
        $description = $category['description'];
        $parent_id = $category['parent_id'];
        $status = $category['status'];
    } else {
        $error = "Category not found.";
        $edit_id = 0;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $slug = sanitize($_POST['slug']);
    $description = sanitize($_POST['description']);
    $parent_id = $_POST['parent_id'] === '0' ? null : (int)$_POST['parent_id'];
    $status = sanitize($_POST['status']);
    
    // Validasi
    if (empty($name)) {
        $error = "Category name is required.";
    } else {
        // Generate slug if not provided
        if (empty($slug)) {
            $slug = createSlug($name);
        }
        
        // Check for circular reference in parent-child relationship
        if ($edit_id > 0 && $parent_id > 0) {
            $current_parent = $parent_id;
            $is_circular = false;
            
            while ($current_parent > 0) {
                if ($current_parent == $edit_id) {
                    $is_circular = true;
                    break;
                }
                
                $stmt = $conn->prepare("SELECT parent_id FROM categories WHERE id = ?");
                $stmt->bind_param("i", $current_parent);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $parent_data = $result->fetch_assoc();
                    $current_parent = $parent_data['parent_id'];
                } else {
                    break;
                }
            }
            
            if ($is_circular) {
                $error = "Invalid parent category. Cannot create circular reference.";
            }
        }
        
        if (!isset($error)) {
            // Upload image if provided
            $image_name = null;
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // PERBAIKAN: Gunakan fungsi upload khusus untuk kategori
                $image_name = uploadCategoryImage($_FILES['image'], $upload_dir);
                
                if (!$image_name) {
                    $error = "Failed to upload image. Please try again.";
                }
            }
            
            if (!isset($error)) {
                if ($edit_id > 0) {
                    // Update existing category
                    if ($image_name) {
                        // Delete old image if exists
                        $stmt = $conn->prepare("SELECT image FROM categories WHERE id = ?");
                        $stmt->bind_param("i", $edit_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $old_data = $result->fetch_assoc();
                            if (!empty($old_data['image'])) {
                                $old_image = '../assets/img/categories/' . $old_data['image'];
                                if (file_exists($old_image)) {
                                    unlink($old_image);
                                }
                            }
                        }
                        
                        if ($parent_id === null) {
                            // Handle NULL parent_id separately
                            $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, parent_id = NULL, status = ?, image = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->bind_param("sssssi", $name, $slug, $description, $status, $image_name, $edit_id);
                        } else {
                            $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, parent_id = ?, status = ?, image = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->bind_param("ssisssi", $name, $slug, $description, $parent_id, $status, $image_name, $edit_id);
                        }
                    } else {
                        if ($parent_id === null) {
                            // Handle NULL parent_id separately
                            $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, parent_id = NULL, status = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->bind_param("ssssi", $name, $slug, $description, $status, $edit_id);
                        } else {
                            $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, parent_id = ?, status = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->bind_param("ssissi", $name, $slug, $description, $parent_id, $status, $edit_id);
                        }
                    }
                    
                    if ($stmt->execute()) {
                        $success = "Category updated successfully.";
                        
                        // Reset form
                        $name = '';
                        $slug = '';
                        $description = '';
                        $parent_id = null;
                        $status = 'active';
                        $edit_id = 0;
                    } else {
                        $error = "Failed to update category. Database error: " . $conn->error;
                    }
                } else {
                    // Insert new category
                    if ($image_name) {
                        if ($parent_id === null) {
                            // Handle NULL parent_id separately
                            $stmt = $conn->prepare("INSERT INTO categories (name, slug, description, parent_id, status, image, created_at, updated_at) VALUES (?, ?, ?, NULL, ?, ?, NOW(), NOW())");
                            $stmt->bind_param("sssss", $name, $slug, $description, $status, $image_name);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO categories (name, slug, description, parent_id, status, image, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                            $stmt->bind_param("ssiss", $name, $slug, $description, $parent_id, $status, $image_name);
                        }
                    } else {
                        if ($parent_id === null) {
                            // Handle NULL parent_id separately
                            $stmt = $conn->prepare("INSERT INTO categories (name, slug, description, parent_id, status, created_at, updated_at) VALUES (?, ?, ?, NULL, ?, NOW(), NOW())");
                            $stmt->bind_param("ssss", $name, $slug, $description, $status);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO categories (name, slug, description, parent_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                            $stmt->bind_param("ssiss", $name, $slug, $description, $parent_id, $status);
                        }
                    }
                    
                    if ($stmt->execute()) {
                        $success = "Category added successfully.";
                        
                        // Reset form
                        $name = '';
                        $slug = '';
                        $description = '';
                        $parent_id = null;
                        $status = 'active';
                    } else {
                        $error = "Failed to add category. Database error: " . $conn->error;
                    }
                }
            }
        }
    }
}

// Get all categories
$all_categories = getAllCategories($conn);

// Handle session messages
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

include '../includes/admin-header.php';
?>

<div class="admin-content-header">
    <h1 class="h3 mb-0">Manage Categories</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Categories</li>
        </ol>
    </nav>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><?= $edit_id ? 'Edit Category' : 'Add Category' ?></h5>
            </div>
            <div class="card-body">
                <form method="post" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="slug" class="form-label">Slug</label>
                        <input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars($slug) ?>">
                        <div class="form-text">Leave empty to auto-generate from name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($description) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Parent Category</label>
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="0" <?= $parent_id === null ? 'selected' : '' ?>>None (Top Level)</option>
                            <?= getCategoryOptions($all_categories, null, '', $parent_id) ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="image" class="form-label">Image (Not Support a WEBP file !!)</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        <?php if ($edit_id && !empty($category['image'])): ?>
                            <div class="mt-2">
                                <img src="<?= SITE_URL ?>/assets/img/categories/<?= $category['image'] ?>" alt="<?= htmlspecialchars($name) ?>" class="img-thumbnail" style="max-width: 100px;">
                                <div class="mt-2">
                                    <a href="categories.php?delete_image=<?= $edit_id ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this image?')">
                                        <i class="fas fa-trash"></i> Delete Image
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="mt-4">
                        <?php if ($edit_id): ?>
                            <button type="submit" class="btn btn-primary">Update Category</button>
                            <a href="categories.php" class="btn btn-outline-secondary">Cancel</a>
                        <?php else: ?>
                            <button type="submit" class="btn btn-success">Add Category</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Categories</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="categories-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Parent</th>
                                <th>Image</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_categories as $category): ?>
                                <tr>
                                    <td><?= htmlspecialchars($category['name']) ?></td>
                                    <td><?= htmlspecialchars($category['slug']) ?></td>
                                    <td><?= htmlspecialchars($category['parent_name'] ?? 'None') ?></td>
                                    <td>
                                        <?php if (!empty($category['image'])): ?>
                                            <img src="<?= SITE_URL ?>/assets/img/categories/<?= $category['image'] ?>" alt="<?= htmlspecialchars($category['name']) ?>" class="img-thumbnail" style="max-width: 50px;">
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No Image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $category['status'] === 'active' ? 'success' : 'danger' ?>">
                                            <?= ucfirst($category['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="categories.php?edit=<?= $category['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="categories.php?delete=<?= $category['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this category?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php if (!empty($category['image'])): ?>
                                            <a href="categories.php?delete_image=<?= $category['id'] ?>" class="btn btn-sm btn-warning" onclick="return confirm('Are you sure you want to delete this image?')">
                                                <i class="fas fa-image"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($all_categories)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No categories found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Generate slug from name
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    
    nameInput.addEventListener('blur', function() {
        if (slugInput.value === '') {
            const name = this.value.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');
            
            slugInput.value = name;
        }
    });
    
    // Image preview
    const imageInput = document.getElementById('image');
    if (imageInput) {
        imageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                const preview = document.createElement('div');
                preview.className = 'mt-2';
                
                const img = document.createElement('img');
                img.className = 'img-thumbnail';
                img.style.maxWidth = '100px';
                
                reader.onload = function(e) {
                    img.src = e.target.result;
                    
                    // Remove existing preview if any
                    const existingPreview = imageInput.nextElementSibling;
                    if (existingPreview && existingPreview.classList.contains('mt-2')) {
                        existingPreview.remove();
                    }
                    
                    preview.appendChild(img);
                    imageInput.parentNode.insertBefore(preview, imageInput.nextSibling);
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    // Initialize DataTable if available
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#categories-table').DataTable({
            order: [[0, 'asc']],
            pageLength: 10,
            responsive: true
        });
    }
});
</script>

<?php include '../includes/admin-footer.php'; ?>

