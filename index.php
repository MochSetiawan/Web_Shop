<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Home';

// Get featured products
$featuredProducts = getFeaturedProducts(8);

// Get latest products
$latestProducts = getLatestProducts(8);

// Get all categories
$categories = getAllCategories();

include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="swiper hero-slider">
        <div class="swiper-wrapper">
            <div class="swiper-slide hero-slide" style="background-image: url('assets/img/categories/shopping-7769500_1280.jpg');">
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <h1 class="hero-title">Discover Amazing Products</h1>
                    <p class="hero-subtitle">Shop the latest trends with amazing deals and offers</p>
                    <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary hero-btn">Shop Now</a>
                </div>
            </div>
            <div class="swiper-slide hero-slide" style="background-image: url('assets/img/categories/jualan.jpg');">
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <h1 class="hero-title">New Season Arrivals</h1>
                    <p class="hero-subtitle">Check out our fresh collection of products from top vendors</p>
                    <a href="<?= SITE_URL ?>/shop.php?new=1" class="btn btn-primary hero-btn">Explore</a>
                </div>
            </div>
            <div class="swiper-slide hero-slide" style="background-image: url('assets/img/categories/seller.webp');">
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <h1 class="hero-title">Become a Seller Today</h1>
                    <p class="hero-subtitle">Start your online business with KrakenStore and reach millions of customers</p>
                    <a href="<?= SITE_URL ?>/vendor-register.php" class="btn btn-primary hero-btn">Start Selling</a>
                </div>
            </div>
        </div>
        <div class="swiper-pagination"></div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h3 class="feature-title">Free Shipping</h3>
                    <p>Free shipping on all orders over Rp 500.000</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-undo"></i>
                    </div>
                    <h3 class="feature-title">Easy Returns</h3>
                    <p>30 days return policy for eligible items</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="feature-title">Secure Payment</h3>
                    <p>Multiple secure payment options</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3 class="feature-title">24/7 Support</h3>
                    <p>Get help whenever you need it</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="categories-section">
    <div class="container">
        <div class="section-title">
            <h2>Shop by Category</h2>
            <p>Browse our wide selection of products across different categories</p>
        </div>
        <div class="row">
            <?php 
            $delay = 0;
            foreach ($categories as $category): 
                $delay += 0.1;
            ?>
                <div class="col-lg-4 col-md-6">
                    <div class="category-card" style="--delay: <?= $delay ?>s;">
                        <div class="category-image">
                            <img src="<?= SITE_URL ?>/assets/img/categories/<?= $category['image'] ?: 'default.jpg' ?>" alt="<?= $category['name'] ?>">
                        </div>
                        <div class="category-content">
                            <h3><?= $category['name'] ?></h3>
                            <a href="<?= SITE_URL ?>/shop.php?category=<?= $category['id'] ?>" class="btn btn-light btn-sm">Shop Now</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Products Section -->
<section class="products-section">
    <div class="container">
        <div class="section-title">
            <h2>Featured Products</h2>
            <p>Handpicked products from our top sellers</p>
        </div>
        <div class="swiper product-slider">
            <div class="swiper-wrapper">
                <?php foreach ($featuredProducts as $product): ?>
                    <div class="swiper-slide">
                        <div class="product-card">
                            <div class="product-image">
                                <a href="<?= SITE_URL ?>/product.php?id=<?= $product['id'] ?>">
                                    <img src="<?= SITE_URL ?>/assets/img/products/<?= $product['image'] ?: 'default.jpg' ?>" alt="<?= $product['name'] ?>">
                                </a>
                                <?php if ($product['sale_price']): ?>
                                    <div class="product-tag">Sale</div>
                                <?php endif; ?>
                                <div class="product-actions">
                                    <a href="#" class="add-to-cart" data-id="<?= $product['id'] ?>"><i class="fas fa-shopping-cart"></i></a>
                                    <a href="#" class="quick-view" data-id="<?= $product['id'] ?>"><i class="fas fa-eye"></i></a>
                                    <a href="#" class="add-to-wishlist" data-id="<?= $product['id'] ?>"><i class="far fa-heart"></i></a>
                                </div>
                            </div>
                            <div class="product-info">
                                <h4 class="product-title">
                                    <a href="<?= SITE_URL ?>/product.php?id=<?= $product['id'] ?>"><?= $product['name'] ?></a>
                                </h4>
                                <div class="product-price">
                                    <?php if ($product['sale_price']): ?>
                                        <span class="old-price"><?= formatPrice($product['price']) ?></span>
                                        <span class="current-price"><?= formatPrice($product['sale_price']) ?></span>
                                    <?php else: ?>
                                        <span class="current-price"><?= formatPrice($product['price']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="rating">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star-half-alt"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-pagination"></div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
    </div>
</section>

<!-- Banner Section -->
<section class="banner-section">
    <div class="container">
        <div class="banner">
            <img src="<?= SITE_URL ?>/assets/img/categories/sale.avif" alt="Special Offer" class="banner-image">
            <div class="banner-overlay"></div>
            <div class="banner-content">
                <h2 class="banner-title">Special Offer</h2>
                <p class="banner-text">Get up to 50% off on selected items. Limited time offer!</p>
                <a href="<?= SITE_URL ?>/shop.php?sale=1" class="btn btn-primary">Shop Now</a>
            </div>
        </div>
    </div>
</section>

<!-- Latest Products Section -->
<section class="products-section">
    <div class="container">
        <div class="section-title">
            <h2>Latest Products</h2>
            <p>Check out our newest arrivals</p>
        </div>
        <div class="row">
            <?php foreach ($latestProducts as $product): ?>
                <div class="col-lg-3 col-md-4 col-6">
                    <div class="product-card">
                        <div class="product-image">
                            <a href="<?= SITE_URL ?>/product.php?id=<?= $product['id'] ?>">
                                <img src="<?= SITE_URL ?>/assets/img/products/<?= $product['image'] ?: 'default.jpg' ?>" alt="<?= $product['name'] ?>">
                            </a>
                            <?php if ($product['sale_price']): ?>
                                <div class="product-tag">Sale</div>
                            <?php endif; ?>
                            <div class="product-actions">
                                <a href="#" class="add-to-cart" data-id="<?= $product['id'] ?>"><i class="fas fa-shopping-cart"></i></a>
                                <a href="#" class="quick-view" data-id="<?= $product['id'] ?>"><i class="fas fa-eye"></i></a>
                                <a href="#" class="add-to-wishlist" data-id="<?= $product['id'] ?>"><i class="far fa-heart"></i></a>
                            </div>
                        </div>
                        <div class="product-info">
                            <h4 class="product-title">
                                <a href="<?= SITE_URL ?>/product.php?id=<?= $product['id'] ?>"><?= $product['name'] ?></a>
                            </h4>
                            <div class="product-price">
                                <?php if ($product['sale_price']): ?>
                                    <span class="old-price"><?= formatPrice($product['price']) ?></span>
                                    <span class="current-price"><?= formatPrice($product['sale_price']) ?></span>
                                <?php else: ?>
                                    <span class="current-price"><?= formatPrice($product['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?= SITE_URL ?>/shop.php" class="btn btn-outline-primary">View All Products</a>
        </div>
    </div>
</section>

<!-- Quick View Modal -->
<div class="modal fade" id="quickViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-body">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>