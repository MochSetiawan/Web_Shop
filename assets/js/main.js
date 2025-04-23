// Main JavaScript file for ShopVerse

// Document Ready
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Mobile Menu Toggle
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const mobileMenu = document.querySelector('.mobile-menu');
    const closeMenu = document.querySelector('.close-menu');
    const mobileOverlay = document.createElement('div');
    mobileOverlay.className = 'mobile-overlay';

    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            mobileMenu.classList.add('show');
            document.body.appendChild(mobileOverlay);
            document.body.style.overflow = 'hidden';
        });

        if (closeMenu) {
            closeMenu.addEventListener('click', function() {
                mobileMenu.classList.remove('show');
                if (document.body.contains(mobileOverlay)) {
                    document.body.removeChild(mobileOverlay);
                }
                document.body.style.overflow = '';
            });
        }

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('mobile-overlay')) {
                mobileMenu.classList.remove('show');
                if (document.body.contains(mobileOverlay)) {
                    document.body.removeChild(mobileOverlay);
                }
                document.body.style.overflow = '';
            }
        });
    }

    // Mobile Submenu Toggle
    const hasSubmenu = document.querySelectorAll('.has-submenu');
    
    if (hasSubmenu) {
        hasSubmenu.forEach(function(item) {
            item.addEventListener('click', function(e) {
                if (e.target === this || e.target === this.querySelector('.mobile-nav-link')) {
                    e.preventDefault();
                    this.classList.toggle('active');
                }
            });
        });
    }

    // Back to Top Button
    const backToTopButton = document.getElementById('backToTop');
    
    if (backToTopButton) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.add('show');
            } else {
                backToTopButton.classList.remove('show');
            }
        });

        backToTopButton.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // Initialize Hero Slider
    const heroSection = document.querySelector('.hero-section');
    
    if (heroSection) {
        const swiper = new Swiper('.hero-slider', {
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            effect: 'fade',
            fadeEffect: {
                crossFade: true
            },
        });
    }

    // Initialize Product Sliders
    const productSliders = document.querySelectorAll('.product-slider');
    
    if (productSliders.length > 0) {
        productSliders.forEach(function(slider) {
            new Swiper(slider, {
                slidesPerView: 1,
                spaceBetween: 20,
                pagination: {
                    el: slider.querySelector('.swiper-pagination'),
                    clickable: true,
                },
                navigation: {
                    nextEl: slider.querySelector('.swiper-button-next'),
                    prevEl: slider.querySelector('.swiper-button-prev'),
                },
                breakpoints: {
                    576: {
                        slidesPerView: 2,
                    },
                    768: {
                        slidesPerView: 3,
                    },
                    992: {
                        slidesPerView: 4,
                    }
                }
            });
        });
    }

    // Product Quantity Selector
    const quantitySelectors = document.querySelectorAll('.quantity-selector');
    
    if (quantitySelectors.length > 0) {
        quantitySelectors.forEach(function(selector) {
            const minus = selector.querySelector('.quantity-minus');
            const plus = selector.querySelector('.quantity-plus');
            const input = selector.querySelector('.quantity-input');
            
            minus.addEventListener('click', function() {
                let value = parseInt(input.value);
                if (value > 1) {
                    value--;
                    input.value = value;
                }
            });
            
            plus.addEventListener('click', function() {
                let value = parseInt(input.value);
                value++;
                input.value = value;
            });
        });
    }

    // Add to Cart Button
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    
    if (addToCartButtons.length > 0) {
        addToCartButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const productId = this.getAttribute('data-id');
                
                // AJAX request to add product to cart
                fetch(`${SITE_URL}/ajax/add-to-cart.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Success', data.message, 'success');
                        
                        // Update cart count
                        const cartCountElements = document.querySelectorAll('.cart-count');
                        if (cartCountElements.length > 0) {
                            cartCountElements.forEach(function(element) {
                                element.textContent = data.cart_count;
                            });
                        }
                    } else {
                        showToast('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error', 'Something went wrong. Please try again.', 'error');
                });
            });
        });
    }

    // Quick View
    const quickViewButtons = document.querySelectorAll('.quick-view');
    const quickViewModal = document.getElementById('quickViewModal');
    
    if (quickViewButtons.length > 0 && quickViewModal) {
        quickViewButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const productId = this.getAttribute('data-id');
                
                // Show loading
                const modalBody = quickViewModal.querySelector('.modal-body');
                modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                
                // Show modal
                const modal = new bootstrap.Modal(quickViewModal);
                modal.show();
                
                // AJAX request to get product details
                fetch(`${SITE_URL}/ajax/get-product.php?id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const product = data.product;
                        
                        // Update modal content
                        modalBody.innerHTML = `
                            <div class="row g-0">
                                <div class="col-md-6">
                                    <div class="quick-view-image">
                                        <img src="${SITE_URL}/assets/img/products/${product.image}" alt="${product.name}" class="img-fluid">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="quick-view-content p-4">
                                        <h2 class="product-title mb-3">${product.name}</h2>
                                        <div class="product-price mb-3">
                                            ${product.sale_price ? `
                                                <span class="current-price">${formatPrice(product.sale_price)}</span>
                                                <span class="old-price">${formatPrice(product.price)}</span>
                                            ` : `
                                                <span class="current-price">${formatPrice(product.price)}</span>
                                            `}
                                        </div>
                                        <div class="rating mb-3">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star-half-alt"></i>
                                            <span class="ms-2">(${product.review_count} reviews)</span>
                                        </div>
                                        <p class="product-description mb-4">${product.description}</p>
                                        <div class="cart-options mb-4">
                                            <div class="quantity-selector d-flex align-items-center mb-3">
                                                <label class="me-3">Quantity:</label>
                                                <div class="input-group" style="width: 130px;">
                                                    <button class="btn btn-outline-secondary quantity-minus" type="button">-</button>
                                                    <input type="text" class="form-control text-center quantity-input" value="1" min="1">
                                                    <button class="btn btn-outline-secondary quantity-plus" type="button">+</button>
                                                </div>
                                            </div>
                                            <button class="btn btn-primary add-to-cart w-100" data-id="${product.id}">
                                                <i class="fas fa-shopping-cart me-2"></i> Add to Cart
                                            </button>
                                        </div>
                                        <div class="product-meta">
                                            <p><strong>Availability:</strong> <span class="stock-status">${product.quantity > 0 ? 'In Stock' : 'Out of Stock'}</span></p>
                                            <p><strong>Category:</strong> <span class="product-category">${product.category_name}</span></p>
                                            <p><strong>Vendor:</strong> <span class="product-vendor">${product.shop_name}</span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        // Reinitialize quantity selector
                        const selector = modalBody.querySelector('.quantity-selector');
                        const minus = selector.querySelector('.quantity-minus');
                        const plus = selector.querySelector('.quantity-plus');
                        const input = selector.querySelector('.quantity-input');
                        
                        minus.addEventListener('click', function() {
                            let value = parseInt(input.value);
                            if (value > 1) {
                                value--;
                                input.value = value;
                            }
                        });
                        
                        plus.addEventListener('click', function() {
                            let value = parseInt(input.value);
                            value++;
                            input.value = value;
                        });
                        
                        // Reinitialize add to cart button
                        const addToCartButton = modalBody.querySelector('.add-to-cart');
                        
                        addToCartButton.addEventListener('click', function(e) {
                            e.preventDefault();
                            const productId = this.getAttribute('data-id');
                            const quantity = input.value;
                            
                            // AJAX request to add product to cart
                            fetch(`${SITE_URL}/ajax/add-to-cart.php`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `product_id=${productId}&quantity=${quantity}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    showToast('Success', data.message, 'success');
                                    
                                    // Update cart count
                                    const cartCountElements = document.querySelectorAll('.cart-count');
                                    if (cartCountElements.length > 0) {
                                        cartCountElements.forEach(function(element) {
                                            element.textContent = data.cart_count;
                                        });
                                    }
                                    
                                    // Close modal
                                    modal.hide();
                                } else {
                                    showToast('Error', data.message, 'error');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                showToast('Error', 'Something went wrong. Please try again.', 'error');
                            });
                        });
                    } else {
                        modalBody.innerHTML = `<div class="text-center py-5"><div class="alert alert-danger">${data.message}</div></div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = '<div class="text-center py-5"><div class="alert alert-danger">Something went wrong. Please try again.</div></div>';
                });
            });
        });
    }

    // Format price
    function formatPrice(price) {
        return 'Rp ' + parseInt(price).toLocaleString('id-ID');
    }

    // Show toast
    function showToast(title, message, type) {
        const toastContainer = document.getElementById('toast-container');
        
        if (!toastContainer) {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '1070';
            document.body.appendChild(container);
        }
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}:</strong> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        document.getElementById('toast-container').appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast, {
            autohide: true,
            delay: 3000
        });
        
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', function() {
            this.remove();
        });
    }
});