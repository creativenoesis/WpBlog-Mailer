<?php
/**
 * Abstract Base Email Service
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Common\Services; // Correct namespace

// Correct paths for dependencies
use WPBlogMailer\Common\Utilities\Logger;
use WPBlogMailer\Common\Utilities\Validator;

/**
 * Abstract Email Service Class
 * Provides the core (DRY) email sending logic.
 */
abstract class BaseEmailService {
    
    /**
     * @var Logger
     */
    protected $logger;
    
    /**
     * @var Validator
     */
    protected $validator;
    
    /**
     * Constructor
     */
    public function __construct(Logger $logger, Validator $validator) {
        $this->logger = $logger;
        $this->validator = $validator;
    }
    
    /**
     * Public send method
     *
     * @param string $recipient
     * @param string $subject
     * @param string $message
     * @param array  $headers
     * @param array  $tracking_data Optional tracking data (subscriber_id, template, campaign_type, etc.)
     * @return bool
     */
    public function send($recipient, $subject, $message, $headers = [], $tracking_data = []) {
        if (!$this->validate_input($recipient, $subject, $message)) {
            $this->logger->error('Email validation failed.', new \Exception('Invalid input for recipient, subject, or message.'));

            // Log failed send
            $this->log_send(false, $recipient, '', $subject, $tracking_data, 'Email validation failed');

            return false;
        }

        $prepared_email = $this->prepare_email($recipient, $subject, $message, $headers);

        $prepared_email['message'] = $this->pre_send_tracking(
            $prepared_email['message'],
            $prepared_email['to'],
            $tracking_data
        );

        try {
            // Debug: Log email headers for troubleshooting
            error_log('WPBM Sending Email - To: ' . $prepared_email['to'] .
                      ' | Subject: ' . $prepared_email['subject'] .
                      ' | Headers: ' . print_r($prepared_email['headers'], true));

            // Capture wp_mail errors using WordPress hook
            $wp_mail_error = null;
            $capture_error = function($wp_error) use (&$wp_mail_error) {
                $wp_mail_error = $wp_error;
            };
            add_action('wp_mail_failed', $capture_error);

            // This is the core logic you will migrate from email-sender.php
            $result = wp_mail(
                $prepared_email['to'],
                $prepared_email['subject'],
                $prepared_email['message'],
                $prepared_email['headers']
            );

            // Remove the error capture hook
            remove_action('wp_mail_failed', $capture_error);

            // Log the send attempt
            $error_message = null;
            if (!$result || $wp_mail_error) {
                if ($wp_mail_error && is_wp_error($wp_mail_error)) {
                    $error_message = $wp_mail_error->get_error_message();
                    $this->logger->error('wp_mail() failed: ' . $error_message);
                    // Always log to error_log for production debugging
                    error_log('WPBM Email Failed: ' . $error_message . ' (To: ' . $prepared_email['to'] . ')');
                } else {
                    $error_message = 'wp_mail() returned false';
                    $this->logger->error('wp_mail() failed to send.');
                    error_log('WPBM Email Failed: wp_mail() returned false (To: ' . $prepared_email['to'] . ')');
                }
                $result = false; // Ensure result is false if there's an error
            }

            $this->log_send($result, $prepared_email['to'], '', $prepared_email['subject'], $tracking_data, $error_message);

            // Pass the $tracking_data to the post-send hook
            $this->track_send($result, $prepared_email, $tracking_data);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Email sending failed with exception.', $e);
            error_log('WPBM Email Exception: ' . $e->getMessage() . ' (To: ' . $prepared_email['to'] . ')');

            // Log exception
            $this->log_send(false, $prepared_email['to'], '', $prepared_email['subject'], $tracking_data, $e->getMessage());

            return false;
        }
    }

