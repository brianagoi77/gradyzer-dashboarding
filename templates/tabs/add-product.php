<?php
if (!defined('ABSPATH')) {
    exit;
}

// Check if user can add products
if (!Gradyzer_Config::user_can_add_products()) {
    echo '<div class="gradyzer-error">You do not have permission to add products.</div>';
    return;
}
?>

<div class="gradyzer-add-product">
    <form id="add-product-form" class="gradyzer-product-form">
        <?php wp_nonce_field('gradyzer_nonce', 'gradyzer_nonce'); ?>
        
        <div class="gradyzer-form-grid">
            <!-- Left Column - Main Product Info -->
            <div class="gradyzer-form-left">
                <div class="gradyzer-form-section">
                    <h3>📝 Product Information</h3>
                    
                    <div class="gradyzer-form-group">
                        <label for="product-title">Product Title *</label>
                        <input type="text" id="product-title" name="title" required maxlength="200" />
                        <small>Enter a clear, descriptive product title</small>
                    </div>
                    
                    <div class="gradyzer-form-group">
                        <label for="product-description">Description *</label>
                        <div class="gradyzer-rich-editor">
                            <div class="gradyzer-editor-toolbar">
                                <button type="button" data-command="bold" title="Bold"><b>B</b></button>
                                <button type="button" data-command="italic" title="Italic"><i>I</i></button>
                                <button type="button" data-command="underline" title="Underline"><u>U</u></button>
                                <button type="button" data-command="insertOrderedList" title="Numbered List">1.</button>
                                <button type="button" data-command="insertUnorderedList" title="Bullet List">•</button>
                                <button type="button" data-command="createLink" title="Add Link">🔗</button>
                            </div>
                            <div id="product-description" class="gradyzer-rich-textarea" contenteditable="true" data-placeholder="Describe your product in detail..."></div>
                        </div>
                        <small>Rich text editor - 10 lines recommended</small>
                    </div>
                    
                    <div class="gradyzer-pricing-group">
                        <h4>💰 Pricing</h4>
                        <div class="gradyzer-price-row">
                            <div class="gradyzer-form-group">
                                <label for="regular-price">Regular Price *</label>
                                <div class="gradyzer-price-input">
                                    <span class="gradyzer-currency">$</span>
                                    <input type="number" id="regular-price" name="price" step="0.01" min="0" required />
                                </div>
                            </div>
                            
                            <div class="gradyzer-form-group">
                                <label for="sale-price">Sale Price</label>
                                <div class="gradyzer-price-input">
                                    <span class="gradyzer-currency">$</span>
                                    <input type="number" id="sale-price" name="sale_price" step="0.01" min="0" />
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="gradyzer-inventory-group">
                        <h4>📦 Inventory</h4>
                        <div class="gradyzer-inventory-row">
                            <div class="gradyzer-form-group">
                                <label for="stock-status">Stock Status</label>
                                <select id="stock-status" name="stock_status">
                                    <option value="instock">In Stock</option>
                                    <option value="outofstock">Out of Stock</option>
                                    <option value="onbackorder">On Backorder</option>
                                </select>
                            </div>
                            
                            <div class="gradyzer-form-group">
                                <label for="stock-quantity">Stock Quantity</label>
                                <input type="number" id="stock-quantity" name="stock_quantity" min="0" placeholder="Leave empty for unlimited" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Photo Section -->
                <div class="gradyzer-form-section">
                    <h3>📸 Product Photo</h3>
                    
                    <div class="gradyzer-image-upload">
                        <div class="gradyzer-image-preview" id="image-preview" style="display: none;">
                            <img id="preview-image" src="" alt="Product preview" />
                            <button type="button" class="gradyzer-remove-image" id="remove-image">&times;</button>
                        </div>
                        
                        <div class="gradyzer-upload-area" id="upload-area">
                            <div class="gradyzer-upload-content">
                                <div class="gradyzer-upload-icon">📷</div>
                                <p>Click to upload product image</p>
                                <small>JPG, PNG, GIF up to 10MB</small>
                            </div>
                            <input type="file" id="product-image" name="image" accept="image/*" />
                        </div>
                        
                        <input type="hidden" id="image-id" name="image_id" />
                    </div>
                </div>
            </div>

            <!-- Right Column - Categories, Tags, Phone -->
            <div class="gradyzer-form-right">
                <div class="gradyzer-form-section">
                    <h3>🏷️ Categories</h3>
                    
                    <div class="gradyzer-categories-manager">
                        <div class="gradyzer-selected-categories" id="selected-categories">
                            <!-- Selected categories will appear here -->
                        </div>
                        
                        <div class="gradyzer-categories-list">
                            <?php
                            $categories = Gradyzer_Products::get_product_categories();
                            foreach ($categories as $category) {
                                echo '<label class="gradyzer-category-item">';
                                echo '<input type="checkbox" name="categories[]" value="' . esc_attr($category->term_id) . '" />';
                                echo '<span>' . esc_html($category->name) . '</span>';
                                if (Gradyzer_Config::user_can_delete_products()) {
                                    echo '<button type="button" class="gradyzer-delete-category" data-term-id="' . esc_attr($category->term_id) . '">×</button>';
                                }
                                echo '</label>';
                            }
                            ?>
                        </div>
                        
                        <div class="gradyzer-add-category">
                            <input type="text" id="new-category-name" placeholder="New category name" />
                            <button type="button" id="add-category-btn" class="gradyzer-btn gradyzer-btn-sm">Add</button>
                        </div>
                    </div>
                </div>

                <div class="gradyzer-form-section">
                    <h3>🏷️ Tags</h3>
                    
                    <div class="gradyzer-tags-manager">
                        <div class="gradyzer-selected-tags" id="selected-tags">
                            <!-- Selected tags will appear here -->
                        </div>
                        
                        <div class="gradyzer-tags-dropdown">
                            <select id="existing-tags" multiple>
                                <?php
                                $tags = Gradyzer_Products::get_product_tags();
                                foreach ($tags as $tag) {
                                    echo '<option value="' . esc_attr($tag->term_id) . '">' . esc_html($tag->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="gradyzer-add-tags">
                            <label for="new-tags">Add New Tags (separated by ;)</label>
                            <input type="text" id="new-tags" name="tags" placeholder="tag1; tag2; tag3" />
                            <button type="button" id="create-tags-btn" class="gradyzer-btn gradyzer-btn-sm">Create Tags</button>
                        </div>
                    </div>
                </div>

                <div class="gradyzer-form-section">
                    <h3>📞 Seller Contact</h3>
                    
                    <div class="gradyzer-seller-phone">
                        <div class="gradyzer-form-group">
                            <label for="seller-phone">Seller Phone Number</label>
                            <input type="tel" id="seller-phone" name="seller_phone" placeholder="+1 (555) 123-4567" />
                            <small>This phone number will appear on the product page for customers to contact you directly.</small>
                        </div>
                        
                        <div class="gradyzer-shortcode-info">
                            <h5>📋 Shortcode Usage</h5>
                            <p>Use this shortcode on your single product page template:</p>
                            <code class="gradyzer-shortcode">[gradyzer_seller_phone]</code>
                            <button type="button" class="gradyzer-copy-shortcode" data-clipboard="[gradyzer_seller_phone]">Copy</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="gradyzer-form-actions">
            <button type="button" id="save-draft" class="gradyzer-btn gradyzer-btn-secondary">
                📝 Save as Draft
            </button>
            <button type="submit" id="publish-product" class="gradyzer-btn gradyzer-btn-primary">
                ✅ Publish Product
            </button>
            <button type="button" id="preview-product" class="gradyzer-btn gradyzer-btn-outline" style="display: none;">
                👁️ Preview
            </button>
        </div>

        <!-- Form Status -->
        <div class="gradyzer-form-status" id="form-status" style="display: none;">
            <div class="gradyzer-status-message"></div>
            <div class="gradyzer-status-progress">
                <div class="gradyzer-progress-bar"></div>
            </div>
        </div>
    </form>
</div>

<!-- Success Modal -->
<div id="product-success-modal" class="gradyzer-modal" style="display: none;">
    <div class="gradyzer-modal-content">
        <div class="gradyzer-modal-header">
            <h3>✅ Product Created Successfully!</h3>
        </div>
        <div class="gradyzer-modal-body">
            <div class="gradyzer-success-info">
                <p>Your product has been created and is now available.</p>
                <div class="gradyzer-product-links">
                    <a href="#" id="view-new-product" class="gradyzer-btn gradyzer-btn-primary" target="_blank">
                        👁️ View Product
                    </a>
                    <a href="#" id="edit-new-product" class="gradyzer-btn gradyzer-btn-secondary" target="_blank">
                        ✏️ Edit in WordPress
                    </a>
                </div>
            </div>
        </div>
        <div class="gradyzer-modal-footer">
            <button type="button" class="gradyzer-btn gradyzer-btn-outline" id="create-another">
                ➕ Create Another Product
            </button>
            <button type="button" class="gradyzer-btn gradyzer-btn-primary" id="view-products">
                📋 View All Products
            </button>
        </div>
    </div>
</div>

<script>
// Initialize add product form
jQuery(document).ready(function($) {
    GradyzerAddProduct.init();
});
</script>