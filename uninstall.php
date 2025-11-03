<?php
/**
 * Plugin Uninstall Handler
 *
 * Fired when the plugin is uninstalled via WordPress admin.
 * Cleans up all plugin data from database including tables and options.
 *
 * @package WPBlogMailer
 * @since 2.1.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Uninstall WP Blog Mailer Plugin
 *
 * WARNING: This will permanently delete ALL plugin data including:
 * - All subscriber information (emails, names, consent records)
 * - All email send history and analytics
 * - All custom templates
 * - All plugin settings
 * - All database tables
 *
 * This action cannot be undone.
 */

global $wpdb;

// Define table names
$tables = array(
    $wpdb->prefix . 'wpbm_subscribers',
    $wpdb->prefix . 'wpbm_send_history',
    $wpdb->prefix . 'wpbm_analytics_log',
    $wpdb->prefix . 'wpbm_analytics_links',
    $wpdb->prefix . 'wpbm_templates',
    $wpdb->prefix . 'wpbm_email_queue',
    $wpdb->prefix . 'wpbm_send_log',
    $wpdb->prefix . 'wpbm_cron_log',
    $wpdb->prefix . 'wpbm_tags',
    $wpdb->prefix . 'wpbm_subscriber_tags',
);

// Drop all plugin tables
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Delete all plugin options
$options = array(
    'wpbm_settings',
    'wpbm_version',
    'wpbm_db_version',
    'wpbm_activated_time',
    'wpbm_last_newsletter_send',
    'wpbm_weekly_report_settings',
    'wpbm_template_type',
    'wpbm_subscriber_keys_migrated',
);

foreach ($options as $option) {
    delete_option($option);
}

// Delete all transients (cache)
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpbm_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpbm_%'");

// Clear all scheduled cron jobs
$cron_hooks = array(
    'wpbm_send_newsletter',
    'wpbm_process_email_queue',
    'wpbm_cleanup_old_data',
    'wpbm_send_weekly_report',
);

foreach ($cron_hooks as $hook) {
    $timestamp = wp_next_scheduled($hook);
    if ($timestamp) {
        wp_unschedule_event($timestamp, $hook);
    }
    // Clear all instances of the hook
    wp_clear_scheduled_hook($hook);
}

// Log the uninstallation for debugging purposes
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('WP Blog Mailer: Plugin uninstalled and all data removed at ' . current_time('mysql'));
}

// Optional: If you want to keep a backup log before deletion, you could:
// 1. Export data to a file in wp-content/uploads/wpbm-backup/
// 2. Send an email to admin with export link
// 3. Create a transient that shows on next admin page load
//
// For GDPR compliance, we're doing a complete deletion by default.
