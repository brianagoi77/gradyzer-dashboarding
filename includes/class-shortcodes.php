<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gradyzer_Shortcodes {
    
    public function __construct() {
        add_shortcode('gradyzer_dashboard', array($this, 'dashboard_shortcode'));
        add_shortcode('gradyzer_seller_phone', array($this, 'seller_phone_shortcode'));
        add_shortcode('gradyzer_notification_bubble', array($this, 'notification_bubble_shortcode'));
    }
    
    /**
     * Dashboard shortcode - renders the complete dashboard
     */
    public function dashboard_shortcode($atts) {
        // Redirect to proper dashboard URL if accessed via shortcode
        if (!get_query_var('gradyzer_dashboard')) {
            wp_redirect(Gradyzer_Config::get_dashboard_page_url());
            exit;
        }
        
        return '<!-- Dashboard rendered via template -->';
    }
    
    /**
     * Seller phone shortcode - displays seller phone number on product pages
     */
    public function seller_phone_shortcode($atts) {
        global $post;
        
        // Only work on single product pages or if product_id is specified
        $product_id = null;
        
        if (isset($atts['product_id'])) {
            $product_id = intval($atts['product_id']);
        } elseif (is_single() && get_post_type() === 'product') {
            $product_id = get_the_ID();
        } elseif (isset($post) && $post->post_type === 'product') {
            $product_id = $post->ID;
        }
        
        if (!$product_id) {
            return '';
        }
        
        $seller_phone = get_post_meta($product_id, '_gradyzer_seller_phone', true);
        
        if (empty($seller_phone)) {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'style' => 'default',
            'show_icon' => 'true',
            'show_label' => 'true',
            'label' => 'Call Seller',
            'class' => '',
            'link' => 'true'
        ), $atts);
        
        $output = '';
        $phone_clean = preg_replace('/[^+\d]/', '', $seller_phone);
        $classes = array('gradyzer-seller-phone', 'gradyzer-seller-phone-' . $atts['style']);
        
        if (!empty($atts['class'])) {
            $classes[] = $atts['class'];
        }
        
        $output .= '<div class="' . implode(' ', $classes) . '">';
        
        if ($atts['show_icon'] === 'true') {
            $output .= '<span class="gradyzer-phone-icon">📞</span>';
        }
        
        if ($atts['show_label'] === 'true') {
            $output .= '<span class="gradyzer-phone-label">' . esc_html($atts['label']) . ':</span> ';
        }
        
        if ($atts['link'] === 'true' && !empty($phone_clean)) {
            $output .= '<a href="tel:' . esc_attr($phone_clean) . '" class="gradyzer-phone-link">' . esc_html($seller_phone) . '</a>';
        } else {
            $output .= '<span class="gradyzer-phone-number">' . esc_html($seller_phone) . '</span>';
        }
        
        $output .= '</div>';
        
        // Add basic styling if not already added
        static $styles_added = false;
        if (!$styles_added) {
            $output .= '<style>
                .gradyzer-seller-phone {
                    margin: 10px 0;
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 6px;
                    border-left: 4px solid #007cba;
                }
                .gradyzer-seller-phone-minimal {
                    background: none;
                    border: none;
                    padding: 5px 0;
                }
                .gradyzer-seller-phone-button {
                    background: #007cba;
                    color: white;
                    padding: 12px 20px;
                    border-radius: 6px;
                    text-align: center;
                }
                .gradyzer-seller-phone-button .gradyzer-phone-link {
                    color: white;
                    text-decoration: none;
                    font-weight: bold;
                }
                .gradyzer-phone-icon {
                    margin-right: 8px;
                }
                .gradyzer-phone-label {
                    font-weight: bold;
                    margin-right: 5px;
                }
                .gradyzer-phone-link {
                    color: #007cba;
                    text-decoration: none;
                    font-weight: bold;
                }
                .gradyzer-phone-link:hover {
                    text-decoration: underline;
                }
            </style>';
            $styles_added = true;
        }
        
        return $output;
    }
    
    /**
     * Notification bubble shortcode - displays message notification bubble
     */
    public function notification_bubble_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'style' => 'default',
            'show_count' => 'true',
            'show_zero' => 'false',
            'class' => ''
        ), $atts);
        
        $messaging = new Gradyzer_Messaging_Integration();
        $unread_count = $messaging->get_unread_count();
        
        if ($unread_count === 0 && $atts['show_zero'] === 'false') {
            return '';
        }
        
        $classes = array('gradyzer-notification-bubble', 'gradyzer-bubble-' . $atts['style']);
        
        if (!empty($atts['class'])) {
            $classes[] = $atts['class'];
        }
        
        $output = '<a href="' . Gradyzer_Config::get_dashboard_tab_url('inbox') . '" class="' . implode(' ', $classes) . '">';
        $output .= '<span class="gradyzer-bubble-icon">📨</span>';
        
        if ($atts['show_count'] === 'true') {
            $output .= '<span class="gradyzer-bubble-count">' . $unread_count . '</span>';
        }
        
        $output .= '</a>';
        
        // Add basic styling
        static $bubble_styles_added = false;
        if (!$bubble_styles_added) {
            $output .= '<style>
                .gradyzer-notification-bubble {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 6px 12px;
                    background: #007cba;
                    color: white;
                    text-decoration: none;
                    border-radius: 20px;
                    font-size: 14px;
                    transition: all 0.3s ease;
                }
                .gradyzer-notification-bubble:hover {
                    background: #005a87;
                    transform: scale(1.05);
                }
                .gradyzer-bubble-count {
                    background: rgba(255, 255, 255, 0.3);
                    padding: 2px 8px;
                    border-radius: 10px;
                    font-weight: bold;
                    min-width: 20px;
                    text-align: center;
                }
                .gradyzer-bubble-minimal {
                    padding: 4px 8px;
                    font-size: 12px;
                }
                .gradyzer-bubble-large {
                    padding: 10px 16px;
                    font-size: 16px;
                }
            </style>';
            $bubble_styles_added = true;
        }
        
        return $output;
    }
    
    /**
     * Get shortcode usage instructions for admin
     */
    public static function get_shortcode_instructions() {
        return array(
            'gradyzer_dashboard' => array(
                'title' => 'Dashboard Shortcode',
                'shortcode' => '[gradyzer_dashboard]',
                'description' => 'Displays the complete user dashboard interface.',
                'usage' => 'Place this shortcode on any page to embed the dashboard. Best used on a dedicated dashboard page.',
                'attributes' => array()
            ),
            'gradyzer_seller_phone' => array(
                'title' => 'Seller Phone Shortcode',
                'shortcode' => '[gradyzer_seller_phone]',
                'description' => 'Displays the seller phone number on product pages.',
                'usage' => 'Add this shortcode to your single product page template or product description to show the seller contact number.',
                'attributes' => array(
                    'product_id' => 'Specific product ID (optional, auto-detects on product pages)',
                    'style' => 'Display style: default, minimal, button (default: default)',
                    'show_icon' => 'Show phone icon: true/false (default: true)',
                    'show_label' => 'Show label text: true/false (default: true)',
                    'label' => 'Custom label text (default: "Call Seller")',
                    'link' => 'Make phone number clickable: true/false (default: true)',
                    'class' => 'Additional CSS classes'
                ),
                'examples' => array(
                    '[gradyzer_seller_phone]' => 'Basic usage with default styling',
                    '[gradyzer_seller_phone style="button"]' => 'Button style display',
                    '[gradyzer_seller_phone style="minimal" show_icon="false"]' => 'Minimal style without icon',
                    '[gradyzer_seller_phone label="Contact Seller" class="my-custom-class"]' => 'Custom label and CSS class'
                )
            ),
            'gradyzer_notification_bubble' => array(
                'title' => 'Notification Bubble Shortcode',
                'shortcode' => '[gradyzer_notification_bubble]',
                'description' => 'Displays a notification bubble showing unread message count.',
                'usage' => 'Use in navigation menus, headers, or anywhere you want to show message notifications.',
                'attributes' => array(
                    'style' => 'Display style: default, minimal, large (default: default)',
                    'show_count' => 'Show message count: true/false (default: true)',
                    'show_zero' => 'Show bubble when count is zero: true/false (default: false)',
                    'class' => 'Additional CSS classes'
                ),
                'examples' => array(
                    '[gradyzer_notification_bubble]' => 'Basic notification bubble',
                    '[gradyzer_notification_bubble style="minimal"]' => 'Minimal style bubble',
                    '[gradyzer_notification_bubble show_zero="true"]' => 'Always show bubble, even with zero count'
                )
            )
        );
    }
    
    /**
     * Register shortcodes with WordPress shortcode UI (if available)
     */
    public static function register_shortcode_ui() {
        if (!function_exists('shortcode_ui_register_for_shortcode')) {
            return;
        }
        
        // Register seller phone shortcode UI
        shortcode_ui_register_for_shortcode('gradyzer_seller_phone', array(
            'label' => 'Seller Phone Number',
            'listItemImage' => 'dashicons-phone',
            'attrs' => array(
                array(
                    'label' => 'Style',
                    'attr' => 'style',
                    'type' => 'select',
                    'options' => array(
                        'default' => 'Default',
                        'minimal' => 'Minimal',
                        'button' => 'Button'
                    )
                ),
                array(
                    'label' => 'Show Icon',
                    'attr' => 'show_icon',
                    'type' => 'checkbox'
                ),
                array(
                    'label' => 'Show Label',
                    'attr' => 'show_label',
                    'type' => 'checkbox'
                ),
                array(
                    'label' => 'Custom Label',
                    'attr' => 'label',
                    'type' => 'text'
                )
            )
        ));
        
        // Register notification bubble shortcode UI
        shortcode_ui_register_for_shortcode('gradyzer_notification_bubble', array(
            'label' => 'Message Notification Bubble',
            'listItemImage' => 'dashicons-email-alt',
            'attrs' => array(
                array(
                    'label' => 'Style',
                    'attr' => 'style',
                    'type' => 'select',
                    'options' => array(
                        'default' => 'Default',
                        'minimal' => 'Minimal',
                        'large' => 'Large'
                    )
                ),
                array(
                    'label' => 'Show Count',
                    'attr' => 'show_count',
                    'type' => 'checkbox'
                ),
                array(
                    'label' => 'Show When Zero',
                    'attr' => 'show_zero',
                    'type' => 'checkbox'
                )
            )
        ));
    }
}