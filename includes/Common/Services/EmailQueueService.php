<?php
/**
 * Email Queue Service
 * Handles background email processing with tier-based rate limiting
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Common\Services;

use WPBlogMailer\Common\Database\Database;
use WPBlogMailer\Common\Utilities\Logger;

/**
 * EmailQueueService Class
 *
 * Manages email queue for background processing:
 * - Adds emails to queue
 * - Processes queue in batches
 * - Implements tier-based rate limiting
 * - Handles retries for failed sends
 */
class EmailQueueService {

    /**
     * @var Database
     */
    private $db;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var BaseEmailService
     */
    private $email_service;

    /**
     * Queue status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Campaign type constants
     */
    const CAMPAIGN_NEWSLETTER = 'newsletter';
    const CAMPAIGN_CUSTOM = 'custom';
    const CAMPAIGN_AUTOMATED = 'automated';

    /**
     * Tier-based rate limits (emails per batch)
     */
    const RATE_LIMIT_FREE = 50;
    const RATE_LIMIT_STARTER = 100;
    const RATE_LIMIT_PRO = 500;

    /**
     * Constructor
     *
     * @param Database $db
     * @param Logger $logger
     * @param BaseEmailService $email_service
     */
    public function __construct(Database $db, Logger $logger, BaseEmailService $email_service) {
        $this->db = $db;
        $this->logger = $logger;
        $this->email_service = $email_service;
    }

    /**
     * Add email to queue
     *
     * @param string $recipient_email
     * @param string $subject
     * @param string $message
     * @param array $args Optional arguments (headers, template_type, campaign_type, priority, subscriber_id, scheduled_for)
     * @return int|false Queue ID on success, false on failure
     */
    public function add_to_queue($recipient_email, $subject, $message, $args = []) {
        global $wpdb;

        $defaults = [
            'headers' => [],
            'template_type' => 'basic',
            'campaign_type' => self::CAMPAIGN_NEWSLETTER,
            'priority' => 5,
            'subscriber_id' => null,
            'scheduled_for' => current_time('mysql'),
        ];

        $args = wp_parse_args($args, $defaults);

        $data = [
            'recipient_email' => sanitize_email($recipient_email),
            'subscriber_id' => $args['subscriber_id'],
            'subject' => sanitize_text_field($subject),
            'message' => $message,
            'headers' => is_array($args['headers']) ? maybe_serialize($args['headers']) : $args['headers'],
            'template_type' => sanitize_text_field($args['template_type']),
            'campaign_type' => sanitize_text_field($args['campaign_type']),
            'status' => self::STATUS_PENDING,
            'priority' => absint($args['priority']),
            'attempts' => 0,
            'max_attempts' => 3,
            'scheduled_for' => $args['scheduled_for'],
            'created_at' => current_time('mysql'),
        ];

        $table_name = $wpdb->prefix . 'wpbm_email_queue';
        $result = $wpdb->insert($table_name, $data);

        if ($result === false) {
            $this->logger->error('Failed to add email to queue: ' . $wpdb->last_error);
            return false;
        }

        $queue_id = $wpdb->insert_id;
        $this->logger->info("Email queued successfully. Queue ID: {$queue_id}, Recipient: {$recipient_email}");

        return $queue_id;
    }

    /**
     * Process email queue in batches based on tier limits
     *
     * @return array Processing results with counts
     */
    public function process_queue() {
        $this->logger->info('Starting queue processing');

        // Get batch size based on tier
        $batch_size = $this->get_batch_size();

        // Get pending emails
        $pending_emails = $this->get_pending_emails($batch_size);

        if (empty($pending_emails)) {
            $this->logger->info('No pending emails in queue');
            return [
                'processed' => 0,
                'sent' => 0,
                'failed' => 0,
                'message' => 'No emails in queue'
            ];
        }

        $sent_count = 0;
        $failed_count = 0;

        foreach ($pending_emails as $queue_item) {
            // Mark as processing
            $this->update_status($queue_item->id, self::STATUS_PROCESSING);

            // Send the email
            $result = $this->send_queued_email($queue_item);

            if ($result) {
                $sent_count++;
                $this->update_status($queue_item->id, self::STATUS_SENT, [
                    'sent_at' => current_time('mysql')
                ]);
                $this->logger->info("Queue ID {$queue_item->id}: Email sent successfully to {$queue_item->recipient_email}");
            } else {
                $failed_count++;
                $this->handle_failed_email($queue_item);
            }

            // Small delay to prevent overwhelming the mail server
            usleep(100000); // 0.1 second delay between emails
        }

        $total_processed = count($pending_emails);
        $this->logger->info("Queue processing completed. Processed: {$total_processed}, Sent: {$sent_count}, Failed: {$failed_count}");

        return [
            'processed' => $total_processed,
            'sent' => $sent_count,
            'failed' => $failed_count,
            'message' => "Processed {$total_processed} emails. Sent: {$sent_count}, Failed: {$failed_count}"
        ];
    }

