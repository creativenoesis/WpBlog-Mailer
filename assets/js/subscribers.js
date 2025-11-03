/**
 * Subscribers Page JavaScript
 * 
 * Handles AJAX operations, modals, and user interactions
 * 
 * @package WP_Blog_Mailer
 * @since 2.0.0
 */

(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(document).ready(function() {
        WPBM_Subscribers.init();
    });

    /**
     * Subscribers Management Object
     */
    const WPBM_Subscribers = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initCheckboxes();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Modal triggers
            $('#wpbm-add-subscriber-btn').on('click', this.openAddModal.bind(this));
            $('#wpbm-import-btn').on('click', this.openImportModal.bind(this));
            
            // Modal close
            $('.wpbm-modal-close').on('click', this.closeModal.bind(this));
            $('.wpbm-modal').on('click', function(e) {
                if ($(e.target).hasClass('wpbm-modal')) {
                    WPBM_Subscribers.closeModal();
                }
            });
            
            // Escape key closes modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    WPBM_Subscribers.closeModal();
                }
            });
            
            // Save subscriber
            $('#wpbm-save-subscriber').on('click', this.saveSubscriber.bind(this));
            
            // Edit subscriber
            $(document).on('click', '.wpbm-edit-subscriber', this.openEditModal.bind(this));
            
            // Delete subscriber
            $(document).on('click', '.wpbm-delete-subscriber', this.deleteSubscriber.bind(this));
            
            // Bulk delete confirmation
            $('#subscribers-list-form').on('submit', this.confirmBulkDelete.bind(this));
        },

        /**
         * Initialize checkboxes (select all)
         */
        initCheckboxes: function() {
            $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
                const isChecked = $(this).prop('checked');
                $('input[name="subscriber_ids[]"]').prop('checked', isChecked);
            });

            // Update select-all when individual checkboxes change
            $('input[name="subscriber_ids[]"]').on('change', function() {
                const totalCheckboxes = $('input[name="subscriber_ids[]"]').length;
                const checkedCheckboxes = $('input[name="subscriber_ids[]"]:checked').length;
                const allChecked = totalCheckboxes === checkedCheckboxes;

                $('#cb-select-all-1, #cb-select-all-2').prop('checked', allChecked);
            });
        },

        /**
         * Open add subscriber modal
         */
        openAddModal: function(e) {
            e.preventDefault();
            
            // Reset form
            $('#wpbm-subscriber-form')[0].reset();
            $('#subscriber-id').val('');
            $('#subscriber-confirmed').prop('checked', true);
            
            // Update modal title
            $('#wpbm-modal-title').text(wpbmAjax.strings.addSubscriber || 'Add Subscriber');
            
            // Hide error
            $('.wpbm-form-error').hide();
            
            // Show modal
            $('#wpbm-subscriber-modal').addClass('active');
            
            // Focus first input
            setTimeout(function() {
                $('#subscriber-name').focus();
            }, 100);
        },

        /**
         * Open edit subscriber modal
         */
        openEditModal: function(e) {
            e.preventDefault();
            
            const subscriberId = $(this).data('id');
            const self = WPBM_Subscribers;
            
            // Show loading
            self.showLoading();
            
            // Fetch subscriber data
            $.ajax({
                url: wpbmAjax.ajaxurl,
                type: 'GET',
                data: {
                    action: 'wpbm_get_subscriber',
                    nonce: wpbmAjax.nonce,
                    id: subscriberId
                },
                success: function(response) {
                    self.hideLoading();
                    
                    if (response.success) {
                        const subscriber = response.data;
                        
                        // Populate form
                        $('#subscriber-id').val(subscriber.id);
                        $('#subscriber-name').val(subscriber.name);
                        $('#subscriber-email').val(subscriber.email);
                        $('#subscriber-confirmed').prop('checked', subscriber.confirmed == 1);
                        
                        // Update modal title
                        $('#wpbm-modal-title').text(wpbmAjax.strings.editSubscriber || 'Edit Subscriber');
                        
                        // Hide error
                        $('.wpbm-form-error').hide();
                        
                        // Show modal
                        $('#wpbm-subscriber-modal').addClass('active');
                        
                        // Focus first input
                        setTimeout(function() {
                            $('#subscriber-name').focus();
                        }, 100);
                    } else {
                        self.showError(response.data || 'Failed to load subscriber');
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.showError('An error occurred. Please try again.');
                }
            });
        },

        /**
         * Open import modal
         */
        openImportModal: function(e) {
            e.preventDefault();
            
            // Reset form
            $('#wpbm-import-form')[0].reset();
            
            // Show modal
            $('#wpbm-import-modal').addClass('active');
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.wpbm-modal').removeClass('active');
        },

        /**
         * Save subscriber (add or edit)
         */
        saveSubscriber: function(e) {
            e.preventDefault();
            
            const self = this;
            const subscriberId = $('#subscriber-id').val();
            const isEdit = subscriberId !== '';
            
            // Validate form
            if (!$('#wpbm-subscriber-form')[0].checkValidity()) {
                $('#wpbm-subscriber-form')[0].reportValidity();
                return;
            }
            
            // Get form data
            const formData = {
                action: isEdit ? 'wpbm_edit_subscriber' : 'wpbm_add_subscriber',
                nonce: wpbmAjax.nonce,
                id: subscriberId,
                name: $('#subscriber-name').val(),
                email: $('#subscriber-email').val(),
                confirmed: $('#subscriber-confirmed').is(':checked') ? 1 : 0
            };
            
            // Show loading
            self.showLoading();
            
            // Send AJAX request
            $.ajax({
                url: wpbmAjax.ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    self.hideLoading();
                    
                    if (response.success) {
                        // Close modal
                        self.closeModal();
                        
                        // Show success message
                        self.showNotice('success', response.data.message);
                        
                        // Reload page after short delay
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        // Show error in modal
                        $('.wpbm-form-error')
                            .text(response.data || 'An error occurred')
                            .show();
                    }
                },
                error: function() {
                    self.hideLoading();
                    $('.wpbm-form-error')
                        .text('An error occurred. Please try again.')
                        .show();
                }
            });
        },

        /**
         * Delete subscriber
         */
        deleteSubscriber: function(e) {
            e.preventDefault();
            
            const self = WPBM_Subscribers;
            const subscriberId = $(this).data('id');
            const subscriberName = $(this).closest('tr').find('.column-name strong').text();
            
            // Confirm deletion
            const message = wpbmAjax.strings.confirmDelete || 'Are you sure you want to delete this subscriber?';
            if (!confirm(message + '\n\n' + subscriberName)) {
                return;
            }
            
            // Show loading
            self.showLoading();
            
            // Send AJAX request
            $.ajax({
                url: wpbmAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpbm_delete_subscriber',
                    nonce: wpbmAjax.nonce,
                    id: subscriberId
                },
                success: function(response) {
                    self.hideLoading();
                    
                    if (response.success) {
                        // Remove row with animation
                        const $row = $('tr[data-id="' + subscriberId + '"]');
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if table is now empty
                            if ($('tbody tr').length === 0) {
                                window.location.reload();
                            }
                        });
                        
                        // Show success message
                        self.showNotice('success', response.data.message);
                    } else {
                        self.showError(response.data || 'Failed to delete subscriber');
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.showError('An error occurred. Please try again.');
                }
            });
        },

        /**
         * Confirm bulk delete
         */
        confirmBulkDelete: function(e) {
            // Check both top and bottom bulk action selectors
            const actionTop = $('#bulk-action-selector-top').val();
            const actionBottom = $('#bulk-action-selector-bottom').val();
            const action = actionTop !== '-1' ? actionTop : actionBottom;

            if (action === 'delete') {
                const checkedBoxes = $('input[name="subscriber_ids[]"]:checked').length;

                if (checkedBoxes === 0) {
                    e.preventDefault();
                    alert('Please select at least one subscriber.');
                    return false;
                }

                const message = wpbmAjax.strings.confirmBulkDelete ||
                    'Are you sure you want to delete the selected subscribers?';

                if (!confirm(message + '\n\n' + checkedBoxes + ' subscriber(s) will be deleted.')) {
                    e.preventDefault();
                    return false;
                }
            }
        },

        /**
         * Show loading indicator
         */
        showLoading: function() {
            $('body').addClass('wpbm-loading');
        },

        /**
         * Hide loading indicator
         */
        hideLoading: function() {
            $('body').removeClass('wpbm-loading');
        },

        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            const $notice = $('<div>')
                .addClass('notice notice-' + type + ' is-dismissible')
                .html('<p>' + message + '</p>');
            
            $('.wrap').prepend($notice);
            
            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.showNotice('error', message);
        }
    };

})(jQuery);
