<?php
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$user_capabilities = Gradyzer_Config::get_user_capabilities();
$messaging = new Gradyzer_Messaging_Integration();
$unread_count = $messaging->get_unread_count();

// Get user statistics
$user_stats = array();

if ($user_capabilities['can_manage_products']) {
    // Product statistics for product managers
    $user_products = get_posts(array(
        'post_type' => 'product',
        'author' => get_current_user_id(),
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));
    
    $published_products = get_posts(array(
        'post_type' => 'product',
        'author' => get_current_user_id(),
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));
    
    $draft_products = get_posts(array(
        'post_type' => 'product',
        'author' => get_current_user_id(),
        'post_status' => 'draft',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));
    
    $user_stats['products'] = array(
        'total' => count($user_products),
        'published' => count($published_products),
        'draft' => count($draft_products)
    );
} else {
    // Customer statistics
    $user_stats['orders'] = array();
    $user_stats['cart'] = array();
    $user_stats['favourites'] = array();
    
    if (class_exists('WooCommerce')) {
        // Get customer orders
        $customer_orders = wc_get_orders(array(
            'customer' => get_current_user_id(),
            'limit' => -1
        ));
        
        $user_stats['orders'] = array(
            'total' => count($customer_orders),
            'completed' => count(array_filter($customer_orders, function($order) {
                return $order->get_status() === 'completed';
            })),
            'pending' => count(array_filter($customer_orders, function($order) {
                return in_array($order->get_status(), array('pending', 'processing'));
            }))
        );
        
        // Get cart items
        if (WC()->cart) {
            $user_stats['cart'] = array(
                'items' => WC()->cart->get_cart_contents_count(),
                'total' => WC()->cart->get_cart_total()
            );
        }
    }
}
?>

