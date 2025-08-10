<?php
if (!defined('ABSPATH')) {
    exit;
}

// Check if user can manage products - with fallback
$can_manage_products = false;

if (class_exists('Gradyzer_Config')) {
    $can_manage_products = Gradyzer_Config::user_can_manage_products();
} else {
    // Fallback check
    $can_manage_products = current_user_can('edit_products') || current_user_can('manage_options');
}

if (!$can_manage_products) {
    echo '<div class="gradyzer-error">You do not have permission to manage products.</div>';
    return;
}

// Get product categories with fallback
$categories = array();
if (function_exists('get_terms')) {
    $categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    if (is_wp_error($categories)) {
        $categories = array();
    }
}
?>

<div class="gradyzer-products-manager">
    <!-- Top Actions Bar -->
    <div class="gradyzer-products-toolbar">
        <div class="gradyzer-toolbar-left">
            <div class="gradyzer-search-box">
                <input type="text" id="products-search" placeholder="Search products..." />
                <button type="button" id="search-products-btn">🔍</button>
            </div>
            
            <div class="gradyzer-filter-box">
                <select id="category-filter">
                    <option value="">All Categories</option>
                    <?php
                    if (!empty($categories)) {
                        foreach ($categories as $category) {
                            echo '<option value="' . esc_attr($category->slug) . '">' . esc_html($category->name) . '</option>';
                        }
                    }
                    ?>
                </select>
                
                <select id="status-filter">
                    <option value="any">All Status</option>
                    <option value="publish">Published</option>
                    <option value="draft">Draft</option>
                    <option value="private">Private</option>
                </select>
            </div>
        </div>
        
        <div class="gradyzer-toolbar-right">
            <div class="gradyzer-bulk-actions" style="display: none;">
                <select id="bulk-action-select">
                    <option value="">Bulk Actions</option>
                    <option value="publish">Set to Published</option>
                    <option value="draft">Set to Draft</option>
                    <option value="private">Set to Private</option>
                    <?php if (current_user_can('delete_products')): ?>
                        <option value="delete">Delete</option>
                    <?php endif; ?>
                </select>
                <button type="button" id="apply-bulk-action" class="gradyzer-btn gradyzer-btn-secondary">Apply</button>
            </div>
            
            <button type="button" id="refresh-products" class="gradyzer-btn gradyzer-btn-primary">
                🔄 Refresh
            </button>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="gradyzer-products-grid" id="products-grid">
        <!-- Initial loading message -->
        <div class="gradyzer-initial-load">
            <p>Click "Refresh" to load your products.</p>
        </div>
    </div>

    <!-- Pagination -->
    <div class="gradyzer-pagination" id="products-pagination">
        <!-- Pagination will be loaded here -->
    </div>

    <!-- Loading Indicator -->
    <div class="gradyzer-products-loading" id="products-loading" style="display: none;">
        <div class="gradyzer-loading-spinner">
            <div class="gradyzer-spinner"></div>
            <p>Loading products...</p>
        </div>
    </div>

    <!-- No Products Message -->
    <div class="gradyzer-no-products" id="no-products" style="display: none;">
        <div class="gradyzer-empty-state">
            <div class="gradyzer-empty-icon">📦</div>
            <h3>No Products Found</h3>
            <p>You haven't created any products yet, or no products match your current filters.</p>
            <a href="<?php echo home_url('/user-dashboard/add-product/'); ?>" class="gradyzer-btn gradyzer-btn-primary">
                ➕ Add Your First Product
            </a>
        </div>
    </div>
</div>

