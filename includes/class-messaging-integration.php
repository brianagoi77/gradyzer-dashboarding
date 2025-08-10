<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gradyzer_Messaging_Integration {
    
    private $messaging_active = false;
    
    public function __construct() {
        $this->messaging_active = $this->is_messaging_plugin_active();
        
        if ($this->messaging_active) {
            add_action('wp_ajax_gradyzer_get_inbox_data', array($this, 'ajax_get_inbox_data'));
            add_action('wp_ajax_gradyzer_get_thread', array($this, 'ajax_get_thread'));
            add_action('wp_ajax_gradyzer_send_reply', array($this, 'ajax_send_reply'));
            add_action('wp_ajax_gradyzer_mark_read', array($this, 'ajax_mark_read'));
            
            // Hide the original messaging notification bubble
            add_filter('wp_nav_menu_items', array($this, 'hide_messaging_bubble'), 5, 2);
            
            // Add our own notification bubble to dashboard
            add_action('wp_footer', array($this, 'add_dashboard_notification_script'));
        }
    }
    
    private function is_messaging_plugin_active() {
        return class_exists('GradyzerMessaging') || function_exists('gradyzer_send_message') || 
               (defined('GRADYZER_MSG_PATH') && file_exists(GRADYZER_MSG_PATH . 'gradyzer-messaging.php'));
    }
    
    public function get_unread_count($user_id = null) {
        if (!$this->messaging_active || !is_user_logged_in()) {
            return 0;
        }
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Check if gradyzer_message post type exists
        if (!post_type_exists('gradyzer_message')) {
            return 0;
        }
        
        $messages = get_posts(array(
            'post_type' => 'gradyzer_message',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'receiver_id',
                    'value' => $user_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'is_read',
                    'value' => '0',
                    'compare' => '='
                )
            )
        ));
        
        // Count unique senders
        $senders = array();
        foreach ($messages as $message) {
            $sender_id = get_post_meta($message->ID, 'sender_id', true);
            if ($sender_id && !isset($senders[$sender_id])) {
                $senders[$sender_id] = true;
            }
        }
        
        return count($senders);
    }
    
    public function get_inbox_data($user_id = null) {
        if (!$this->messaging_active || !is_user_logged_in()) {
            return array('threads' => array(), 'unread_count' => 0);
        }
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!post_type_exists('gradyzer_message')) {
            return array('threads' => array(), 'unread_count' => 0);
        }
        
        // Get all messages for the user
        $messages = get_posts(array(
            'post_type' => 'gradyzer_message',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => 'receiver_id',
                    'value' => $user_id,
                    'compare' => '='
                )
            )
        ));
        
        $threads = array();
        $unread_count = 0;
        
        foreach ($messages as $message) {
            $sender_id = get_post_meta($message->ID, 'sender_id', true);
            $product_id = get_post_meta($message->ID, 'product_id', true);
            $is_read = get_post_meta($message->ID, 'is_read', true);
            
            if (!isset($threads[$sender_id])) {
                $sender = get_userdata($sender_id);
                $product_info = null;
                
                if ($product_id) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        $product_info = array(
                            'id' => $product_id,
                            'title' => $product->get_name(),
                            'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                            'price' => $product->get_price_html(),
                            'url' => get_permalink($product_id)
                        );
                    }
                }
                
                $threads[$sender_id] = array(
                    'sender_id' => $sender_id,
                    'sender_name' => $sender ? $sender->display_name : 'Unknown User',
                    'sender_avatar' => get_avatar_url($sender_id, array('size' => 48)),
                    'latest_message' => $message->post_content,
                    'latest_date' => get_the_date('Y-m-d H:i:s', $message->ID),
                    'latest_date_formatted' => get_the_date('M j, Y g:i A', $message->ID),
                    'is_unread' => ($is_read === '0'),
                    'message_count' => 1,
                    'product' => $product_info,
                    'latest_message_id' => $message->ID
                );
                
                if ($is_read === '0') {
                    $unread_count++;
                }
            } else {
                $threads[$sender_id]['message_count']++;
                if ($is_read === '0' && !$threads[$sender_id]['is_unread']) {
                    $threads[$sender_id]['is_unread'] = true;
                    $unread_count++;
                }
            }
        }
        
        // Sort threads by latest message date
        uasort($threads, function($a, $b) {
            return strtotime($b['latest_date']) - strtotime($a['latest_date']);
        });
        
        return array(
            'threads' => array_values($threads),
            'unread_count' => $unread_count
        );
    }
    
    public function get_thread_messages($sender_id, $receiver_id) {
        if (!$this->messaging_active || !post_type_exists('gradyzer_message')) {
            return array();
        }
        
        $messages = get_posts(array(
            'post_type' => 'gradyzer_message',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'ASC',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'relation' => 'AND',
                    array(
                        'key' => 'sender_id',
                        'value' => $sender_id,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'receiver_id',
                        'value' => $receiver_id,
                        'compare' => '='
                    )
                ),
                array(
                    'relation' => 'AND',
                    array(
                        'key' => 'sender_id',
                        'value' => $receiver_id,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'receiver_id',
                        'value' => $sender_id,
                        'compare' => '='
                    )
                )
            )
        ));
        
        $thread_messages = array();
        $product_info = null;
        
        foreach ($messages as $message) {
            $msg_sender_id = get_post_meta($message->ID, 'sender_id', true);
            $msg_receiver_id = get_post_meta($message->ID, 'receiver_id', true);
            $product_id = get_post_meta($message->ID, 'product_id', true);
            $is_read = get_post_meta($message->ID, 'is_read', true);
            
            // Mark as read if current user is receiver
            if ($msg_receiver_id == get_current_user_id() && $is_read === '0') {
                update_post_meta($message->ID, 'is_read', '1');
            }
            
            $sender = get_userdata($msg_sender_id);
            
            $thread_messages[] = array(
                'id' => $message->ID,
                'sender_id' => $msg_sender_id,
                'receiver_id' => $msg_receiver_id,
                'sender_name' => $sender ? $sender->display_name : 'Unknown User',
                'sender_avatar' => get_avatar_url($msg_sender_id, array('size' => 32)),
                'content' => $message->post_content,
                'date' => get_the_date('Y-m-d H:i:s', $message->ID),
                'date_formatted' => get_the_date('M j, Y g:i A', $message->ID),
                'is_current_user' => ($msg_sender_id == get_current_user_id())
            );
            
            // Get product info if available
            if ($product_id && !$product_info) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $product_info = array(
                        'id' => $product_id,
                        'title' => $product->get_name(),
                        'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                        'price' => $product->get_price_html(),
                        'url' => get_permalink($product_id)
                    );
                }
            }
        }
        
        return array(
            'messages' => $thread_messages,
            'product' => $product_info
        );
    }
    
    public function send_reply($sender_id, $receiver_id, $message, $product_id = 0) {
        if (!$this->messaging_active || !is_user_logged_in()) {
            return false;
        }
        
        $post_id = wp_insert_post(array(
            'post_type' => 'gradyzer_message',
            'post_title' => 'Message from User ' . $sender_id,
            'post_content' => sanitize_textarea_field($message),
            'post_status' => 'publish',
            'post_author' => $sender_id,
            'meta_input' => array(
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'product_id' => $product_id,
                'is_read' => '0'
            )
        ));
        
        return $post_id ? true : false;
    }
    
    // AJAX Handlers
    public function ajax_get_inbox_data() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        $data = $this->get_inbox_data();
        wp_send_json_success($data);
    }
    
    public function ajax_get_thread() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        $sender_id = intval($_POST['sender_id']);
        $receiver_id = get_current_user_id();
        
        $data = $this->get_thread_messages($sender_id, $receiver_id);
        wp_send_json_success($data);
    }
    
    public function ajax_send_reply() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        $receiver_id = intval($_POST['receiver_id']);
        $message = sanitize_textarea_field($_POST['message']);
        $product_id = intval($_POST['product_id']);
        $sender_id = get_current_user_id();
        
        $result = $this->send_reply($sender_id, $receiver_id, $message, $product_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Reply sent successfully'));
        } else {
            wp_send_json_error('Failed to send reply');
        }
    }
    
    public function ajax_mark_read() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        $message_id = intval($_POST['message_id']);
        $current_user_id = get_current_user_id();
        
        // Verify user is the receiver
        $receiver_id = get_post_meta($message_id, 'receiver_id', true);
        if ($receiver_id != $current_user_id) {
            wp_send_json_error('Unauthorized');
        }
        
        $result = update_post_meta($message_id, 'is_read', '1');
        wp_send_json_success(array('marked_read' => (bool) $result));
    }
    
    public function hide_messaging_bubble($items, $args) {
        // Remove gradyzer-messaging notification bubble
        $items = preg_replace('/<li[^>]*class="[^"]*gradyzer-bubble-menu[^"]*"[^>]*>.*?<\/li>/is', '', $items);
        return $items;
    }
    
    public function add_dashboard_notification_script() {
        if (!is_user_logged_in() || !get_query_var('gradyzer_dashboard')) {
            return;
        }
        ?>
        <script>
        // Update notification bubble and inbox counter
        function updateNotificationCounts() {
            if (typeof gradyzer_ajax !== 'undefined') {
                jQuery.ajax({
                    url: gradyzer_ajax.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'gradyzer_get_unread_count',
                        nonce: gradyzer_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var count = response.data.count;
                            
                            // Update bubble count
                            jQuery('#bubble-count').text(count);
                            jQuery('#gradyzer-message-bubble').toggle(count > 0);
                            
                            // Update inbox counter
                            jQuery('#inbox-counter .gradyzer-counter-badge').text(count);
                            jQuery('#inbox-counter').toggle(count > 0);
                        }
                    }
                });
            }
        }
        
        // Update counts on load and every 30 seconds
        jQuery(document).ready(function() {
            updateNotificationCounts();
            setInterval(updateNotificationCounts, 30000);
        });
        </script>
        <?php
    }
}