// Shop Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Price Range Slider
    const priceSlider = document.getElementById('price-slider');
    const minPriceInput = document.getElementById('min-price');
    const maxPriceInput = document.getElementById('max-price');
    const filterPriceBtn = document.getElementById('filter-price-btn');
    
    if (priceSlider && minPriceInput && maxPriceInput) {
        noUiSlider.create(priceSlider, {
            start: [0, 10000000], // Rp 0 - 10,000,000
            connect: true,
            step: 50000,
            range: {
                'min': 0,
                'max': 10000000
            },
            format: {
                to: function(value) {
                    return Math.round(value);
                },
                from: function(value) {
                    return Math.round(value);
                }
            }
        });
        
        priceSlider.noUiSlider.on('update', function(values, handle) {
            const value = values[handle];
            
            if (handle === 0) {
                minPriceInput.value = formatPrice(value);
            } else {
                maxPriceInput.value = formatPrice(value);
            }
        });
        
        if (filterPriceBtn) {
            filterPriceBtn.addEventListener('click', function() {
                const values = priceSlider.noUiSlider.get();
                
                // Get current URL and parameters
                const url = new URL(window.location.href);
                const params = url.searchParams;
                
                // Update or add min_price and max_price parameters
                params.set('min_price', values[0]);
                params.set('max_price', values[1]);
                
                // Redirect to updated URL
                window.location.href = url.toString();
            });
        }
    }
    
    // Sort Select
    const sortSelect = document.getElementById('sort-select');
    
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            const sort = this.value;
            
            // Get current URL and parameters
            const url = new URL(window.location.href);
            const params = url.searchParams;
            
            // Update or add sort parameter
            params.set('sort', sort);
            
            // Redirect to updated URL
            window.location.href = url.toString();
        });
    }
    
    // View Toggle
    const gridViewBtn = document.querySelector('.view-grid');
    const listViewBtn = document.querySelector('.view-list');
    const productsContainer = document.getElementById('products-container');
    
    if (gridViewBtn && listViewBtn && productsContainer) {
        gridViewBtn.addEventListener('click', function() {
            productsContainer.classList.remove('products-list');
            gridViewBtn.classList.add('active');
            listViewBtn.classList.remove('active');
            
            // Save view preference in localStorage
            localStorage.setItem('shop_view', 'grid');
        });
        
        listViewBtn.addEventListener('click', function() {
            productsContainer.classList.add('products-list');
            listViewBtn.classList.add('active');
            gridViewBtn.classList.remove('active');
            
            // Save view preference in localStorage
            localStorage.setItem('shop_view', 'list');
        });
        
        // Apply saved view preference
        const savedView = localStorage.getItem('shop_view');
        
        if (savedView === 'list') {
            productsContainer.classList.add('products-list');
            listViewBtn.classList.add('active');
            gridViewBtn.classList.remove('active');
        } else {
            productsContainer.classList.remove('products-list');
            gridViewBtn.classList.add('active');
            listViewBtn.classList.remove('active');
        }
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
                                            <span class="ms-2">(${product.review_count || 0} reviews)</span>
                                        </div>
                                        <p class="product-description mb-4">${product.short_description || product.description}</p>
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
                        
                        // Initialize quantity selector
                        const minusBtn = modalBody.querySelector('.quantity-minus');
                        const plusBtn = modalBody.querySelector('.quantity-plus');
                        const input = modalBody.querySelector('.quantity-input');
                        
                        minusBtn.addEventListener('click', function() {
                            let value = parseInt(input.value);
                            if (value > 1) {
                                value--;
                                input.value = value;
                            }
                        });
                        
                        plusBtn.addEventListener('click', function() {
                            let value = parseInt(input.value);
                            value++;
                            input.value = value;
                        });
                        
                        // Initialize add to cart button
                        const addToCartBtn = modalBody.querySelector('.add-to-cart');
                        
                        addToCartBtn.addEventListener('click', function() {
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
                                    const cartBadge = document.querySelector('.header-icon .badge');
                                    if (cartBadge) {
                                        cartBadge.textContent = data.cart_count;
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