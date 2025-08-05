/**
 * Gradyzer Products Manager
 * File: assets/js/products.js
 */

var GradyzerProducts = {
    currentPage: 1,
    totalPages: 1,
    isLoading: false,
    
    init: function() {
        console.log('GradyzerProducts initializing...');
        this.bindEvents();
        this.loadProducts();
    },
    
    bindEvents: function() {
        var self = this;
        
        // Refresh button
        $('#refresh-products').on('click', function() {
            self.currentPage = 1;
            self.loadProducts();
        });
        
        // Search functionality
        $('#search-products-btn').on('click', function() {
            self.currentPage = 1;
            self.loadProducts();
        });
        
        $('#products-search').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                self.currentPage = 1;
                self.loadProducts();
            }
        });
        
        // Filter changes
        $('#category-filter, #status-filter').on('change', function() {
            self.currentPage = 1;
            self.loadProducts();
        });
        
        // Bulk actions
        $(document).on('change', '.product-checkbox', function() {
            self.updateBulkActions();
        });
        
        $('#apply-bulk-action').on('click', function() {
            self.applyBulkAction();
        });
        
        // Product actions
        $(document).on('click', '.product-actions-btn', function() {
            var productId = $(this).data('product-id');
            self.showProductModal(productId);
        });
        
        // Modal close
        $(document).on('click', '.gradyzer-modal-close, .gradyzer-modal', function(e) {
            if (e.target === this) {
                $('.gradyzer-modal').hide();
            }
        });
        
        // Pagination
        $(document).on('click', '.gradyzer-page-btn', function() {
            var page = $(this).data('page');
            if (page && page !== self.currentPage && !self.isLoading) {
                self.currentPage = page;
                self.loadProducts();
            }
        });
    },
    
    loadProducts: function() {
        if (this.isLoading) return;
        
        console.log('Loading products, page:', this.currentPage);
        this.isLoading = true;
        
        var data = {
            action: 'gradyzer_load_products',
            nonce: gradyzer_ajax.nonce,
            page: this.currentPage,
            search: $('#products-search').val(),
            category: $('#category-filter').val(),
            status: $('#status-filter').val()
        };
        
        $('#products-loading').show();
        
        $.ajax({
            url: gradyzer_ajax.ajax_url,
            type: 'POST',
            data: data,
            timeout: 30000, // 30 second timeout
            success: function(response) {
                console.log('Products response:', response);
                $('#products-loading').hide();
                
                if (response.success && response.data) {
                    var data = response.data;
                    
                    if (data.products && data.products.length > 0) {
                        GradyzerProducts.renderProducts(data.products);
                        GradyzerProducts.renderPagination(data.pagination);
                        $('#no-products').hide();
                        $('#products-grid').show();
                    } else {
                        $('#products-grid').hide();
                        $('#no-products').show();
                        $('#products-pagination').html('');
                    }
                    
                    if (data.pagination) {
                        GradyzerProducts.totalPages = data.pagination.total_pages;
                    }
                } else {
                    GradyzerProducts.showError('Failed to load products: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', xhr.responseText);
                $('#products-loading').hide();
                
                var errorMsg = 'Network error loading products';
                if (xhr.responseText) {
                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                        errorMsg = errorResponse.data || errorMsg;
                    } catch(e) {
                        errorMsg = 'Server error: ' + xhr.status;
                    }
                }
                
                GradyzerProducts.showError(errorMsg);
            },
            complete: function() {
                GradyzerProducts.isLoading = false;
            }
        });
    },
    
    renderProducts: function(products) {
        var grid = $('#products-grid');
        grid.html('');
        
        if (!products || products.length === 0) {
            grid.hide();
            $('#no-products').show();