<!-- Product Actions Modal -->
<div id="product-actions-modal" class="gradyzer-modal" style="display: none;">
    <div class="gradyzer-modal-content">
        <div class="gradyzer-modal-header">
            <h3>Product Actions</h3>
            <button type="button" class="gradyzer-modal-close">&times;</button>
        </div>
        <div class="gradyzer-modal-body">
            <div class="gradyzer-product-quick-info">
                <div class="gradyzer-product-image">
                    <img id="modal-product-image" src="" alt="" />
                </div>
                <div class="gradyzer-product-details">
                    <h4 id="modal-product-title"></h4>
                    <p id="modal-product-status"></p>
                    <p id="modal-product-price"></p>
                </div>
            </div>
            
            <div class="gradyzer-product-actions">
                <button type="button" class="gradyzer-btn gradyzer-btn-primary" id="edit-product-wp">
                    ✏️ Edit in WordPress
                </button>
                <button type="button" class="gradyzer-btn gradyzer-btn-secondary" id="view-product">
                    👁️ View Product
                </button>
                <button type="button" class="gradyzer-btn gradyzer-btn-warning" id="set-draft">
                    📝 Set to Draft
                </button>
                <button type="button" class="gradyzer-btn gradyzer-btn-success" id="set-publish">
                    ✅ Publish
                </button>
                <?php if (current_user_can('delete_products')): ?>
                    <button type="button" class="gradyzer-btn gradyzer-btn-danger" id="delete-product">
                        🗑️ Delete Product
                    </button>
                <?php endif; ?>
            </div>
            
            <div class="gradyzer-seller-phone-section">
                <h5>Seller Phone Number</h5>
                <div class="gradyzer-phone-input-group">
                    <input type="text" id="seller-phone-input" placeholder="Enter phone number" />
                    <button type="button" class="gradyzer-btn gradyzer-btn-primary" id="save-seller-phone">
                        Save Phone
                    </button>
                </div>
                <p class="gradyzer-phone-instructions">
                    <small>Use shortcode <code>[gradyzer_seller_phone]</code> on single product pages to display this phone number.</small>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Product Card Template -->
