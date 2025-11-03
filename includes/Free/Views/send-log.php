<?php
/**
 * Send Log View
 * Displays email send history with filtering
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

// Get SendLogService from container
$plugin = \WPBlogMailer\Core\Plugin::instance();
$send_log_service = $plugin->container->get(\WPBlogMailer\Common\Services\SendLogService::class);

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$campaign_filter = isset($_GET['campaign_type']) ? sanitize_text_field($_GET['campaign_type']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

// Get logs
$logs_data = $send_log_service->get_logs([
    'per_page' => 20,
    'page' => $paged,
    'status' => $status_filter,
    'campaign_type' => $campaign_filter,
    'search' => $search,
]);

// Get statistics
$stats = $send_log_service->get_statistics(30);
?>

<div class="wrap wpbm-send-log-page">
    <h1 class="wp-heading-inline"><?php esc_html_e('Email Send Log', 'wpblogmailer'); ?></h1>
    <hr class="wp-header-end">

    <!-- Statistics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
        <div style="background: #fff; padding: 20px; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="font-size: 13px; color: #646970; margin-bottom: 5px;"><?php esc_html_e('Total Sent (30 days)', 'wpblogmailer'); ?></div>
            <div style="font-size: 28px; font-weight: 600; color: #2271b1;"><?php echo number_format($stats['total_sent']); ?></div>
        </div>
        <div style="background: #fff; padding: 20px; border-left: 4px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="font-size: 13px; color: #646970; margin-bottom: 5px;"><?php esc_html_e('Successful', 'wpblogmailer'); ?></div>
            <div style="font-size: 28px; font-weight: 600; color: #00a32a;"><?php echo number_format($stats['successful']); ?></div>
        </div>
        <div style="background: #fff; padding: 20px; border-left: 4px solid #d63638; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="font-size: 13px; color: #646970; margin-bottom: 5px;"><?php esc_html_e('Failed', 'wpblogmailer'); ?></div>
            <div style="font-size: 28px; font-weight: 600; color: #d63638;"><?php echo number_format($stats['failed']); ?></div>
        </div>
        <div style="background: #fff; padding: 20px; border-left: 4px solid #50575e; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="font-size: 13px; color: #646970; margin-bottom: 5px;"><?php esc_html_e('Success Rate', 'wpblogmailer'); ?></div>
            <div style="font-size: 28px; font-weight: 600; color: #50575e;"><?php echo $stats['success_rate']; ?>%</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="tablenav top" style="background: #fff; padding: 10px; margin-bottom: 10px;">
        <form method="get" action="">
            <input type="hidden" name="page" value="wpbm-send-log">

            <div style="display: flex; gap: 10px; align-items: center;">
                <select name="status" id="filter-status">
                    <option value=""><?php esc_html_e('All Statuses', 'wpblogmailer'); ?></option>
                    <option value="success" <?php selected($status_filter, 'success'); ?>><?php esc_html_e('Success', 'wpblogmailer'); ?></option>
                    <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php esc_html_e('Failed', 'wpblogmailer'); ?></option>
                </select>

                <select name="campaign_type" id="filter-campaign">
                    <option value=""><?php esc_html_e('All Campaigns', 'wpblogmailer'); ?></option>
                    <option value="newsletter" <?php selected($campaign_filter, 'newsletter'); ?>><?php esc_html_e('Newsletter', 'wpblogmailer'); ?></option>
                    <option value="custom" <?php selected($campaign_filter, 'custom'); ?>><?php esc_html_e('Custom Email', 'wpblogmailer'); ?></option>
                </select>

                <input type="search" name="s" value="<?php echo esc_attr($search); ?>"
                       placeholder="<?php esc_attr_e('Search emails...', 'wpblogmailer'); ?>" style="width: 250px;">

                <button type="submit" class="button"><?php esc_html_e('Filter', 'wpblogmailer'); ?></button>

                <?php if ($status_filter || $campaign_filter || $search): ?>
                    <a href="?page=wpbm-send-log" class="button"><?php esc_html_e('Clear Filters', 'wpblogmailer'); ?></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 50px;"><?php esc_html_e('ID', 'wpblogmailer'); ?></th>
                <th style="width: 180px;"><?php esc_html_e('Date/Time', 'wpblogmailer'); ?></th>
                <th><?php esc_html_e('Recipient', 'wpblogmailer'); ?></th>
                <th><?php esc_html_e('Subject', 'wpblogmailer'); ?></th>
                <th style="width: 100px;"><?php esc_html_e('Campaign', 'wpblogmailer'); ?></th>
                <th style="width: 80px;"><?php esc_html_e('Status', 'wpblogmailer'); ?></th>
                <th><?php esc_html_e('Error Message', 'wpblogmailer'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($logs_data['logs'])): ?>
                <?php foreach ($logs_data['logs'] as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log->id); ?></td>
                        <td><?php echo esc_html(wp_date('Y-m-d H:i:s', strtotime($log->sent_at))); ?></td>
                        <td>
                            <strong><?php echo esc_html($log->recipient_email); ?></strong>
                            <?php if ($log->recipient_name): ?>
                                <br><span style="color: #646970; font-size: 12px;"><?php echo esc_html($log->recipient_name); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($log->subject); ?></td>
                        <td>
                            <span class="wpbm-campaign-badge wpbm-campaign-<?php echo esc_attr($log->campaign_type); ?>">
                                <?php echo esc_html(ucfirst($log->campaign_type)); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log->status === 'success'): ?>
                                <span class="wpbm-status-badge wpbm-status-success">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php esc_html_e('Success', 'wpblogmailer'); ?>
                                </span>
                            <?php else: ?>
                                <span class="wpbm-status-badge wpbm-status-failed">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php esc_html_e('Failed', 'wpblogmailer'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log->error_message): ?>
                                <details>
                                    <summary style="cursor: pointer; color: #d63638;">
                                        <?php esc_html_e('View Error', 'wpblogmailer'); ?>
                                    </summary>
                                    <div style="margin-top: 5px; padding: 8px; background: #fff8e5; border-left: 3px solid #d63638; font-size: 12px;">
                                        <?php echo esc_html($log->error_message); ?>
                                    </div>
                                </details>
                            <?php else: ?>
                                <span style="color: #8c8f94;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: #646970;">
                        <?php esc_html_e('No send logs found.', 'wpblogmailer'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($logs_data['pages'] > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'current' => $paged,
                    'total' => $logs_data['pages'],
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.wpbm-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}
.wpbm-status-success {
    background: #d7f7e3;
    color: #00550a;
}
.wpbm-status-failed {
    background: #ffebee;
    color: #8b0000;
}
.wpbm-status-badge .dashicons {
    width: 14px;
    height: 14px;
    font-size: 14px;
}
.wpbm-campaign-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    background: #f0f0f1;
    color: #50575e;
}
.wpbm-campaign-newsletter {
    background: #e7f5ff;
    color: #0c5687;
}
.wpbm-campaign-custom {
    background: #fff3e0;
    color: #7d4e04;
}
</style>
