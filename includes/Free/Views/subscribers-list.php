<?php
/**
 * Subscribers List View
 * * Displays subscriber table with search, filtering, and pagination
 * * @package WP_Blog_Mailer
 * @subpackage Free\Views
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

// The $service variable is provided by SubscribersController.php

// Get current page and search parameters
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Get tag service for Pro users
$tag_model = null;
$available_tags = array();
if (wpbm_is_pro()) {
    $tag_model = new \WPBlogMailer\Common\Models\Tag();
    $tags_result = $tag_model->get_all(array('per_page' => 999999, 'orderby' => 'name'));
    $available_tags = $tags_result['tags'];
}

// Get subscribers using the correct service method
$result = $service->get_all(array(
    'page' => $current_page,
    'per_page' => 20, // Set items per page
    'search' => $search,
    'status' => $status
));

// Get statistics using the correct service method
$stats = $service->get_stats();
?>

<div class="wrap wpbm-subscribers-page">
    <h1 class="wp-heading-inline"><?php _e('Subscribers', 'wpblogmailer'); ?></h1>
    <button type="button" class="page-title-action" id="wpbm-add-subscriber-btn">
        <?php _e('Add New', 'wpblogmailer'); ?>
    </button>
    
    <?php if (function_exists('wpbm_is_free_plan') && !wpbm_is_free_plan()): ?>
         <a href="<?php echo admin_url('admin.php?page=wpbm-import-export'); ?>" class="page-title-action">
            <?php _e('Import / Export', 'wpblogmailer'); ?>
        </a>
    <?php else: ?>
         <a href="<?php echo function_exists('wpbm_get_upgrade_url') ? wpbm_get_upgrade_url() : '#'; ?>" class="page-title-action" style="color: #99c56b;">
            <?php _e('⭐ Upgrade to Import/Export', 'wpblogmailer'); ?>
        </a>
    <?php endif; ?>
    
    <hr class="wp-header-end">

    <?php
    // Show subscriber limit notice for free users
    if (function_exists('wpbm_is_free') && wpbm_is_free()) {
        $total_subscribers = $stats['total'];
        $subscriber_limit = 250;
        $percentage = ($total_subscribers / $subscriber_limit) * 100;

        // Show warning when at 80% or higher
        if ($percentage >= 80) {
            $notice_type = $percentage >= 100 ? 'error' : 'warning';
            ?>
            <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible" style="margin-top: 15px;">
                <p>
                    <strong><?php _e('Subscriber Limit:', 'wpblogmailer'); ?></strong>
                    <?php
                    printf(
                        __('You have %1$d of %2$d subscribers (%3$d%%). ', 'wpblogmailer'),
                        $total_subscribers,
                        $subscriber_limit,
                        round($percentage)
                    );

                    if ($percentage >= 100) {
                        _e('You have reached the subscriber limit for the free version.', 'wpblogmailer');
                    } else {
                        _e('You are approaching the subscriber limit for the free version.', 'wpblogmailer');
                    }
                    ?>
                    <a href="<?php echo esc_url(function_exists('wpbm_get_upgrade_url') ? wpbm_get_upgrade_url() : '#'); ?>">
                        <?php _e('Upgrade to add unlimited subscribers', 'wpblogmailer'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    ?>

    <ul class="subsubsub">
        <li class="all">
            <a href="?page=wpbm-subscribers" class="<?php echo ($status === '') ? 'current' : ''; ?>">
                <?php _e('All', 'wpblogmailer'); ?> 
                <span class="count">(<?php echo number_format_i18n($stats['total']); ?>)</span>
            </a> |
        </li>
        <li class="confirmed">
             <a href="?page=wpbm-subscribers&status=confirmed" class="<?php echo ($status === 'confirmed') ? 'current' : ''; ?>">
                <?php _e('Confirmed', 'wpblogmailer'); ?> 
                <span class="count">(<?php echo number_format_i18n($stats['active']); ?>)</span>
            </a> |
        </li>
        <li class="pending">
             <a href="?page=wpbm-subscribers&status=pending" class="<?php echo ($status === 'pending') ? 'current' : ''; ?>">
                <?php _e('Pending', 'wpblogmailer'); ?> 
                <span class="count">(<?php echo number_format_i18n($stats['unconfirmed']); ?>)</span>
            </a>
        </li>
        </ul>

    <form method="get" class="wpbm-search-form">
        <input type="hidden" name="page" value="wpbm-subscribers">
        <?php if ($status): ?>
            <input type="hidden" name="status" value="<?php echo esc_attr($status); ?>">
        <?php endif; ?>
        <p class="search-box">
            <label class="screen-reader-text" for="subscriber-search-input">
                <?php _e('Search Subscribers:', 'wpblogmailer'); ?>
            </label>
            <input type="search" 
                   id="subscriber-search-input" 
                   name="s" 
                   value="<?php echo esc_attr($search); ?>"
                   placeholder="<?php esc_attr_e('Search by name or email...', 'wpblogmailer'); ?>">
            <button type="submit" class="button">
                <?php _e('Search', 'wpblogmailer'); ?>
            </button>
        </p>
    </form>

    <form method="post" id="subscribers-list-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
         <input type="hidden" name="action" value="wpbm_bulk_delete_subscribers"> <input type="hidden" name="page" value="wpbm-subscribers"> <?php wp_nonce_field('wpbm_bulk_delete_subscribers', 'wpbm_bulk_nonce'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text">
                    <?php _e('Select bulk action', 'wpblogmailer'); ?>
                </label>
                 <select name="bulk_action" id="bulk-action-selector-top"> <option value="-1"><?php _e('Bulk Actions', 'wpblogmailer'); ?></option>
                    <option value="delete"><?php _e('Delete', 'wpblogmailer'); ?></option>
                </select>
                <button type="submit" class="button action" id="doaction">
                    <?php _e('Apply', 'wpblogmailer'); ?>
                </button>
            </div>
            
            <?php if ($result['total_pages'] > 1): ?>
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(
                            _n('%s item', '%s items', $result['total'], 'wpblogmailer'),
                            number_format_i18n($result['total'])
                        ); ?>
                    </span>
                    <span class="pagination-links">
                        <?php
                        $query_args = array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;', 'wpblogmailer'),
                            'next_text' => __('&raquo;', 'wpblogmailer'),
                            'total' => $result['total_pages'],
                            'current' => $current_page
                        );
                        if (!empty($search)) {
                            $query_args['base'] = add_query_arg('s', urlencode($search), $query_args['base']);
                        }
                        if (!empty($status)) {
                            $query_args['base'] = add_query_arg('status', $status, $query_args['base']);
                        }
                        echo paginate_links($query_args);
                        ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <br class="clear">
        </div>

        <table class="wp-list-table widefat fixed striped subscribers">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <label class="screen-reader-text" for="cb-select-all-1">
                            <?php _e('Select All', 'wpblogmailer'); ?>
                        </label>
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <th scope="col" class="manage-column column-name column-primary">
                        <?php _e('Name', 'wpblogmailer'); ?>
                    </th>
                    <th scope="col" class="manage-column column-email">
                        <?php _e('Email', 'wpblogmailer'); ?>
                    </th>
                    <th scope="col" class="manage-column column-status">
                        <?php _e('Status', 'wpblogmailer'); ?>
                    </th>
                    <?php if (wpbm_is_pro()): ?>
                    <th scope="col" class="manage-column column-tags">
                        <?php _e('Tags', 'wpblogmailer'); ?>
                    </th>
                    <?php endif; ?>
                    <th scope="col" class="manage-column column-date">
                        <?php _e('Subscribed', 'wpblogmailer'); ?>
                    </th>
                </tr>
            </thead>
            
            <tbody>
                <?php if (empty($result['subscribers'])): ?>
                    <tr class="no-items">
                         <td class="colspanchange" colspan="<?php echo wpbm_is_pro() ? '6' : '5'; ?>"> 
                            <?php 
                            if ($search) {
                                _e('No subscribers found matching your search.', 'wpblogmailer');
                            } else {
                                _e('No subscribers found. Add your first subscriber!', 'wpblogmailer');
                            }
                            ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($result['subscribers'] as $subscriber): ?>
                        <tr data-id="<?php echo esc_attr($subscriber->id); ?>">
                            <th scope="row" class="check-column">
                                <label class="screen-reader-text" for="cb-select-<?php echo esc_attr($subscriber->id); ?>">
                                     <?php printf(__('Select %s', 'wpblogmailer'), esc_html(trim($subscriber->first_name . ' ' . $subscriber->last_name))); // --- FIX --- ?>
                                </label>
                                <input type="checkbox" 
                                       name="subscriber_ids[]" // Name for bulk action array
                                       id="cb-select-<?php echo esc_attr($subscriber->id); ?>"
                                       value="<?php echo esc_attr($subscriber->id); ?>">
                            </th>
                            <td class="column-name column-primary" data-colname="<?php esc_attr_e('Name', 'wpblogmailer'); ?>">
                                  <?php // --- FIX: Use first_name and last_name --- ?>
                                <strong><?php echo esc_html(trim($subscriber->first_name . ' ' . $subscriber->last_name)); ?></strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="#" class="wpbm-edit-subscriber" data-id="<?php echo esc_attr($subscriber->id); ?>">
                                            <?php _e('Edit', 'wpblogmailer'); ?>
                                        </a> |
                                    </span>
                                    <?php if (wpbm_is_pro()): ?>
                                    <span class="analytics">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpbm-subscriber-analytics&subscriber_id=' . $subscriber->id)); ?>">
                                            <?php _e('Analytics', 'wpblogmailer'); ?>
                                        </a> |
                                    </span>
                                    <?php endif; ?>
                                    <span class="delete">
                                        <a href="#" class="wpbm-delete-subscriber" data-id="<?php echo esc_attr($subscriber->id); ?>">
                                            <?php _e('Delete', 'wpblogmailer'); ?>
                                        </a>
                                    </span>
                                </div>
                                <button type="button" class="toggle-row">
                                    <span class="screen-reader-text"><?php _e('Show more details', 'wpblogmailer'); ?></span>
                                </button>
                            </td>
                            <td class="column-email" data-colname="<?php esc_attr_e('Email', 'wpblogmailer'); ?>">
                                <a href="mailto:<?php echo esc_attr($subscriber->email); ?>">
                                    <?php echo esc_html($subscriber->email); ?>
                                </a>
                            </td>
                            <td class="column-status" data-colname="<?php esc_attr_e('Status', 'wpblogmailer'); ?>">
                                  <?php // --- FIX: Use the 'status' property --- ?>
                                <?php if ($subscriber->status === 'confirmed'): ?>
                                    <span class="wpbm-status-badge wpbm-status-confirmed">
                                        <?php _e('Confirmed', 'wpblogmailer'); ?>
                                    </span>
                                <?php elseif ($subscriber->status === 'pending'): ?>
                                    <span class="wpbm-status-badge wpbm-status-pending">
                                        <?php _e('Pending', 'wpblogmailer'); ?>
                                    </span>
                                 <?php elseif ($subscriber->status === 'unsubscribed'): ?>
                                    <span class="wpbm-status-badge wpbm-status-unsubscribed">
                                        <?php _e('Unsubscribed', 'wpblogmailer'); ?>
                                    </span>
                                <?php else: ?>
                                     <span class="wpbm-status-badge">
                                        <?php echo esc_html(ucfirst($subscriber->status)); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <?php if (wpbm_is_pro() && $tag_model): ?>
                            <td class="column-tags" data-colname="<?php esc_attr_e('Tags', 'wpblogmailer'); ?>">
                                <?php
                                $subscriber_tags = $tag_model->get_subscriber_tags($subscriber->id);
                                if (!empty($subscriber_tags)):
                                    foreach ($subscriber_tags as $tag): ?>
                                        <span class="wpbm-tag-badge" style="background-color: <?php echo esc_attr($tag->color); ?>; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-right: 4px; display: inline-block;">
                                            <?php echo esc_html($tag->name); ?>
                                        </span>
                                    <?php endforeach;
                                else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td class="column-date" data-colname="<?php esc_attr_e('Subscribed', 'wpblogmailer'); ?>">
                                   <?php // --- FIX: Use created_at --- ?>
                                   <?php // Attempt to format the date nicely ?>
                                   <?php 
                                   $date_string = 'N/A';
                                   if (!empty($subscriber->created_at)) {
                                       try {
                                           // Use WordPress date formatting
                                           $timestamp = strtotime($subscriber->created_at);
                                           if ($timestamp) {
                                                $date_string = date_i18n(get_option('date_format'), $timestamp);
                                           } else {
                                                $date_string = $subscriber->created_at; // Fallback to raw string if parsing fails
                                           }
                                       } catch (\Exception $e) {
                                            $date_string = $subscriber->created_at; // Fallback
                                       }
                                   }
                                   echo esc_html($date_string); 
                                   ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            
            <tfoot>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <label class="screen-reader-text" for="cb-select-all-2">
                            <?php _e('Select All', 'wpblogmailer'); ?>
                        </label>
                        <input id="cb-select-all-2" type="checkbox">
                    </td>
                    <th scope="col" class="manage-column column-name column-primary">
                        <?php _e('Name', 'wpblogmailer'); ?>
                    </th>
                    <th scope="col" class="manage-column column-email">
                        <?php _e('Email', 'wpblogmailer'); ?>
                    </th>
                    <th scope="col" class="manage-column column-status">
                        <?php _e('Status', 'wpblogmailer'); ?>
                    </th>
                    <th scope="col" class="manage-column column-date">
                        <?php _e('Subscribed', 'wpblogmailer'); ?>
                    </th>
                </tr>
            </tfoot>
        </table>

        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-bottom" class="screen-reader-text">
                    <?php _e('Select bulk action', 'wpblogmailer'); ?>
                </label>
                 <select name="bulk_action2" id="bulk-action-selector-bottom"> <option value="-1"><?php _e('Bulk Actions', 'wpblogmailer'); ?></option>
                    <option value="delete"><?php _e('Delete', 'wpblogmailer'); ?></option>
                </select>
                <button type="submit" class="button action">
                    <?php _e('Apply', 'wpblogmailer'); ?>
                </button>
            </div>
            
            <?php if ($result['total_pages'] > 1): ?>
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(
                            _n('%s item', '%s items', $result['total'], 'wpblogmailer'),
                            number_format_i18n($result['total'])
                        ); ?>
                    </span>
                    <span class="pagination-links">
                        <?php
                         echo paginate_links($query_args); // Use the same args as above
                        ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <br class="clear">
        </div>
    </form>
</div>

<div id="wpbm-subscriber-modal" class="wpbm-modal" style="display: none;">
    <div class="wpbm-modal-content">
        <div class="wpbm-modal-header">
            <h2 id="wpbm-modal-title"><?php _e('Add Subscriber', 'wpblogmailer'); ?></h2>
            <button type="button" class="wpbm-modal-close">&times;</button>
        </div>
        <div class="wpbm-modal-body">
            <form id="wpbm-subscriber-form">
                <input type="hidden" name="id" id="subscriber-id">
                 <?php wp_nonce_field('wpbm_subscriber_nonce', '_wpnonce'); ?>
                
                <table class="form-table">
                     <tr>
                        <th scope="row">
                            <label for="subscriber-first-name">
                                <?php _e('First Name', 'wpblogmailer'); ?> 
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="subscriber-first-name" 
                                   name="first_name" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="subscriber-last-name">
                                <?php _e('Last Name', 'wpblogmailer'); ?> 
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="subscriber-last-name" 
                                   name="last_name" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="subscriber-email">
                                <?php _e('Email', 'wpblogmailer'); ?> 
                                <span class="required">*</span>
                            </label>
                        </th>
                        <td>
                            <input type="email" 
                                   id="subscriber-email" 
                                   name="email" 
                                   class="regular-text"
                                   required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="subscriber-status">
                                <?php _e('Status', 'wpblogmailer'); ?>
                            </label>
                        </th>
                        <td>
                             <select id="subscriber-status" name="status">
                                <option value="confirmed"><?php _e('Confirmed', 'wpblogmailer'); ?></option>
                                <option value="pending"><?php _e('Pending', 'wpblogmailer'); ?></option>
                                <option value="unsubscribed"><?php _e('Unsubscribed', 'wpblogmailer'); ?></option>
                            </select>
                            <p class="description">
                                <?php _e('Pending and Unsubscribed users will not receive emails.', 'wpblogmailer'); ?>
                            </p>
                        </td>
                    </tr>
                    <?php if (wpbm_is_pro() && !empty($available_tags)): ?>
                    <tr>
                        <th scope="row">
                            <label for="subscriber-tags">
                                <?php _e('Tags', 'wpblogmailer'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="subscriber-tags" name="tags[]" multiple style="width: 100%; max-width: 400px; height: 120px;">
                                <?php foreach ($available_tags as $tag): ?>
                                    <option value="<?php echo esc_attr($tag->id); ?>">
                                        <?php echo esc_html($tag->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('Hold Ctrl/Cmd to select multiple tags. Tags help you organize and segment your subscribers.', 'wpblogmailer'); ?>
                            </p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <p class="wpbm-form-error" style="display: none; color: #d63638;"></p>
            </form>
        </div>
        <div class="wpbm-modal-footer">
            <button type="button" class="button button-secondary wpbm-modal-close">
                <?php _e('Cancel', 'wpblogmailer'); ?>
            </button>
            <button type="button" class="button button-primary" id="wpbm-save-subscriber">
                <?php _e('Save Subscriber', 'wpblogmailer'); ?>
            </button>
            <span class="spinner"></span> 
        </div>
    </div>
</div>

<?php // Ensure necessary JS is enqueued for modal and AJAX actions ?>
<script type="text/javascript">
jQuery(document).ready(function($) {
    var modal = $('#wpbm-subscriber-modal');
    var form = $('#wpbm-subscriber-form');
    var title = $('#wpbm-modal-title');
    var saveButton = $('#wpbm-save-subscriber');
    var spinner = modal.find('.spinner');
    var errorContainer = form.find('.wpbm-form-error');

    // Function to reset and close modal
    function closeModal() {
        modal.hide();
        form[0].reset();
        $('#subscriber-id').val('');
        errorContainer.hide().text('');
        spinner.removeClass('is-active');
        saveButton.prop('disabled', false);
    }

    // Open Add modal
    $('#wpbm-add-subscriber-btn, #wpbm-quick-add-subscriber, #wpbm-quick-add-subscriber-empty').on('click', function(e) {
        e.preventDefault();
        title.text('<?php esc_html_e('Add Subscriber', 'wpblogmailer'); ?>');
        form[0].reset();
         $('#subscriber-id').val('');
         $('#subscriber-status').val('confirmed'); // Default to confirmed
        modal.show();
    });

    // Open Edit modal
    $('.wpbm-edit-subscriber').on('click', function(e) {
        e.preventDefault();
        var subId = $(this).data('id');
        title.text('<?php esc_html_e('Edit Subscriber', 'wpblogmailer'); ?>');
        form[0].reset();
        $('#subscriber-id').val(subId);
        errorContainer.hide().text('');
        spinner.addClass('is-active'); // Show spinner while loading
        modal.show();

        // AJAX request to get subscriber data
        $.post(ajaxurl, {
            action: 'wpbm_get_subscriber',
            _wpnonce: $('#_wpnonce').val(), // Get nonce from the modal form
            id: subId
        })
        .done(function(response) {
            if (response.success && response.data.subscriber) {
                var sub = response.data.subscriber;
                $('#subscriber-first-name').val(sub.first_name);
                $('#subscriber-last-name').val(sub.last_name);
                $('#subscriber-email').val(sub.email);
                $('#subscriber-status').val(sub.status);

                // Load tags if Pro and tags exist
                <?php if (wpbm_is_pro()): ?>
                $('#subscriber-tags').val([]); // Clear selection first
                if (response.data.tags && response.data.tags.length > 0) {
                    var tagIds = response.data.tags.map(function(tag) {
                        return tag.id.toString();
                    });
                    $('#subscriber-tags').val(tagIds);
                }
                <?php endif; ?>
            } else {
                errorContainer.text(response.data.message || '<?php esc_html_e('Could not load subscriber data.', 'wpblogmailer'); ?>').show();
            }
        })
        .fail(function() {
             errorContainer.text('<?php esc_html_e('Error loading subscriber data.', 'wpblogmailer'); ?>').show();
        })
        .always(function() {
             spinner.removeClass('is-active');
        });
    });

    // Close modal
    $('.wpbm-modal-close').on('click', function() {
        closeModal();
    });
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27 && modal.is(':visible')) { // ESC key
            closeModal();
        }
    });

    // Save (Add/Edit) subscriber
    saveButton.on('click', function() {
        var subId = $('#subscriber-id').val();
        var action = subId ? 'wpbm_edit_subscriber' : 'wpbm_add_subscriber';
        var formData = form.serializeArray(); // Get form data as array
        var data = {};

        // Convert form data array to object
        $.each(formData, function(i, field){
            data[field.name] = field.value;
        });
        
        data.action = action; // Add action to data object

        spinner.addClass('is-active');
        saveButton.prop('disabled', true);
        errorContainer.hide().text('');

        $.post(ajaxurl, data)
        .done(function(response) {
            if (response.success) {
                closeModal();
                // Clear hash before reloading to prevent modal from reopening
                if (window.location.hash) {
                    window.location.hash = '';
                }
                // Refresh the page to show changes
                window.location.reload();
                // Optionally, you could update the table row via JS instead of reloading
            } else {
                errorContainer.text(response.data.message || '<?php esc_html_e('An error occurred.', 'wpblogmailer'); ?>').show();
            }
        })
        .fail(function(jqXHR) {
             var errorMsg = '<?php esc_html_e('An unknown error occurred.', 'wpblogmailer'); ?>';
             if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                 errorMsg = jqXHR.responseJSON.data.message;
             }
             errorContainer.text(errorMsg).show();
        })
        .always(function() {
            spinner.removeClass('is-active');
            saveButton.prop('disabled', false);
        });
    });

    // Delete subscriber (row action)
    $('.wpbm-delete-subscriber').on('click', function(e) {
         e.preventDefault();
        if (!confirm('<?php esc_html_e('Are you sure you want to delete this subscriber?', 'wpblogmailer'); ?>')) {
            return;
        }
        var subId = $(this).data('id');
        var $row = $(this).closest('tr');

        $row.css('opacity', '0.5'); // Visual feedback

        $.post(ajaxurl, {
            action: 'wpbm_delete_subscriber',
            _wpnonce: $('#_wpnonce').val(), // Make sure nonce is available, might need to add it outside the modal
            id: subId
        })
        .done(function(response) {
            if (response.success) {
                 $row.fadeOut(300, function() { $(this).remove(); });
                 // Optionally show a success notice
            } else {
                 alert(response.data.message || '<?php esc_html_e('Could not delete subscriber.', 'wpblogmailer'); ?>');
                 $row.css('opacity', '1');
            }
        })
        .fail(function() {
            alert('<?php esc_html_e('An error occurred while trying to delete.', 'wpblogmailer'); ?>');
             $row.css('opacity', '1');
        });
    });

    // Handle Check All checkboxes
    $('#cb-select-all-1, #cb-select-all-2').on('click', function() {
        var isChecked = $(this).prop('checked');
        $('input[name="subscriber_ids[]"]').prop('checked', isChecked);
        $('#cb-select-all-1, #cb-select-all-2').prop('checked', isChecked); // Keep top/bottom synced
    });

     // Check if hash is #add-new on page load
     if (window.location.hash === '#add-new') {
         $('#wpbm-add-subscriber-btn').trigger('click');
     }

});
</script>