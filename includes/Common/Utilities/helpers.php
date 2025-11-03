<?php
/**
 * Helper Functions
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Sanitize email address
 *
 * @param string $email Email address
 * @return string|false Sanitized email or false if invalid
 */
function wpbm_sanitize_email($email) {
    $email = sanitize_email($email);
    return is_email($email) ? $email : false;
}

/**
 * Format date for display
 *
 * @param string $date Date string
 * @param string $format Date format (default: WordPress format)
 * @return string Formatted date
 */
function wpbm_format_date($date, $format = '') {
    if (empty($format)) {
        $format = get_option('date_format') . ' ' . get_option('time_format');
    }
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date_i18n($format, $timestamp);
}

/**
 * Get subscriber count
 *
 * @param string $status Subscriber status (active, inactive, all)
 * @return int Subscriber count
 */
function wpbm_get_subscriber_count($status = 'active') {
    global $wpdb;
    
    $table = WPBM_TABLE_SUBSCRIBERS;
    
    if ($status === 'all') {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    } else {
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = %s",
            $status
        ));
    }
    
    return (int) $count;
}

/**
 * Check if email exists
 *
 * @param string $email Email address
 * @return bool True if exists, false otherwise
 */
function wpbm_email_exists($email) {
    global $wpdb;
    
    $table = WPBM_TABLE_SUBSCRIBERS;
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE email = %s",
        $email
    ));
    
    return $exists > 0;
}

/**
 * Generate confirmation token
 *
 * @return string Unique token
 */
function wpbm_generate_token() {
    return bin2hex(random_bytes(32));
}

/**
 * Log error message
 *
 * @param string $message Error message
 * @param array $context Additional context data
 * @return void
 */
function wpbm_log_error($message, $context = array()) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[WP Blog Mailer] ' . $message);
        if (!empty($context)) {
            error_log('[WP Blog Mailer Context] ' . print_r($context, true));
        }
    }
}

/**
 * Get plugin option
 *
 * @param string $key Option key
 * @param mixed $default Default value
 * @return mixed Option value
 */
function wpbm_get_option($key, $default = null) {
    $options = get_option('wpbm_settings', array());
    return isset($options[$key]) ? $options[$key] : $default;
}

/**
 * Update plugin option
 *
 * @param string $key Option key
 * @param mixed $value Option value
 * @return bool True on success, false on failure
 */
function wpbm_update_option($key, $value) {
    $options = get_option('wpbm_settings', array());
    $options[$key] = $value;
    return update_option('wpbm_settings', $options);
}

/**
 * Check if feature is available for current tier
 *
 * @param string $feature Feature name
 * @return bool True if available, false otherwise
 */
function wpbm_feature_available($feature) {
    $plugin = \WPBlogMailer\Core\Plugin::instance();
    $tier = $plugin->get_tier();
    
    $features = array(
        'custom_emails' => array('starter', 'pro'),
        'templates' => array('starter', 'pro'),
        'analytics' => array('starter', 'pro'),
        'queue' => array('starter', 'pro'),
        'advanced_analytics' => array('pro'),
        'custom_templates' => array('pro'),
        'campaigns' => array('pro'),
        'tracking' => array('pro'),
    );
    
    if (!isset($features[$feature])) {
        return false;
    }
    
    return in_array($tier, $features[$feature]);
}

/**
 * Get upgrade URL for feature
 *
 * @param string $feature Feature name
 * @return string Upgrade URL
 */
if (!function_exists('wpbm_get_upgrade_url')) {
    function wpbm_get_upgrade_url($feature = '') {
        if (function_exists('wpbm_fs')) {
            return wpbm_fs()->get_upgrade_url();
        }

        return admin_url('admin.php?page=wpbm-pricing');
    }
}

/**
 * Render upgrade notice
 *
 * @param string $feature Feature name
 * @param string $tier Required tier
 * @return void
 */
if (!function_exists('wpbm_upgrade_notice')) {
    function wpbm_upgrade_notice($feature, $tier = 'starter') {
        ?>
        <div class="wpbm-upgrade-notice notice notice-info">
            <p>
                <strong><?php _e('Upgrade Required', 'wpblogmailer'); ?></strong>
            </p>
            <p>
                <?php
                printf(
                    __('The %s feature is available in the %s plan.', 'wpblogmailer'),
                    '<strong>' . esc_html($feature) . '</strong>',
                    '<strong>' . esc_html(ucfirst($tier)) . '</strong>'
                );
                ?>
            </p>
            <p>
                <a href="<?php echo esc_url(wpbm_get_upgrade_url($feature)); ?>" class="button button-primary">
                    <?php _e('Upgrade Now', 'wpblogmailer'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}