<?php
/**
 * Plugin Name: WP Blog Mailer
 * Plugin URI:  https://wordpress.org/plugins/wpblog-mailer/
 * Description: Simple automated newsletter system for WordPress. Send your latest blog posts to subscribers automatically.
 * Version:     1.0.0
 * Author:      Creative Noesis
 * Author URI:  https://creativenoesis.com
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpblogmailer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

// ============================================================
// SECURITY & VERSION CHECKS
// ============================================================

define('WPBM_MIN_PHP_VERSION', '7.4');
define('WPBM_MIN_WP_VERSION', '5.8');

// PHP version check
if (version_compare(PHP_VERSION, WPBM_MIN_PHP_VERSION, '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error is-dismissible"><p>';
        printf(
            esc_html__('WP Blog Mailer requires PHP %s or higher. Your current version is %s. The plugin has been deactivated.', 'wpblogmailer'),
            WPBM_MIN_PHP_VERSION,
            PHP_VERSION
        );
        echo '</p></div>';
        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
        deactivate_plugins( plugin_basename( __FILE__ ) );

    });
    return;
}

// ============================================================
// PLUGIN CONSTANTS
// ============================================================

define('WPBM_VERSION', '1.0.0');
define('WPBM_PLUGIN_FILE', __FILE__);
define('WPBM_PLUGIN_PATH', plugin_dir_path( WPBM_PLUGIN_FILE ));
define('WPBM_PLUGIN_URL', plugin_dir_url( WPBM_PLUGIN_FILE ));
define('WPBM_PLUGIN_DIR', WPBM_PLUGIN_PATH ); // Keep for compatibility if needed

// ============================================================
// DATABASE TABLE CONSTANTS
// ============================================================

// Define table name constants for use throughout the plugin
global $wpdb;
define('WPBM_TABLE_SUBSCRIBERS', $wpdb->prefix . 'wpbm_subscribers');
define('WPBM_TABLE_SEND_HISTORY', $wpdb->prefix . 'wpbm_send_history');
define('WPBM_TABLE_ANALYTICS_LOG', $wpdb->prefix . 'wpbm_analytics_log');
define('WPBM_TABLE_ANALYTICS_LINKS', $wpdb->prefix . 'wpbm_analytics_links');
define('WPBM_TABLE_TEMPLATES', $wpdb->prefix . 'wpbm_templates');
define('WPBM_TABLE_EMAIL_QUEUE', $wpdb->prefix . 'wpbm_email_queue');
define('WPBM_TABLE_SEND_LOG', $wpdb->prefix . 'wpbm_send_log');
define('WPBM_TABLE_CRON_LOG', $wpdb->prefix . 'wpbm_cron_log');
define('WPBM_TABLE_TAGS', $wpdb->prefix . 'wpbm_tags');
define('WPBM_TABLE_SUBSCRIBER_TAGS', $wpdb->prefix . 'wpbm_subscriber_tags');


// ============================================================
// AUTOLOADER SETUP
// ============================================================

// Check if Composer autoloader exists first (Recommended)
if ( file_exists( WPBM_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
    require_once WPBM_PLUGIN_PATH . 'vendor/autoload.php';
}
// Fallback to custom autoloader if Composer one doesn't exist AND Autoloader class exists
elseif ( file_exists( WPBM_PLUGIN_PATH . 'includes/Core/Autoloader.php' ) ) {
    require_once WPBM_PLUGIN_PATH . 'includes/Core/Autoloader.php';
    \WPBlogMailer\Core\Autoloader::init(); // Initialize your custom autoloader
} else {
     add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error is-dismissible"><p>';
        echo esc_html__( 'WP Blog Mailer Error: Autoloader not found. Please run "composer install" or ensure includes/Core/Autoloader.php exists.', 'wpblogmailer' );
        echo '</p></div>';
    });
    return; // Stop if no autoloader found
}

// ============================================================
// ACTIVATION & DEACTIVATION HOOKS
// ============================================================

/**
 * Activation Hook: Creates database tables correctly.
 */
function wpbm_activate() {
    // Ensure the Schema class is loaded (Autoloader should handle this)
    if (!class_exists('\WPBlogMailer\Common\Database\Schema')) {
        error_log('WP Blog Mailer Activation Error: Schema class not found.');
        set_transient('wpbm_activation_error', 'Schema class not found. Autoloader might be broken.', MINUTE_IN_SECONDS);
        return;
    }

    try {
        // Instantiate the Schema class
        $schema = new \WPBlogMailer\Common\Database\Schema();

        // Call the create_tables method
        $schema->create_tables();

        // Schedule initial cron jobs
        if (class_exists('\WPBlogMailer\Common\Services\CronService') && method_exists('\WPBlogMailer\Common\Services\CronService', 'schedule_events')) {
            \WPBlogMailer\Common\Services\CronService::schedule_events();
        }

        // Set plugin version and activation time options
        update_option('wpbm_version', WPBM_VERSION);
        update_option('wpbm_activated_time', time());

    } catch (\Exception $e) {
        // Catch potential errors during activation
        error_log('WP Blog Mailer Activation Error: ' . $e->getMessage());
        set_transient('wpbm_activation_error', 'An error occurred during activation: ' . esc_html($e->getMessage()), MINUTE_IN_SECONDS);
    }
}
register_activation_hook(WPBM_PLUGIN_FILE, 'wpbm_activate');

