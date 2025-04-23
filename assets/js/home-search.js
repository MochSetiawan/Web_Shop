/**
 * Live Search untuk Homepage ShopVerse
 */
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('home-search-input');
    const searchResults = document.getElementById('home-search-results');
    
    if (!searchInput || !searchResults) return;
    
    let searchTimeout;
    const minChars = 2; // Minimal karakter untuk mulai pencarian
    
    // Handler untuk input pencarian
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        
        const query = this.value.trim();
        
        // Hapus hasil jika query terlalu pendek
        if (query.length < minChars) {
            searchResults.style.display = 'none';
            searchResults.innerHTML = '';
            return;
        }
        
        // Delay untuk menghindari terlalu banyak request
        searchTimeout = setTimeout(function() {
            fetchSearchResults(query);
        }, 300);
    });
    
    // Tutup hasil pencarian jika klik di luar
    document.addEventListener('click', function(event) {
        if (!searchInput.contains(event.target) && !searchResults.contains(event.target)) {
            searchResults.style.display = 'none';
        }
    });
    
    // Tampilkan kembali hasil jika input mendapat fokus dan ada hasil sebelumnya
    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= minChars && searchResults.innerHTML !== '') {
            searchResults.style.display = 'block';
        }
    });
    
    // Fungsi untuk melakukan pencarian via AJAX
    function fetchSearchResults(query) {
        // Tampilkan loading
        searchResults.style.display = 'block';
        searchResults.innerHTML = '<div class="p-3 text-center"><i class="fas fa-spinner fa-spin me-2"></i>Searching...</div>';
        
        // Ambil base URL dari meta tag atau dari variabel JS yang sudah didefinisikan
        const baseUrl = typeof SITE_URL !== 'undefined' ? SITE_URL : '';
        
        // Lakukan request AJAX
        fetch(`${baseUrl}/ajax/search-products.php?query=${encodeURIComponent(query)}&limit=5`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.products.length > 0) {
                    renderSearchResults(data.products, query, data.count);
                } else {
                    searchResults.innerHTML = `
                        <div class="live-search-empty">
                            <i class="fas fa-search me-2"></i>
                            No products found matching "${query}"
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                searchResults.innerHTML = '<div class="live-search-empty text-danger"><i class="fas fa-exclamation-circle me-2"></i>Error while searching</div>';
            });
    }
    
    // Render hasil pencarian
    function renderSearchResults(products, query, totalCount) {
        let html = '';
        
        // Render setiap produk
        products.forEach(product => {
            html += `
                <a href="${product.url}" class="live-search-item">
                    <img src="${product.image}" alt="${product.name}" class="live-search-image">
                    <div class="live-search-info">
                        <div class="live-search-name">${highlightText(product.name, query)}</div>
                        <div class="live-search-category">${product.category || 'Uncategorized'}</div>
                        <div class="live-search-price">${product.price_html}</div>
                    </div>
                </a>
            `;
        });
        
        // Tambahkan footer jika ada lebih banyak hasil
        if (totalCount > products.length) {
            const remainingCount = totalCount - products.length;
            html += `
                <div class="live-search-footer">
                    <a href="${baseUrl}/search.php?q=${encodeURIComponent(query)}" class="btn btn-primary btn-sm">
                        View all ${totalCount} results <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            `;
        }
        
        searchResults.innerHTML = html;
    }
    
    // Highlight teks yang cocok dengan query
    function highlightText(text, query) {
        if (!query) return text;
        
        const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }
    
    // Helper untuk escape karakter regex
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
});