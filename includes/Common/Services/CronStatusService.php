<?php
/**
 * Cron Status Service
 *
 * Tracks cron job executions and provides health monitoring
 *
 * @package WPBlogMailer
 */

namespace WPBlogMailer\Common\Services;

use WPBlogMailer\Common\Utilities\Logger;

class CronStatusService {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new Logger();
    }

    /**
     * Log cron execution
     *
     * @param string $hook Cron hook name
     * @param string $status Status (success, failed, started)
     * @param string $message Optional message
     * @param array $details Optional additional details
     * @return int|false Log entry ID or false on failure
     */
    public function log_execution($hook, $status, $message = '', $details = []) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpbm_cron_log';

        $data = [
            'hook' => $hook,
            'status' => $status,
            'message' => $message,
            'details' => maybe_serialize($details),
            'executed_at' => current_time('mysql'),
        ];

        $result = $wpdb->insert($table_name, $data);

        if ($result === false) {
            $this->logger->error("Failed to log cron execution for hook: {$hook}");
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get cron execution logs
     *
     * @param array $args Query arguments
     * @return array Array of log entries
     */
    public function get_logs($args = []) {
        global $wpdb;

        $defaults = [
            'hook' => '',
            'status' => '',
            'limit' => 50,
            'offset' => 0,
            'order_by' => 'executed_at',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $table_name = $wpdb->prefix . 'wpbm_cron_log';

        $where = ['1=1'];
        $where_values = [];

        if (!empty($args['hook'])) {
            $where[] = 'hook = %s';
            $where_values[] = $args['hook'];
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        $where_clause = implode(' AND ', $where);

        $query = "SELECT * FROM {$table_name} WHERE {$where_clause}
                  ORDER BY {$args['order_by']} {$args['order']}
                  LIMIT %d OFFSET %d";

        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        $results = $wpdb->get_results($query);

        // Unserialize details
        foreach ($results as $result) {
            $result->details = maybe_unserialize($result->details);
        }

        return $results;
    }

    /**
     * Get last execution for a specific hook
     *
     * @param string $hook Cron hook name
     * @return object|null Last execution log entry
     */
    public function get_last_execution($hook) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpbm_cron_log';

        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name}
             WHERE hook = %s
             ORDER BY executed_at DESC
             LIMIT 1",
            $hook
        );

        $result = $wpdb->get_row($query);

        if ($result) {
            $result->details = maybe_unserialize($result->details);
        }

        return $result;
    }

    /**
     * Get cron health status
     *
     * @return array Health status information
     */
    public function get_health_status() {
        $status = [
            'overall_status' => 'healthy',
            'issues' => [],
            'jobs' => [],
        ];

        // Check main cron hooks
        $hooks = [
            'wpbm_send_newsletter' => [
                'name' => 'Newsletter Sending',
                'expected_frequency' => $this->get_expected_frequency(),
            ],
            'wpbm_cleanup_old_data' => [
                'name' => 'Data Cleanup',
                'expected_frequency' => 'weekly',
            ],
        ];

        // Only check queue processing for Starter+ users
        if (function_exists('wpbm_is_starter') && wpbm_is_starter()) {
            $hooks['wpbm_process_email_queue'] = [
                'name' => 'Email Queue Processing',
                'expected_frequency' => 'every_five_minutes',
            ];
        }

        foreach ($hooks as $hook => $info) {
            $job_status = $this->check_job_status($hook, $info);
            $status['jobs'][$hook] = $job_status;

            // Collect issues
            if (!empty($job_status['issues'])) {
                $status['issues'] = array_merge($status['issues'], $job_status['issues']);
            }

            // Update overall status
            if ($job_status['status'] === 'error') {
                $status['overall_status'] = 'error';
            } elseif ($job_status['status'] === 'warning' && $status['overall_status'] !== 'error') {
                $status['overall_status'] = 'warning';
            }
        }

        return $status;
    }

    /**
     * Check individual job status
     *
     * @param string $hook Hook name
     * @param array $info Job information
     * @return array Job status
     */
    private function check_job_status($hook, $info) {
        $job_status = [
            'name' => $info['name'],
            'status' => 'healthy',
            'next_run' => null,
            'last_run' => null,
            'last_status' => null,
            'issues' => [],
        ];

        // Check if job is scheduled
        $next_run = wp_next_scheduled($hook);

        if ($next_run === false) {
            $job_status['status'] = 'error';
            $job_status['issues'][] = "{$info['name']} is not scheduled";
        } else {
            $job_status['next_run'] = $next_run;

            // Check if it's overdue
            $time_until_run = $next_run - current_time('timestamp');
            if ($time_until_run < -3600) { // More than 1 hour overdue
                $job_status['status'] = 'warning';
                $job_status['issues'][] = "{$info['name']} appears to be overdue";
            }
        }

        // Check last execution
        $last_execution = $this->get_last_execution($hook);

        if ($last_execution) {
            $job_status['last_run'] = strtotime($last_execution->executed_at);
            $job_status['last_status'] = $last_execution->status;

            // Check for recent failures
            if ($last_execution->status === 'failed') {
                $job_status['status'] = 'warning';
                $job_status['issues'][] = "{$info['name']} failed on last execution: {$last_execution->message}";
            }

            // Check if it hasn't run in a while
            $hours_since_last_run = (current_time('timestamp') - strtotime($last_execution->executed_at)) / 3600;
            $expected_hours = $this->get_expected_hours($info['expected_frequency']);

            if ($expected_hours > 0 && $hours_since_last_run > ($expected_hours * 2)) {
                $job_status['status'] = 'warning';
                $job_status['issues'][] = "{$info['name']} hasn't run in " . round($hours_since_last_run, 1) . " hours";
            }
        }

        return $job_status;
    }

    /**
     * Get expected frequency from settings
     *
     * @return string Expected frequency
     */
    private function get_expected_frequency() {
        $settings = get_option('wpbm_settings', []);
        return isset($settings['schedule_frequency']) ? $settings['schedule_frequency'] : 'hourly';
    }

    /**
     * Convert frequency to expected hours
     *
     * @param string $frequency Frequency name
     * @return float Expected hours between runs
     */
    private function get_expected_hours($frequency) {
        $hours_map = [
            'hourly' => 1,
            'twicedaily' => 12,
            'daily' => 24,
            'weekly' => 168,
            'monthly' => 720,
            'every_five_minutes' => 0.083, // 5 minutes
            'every_fifteen_minutes' => 0.25, // 15 minutes
        ];

        return isset($hours_map[$frequency]) ? $hours_map[$frequency] : 0;
    }

    /**
     * Get statistics for cron executions
     *
     * @param int $days Number of days to look back (default 7)
     * @return array Statistics
     */
    public function get_statistics($days = 7) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpbm_cron_log';
        $date_limit = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $stats = [
            'total_executions' => 0,
            'successful' => 0,
            'failed' => 0,
            'success_rate' => 0,
            'by_hook' => [],
        ];

        // Get overall stats
        $query = $wpdb->prepare(
            "SELECT status, COUNT(*) as count
             FROM {$table_name}
             WHERE executed_at >= %s
             GROUP BY status",
            $date_limit
        );

        $results = $wpdb->get_results($query);

        foreach ($results as $result) {
            // Only count completed executions (success or failed), not 'started' status
            if ($result->status === 'success') {
                $stats['successful'] = $result->count;
                $stats['total_executions'] += $result->count;
            } elseif ($result->status === 'failed') {
                $stats['failed'] = $result->count;
                $stats['total_executions'] += $result->count;
            }
            // Ignore 'started' status in totals - it's just a marker, not a completion
        }

        // Calculate success rate
        if ($stats['total_executions'] > 0) {
            $stats['success_rate'] = round(($stats['successful'] / $stats['total_executions']) * 100, 2);
        }

        // Get stats by hook
        $query = $wpdb->prepare(
            "SELECT hook, status, COUNT(*) as count
             FROM {$table_name}
             WHERE executed_at >= %s
             GROUP BY hook, status",
            $date_limit
        );

        $results = $wpdb->get_results($query);

        foreach ($results as $result) {
            if (!isset($stats['by_hook'][$result->hook])) {
                $stats['by_hook'][$result->hook] = [
                    'total' => 0,
                    'successful' => 0,
                    'failed' => 0,
                ];
            }

            $stats['by_hook'][$result->hook]['total'] += $result->count;

            if ($result->status === 'success') {
                $stats['by_hook'][$result->hook]['successful'] = $result->count;
            } elseif ($result->status === 'failed') {
                $stats['by_hook'][$result->hook]['failed'] = $result->count;
            }
        }

        return $stats;
    }

    /**
     * Cleanup old log entries
     *
     * @param int $days Number of days to keep (default 30)
     * @return int Number of entries deleted
     */
    public function cleanup_old_logs($days = 30) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpbm_cron_log';
        $date_limit = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE executed_at < %s",
                $date_limit
            )
        );

        $this->logger->info("Cleaned up {$deleted} old cron log entries");

        return $deleted;
    }

    /**
     * Create cron log table
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpbm_cron_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            hook varchar(255) NOT NULL,
            status varchar(50) NOT NULL,
            message text,
            details longtext,
            executed_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY hook (hook),
            KEY status (status),
            KEY executed_at (executed_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
