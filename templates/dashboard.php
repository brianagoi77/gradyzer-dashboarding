<?php
if (!defined('ABSPATH')) {
    exit;
}

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}

get_header();

$current_tab = get_query_var('gradyzer_tab') ?: 'overview';
$current_user = wp_get_current_user();
$user_menu_items = Gradyzer_Config::get_user_menu_items();
$current_menu_item = Gradyzer_Config::get_menu_item($current_tab);

// Handle logout action
if ($current_tab === 'logout') {
    wp_logout();
    wp_redirect(home_url());
    exit;
}

// Check if user has access to current tab
if (!isset($user_menu_items[$current_tab])) {
    $current_tab = 'overview';
    $current_menu_item = Gradyzer_Config::get_menu_item($current_tab);
}
?>

<div id="gradyzer-dashboard" class="gradyzer-dashboard-wrapper">
    <!-- Mobile Menu Toggle -->
    <button id="gradyzer-mobile-toggle" class="gradyzer-mobile-toggle" aria-label="Toggle Menu">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <!-- Sidebar Navigation -->
    <aside class="gradyzer-sidebar">
        <div class="gradyzer-sidebar-header">
            <div class="gradyzer-user-info">
                <div class="gradyzer-avatar">
                    <?php echo get_avatar($current_user->ID, 48); ?>
                </div>
                <div class="gradyzer-user-details">
                    <h3><?php echo esc_html($current_user->display_name); ?></h3>
                    <p><?php echo esc_html($current_user->user_email); ?></p>
                </div>
            </div>
        </div>

        <nav class="gradyzer-sidebar-nav">
            <ul class="gradyzer-nav-list">
                <?php foreach ($user_menu_items as $menu_key => $menu_item): ?>
                    <li class="gradyzer-nav-item <?php echo $current_tab === $menu_key ? 'active' : ''; ?>">
                        <?php if ($menu_key === 'logout'): ?>
                            <a href="<?php echo wp_logout_url(home_url()); ?>" class="gradyzer-nav-link">
                                <span class="gradyzer-nav-icon"><?php echo $menu_item['icon']; ?></span>
                                <span class="gradyzer-nav-label"><?php echo esc_html($menu_item['label']); ?></span>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo Gradyzer_Config::get_dashboard_tab_url($menu_key); ?>" class="gradyzer-nav-link">
                                <span class="gradyzer-nav-icon"><?php echo $menu_item['icon']; ?></span>
                                <span class="gradyzer-nav-label"><?php echo esc_html($menu_item['label']); ?></span>
                                <?php if (isset($menu_item['has_counter']) && $menu_key === 'inbox'): ?>
                                    <span class="gradyzer-counter" id="inbox-counter">
                                        <span class="gradyzer-counter-badge">0</span>
                                    </span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <main class="gradyzer-main-content">
        <!-- Top Bar -->
        <header class="gradyzer-topbar">
            <div class="gradyzer-topbar-left">
                <h1 class="gradyzer-page-title">
                    <span class="gradyzer-page-icon"><?php echo $current_menu_item['icon']; ?></span>
                    <?php echo esc_html($current_menu_item['label']); ?>
                </h1>
            </div>
            <div class="gradyzer-topbar-right">
                <div class="gradyzer-notifications">
                    <?php if (class_exists('Gradyzer_Messaging_Integration')): ?>
                        <a href="<?php echo Gradyzer_Config::get_dashboard_tab_url('inbox'); ?>" class="gradyzer-notification-bubble" id="gradyzer-message-bubble">
                            <span class="gradyzer-bubble-icon">📨</span>
                            <span class="gradyzer-bubble-count" id="bubble-count">0</span>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="gradyzer-user-menu">
                    <div class="gradyzer-user-avatar">
                        <?php echo get_avatar($current_user->ID, 32); ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <div class="gradyzer-content">
            <?php
            // Load the appropriate template
            if ($current_menu_item && $current_menu_item['template']) {
                $template_path = GRADYZER_PLUGIN_PATH . 'templates/tabs/' . $current_menu_item['template'];
                if (file_exists($template_path)) {
                    include $template_path;
                } else {
                    include GRADYZER_PLUGIN_PATH . 'templates/tabs/overview.php';
                }
            } else {
                include GRADYZER_PLUGIN_PATH . 'templates/tabs/overview.php';
            }
            ?>
        </div>
    </main>

    <!-- Loading Overlay -->
    <div id="gradyzer-loading" class="gradyzer-loading-overlay" style="display: none;">
        <div class="gradyzer-loading-spinner">
            <div class="gradyzer-spinner"></div>
            <p>Loading...</p>
        </div>
    </div>
</div>

<!-- Modal Container -->
<div id="gradyzer-modal-container"></div>

<?php get_footer(); ?>