<div class="gradyzer-overview">
    <!-- Welcome Section -->
    <div class="gradyzer-welcome-section">
        <div class="gradyzer-welcome-content">
            <h2>👋 Welcome back, <?php echo esc_html($current_user->display_name); ?>!</h2>
            <p>Here's what's happening with your account today.</p>
        </div>
        <div class="gradyzer-welcome-actions">
            <?php if ($user_capabilities['can_add_products']): ?>
                <a href="<?php echo Gradyzer_Config::get_dashboard_tab_url('add-product'); ?>" class="gradyzer-btn gradyzer-btn-primary">
                    ➕ Add New Product
                </a>
            <?php endif; ?>
            <a href="<?php echo Gradyzer_Config::get_dashboard_tab_url('account'); ?>" class="gradyzer-btn gradyzer-btn-outline">
                👤 Edit Profile
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="gradyzer-stats-grid">
        <?php if ($user_capabilities['can_manage_products']): ?>
            <!-- Product Manager Stats -->
            <div class="gradyzer-stat-card">
                <div class="gradyzer-stat-icon">📦</div>
                <div class="gradyzer-stat-content">
                    <h3><?php echo $user_stats['products']['total']; ?></h3>
                    <p>Total Products</p>
                </div>
                <div class="gradyzer-stat-link">
                    <a href="<?php echo Gradyzer_Config::get_dashboard_tab_url('products'); ?>">View All</a>
                </div>
            </div>

            <div class="gradyzer-stat-card">
                <div class="gradyzer-stat-icon">✅</div>
                <div class="gradyzer-stat-content">
                    <h3><?php echo $user_stats['products']['published']; ?></h3>
                    <p>Published</p>
                </div>
                <div class="gradyzer-stat-link">
                    <a href="<?php echo Gradyzer_Config::get_dashboard_tab_url('products'); ?>?status=publish">View</a>
                </div>
            </div>

            <div class="gradyzer-stat-card">
                <div class="gradyzer-stat-icon">📝</div>
                <div class="gradyzer-stat-content">
                    <h3><?php echo $user_stats['products']['draft']; ?></h3>
                    <p>Draft Products</p>
                </div>
                <div class="gradyzer-stat-link">
                    <a href="<?php echo Gradyzer_Config::get_dashboard_tab_url('products'); ?>?status=draft">View</a>
                </div>
            </div>

        <?php else: ?>
            <!-- Customer Stats -->
            <?php if (isset($user_stats['orders'])): ?>
                <div class="gradyzer-stat-card">
                    <div class="gradyzer-stat-icon">🛒</div>
                    <div class="gradyzer-stat-content">
                        <h3><?php echo $user_stats['orders']['total']; ?></h3>
                        <p>Total Orders</p>
                    </div>
                    <div class="gradyzer-stat-link">
                        <a href="<?php echo wc_get_account_endpoint_url('orders'); ?>" target="_blank">View All</a>
                    </div>
                </div>

                <div class="gradyzer-stat-card">
                    <div class="gradyzer-stat-icon">✅</div>
                    <div class="gradyzer-stat-content">
                        <h3><?php echo $user_stats['orders']['completed']; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>

                <div class="gradyzer-stat-card">
                    <div class="gradyzer-stat-icon">⏳</div>
                    <div class="gradyzer-stat-content">
                        <h3><?php echo $user_stats['orders']['pending']; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($user_stats['cart']) && $user_stats['cart']['items'] > 0): ?>
                <div class="gradyzer-stat-card">
                    <div class="gradyzer-stat-icon">🛍️</div>
                    <div class="gradyzer-stat-content">
                        <h3><?php echo $user_stats['cart']['items']; ?></h3>
                        <p>Items in Cart</p>
                    </div>
                    <div class="gradyzer-stat-link">
                        <a href="<?php echo Gradyzer_Config::get_dashboard_tab_url('cart'); ?>">View Cart</a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Messages Stat (Common for all users) -->
        <div class="gradyzer-stat-card <?php echo $unread_count > 0 ? 'gradyzer-stat-highlight' : ''; ?>">
            <div class="gradyzer-stat-icon">📨</div>
            <div class="gradyzer-stat-content">
                <h3><?php echo $unread_count; ?></h3>
                <p>Unread Messages</p>
            </div>
            <div class="gradyzer-stat-link">
                <a href="<?php echo Gradyzer_Config::get_dashboard_tab_url('inbox'); ?>">View Inbox</a>
            </div>
        </div>
    </div>

    <!-- Quick Actions Section -->
    <div class="gradyzer-quick-actions">
        <h3>🚀 Quick Actions</h3>
        <div class="gradyzer-actions-grid">
            <?php if ($user_capabilities['can_manage_products']): ?>
                <div class="gradyzer-action-card">
                    <div class="gradyzer-action-icon">➕</div>
                    <div class="gradyzer-action-content">
                        <h4>Add New Product</h4>
                        <p>Create and publish a new product listing</p>
                    </div>
                    <a href="<?php echo Gradyzer_Config::get_dashboard_tab_url('add-product'); ?>" class="gradyzer-action-btn gradyzer-btn gradyzer-btn-primary">Get Started</a>
                </div>

                <div class="gradyzer-action-card">
                    <div class="gradyzer-action-icon">📊</div>
                    <div class="gradyzer-action-content">
                        <h4>Manage Products</h4>
                        <p>Edit, update, or delete existing products</p>
                    </div>
                    <a href="<?php echo Gradyzer_Config::get_dashboard_tab_url('products'); ?>" class="gradyzer-action-btn gradyzer-btn gradyzer-btn-outline">Manage</a>
                </div>
            <?php else: ?>
                <div class="gradyzer-action-card">
                    <div class="gradyzer-action-icon">🛒</div>
                    <div class="gradyzer-action-content">
                        <h4>Shop Products</h4>
                        <p>Browse and purchase products</p>
                    </div>
                    <a href="<?php echo get_permalink(wc_get_page_id('shop')); ?>" class="gradyzer-action-btn gradyzer-btn gradyzer-btn-primary">Shop Now</a>
                </div>

                <div class="gradyzer-action-card">
                    <div class="gradyzer-action-icon">❤️</div>
                    <div class="gradyzer-action-content">
                        <h4>My Favourites</h4>
                        <p>View your favourite products</p>
                    </div>
                    <a href="<?php echo Gradyzer_Config::get_dashboard_tab_url('favourites'); ?>" class="gradyzer-action-btn gradyzer-btn gradyzer-btn-outline">View</a>
                </div>
            <?php endif; ?>

            <div class="gradyzer-action-card">
                <div class="gradyzer-action-icon">💬</div>
                <div class="gradyzer-action-content">
                    <h4>Messages</h4>
                    <p>Check and respond to messages</p>
                </div>
                <a href="<?php echo Gradyzer_Config::get_dashboard_tab_url('inbox'); ?>" class="gradyzer-action-btn gradyzer-btn gradyzer-btn-outline">
                    View Inbox <?php if ($unread_count > 0): ?><span class="gradyzer-badge"><?php echo $unread_count; ?></span><?php endif; ?>
                </a>
            </div>

            <div class="gradyzer-action-card">
                <div class="gradyzer-action-icon">👤</div>
                <div class="gradyzer-action-content">
                    <h4>Account Settings</h4>
                    <p>Update your profile and preferences</p>
                </div>
                <a href="<?php echo Gradyzer_Config::get_dashboard_tab_url('account'); ?>" class="gradyzer-action-btn gradyzer-btn gradyzer-btn-outline">Settings</a>
            </div>
        </div>
    </div>

    <!-- Recent Activity Section -->
    <div class="gradyzer-recent-activity">
        <h3>📈 Recent Activity</h3>
        <div class="gradyzer-activity-list">
            <?php
            $recent_activities = array();
            
            if ($user_capabilities['can_manage_products']) {
                // Get recent products
                $recent_products = get_posts(array(
                    'post_type' => 'product',
                    'author' => get_current_user_id(),
                    'posts_per_page' => 5,
                    'orderby' => 'date',
                    'order' => 'DESC'
                ));
                
                foreach ($recent_products as $product) {
                    $recent_activities[] = array(
                        'type' => 'product',
                        'icon' => '📦',
                        'title' => 'Product: ' . $product->post_title,
                        'description' => 'Status: ' . ucfirst($product->post_status),
                        'date' => get_the_date('M j, Y', $product->ID),
                        'url' => admin_url("post.php?post={$product->ID}&action=edit")
                    );
                }
            } else {
                // Get recent orders for customers
                if (class_exists('WooCommerce')) {
                    $recent_orders = wc_get_orders(array(
                        'customer' => get_current_user_id(),
                        'limit' => 5,
                        'orderby' => 'date',
                        'order' => 'DESC'
                    ));
                    
                    foreach ($recent_orders as $order) {
                        $recent_activities[] = array(
                            'type' => 'order',
                            'icon' => '🛒',
                            'title' => 'Order #' . $order->get_order_number(),
                            'description' => 'Status: ' . ucfirst($order->get_status()) . ' - ' . $order->get_formatted_order_total(),
                            'date' => $order->get_date_created()->format('M j, Y'),
                            'url' => $order->get_view_order_url()
                        );
                    }
                }
            }
            
            // Get recent messages
            if (post_type_exists('gradyzer_message')) {
                $recent_messages = get_posts(array(
                    'post_type' => 'gradyzer_message',
                    'posts_per_page' => 3,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'meta_query' => array(
                        'relation' => 'OR',
                        array(
                            'key' => 'sender_id',
                            'value' => get_current_user_id(),
                            'compare' => '='
                        ),
                        array(
                            'key' => 'receiver_id',
                            'value' => get_current_user_id(),
                            'compare' => '='
                        )
                    )
                ));
                
                foreach ($recent_messages as $message) {
                    $sender_id = get_post_meta($message->ID, 'sender_id', true);
                    $receiver_id = get_post_meta($message->ID, 'receiver_id', true);
                    $is_sent = ($sender_id == get_current_user_id());
                    
                    $other_user_id = $is_sent ? $receiver_id : $sender_id;
                    $other_user = get_userdata($other_user_id);
                    
                    $recent_activities[] = array(
                        'type' => 'message',
                        'icon' => $is_sent ? '📤' : '📥',
                        'title' => ($is_sent ? 'Sent to: ' : 'Received from: ') . ($other_user ? $other_user->display_name : 'Unknown User'),
                        'description' => wp_trim_words($message->post_content, 10),
                        'date' => get_the_date('M j, Y', $message->ID),
                        'url' => Gradyzer_Config::get_dashboard_tab_url('inbox')
                    );
                }
            }
            
            // Sort all activities by date
            usort($recent_activities, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
            
            // Limit to 8 activities
            $recent_activities = array_slice($recent_activities, 0, 8);
            
            if (empty($recent_activities)): ?>
                <div class="gradyzer-no-activity">
                    <div class="gradyzer-no-activity-icon">🌟</div>
                    <h4>No recent activity</h4>
                    <p>Start by <?php echo $user_capabilities['can_manage_products'] ? 'adding a new product' : 'browsing products'; ?> to see activity here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="gradyzer-activity-item">
                        <div class="gradyzer-activity-icon"><?php echo $activity['icon']; ?></div>
                        <div class="gradyzer-activity-content">
                            <h5><?php echo esc_html($activity['title']); ?></h5>
                            <p><?php echo esc_html($activity['description']); ?></p>
                            <span class="gradyzer-activity-date"><?php echo esc_html($activity['date']); ?></span>
                        </div>
                        <?php if ($activity['url']): ?>
                            <div class="gradyzer-activity-action">
                                <a href="<?php echo esc_url($activity['url']); ?>" class="gradyzer-btn gradyzer-btn-sm gradyzer-btn-outline">View</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>