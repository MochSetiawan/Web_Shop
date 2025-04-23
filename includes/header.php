<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME ?></title>
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?= SITE_URL ?>/assets/img/favicon.ico" type="image/x-icon">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <?php if (isset($extraCSS)): ?>
        <?= $extraCSS ?>
    <?php endif; ?>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar bg-dark text-white py-2">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <ul class="list-inline mb-0">
                        <li class="list-inline-item me-3"><i class="fas fa-phone me-2"></i> +62 895 400 789 815</li>
                        <li class="list-inline-item"><i class="fas fa-envelope me-2"></i> Krakenstore@gmail.com</li>
                    </ul>
                </div>
                <div class="col-md-6 text-md-end">
                    <ul class="list-inline mb-0">
                        <?php if (isLoggedIn()): ?>
                            <li class="list-inline-item me-3">
                                <a href="<?= SITE_URL ?>/profile.php" class="text-white text-decoration-none">
                                    <i class="fas fa-user me-1"></i> <?= $_SESSION['username'] ?>
                                </a>
                            </li>
                            <?php if (isVendor()): ?>
                                <li class="list-inline-item me-3">
                                    <a href="<?= VENDOR_URL ?>/dashboard.php" class="text-white text-decoration-none">
                                        <i class="fas fa-store me-1"></i> Vendor Dashboard
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (isAdmin()): ?>
                                <li class="list-inline-item me-3">
                                    <a href="<?= ADMIN_URL ?>/dashboard.php" class="text-white text-decoration-none">
                                        <i class="fas fa-lock me-1"></i> Admin Panel
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li class="list-inline-item">
                                <a href="<?= SITE_URL ?>/logout.php" class="text-white text-decoration-none">
                                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="list-inline-item me-3">
                                <a href="<?= SITE_URL ?>/login.php" class="text-white text-decoration-none">
                                    <i class="fas fa-sign-in-alt me-1"></i> Login
                                </a>
                            </li>
                            <li class="list-inline-item">
                                <a href="<?= SITE_URL ?>/register.php" class="text-white text-decoration-none">
                                    <i class="fas fa-user-plus me-1"></i> Register
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header class="header bg-white py-3 shadow-sm">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-3 col-6">
                    <a href="<?= SITE_URL ?>" class="logo">
                        <h1 class="mb-0"><span class="text-primary">Kraken</span>Store</h1>
                    </a>
                </div>
                <div class="col-lg-5 d-none d-lg-block">
                    <form action="<?= SITE_URL ?>/shop.php" method="GET" class="search-form">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-4 col-6 text-end">
                    <div class="header-icons">
                        <a href="<?= SITE_URL ?>/wishlist.php" class="header-icon me-3">
                            <i class="far fa-heart"></i>
                        </a>
                        <a href="<?= SITE_URL ?>/cart.php" class="header-icon position-relative">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if (isLoggedIn() && getCartCount() > 0): ?>
                                <span class="badge bg-primary position-absolute top-0 start-100 translate-middle rounded-pill">
                                    <?= getCartCount() ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <button class="btn d-lg-none ms-3 mobile-menu-toggle">
                            <i class="fas fa-bars"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="main-nav bg-primary">
        <div class="container">
            <ul class="nav-list d-none d-lg-flex">
                <li class="nav-item"><a href="<?= SITE_URL ?>" class="nav-link">Home</a></li>
                <li class="nav-item dropdown">
                    <a href="<?= SITE_URL ?>/shop.php" class="nav-link dropdown-toggle">Shop</a>
                    <ul class="dropdown-menu">
                        <?php foreach (getAllCategories() as $category): ?>
                            <li>
                                <a href="<?= SITE_URL ?>/shop.php?category=<?= $category['id'] ?>" class="dropdown-item">
                                    <?= $category['name'] ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <li class="nav-item"><a href="<?= SITE_URL ?>/about.php" class="nav-link">About Us</a></li>
                <li class="nav-item"><a href="<?= SITE_URL ?>/contact.php" class="nav-link">Contact</a></li>
                <?php if (!isVendor() && !isAdmin()): ?>
                    <li class="nav-item">
                        <a href="<?= SITE_URL ?>/vendor-register.php" class="nav-link">Become a Seller</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>


    <!-- Mobile Menu -->
    <div class="mobile-menu d-lg-none">
        <div class="close-menu">
            <i class="fas fa-times"></i>
        </div>
        <ul class="mobile-nav-list">
            <li class="mobile-nav-item"><a href="<?= SITE_URL ?>" class="mobile-nav-link">Home</a></li>
            <li class="mobile-nav-item has-submenu">
                <a href="<?= SITE_URL ?>/shop.php" class="mobile-nav-link">Shop</a>
                <ul class="submenu">
                    <?php foreach (getAllCategories() as $category): ?>
                        <li>
                            <a href="<?= SITE_URL ?>/shop.php?category=<?= $category['id'] ?>" class="submenu-link">
                                <?= $category['name'] ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </li>
            <li class="mobile-nav-item"><a href="<?= SITE_URL ?>/about.php" class="mobile-nav-link">About Us</a></li>
            <li class="mobile-nav-item"><a href="<?= SITE_URL ?>/contact.php" class="mobile-nav-link">Contact</a></li>
            <?php if (!isVendor() && !isAdmin()): ?>
                <li class="mobile-nav-item">
                    <a href="<?= SITE_URL ?>/vendor-register.php" class="mobile-nav-link">Become a Seller</a>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <?= displayMessages() ?>