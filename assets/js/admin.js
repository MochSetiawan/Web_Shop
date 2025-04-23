// Admin Panel JavaScript

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Toggle Sidebar
    const menuToggle = document.querySelector('.menu-toggle');
    const adminSidebar = document.querySelector('.admin-sidebar');
    const adminMain = document.querySelector('.admin-main');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            adminSidebar.classList.toggle('show');
            
            // Add overlay when sidebar is shown on mobile
            if (window.innerWidth < 992) {
                if (adminSidebar.classList.contains('show')) {
                    const overlay = document.createElement('div');
                    overlay.className = 'sidebar-overlay';
                    document.body.appendChild(overlay);
                    
                    overlay.addEventListener('click', function() {
                        adminSidebar.classList.remove('show');
                        this.remove();
                    });
                } else {
                    const overlay = document.querySelector('.sidebar-overlay');
                    if (overlay) {
                        overlay.remove();
                    }
                }
            }
        });
    }
    
    // Initialize DataTables
    const dataTables = document.querySelectorAll('.datatable');
    
    if (dataTables.length > 0) {
        dataTables.forEach(function(table) {
            new DataTable(table, {
                responsive: true,
                language: {
                    search: '',
                    searchPlaceholder: 'Search...',
                    lengthMenu: '_MENU_ records per page',
                },
                dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
            });
        });
    }
    
    // Initialize Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // File Input Preview
    const fileInputs = document.querySelectorAll('.form-file-input');
    
    if (fileInputs.length > 0) {
        fileInputs.forEach(function(input) {
            input.addEventListener('change', function() {
                const previewContainer = document.querySelector(this.dataset.preview);
                
                if (previewContainer) {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            if (previewContainer.tagName === 'IMG') {
                                previewContainer.src = e.target.result;
                            } else {
                                previewContainer.style.backgroundImage = `url(${e.target.result})`;
                            }
                        };
                        
                        reader.readAsDataURL(this.files[0]);
                    }
                }
            });
        });
    }
    
    // Multi Image Upload Preview
    const multiFileInputs = document.querySelectorAll('.multi-file-input');
    
    if (multiFileInputs.length > 0) {
        multiFileInputs.forEach(function(input) {
            input.addEventListener('change', function() {
                const previewContainer = document.querySelector(this.dataset.preview);
                
                if (previewContainer) {
                    // Clear existing previews
                    previewContainer.innerHTML = '';
                    
                    if (this.files && this.files.length > 0) {
                        for (let i = 0; i < this.files.length; i++) {
                            const reader = new FileReader();
                            const file = this.files[i];
                            
                            reader.onload = function(e) {
                                const preview = document.createElement('div');
                                preview.className = 'image-preview-item';
                                preview.innerHTML = `
                                    <img src="${e.target.result}" alt="Preview">
                                    <div class="image-actions">
                                        <button type="button" class="btn btn-sm btn-danger delete-image">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                `;
                                
                                previewContainer.appendChild(preview);
                                
                                // Add delete functionality
                                preview.querySelector('.delete-image').addEventListener('click', function() {
                                    preview.remove();
                                });
                            };
                            
                            reader.readAsDataURL(file);
                        }
                    }
                }
            });
        });
    }
    
    // Delete Confirmation
    const deleteButtons = document.querySelectorAll('.btn-delete');
    
    if (deleteButtons.length > 0) {
        deleteButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const confirmMessage = this.dataset.confirm || 'Are you sure you want to delete this item?';
                
                if (confirm(confirmMessage)) {
                    window.location.href = this.href;
                }
            });
        });
    }
    
    // Status Toggle
    const statusToggles = document.querySelectorAll('.status-toggle');
    
    if (statusToggles.length > 0) {
        statusToggles.forEach(function(toggle) {
            toggle.addEventListener('change', function() {
                const id = this.dataset.id;
                const status = this.checked ? 'active' : 'inactive';
                const url = this.dataset.url;
                
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}&status=${status}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Success', data.message, 'success');
                    } else {
                        showToast('Error', data.message, 'error');
                        // Reset toggle to original state
                        this.checked = !this.checked;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error', 'Something went wrong. Please try again.', 'error');
                    // Reset toggle to original state
                    this.checked = !this.checked;
                });
            });
        });
    }
    
    // Show Toast
    function showToast(title, message, type) {
        const toastContainer = document.getElementById('toast-container');
        
        if (!toastContainer) {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'position-fixed top-0 end-0 p-3';
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