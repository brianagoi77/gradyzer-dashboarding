jQuery(document).ready(function($) {
    'use strict';

    window.GradyzerAddProduct = {
        selectedCategories: [],
        selectedTags: [],
        imageId: 0,

        init: function() {
            this.bindEvents();
            this.initRichEditor();
        },

        bindEvents: function() {
            // Form submission
            $('#add-product-form').on('submit', this.handleSubmit.bind(this));
            $('#save-draft').on('click', this.saveDraft.bind(this));
            
            // Image upload
            $('#product-image').on('change', this.handleImageUpload.bind(this));
            $('#remove-image').on('click', this.removeImage.bind(this));
            
            // Categories
            $('input[name="categories[]"]').on('change', this.handleCategoryChange.bind(this));
            $('#add-category-btn').on('click', this.addCategory.bind(this));
            
            // Tags
            $('#existing-tags').on('change', this.handleTagSelection.bind(this));
            $('#create-tags-btn').on('click', this.createTags.bind(this));
            
            // Copy shortcode
            $('.gradyzer-copy-shortcode').on('click', this.copyShortcode.bind(this));
            
            // Success modal actions
            $('#create-another').on('click', this.createAnother.bind(this));
            $('#view-products').on('click', this.viewProducts.bind(this));
        },

        initRichEditor: function() {
            // Rich text editor functionality
            $('.gradyzer-editor-toolbar button').on('click', function(e) {
                e.preventDefault();
                var command = $(this).data('command');
                
                if (command === 'createLink') {
                    var url = prompt('Enter the link URL:');
                    if (url) {
                        document.execCommand(command, false, url);
                    }
                } else {
                    document.execCommand(command, false, null);
                }
                
                $('#product-description').focus();
            });
            
            // Placeholder functionality
            $('#product-description').on('focus blur input', function() {
                var $this = $(this);
                if ($this.text().trim() === '' && $this.html() === '') {
                    $this.addClass('empty');
                } else {
                    $this.removeClass('empty');
                }
            });
        },

        handleImageUpload: function(e) {
            var file = e.target.files[0];
            if (!file) return;
            
            // Validate file type
            if (!file.type.match('image.*')) {
                GradyzerDashboard.showNotification('Please select a valid image file.', 'error');
                return;
            }
            
            // Validate file size (10MB max)
            if (file.size > 10 * 1024 * 1024) {
                GradyzerDashboard.showNotification('Image file must be less than 10MB.', 'error');
                return;
            }
            
            var formData = new FormData();
            formData.append('image', file);
            formData.append('action', 'gradyzer_upload_product_image');
            formData.append('nonce', gradyzer_ajax.nonce);
            
            // Show upload progress
            $('#upload-area').addClass('uploading');
            
            $.ajax({
                url: gradyzer_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#upload-area').removeClass('uploading');
                    
                    if (response.success) {
                        GradyzerAddProduct.showImagePreview(response.data);
                        GradyzerDashboard.showNotification('Image uploaded successfully!', 'success');
                    } else {
                        GradyzerDashboard.showNotification('Failed to upload image.', 'error');
                    }
                },
                error: function() {
                    $('#upload-area').removeClass('uploading');
                    GradyzerDashboard.showNotification('Upload failed. Please try again.', 'error');
                }
            });
        },

        showImagePreview: function(imageData) {
            this.imageId = imageData.attachment_id;
            $('#image-id').val(this.imageId);
            
            $('#preview-image').attr('src', imageData.thumbnail_url);
            $('#image-preview').show();
            $('#upload-area').hide();
        },

        removeImage: function() {
            this.imageId = 0;
            $('#image-id').val('');
            $('#image-preview').hide();
            $('#upload-area').show();
            $('#product-image').val('');
        },

        handleCategoryChange: function(e) {
            var categoryId = parseInt($(e.target).val());
            var categoryName = $(e.target).closest('label').find('span').text();
            
            if ($(e.target).is(':checked')) {
                if (this.selectedCategories.indexOf(categoryId) === -1) {
                    this.selectedCategories.push(categoryId);
                    this.addCategoryTag(categoryId, categoryName);
                }
            } else {
                var index = this.selectedCategories.indexOf(categoryId);
                if (index > -1) {
                    this.selectedCategories.splice(index, 1);
                    this.removeCategoryTag(categoryId);
                }
            }
        },

        addCategoryTag: function(id, name) {
            var tag = `<span class="gradyzer-category-tag" data-id="${id}">${name} <button type="button" onclick="GradyzerAddProduct.removeCategoryById(${id})">&times;</button></span>`;
            $('#selected-categories').append(tag);
        },

        removeCategoryTag: function(id) {
            $('#selected-categories').find(`[data-id="${id}"]`).remove();
        },

        removeCategoryById: function(id) {
            var index = this.selectedCategories.indexOf(id);
            if (index > -1) {
                this.selectedCategories.splice(index, 1);
            }
            this.removeCategoryTag(id);
            $(`input[name="categories[]"][value="${id}"]`).prop('checked', false);
        },

        addCategory: function() {
            var categoryName = $('#new-category-name').val().trim();
            if (!categoryName) {
                GradyzerDashboard.showNotification('Please enter a category name.', 'error');
                return;
            }
            
            GradyzerDashboard.ajaxRequest({
                url: gradyzer_ajax.ajax_url,
                data: {
                    action: 'gradyzer_create_category',
                    category_name: categoryName,
                    parent_id: 0
                },
                success: function(response) {
                    if (response.success) {
                        var category = response.data;
                        
                        // Add to categories list
                        var categoryHtml = `
                            <label class="gradyzer-category-item">
                                <input type="checkbox" name="categories[]" value="${category.term_id}" checked />
                                <span>${category.name}</span>
                                <button type="button" class="gradyzer-delete-category" data-term-id="${category.term_id}">×</button>
                            </label>
                        `;
                        $('.gradyzer-categories-list').append(categoryHtml);
                        
                        // Add to selected
                        GradyzerAddProduct.selectedCategories.push(category.term_id);
                        GradyzerAddProduct.addCategoryTag(category.term_id, category.name);
                        
                        $('#new-category-name').val('');
                        GradyzerDashboard.showNotification('Category created successfully!', 'success');
                    }
                }
            });
        },

        handleTagSelection: function() {
            // Handle existing tags selection
            var selectedOptions = $('#existing-tags option:selected');
            selectedOptions.each(function() {
                var tagId = parseInt($(this).val());
                var tagName = $(this).text();
                
                if (GradyzerAddProduct.selectedTags.indexOf(tagId) === -1) {
                    GradyzerAddProduct.selectedTags.push(tagId);
                    GradyzerAddProduct.addTagElement(tagId, tagName);
                }
            });
        },

        addTagElement: function(id, name) {
            var tag = `<span class="gradyzer-tag-item" data-id="${id}">${name} <button type="button" onclick="GradyzerAddProduct.removeTagById(${id})">&times;</button></span>`;
            $('#selected-tags').append(tag);
        },

        removeTagById: function(id) {
            var index = this.selectedTags.indexOf(id);
            if (index > -1) {
                this.selectedTags.splice(index, 1);
            }
            $('#selected-tags').find(`[data-id="${id}"]`).remove();
        },

        createTags: function() {
            var tagNames = $('#new-tags').val().trim();
            if (!tagNames) {
                GradyzerDashboard.showNotification('Please enter tag names.', 'error');
                return;
            }
            
            GradyzerDashboard.ajaxRequest({
                url: gradyzer_ajax.ajax_url,
                data: {
                    action: 'gradyzer_create_tag',
                    tag_names: tagNames
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        response.data.forEach(function(tag) {
                            // Add to dropdown
                            $('#existing-tags').append(`<option value="${tag.term_id}">${tag.name}</option>`);
                            
                            // Add to selected
                            GradyzerAddProduct.selectedTags.push(tag.term_id);
                            GradyzerAddProduct.addTagElement(tag.term_id, tag.name);
                        });
                        
                        $('#new-tags').val('');
                        GradyzerDashboard.showNotification('Tags created successfully!', 'success');
                    }
                }
            });
        },

        copyShortcode: function(e) {
            var shortcode = $(e.target).data('clipboard');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(shortcode).then(function() {
                    GradyzerDashboard.showNotification('Shortcode copied to clipboard!', 'success');
                });
            } else {
                // Fallback for older browsers
                var textArea = document.createElement('textarea');
                textArea.value = shortcode;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                GradyzerDashboard.showNotification('Shortcode copied to clipboard!', 'success');
            }
        },

        validateForm: function() {
            var errors = [];
            
            if (!$('#product-title').val().trim()) {
                errors.push('Product title is required.');
            }
            
            if (!$('#product-description').text().trim()) {
                errors.push('Product description is required.');
            }
            
            if (!$('#regular-price').val() || parseFloat($('#regular-price').val()) <= 0) {
                errors.push('Valid regular price is required.');
            }
            
            var salePrice = $('#sale-price').val();
            var regularPrice = $('#regular-price').val();
            if (salePrice && parseFloat(salePrice) >= parseFloat(regularPrice)) {
                errors.push('Sale price must be less than regular price.');
            }
            
            return errors;
        },

        handleSubmit: function(e) {
            e.preventDefault();
            this.submitProduct('publish');
        },

        saveDraft: function() {
            this.submitProduct('draft');
        },

        submitProduct: function(status) {
            var errors = this.validateForm();
            if (errors.length > 0) {
                GradyzerDashboard.showNotification('Please fix the following errors:\n' + errors.join('\n'), 'error');
                return;
            }
            
            var formData = {
                action: 'gradyzer_create_product',
                title: $('#product-title').val(),
                description: $('#product-description').html(),
                price: $('#regular-price').val(),
                sale_price: $('#sale-price').val() || 0,
                image_id: this.imageId,
                categories: this.selectedCategories,
                tags: $('#new-tags').val(),
                seller_phone: $('#seller-phone').val(),
                stock_status: $('#stock-status').val(),
                stock_quantity: $('#stock-quantity').val() || 0,
                product_status: status
            };
            
            this.showProgress('Creating product...');
            
            GradyzerDashboard.ajaxRequest({
                url: gradyzer_ajax.ajax_url,
                data: formData,
                success: function(response) {
                    GradyzerAddProduct.hideProgress();
                    
                    if (response.success) {
                        GradyzerAddProduct.showSuccessModal(response.data);
                    } else {
                        GradyzerDashboard.showNotification('Failed to create product. Please try again.', 'error');
                    }
                },
                error: function() {
                    GradyzerAddProduct.hideProgress();
                    GradyzerDashboard.showNotification('An error occurred. Please try again.', 'error');
                }
            });
        },

        showProgress: function(message) {
            $('#form-status').show();
            $('.gradyzer-status-message').text(message);
            $('.gradyzer-progress-bar').css('width', '0%');
            
            // Animate progress bar
            var progress = 0;
            var interval = setInterval(function() {
                progress += Math.random() * 20;
                if (progress > 90) progress = 90;
                $('.gradyzer-progress-bar').css('width', progress + '%');
            }, 200);
            
            // Store interval for cleanup
            this.progressInterval = interval;
        },

        hideProgress: function() {
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
            }
            $('.gradyzer-progress-bar').css('width', '100%');
            setTimeout(function() {
                $('#form-status').hide();
            }, 500);
        },

        showSuccessModal: function(productData) {
            var modalHtml = `
                <div class="gradyzer-modal" id="product-success-modal">
                    <div class="gradyzer-modal-content">
                        <div class="gradyzer-modal-header">
                            <h3>✅ Product Created Successfully!</h3>
                        </div>
                        <div class="gradyzer-modal-body">
                            <div class="gradyzer-success-info">
                                <p>Your product "${productData.title || 'Untitled'}" has been created and is now available.</p>
                                <div class="gradyzer-product-links">
                                    <a href="/?p=${productData.product_id}" class="gradyzer-btn gradyzer-btn-primary" target="_blank">
                                        👁️ View Product
                                    </a>
                                    <a href="${gradyzer_ajax.admin_url}post.php?post=${productData.product_id}&action=edit" class="gradyzer-btn gradyzer-btn-secondary" target="_blank">
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
            `;
            
            $('body').append(modalHtml);
            $('#product-success-modal').fadeIn(200);
        },

        createAnother: function() {
            $('#product-success-modal').fadeOut(200, function() {
                $(this).remove();
            });
            
            // Reset form
            this.resetForm();
        },

        viewProducts: function() {
            window.location.href = gradyzer_ajax.dashboard_url + 'products/';
        },

        resetForm: function() {
            $('#add-product-form')[0].reset();
            $('#product-description').html('').addClass('empty');
            this.removeImage();
            this.selectedCategories = [];
            this.selectedTags = [];
            $('#selected-categories').empty();
            $('#selected-tags').empty();
            $('input[name="categories[]"]').prop('checked', false);
        }
    };

    // Initialize if on add product page
    if ($('#add-product-form').length) {
        GradyzerAddProduct.init();
    }
});