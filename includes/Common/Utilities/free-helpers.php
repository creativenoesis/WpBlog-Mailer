<?php
/**
 * Helper Functions for Free Version
 *
 * These functions replace Freemius tier-checking functions
 * and always return values appropriate for the free tier.
 *
 * @package WPBlogMailer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Check if user is on free plan (always true in free version)
 *
 * @return bool
 */
if (!function_exists('wpbm_is_free_plan')) {
    function wpbm_is_free_plan() {
        return true;
    }
}

/**
 * Check if user is on starter plan (always false in free version)
 *
 * @return bool
 */
if (!function_exists('wpbm_is_starter')) {
    function wpbm_is_starter() {
        return false;
    }
}

/**
 * Check if user is on pro plan (always false in free version)
 *
 * @return bool
 */
if (!function_exists('wpbm_is_pro')) {
    function wpbm_is_pro() {
        return false;
    }
}

/**
 * Check if user is paying (always false in free version)
 *
 * @return bool
 */
if (!function_exists('wpbm_is_paying')) {
    function wpbm_is_paying() {
        return false;
    }
}

/**
 * Check if user is on trial (always false in free version)
 *
 * @return bool
 */
if (!function_exists('wpbm_is_trial')) {
    function wpbm_is_trial() {
        return false;
    }
}

/**
 * Check if user can use premium code (always false in free version)
 *
 * @return bool
 */
if (!function_exists('wpbm_can_use_premium_code')) {
    function wpbm_can_use_premium_code() {
        return false;
    }
}

/**
 * Get current plan name (always 'free' in free version)
 *
 * @return string
 */
if (!function_exists('wpbm_get_plan_name')) {
    function wpbm_get_plan_name() {
        return 'free';
    }
}

/**
 * Check if double opt-in is enabled
 *
 * @return bool
 */
if (!function_exists('wpbm_is_double_optin_enabled')) {
    function wpbm_is_double_optin_enabled() {
        $settings = get_option('wpbm_settings', []);
        return !empty($settings['double_optin']);
    }
}

/**
 * Get upgrade URL
 *
 * @return string
 */
if (!function_exists('wpbm_get_upgrade_url')) {
    function wpbm_get_upgrade_url() {
        return 'https://creativenoesis.com/wp-blog-mailer/';
    }
}

/**
 * Show upgrade notice
 *
 * @param string $feature Feature name
 * @return void
 */
if (!function_exists('wpbm_show_upgrade_notice')) {
    function wpbm_show_upgrade_notice($feature = '') {
        $feature_text = $feature ? sprintf(__('%s feature', 'wpblogmailer'), $feature) : __('This feature', 'wpblogmailer');
        ?>
        <div class="notice notice-info" style="padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1;">
            <p style="margin: 0;">
                <strong>🌟 <?php echo esc_html($feature_text); ?></strong>
                <?php esc_html_e('is available in WP Blog Mailer Pro.', 'wpblogmailer'); ?>
                <a href="<?php echo esc_url(wpbm_get_upgrade_url()); ?>" target="_blank" class="button button-primary" style="margin-left: 10px;">
                    <?php esc_html_e('Upgrade Now', 'wpblogmailer'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
