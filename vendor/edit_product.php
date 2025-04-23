<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi vendor
if (!isLoggedIn() || $_SESSION['role'] !== 'vendor') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pageTitle = 'Edit Produk';
$currentPage = 'products';

// Cek koneksi database
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Dapatkan ID vendor dari tabel users
$vendor_data_sql = "SELECT v.id AS vendor_id FROM vendors v WHERE v.user_id = $user_id";
$vendor_result = $conn->query($vendor_data_sql);

if (!$vendor_result || $vendor_result->num_rows == 0) {
    // Buat record vendor jika belum ada
    $conn->query("INSERT INTO vendors (user_id, shop_name, status) VALUES 
                 ($user_id, 'Toko Saya', 'active')");
    
    // Dapatkan ID vendor yang baru dibuat
    $vendor_result = $conn->query($vendor_data_sql);
}

$vendor_data = $vendor_result->fetch_assoc();
$vendor_id = $vendor_data['vendor_id'];

// Validasi ID produk
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID produk tidak valid.";
    header('Location: ' . VENDOR_URL . '/products.php');
    exit;
}

$product_id = (int)$_GET['id'];

// Ambil data produk
$product_sql = "SELECT p.*, c.name as category_name 
               FROM products p 
               LEFT JOIN categories c ON p.category_id = c.id
               WHERE p.id = $product_id AND p.vendor_id = $vendor_id";
$product_result = $conn->query($product_sql);

// Cek apakah produk ditemukan dan milik vendor ini
if (!$product_result || $product_result->num_rows === 0) {
    $_SESSION['error_message'] = "Produk tidak ditemukan atau Anda tidak memiliki akses.";
    header('Location: ' . VENDOR_URL . '/products.php');
    exit;
}

$product = $product_result->fetch_assoc();

// Ambil semua kategori untuk dropdown
$categories_sql = "SELECT id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);
$categories = [];
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Ambil gambar produk
$images_sql = "SELECT * FROM product_images WHERE product_id = $product_id ORDER BY is_main DESC, id ASC";
$images_result = $conn->query($images_sql);
$product_images = [];
if ($images_result && $images_result->num_rows > 0) {
    while ($row = $images_result->fetch_assoc()) {
        $product_images[] = $row;
    }
}

