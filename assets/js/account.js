jQuery(document).ready(function($) {
    'use strict';

    window.GradyzerAccount = {
        init: function() {
            this.bindEvents();
            this.initPasswordStrength();
        },

        bindEvents: function() {
            // Form submission
            $('#account-form').on('submit', this.handleSubmit.bind(this));
            
            // Password strength
            $('#new_password').on('input', this.checkPasswordStrength.bind(this));
            
            // Reset form
            $('#reset-form').on('click', this.resetForm.bind(this));
            
            // Export data
            $('#export-data').on('click', this.exportData.bind(this));
            
            // Delete account
            $('#delete-account').on('click', this.deleteAccount.bind(this));
        },

        handleSubmit: function(e) {
            e.preventDefault();
            
            var formData = new FormData(e.target);
            var self = this;
            
            // Validate passwords if changing
            var newPassword = $('#new_password').val();
            var confirmPassword = $('#confirm_password').val();
            
            if (newPassword && newPassword !== confirmPassword) {
                GradyzerDashboard.showNotification('New passwords do not match.', 'error');
                return;
            }
            
            // Show loading state
            var $submitBtn = $('#account-form button[type="submit"]');
            var originalText = $submitBtn.text();
            $submitBtn.prop('disabled', true).html('<div class="gradyzer-btn-spinner"></div> Saving...');
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    // Parse response for success/error messages
                    var $response = $(response);
                    var $success = $response.find('.gradyzer-success');
                    var $error = $response.find('.gradyzer-error');
                    
                    if ($success.length) {
                        GradyzerDashboard.showNotification('Account updated successfully!', 'success');
                        
                        // Clear password fields
                        $('#current_password, #new_password, #confirm_password').val('');
                        $('#password-strength').hide();
                        
                        // Update display name in sidebar if changed
                        var newDisplayName = $('#display_name').val();
                        $('.gradyzer-user-details h3').text(newDisplayName);
                    }
                    
                    if ($error.length) {
                        var errorText = $error.text().trim();
                        GradyzerDashboard.showNotification(errorText, 'error');
                    }
                },
                error: function() {
                    GradyzerDashboard.showNotification('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },

        initPasswordStrength: function() {
            // Add password strength indicator
            if (!$('#password-strength').length) {
                var strengthHtml = `
                    <div class="gradyzer-password-strength" id="password-strength" style="display: none;">
                        <div class="gradyzer-strength-bar">
                            <div class="gradyzer-strength-fill"></div>
                        </div>
                        <span class="gradyzer-strength-text"></span>
                    </div>
                `;
                $('#confirm_password').closest('.gradyzer-form-group').after(strengthHtml);
            }
        },

        checkPasswordStrength: function() {
            var password = $('#new_password').val();
            var strength = this.calculatePasswordStrength(password);
            
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
        },

        calculatePasswordStrength: function(password) {
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
        },

        resetForm: function(e) {
            e.preventDefault();
            
            GradyzerDashboard.confirmAction(
                'Are you sure you want to reset all changes?',
                function() {
                    $('#account-form')[0].reset();
                    $('#password-strength').hide();
                    GradyzerDashboard.showNotification('Form reset successfully.', 'info');
                }
            );
        },

        exportData: function(e) {
            e.preventDefault();
            
            var self = this;
            var $btn = $(e.target);
            var originalText = $btn.text();
            
            $btn.prop('disabled', true).html('<div class="gradyzer-btn-spinner"></div> Preparing Export...');
            
            GradyzerDashboard.ajaxRequest({
                url: gradyzer_ajax.ajax_url,
                data: {
                    action: 'gradyzer_export_user_data'
                },
                success: function(response) {
                    if (response.success) {
                        // Create download link
                        var blob = new Blob([JSON.stringify(response.data, null, 2)], {
                            type: 'application/json'
                        });
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'gradyzer-account-data-' + new Date().toISOString().split('T')[0] + '.json';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                        
                        GradyzerDashboard.showNotification('Account data exported successfully!', 'success');
                    } else {
                        GradyzerDashboard.showNotification('Export failed. Please try again.', 'error');
                    }
                },
                error: function() {
                    GradyzerDashboard.showNotification('Export failed. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        deleteAccount: function(e) {
            e.preventDefault();
            
            var self = this;
            
            // Multi-step confirmation
            GradyzerDashboard.confirmAction(
                'Are you absolutely sure you want to delete your account? This will permanently delete all your data including products, messages, and profile information. This action cannot be undone.',
                function() {
                    // Second confirmation with typing requirement
                    self.showDeleteConfirmation();
                },
                {
                    title: 'Delete Account',
                    confirmText: 'Continue',
                    confirmClass: 'gradyzer-btn-danger'
                }
            );
        },

        showDeleteConfirmation: function() {
            var currentUser = $('.gradyzer-user-details h3').text();
            
            var confirmationHtml = `
                <div class="gradyzer-delete-confirmation">
                    <div class="gradyzer-warning-box">
                        <h4>⚠️ Final Confirmation</h4>
                        <p>To confirm account deletion, please type your display name: <strong>"${currentUser}"</strong></p>
                        <input type="text" id="delete-confirmation-input" placeholder="Type your display name" />
                        <p><small>This will delete all your data permanently and cannot be undone.</small></p>
                    </div>
                </div>
            `;
            
            var $modal = GradyzerDashboard.showModal(confirmationHtml, {
                title: 'Confirm Account Deletion',
                closable: true,
                className: 'gradyzer-delete-modal'
            });
            
            // Add footer buttons
            var footerHtml = `
                <button type="button" class="gradyzer-btn gradyzer-btn-outline" onclick="GradyzerDashboard.closeModal()">Cancel</button>
                <button type="button" class="gradyzer-btn gradyzer-btn-danger" id="confirm-delete-final">Delete My Account</button>
            `;
            
            $modal.find('.gradyzer-modal-body').after(`
                <div class="gradyzer-modal-footer">
                    ${footerHtml}
                </div>
            `);
            
            // Bind final confirmation
            $('#confirm-delete-final').on('click', function() {
                var typedName = $('#delete-confirmation-input').val();
                if (typedName !== currentUser) {
                    GradyzerDashboard.showNotification('Display name does not match. Please try again.', 'error');
                    return;
                }
                
                GradyzerAccount.performAccountDeletion();
            });
        },

        performAccountDeletion: function() {
            GradyzerDashboard.closeModal();
            
            GradyzerDashboard.ajaxRequest({
                url: gradyzer_ajax.ajax_url,
                data: {
                    action: 'gradyzer_delete_user_account'
                },
                success: function(response) {
                    if (response.success) {
                        GradyzerDashboard.showNotification('Account deletion request submitted. You will be contacted by an administrator.', 'info');
                    } else {
                        GradyzerDashboard.showNotification('Account deletion requires administrator approval. Please contact support.', 'warning');
                    }
                }
            });
        }
    };

    // Add custom CSS for account page
    var accountCSS = `
        <style>
        .gradyzer-password-strength {
            margin-top: 10px;
        }
        
        .gradyzer-strength-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .gradyzer-strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 4px;
        }
        
        .gradyzer-strength-fill.weak {
            background: #dc2626;
        }
        
        .gradyzer-strength-fill.medium {
            background: #d97706;
        }
        
        .gradyzer-strength-fill.strong {
            background: #059669;
        }
        
        .gradyzer-strength-text {
            font-size: 12px;
            color: #6b7280;
        }
        
        .gradyzer-delete-confirmation {
            text-align: center;
        }
        
        .gradyzer-warning-box {
            background: #fef2f2;
            border: 2px solid #fecaca;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .gradyzer-warning-box h4 {
            color: #991b1b;
            margin: 0 0 15px 0;
        }
        
        .gradyzer-warning-box input {
            width: 100%;
            padding: 10px;
            margin: 15px 0;
            border: 2px solid #fecaca;
            border-radius: 6px;
        }
        
        .gradyzer-warning-box input:focus {
            border-color: #dc2626;
            outline: none;
        }
        
        .gradyzer-delete-modal .gradyzer-modal-content {
            max-width: 500px;
        }
        
        .gradyzer-account-sections {
            display: grid;
            gap: 30px;
        }
        
        .gradyzer-account-section {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .gradyzer-section-header h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }
        
        .gradyzer-section-header p {
            margin: 0 0 20px 0;
            color: #6b7280;
            font-size: 14px;
        }
        
        .gradyzer-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .gradyzer-form-group-full {
            grid-column: 1 / -1;
        }
        
        .gradyzer-avatar-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .gradyzer-current-avatar img {
            border-radius: 50%;
            border: 3px solid #e5e7eb;
        }
        
        .gradyzer-avatar-info h4 {
            margin: 0 0 8px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .gradyzer-account-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .gradyzer-stat-item {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .gradyzer-stat-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .gradyzer-stat-value {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
        }
        
        .gradyzer-danger-section {
            border-color: #fecaca;
            background: linear-gradient(135deg, #fef2f2 0%, #fff 100%);
        }
        
        .gradyzer-danger-actions {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .gradyzer-danger-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        
        .gradyzer-danger-info h4 {
            margin: 0 0 5px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .gradyzer-danger-info p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .gradyzer-form-grid {
                grid-template-columns: 1fr;
            }
            
            .gradyzer-avatar-section {
                flex-direction: column;
                text-align: center;
            }
            
            .gradyzer-account-stats {
                grid-template-columns: 1fr;
            }
            
            .gradyzer-danger-item {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
        </style>
    `;
    
    $('head').append(accountCSS);

    // Initialize if on account page
    if ($('#account-form').length) {
        GradyzerAccount.init();
    }
});