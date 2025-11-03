<?php
/**
 * Cron Status View
 *
 * Displays cron job health, execution history, and monitoring information
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

// Get current plan tier
$is_free = wpbm_is_free_plan();
$is_starter = wpbm_is_starter();
$current_tier = wpbm_get_plan_name();

// Get services and data
$cron_status_service = new \WPBlogMailer\Common\Services\CronStatusService();

// Get health status
$health = $cron_status_service->get_health_status();

// Tier-based limitations
$stats_days = $is_free ? 1 : 7; // Free: 24 hours, Paid: 7 days
$logs_limit = $is_free ? 5 : 20; // Free: 5 logs, Paid: 20 logs

// Get statistics
$stats = $cron_status_service->get_statistics($stats_days);

// Get recent execution logs
$recent_logs = $cron_status_service->get_logs([
    'limit' => $logs_limit,
    'order_by' => 'executed_at',
    'order' => 'DESC'
]);

// Get settings
$settings = get_option('wpbm_settings', []);
$schedule_frequency = isset($settings['schedule_frequency']) ? $settings['schedule_frequency'] : 'hourly';
$schedule_day = isset($settings['schedule_day']) ? $settings['schedule_day'] : 'Monday';
$schedule_time = isset($settings['schedule_time']) ? $settings['schedule_time'] : '09:00';

// Map hook names to friendly names
$hook_names = [
    'wpbm_send_newsletter' => 'Newsletter Sending',
    'wpbm_process_email_queue' => 'Email Queue Processing',
    'wpbm_cleanup_old_data' => 'Data Cleanup',
];

// Map frequency to friendly names
$frequency_names = [
    'hourly' => 'Hourly',
    'twicedaily' => 'Twice Daily',
    'daily' => 'Daily',
    'weekly' => 'Weekly',
    'monthly' => 'Monthly',
    'every_five_minutes' => 'Every 5 Minutes',
    'every_fifteen_minutes' => 'Every 15 Minutes',
];

// Map status to colors
$status_colors = [
    'healthy' => '#00a32a',
    'warning' => '#dba617',
    'error' => '#d63638',
];

$overall_status_icon = [
    'healthy' => 'yes-alt',
    'warning' => 'warning',
    'error' => 'dismiss',
];
?>

<div class="wrap wpbm-cron-status">
    <h1 class="wp-heading-inline"><?php esc_html_e('Cron Status & Health', 'wpblogmailer'); ?></h1>

    <span class="wpbm-plan-badge wpbm-plan-<?php echo esc_attr($current_tier); ?>" style="display: inline-block; margin-left: 10px; padding: 5px 12px; background: <?php echo $is_free ? '#646970' : ($is_starter && !wpbm_is_pro() ? '#00a32a' : '#2271b1'); ?>; color: #fff; border-radius: 3px; font-size: 12px; font-weight: 600; text-transform: uppercase; vertical-align: middle;">
        <?php echo esc_html($current_tier); ?> <?php esc_html_e('Plan', 'wpblogmailer'); ?>
    </span>

    <?php if (isset($_GET['logs_cleaned']) && $_GET['logs_cleaned'] === '1'): ?>
    <div class="notice notice-success is-dismissible" style="margin-top: 20px;">
        <p><strong><?php esc_html_e('Cron logs cleaned successfully!', 'wpblogmailer'); ?></strong></p>
    </div>
    <?php endif; ?>

    <?php if ($is_free): ?>
    <div class="notice notice-info" style="margin: 20px 0;">
        <p>
            <strong><?php esc_html_e('🔒 Free Plan Limitations:', 'wpblogmailer'); ?></strong>
            <?php esc_html_e('You\'re viewing basic cron monitoring (24-hour stats, 5 recent logs). Upgrade to Starter or Pro for extended history, detailed logs, and priority support.', 'wpblogmailer'); ?>
            <a href="<?php echo function_exists('wpbm_fs') ? wpbm_fs()->get_upgrade_url() : '#'; ?>" class="button button-primary" style="margin-left: 10px;">
                <?php esc_html_e('Upgrade Now', 'wpblogmailer'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- Overall Health Status -->
    <div class="wpbm-cron-health-card" style="margin: 20px 0; padding: 20px; background: #fff; border-left: 4px solid <?php echo esc_attr($status_colors[$health['overall_status']]); ?>; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <div style="display: flex; align-items: center;">
            <span class="dashicons dashicons-<?php echo esc_attr($overall_status_icon[$health['overall_status']]); ?>" style="font-size: 48px; color: <?php echo esc_attr($status_colors[$health['overall_status']]); ?>; margin-right: 15px; flex-shrink: 0;"></span>
            <div>
                <h2 style="margin: 0; font-size: 24px;">
                    <?php
                    if ($health['overall_status'] === 'healthy') {
                        esc_html_e('All Systems Running', 'wpblogmailer');
                    } elseif ($health['overall_status'] === 'warning') {
                        esc_html_e('Some Issues Detected', 'wpblogmailer');
                    } else {
                        esc_html_e('Critical Issues Found', 'wpblogmailer');
                    }
                    ?>
                </h2>
                <p style="margin: 5px 0 0 0; color: #646970;">
                    <?php
                    if (empty($health['issues'])) {
                        esc_html_e('All cron jobs are scheduled and running correctly.', 'wpblogmailer');
                    } else {
                        echo esc_html(sprintf(__('%d issue(s) need attention.', 'wpblogmailer'), count($health['issues'])));
                    }
                    ?>
                </p>
            </div>
        </div>

        <?php if (!empty($health['issues'])): ?>
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #dcdcde;">
            <h3 style="margin: 0 0 10px 0; font-size: 16px;"><?php esc_html_e('Issues:', 'wpblogmailer'); ?></h3>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($health['issues'] as $issue): ?>
                <li style="color: #d63638; margin: 5px 0;"><?php echo esc_html($issue); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <!-- Cron Job Details -->
    <h2 style="margin-top: 30px;"><?php esc_html_e('Cron Job Details', 'wpblogmailer'); ?></h2>

    <div class="wpbm-cron-jobs-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin: 20px 0;">
        <?php foreach ($health['jobs'] as $hook => $job): ?>
        <div class="wpbm-cron-job-card" style="background: #fff; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-left: 4px solid <?php echo esc_attr($status_colors[$job['status']]); ?>;">
            <h3 style="margin: 0 0 10px 0; font-size: 18px; display: flex; align-items: center;">
                <span class="dashicons dashicons-<?php echo esc_attr($job['status'] === 'healthy' ? 'yes' : 'warning'); ?>" style="color: <?php echo esc_attr($status_colors[$job['status']]); ?>; margin-right: 8px; flex-shrink: 0;"></span>
                <span><?php echo esc_html($job['name']); ?></span>
            </h3>

            <div style="margin: 15px 0;">
                <strong><?php esc_html_e('Next Run:', 'wpblogmailer'); ?></strong>
                <?php if ($job['next_run']): ?>
                    <br><code><?php echo esc_html(wp_date('Y-m-d H:i:s', $job['next_run'])); ?></code>
                    <br><small style="color: #646970;"><?php echo esc_html(human_time_diff($job['next_run'], time())); ?> <?php echo $job['next_run'] > time() ? esc_html__('from now', 'wpblogmailer') : esc_html__('ago', 'wpblogmailer'); ?></small>
                <?php else: ?>
                    <br><span style="color: #d63638;"><?php esc_html_e('Not scheduled', 'wpblogmailer'); ?></span>
                <?php endif; ?>
            </div>

            <div style="margin: 15px 0;">
                <strong><?php esc_html_e('Last Run:', 'wpblogmailer'); ?></strong>
                <?php if ($job['last_run']): ?>
                    <br><code><?php echo esc_html(wp_date('Y-m-d H:i:s', $job['last_run'])); ?></code>
                    <br><small style="color: #646970;"><?php echo esc_html(human_time_diff($job['last_run'], time())); ?> <?php esc_html_e('ago', 'wpblogmailer'); ?></small>
                    <br><span class="wpbm-status-badge wpbm-status-<?php echo esc_attr($job['last_status']); ?>" style="display: inline-block; margin-top: 5px; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; background: <?php echo $job['last_status'] === 'success' ? '#d7fce8' : '#ffd8d8'; ?>; color: <?php echo $job['last_status'] === 'success' ? '#1e8e3e' : '#d63638'; ?>;">
                        <?php echo esc_html($job['last_status']); ?>
                    </span>
                <?php else: ?>
                    <br><span style="color: #646970;"><?php esc_html_e('Never run', 'wpblogmailer'); ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($job['issues'])): ?>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #f0f0f1;">
                <strong style="color: #d63638;"><?php esc_html_e('Issues:', 'wpblogmailer'); ?></strong>
                <ul style="margin: 5px 0 0 0; padding-left: 20px;">
                    <?php foreach ($job['issues'] as $issue): ?>
                    <li style="color: #d63638; font-size: 13px;"><?php echo esc_html($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Configuration Info -->
    <h2 style="margin-top: 30px;"><?php esc_html_e('Current Configuration', 'wpblogmailer'); ?></h2>

    <div style="background: #fff; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
        <table class="widefat" style="border: none;">
            <tr>
                <th style="width: 200px; padding: 10px;"><?php esc_html_e('Newsletter Schedule', 'wpblogmailer'); ?></th>
                <td style="padding: 10px;">
                    <strong><?php echo esc_html(isset($frequency_names[$schedule_frequency]) ? $frequency_names[$schedule_frequency] : ucfirst($schedule_frequency)); ?></strong>
                    <?php if ($schedule_frequency === 'weekly'): ?>
                        <?php esc_html_e('on', 'wpblogmailer'); ?> <?php echo esc_html($schedule_day); ?> <?php esc_html_e('at', 'wpblogmailer'); ?> <?php echo esc_html($schedule_time); ?>
                    <?php elseif (in_array($schedule_frequency, ['daily', 'monthly'])): ?>
                        <?php esc_html_e('at', 'wpblogmailer'); ?> <?php echo esc_html($schedule_time); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr style="background: #f6f7f7;">
                <th style="padding: 10px;"><?php esc_html_e('WordPress Cron', 'wpblogmailer'); ?></th>
                <td style="padding: 10px;">
                    <?php if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON): ?>
                        <span style="color: #dba617; display: inline-flex; align-items: center;">
                            <span class="dashicons dashicons-warning" style="font-size: 16px; margin-right: 5px;"></span>
                            <span><?php esc_html_e('DISABLED - Using system cron', 'wpblogmailer'); ?></span>
                        </span>
                        <br><small style="color: #646970;"><?php esc_html_e('Make sure you have a system cron job configured to call wp-cron.php', 'wpblogmailer'); ?></small>
                    <?php else: ?>
                        <span style="color: #00a32a; display: inline-flex; align-items: center;">
                            <span class="dashicons dashicons-yes" style="font-size: 16px; margin-right: 5px;"></span>
                            <span><?php esc_html_e('ENABLED - Using WordPress cron', 'wpblogmailer'); ?></span>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Statistics -->
    <h2 style="margin-top: 30px;">
        <?php
        if ($is_free) {
            esc_html_e('Execution Statistics (Last 24 Hours)', 'wpblogmailer');
        } else {
            esc_html_e('Execution Statistics (Last 7 Days)', 'wpblogmailer');
        }
        ?>
        <?php if ($is_free): ?>
            <a href="<?php echo function_exists('wpbm_fs') ? wpbm_fs()->get_upgrade_url() : '#'; ?>" class="button button-small" style="margin-left: 10px; vertical-align: middle;">
                <?php esc_html_e('Upgrade for 7-Day History', 'wpblogmailer'); ?>
            </a>
        <?php endif; ?>
    </h2>

    <div class="wpbm-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
        <div style="background: #fff; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <div style="color: #646970; font-size: 13px; margin-bottom: 5px;"><?php esc_html_e('Total Executions', 'wpblogmailer'); ?></div>
            <div style="font-size: 32px; font-weight: 600; color: #1d2327;"><?php echo esc_html(number_format_i18n($stats['total_executions'])); ?></div>
        </div>

        <div style="background: #fff; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <div style="color: #646970; font-size: 13px; margin-bottom: 5px;"><?php esc_html_e('Successful', 'wpblogmailer'); ?></div>
            <div style="font-size: 32px; font-weight: 600; color: #00a32a;"><?php echo esc_html(number_format_i18n($stats['successful'])); ?></div>
        </div>

        <div style="background: #fff; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <div style="color: #646970; font-size: 13px; margin-bottom: 5px;"><?php esc_html_e('Failed', 'wpblogmailer'); ?></div>
            <div style="font-size: 32px; font-weight: 600; color: #d63638;"><?php echo esc_html(number_format_i18n($stats['failed'])); ?></div>
        </div>

        <div style="background: #fff; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <div style="color: #646970; font-size: 13px; margin-bottom: 5px;"><?php esc_html_e('Success Rate', 'wpblogmailer'); ?></div>
            <div style="font-size: 32px; font-weight: 600; color: <?php echo $stats['success_rate'] >= 95 ? '#00a32a' : ($stats['success_rate'] >= 80 ? '#dba617' : '#d63638'); ?>;">
                <?php echo esc_html(number_format_i18n($stats['success_rate'], 1)); ?>%
            </div>
        </div>
    </div>

    <!-- Execution History -->
    <h2 style="margin-top: 30px;">
        <?php esc_html_e('Recent Execution History', 'wpblogmailer'); ?>
        <?php if ($is_free): ?>
            <span style="color: #646970; font-weight: 400; font-size: 14px; margin-left: 10px;">
                (<?php echo esc_html(sprintf(__('Showing last %d executions', 'wpblogmailer'), $logs_limit)); ?>)
            </span>
        <?php endif; ?>
    </h2>

    <?php if ($is_free && !empty($recent_logs)): ?>
        <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 15px 0;">
            <p style="margin: 0;">
                <strong><?php esc_html_e('📊 Want More History?', 'wpblogmailer'); ?></strong>
                <?php esc_html_e('Upgrade to Starter or Pro to see up to 20 recent executions and detailed execution logs.', 'wpblogmailer'); ?>
                <a href="<?php echo function_exists('wpbm_fs') ? wpbm_fs()->get_upgrade_url() : '#'; ?>" class="button button-primary button-small" style="margin-left: 10px;">
                    <?php esc_html_e('View Plans', 'wpblogmailer'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <div style="background: #fff; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
        <?php if (empty($recent_logs)): ?>
            <p style="color: #646970; text-align: center; padding: 20px;">
                <?php esc_html_e('No cron executions logged yet. Logs will appear here once cron jobs start running.', 'wpblogmailer'); ?>
            </p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 200px;"><?php esc_html_e('Job Name', 'wpblogmailer'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Status', 'wpblogmailer'); ?></th>
                        <th style="width: 180px;"><?php esc_html_e('Executed At', 'wpblogmailer'); ?></th>
                        <th><?php esc_html_e('Message', 'wpblogmailer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log): ?>
                    <tr>
                        <td><strong><?php echo esc_html(isset($hook_names[$log->hook]) ? $hook_names[$log->hook] : $log->hook); ?></strong></td>
                        <td>
                            <span class="wpbm-status-badge wpbm-status-<?php echo esc_attr($log->status); ?>" style="display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; background: <?php echo $log->status === 'success' ? '#d7fce8' : ($log->status === 'failed' ? '#ffd8d8' : '#f0f0f1'); ?>; color: <?php echo $log->status === 'success' ? '#1e8e3e' : ($log->status === 'failed' ? '#d63638' : '#646970'); ?>;">
                                <?php echo esc_html($log->status); ?>
                            </span>
                        </td>
                        <td>
                            <code><?php echo esc_html(wp_date('Y-m-d H:i:s', strtotime($log->executed_at))); ?></code>
                            <br><small style="color: #646970;"><?php echo esc_html(human_time_diff(strtotime($log->executed_at), time())); ?> <?php esc_html_e('ago', 'wpblogmailer'); ?></small>
                        </td>
                        <td>
                            <?php if (!empty($log->message)): ?>
                                <?php echo esc_html($log->message); ?>
                            <?php else: ?>
                                <span style="color: #646970;">-</span>
                            <?php endif; ?>
                            <?php if (!empty($log->details) && is_array($log->details)): ?>
                                <br><small style="color: #646970;">
                                    <?php if (isset($log->details['success'])): ?>
                                        <?php echo esc_html(sprintf(__('Success: %d', 'wpblogmailer'), $log->details['success'])); ?>
                                    <?php endif; ?>
                                    <?php if (isset($log->details['error'])): ?>
                                        <?php echo esc_html(sprintf(__('| Errors: %d', 'wpblogmailer'), $log->details['error'])); ?>
                                    <?php endif; ?>
                                    <?php if (isset($log->details['processed'])): ?>
                                        <?php echo esc_html(sprintf(__('Processed: %d', 'wpblogmailer'), $log->details['processed'])); ?>
                                    <?php endif; ?>
                                    <?php if (isset($log->details['sent'])): ?>
                                        <?php echo esc_html(sprintf(__('| Sent: %d', 'wpblogmailer'), $log->details['sent'])); ?>
                                    <?php endif; ?>
                                    <?php if (isset($log->details['failed'])): ?>
                                        <?php echo esc_html(sprintf(__('| Failed: %d', 'wpblogmailer'), $log->details['failed'])); ?>
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Help & Troubleshooting -->
    <h2 style="margin-top: 30px;"><?php esc_html_e('Help & Troubleshooting', 'wpblogmailer'); ?></h2>

    <div style="background: #fff; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
        <h3 style="margin-top: 0;"><?php esc_html_e('Cron Not Running?', 'wpblogmailer'); ?></h3>

        <p><strong><?php esc_html_e('Common Issues:', 'wpblogmailer'); ?></strong></p>
        <ul style="line-height: 1.8;">
            <li><?php esc_html_e('Your site needs regular traffic for WordPress cron to work. If you have low traffic, consider using a real system cron job.', 'wpblogmailer'); ?></li>
            <li><?php esc_html_e('Some hosting providers disable or throttle WordPress cron. Check with your host.', 'wpblogmailer'); ?></li>
            <li><?php esc_html_e('If DISABLE_WP_CRON is true, you must set up a system cron job to call wp-cron.php regularly.', 'wpblogmailer'); ?></li>
        </ul>

        <p><strong><?php esc_html_e('Setting Up System Cron:', 'wpblogmailer'); ?></strong></p>
        <p><?php esc_html_e('Add this to your crontab:', 'wpblogmailer'); ?></p>
        <pre style="background: #f6f7f7; padding: 15px; border-radius: 3px; overflow-x: auto;">*/5 * * * * wget -q -O - <?php echo esc_url(site_url('wp-cron.php')); ?> >/dev/null 2>&1</pre>
        <p><small style="color: #646970;"><?php esc_html_e('Or use curl:', 'wpblogmailer'); ?></small></p>
        <pre style="background: #f6f7f7; padding: 15px; border-radius: 3px; overflow-x: auto;">*/5 * * * * curl <?php echo esc_url(site_url('wp-cron.php')); ?> >/dev/null 2>&1</pre>

        <p style="margin-top: 20px;"><strong><?php esc_html_e('Need More Help?', 'wpblogmailer'); ?></strong></p>
        <p><?php esc_html_e('Check the Send Log page for detailed email sending errors and the WordPress debug log for additional information.', 'wpblogmailer'); ?></p>

        <?php if (!$is_free): ?>
        <div style="background: #d7fce8; border-left: 4px solid #00a32a; padding: 15px; margin-top: 15px;">
            <p style="margin: 0;">
                <strong><?php esc_html_e('✅ Priority Support Available', 'wpblogmailer'); ?></strong><br>
                <?php esc_html_e('As a paying customer, you have access to priority support. Contact us if you need help troubleshooting cron issues.', 'wpblogmailer'); ?>
            </p>
        </div>
        <?php else: ?>
        <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin-top: 15px;">
            <p style="margin: 0;">
                <strong><?php esc_html_e('Need Priority Support?', 'wpblogmailer'); ?></strong><br>
                <?php esc_html_e('Upgrade to Starter or Pro to get priority email support and faster response times.', 'wpblogmailer'); ?>
                <a href="<?php echo function_exists('wpbm_fs') ? wpbm_fs()->get_upgrade_url() : '#'; ?>" class="button button-small" style="margin-left: 10px;">
                    <?php esc_html_e('View Plans', 'wpblogmailer'); ?>
                </a>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.wpbm-cron-status h2 {
    font-size: 20px;
    font-weight: 600;
    margin: 30px 0 15px 0;
}

.wpbm-cron-status h3 {
    font-size: 16px;
    font-weight: 600;
}

.wpbm-cron-status code {
    background: #f6f7f7;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: Consolas, Monaco, monospace;
    font-size: 13px;
}

.wpbm-cron-status pre {
    margin: 10px 0;
}

.wpbm-cron-status table th {
    font-weight: 600;
    background: #f6f7f7;
}
</style>