// Proses form update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    // Sanitasi input
    $name = $conn->real_escape_string($_POST['name']);
    $category_id = (int)$_POST['category_id'];
    $short_description = $conn->real_escape_string($_POST['short_description'] ?? '');
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $price = (float)$_POST['price'];
    $sale_price = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : 'NULL';
    $quantity = (int)$_POST['quantity'];
    $sku = $conn->real_escape_string($_POST['sku'] ?? '');
    $status = $conn->real_escape_string($_POST['status']);
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Memastikan slug bersih dan unik
    $original_slug = strtolower(str_replace(' ', '-', $name));
    $slug = $original_slug;
    $slug_count = 0;
    
    $slug_check_sql = "SELECT id FROM products WHERE slug = '$slug' AND id != $product_id";
    $slug_check_result = $conn->query($slug_check_sql);
    
    while ($slug_check_result && $slug_check_result->num_rows > 0) {
        $slug_count++;
        $slug = $original_slug . '-' . $slug_count;
        $slug_check_sql = "SELECT id FROM products WHERE slug = '$slug' AND id != $product_id";
        $slug_check_result = $conn->query($slug_check_sql);
    }
    
    // Update produk
    $update_sql = "UPDATE products SET 
                  name = '$name',
                  category_id = $category_id,
                  slug = '$slug',
                  short_description = '$short_description',
                  description = '$description',
                  price = $price,
                  sale_price = " . ($sale_price === 'NULL' ? "NULL" : $sale_price) . ",
                  quantity = $quantity,
                  sku = '$sku',
                  status = '$status',
                  featured = $featured,
                  updated_at = NOW()
                  WHERE id = $product_id AND vendor_id = $vendor_id";
    
    if ($conn->query($update_sql)) {
        // Proses upload gambar
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $upload_dir = '../assets/img/products/';
            
            // Buat direktori jika belum ada
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] === 0) {
                    $file_name = $_FILES['images']['name'][$key];
                    $file_size = $_FILES['images']['size'][$key];
                    $file_type = $_FILES['images']['type'][$key];
                    $file_tmp = $_FILES['images']['tmp_name'][$key];
                    
                    // Validasi file
                    if ($file_size > $max_size) {
                        $upload_errors[] = "File '$file_name' terlalu besar. Maksimal 2MB.";
                        continue;
                    }
                    
                    if (!in_array($file_type, $allowed_types)) {
                        $upload_errors[] = "File '$file_name' tidak diizinkan. Hanya JPG, PNG, dan GIF yang diperbolehkan.";
                        continue;
                    }
                    
                    // Buat nama file unik
                    $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                    $unique_name = 'product_' . $product_id . '_' . uniqid() . '.' . $file_ext;
                    $upload_path = $upload_dir . $unique_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        // Cek jika ini gambar utama
                        $is_main = isset($_POST['main_image']) && $_POST['main_image'] == $key ? 1 : 0;
                        
                        // Jika ini gambar utama, ubah semua gambar lain menjadi bukan utama
                        if ($is_main) {
                            $conn->query("UPDATE product_images SET is_main = 0 WHERE product_id = $product_id");
                        }
                        
                        // Simpan ke database
                        $insert_image_sql = "INSERT INTO product_images (product_id, image, is_main) 
                                           VALUES ($product_id, '$unique_name', $is_main)";
                        $conn->query($insert_image_sql);
                    } else {
                        $upload_errors[] = "Gagal mengupload file '$file_name'.";
                    }
                }
            }
        }
        
        // Update gambar utama jika dipilih dari gambar yang sudah ada
        if (isset($_POST['existing_main_image']) && !empty($_POST['existing_main_image'])) {
            $main_image_id = (int)$_POST['existing_main_image'];
            $conn->query("UPDATE product_images SET is_main = 0 WHERE product_id = $product_id");
            $conn->query("UPDATE product_images SET is_main = 1 WHERE id = $main_image_id AND product_id = $product_id");
        }
        
        // Hapus gambar jika ada yang dipilih untuk dihapus
        if (isset($_POST['delete_images']) && !empty($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $image_id) {
                $image_id = (int)$image_id;
                
                // Dapatkan nama file gambar
                $image_sql = "SELECT image FROM product_images WHERE id = $image_id AND product_id = $product_id";
                $image_result = $conn->query($image_sql);
                
                if ($image_result && $image_result->num_rows > 0) {
                    $image_data = $image_result->fetch_assoc();
                    $image_file = $upload_dir . $image_data['image'];
                    
                    // Hapus file fisik
                    if (file_exists($image_file)) {
                        unlink($image_file);
                    }
                    
                    // Hapus dari database
                    $conn->query("DELETE FROM product_images WHERE id = $image_id AND product_id = $product_id");
                }
            }
        }
        
        $_SESSION['success_message'] = "Produk berhasil diperbarui.";
        header('Location: ' . VENDOR_URL . '/products.php');
        exit;
    } else {
        $error_message = "Gagal memperbarui produk: " . $conn->error;
    }
}

// Current time untuk display
$current_datetime = date('Y-m-d H:i:s');

include '../includes/vendor-header.php';
?>

