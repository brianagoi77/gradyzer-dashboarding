<?php
/**
 * Helper Functions
 * File: includes/functions.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get current user dashboard capabilities
 */
function gradyzer_get_user_capabilities($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (class_exists('Gradyzer_Config')) {
        return Gradyzer_Config::get_user_capabilities(get_userdata($user_id));
    }
    
    // Fallback capabilities
    return array(
        'can_manage_products' => current_user_can('edit_products'),
        'can_add_products' => current_user_can('edit_products'),
        'can_delete_products' => current_user_can('delete_products'),
        'can_edit_all_products' => current_user_can('edit_others_products'),
        'menu_items' => array('overview', 'account', 'logout')
    );
}

/**
 * Get dashboard page URL
 */
function gradyzer_get_dashboard_url() {
    return home_url('/user-dashboard/');
}

/**
 * Get dashboard tab URL
 */
function gradyzer_get_tab_url($tab) {
    return home_url('/user-dashboard/' . $tab . '/');
}

/**
 * Check if current page is dashboard
 */
function gradyzer_is_dashboard() {
    return get_query_var('gradyzer_dashboard') == 1;
}

/**
 * Get current dashboard tab
 */
function gradyzer_get_current_tab() {
    return get_query_var('gradyzer_tab', 'overview');
}

/**
 * Display dashboard navigation
 */
function gradyzer_dashboard_nav() {
    if (!is_user_logged_in()) {
        return;
    }
    
    $current_tab = gradyzer_get_current_tab();
    $user_capabilities = gradyzer_get_user_capabilities();
    
    $menu_items = array(
        'overview' => array('label' => 'Overview', 'icon' => '📊'),
        'account' => array('label' => 'Account', 'icon' => '👤'),
        'logout' => array('label' => 'Logout', 'icon' => '🚪')
    );
    
    if ($user_capabilities['can_manage_products']) {
        $menu_items['products'] = array('label' => 'Products', 'icon' => '🛍️');
        $menu_items['add-product'] = array('label' => 'Add Product', 'icon' => '➕');
    }
    
    $menu_items['inbox'] = array('label' => 'Messages', 'icon' => '📨');
    
    foreach ($menu_items as $tab => $item) {
        $url = ($tab === 'logout') ? wp_logout_url(home_url()) : gradyzer_get_tab_url($tab);
        $active = ($current_tab === $tab) ? 'active' : '';
        
        echo '<a href="' . esc_url($url) . '" class="dashboard-nav-item ' . $active . '">';
        echo '<span class="nav-icon">' . $item['icon'] . '</span>';
        echo '<span class="nav-label">' . esc_html($item['label']) . '</span>';
        echo '</a>';
    }
}

/**
 * Format currency
 */
function gradyzer_format_currency($amount, $currency = '$') {
    return $currency . number_format(floatval($amount), 2);
}

/**
 * Get product image URL
 */
function gradyzer_get_product_image($product_id, $size = 'thumbnail') {
    if (has_post_thumbnail($product_id)) {
        return get_the_post_thumbnail_url($product_id, $size);
    }
    
    return GRADYZER_PLUGIN_URL . 'assets/images/placeholder.png';
}

/**
 * Truncate text
 */
function gradyzer_truncate_text($text, $length = 100) {
    return wp_trim_words($text, $length / 5);
}