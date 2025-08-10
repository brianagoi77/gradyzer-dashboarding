jQuery(document).ready(function($) {
    'use strict';

    // Global Dashboard Object
    window.GradyzerDashboard = {
        init: function() {
            this.bindEvents();
            this.initMobileMenu();
            this.updateNotificationCounts();
            this.startPeriodicUpdates();
            this.loadTabSpecificJS();
        },

        bindEvents: function() {
            // Mobile menu toggle
            $(document).on('click', '#gradyzer-mobile-toggle', this.toggleMobileMenu);
            
            // Modal handlers
            $(document).on('click', '.gradyzer-modal-close, .gradyzer-modal', function(e) {
                if (e.target === this) {
                    GradyzerDashboard.closeModal();
                }
            });

            // Loading states
            $(document).on('click', '.gradyzer-btn[data-loading]', this.handleLoadingButton);

            // Auto-resize textareas
            $(document).on('input', 'textarea[data-auto-resize]', this.autoResizeTextarea);
            
            // Copy functionality
            $(document).on('click', '[data-clipboard]', this.copyToClipboard);
        },

        initMobileMenu: function() {
            // Close mobile menu when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.gradyzer-sidebar, #gradyzer-mobile-toggle').length) {
                    $('.gradyzer-sidebar').removeClass('open');
                }
            });

            // Close mobile menu when navigating
            $('.gradyzer-nav-link').on('click', function() {
                $('.gradyzer-sidebar').removeClass('open');
            });
        },

        toggleMobileMenu: function(e) {
            e.preventDefault();
            $('.gradyzer-sidebar').toggleClass('open');
        },

        updateNotificationCounts: function() {
            if (typeof gradyzer_ajax === 'undefined') return;

            $.ajax({
                url: gradyzer_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'gradyzer_get_unread_count',
                    nonce: gradyzer_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var count = response.data.count || 0;
                        
                        // Update inbox counter in sidebar
                        var $inboxCounter = $('#inbox-counter');
                        var $counterBadge = $inboxCounter.find('.gradyzer-counter-badge');
                        
                        if (count > 0) {
                            $counterBadge.text(count);
                            $inboxCounter.show();
                        } else {
                            $inboxCounter.hide();
                        }

                        // Update notification bubble in topbar
                        var $bubble = $('#gradyzer-message-bubble');
                        var $bubbleCount = $bubble.find('.gradyzer-bubble-count');
                        
                        if (count > 0) {
                            $bubbleCount.text(count);
                            $bubble.show();
                        } else {
                            $bubble.hide();
                        }

                        // Trigger custom event
                        $(document).trigger('gradyzer:notification-count-updated', [count]);
                    }
                }
            });
        },

        startPeriodicUpdates: function() {
            // Update notification counts every 30 seconds
            setInterval(function() {
                GradyzerDashboard.updateNotificationCounts();
            }, 30000);
        },

        loadTabSpecificJS: function() {
            // Load tab-specific JavaScript files
            var currentTab = this.getCurrentTab();
            var jsFiles = {
                'products': 'products.js',
                'add-product': 'add-product.js',
                'inbox': 'inbox.js',
                'account': 'account.js'
            };
            
            if (jsFiles[currentTab]) {
                this.loadScript(gradyzer_ajax.plugin_url + 'assets/js/' + jsFiles[currentTab]);
            }
        },

        getCurrentTab: function() {
            var url = window.location.pathname;
            var matches = url.match(/\/user-dashboard\/([^\/]+)/);
            return matches ? matches[1] : 'overview';
        },

        loadScript: function(src) {
            if ($('script[src="' + src + '"]').length === 0) {
                var script = document.createElement('script');
                script.src = src;
                script.async = true;
                document.head.appendChild(script);
            }
        },

        showLoading: function(message) {
            message = message || 'Loading...';
            
            var $loading = $('#gradyzer-loading');
            if ($loading.length === 0) {
                $loading = $('<div id="gradyzer-loading" class="gradyzer-loading-overlay">' +
                    '<div class="gradyzer-loading-spinner">' +
                    '<div class="gradyzer-spinner"></div>' +
                    '<p>' + message + '</p>' +
                    '</div>' +
                    '</div>');
                $('body').append($loading);
            } else {
                $loading.find('p').text(message);
            }
            
            $loading.fadeIn(200);
        },

        hideLoading: function() {
            $('#gradyzer-loading').fadeOut(200);
        },

        showModal: function(content, options) {
            options = $.extend({
                title: '',
                closable: true,
                className: '',
                width: 'auto',
                onOpen: null,
                onClose: null
            }, options);

            var modalHtml = '<div class="gradyzer-modal ' + options.className + '">' +
                '<div class="gradyzer-modal-content" style="' + (options.width !== 'auto' ? 'width: ' + options.width : '') + '">';

            if (options.title) {
                modalHtml += '<div class="gradyzer-modal-header">' +
                    '<h3>' + options.title + '</h3>';
                if (options.closable) {
                    modalHtml += '<button type="button" class="gradyzer-modal-close">&times;</button>';
                }
                modalHtml += '</div>';
            }

            modalHtml += '<div class="gradyzer-modal-body">' + content + '</div>' +
                '</div>' +
                '</div>';

            var $modal = $(modalHtml);
            $('body').append($modal);
            
            $modal.fadeIn(200);

            if (options.onOpen) {
                options.onOpen($modal);
            }

            // Store close callback
            $modal.data('onClose', options.onClose);

            return $modal;
        },

        closeModal: function() {
            var $modal = $('.gradyzer-modal:visible');
            if ($modal.length) {
                var onClose = $modal.data('onClose');
                
                $modal.fadeOut(200, function() {
                    $modal.remove();
                    if (onClose) {
                        onClose();
                    }
                });
            }
        },

        showNotification: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 5000;

            var icons = {
                'success': '✅',
                'error': '❌',
                'warning': '⚠️',
                'info': 'ℹ️'
            };

            var $notification = $('<div class="gradyzer-notification gradyzer-notification-' + type + '">' +
                '<div class="gradyzer-notification-icon">' + (icons[type] || icons.info) + '</div>' +
                '<div class="gradyzer-notification-content">' + message + '</div>' +
                '<button type="button" class="gradyzer-notification-close">&times;</button>' +
                '</div>');

            // Add to container or create one
            var $container = $('.gradyzer-notifications-container');
            if ($container.length === 0) {
                $container = $('<div class="gradyzer-notifications-container"></div>');
                $('body').append($container);
                
                // Add notification styles
                this.addNotificationStyles();
            }

            $container.append($notification);
            $notification.slideDown(200);

            // Auto-remove after duration
            setTimeout(function() {
                $notification.slideUp(200, function() {
                    $notification.remove();
                });
            }, duration);

            // Manual close
            $notification.find('.gradyzer-notification-close').on('click', function() {
                $notification.slideUp(200, function() {
                    $notification.remove();
                });
            });
        },

        addNotificationStyles: function() {
            if ($('#gradyzer-notification-styles').length === 0) {
                var styles = `
                    <style id="gradyzer-notification-styles">
                    .gradyzer-notifications-container {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        z-index: 10001;
                        max-width: 400px;
                    }
                    
                    .gradyzer-notification {
                        display: flex;
                        align-items: center;
                        gap: 12px;
                        padding: 16px;
                        margin-bottom: 10px;
                        border-radius: 8px;
                        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                        animation: slideInRight 0.3s ease;
                    }
                    
                    .gradyzer-notification-success {
                        background: #d1fae5;
                        border-left: 4px solid #059669;
                        color: #065f46;
                    }
                    
                    .gradyzer-notification-error {
                        background: #fee2e2;
                        border-left: 4px solid #dc2626;
                        color: #991b1b;
                    }
                    
                    .gradyzer-notification-warning {
                        background: #fef3c7;
                        border-left: 4px solid #d97706;
                        color: #92400e;
                    }
                    
                    .gradyzer-notification-info {
                        background: #dbeafe;
                        border-left: 4px solid #1d4ed8;
                        color: #1e40af;
                    }
                    
                    .gradyzer-notification-icon {
                        font-size: 18px;
                        flex-shrink: 0;
                    }
                    
                    .gradyzer-notification-content {
                        flex: 1;
                        font-weight: 500;
                    }
                    
                    .gradyzer-notification-close {
                        background: none;
                        border: none;
                        font-size: 18px;
                        cursor: pointer;
                        opacity: 0.7;
                        flex-shrink: 0;
                    }
                    
                    .gradyzer-notification-close:hover {
                        opacity: 1;
                    }
                    
                    @keyframes slideInRight {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                    
                    @media (max-width: 768px) {
                        .gradyzer-notifications-container {
                            left: 20px;
                            right: 20px;
                            max-width: none;
                        }
                    }
                    </style>
                `;
                $('head').append(styles);
            }
        },

        confirmAction: function(message, callback, options) {
            options = $.extend({
                title: 'Confirm Action',
                confirmText: 'Confirm',
                cancelText: 'Cancel',
                confirmClass: 'gradyzer-btn-danger'
            }, options);

            var content = '<div class="gradyzer-confirm-dialog">' +
                '<p>' + message + '</p>' +
                '<div class="gradyzer-confirm-actions">' +
                '<button type="button" class="gradyzer-btn gradyzer-btn-outline gradyzer-confirm-cancel">' + options.cancelText + '</button>' +
                '<button type="button" class="gradyzer-btn ' + options.confirmClass + ' gradyzer-confirm-ok">' + options.confirmText + '</button>' +
                '</div>' +
                '</div>';

            var $modal = this.showModal(content, {
                title: options.title,
                className: 'gradyzer-confirm-modal',
                width: '400px'
            });

            $modal.find('.gradyzer-confirm-cancel').on('click', function() {
                GradyzerDashboard.closeModal();
            });

            $modal.find('.gradyzer-confirm-ok').on('click', function() {
                GradyzerDashboard.closeModal();
                if (callback) {
                    callback();
                }
            });
        },

        handleLoadingButton: function(e) {
            var $btn = $(this);
            var originalText = $btn.text();
            
            $btn.prop('disabled', true)
                .html('<div class="gradyzer-btn-spinner"></div> Loading...');

            // Restore button after operation (caller should handle this)
            $btn.data('original-text', originalText);
        },

        restoreButton: function($btn) {
            var originalText = $btn.data('original-text') || 'Submit';
            $btn.prop('disabled', false).text(originalText);
        },

        autoResizeTextarea: function() {
            var $textarea = $(this);
            $textarea.css('height', 'auto');
            $textarea.css('height', $textarea.prop('scrollHeight') + 'px');
        },

        copyToClipboard: function(e) {
            e.preventDefault();
            var text = $(this).data('clipboard');
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    GradyzerDashboard.showNotification('Copied to clipboard!', 'success');
                });
            } else {
                // Fallback for older browsers
                var textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                GradyzerDashboard.showNotification('Copied to clipboard!', 'success');
            }
        },

        formatCurrency: function(amount, currency) {
            currency = currency || '$';
            return currency + parseFloat(amount).toFixed(2);
        },

        formatDate: function(date, format) {
            if (typeof date === 'string') {
                date = new Date(date);
            }
            
            format = format || 'MMM DD, YYYY';
            
            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                         'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            
            var day = date.getDate();
            var month = months[date.getMonth()];
            var year = date.getFullYear();
            
            return format.replace('MMM', month)
                        .replace('DD', day.toString().padStart(2, '0'))
                        .replace('YYYY', year);
        },

        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // AJAX helper with error handling
        ajaxRequest: function(options) {
            var defaults = {
                type: 'POST',
                dataType: 'json',
                beforeSend: function() {
                    if (options.showLoading !== false) {
                        GradyzerDashboard.showLoading();
                    }
                },
                complete: function() {
                    if (options.showLoading !== false) {
                        GradyzerDashboard.hideLoading();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    GradyzerDashboard.showNotification('An error occurred. Please try again.', 'error');
                    
                    if (options.error) {
                        options.error(xhr, status, error);
                    }
                }
            };

            // Add nonce if available
            if (typeof gradyzer_ajax !== 'undefined') {
                options.data = options.data || {};
                if (typeof options.data === 'object') {
                    options.data.nonce = gradyzer_ajax.nonce;
                }
            }

            return $.ajax($.extend(defaults, options));
        }
    };

    // Initialize Dashboard
    GradyzerDashboard.init();
});