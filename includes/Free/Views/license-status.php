<?php
/**
 * License Status & Debug View
 *
 * Shows current license status and helps verify Pro/Starter features are working
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

// Get Freemius instance
$fs = function_exists('wpbm_fs') ? wpbm_fs() : null;

// Get license information
$license_info = [
    'is_registered' => false,
    'is_anonymous' => true,
    'is_paying' => false,
    'is_trial' => false,
    'is_free' => true,
    'is_starter' => false,
    'is_pro' => false,
    'plan_name' => 'free',
    'license_key' => null,
    'license_expires' => null,
    'license_activated' => null,
    'site_count' => 0,
    'max_sites' => 0,
];

if ($fs) {
    try {
        $license_info['is_registered'] = $fs->is_registered();
        $license_info['is_anonymous'] = $fs->is_anonymous();
        $license_info['is_paying'] = wpbm_is_paying();
        $license_info['is_trial'] = wpbm_is_trial();
        $license_info['is_free'] = wpbm_is_free_plan();
        $license_info['is_starter'] = wpbm_is_starter();
        $license_info['is_pro'] = wpbm_is_pro();
        $license_info['plan_name'] = wpbm_get_plan_name();

        // Get license details if available
        if ($fs->is_paying() || $fs->is_trial()) {
            $license = $fs->_get_license();
            if ($license) {
                $license_info['license_key'] = substr($license->secret_key, 0, 8) . '...' . substr($license->secret_key, -4);
                $license_info['license_expires'] = $license->expiration;
                $license_info['license_activated'] = $license->activated;
                $license_info['site_count'] = $license->activated;
                $license_info['max_sites'] = $license->quota;
            }
        }
    } catch (\Exception $e) {
        error_log('License Status Error: ' . $e->getMessage());
    }
}

// Feature availability check
$features = [
    'email_queue' => [
        'name' => 'Email Queue Processing',
        'tier' => 'Starter+',
        'available' => class_exists('\WPBlogMailer\Common\Services\EmailQueueService'),
        'enabled' => wpbm_is_starter(),
    ],
    'analytics' => [
        'name' => 'Basic Analytics',
        'tier' => 'Starter+',
        'available' => class_exists('\WPBlogMailer\Common\Analytics\BasicAnalytics'),
        'enabled' => wpbm_is_starter(),
    ],
    'custom_email' => [
        'name' => 'Custom Email Sending',
        'tier' => 'Starter+',
        'available' => true,
        'enabled' => wpbm_is_starter(),
    ],
    'tracking' => [
        'name' => 'Open/Click Tracking',
        'tier' => 'Pro',
        'available' => class_exists('\WPBlogMailer\Pro\Services\TrackingService'),
        'enabled' => wpbm_is_pro(),
    ],
    'custom_templates' => [
        'name' => 'Custom Templates',
        'tier' => 'Pro',
        'available' => true,
        'enabled' => wpbm_is_pro(),
    ],
    'subscriber_analytics' => [
        'name' => 'Per-Subscriber Analytics',
        'tier' => 'Pro',
        'available' => true,
        'enabled' => wpbm_is_pro(),
    ],
];

?>

<div class="wrap wpbm-license-status">
    <h1 class="wp-heading-inline"><?php esc_html_e('License Status & Debug', 'wpblogmailer'); ?></h1>

    <hr class="wp-header-end">

    <!-- Current License Status -->
    <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h2 style="margin-top: 0;"><?php esc_html_e('Current License Status', 'wpblogmailer'); ?></h2>

        <table class="widefat" style="border: none;">
            <tr>
                <th style="width: 250px; padding: 12px; background: #f6f7f7;"><?php esc_html_e('Registration Status', 'wpblogmailer'); ?></th>
                <td style="padding: 12px;">
                    <?php if ($license_info['is_registered']): ?>
                        <span style="color: #00a32a;">
                            <span class="dashicons dashicons-yes" style="font-size: 20px; vertical-align: middle;"></span>
                            <?php esc_html_e('Registered', 'wpblogmailer'); ?>
                        </span>
                    <?php else: ?>
                        <span style="color: #d63638;">
                            <span class="dashicons dashicons-no" style="font-size: 20px; vertical-align: middle;"></span>
                            <?php esc_html_e('Not Registered', 'wpblogmailer'); ?>
                        </span>
                        <br><a href="<?php echo esc_url($fs->get_activation_url()); ?>" class="button button-primary" style="margin-top: 10px;">
                            <?php esc_html_e('Connect Account', 'wpblogmailer'); ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>

            <tr style="background: #f6f7f7;">
                <th style="padding: 12px; background: #f6f7f7;"><?php esc_html_e('Current Plan', 'wpblogmailer'); ?></th>
                <td style="padding: 12px;">
                    <strong style="font-size: 18px; text-transform: uppercase; color: <?php echo $license_info['is_pro'] ? '#2271b1' : ($license_info['is_starter'] ? '#00a32a' : '#646970'); ?>;">
                        <?php echo esc_html($license_info['plan_name']); ?>
                    </strong>
                    <?php if ($license_info['is_trial']): ?>
                        <span class="wpbm-badge" style="display: inline-block; margin-left: 10px; padding: 3px 8px; background: #dba617; color: #fff; border-radius: 3px; font-size: 11px; font-weight: 600;">
                            <?php esc_html_e('TRIAL', 'wpblogmailer'); ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>

            <?php if ($license_info['license_key']): ?>
            <tr>
                <th style="padding: 12px; background: #f6f7f7;"><?php esc_html_e('License Key', 'wpblogmailer'); ?></th>
                <td style="padding: 12px;">
                    <code style="font-size: 14px;"><?php echo esc_html($license_info['license_key']); ?></code>
                </td>
            </tr>

            <tr style="background: #f6f7f7;">
                <th style="padding: 12px; background: #f6f7f7;"><?php esc_html_e('Sites Activated', 'wpblogmailer'); ?></th>
                <td style="padding: 12px;">
                    <?php echo esc_html($license_info['site_count']); ?> / <?php echo esc_html($license_info['max_sites']); ?>
                    <?php if ($license_info['site_count'] >= $license_info['max_sites']): ?>
                        <span style="color: #dba617;"><?php esc_html_e('(Limit Reached)', 'wpblogmailer'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>

            <?php if ($license_info['license_expires']): ?>
            <tr>
                <th style="padding: 12px; background: #f6f7f7;"><?php esc_html_e('Expires', 'wpblogmailer'); ?></th>
                <td style="padding: 12px;">
                    <?php
                    $expires = strtotime($license_info['license_expires']);
                    $days_remaining = floor(($expires - current_time('timestamp')) / DAY_IN_SECONDS);
                    ?>
                    <?php echo esc_html(wp_date('F j, Y', $expires)); ?>
                    <?php if ($days_remaining > 0): ?>
                        <span style="color: <?php echo $days_remaining < 30 ? '#dba617' : '#646970'; ?>;">
                            (<?php echo esc_html(sprintf(__('%d days remaining', 'wpblogmailer'), $days_remaining)); ?>)
                        </span>
                    <?php else: ?>
                        <span style="color: #d63638;"><?php esc_html_e('(Expired)', 'wpblogmailer'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php endif; ?>

            <tr style="background: #f6f7f7;">
                <th style="padding: 12px; background: #f6f7f7;"><?php esc_html_e('Account Page', 'wpblogmailer'); ?></th>
                <td style="padding: 12px;">
                    <a href="<?php echo admin_url('admin.php?page=wpblog-mailer-account'); ?>" class="button">
                        <?php esc_html_e('Manage License', 'wpblogmailer'); ?>
                    </a>
                </td>
            </tr>
        </table>
    </div>

    <!-- Feature Availability -->
    <h2><?php esc_html_e('Feature Availability', 'wpblogmailer'); ?></h2>

    <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Feature', 'wpblogmailer'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Required', 'wpblogmailer'); ?></th>
                    <th style="width: 120px;"><?php esc_html_e('Code Available', 'wpblogmailer'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Enabled', 'wpblogmailer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($features as $feature): ?>
                <tr>
                    <td><strong><?php echo esc_html($feature['name']); ?></strong></td>
                    <td>
                        <span class="wpbm-badge" style="display: inline-block; padding: 3px 8px; background: <?php echo strpos($feature['tier'], 'Pro') !== false ? '#2271b1' : '#00a32a'; ?>; color: #fff; border-radius: 3px; font-size: 11px; font-weight: 600;">
                            <?php echo esc_html($feature['tier']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($feature['available']): ?>
                            <span style="color: #00a32a;">
                                <span class="dashicons dashicons-yes"></span>
                                <?php esc_html_e('Yes', 'wpblogmailer'); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #d63638;">
                                <span class="dashicons dashicons-no"></span>
                                <?php esc_html_e('No', 'wpblogmailer'); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($feature['enabled']): ?>
                            <span style="color: #00a32a;">
                                <span class="dashicons dashicons-yes"></span>
                                <?php esc_html_e('Active', 'wpblogmailer'); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #646970;">
                                <span class="dashicons dashicons-minus"></span>
                                <?php esc_html_e('Inactive', 'wpblogmailer'); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Helper Functions Status -->
    <h2><?php esc_html_e('Helper Functions Test', 'wpblogmailer'); ?></h2>

    <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width: 250px;"><?php esc_html_e('Function', 'wpblogmailer'); ?></th>
                    <th><?php esc_html_e('Result', 'wpblogmailer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>wpbm_is_free_plan()</code></td>
                    <td><?php echo wpbm_is_free_plan() ? '<span style="color: #00a32a;">TRUE</span>' : '<span style="color: #646970;">FALSE</span>'; ?></td>
                </tr>
                <tr>
                    <td><code>wpbm_is_starter()</code></td>
                    <td><?php echo wpbm_is_starter() ? '<span style="color: #00a32a;">TRUE</span>' : '<span style="color: #646970;">FALSE</span>'; ?></td>
                </tr>
                <tr>
                    <td><code>wpbm_is_pro()</code></td>
                    <td><?php echo wpbm_is_pro() ? '<span style="color: #00a32a;">TRUE</span>' : '<span style="color: #646970;">FALSE</span>'; ?></td>
                </tr>
                <tr>
                    <td><code>wpbm_is_paying()</code></td>
                    <td><?php echo wpbm_is_paying() ? '<span style="color: #00a32a;">TRUE</span>' : '<span style="color: #646970;">FALSE</span>'; ?></td>
                </tr>
                <tr>
                    <td><code>wpbm_is_trial()</code></td>
                    <td><?php echo wpbm_is_trial() ? '<span style="color: #00a32a;">TRUE</span>' : '<span style="color: #646970;">FALSE</span>'; ?></td>
                </tr>
                <tr>
                    <td><code>wpbm_get_plan_name()</code></td>
                    <td><strong><?php echo esc_html(wpbm_get_plan_name()); ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Activation Guide -->
    <h2><?php esc_html_e('How to Activate Pro License', 'wpblogmailer'); ?></h2>

    <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h3 style="margin-top: 0;"><?php esc_html_e('For Live Site:', 'wpblogmailer'); ?></h3>
        <ol style="line-height: 2;">
            <li><strong><?php esc_html_e('Install the plugin', 'wpblogmailer'); ?></strong> - Upload and activate WP Blog Mailer on your live site</li>
            <li><strong><?php esc_html_e('Navigate to Account page', 'wpblogmailer'); ?></strong> - Go to <code>Blog Mailer → Account</code> in your WordPress admin</li>
            <li><strong><?php esc_html_e('Enter your license key', 'wpblogmailer'); ?></strong> - Paste the license key you received via email</li>
            <li><strong><?php esc_html_e('Click Activate', 'wpblogmailer'); ?></strong> - The plugin will verify your license with Freemius</li>
            <li><strong><?php esc_html_e('Check this page', 'wpblogmailer'); ?></strong> - Return here to verify all features are enabled</li>
        </ol>

        <h3><?php esc_html_e('Troubleshooting:', 'wpblogmailer'); ?></h3>
        <ul style="line-height: 2;">
            <li><strong><?php esc_html_e('License shows as Free', 'wpblogmailer'); ?></strong> - Make sure you entered the correct license key</li>
            <li><strong><?php esc_html_e('Features not appearing', 'wpblogmailer'); ?></strong> - Clear your browser cache and WordPress object cache</li>
            <li><strong><?php esc_html_e('403 Error on Account page', 'wpblogmailer'); ?></strong> - Click "Connect Account" first to opt-in</li>
            <li><strong><?php esc_html_e('Site limit reached', 'wpblogmailer'); ?></strong> - Deactivate license from another site first</li>
        </ul>

        <h3><?php esc_html_e('For Development/Testing:', 'wpblogmailer'); ?></h3>
        <p><?php esc_html_e('Freemius licenses work on live domains only. For local testing, you have these options:', 'wpblogmailer'); ?></p>
        <ol style="line-height: 2;">
            <li><strong><?php esc_html_e('Use trial license', 'wpblogmailer'); ?></strong> - Start a 14-day trial from the Account page</li>
            <li><strong><?php esc_html_e('Use staging domain', 'wpblogmailer'); ?></strong> - Many licenses allow activation on staging.yourdomain.com</li>
            <li><strong><?php esc_html_e('Contact support', 'wpblogmailer'); ?></strong> - Request a development license for localhost testing</li>
        </ol>

        <div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1;">
            <p style="margin: 0;"><strong><?php esc_html_e('Need Help?', 'wpblogmailer'); ?></strong></p>
            <p style="margin: 10px 0 0 0;">
                <?php esc_html_e('If you\'re having issues activating your license, check the Account page for detailed error messages or contact support with your license key.', 'wpblogmailer'); ?>
            </p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div style="margin: 30px 0;">
        <a href="<?php echo admin_url('admin.php?page=wpblog-mailer-account'); ?>" class="button button-primary button-large">
            <?php esc_html_e('Go to Account & Licensing', 'wpblogmailer'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=wpbm-newsletter'); ?>" class="button button-large" style="margin-left: 10px;">
            <?php esc_html_e('Back to Dashboard', 'wpblogmailer'); ?>
        </a>
    </div>
</div>

<style>
.wpbm-license-status h2 {
    font-size: 20px;
    font-weight: 600;
    margin: 30px 0 15px 0;
}

.wpbm-license-status h3 {
    font-size: 16px;
    font-weight: 600;
    margin: 20px 0 10px 0;
}

.wpbm-license-status code {
    background: #f6f7f7;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: Consolas, Monaco, monospace;
    font-size: 13px;
}

.wpbm-license-status table th {
    font-weight: 600;
}

.wpbm-license-status ol li,
.wpbm-license-status ul li {
    margin: 8px 0;
}
</style>
