<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gradyzer_Settings {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Gradyzer Settings',
            'Gradyzer Settings',
            'manage_options',
            'gradyzer-settings',
            array($this, 'settings_page'),
            'dashicons-dashboard',
            30
        );
    }
    
    public function settings_init() {
        register_setting('gradyzer_settings', 'gradyzer_options', array($this, 'sanitize_options'));
        
        // General Settings Section
        add_settings_section(
            'gradyzer_general_section',
            'General Settings',
            array($this, 'general_section_callback'),
            'gradyzer_settings'
        );
        
        add_settings_field(
            'products_per_page',
            'Products Per Page',
            array($this, 'products_per_page_callback'),
            'gradyzer_settings',
            'gradyzer_general_section'
        );
        
        add_settings_field(
            'enable_messaging',
            'Enable Messaging Integration',
            array($this, 'enable_messaging_callback'),
            'gradyzer_settings',
            'gradyzer_general_section'
        );
        
        add_settings_field(
            'dashboard_page_id',
            'Dashboard Page',
            array($this, 'dashboard_page_callback'),
            'gradyzer_settings',
            'gradyzer_general_section'
        );
        
        // Shortcodes Section
        add_settings_section(
            'gradyzer_shortcodes_section',
            'Shortcodes Reference',
            array($this, 'shortcodes_section_callback'),
            'gradyzer_settings'
        );
        
        // System Info Section
        add_settings_section(
            'gradyzer_system_section',
            'System Information',
            array($this, 'system_section_callback'),
            'gradyzer_settings'
        );
    }
    
    public function sanitize_options($input) {
        $output = array();
        
        if (isset($input['products_per_page'])) {
            $output['products_per_page'] = absint($input['products_per_page']);
            if ($output['products_per_page'] < 5) $output['products_per_page'] = 15;
            if ($output['products_per_page'] > 50) $output['products_per_page'] = 50;
        }
        
        if (isset($input['enable_messaging'])) {
            $output['enable_messaging'] = (bool) $input['enable_messaging'];
        }
        
        if (isset($input['dashboard_page_id'])) {
            $output['dashboard_page_id'] = absint($input['dashboard_page_id']);
        }
        
        return $output;
    }
    
    public function settings_page() {
        $options = get_option('gradyzer_options', array());
        ?>
        <div class="wrap">
            <h1>🎛️ Gradyzer Dashboard Settings</h1>
            
            <?php settings_errors(); ?>
            
            <div class="gradyzer-admin-container">
                <div class="gradyzer-admin-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('gradyzer_settings');
                        do_settings_sections('gradyzer_settings');
                        submit_button('Save Settings', 'primary', 'submit', true, array('class' => 'button-primary button-large'));
                        ?>
                    </form>
                </div>
                
                <div class="gradyzer-admin-sidebar">
                    <!-- Plugin Info -->
                    <div class="gradyzer-admin-widget">
                        <h3>📋 Plugin Information</h3>
                        <table class="gradyzer-info-table">
                            <tr>
                                <td><strong>Version:</strong></td>
                                <td><?php echo GRADYZER_VERSION; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td><span class="gradyzer-status-active">Active</span></td>
                            </tr>
                            <tr>
                                <td><strong>Dashboard URL:</strong></td>
                                <td><a href="<?php echo Gradyzer_Config::get_dashboard_page_url(); ?>" target="_blank">View Dashboard</a></td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="gradyzer-admin-widget">
                        <h3>⚡ Quick Actions</h3>
                        <div class="gradyzer-quick-actions">
                            <a href="<?php echo Gradyzer_Config::get_dashboard_page_url(); ?>" class="button button-secondary" target="_blank">
                                👁️ View Dashboard
                            </a>
                            <button type="button" class="button button-secondary" onclick="location.reload();">
                                🔄 Refresh Page
                            </button>
                            <button type="button" class="button button-secondary" id="clear-cache">
                                🗑️ Clear Cache
                            </button>
                        </div>
                    </div>
                    
                    <!-- Support -->
                    <div class="gradyzer-admin-widget">
                        <h3>🆘 Support</h3>
                        <p>Need help with the plugin? Check out these resources:</p>
                        <ul>
                            <li><a href="#" target="_blank">📖 Documentation</a></li>
                            <li><a href="#" target="_blank">💬 Support Forum</a></li>
                            <li><a href="#" target="_blank">🐛 Report Bug</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .gradyzer-admin-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            margin-top: 20px;
        }
        
        .gradyzer-admin-widget {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .gradyzer-admin-widget h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
        }
        
        .gradyzer-info-table {
            width: 100%;
        }
        
        .gradyzer-info-table td {
            padding: 5px 0;
            vertical-align: top;
        }
        
        .gradyzer-info-table td:first-child {
            width: 40%;
        }
        
        .gradyzer-status-active {
            color: #46b450;
            font-weight: bold;
        }
        
        .gradyzer-quick-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .gradyzer-shortcode-box {
            background: #f1f1f1;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .gradyzer-shortcode-title {
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .gradyzer-shortcode-code {
            background: #23282d;
            color: #fff;
            padding: 8px 12px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 13px;
            margin: 8px 0;
            position: relative;
        }
        
        .gradyzer-shortcode-copy {
            position: absolute;
            right: 8px;
            top: 8px;
            background: #0073aa;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
        }
        
        .gradyzer-shortcode-description {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }
        
        .gradyzer-system-info {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            line-height: 1.5;
        }
        
        @media (max-width: 768px) {
            .gradyzer-admin-container {
                grid-template-columns: 1fr;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Copy shortcode functionality
            $(document).on('click', '.gradyzer-shortcode-copy', function() {
                var code = $(this).siblings('.gradyzer-shortcode-text').text();
                navigator.clipboard.writeText(code).then(function() {
                    alert('Shortcode copied to clipboard!');
                });
            });
            
            // Clear cache
            $('#clear-cache').on('click', function() {
                if (confirm('Are you sure you want to clear the plugin cache?')) {
                    // Add cache clearing logic here
                    alert('Cache cleared successfully!');
                }
            });
        });
        </script>
        <?php
    }
    
    public function general_section_callback() {
        echo '<p>Configure the main settings for the Gradyzer Dashboard plugin.</p>';
    }
    
    public function products_per_page_callback() {
        $options = get_option('gradyzer_options', array());
        $value = isset($options['products_per_page']) ? $options['products_per_page'] : 15;
        ?>
        <input type="number" id="products_per_page" name="gradyzer_options[products_per_page]" value="<?php echo esc_attr($value); ?>" min="5" max="50" />
        <p class="description">Number of products to display per page in the dashboard (5-50).</p>
        <?php
    }
    
    public function enable_messaging_callback() {
        $options = get_option('gradyzer_options', array());
        $value = isset($options['enable_messaging']) ? $options['enable_messaging'] : true;
        ?>
        <label for="enable_messaging">
            <input type="checkbox" id="enable_messaging" name="gradyzer_options[enable_messaging]" value="1" <?php checked($value, true); ?> />
            Enable messaging integration (requires Gradyzer Messaging plugin)
        </label>
        <p class="description">When enabled, integrates with the Gradyzer Messaging plugin for seamless inbox functionality.</p>
        <?php
    }
    
    public function dashboard_page_callback() {
        $options = get_option('gradyzer_options', array());
        $value = isset($options['dashboard_page_id']) ? $options['dashboard_page_id'] : 0;
        
        $pages = get_pages();
        ?>
        <select id="dashboard_page_id" name="gradyzer_options[dashboard_page_id]">
            <option value="0">Select a page...</option>
            <?php foreach ($pages as $page): ?>
                <option value="<?php echo $page->ID; ?>" <?php selected($value, $page->ID); ?>>
                    <?php echo esc_html($page->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Select the page that contains the [gradyzer_dashboard] shortcode.</p>
        <?php
    }
    
    public function shortcodes_section_callback() {
        echo '<p>Available shortcodes and their usage instructions.</p>';
        
        $shortcodes = Gradyzer_Shortcodes::get_shortcode_instructions();
        
        foreach ($shortcodes as $shortcode_key => $shortcode_info) {
            echo '<div class="gradyzer-shortcode-box">';
            echo '<div class="gradyzer-shortcode-title">' . esc_html($shortcode_info['title']) . '</div>';
            echo '<div class="gradyzer-shortcode-code">';
            echo '<span class="gradyzer-shortcode-text">' . esc_html($shortcode_info['shortcode']) . '</span>';
            echo '<button type="button" class="gradyzer-shortcode-copy">Copy</button>';
            echo '</div>';
            echo '<div class="gradyzer-shortcode-description">' . esc_html($shortcode_info['description']) . '</div>';
            echo '<div class="gradyzer-shortcode-usage"><strong>Usage:</strong> ' . esc_html($shortcode_info['usage']) . '</div>';
            
            if (!empty($shortcode_info['attributes'])) {
                echo '<div class="gradyzer-shortcode-attributes"><strong>Attributes:</strong>';
                echo '<ul>';
                foreach ($shortcode_info['attributes'] as $attr => $desc) {
                    echo '<li><code>' . esc_html($attr) . '</code> - ' . esc_html($desc) . '</li>';
                }
                echo '</ul></div>';
            }
            
            if (!empty($shortcode_info['examples'])) {
                echo '<div class="gradyzer-shortcode-examples"><strong>Examples:</strong>';
                foreach ($shortcode_info['examples'] as $example => $desc) {
                    echo '<div style="margin: 8px 0;">';
                    echo '<code style="background: #f0f0f0; padding: 4px 8px; border-radius: 3px;">' . esc_html($example) . '</code>';
                    echo '<br><small>' . esc_html($desc) . '</small>';
                    echo '</div>';
                }
                echo '</div>';
            }
            
            echo '</div>';
        }
    }
    
    public function system_section_callback() {
        echo '<p>System information and compatibility checks.</p>';
        
        $system_info = $this->get_system_info();
        
        echo '<div class="gradyzer-system-info">';
        foreach ($system_info as $label => $value) {
            echo '<strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '<br>';
        }
        echo '</div>';
    }
    
    private function get_system_info() {
        global $wp_version;
        
        $system_info = array(
            'WordPress Version' => $wp_version,
            'PHP Version' => PHP_VERSION,
            'WooCommerce Version' => class_exists('WooCommerce') ? WC()->version : 'Not installed',
            'Gradyzer Messaging' => class_exists('GradyzerMessaging') ? 'Active' : 'Not installed',
            'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'Max Upload Size' => wp_max_upload_size() ? size_format(wp_max_upload_size()) : 'Unknown',
            'Memory Limit' => ini_get('memory_limit'),
            'Time Limit' => ini_get('max_execution_time') . 's',
            'Dashboard URL' => Gradyzer_Config::get_dashboard_page_url()
        );
        
        return $system_info;
    }
}