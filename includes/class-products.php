<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gradyzer_Products {
    
    public function __construct() {
        add_action('wp_ajax_gradyzer_create_product', array($this, 'ajax_create_product'));
        add_action('wp_ajax_gradyzer_upload_product_image', array($this, 'ajax_upload_product_image'));
        add_action('wp_ajax_gradyzer_create_category', array($this, 'ajax_create_category'));
        add_action('wp_ajax_gradyzer_delete_category', array($this, 'ajax_delete_category'));
        add_action('wp_ajax_gradyzer_create_tag', array($this, 'ajax_create_tag'));
        add_action('wp_ajax_gradyzer_save_seller_phone', array($this, 'ajax_save_seller_phone'));
        add_action('wp_ajax_gradyzer_bulk_action_products', array($this, 'ajax_bulk_action_products'));
        
        // Add seller phone meta box to product edit page
        add_action('add_meta_boxes', array($this, 'add_seller_phone_meta_box'));
        add_action('save_post', array($this, 'save_seller_phone_meta'));
    }
    
    public function get_products_ajax($page = 1, $search = '', $category = '', $status = 'any') {
        $per_page = Gradyzer_Config::get_products_per_page();
        $offset = ($page - 1) * $per_page;
        
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $per_page,
            'offset' => $offset,
            'post_status' => $status === 'any' ? array('publish', 'draft', 'private') : $status,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        // Add search
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        // Add category filter
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
        if (!Gradyzer_Config::user_can_edit_all_products()) {
            $args['author'] = get_current_user_id();
        }
        
        $query = new WP_Query($args);
        $products = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);
                
                if (!$product) continue;
                
                $products[] = array(
                    'id' => $product_id,
                    'title' => get_the_title(),
                    'status' => get_post_status(),
                    'price' => $product->get_price_html(),
                    'regular_price' => $product->get_regular_price(),
                    'sale_price' => $product->get_sale_price(),
                    'stock_status' => $product->get_stock_status(),
                    'stock_quantity' => $product->get_stock_quantity(),
                    'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                    'categories' => wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names')),
                    'tags' => wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names')),
                    'date_created' => get_the_date('Y-m-d H:i:s'),
                    'author' => get_the_author(),
                    'seller_phone' => get_post_meta($product_id, '_gradyzer_seller_phone', true),
                    'edit_url' => admin_url("post.php?post={$product_id}&action=edit"),
                    'view_url' => get_permalink($product_id)
                );
            }
        }
        
        wp_reset_postdata();
        
        // Get total count for pagination
        $total_args = $args;
        $total_args['posts_per_page'] = -1;
        $total_args['fields'] = 'ids';
        unset($total_args['offset']);
        
        $total_query = new WP_Query($total_args);
        $total_products = $total_query->found_posts;
        $total_pages = ceil($total_products / $per_page);
        
        return array(
            'products' => $products,
            'pagination' => array(
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_products' => $total_products,
                'per_page' => $per_page
            )
        );
    }
    
    public function ajax_create_product() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!current_user_can('edit_products')) {
            wp_send_json_error('Unauthorized');
        }
        
        $title = sanitize_text_field($_POST['title']);
        $description = wp_kses_post($_POST['description']);
        $price = floatval($_POST['price']);
        $sale_price = floatval($_POST['sale_price']);
        $image_id = intval($_POST['image_id']);
        $categories = array_map('intval', (array) $_POST['categories']);
        $tags = sanitize_text_field($_POST['tags']);
        $seller_phone = sanitize_text_field($_POST['seller_phone']);
        $stock_status = sanitize_text_field($_POST['stock_status']);
        $stock_quantity = intval($_POST['stock_quantity']);
        
        // Create WooCommerce product
        $product = new WC_Product_Simple();
        $product->set_name($title);
        $product->set_description($description);
        $product->set_regular_price($price);
        
        if ($sale_price > 0 && $sale_price < $price) {
            $product->set_sale_price($sale_price);
        }
        
        $product->set_stock_status($stock_status);
        if ($stock_quantity > 0) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity($stock_quantity);
        }
        
        if ($image_id) {
            $product->set_image_id($image_id);
        }
        
        $product->set_status('publish');
        $product_id = $product->save();
        
        if ($product_id) {
            // Set author
            wp_update_post(array(
                'ID' => $product_id,
                'post_author' => get_current_user_id()
            ));
            
            // Set categories
            if (!empty($categories)) {
                wp_set_post_terms($product_id, $categories, 'product_cat');
            }
            
            // Set tags
            if (!empty($tags)) {
                $tag_names = array_map('trim', explode(';', $tags));
                wp_set_post_terms($product_id, $tag_names, 'product_tag');
            }
            
            // Save seller phone
            if (!empty($seller_phone)) {
                update_post_meta($product_id, '_gradyzer_seller_phone', $seller_phone);
            }
            
            wp_send_json_success(array(
                'product_id' => $product_id,
                'message' => 'Product created successfully!'
            ));
        } else {
            wp_send_json_error('Failed to create product');
        }
    }
    
    public function ajax_upload_product_image() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!current_user_can('edit_products')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $uploadedfile = $_FILES['image'];
        $upload_overrides = array('test_form' => false);
        
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            $attachment = array(
                'post_mime_type' => $movefile['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', basename($movefile['file'])),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attach_id = wp_insert_attachment($attachment, $movefile['file']);
            
            if (!is_wp_error($attach_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
                wp_update_attachment_metadata($attach_id, $attach_data);
                
                wp_send_json_success(array(
                    'attachment_id' => $attach_id,
                    'thumbnail_url' => wp_get_attachment_image_url($attach_id, 'thumbnail'),
                    'full_url' => wp_get_attachment_url($attach_id)
                ));
            }
        }
        
        wp_send_json_error('Failed to upload image');
    }
    
    public function ajax_create_category() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!current_user_can('edit_products')) {
            wp_send_json_error('Unauthorized');
        }
        
        $category_name = sanitize_text_field($_POST['category_name']);
        $parent_id = intval($_POST['parent_id']);
        
        $result = wp_insert_term($category_name, 'product_cat', array(
            'parent' => $parent_id
        ));
        
        if (!is_wp_error($result)) {
            $term = get_term($result['term_id'], 'product_cat');
            wp_send_json_success(array(
                'term_id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug
            ));
        } else {
            wp_send_json_error($result->get_error_message());
        }
    }
    
    public function ajax_delete_category() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!current_user_can('edit_products')) {
            wp_send_json_error('Unauthorized');
        }
        
        $term_id = intval($_POST['term_id']);
        $result = wp_delete_term($term_id, 'product_cat');
        
        if (!is_wp_error($result)) {
            wp_send_json_success('Category deleted successfully');
        } else {
            wp_send_json_error($result->get_error_message());
        }
    }
    
    public function ajax_create_tag() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!current_user_can('edit_products')) {
            wp_send_json_error('Unauthorized');
        }
        
        $tag_names = sanitize_text_field($_POST['tag_names']);
        $tags = array_map('trim', explode(';', $tag_names));
        $created_tags = array();
        
        foreach ($tags as $tag_name) {
            if (!empty($tag_name)) {
                $result = wp_insert_term($tag_name, 'product_tag');
                if (!is_wp_error($result)) {
                    $term = get_term($result['term_id'], 'product_tag');
                    $created_tags[] = array(
                        'term_id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug
                    );
                }
            }
        }
        
        wp_send_json_success($created_tags);
    }
    
    public function ajax_save_seller_phone() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!current_user_can('edit_products')) {
            wp_send_json_error('Unauthorized');
        }
        
        $product_id = intval($_POST['product_id']);
        $seller_phone = sanitize_text_field($_POST['seller_phone']);
        
        $result = update_post_meta($product_id, '_gradyzer_seller_phone', $seller_phone);
        
        wp_send_json_success(array(
            'saved' => $result,
            'message' => 'Seller phone number updated successfully!'
        ));
    }
    
    public function ajax_bulk_action_products() {
        check_ajax_referer('gradyzer_nonce', 'nonce');
        
        if (!current_user_can('edit_products')) {
            wp_send_json_error('Unauthorized');
        }
        
        $action = sanitize_text_field($_POST['action']);
        $product_ids = array_map('intval', (array) $_POST['product_ids']);
        $results = array();
        
        foreach ($product_ids as $product_id) {
            // Check if user can edit this specific product
            if (!Gradyzer_Config::user_can_edit_all_products()) {
                $product_author = get_post_field('post_author', $product_id);
                if ($product_author != get_current_user_id()) {
                    continue;
                }
            }
            
            switch ($action) {
                case 'delete':
                    if (Gradyzer_Config::user_can_delete_products()) {
                        $result = wp_delete_post($product_id, true);
                        $results[$product_id] = (bool) $result;
                    }
                    break;
                    
                case 'draft':
                case 'publish':
                case 'private':
                    $result = wp_update_post(array(
                        'ID' => $product_id,
                        'post_status' => $action
                    ));
                    $results[$product_id] = (bool) $result;
                    break;
            }
        }
        
        wp_send_json_success($results);
    }
    
    public function add_seller_phone_meta_box() {
        add_meta_box(
            'gradyzer_seller_phone',
            'Seller Phone Number',
            array($this, 'seller_phone_meta_box_callback'),
            'product',
            'side',
            'default'
        );
    }
    
    public function seller_phone_meta_box_callback($post) {
        wp_nonce_field('gradyzer_seller_phone_nonce', 'gradyzer_seller_phone_nonce_field');
        $value = get_post_meta($post->ID, '_gradyzer_seller_phone', true);
        
        echo '<label for="gradyzer_seller_phone">Phone Number:</label>';
        echo '<input type="text" id="gradyzer_seller_phone" name="gradyzer_seller_phone" value="' . esc_attr($value) . '" style="width: 100%;" />';
        echo '<p><small>This phone number will be displayed on the product page using the [gradyzer_seller_phone] shortcode.</small></p>';
    }
    
    public function save_seller_phone_meta($post_id) {
        if (!isset($_POST['gradyzer_seller_phone_nonce_field'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['gradyzer_seller_phone_nonce_field'], 'gradyzer_seller_phone_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_product', $post_id)) {
            return;
        }
        
        if (isset($_POST['gradyzer_seller_phone'])) {
            $seller_phone = sanitize_text_field($_POST['gradyzer_seller_phone']);
            update_post_meta($post_id, '_gradyzer_seller_phone', $seller_phone);
        }
    }
    
    public static function get_product_categories() {
        return get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
    }
    
    public static function get_product_tags() {
        return get_terms(array(
            'taxonomy' => 'product_tag',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
    }
}