    /**
     * Log email send attempt
     *
     * @param bool $success Whether send was successful
     * @param string $recipient_email Recipient email address
     * @param string $recipient_name Recipient name
     * @param string $subject Email subject
     * @param array $tracking_data Additional tracking data
     * @param string|null $error_message Error message if failed
     */
    protected function log_send($success, $recipient_email, $recipient_name, $subject, $tracking_data = [], $error_message = null) {
        global $wpdb;

        // Get SendLogService from container
        try {
            $plugin = \WPBlogMailer\Core\Plugin::instance();
            $send_log_service = $plugin->container->get(SendLogService::class);

            $log_data = [
                'recipient_email' => $recipient_email,
                'recipient_name' => $recipient_name,
                'subscriber_id' => isset($tracking_data['subscriber_id']) ? $tracking_data['subscriber_id'] : null,
                'subject' => $subject,
                'template_type' => isset($tracking_data['template']) ? $tracking_data['template'] : 'basic',
                'campaign_type' => isset($tracking_data['campaign_type']) ? $tracking_data['campaign_type'] : 'newsletter',
                'status' => $success ? 'success' : 'failed',
                'error_message' => $error_message,
                'queue_id' => isset($tracking_data['queue_id']) ? $tracking_data['queue_id'] : null,
            ];

            $log_id = $send_log_service->log_send($log_data);

            // DEBUG: Verify logging worked
            if ($log_id && $success) {
                static $log_count = 0;
                $log_count++;
                if ($log_count % 100 == 0) {  // Log every 100th email to avoid spam
                    error_log("WPBM: Logged {$log_count} emails to analytics (last ID: {$log_id})");
                }
            }
        } catch (\Exception $e) {
            // Silently fail if logging fails - don't break email sending
            error_log('WPBM Send Log Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Validate email inputs.
     *
     * @return bool
     */
    protected function validate_input($recipient, $subject, $message) {
        return $this->validator->is_email($recipient) && 
               $this->validator->is_not_empty($subject) && 
               $this->validator->is_not_empty($message);
    }
    
    /**
     * Prepare email properties and headers.
     *
     * @return array
     */
    protected function prepare_email($recipient, $subject, $message, $headers = []) {
        // Get 'From' settings from WordPress options (stored in wpbm_settings array)
        $settings = get_option('wpbm_settings', []);
        $settings = wp_parse_args($settings, [
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
        ]);

        // Validate and sanitize from_email
        $from_email = $settings['from_email'];
        if (!$this->validator->is_email($from_email)) {
            $this->logger->error('Invalid from_email in settings: ' . $from_email . '. Using admin_email as fallback.');
            $from_email = get_option('admin_email');
        }

        // Sanitize from_name to prevent header injection
        $from_name = str_replace(["\r", "\n", "\0"], '', $settings['from_name']);
        $from_name = trim($from_name);

        // Check if headers already contain From or Content-Type to avoid duplicates
        $has_from = false;
        $has_content_type = false;

        foreach ($headers as $header) {
            if (stripos($header, 'From:') === 0) {
                $has_from = true;
            }
            if (stripos($header, 'Content-Type:') === 0) {
                $has_content_type = true;
            }
        }

        // Only add default headers if not already present
        $default_headers = [];
        if (!$has_content_type) {
            $default_headers[] = 'Content-Type: text/html; charset=UTF-8';
        }
        if (!$has_from) {
            $default_headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        }

        // Merge with provided headers (default headers first, so provided headers can override)
        $final_headers = array_merge($default_headers, $headers);

        return [
            'to'      => $recipient,
            'subject' => $subject,
            'message' => $message,
            'headers' => $final_headers,
        ];
    }
    
    /**
     * Tier-specific pre-send tracking hook.
     * Use this to modify email content before sending (e.g., add tracking).
     *
     * @param string $content The email HTML content
     * @param string $recipient_email The recipient's email
     * @param array  $tracking_data (Optional) e.g., ['subscriber_id' => 123, 'campaign_id' => 456]
     * @return string The modified email HTML content
     */
    abstract protected function pre_send_tracking($content, $recipient_email, $tracking_data); // <-- ADD THIS
    

    /**
     * Tier-specific post-send tracking logic.
     *
     * @param bool $result Send result
     * @param array $email_data The prepared email data
     * @param array $tracking_data (Optional)
     */
    abstract protected function track_send($result, $email_data, $tracking_data); // <-- ADD $tracking_data

    /**
     * Send confirmation email for double opt-in
     *
     * @param int $subscriber_id Subscriber ID
     * @param string $email Subscriber email address
     * @return bool Whether the email was sent successfully
     */
    public function send_confirmation_email($subscriber_id, $email) {
        // Get subscriber data
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpbm_subscribers';
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $subscriber_id
        ));

        if (!$subscriber) {
            $this->logger->error('Cannot send confirmation email: Subscriber not found', new \Exception('Subscriber ID ' . $subscriber_id . ' not found'));
            return false;
        }

        // Generate confirmation URL
        $confirmation_url = add_query_arg([
            'wpbm_action' => 'confirm',
            'key' => $subscriber->unsubscribe_key,
            'email' => urlencode($subscriber->email)
        ], home_url());

        // Get site info
        $site_name = get_bloginfo('name');
        $site_url = home_url();

        // Build email subject
        $subject = sprintf(__('[%s] Please confirm your subscription', 'wpblogmailer'), $site_name);

        // Build email message
        $message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $message .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
        $message .= '<h2 style="color: #2271b1;">Confirm Your Subscription</h2>';
        $message .= '<p>Hello' . (!empty($subscriber->first_name) ? ' ' . esc_html($subscriber->first_name) : '') . ',</p>';
        $message .= '<p>Thank you for subscribing to ' . esc_html($site_name) . '! Please confirm your email address by clicking the button below:</p>';
        $message .= '<p style="text-align: center; margin: 30px 0;">';
        $message .= '<a href="' . esc_url($confirmation_url) . '" style="background-color: #2271b1; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Confirm Subscription</a>';
        $message .= '</p>';
        $message .= '<p>Or copy and paste this link into your browser:</p>';
        $message .= '<p style="word-break: break-all; color: #666; font-size: 12px;">' . esc_url($confirmation_url) . '</p>';
        $message .= '<hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">';
        $message .= '<p style="color: #999; font-size: 12px;">If you did not subscribe to ' . esc_html($site_name) . ', please ignore this email.</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Send the email
        return $this->send(
            $email,
            $subject,
            $message,
            [],
            ['subscriber_id' => $subscriber_id, 'campaign_type' => 'confirmation']
        );
    }
}