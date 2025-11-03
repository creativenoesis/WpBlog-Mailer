<?php
/**
 * Send Log Service
 * Tracks all email sends with success/failure status
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Common\Services;

use WPBlogMailer\Common\Database\Database;
use WPBlogMailer\Common\Database\Schema;

class SendLogService {

    /**
     * @var Database
     */
    private $database;

    public function __construct(Database $database) {
        $this->database = $database;
    }

    /**
     * Log an email send attempt
     *
     * @param array $args {
     *     @type string $recipient_email Required
     *     @type string $recipient_name Optional
     *     @type int $subscriber_id Optional
     *     @type string $subject Required
     *     @type string $template_type Optional (default: 'basic')
     *     @type string $campaign_type Optional (default: 'newsletter')
     *     @type string $status Required ('success' or 'failed')
     *     @type string $error_message Optional (for failures)
     *     @type int $queue_id Optional (if from queue)
     * }
     * @return int|false Log ID on success, false on failure
     */
    public function log_send($args) {
        global $wpdb;
        $table = $wpdb->prefix . Schema::TABLE_SEND_LOG;

        $defaults = [
            'recipient_email' => '',
            'recipient_name' => '',
            'subscriber_id' => null,
            'subject' => '',
            'template_type' => 'basic',
            'campaign_type' => 'newsletter',
            'status' => 'success',
            'error_message' => null,
            'sent_at' => current_time('mysql'),
            'queue_id' => null,
        ];

        $args = wp_parse_args($args, $defaults);

        $result = $wpdb->insert(
            $table,
            [
                'recipient_email' => $args['recipient_email'],
                'recipient_name' => $args['recipient_name'],
                'subscriber_id' => $args['subscriber_id'],
                'subject' => $args['subject'],
                'template_type' => $args['template_type'],
                'campaign_type' => $args['campaign_type'],
                'status' => $args['status'],
                'error_message' => $args['error_message'],
                'sent_at' => $args['sent_at'],
                'queue_id' => $args['queue_id'],
            ],
            [
                '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d'
            ]
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get send logs with pagination and filtering
     *
     * @param array $args {
     *     @type int $per_page Number of logs per page (default: 20)
     *     @type int $page Current page (default: 1)
     *     @type string $status Filter by status (success/failed)
     *     @type string $campaign_type Filter by campaign type
     *     @type string $search Search in email/name/subject
     *     @type string $orderby Order by column (default: 'sent_at')
     *     @type string $order ASC or DESC (default: 'DESC')
     * }
     * @return array {
     *     @type array $logs Array of log records
     *     @type int $total Total count of logs
     *     @type int $pages Total number of pages
     * }
     */
    public function get_logs($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . Schema::TABLE_SEND_LOG;

        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'campaign_type' => '',
            'search' => '',
            'orderby' => 'sent_at',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where = ['1=1'];
        $where_values = [];

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if (!empty($args['campaign_type'])) {
            $where[] = 'campaign_type = %s';
            $where_values[] = $args['campaign_type'];
        }

        if (!empty($args['search'])) {
            $where[] = '(recipient_email LIKE %s OR recipient_name LIKE %s OR subject LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $count_sql = $wpdb->prepare($count_sql, $where_values);
        }
        $total = (int) $wpdb->get_var($count_sql);

        // Build ORDER BY clause
        $allowed_orderby = ['sent_at', 'recipient_email', 'status', 'campaign_type'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'sent_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Calculate offset
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Get logs
        $logs_sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $logs_values = array_merge($where_values, [$args['per_page'], $offset]);
        $logs_sql = $wpdb->prepare($logs_sql, $logs_values);
        $logs = $wpdb->get_results($logs_sql);

        $pages = ceil($total / $args['per_page']);

        return [
            'logs' => $logs,
            'total' => $total,
            'pages' => $pages,
        ];
    }

    /**
     * Get send statistics
     *
     * @param int $days Number of days to look back (default: 30)
     * @return array Statistics array
     */
    public function get_statistics($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . Schema::TABLE_SEND_LOG;

        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $sql = $wpdb->prepare(
            "SELECT
                COUNT(*) as total_sent,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                COUNT(DISTINCT recipient_email) as unique_recipients
            FROM {$table}
            WHERE sent_at >= %s",
            $date_from
        );

        $stats = $wpdb->get_row($sql, ARRAY_A);

        // Calculate success rate
        $stats['success_rate'] = $stats['total_sent'] > 0
            ? round(($stats['successful'] / $stats['total_sent']) * 100, 2)
            : 0;

        return $stats;
    }

    /**
     * Delete old logs
     *
     * @param int $days Keep logs newer than this many days
     * @return int Number of logs deleted
     */
    public function cleanup_old_logs($days = 90) {
        global $wpdb;
        $table = $wpdb->prefix . Schema::TABLE_SEND_LOG;

        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE sent_at < %s",
                $date_threshold
            )
        );

        return $result !== false ? $result : 0;
    }
}
