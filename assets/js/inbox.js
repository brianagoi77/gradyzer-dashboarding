jQuery(document).ready(function($) {
    'use strict';

    window.GradyzerInbox = {
        currentThread: null,
        threads: [],
        
        init: function() {
            this.bindEvents();
            this.loadInbox();
        },

        bindEvents: function() {
            // Thread selection
            $(document).on('click', '.gradyzer-thread-item', this.selectThread.bind(this));
            
            // Refresh inbox
            $('#refresh-inbox').on('click', this.loadInbox.bind(this));
            
            // Reply form
            $('#reply-form').on('submit', this.sendReply.bind(this));
            
            // Mark as read
            $('#mark-all-read').on('click', this.markAllRead.bind(this));
            
            // Auto refresh every 30 seconds
            setInterval(this.loadInbox.bind(this), 30000);
        },

        loadInbox: function() {
            var self = this;
            
            $('#inbox-loading').show();
            
            GradyzerDashboard.ajaxRequest({
                url: gradyzer_ajax.ajax_url,
                data: {
                    action: 'gradyzer_get_inbox_data'
                },
                showLoading: false,
                success: function(response) {
                    $('#inbox-loading').hide();
                    
                    if (response.success) {
                        self.threads = response.data.threads;
                        self.renderThreads(response.data.threads);
                        
                        // Update notification counts
                        GradyzerDashboard.updateNotificationCounts();
                    }
                },
                error: function() {
                    $('#inbox-loading').hide();
                }
            });
        },

        renderThreads: function(threads) {
            var $threadsList = $('#threads-list');
            
            if (!threads || threads.length === 0) {
                $('.gradyzer-inbox-layout').hide();
                $('.gradyzer-inbox-empty').show();
                return;
            }

            $('.gradyzer-inbox-empty').hide();
            $('.gradyzer-inbox-layout').show();

            var html = '';
            threads.forEach(function(thread) {
                var unreadClass = thread.is_unread ? 'unread' : 'read';
                var productHtml = '';
                
                if (thread.product) {
                    productHtml = `
                        <div class="gradyzer-thread-product">
                            <img src="${thread.product.image}" alt="${thread.product.title}" />
                            <span>${thread.product.title}</span>
                        </div>
                    `;
                }
                
                html += `
                    <div class="gradyzer-thread-item ${unreadClass}" 
                         data-sender-id="${thread.sender_id}"
                         data-message-id="${thread.latest_message_id}">
                        
                        <div class="gradyzer-thread-avatar">
                            <img src="${thread.sender_avatar}" alt="${thread.sender_name}" />
                            ${thread.is_unread ? '<span class="gradyzer-unread-indicator"></span>' : ''}
                        </div>
                        
                        <div class="gradyzer-thread-content">
                            <div class="gradyzer-thread-header">
                                <h4 class="gradyzer-sender-name">${thread.sender_name}</h4>
                                <span class="gradyzer-thread-date">${thread.latest_date_formatted}</span>
                            </div>
                            
                            ${productHtml}
                            
                            <div class="gradyzer-thread-preview">
                                ${this.truncateText(thread.latest_message, 50)}
                            </div>
                            
                            <div class="gradyzer-thread-meta">
                                <span class="gradyzer-message-count">${thread.message_count} message${thread.message_count > 1 ? 's' : ''}</span>
                                ${thread.is_unread ? '<span class="gradyzer-unread-badge">New</span>' : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $threadsList.html(html);
        },

        selectThread: function(e) {
            var $thread = $(e.currentTarget);
            var senderId = $thread.data('sender-id');
            
            // Mark as active
            $('.gradyzer-thread-item').removeClass('active');
            $thread.addClass('active');
            
            // Load thread messages
            this.loadThread(senderId);
            
            // Mark as read
            $thread.removeClass('unread').addClass('read');
            $thread.find('.gradyzer-unread-indicator, .gradyzer-unread-badge').remove();
        },

        loadThread: function(senderId) {
            var self = this;
            
            $('#thread-placeholder').hide();
            $('#thread-content').show();
            $('#messages-container').html('<div class="gradyzer-loading">Loading messages...</div>');
            
            GradyzerDashboard.ajaxRequest({
                url: gradyzer_ajax.ajax_url,
                data: {
                    action: 'gradyzer_get_thread',
                    sender_id: senderId
                },
                showLoading: false,
                success: function(response) {
                    if (response.success) {
                        self.currentThread = {
                            sender_id: senderId,
                            messages: response.data.messages,
                            product: response.data.product
                        };
                        
                        self.renderThread(response.data);
                    }
                },
                error: function() {
                    $('#messages-container').html('<div class="gradyzer-error">Failed to load messages.</div>');
                }
            });
        },

        renderThread: function(threadData) {
            var messages = threadData.messages;
            var product = threadData.product;
            
            // Update thread header
            if (messages.length > 0) {
                var firstMessage = messages[0];
                var otherUserId = firstMessage.sender_id === gradyzer_ajax.user_id ? firstMessage.receiver_id : firstMessage.sender_id;
                var otherUser = messages.find(m => m.sender_id == otherUserId || m.receiver_id == otherUserId);
                
                if (otherUser) {
                    $('#thread-user-avatar').attr('src', otherUser.sender_avatar);
                    $('#thread-user-name').text(otherUser.sender_name);
                    $('#thread-user-email').text(''); // Add email if available
                }
            }
            
            // Show product info if available
            if (product) {
                $('#thread-product-info').show();
                $('#thread-product-image').attr('src', product.image);
                $('#thread-product-title').text(product.title);
                $('#thread-product-price').text(product.price);
                $('#thread-product-link').attr('href', product.url);
            } else {
                $('#thread-product-info').hide();
            }
            
            // Render messages
            this.renderMessages(messages);
            
            // Update reply form
            $('#reply-form').data('receiver-id', this.currentThread.sender_id);
        },

        renderMessages: function(messages) {
            var html = '';
            var currentUserId = parseInt(gradyzer_ajax.user_id);
            
            messages.forEach(function(message) {
                var isCurrentUser = parseInt(message.sender_id) === currentUserId;
                var messageClass = isCurrentUser ? 'gradyzer-message-own' : 'gradyzer-message-other';
                
                html += `
                    <div class="gradyzer-message ${messageClass}">
                        <div class="gradyzer-message-avatar">
                            <img src="${message.sender_avatar}" alt="${message.sender_name}" />
                        </div>
                        <div class="gradyzer-message-content">
                            <div class="gradyzer-message-header">
                                <span class="gradyzer-message-sender">${message.sender_name}</span>
                                <span class="gradyzer-message-date">${message.date_formatted}</span>
                            </div>
                            <div class="gradyzer-message-text">${message.content}</div>
                        </div>
                    </div>
                `;
            });
            
            $('#messages-container').html(html);
            
            // Scroll to bottom
            this.scrollToBottom();
        },

        sendReply: function(e) {
            e.preventDefault();
            
            var message = $('#reply-message').val().trim();
            if (!message) {
                GradyzerDashboard.showNotification('Please enter a message.', 'error');
                return;
            }
            
            if (!this.currentThread) {
                GradyzerDashboard.showNotification('No thread selected.', 'error');
                return;
            }
            
            var self = this;
            var receiverId = this.currentThread.sender_id;
            var productId = this.currentThread.product ? this.currentThread.product.id : 0;
            
            GradyzerDashboard.ajaxRequest({
                url: gradyzer_ajax.ajax_url,
                data: {
                    action: 'gradyzer_send_reply',
                    receiver_id: receiverId,
                    message: message,
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        $('#reply-message').val('');
                        
                        // Add message to current thread display
                        var currentUser = {
                            sender_id: gradyzer_ajax.user_id,
                            sender_name: 'You',
                            sender_avatar: $('.gradyzer-user-avatar img').attr('src'),
                            content: message,
                            date_formatted: 'Just now'
                        };
                        
                        self.currentThread.messages.push(currentUser);
                        self.renderMessages(self.currentThread.messages);
                        
                        GradyzerDashboard.showNotification('Reply sent successfully!', 'success');
                        
                        // Refresh inbox to update thread list
                        setTimeout(function() {
                            self.loadInbox();
                        }, 1000);
                    }
                }
            });
        },

        markAllRead: function() {
            if (!this.currentThread) return;
            
            var self = this;
            var messageIds = this.currentThread.messages
                .filter(m => m.receiver_id == gradyzer_ajax.user_id)
                .map(m => m.id);
            
            if (messageIds.length === 0) return;
            
            GradyzerDashboard.ajaxRequest({
                url: gradyzer_ajax.ajax_url,
                data: {
                    action: 'gradyzer_mark_thread_read',
                    message_ids: messageIds
                },
                success: function(response) {
                    if (response.success) {
                        GradyzerDashboard.showNotification('Messages marked as read.', 'success');
                        
                        // Update UI
                        $('.gradyzer-thread-item.active')
                            .removeClass('unread')
                            .addClass('read')
                            .find('.gradyzer-unread-indicator, .gradyzer-unread-badge')
                            .remove();
                        
                        // Update notification counts
                        GradyzerDashboard.updateNotificationCounts();
                    }
                }
            });
        },

        scrollToBottom: function() {
            var $container = $('#messages-container');
            $container.scrollTop($container[0].scrollHeight);
        },

        truncateText: function(text, length) {
            if (text.length <= length) return text;
            return text.substring(0, length) + '...';
        }
    };

    // Initialize if on inbox page
    if ($('.gradyzer-inbox-container').length) {
        GradyzerInbox.init();
    }
});