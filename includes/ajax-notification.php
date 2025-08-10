<?php
/**
 * AJAX Notification Handler
 * File: includes/ajax-notification.php
 * 
 * Replace your existing ajax-notification.php with this file
 */

if (!defined('ABSPATH')) {
    exit;
}

// Remove this file if you already have the class-based approach
// This is a compatibility layer for the old function-based approach

if (!function_exists('gradyzer_ajax_get_unread_count')) {
    /**
     * Get unread message count - Legacy function
     * This is kept for backward compatibility
     */
    function gradyzer_ajax_get_unread_count() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        // Use the class-based approach if available
        if (class_exists('Gradyzer_Messaging_Integration')) {
            $messaging = new Gradyzer_Messaging_Integration();
            $count = $messaging->get_unread_count();
        } else {
            $count = 0;
        }
        
        wp_send_json_success(array('count' => $count));
    }
}

// Register the AJAX action only if not already registered by the class
if (!has_action('wp_ajax_gradyzer_get_unread_count')) {
    add_action('wp_ajax_gradyzer_get_unread_count', 'gradyzer_ajax_get_unread_count');
}
