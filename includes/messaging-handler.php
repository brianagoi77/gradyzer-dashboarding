<?php
/**
 * Messaging Handler
 * File: includes/messaging-handler.php
 * 
 * Replace your existing messaging-handler.php with this file
 */

if (!defined('ABSPATH')) {
    exit;
}

// This file provides additional messaging functionality
// All main functionality is now in class-messaging-integration.php

if (!function_exists('gradyzer_messaging_init')) {
    /**
     * Initialize messaging functionality
     */
    function gradyzer_messaging_init() {
        // Check if messaging integration class exists
        if (class_exists('Gradyzer_Messaging_Integration')) {
            // Messaging is handled by the class
            return;
        }
        
        // Fallback initialization if class doesn't exist
        add_action('wp_ajax_gradyzer_get_unread_count', 'gradyzer_messaging_get_unread_count_fallback');
    }
}

if (!function_exists('gradyzer_messaging_get_unread_count_fallback')) {
    /**
     * Fallback function for getting unread count
     * Only used if the main class is not available
     */
    function gradyzer_messaging_get_unread_count_fallback() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        // Simple fallback - return 0 if no proper messaging system
        wp_send_json_success(array('count' => 0));
    }
}

if (!function_exists('gradyzer_send_message')) {
    /**
     * Send a message - utility function
     */
    function gradyzer_send_message($sender_id, $receiver_id, $message, $product_id = 0) {
        if (!post_type_exists('gradyzer_message')) {
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
}

// Initialize messaging
add_action('init', 'gradyzer_messaging_init');