    /**
     * Send a queued email
     *
     * @param object $queue_item
     * @return bool
     */
    private function send_queued_email($queue_item) {
        try {
            $headers = maybe_unserialize($queue_item->headers);
            if (!is_array($headers)) {
                $headers = [];
            }

            $tracking_data = [
                'email_id' => $queue_item->id, // Use queue ID for unique tracking per email
                'subscriber_id' => $queue_item->subscriber_id,
                'template' => $queue_item->template_type,
                'campaign_type' => $queue_item->campaign_type,
                'queue_id' => $queue_item->id,
            ];

            $result = $this->email_service->send(
                $queue_item->recipient_email,
                $queue_item->subject,
                $queue_item->message,
                $headers,
                $tracking_data
            );

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("Queue ID {$queue_item->id}: Exception sending email - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle failed email sending
     *
     * @param object $queue_item
     */
    private function handle_failed_email($queue_item) {
        $attempts = (int)$queue_item->attempts + 1;
        $max_attempts = (int)$queue_item->max_attempts;

        if ($attempts >= $max_attempts) {
            // Mark as permanently failed
            $this->update_status($queue_item->id, self::STATUS_FAILED, [
                'attempts' => $attempts,
                'error_message' => 'Max attempts reached'
            ]);
            $this->logger->error("Queue ID {$queue_item->id}: Max attempts ({$max_attempts}) reached. Marking as failed.");
        } else {
            // Retry later
            $retry_delay = $this->get_retry_delay($attempts);
            $next_attempt = date('Y-m-d H:i:s', strtotime("+{$retry_delay} minutes"));

            $this->update_status($queue_item->id, self::STATUS_PENDING, [
                'attempts' => $attempts,
                'scheduled_for' => $next_attempt,
                'error_message' => 'Retrying after failure'
            ]);
            $this->logger->warning("Queue ID {$queue_item->id}: Attempt {$attempts} failed. Scheduled for retry at {$next_attempt}");
        }
    }

    /**
     * Get retry delay in minutes based on attempt number (exponential backoff)
     *
     * @param int $attempts
     * @return int Minutes to delay
     */
    private function get_retry_delay($attempts) {
        // Exponential backoff: 5, 15, 30 minutes
        $delays = [5, 15, 30];
        $index = $attempts - 1;
        return isset($delays[$index]) ? $delays[$index] : 30;
    }

    /**
     * Get pending emails from queue
     *
     * @param int $limit
     * @return array
     */
    private function get_pending_emails($limit) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpbm_email_queue';

        // Get all pending emails that are ready to be sent
        // NOTE: Old pending emails are cleaned up before each Send Now in Plugin.php
        // so we don't need additional time-based filtering here
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name}
            WHERE status = %s
            AND scheduled_for <= %s
            ORDER BY priority DESC, created_at ASC
            LIMIT %d",
            self::STATUS_PENDING,
            current_time('mysql'),
            $limit
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Update queue item status
     *
     * @param int $queue_id
     * @param string $status
     * @param array $extra_data Optional additional data to update
     * @return bool
     */
    private function update_status($queue_id, $status, $extra_data = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpbm_email_queue';

        $data = array_merge(['status' => $status], $extra_data);

        return $wpdb->update(
            $table_name,
            $data,
            ['id' => $queue_id],
            null,
            ['%d']
        ) !== false;
    }

    /**
     * Get batch size based on license tier
     *
     * @return int
     */
    private function get_batch_size() {
        if (function_exists('wpbm_is_pro') && wpbm_is_pro()) {
            return self::RATE_LIMIT_PRO;
        }

        if (function_exists('wpbm_is_starter') && wpbm_is_starter()) {
            return self::RATE_LIMIT_STARTER;
        }

        return self::RATE_LIMIT_FREE;
    }

    /**
     * Get queue statistics
     *
     * @return array
     */
    public function get_queue_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpbm_email_queue';

        $stats = [
            'pending' => 0,
            'processing' => 0,
            'sent' => 0,
            'failed' => 0,
            'total' => 0,
        ];

        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count
            FROM {$table_name}
            GROUP BY status"
        );

        if ($results) {
            foreach ($results as $row) {
                $stats[$row->status] = (int)$row->count;
                $stats['total'] += (int)$row->count;
            }
        }

        return $stats;
    }

    /**
     * Clear old completed/failed emails from queue
     *
     * @param int $days Keep records for this many days
     * @return int Number of records deleted
     */
    public function cleanup_old_emails($days = 30) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpbm_email_queue';

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name}
                WHERE status IN (%s, %s)
                AND (sent_at < %s OR created_at < %s)",
                self::STATUS_SENT,
                self::STATUS_FAILED,
                $cutoff_date,
                $cutoff_date
            )
        );

        $this->logger->info("Cleaned up {$deleted} old emails from queue (older than {$days} days)");

        return $deleted;
    }

    /**
     * Cancel all pending emails for a campaign
     *
     * @param string $campaign_type
     * @return int Number of cancelled emails
     */
    public function cancel_campaign($campaign_type) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpbm_email_queue';

        $cancelled = $wpdb->update(
            $table_name,
            ['status' => self::STATUS_CANCELLED],
            [
                'campaign_type' => $campaign_type,
                'status' => self::STATUS_PENDING
            ]
        );

        $this->logger->info("Cancelled {$cancelled} pending emails for campaign: {$campaign_type}");

        return $cancelled;
    }
}
