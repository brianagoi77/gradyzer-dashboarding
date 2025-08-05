<?php
if (!defined('ABSPATH')) {
    exit;
}

$messaging = new Gradyzer_Messaging_Integration();
$inbox_data = $messaging->get_inbox_data();
?>

<div class="gradyzer-inbox-container">
    <?php if (empty($inbox_data['threads'])): ?>
        <div class="gradyzer-inbox-empty">
            <div class="gradyzer-empty-icon">📭</div>
            <h3>No Messages Yet</h3>
            <p>Your inbox is empty. Messages from customers and other users will appear here.</p>
        </div>
    <?php else: ?>
        <div class="gradyzer-inbox-layout">
            <!-- Threads Sidebar -->
            <div class="gradyzer-threads-sidebar">
                <div class="gradyzer-threads-header">
                    <h3>Messages (<?php echo count($inbox_data['threads']); ?>)</h3>
                    <button type="button" id="refresh-inbox" class="gradyzer-btn gradyzer-btn-sm">
                        🔄 Refresh
                    </button>
                </div>
                
                <div class="gradyzer-threads-list" id="threads-list">
                    <?php foreach ($inbox_data['threads'] as $thread): ?>
                        <div class="gradyzer-thread-item <?php echo $thread['is_unread'] ? 'unread' : 'read'; ?>" 
                             data-sender-id="<?php echo esc_attr($thread['sender_id']); ?>"
                             data-message-id="<?php echo esc_attr($thread['latest_message_id']); ?>">
                            
                            <div class="gradyzer-thread-avatar">
                                <img src="<?php echo esc_url($thread['sender_avatar']); ?>" 
                                     alt="<?php echo esc_attr($thread['sender_name']); ?>" />
                                <?php if ($thread['is_unread']): ?>
                                    <span class="gradyzer-unread-indicator"></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="gradyzer-thread-content">
                                <div class="gradyzer-thread-header">
                                    <h4 class="gradyzer-sender-name"><?php echo esc_html($thread['sender_name']); ?></h4>
                                    <span class="gradyzer-thread-date"><?php echo esc_html($thread['latest_date_formatted']); ?></span>
                                </div>
                                
                                <?php if ($thread['product']): ?>
                                    <div class="gradyzer-thread-product">
                                        <img src="<?php echo esc_url($thread['product']['image']); ?>" 
                                             alt="<?php echo esc_attr($thread['product']['title']); ?>" />
                                        <span><?php echo esc_html($thread['product']['title']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="gradyzer-thread-preview">
                                    <?php echo esc_html(wp_trim_words($thread['latest_message'], 8)); ?>
                                </div>
                                
                                <div class="gradyzer-thread-meta">
                                    <span class="gradyzer-message-count"><?php echo $thread['message_count']; ?> message<?php echo $thread['message_count'] > 1 ? 's' : ''; ?></span>
                                    <?php if ($thread['is_unread']): ?>
                                        <span class="gradyzer-unread-badge">New</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Message Thread View -->
            <div class="gradyzer-thread-view">
                <div class="gradyzer-thread-placeholder" id="thread-placeholder">
                    <div class="gradyzer-placeholder-icon">💬</div>
                    <h3>Select a conversation</h3>
                    <p>Choose a message thread from the sidebar to start viewing the conversation.</p>
                </div>

                <!-- Thread Content (Initially Hidden) -->
                <div class="gradyzer-thread-content-area" id="thread-content" style="display: none;">
                    <!-- Thread Header -->
                    <div class="gradyzer-thread-header-bar">
                        <div class="gradyzer-thread-info">
                            <div class="gradyzer-thread-user">
                                <img id="thread-user-avatar" src="" alt="" />
                                <div class="gradyzer-thread-user-details">
                                    <h3 id="thread-user-name"></h3>
                                    <span id="thread-user-email"></span>
                                </div>
                            </div>
                            
                            <div id="thread-product-info" class="gradyzer-thread-product-info" style="display: none;">
                                <img id="thread-product-image" src="" alt="" />
                                <div class="gradyzer-product-details">
                                    <h4 id="thread-product-title"></h4>
                                    <span id="thread-product-price"></span>
                                    <a id="thread-product-link" href="" target="_blank">View Product</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="gradyzer-thread-actions">
                            <button type="button" id="mark-all-read" class="gradyzer-btn gradyzer-btn-sm">
                                ✓ Mark as Read
                            </button>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="gradyzer-messages-container" id="messages-container">
                        <!-- Messages will be loaded here -->
                    </div>

                    <!-- Reply Form -->
                    <div class="gradyzer-reply-section">
                        <form id="reply-form" class="gradyzer-reply-form">
                            <div class="gradyzer-reply-input">
                                <textarea id="reply-message" placeholder="Type your reply..." rows="3" required></textarea>
                                <button type="submit" class="gradyzer-btn gradyzer-btn-primary">
                                    📤 Send Reply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Loading Indicator -->
    <div class="gradyzer-inbox-loading" id="inbox-loading" style="display: none;">
        <div class="gradyzer-loading-spinner">
            <div class="gradyzer-spinner"></div>
            <p>Loading messages...</p>
        </div>
    </div>
</div>

<!-- Message Template -->
<script type="text/template" id="message-template">
    <div class="gradyzer-message {{#is_current_user}}gradyzer-message-own{{/is_current_user}}{{^is_current_user}}gradyzer-message-other{{/is_current_user}}">
        <div class="gradyzer-message-avatar">
            <img src="{{sender_avatar}}" alt="{{sender_name}}" />
        </div>
        <div class="gradyzer-message-content">
            <div class="gradyzer-message-header">
                <span class="gradyzer-message-sender">{{sender_name}}</span>
                <span class="gradyzer-message-date">{{date_formatted}}</span>
            </div>
            <div class="gradyzer-message-text">{{content}}</div>
        </div>
    </div>
</script>

<script>
// Initialize inbox functionality
jQuery(document).ready(function($) {
    GradyzerInbox.init();
});
</script>