/**
 * Deactivation Hook: Clean up cron jobs.
 */
function wpbm_deactivate() {
    // Clear scheduled cron jobs
     if (class_exists('\WPBlogMailer\Common\Services\CronService') && method_exists('\WPBlogMailer\Common\Services\CronService', 'unschedule_events')) {
        \WPBlogMailer\Common\Services\CronService::unschedule_events();
    }
}
register_deactivation_hook(WPBM_PLUGIN_FILE, 'wpbm_deactivate');

// ============================================================
// INITIALIZE PLUGIN CORE
// ============================================================

/**
 * Initialize the core plugin class after all plugins are loaded.
 */
function wpbm_init() {
    // Check for activation errors first
    if ( get_transient( 'wpbm_activation_error' ) ) {
        add_action( 'admin_notices', 'wpbm_display_activation_error' );
        return; // Stop initialization if activation failed critically
    }

    // Initialize the main plugin class via Singleton
    try {
        \WPBlogMailer\Core\Plugin::instance();
    } catch (\Exception $e) {
         // Catch errors during Plugin instantiation
         error_log('WP Blog Mailer Initialization Error: ' . $e->getMessage());
         add_action( 'admin_notices', function() use ($e) {
             echo '<div class="notice notice-error is-dismissible"><p>';
             echo '<strong>WP Blog Mailer Error:</strong> Failed to initialize the plugin. Please check the logs. Details: ' . esc_html($e->getMessage());
             echo '</p></div>';
         });
         return;
    }
}
add_action('plugins_loaded', 'wpbm_init', 15);

/**
* Displays activation errors as an admin notice.
*/
function wpbm_display_activation_error() {
    $error_message = get_transient( 'wpbm_activation_error' );
    if ( $error_message ) {
        echo '<div class="notice notice-error is-dismissible"><p>';
        echo '<strong>WP Blog Mailer Activation Error:</strong> ' . esc_html( $error_message );
        echo '</p></div>';
        delete_transient( 'wpbm_activation_error' ); // Show only once
    }
}

// ============================================================
// GLOBAL HELPER FUNCTIONS
// ============================================================

// Load free version helpers first (tier-checking stubs)
$free_helpers_file = WPBM_PLUGIN_PATH . 'includes/Common/Utilities/free-helpers.php';
if (file_exists($free_helpers_file)) {
    require_once $free_helpers_file;
}

// Load regular helpers
$helpers_file = WPBM_PLUGIN_PATH . 'includes/Common/Utilities/helpers.php';
if (file_exists($helpers_file)) {
    require_once $helpers_file;
}

/**
 * Get plugin instance shortcut
 * @return \WPBlogMailer\Core\Plugin|null
 */
if (!function_exists('wpbm')) {
    function wpbm() {
        if (!did_action('plugins_loaded')) {
            _doing_it_wrong(__FUNCTION__, 'wpbm() should not be called before the plugins_loaded action.', '1.0.0');
            return null;
        }
        return \WPBlogMailer\Core\Plugin::instance();
    }
}

// ============================================================
// UPGRADE NOTICE
// ============================================================

/**
 * Show upgrade notice in admin
 */
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'wpbm') === false) {
        return;
    }

    // Don't show if user dismissed it
    if (get_user_meta(get_current_user_id(), 'wpbm_dismissed_upgrade_notice', true)) {
        return;
    }

    ?>
    <div class="notice notice-info is-dismissible wpbm-upgrade-notice">
        <p>
            <strong><?php esc_html_e('Love WP Blog Mailer?', 'wpblogmailer'); ?></strong>
            <?php esc_html_e('Upgrade to Pro for advanced analytics, custom templates, subscriber tags, and more!', 'wpblogmailer'); ?>
            <a href="https://creativenoesis.com/wp-blog-mailer/" target="_blank" class="button button-primary" style="margin-left: 10px;">
                <?php esc_html_e('Learn More', 'wpblogmailer'); ?>
            </a>
        </p>
    </div>
    <script>
    jQuery(document).on('click', '.wpbm-upgrade-notice .notice-dismiss', function() {
        jQuery.post(ajaxurl, {
            action: 'wpbm_dismiss_upgrade_notice'
        });
    });
    </script>
    <?php
});

// AJAX handler for dismissing upgrade notice
add_action('wp_ajax_wpbm_dismiss_upgrade_notice', function() {
    update_user_meta(get_current_user_id(), 'wpbm_dismissed_upgrade_notice', true);
    wp_send_json_success();
});
