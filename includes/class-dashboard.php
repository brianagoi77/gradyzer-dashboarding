<?php
/**
 * Dashboard Controller Class
 * File: includes/class-dashboard.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gradyzer_Dashboard {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function init() {
        // Initialize dashboard functionality
        $this->setup_dashboard_hooks();
    }
    
    private function setup_dashboard_hooks() {
        // Add dashboard-specific hooks here
        add_action('wp_ajax_gradyzer_dashboard_action', array($this, 'handle_dashboard_action'));
    }
    
    public function enqueue_scripts() {
        // Dashboard-specific scripts and styles
        if (get_query_var('gradyzer_dashboard')) {
            // Scripts are handled in main plugin file
        }
    }
    
    public function handle_dashboard_action() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        // Handle dashboard-specific AJAX actions
        wp_send_json_success();
    }
    
    public static function get_current_tab() {
        return get_query_var('gradyzer_tab', 'overview');
    }
    
    public static function get_dashboard_url() {
        return home_url('/user-dashboard/');
    }
    
    public static function get_tab_url($tab) {
        return home_url('/user-dashboard/' . $tab . '/');
    }
}