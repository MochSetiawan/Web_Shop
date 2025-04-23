<?php
requireVendor();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME . ' Vendor' : SITE_NAME . ' Vendor' ?></title>
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?= SITE_URL ?>/assets/img/favicon.ico" type="image/x-icon">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/vendor.css">
    <?php if (isset($extraCSS)): ?>
        <?= $extraCSS ?>
    <?php endif; ?>
</head>
<body>
    <!-- Get vendor data -->
    <?php
    $vendor = getVendorByUserId($_SESSION['user_id']);
    ?>

    <div class="vendor-wrapper">
        <!-- Sidebar -->
        <aside class="vendor-sidebar">
            <div class="sidebar-header">
                <h3 class="mb-0"><span class="text-primary">Kraken</span>Shop</h3>
                <p class="text-muted small mb-0">Vendor Panel</p>
            </div>
            <div class="sidebar-profile">
                <img src="<?= SITE_URL ?>/assets/img/<?= $vendor['logo'] ?? DEFAULT_IMG ?>" alt="Vendor" class="vendor-avatar">
                <div class="vendor-info">
                    <p class="vendor-name"><?= $vendor['shop_name'] ?></p>
                    <p class="vendor-status <?= $vendor['status'] ?>"><?= ucfirst($vendor['status']) ?></p>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul class="nav-list">
                    <li class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                        <a href="<?= VENDOR_URL ?>/dashboard.php" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item <?= $currentPage === 'products' ? 'active' : '' ?>">
                        <a href="<?= VENDOR_URL ?>/products.php" class="nav-link">
                            <i class="fas fa-box"></i> Products
                        </a>
                    </li>
                    <li class="nav-item <?= $currentPage === 'orders' ? 'active' : '' ?>">
                        <a href="<?= VENDOR_URL ?>/orders.php" class="nav-link">
                            <i class="fas fa-shopping-bag"></i> Orders
                        </a>
                    </li>
                    <li class="nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
                        <a href="<?= VENDOR_URL ?>/profile.php" class="nav-link">
                            <i class="fas fa-store"></i> Shop Profile
                        </a>
                    </li>
                    <li class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
                        <a href="<?= VENDOR_URL ?>/settings.php" class="nav-link">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item <?= $currentPage === 'report-bug' ? 'active' : '' ?>">
                        <a href="<?= VENDOR_URL ?>/report-bug.php" class="nav-link">
                            <i class="fas fa-triangle-exclamation"></i> Bug Report
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= SITE_URL ?>/logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="vendor-main">
            <!-- Top Bar -->
            <header class="vendor-header">
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="header-actions">
                    <a href="<?= SITE_URL ?>" class="btn btn-outline-primary btn-sm me-2" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Site
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-link dropdown-toggle" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <span class="badge bg-danger">2</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#">New order received</a></li>
                            <li><a class="dropdown-item" href="#">Product almost out of stock</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="#">View all notifications</a></li>
                        </ul>
                    </div>
                    <div class="dropdown ms-2">
                        <button class="btn btn-link dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= SITE_URL ?>/assets/img/<?= $_SESSION['profile_image'] ?? DEFAULT_IMG ?>" alt="Vendor" class="header-avatar">
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?= VENDOR_URL ?>/profile.php">Shop Profile</a></li>
                            <li><a class="dropdown-item" href="<?= VENDOR_URL ?>/settings.php">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= SITE_URL ?>/logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="vendor-content">
                <?= displayMessages() ?>