</main>

<!-- Newsletter -->
<section class="newsletter-section py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 text-center">
                <h3>Subscribe to Our Newsletter</h3>
                <p class="mb-4">Get updates on new products and special offers</p>
                <form class="newsletter-form" action="#" method="POST">
                    <div class="input-group">
                        <input type="email" class="form-control" placeholder="Your email address" required>
                        <button type="submit" class="btn btn-primary">Subscribe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer bg-dark text-white pt-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <h5 class="footer-heading">About ShopVerse</h5>
                <p>ShopVerse is a multi-vendor e-commerce platform offering a wide range of products from various sellers.</p>
                <div class="social-icons">
                    <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <h5 class="footer-heading">Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="<?= SITE_URL ?>">Home</a></li>
                    <li><a href="<?= SITE_URL ?>/shop.php">Shop</a></li>
                    <li><a href="<?= SITE_URL ?>/about.php">About Us</a></li>
                    <li><a href="<?= SITE_URL ?>/contact.php">Contact</a></li>
                    <li><a href="<?= SITE_URL ?>/vendor-register.php">Become a Seller</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <h5 class="footer-heading">Customer Service</h5>
                <ul class="footer-links">
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Shipping Policy</a></li>
                    <li><a href="#">Return Policy</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms & Conditions</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h5 class="footer-heading">Contact Us</h5>
                <address>
                    <p><i class="fas fa-map-marker-alt me-2"></i> 123 E-Commerce Street, Jakarta, Indonesia</p>
                    <p><i class="fas fa-phone me-2"></i> +62 123 456 7890</p>
                    <p><i class="fas fa-envelope me-2"></i> info@shopverse.com</p>
                </address>
            </div>
        </div>
    </div>
    <div class="footer-bottom py-3 text-center">
        <div class="container">
            <p class="mb-0">&copy; <?= date('Y') ?> ShopVerse. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- Back to Top Button -->
<a href="#" class="back-to-top" id="backToTop">
    <i class="fas fa-arrow-up"></i>
</a>

<!-- Scripts -->
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<?php if (isset($extraJS)): ?>
    <?= $extraJS ?>
<?php endif; ?>
</body>
</html>