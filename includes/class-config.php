<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gradyzer_Config {
    
    private static $user_roles = array();
    private static $menu_items = array();
    
    public function __construct() {
        $this->init_user_roles();
        $this->init_menu_items();
    }
    
    private function init_user_roles() {
        self::$user_roles = array(
            'administrator' => array(
                'can_manage_products' => true,
                'can_add_products' => true,
                'can_delete_products' => true,
                'can_edit_all_products' => true,
                'menu_items' => array('overview', 'products', 'add-product', 'inbox', 'account', 'logout')
            ),
            'editor' => array(
                'can_manage_products' => true,
                'can_add_products' => true,
                'can_delete_products' => true,
                'can_edit_all_products' => true,
                'menu_items' => array('overview', 'products', 'add-product', 'inbox', 'account', 'logout')
            ),
            'author' => array(
                'can_manage_products' => true,
                'can_add_products' => true,
                'can_delete_products' => false,
                'can_edit_all_products' => false,
                'menu_items' => array('overview', 'products', 'add-product', 'inbox', 'account', 'logout')
            ),
            'customer' => array(
                'can_manage_products' => false,
                'can_add_products' => false,
                'can_delete_products' => false,
                'can_edit_all_products' => false,
                'menu_items' => array('cart', 'favourites', 'inbox', 'account', 'logout')
            ),
            'subscriber' => array(
                'can_manage_products' => false,
                'can_add_products' => false,
                'can_delete_products' => false,
                'can_edit_all_products' => false,
                'menu_items' => array('cart', 'favourites', 'inbox', 'account', 'logout')
            )
        );
    }
    
    private function init_menu_items() {
        self::$menu_items = array(
            'overview' => array(
                'label' => 'Overview',
                'icon' => '📊',
                'template' => 'overview.php',
                'capability' => 'read'
            ),
            'products' => array(
                'label' => 'Products',
                'icon' => '🛍️',
                'template' => 'products.php',
                'capability' => 'edit_products'
            ),
            'add-product' => array(
                'label' => 'Add New Product',
                'icon' => '➕',
                'template' => 'add-product.php',
                'capability' => 'edit_products'
            ),
            'cart' => array(
                'label' => 'Cart',
                'icon' => '🛒',
                'template' => 'cart.php',
                'capability' => 'read'
            ),
            'favourites' => array(
                'label' => 'Favourites',
                'icon' => '❤️',
                'template' => 'favourites.php',
                'capability' => 'read'
            ),
            'inbox' => array(
                'label' => 'Inbox',
                'icon' => '📨',
                'template' => 'inbox.php',
                'capability' => 'read',
                'has_counter' => true
            ),
            'account' => array(
                'label' => 'Account',
                'icon' => '👤',
                'template' => 'account.php',
                'capability' => 'read'
            ),
            'logout' => array(
                'label' => 'Logout',
                'icon' => '🚪',
                'template' => false,
                'capability' => 'read',
                'action' => 'logout'
            )
        );
    }
    
    public static function get_user_capabilities($user = null) {
        if (!$user) {
            $user = wp_get_current_user();
        }
        
        $user_role = self::get_primary_user_role($user);
        
        return isset(self::$user_roles[$user_role]) ? self::$user_roles[$user_role] : self::$user_roles['subscriber'];
    }
    
    public static function get_user_menu_items($user = null) {
        $capabilities = self::get_user_capabilities($user);
        $menu_items = array();
        
        foreach ($capabilities['menu_items'] as $menu_key) {
            if (isset(self::$menu_items[$menu_key])) {
                $menu_item = self::$menu_items[$menu_key];
                
                // Check capability
                if ($menu_item['capability'] && !current_user_can($menu_item['capability'])) {
                    continue;
                }
                
                $menu_items[$menu_key] = $menu_item;
            }
        }
        
        return $menu_items;
    }
    
    public static function get_menu_item($key) {
        return isset(self::$menu_items[$key]) ? self::$menu_items[$key] : null;
    }
    
    public static function user_can_manage_products($user = null) {
        $capabilities = self::get_user_capabilities($user);
        return $capabilities['can_manage_products'];
    }
    
    public static function user_can_add_products($user = null) {
        $capabilities = self::get_user_capabilities($user);
        return $capabilities['can_add_products'];
    }
    
    public static function user_can_delete_products($user = null) {
        $capabilities = self::get_user_capabilities($user);
        return $capabilities['can_delete_products'];
    }
    
    public static function user_can_edit_all_products($user = null) {
        $capabilities = self::get_user_capabilities($user);
        return $capabilities['can_edit_all_products'];
    }
    
    private static function get_primary_user_role($user) {
        if (empty($user->roles)) {
            return 'subscriber';
        }
        
        // Priority order for roles
        $role_priority = array('administrator', 'editor', 'author', 'customer', 'subscriber');
        
        foreach ($role_priority as $role) {
            if (in_array($role, $user->roles)) {
                return $role;
            }
        }
        
        return $user->roles[0]; // Fallback to first role
    }
    
    public static function get_products_per_page() {
        return get_option('gradyzer_products_per_page', 15);
    }
    
    public static function get_dashboard_page_url() {
        return home_url('/user-dashboard/');
    }
    
    public static function get_dashboard_tab_url($tab) {
        return home_url('/user-dashboard/' . $tab . '/');
    }
}