<script type="text/template" id="product-card-template">
    <div class="gradyzer-product-card" data-product-id="{{id}}">
        <div class="gradyzer-product-select">
            <input type="checkbox" class="product-checkbox" value="{{id}}" />
        </div>
        
        <div class="gradyzer-product-image">
            <img src="{{image}}" alt="{{title}}" onerror="this.src='<?php echo GRADYZER_PLUGIN_URL; ?>assets/images/placeholder.png'" />
            <div class="gradyzer-product-status gradyzer-status-{{status}}">{{status}}</div>
        </div>
        
        <div class="gradyzer-product-content">
            <h3 class="gradyzer-product-title">{{title}}</h3>
            <div class="gradyzer-product-price">{{price}}</div>
            
            <div class="gradyzer-product-meta">
                <span class="gradyzer-product-author">By: {{author}}</span>
                <span class="gradyzer-product-date">{{date_created}}</span>
            </div>
            
            <div class="gradyzer-product-categories">
                {{#categories}}
                    <span class="gradyzer-category-tag">{{.}}</span>
                {{/categories}}
            </div>
            
            <div class="gradyzer-product-stock">
                <span class="gradyzer-stock-status gradyzer-stock-{{stock_status}}">
                    {{stock_status}}
                    {{#stock_quantity}}({{stock_quantity}} in stock){{/stock_quantity}}
                </span>
            </div>
        </div>
        
        <div class="gradyzer-product-actions">
            <button type="button" class="gradyzer-btn gradyzer-btn-primary gradyzer-btn-sm product-actions-btn" data-product-id="{{id}}">
                ⚙️ Actions
            </button>
        </div>
    </div>
</script>

<style>
/* Products Manager Styles */
.gradyzer-products-manager {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
}

.gradyzer-products-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid #f0f0f0;
    flex-wrap: wrap;
    gap: 16px;
}

.gradyzer-toolbar-left,
.gradyzer-toolbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.gradyzer-search-box {
    display: flex;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    overflow: hidden;
    background: white;
}

.gradyzer-search-box input {
    border: none;
    padding: 10px 16px;
    outline: none;
    min-width: 250px;
    font-size: 14px;
}

.gradyzer-search-box button {
    background: #007cba;
    color: white;
    border: none;
    padding: 10px 16px;
    cursor: pointer;
    font-size: 16px;
    transition: background 0.2s;
}

.gradyzer-search-box button:hover {
    background: #005a87;
}

.gradyzer-filter-box select {
    padding: 10px 16px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    background: white;
    font-size: 14px;
    cursor: pointer;
    min-width: 140px;
}

.gradyzer-bulk-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.gradyzer-btn {
    padding: 10px 20px;
    border: 2px solid transparent;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    font-size: 14px;
    line-height: 1;
}

.gradyzer-btn-primary {
    background: #007cba;
    color: white;
    border-color: #007cba;
}

.gradyzer-btn-primary:hover {
    background: #005a87;
    border-color: #005a87;
}

.gradyzer-btn-secondary {
    background: #6c757d;
    color: white;
    border-color: #6c757d;
}

.gradyzer-btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.gradyzer-products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
    min-height: 200px;
}

.gradyzer-initial-load {
    grid-column: 1 / -1;
    text-align: center;
    padding: 40px;
    color: #6c757d;
    background: #f8f9fa;
    border-radius: 8px;
    border: 2px dashed #dee2e6;
}

.gradyzer-product-card {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s;
    position: relative;
}

.gradyzer-product-card:hover {
    border-color: #007cba;
    box-shadow: 0 4px 20px rgba(0, 124, 186, 0.1);
    transform: translateY(-2px);
}

.gradyzer-product-select {
    position: absolute;
    top: 12px;
    left: 12px;
    z-index: 2;
}

.gradyzer-product-select input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #007cba;
}

.gradyzer-product-image {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.gradyzer-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.gradyzer-product-card:hover .gradyzer-product-image img {
    transform: scale(1.05);
}

.gradyzer-product-status {
    position: absolute;
    top: 12px;
    right: 12px;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    color: white;
}

.gradyzer-status-publish {
    background: #28a745;
}

.gradyzer-status-draft {
    background: #ffc107;
    color: #212529;
}

.gradyzer-status-private {
    background: #6c757d;
}

.gradyzer-product-content {
    padding: 16px;
}

.gradyzer-product-title {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 8px 0;
    line-height: 1.3;
    color: #212529;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.gradyzer-product-price {
    font-size: 18px;
    font-weight: bold;
    color: #007cba;
    margin-bottom: 12px;
}

.gradyzer-product-meta {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 12px;
}

.gradyzer-product-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 12px;
}

.gradyzer-category-tag {
    background: #e9ecef;
    color: #495057;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
}

.gradyzer-product-stock {
    margin-bottom: 16px;
}

.gradyzer-stock-status {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.gradyzer-stock-instock {
    background: #d4edda;
    color: #155724;
}

.gradyzer-stock-outofstock {
    background: #f8d7da;
    color: #721c24;
}

.gradyzer-product-actions {
    padding: 0 16px 16px;
}

.gradyzer-products-loading {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.gradyzer-loading-spinner {
    text-align: center;
}

.gradyzer-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007cba;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.gradyzer-no-products {
    grid-column: 1 / -1;
}

.gradyzer-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 2px dashed #dee2e6;
}

.gradyzer-empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
}

.gradyzer-empty-state h3 {
    color: #495057;
    margin-bottom: 8px;
}

.gradyzer-empty-state p {
    color: #6c757d;
    margin-bottom: 24px;
}

/* Modal Styles */
.gradyzer-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.gradyzer-modal-content {
    background: white;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.gradyzer-modal-header {
    padding: 20px 20px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.gradyzer-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6c757d;
}

.gradyzer-modal-body {
    padding: 20px;
}

.gradyzer-product-quick-info {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e9ecef;
}

.gradyzer-product-quick-info img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
}

.gradyzer-product-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 20px;
}

.gradyzer-seller-phone-section {
    border-top: 1px solid #e9ecef;
    padding-top: 16px;
}

.gradyzer-phone-input-group {
    display: flex;
    gap: 8px;
    margin: 12px 0;
}

.gradyzer-phone-input-group input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
}

