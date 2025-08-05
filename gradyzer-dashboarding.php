<?php
/**
 * Plugin Name: Gradyzer Dashboarding
 * Plugin URI: https://github.com/gradyzer/gradyzer-dashboarding
 * Description: Complete user dashboard framework with WooCommerce integration and messaging system
 * Version: 8.0.0
 * Author: Brian Agoi
 * Requires at least: 6.6
 * Tested up to: 6.8.1
 * Requires PHP: 7.4
 * License: GPL v3
 * Text Domain: gradyzer-dashboarding
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent multiple plugin instances
if (defined('GRADYZER_PLUGIN_LOADED')) {
    return;
}
define('GRADYZER_PLUGIN_LOADED', true);

// === Plugin Constants ===
define('GRADYZER_VERSION', '8.0.0');
define('GRADYZER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GRADYZER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GRADYZER_PLUGIN_FILE', __FILE__);

// === Core Plugin Class ===
class GradyzerDashboard {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'), 5);
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Check WooCommerce dependency
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->load_dependencies();
        $this->define_hooks();
        $this->init_ajax_hooks();
    }
    
    private function load_dependencies() {
        // Safe loading - check if files exist before requiring
        $core_files = array(
            'includes/class-config.php',
            'includes/class-dashboard.php',
            'includes/class-products.php',
            'includes/class-messaging-integration.php',
            'includes/class-shortcodes.php',
            'includes/functions.php'
        );
        
        foreach ($core_files as $file) {
            $file_path = GRADYZER_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                // Log missing file
                error_log("Gradyzer Dashboard: Missing file - " . $file);
            }
        }
        
        // Load legacy files if they exist (for backward compatibility)
        $legacy_files = array(
            'includes/ajax-notification.php',
            'includes/messaging-handler.php',
            'includes/class-router.php',
            'includes/class-assets.php',
            'includes/class-user-management.php'
        );
        
        foreach ($legacy_files as $file) {
            $file_path = GRADYZER_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
        
        // Admin files
        if (is_admin()) {
            if (file_exists(GRADYZER_PLUGIN_PATH . 'admin/class-admin.php')) {
                require_once GRADYZER_PLUGIN_PATH . 'admin/class-admin.php';
                if (class_exists('Gradyzer_Admin')) {
                    new Gradyzer_Admin();
                }
            }
            
            if (file_exists(GRADYZER_PLUGIN_PATH . 'admin/class-settings.php')) {
                require_once GRADYZER_PLUGIN_PATH . 'admin/class-settings.php';
                if (class_exists('Gradyzer_Settings')) {
                    new Gradyzer_Settings();
                }
            }
        }
    }
    
    private function define_hooks() {
        // Initialize components only if classes exist
        if (class_exists('Gradyzer_Config')) {
            new Gradyzer_Config();
        }
        
        if (class_exists('Gradyzer_Dashboard')) {
            new Gradyzer_Dashboard();
        }
        
        if (class_exists('Gradyzer_Products')) {
            new Gradyzer_Products();
        }
        
        if (class_exists('Gradyzer_Messaging_Integration')) {
            new Gradyzer_Messaging_Integration();
        }
        
        if (class_exists('Gradyzer_Shortcodes')) {
            new Gradyzer_Shortcodes();
        }
        
        // Core WordPress hooks
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_assets'));
        
        // User access control - FIXED to prevent redirect loops
        add_action('template_redirect', array($this, 'dashboard_access_control'));
        
        // Hide Gradyzer Messaging notification if this plugin is active
        add_filter('wp_nav_menu_items', array($this, 'hide_messaging_bubble'), 5, 2);
    }
    
    private function init_ajax_hooks() {
        // For products
        add_action('wp_ajax_gradyzer_load_products', array($this, 'ajax_load_products'));
        add_action('wp_ajax_gradyzer_bulk_action_products', array($this, 'ajax_bulk_action_products'));
        add_action('wp_ajax_gradyzer_upload_product_image', array($this, 'ajax_upload_product_image'));
        add_action('wp_ajax_gradyzer_create_product', array($this, 'ajax_create_product'));
        add_action('wp_ajax_gradyzer_update_product_status', array($this, 'ajax_update_product_status'));
        add_action('wp_ajax_gradyzer_delete_product', array($this, 'ajax_delete_product'));
        
        // For inbox
        add_action('wp_ajax_gradyzer_get_inbox_data', array($this, 'ajax_get_inbox_data'));
        add_action('wp_ajax_gradyzer_get_thread', array($this, 'ajax_get_thread'));
        add_action('wp_ajax_gradyzer_send_reply', array($this, 'ajax_send_reply'));
        add_action('wp_ajax_gradyzer_mark_thread_read', array($this, 'ajax_mark_thread_read'));
        add_action('wp_ajax_gradyzer_get_unread_count', array($this, 'ajax_get_unread_count'));
        
        // For account
        add_action('wp_ajax_gradyzer_export_user_data', array($this, 'ajax_export_user_data'));
        add_action('wp_ajax_gradyzer_delete_user_account', array($this, 'ajax_delete_user_account'));
    }
    
    // AJAX Handlers for Products
    public function ajax_load_products() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!current_user_can('edit_products') && !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'any';
        $per_page = get_option('gradyzer_products_per_page', 15);
        
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => $status === 'any' ? array('publish', 'draft', 'private') : $status,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        if (!empty($category)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => $category
                )
            );
        }
        
        // If user can't edit all products, show only their products
        if (!current_user_can('edit_others_products')) {
            $args['author'] = get_current_user_id();
        }
        
        $query = new WP_Query($args);
        $products = array();
        
        while ($query->have_posts()) {
            $query->the_post();
            $product_id = get_the_ID();
            $product = wc_get_product($product_id);
            
            if (!$product) continue;
            
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : wc_placeholder_img_src();
            
            $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
            
            $products[] = array(
                'id' => $product_id,
                'title' => get_the_title(),
                'status' => get_post_status(),
                'price' => $product->get_price_html(),
                'image' => $image_url,
                'author' => get_the_author(),
                'date_created' => get_the_date('M j, Y'),
                'categories' => $categories,
                'stock_status' => $product->get_stock_status(),
                'stock_quantity' => $product->get_stock_quantity(),
                'edit_url' => admin_url("post.php?post={$product_id}&action=edit"),
                'view_url' => get_permalink($product_id)
            );
        }
        
        wp_reset_postdata();
        
        $response = array(
            'success' => true,
            'products' => $products,
            'pagination' => array(
                'current_page' => $page,
                'total_pages' => $query->max_num_pages,
                'total_products' => $query->found_posts
            )
        );
        
        wp_send_json_success($response);
    }
    
    public function ajax_create_product() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!current_user_can('edit_products')) {
            wp_send_json_error('Unauthorized');
        }
        
        $title = sanitize_text_field($_POST['title']);
        $description = wp_kses_post($_POST['description']);
        $price = floatval($_POST['price']);
        $sale_price = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : '';
        $stock_status = sanitize_text_field($_POST['stock_status']);
        $stock_quantity = !empty($_POST['stock_quantity']) ? intval($_POST['stock_quantity']) : '';
        $categories = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : array();
        $tags = sanitize_text_field($_POST['tags']);
        $seller_phone = sanitize_text_field($_POST['seller_phone']);
        $image_id = !empty($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        $status = sanitize_text_field($_POST['product_status']) === 'publish' ? 'publish' : 'draft';
        
        // Create product
        $product_data = array(
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => $status,
            'post_type' => 'product',
            'post_author' => get_current_user_id()
        );
        
        $product_id = wp_insert_post($product_data);
        
        if (is_wp_error($product_id)) {
            wp_send_json_error('Failed to create product');
        }
        
        // Set product type
        wp_set_object_terms($product_id, 'simple', 'product_type');
        
        // Set categories
        if (!empty($categories)) {
            wp_set_object_terms($product_id, $categories, 'product_cat');
        }
        
        // Set tags
        if (!empty($tags)) {
            $tag_names = array_map('trim', explode(';', $tags));
            wp_set_object_terms($product_id, $tag_names, 'product_tag');
        }
        
        // Set product meta
        update_post_meta($product_id, '_regular_price', $price);
        update_post_meta($product_id, '_price', $sale_price ?: $price);
        
        if ($sale_price) {
            update_post_meta($product_id, '_sale_price', $sale_price);
        }
        
        update_post_meta($product_id, '_stock_status', $stock_status);
        
        if ($stock_quantity !== '') {
            update_post_meta($product_id, '_manage_stock', 'yes');
            update_post_meta($product_id, '_stock', $stock_quantity);
        }
        
        // Set product image
        if ($image_id) {
            set_post_thumbnail($product_id, $image_id);
        }
        
        // Save seller phone
        if (!empty($seller_phone)) {
            update_post_meta($product_id, '_gradyzer_seller_phone', $seller_phone);
        }
        
        // Clear WooCommerce cache
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }
        
        wp_send_json_success(array(
            'product_id' => $product_id,
            'edit_url' => admin_url("post.php?post={$product_id}&action=edit"),
            'view_url' => get_permalink($product_id),
            'message' => 'Product created successfully!'
        ));
    }
    
    public function ajax_get_unread_count() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        $count = 0;
        if (class_exists('Gradyzer_Messaging_Integration')) {
            $messaging = new Gradyzer_Messaging_Integration();
            $count = $messaging->get_unread_count();
        }
        
        wp_send_json_success(array('count' => $count));
    }
    
    // Placeholder AJAX handlers - implement as needed
    public function ajax_bulk_action_products() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        wp_send_json_success(array('message' => 'Bulk action completed'));
    }
    
    public function ajax_upload_product_image() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        wp_send_json_success(array('message' => 'Image uploaded'));
    }
    
    public function ajax_update_product_status() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        wp_send_json_success(array('message' => 'Status updated'));
    }
    
    public function ajax_delete_product() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        wp_send_json_success(array('message' => 'Product deleted'));
    }
    
    public function ajax_get_inbox_data() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        wp_send_json_success(array('threads' => array(), 'unread_count' => 0));
    }
    
    public function ajax_get_thread() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        wp_send_json_success(array('messages' => array()));
    }
    
    public function ajax_send_reply() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        wp_send_json_success(array('message' => 'Reply sent'));
    }
    
    public function ajax_mark_thread_read() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        wp_send_json_success(array('marked_read' => true));
    }
    
    public function ajax_export_user_data() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        wp_send_json_success(array('message' => 'Data exported'));
    }
    
    public function ajax_delete_user_account() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        wp_send_json_success(array('message' => 'Account deleted'));
    }
    
    public function register_post_types() {
        // Register custom post type for seller phone numbers
        // FIXED: Shortened name to 20 characters maximum
        register_post_type('gradyzer_phone', array(
            'labels' => array(
                'name' => 'Seller Phones',
                'singular_name' => 'Seller Phone'
            ),
            'public' => false,
            'show_ui' => false,
            'supports' => array('title'),
            'capability_type' => 'post'
        ));
    }
    
    public function add_rewrite_rules() {
        add_rewrite_rule('^user-dashboard/?$', 'index.php?gradyzer_dashboard=1', 'top');
        add_rewrite_rule('^user-dashboard/([^/]+)/?$', 'index.php?gradyzer_dashboard=1&gradyzer_tab=$matches[1]', 'top');
        
        add_filter('query_vars', function($vars) {
            $vars[] = 'gradyzer_dashboard';
            $vars[] = 'gradyzer_tab';
            return $vars;
        });
        
        add_action('template_include', array($this, 'dashboard_template'));
    }
    
    public function dashboard_template($template) {
        if (get_query_var('gradyzer_dashboard')) {
            $dashboard_template = GRADYZER_PLUGIN_PATH . 'templates/dashboard.php';
            if (file_exists($dashboard_template)) {
                return $dashboard_template;
            } else {
                // Fallback to simple dashboard display
                return $this->fallback_dashboard_template();
            }
        }
        return $template;
    }
    
    private function fallback_dashboard_template() {
        // Create a temporary template file
        $temp_template = GRADYZER_PLUGIN_PATH . 'temp-dashboard.php';
        
        $content = '<?php
        get_header();
        ?>
        <div style="padding: 20px; max-width: 1200px; margin: 0 auto;">
            <h1>User Dashboard</h1>
            <?php if (is_user_logged_in()): ?>
                <p>Welcome, <?php echo wp_get_current_user()->display_name; ?>!</p>
                <p>Dashboard is loading... Please make sure all template files are in place.</p>
                <ul>
                    <li><a href="<?php echo home_url("/user-dashboard/overview/"); ?>">Overview</a></li>
                    <li><a href="<?php echo home_url("/user-dashboard/account/"); ?>">Account</a></li>
                    <li><a href="<?php echo wp_logout_url(home_url()); ?>">Logout</a></li>
                </ul>
            <?php else: ?>
                <p>Please <a href="<?php echo wp_login_url(home_url("/user-dashboard/")); ?>">login</a> to access the dashboard.</p>
            <?php endif; ?>
        </div>
        <?php
        get_footer();';
        
        file_put_contents($temp_template, $content);
        return $temp_template;
    }
    
    public function dashboard_access_control() {
        // FIXED: Prevent redirect loops
        $is_dashboard = get_query_var('gradyzer_dashboard');
        $is_login_page = $GLOBALS['pagenow'] === 'wp-login.php';
        $is_admin = is_admin();
        
        // Only redirect if:
        // 1. We're on the dashboard page
        // 2. User is not logged in
        // 3. We're not already on the login page
        // 4. We're not in admin area
        if ($is_dashboard && !is_user_logged_in() && !$is_login_page && !$is_admin) {
            // Prevent infinite redirect by checking if we're already being redirected
            if (!isset($_GET['redirect_to']) || $_GET['redirect_to'] !== home_url('/user-dashboard/')) {
                wp_redirect(wp_login_url(home_url('/user-dashboard/')));
                exit;
            }
        }
    }
    
   public function enqueue_assets() {
        if (get_query_var('gradyzer_dashboard')) {
            // Enqueue styles
            if (file_exists(GRADYZER_PLUGIN_PATH . 'assets/css/dashboard.css')) {
                wp_enqueue_style('gradyzer-dashboard', GRADYZER_PLUGIN_URL . 'assets/css/dashboard.css', array(), GRADYZER_VERSION);
            }
            
            if (file_exists(GRADYZER_PLUGIN_PATH . 'assets/css/products.css')) {
                wp_enqueue_style('gradyzer-products', GRADYZER_PLUGIN_URL . 'assets/css/products.css', array(), GRADYZER_VERSION);
            }
            
            // Enqueue main dashboard script
            if (file_exists(GRADYZER_PLUGIN_PATH . 'assets/js/dashboard.js')) {
                wp_enqueue_script('gradyzer-dashboard', GRADYZER_PLUGIN_URL . 'assets/js/dashboard.js', array('jquery'), GRADYZER_VERSION, true);
            }
            
            // Enqueue tab-specific scripts
            $current_tab = get_query_var('gradyzer_tab', 'overview');
            $tab_scripts = array(
                'products' => 'products.js',
                'add-product' => 'add-product.js',
                'inbox' => 'inbox.js',
                'account' => 'account.js'
            );
            
            if (isset($tab_scripts[$current_tab]) && file_exists(GRADYZER_PLUGIN_PATH . 'assets/js/' . $tab_scripts[$current_tab])) {
                wp_enqueue_script('gradyzer-' . $current_tab, GRADYZER_PLUGIN_URL . 'assets/js/' . $tab_scripts[$current_tab], array('jquery', 'gradyzer-dashboard'), GRADYZER_VERSION, true);
            }
            
            // Localize scripts with comprehensive data
            wp_localize_script('gradyzer-dashboard', 'gradyzer_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gradyzer_nonce'),
                'user_id' => get_current_user_id(),
                'admin_url' => admin_url(),
                'plugin_url' => GRADYZER_PLUGIN_URL,
                'dashboard_url' => home_url('/user-dashboard/'),
                'is_admin' => current_user_can('manage_options'),
                'can_edit_products' => current_user_can('edit_products'),
                'can_delete_products' => current_user_can('delete_products'),
                'current_tab' => $current_tab,
                'strings' => array(
                    'loading' => 'Loading...',
                    'error' => 'An error occurred',
                    'success' => 'Success!',
                    'confirm_delete' => 'Are you sure you want to delete this item?',
                    'copied' => 'Copied to clipboard!',
                    'no_results' => 'No results found',
                    'try_again' => 'Please try again'
                )
            ));
            
            // Enqueue media scripts for image uploads
            wp_enqueue_media();
        }
    }
    
    public function admin_enqueue_assets($hook) {
        if ('toplevel_page_gradyzer-settings' === $hook) {
            if (file_exists(GRADYZER_PLUGIN_PATH . 'assets/css/admin.css')) {
                wp_enqueue_style('gradyzer-admin', GRADYZER_PLUGIN_URL . 'assets/css/admin.css', array(), GRADYZER_VERSION);
            }
            
            if (file_exists(GRADYZER_PLUGIN_PATH . 'assets/js/admin.js')) {
                wp_enqueue_script('gradyzer-admin', GRADYZER_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), GRADYZER_VERSION, true);
            }
        }
    }
    
    public function hide_messaging_bubble($items, $args) {
        // Remove Gradyzer Messaging notification bubble if it exists
        if (class_exists('GradyzerMessaging')) {
            $items = preg_replace('/<li[^>]*class="[^"]*gradyzer-bubble-menu[^"]*"[^>]*>.*?<\/li>/is', '', $items);
        }
        return $items;
    }
    
    public function activate() {
        // Create User Dashboard page
        $page_title = 'User Dashboard';
        $page_slug = 'user-dashboard';
        $page_content = '[gradyzer_dashboard]';
        
        $existing_page = get_page_by_path($page_slug);
        
        if (!$existing_page) {
            wp_insert_post(array(
                'post_title' => $page_title,
                'post_name' => $page_slug,
                'post_content' => $page_content,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1,
                'comment_status' => 'closed'
            ));
        }
        
        // Create necessary directories
        $upload_dir = wp_upload_dir();
        $gradyzer_dir = $upload_dir['basedir'] . '/gradyzer';
        if (!file_exists($gradyzer_dir)) {
            wp_mkdir_p($gradyzer_dir);
        }
        
        // Flush rewrite rules after adding them
        $this->add_rewrite_rules();
        flush_rewrite_rules();
        
        // Set default options
        add_option('gradyzer_dashboard_version', GRADYZER_VERSION);
        add_option('gradyzer_products_per_page', 15);
        add_option('gradyzer_seller_phone_shortcode_instructions', 'Use [gradyzer_seller_phone] shortcode on single product pages to display the seller phone number.');
    }
    
    public function deactivate() {
        flush_rewrite_rules();
        
        // Clean up temporary files
        $temp_template = GRADYZER_PLUGIN_PATH . 'temp-dashboard.php';
        if (file_exists($temp_template)) {
            unlink($temp_template);
        }
    }
    
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>Gradyzer Dashboarding:</strong> This plugin requires WooCommerce to be installed and activated.</p></div>';
    }
}

// Initialize the plugin
GradyzerDashboard::get_instance();