<div class="container-fluid py-4">
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

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Edit Produk</h1>
        <div>
            <a href="<?= VENDOR_URL ?>/products.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar Produk
            </a>
            <a href="<?= SITE_URL ?>/product.php?id=<?= $product_id ?>" class="btn btn-info" target="_blank">
                <i class="fas fa-eye me-1"></i> Lihat Produk
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>
    
    <?php if (isset($upload_errors) && !empty($upload_errors)): ?>
        <div class="alert alert-warning">
            <h6 class="alert-heading">Ada masalah dengan beberapa gambar:</h6>
            <ul class="mb-0">
                <?php foreach ($upload_errors as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Informasi Produk</h6>
        </div>
        <div class="card-body">
            <form method="post" action="" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nama Produk <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category_id" class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" <?= $product['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="price" class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" value="<?= $product['price'] ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sale_price" class="form-label">Harga Diskon (Rp)</label>
                            <input type="number" class="form-control" id="sale_price" name="sale_price" min="0" step="0.01" value="<?= $product['sale_price'] ?? '' ?>">
                            <small class="text-muted">Kosongkan jika tidak ada diskon</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Stok <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="0" value="<?= $product['quantity'] ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sku" class="form-label">SKU</label>
                            <input type="text" class="form-control" id="sku" name="sku" value="<?= htmlspecialchars($product['sku'] ?? '') ?>">
                            <small class="text-muted">Nomor identifikasi unik untuk produk Anda (opsional)</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="short_description" class="form-label">Deskripsi Singkat</label>
                            <textarea class="form-control" id="short_description" name="short_description" rows="2"><?= htmlspecialchars($product['short_description'] ?? '') ?></textarea>
                            <small class="text-muted">Maksimal 255 karakter</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Deskripsi Lengkap</label>
                            <textarea class="form-control" id="description" name="description" rows="7"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                                <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                                <option value="draft" <?= $product['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="featured" name="featured" value="1" <?= $product['featured'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="featured">Produk Unggulan</label>
                            <small class="d-block text-muted">Produk unggulan akan ditampilkan di halaman utama</small>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <h5 class="mb-3">Gambar Produk</h5>
                
                <?php if (!empty($product_images)): ?>
                    <div class="mb-4">
                        <label class="form-label">Gambar yang Ada</label>
                        <div class="row">
                            <?php foreach ($product_images as $image): ?>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="card h-100">
                                        <img src="<?= SITE_URL ?>/assets/img/products/<?= $image['image'] ?>" 
                                             class="card-img-top" alt="<?= $product['name'] ?>"
                                             style="height: 150px; object-fit: cover;">
                                        <div class="card-body">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" name="existing_main_image" 
                                                      value="<?= $image['id'] ?>" id="main_image_<?= $image['id'] ?>"
                                                      <?= $image['is_main'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="main_image_<?= $image['id'] ?>">
                                                    Gambar Utama
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="delete_images[]" 
                                                      value="<?= $image['id'] ?>" id="delete_image_<?= $image['id'] ?>">
                                                <label class="form-check-label" for="delete_image_<?= $image['id'] ?>">
                                                    Hapus Gambar
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <label for="images" class="form-label">Tambah Gambar Baru</label>
                    <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
                    <small class="text-muted">Anda dapat memilih beberapa gambar sekaligus. Maks 2MB per gambar. Format: JPG, PNG, GIF</small>
                </div>
                
                <div id="image-preview-container" class="row mt-3 d-none">
                    <!-- Preview gambar akan ditampilkan di sini -->
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="set_new_main" name="set_new_main" value="1">
                        <label class="form-check-label" for="set_new_main">
                            Jadikan gambar pertama dari upload baru sebagai gambar utama
                        </label>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" name="update_product" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Simpan Perubahan
                    </button>
                    <a href="<?= VENDOR_URL ?>/products.php" class="btn btn-secondary">
                        Batal
                    </a>
                </div>
            </form>
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

// Fungsi untuk preview gambar
document.addEventListener('DOMContentLoaded', function() {
    const imageInput = document.getElementById('images');
    const previewContainer = document.getElementById('image-preview-container');
    const mainImageCheckbox = document.getElementById('set_new_main');
    
    if (imageInput) {
        imageInput.addEventListener('change', function() {
            // Kosongkan container preview
            previewContainer.innerHTML = '';
            
            if (this.files && this.files.length > 0) {
                previewContainer.classList.remove('d-none');
                
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    
                    if (!file.type.match('image.*')) {
                        continue;
                    }
                    
                    const reader = new FileReader();
                    const colDiv = document.createElement('div');
                    colDiv.className = 'col-md-3 col-sm-6 mb-3';
                    
                    const cardDiv = document.createElement('div');
                    cardDiv.className = 'card h-100';
                    
                    const imagePreview = document.createElement('img');
                    imagePreview.className = 'card-img-top';
                    imagePreview.style.height = '150px';
                    imagePreview.style.objectFit = 'cover';
                    
                    const cardBody = document.createElement('div');
                    cardBody.className = 'card-body';
                    
                    const radioDiv = document.createElement('div');
                    radioDiv.className = 'form-check';
                    
                    const radioInput = document.createElement('input');
                    radioInput.type = 'radio';
                    radioInput.className = 'form-check-input';
                    radioInput.name = 'main_image';
                    radioInput.value = i;
                    radioInput.id = `new_main_image_${i}`;
                    if (i === 0) radioInput.checked = true;
                    
                    const radioLabel = document.createElement('label');
                    radioLabel.className = 'form-check-label';
                    radioLabel.htmlFor = `new_main_image_${i}`;
                    radioLabel.textContent = 'Jadikan Gambar Utama';
                    
                    radioDiv.appendChild(radioInput);
                    radioDiv.appendChild(radioLabel);
                    cardBody.appendChild(radioDiv);
                    
                    cardDiv.appendChild(imagePreview);
                    cardDiv.appendChild(cardBody);
                    
                    colDiv.appendChild(cardDiv);
                    previewContainer.appendChild(colDiv);
                    
                    reader.onload = (function(imgElement) {
                        return function(e) {
                            imgElement.src = e.target.result;
                        };
                    })(imagePreview);
                    
                    reader.readAsDataURL(file);
                }
                
                // Jika ada gambar yang dipilih, cek checkbox secara otomatis
                if (this.files.length > 0) {
                    mainImageCheckbox.checked = true;
                }
            } else {
                previewContainer.classList.add('d-none');
                mainImageCheckbox.checked = false;
            }
        });
    }
});
</script>

<?php include '../includes/vendor-footer.php'; ?>