<?php
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$user_meta = get_user_meta($current_user->ID);

// Handle form submission
if ($_POST && isset($_POST['gradyzer_update_account']) && wp_verify_nonce($_POST['gradyzer_account_nonce'], 'gradyzer_update_account')) {
    $errors = array();
    $success_messages = array();
    
    // Update basic info
    $display_name = sanitize_text_field($_POST['display_name']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    $description = sanitize_textarea_field($_POST['description']);
    
    // Validate email
    if (!is_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (email_exists($email) && $email !== $current_user->user_email) {
        $errors[] = 'This email address is already in use.';
    }
    
    if (empty($errors)) {
        // Update user data
        $user_data = array(
            'ID' => $current_user->ID,
            'display_name' => $display_name,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'user_email' => $email,
            'description' => $description
        );
        
        $user_id = wp_update_user($user_data);
        
        if (!is_wp_error($user_id)) {
            // Update phone number
            update_user_meta($current_user->ID, 'phone', $phone);
            
            // Update WooCommerce billing info if available
            if (class_exists('WooCommerce')) {
                update_user_meta($current_user->ID, 'billing_phone', $phone);
                update_user_meta($current_user->ID, 'billing_first_name', $first_name);
                update_user_meta($current_user->ID, 'billing_last_name', $last_name);
                update_user_meta($current_user->ID, 'billing_email', $email);
            }
            
            $success_messages[] = 'Account information updated successfully!';
            
            // Refresh user data
            $current_user = wp_get_current_user();
            $user_meta = get_user_meta($current_user->ID);
        } else {
            $errors[] = 'Failed to update account information. Please try again.';
        }
    }
    
    // Handle password change
    if (!empty($_POST['new_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password)) {
            $errors[] = 'Please enter your current password.';
        } elseif (!wp_check_password($current_password, $current_user->user_pass, $current_user->ID)) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new_password) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match.';
        } else {
            wp_set_password($new_password, $current_user->ID);
            $success_messages[] = 'Password updated successfully!';
        }
    }
}

// Get current values
$display_name = $current_user->display_name;
$first_name = $current_user->first_name;
$last_name = $current_user->last_name;
$email = $current_user->user_email;
$phone = get_user_meta($current_user->ID, 'phone', true);
$description = $current_user->description;

// Get billing info from WooCommerce if available
if (class_exists('WooCommerce')) {
    if (empty($phone)) {
        $phone = get_user_meta($current_user->ID, 'billing_phone', true);
    }
    if (empty($first_name)) {
        $first_name = get_user_meta($current_user->ID, 'billing_first_name', true);
    }
    if (empty($last_name)) {
        $last_name = get_user_meta($current_user->ID, 'billing_last_name', true);
    }
}
?>

<div class="gradyzer-account-manager">
    <?php if (!empty($errors)): ?>
        <div class="gradyzer-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_messages)): ?>
        <div class="gradyzer-success">
            <ul>
                <?php foreach ($success_messages as $message): ?>
                    <li><?php echo esc_html($message); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="gradyzer-account-form" id="account-form">
        <?php wp_nonce_field('gradyzer_update_account', 'gradyzer_account_nonce'); ?>
        <input type="hidden" name="gradyzer_update_account" value="1" />

        <div class="gradyzer-account-sections">
            <!-- Profile Information -->
            <div class="gradyzer-account-section">
                <div class="gradyzer-section-header">
                    <h3>👤 Profile Information</h3>
                    <p>Update your basic account information</p>
                </div>

                <div class="gradyzer-form-grid">
                    <div class="gradyzer-form-group">
                        <label for="display_name">Display Name *</label>
                        <input type="text" id="display_name" name="display_name" value="<?php echo esc_attr($display_name); ?>" required />
                        <small>This name will be displayed publicly on your products and messages.</small>
                    </div>

                    <div class="gradyzer-form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" value="<?php echo esc_attr($email); ?>" required />
                        <small>Used for account notifications and password recovery.</small>
                    </div>

                    <div class="gradyzer-form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($first_name); ?>" />
                    </div>

                    <div class="gradyzer-form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($last_name); ?>" />
                    </div>

                    <div class="gradyzer-form-group gradyzer-form-group-full">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo esc_attr($phone); ?>" placeholder="+1 (555) 123-4567" />
                        <small>This will be used as your default contact number for product listings.</small>
                    </div>

                    <div class="gradyzer-form-group gradyzer-form-group-full">
                        <label for="description">Bio / Description</label>
                        <textarea id="description" name="description" rows="4" placeholder="Tell others about yourself..."><?php echo esc_textarea($description); ?></textarea>
                        <small>Optional bio that may be displayed on your public profile.</small>
                    </div>
                </div>
            </div>

            <!-- Avatar Section -->
            <div class="gradyzer-account-section">
                <div class="gradyzer-section-header">
                    <h3>🖼️ Profile Picture</h3>
                    <p>Manage your profile avatar</p>
                </div>

                <div class="gradyzer-avatar-section">
                    <div class="gradyzer-current-avatar">
                        <?php echo get_avatar($current_user->ID, 96); ?>
                    </div>
                    <div class="gradyzer-avatar-info">
                        <h4>Current Avatar</h4>
                        <p>Your avatar is managed through <a href="https://gravatar.com" target="_blank">Gravatar</a>. Update your Gravatar account to change your profile picture across all WordPress sites.</p>
                        <a href="https://gravatar.com" target="_blank" class="gradyzer-btn gradyzer-btn-outline">
                            🔗 Manage on Gravatar
                        </a>
                    </div>
                </div>
            </div>

            <!-- Password Change -->
            <div class="gradyzer-account-section">
                <div class="gradyzer-section-header">
                    <h3>🔒 Change Password</h3>
                    <p>Update your account password for better security</p>
                </div>

                <div class="gradyzer-password-section">
                    <div class="gradyzer-form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" />
                        <small>Enter your current password to make changes.</small>
                    </div>

                    <div class="gradyzer-form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" />
                        <small>Minimum 8 characters. Use a mix of letters, numbers, and symbols.</small>
                    </div>

                    <div class="gradyzer-form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" />
                        <small>Re-enter your new password to confirm.</small>
                    </div>

                    <div class="gradyzer-password-strength" id="password-strength" style="display: none;">
                        <div class="gradyzer-strength-bar">
                            <div class="gradyzer-strength-fill"></div>
                        </div>
                        <span class="gradyzer-strength-text"></span>
                    </div>
                </div>
            </div>

            <!-- Account Statistics -->
            <div class="gradyzer-account-section">
                <div class="gradyzer-section-header">
                    <h3>📊 Account Statistics</h3>
                    <p>Your account activity overview</p>
                </div>

                <div class="gradyzer-account-stats">
                    <div class="gradyzer-stat-item">
                        <div class="gradyzer-stat-label">Member Since</div>
                        <div class="gradyzer-stat-value"><?php echo date('F Y', strtotime($current_user->user_registered)); ?></div>
                    </div>

                    <div class="gradyzer-stat-item">
                        <div class="gradyzer-stat-label">User Role</div>
                        <div class="gradyzer-stat-value"><?php echo ucfirst(implode(', ', $current_user->roles)); ?></div>
                    </div>

                    <?php if (Gradyzer_Config::user_can_manage_products()): ?>
                        <?php
                        $user_products = get_posts(array(
                            'post_type' => 'product',
                            'author' => $current_user->ID,
                            'posts_per_page' => -1,
                            'fields' => 'ids'
                        ));
                        ?>
                        <div class="gradyzer-stat-item">
                            <div class="gradyzer-stat-label">Total Products</div>
                            <div class="gradyzer-stat-value"><?php echo count($user_products); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php
                    $messaging = new Gradyzer_Messaging_Integration();
                    $message_count = 0;
                    if (post_type_exists('gradyzer_message')) {
                        $messages = get_posts(array(
                            'post_type' => 'gradyzer_message',
                            'posts_per_page' => -1,
                            'fields' => 'ids',
                            'meta_query' => array(
                                'relation' => 'OR',
                                array('key' => 'sender_id', 'value' => $current_user->ID),
                                array('key' => 'receiver_id', 'value' => $current_user->ID)
                            )
                        ));
                        $message_count = count($messages);
                    }
                    ?>
                    <div class="gradyzer-stat-item">
                        <div class="gradyzer-stat-label">Messages</div>
                        <div class="gradyzer-stat-value"><?php echo $message_count; ?></div>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="gradyzer-account-section gradyzer-danger-section">
                <div class="gradyzer-section-header">
                    <h3>⚠️ Account Actions</h3>
                    <p>Permanent account actions</p>
                </div>

                <div class="gradyzer-danger-actions">
                    <div class="gradyzer-danger-item">
                        <div class="gradyzer-danger-info">
                            <h4>Export Account Data</h4>
                            <p>Download a copy of all your account data including products, messages, and profile information.</p>
                        </div>
                        <button type="button" class="gradyzer-btn gradyzer-btn-outline" id="export-data">
                            📥 Export Data
                        </button>
                    </div>

                    <div class="gradyzer-danger-item">
                        <div class="gradyzer-danger-info">
                            <h4>Delete Account</h4>
                            <p>Permanently delete your account and all associated data. This action cannot be undone.</p>
                        </div>
                        <button type="button" class="gradyzer-btn gradyzer-btn-danger" id="delete-account">
                            🗑️ Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="gradyzer-form-actions">
            <button type="submit" class="gradyzer-btn gradyzer-btn-primary" data-loading>
                💾 Save Changes
            </button>
            <button type="button" class="gradyzer-btn gradyzer-btn-outline" id="reset-form">
                🔄 Reset Form
            </button>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Password strength checker
    $('#new_password').on('input', function() {
        var password = $(this).val();
        var strength = checkPasswordStrength(password);
        
        var $strengthDiv = $('#password-strength');
        var $strengthBar = $('.gradyzer-strength-fill');
        var $strengthText = $('.gradyzer-strength-text');
        
        if (password.length > 0) {
            $strengthDiv.show();
            $strengthBar.css('width', strength.percentage + '%');
            $strengthBar.removeClass('weak medium strong').addClass(strength.class);
            $strengthText.text(strength.text);
        } else {
            $strengthDiv.hide();
        }
    });

    // Reset form
    $('#reset-form').on('click', function() {
        if (confirm('Are you sure you want to reset all changes?')) {
            $('#account-form')[0].reset();
            $('#password-strength').hide();
        }
    });

    // Export data
    $('#export-data').on('click', function() {
        GradyzerDashboard.showNotification('Data export feature coming soon!', 'info');
    });

    // Delete account
    $('#delete-account').on('click', function() {
        GradyzerDashboard.confirmAction(
            'Are you absolutely sure you want to delete your account? This will permanently delete all your data including products, messages, and profile information. This action cannot be undone.',
            function() {
                GradyzerDashboard.showNotification('Account deletion feature requires administrator approval. Please contact support.', 'warning');
            },
            {
                title: 'Delete Account',
                confirmText: 'Delete My Account',
                confirmClass: 'gradyzer-btn-danger'
            }
        );
    });

    function checkPasswordStrength(password) {
        var score = 0;
        var feedback = [];

        if (password.length >= 8) score += 25;
        else feedback.push('At least 8 characters');

        if (/[a-z]/.test(password)) score += 25;
        else feedback.push('Lowercase letter');

        if (/[A-Z]/.test(password)) score += 25;
        else feedback.push('Uppercase letter');

        if (/[0-9]/.test(password)) score += 25;
        else feedback.push('Number');

        if (/[^A-Za-z0-9]/.test(password)) score += 10;

        var result = {
            percentage: Math.min(score, 100),
            class: 'weak',
            text: 'Weak'
        };

        if (score >= 70) {
            result.class = 'strong';
            result.text = 'Strong';
        } else if (score >= 40) {
            result.class = 'medium';
            result.text = 'Medium';
        }

        if (feedback.length > 0 && score < 100) {
            result.text += ' - Add: ' + feedback.join(', ');
        }

        return result;
    }
});
</script>