/* Responsive */
@media (max-width: 768px) {
    .gradyzer-products-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .gradyzer-toolbar-left,
    .gradyzer-toolbar-right {
        justify-content: center;
    }
    
    .gradyzer-search-box input {
        min-width: 200px;
    }
    
    .gradyzer-products-grid {
        grid-template-columns: 1fr;
    }
    
    .gradyzer-product-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Initialize products manager
jQuery(document).ready(function($) {
    console.log('Products manager initializing...');
    
    // Check if GradyzerProducts exists, if not create basic functionality
    if (typeof GradyzerProducts === 'undefined') {
        window.GradyzerProducts = {
            init: function() {
                console.log('Basic GradyzerProducts init');
                this.bindEvents();
                // Auto-load products on page load
                this.loadProducts();
            },
            
            bindEvents: function() {
                var self = this;
                
                $('#refresh-products').on('click', function() {
                    self.loadProducts();
                });
                
                $('#search-products-btn').on('click', function() {
                    self.loadProducts();
                });
                
                $('#products-search').on('keypress', function(e) {
                    if (e.which === 13) {
                        self.loadProducts();
                    }
                });
                
                $('#category-filter, #status-filter').on('change', function() {
                    self.loadProducts();
                });
            },
            
            loadProducts: function() {
                console.log('Loading products...');
                
                var data = {
                    action: 'gradyzer_load_products',
                    nonce: gradyzer_ajax.nonce,
                    page: 1,
                    search: $('#products-search').val(),
                    category: $('#category-filter').val(),
                    status: $('#status-filter').val()
                };
                
                $('#products-loading').show();
                $('#products-grid').html('<div class="gradyzer-loading-message">Loading products...</div>');
                
                $.ajax({
                    url: gradyzer_ajax.ajax_url,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        console.log('Products loaded:', response);
                        $('#products-loading').hide();
                        
                        if (response.success && response.data) {
                            if (response.data.products && response.data.products.length > 0) {
                                self.renderProducts(response.data.products);
                                $('#no-products').hide();
                            } else {
                                $('#products-grid').html('');
                                $('#no-products').show();
                            }
                        } else {
                            $('#products-grid').html('<div class="gradyzer-error">Failed to load products. Please try again.</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                        $('#products-loading').hide();
                        $('#products-grid').html('<div class="gradyzer-error">Error loading products: ' + error + '</div>');
                    }
                });
            },
            
            renderProducts: function(products) {
                var grid = $('#products-grid');
                grid.html('');
                
                products.forEach(function(product) {
                    var card = $('<div class="gradyzer-product-card" data-product-id="' + product.id + '">');
                    
                    var html = '<div class="gradyzer-product-select">' +
                        '<input type="checkbox" class="product-checkbox" value="' + product.id + '" />' +
                        '</div>' +
                        '<div class="gradyzer-product-image">' +
                        '<img src="' + product.image + '" alt="' + product.title + '" />' +
                        '<div class="gradyzer-product-status gradyzer-status-' + product.status + '">' + product.status + '</div>' +
                        '</div>' +
                        '<div class="gradyzer-product-content">' +
                        '<h3 class="gradyzer-product-title">' + product.title + '</h3>' +
                        '<div class="gradyzer-product-price">' + product.price + '</div>' +
                        '<div class="gradyzer-product-meta">' +
                        '<span class="gradyzer-product-author">By: ' + product.author + '</span>' +
                        '<span class="gradyzer-product-date">' + product.date_created + '</span>' +
                        '</div>';
                    
                    if (product.categories && product.categories.length > 0) {
                        html += '<div class="gradyzer-product-categories">';
                        product.categories.forEach(function(category) {
                            html += '<span class="gradyzer-category-tag">' + category + '</span>';
                        });
                        html += '</div>';
                    }
                    
                    html += '<div class="gradyzer-product-stock">' +
                        '<span class="gradyzer-stock-status gradyzer-stock-' + product.stock_status + '">' +
                        product.stock_status +
                        (product.stock_quantity ? ' (' + product.stock_quantity + ' in stock)' : '') +
                        '</span>' +
                        '</div>' +
                        '</div>' +
                        '<div class="gradyzer-product-actions">' +
                        '<button type="button" class="gradyzer-btn gradyzer-btn-primary gradyzer-btn-sm product-actions-btn" data-product-id="' + product.id + '">' +
                        '⚙️ Actions' +
                        '</button>' +
                        '</div>';
                    
                    card.html(html);
                    grid.append(card);
                });
                
                // Bind action buttons
                $('.product-actions-btn').on('click', function() {
                    var productId = $(this).data('product-id');
                    console.log('Product action clicked:', productId);
                    // You can add modal functionality here
                });
            }
        };
    }
    
    // Initialize
    GradyzerProducts.init();
});
</script>