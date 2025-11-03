<?php
/**
 * Frontend Subscribe Form
 * * Handles frontend subscription form display and processing
 * * @package WP_Blog_Mailer
 * @subpackage Free
 * @since 2.0.0
 */

namespace WPBlogMailer\Free;

// --- START FIX: Import dependencies to be injected ---
use WPBlogMailer\Common\Services\SubscriberService;
use WPBlogMailer\Common\Services\BaseEmailService; // Added this line
// --- END FIX ---

if (!defined('ABSPATH')) exit;

class SubscribeForm {

    /**
     * Subscriber service instance
     *
     * @var SubscriberService
     */
    private $service;

    /**
     * Email service instance
     *
     * @var BaseEmailService
     */
    private $email_service;

    /**
     * Form messages
     *
     * @var array
     */
    private $messages = array();

    /**
     * Constructor
     *
     * --- START FIX: Accept dependencies from Service Container ---
     * @param SubscriberService $subscriber_service
     * @param BaseEmailService $email_service // Added this parameter
     */
    public function __construct(SubscriberService $subscriber_service, BaseEmailService $email_service) {
        // Use the injected service
        $this->service = $subscriber_service;
        $this->email_service = $email_service;
        // --- END FIX ---
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Note: Shortcode is registered in Plugin.php via register_shortcodes()

        // Handle form submission
        add_action('init', array($this, 'handle_submission'));

        // Handle email confirmation
        add_action('init', array($this, 'handle_confirmation'));

        // Enqueue styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        // Define WPBM_PLUGIN_URL and WPBM_VERSION if not already defined globally
        if (!defined('WPBM_PLUGIN_URL')) {
            define('WPBM_PLUGIN_URL', plugin_dir_url(dirname(dirname(__FILE__)))); // Adjust path if needed
        }
        if (!defined('WPBM_VERSION')) {
             // Get version from composer.json or define statically
             // This is a placeholder, adjust as needed
            $composer_path = plugin_dir_path(dirname(dirname(__FILE__))) . 'composer.json';
            if (file_exists($composer_path)) {
                $composer_data = json_decode(file_get_contents($composer_path), true);
                define('WPBM_VERSION', $composer_data['version'] ?? '2.0.0');
            } else {
                 define('WPBM_VERSION', '2.0.0'); // Fallback version
            }
        }

        if (!is_admin()) {
            wp_enqueue_style(
                'wpbm-subscribe-form',
                WPBM_PLUGIN_URL . 'assets/css/subscribe-form.css',
                array(),
                WPBM_VERSION
            );

            wp_enqueue_script(
                'wpbm-subscribe-form',
                WPBM_PLUGIN_URL . 'assets/js/subscribe-form.js',
                array('jquery'),
                WPBM_VERSION,
                true
            );

            wp_localize_script('wpbm-subscribe-form', 'wpbmForm', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpbm_subscribe'),
                'strings' => array(
                    'processing' => __('Processing...', 'wpblogmailer'),
                    'error' => __('An error occurred. Please try again.', 'wpblogmailer')
                )
            ));
        }
    }

    /**
     * Render subscribe form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Form HTML
     */
    public function render_form($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'title' => __('Subscribe to Our Newsletter', 'wpblogmailer'),
            'description' => __('Get the latest updates delivered to your inbox.', 'wpblogmailer'),
            'button_text' => __('Subscribe', 'wpblogmailer'),
            'show_name' => 'yes',
            'success_message' => __('Thank you for subscribing!', 'wpblogmailer'),
            'class' => ''
        ), $atts);

        // Build form HTML
        ob_start();
        ?>
        <div class="wpbm-subscribe-form-wrapper <?php echo esc_attr($atts['class']); ?>">

            <?php if (!empty($this->messages)): ?>
                <?php foreach ($this->messages as $type => $message): ?>
                    <div class="wpbm-message wpbm-message-<?php echo esc_attr($type); ?>">
                        <?php echo esc_html($message); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!isset($this->messages['success'])): ?>

                <?php if (!empty($atts['title'])): ?>
                    <h3 class="wpbm-form-title"><?php echo esc_html($atts['title']); ?></h3>
                <?php endif; ?>

                <?php if (!empty($atts['description'])): ?>
                    <p class="wpbm-form-description"><?php echo esc_html($atts['description']); ?></p>
                <?php endif; ?>

                <form method="post" action="" class="wpbm-subscribe-form" id="wpbm-subscribe-form">
                    <?php wp_nonce_field('wpbm_subscribe_action', 'wpbm_subscribe_nonce'); ?>
                    <input type="hidden" name="wpbm_subscribe" value="1">

                    <div class="wpbm-form-fields">
                        <?php if ($atts['show_name'] === 'yes'): ?>
                            <div class="wpbm-form-field">
                                <label for="wpbm-first-name" class="wpbm-label">
                                    <?php _e('First Name', 'wpblogmailer'); ?>
                                </label>
                                <input type="text"
                                       id="wpbm-first-name"
                                       name="wpbm_first_name"
                                       class="wpbm-input"
                                       placeholder="<?php esc_attr_e('Your first name', 'wpblogmailer'); ?>">
                            </div>
                            <div class="wpbm-form-field">
                                <label for="wpbm-last-name" class="wpbm-label">
                                    <?php _e('Last Name', 'wpblogmailer'); ?>
                                </label>
                                <input type="text"
                                       id="wpbm-last-name"
                                       name="wpbm_last_name"
                                       class="wpbm-input"
                                       placeholder="<?php esc_attr_e('Your last name', 'wpblogmailer'); ?>">
                            </div>
                         <?php else: ?>
                             <input type="hidden" name="wpbm_first_name" value="">
                             <input type="hidden" name="wpbm_last_name" value="">
                        <?php endif; ?>

                        <div class="wpbm-form-field">
                            <label for="wpbm-email" class="wpbm-label">
                                <?php _e('Email', 'wpblogmailer'); ?>
                                <span class="required">*</span>
                            </label>
                            <input type="email"
                                   id="wpbm-email"
                                   name="wpbm_email"
                                   class="wpbm-input"
                                   placeholder="<?php esc_attr_e('your@email.com', 'wpblogmailer'); ?>"
                                   required>
                        </div>

                        <div class="wpbm-form-field wpbm-form-submit">
                            <button type="submit" class="wpbm-button">
                                <?php echo esc_html($atts['button_text']); ?>
                            </button>
                        </div>
                    </div>

                    <p class="wpbm-form-note">
                        <?php _e('We respect your privacy. Unsubscribe at any time.', 'wpblogmailer'); ?>
                    </p>
                </form>

            <?php endif; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Handle form submission
     */
    public function handle_submission() {
        // Check if form was submitted
        if (!isset($_POST['wpbm_subscribe']) || $_POST['wpbm_subscribe'] !== '1') {
            return;
        }

        // Verify nonce
        if (!isset($_POST['wpbm_subscribe_nonce']) ||
            !wp_verify_nonce($_POST['wpbm_subscribe_nonce'], 'wpbm_subscribe_action')) {
            $this->messages['error'] = __('Security check failed. Please try again.', 'wpblogmailer');
            return;
        }

        // Get form data
        $first_name = isset($_POST['wpbm_first_name']) ? sanitize_text_field($_POST['wpbm_first_name']) : '';
        $last_name = isset($_POST['wpbm_last_name']) ? sanitize_text_field($_POST['wpbm_last_name']) : '';
        $email = isset($_POST['wpbm_email']) ? sanitize_email($_POST['wpbm_email']) : '';

        // Validate
        if (empty($email) || !is_email($email)) {
            $this->messages['error'] = __('Please enter a valid email address.', 'wpblogmailer');
            return;
        }

        // Set default first name if empty
        if (empty($first_name) && empty($last_name)) {
             $name_part = substr($email, 0, strpos($email, '@'));
             $first_name = ucfirst(preg_replace('/[^a-zA-Z]/', '', $name_part));
        }

        // Check if email already exists
        if ($this->service->email_exists($email)) {
             $this->messages['error'] = __('This email address is already subscribed.', 'wpblogmailer');
             return;
        }

        // Prepare data for the 'create' method
        $data = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'status' => 'pending' // Default status
        );

        // Add subscriber using the 'create' method
        try {
            $result_id = $this->service->create($data);

            if ($result_id) {
                // Determine if double opt-in is active
                $double_optin_enabled = function_exists('wpbm_is_double_optin_enabled') ? wpbm_is_double_optin_enabled() : false;

                if ($double_optin_enabled) {
                    $this->messages['success'] = __('Thank you! Please check your email to confirm your subscription.', 'wpblogmailer');
                    // Send confirmation email
                    if ($this->email_service) {
                        try {
                            $this->email_service->send_confirmation_email($result_id, $email);
                        } catch (\Exception $e) {
                            error_log('WP Blog Mailer: Failed to send confirmation email - ' . $e->getMessage());
                        }
                    }
                } else {
                    // Confirm immediately
                    $this->service->confirm($result_id);
                    $this->messages['success'] = __('Thank you for subscribing!', 'wpblogmailer');
                }

                // Fire action hook
                $full_name = trim($first_name . ' ' . $last_name);
                do_action('wpbm_subscriber_added_frontend', $result_id, $email, $full_name);
            } else {
                $this->messages['error'] = __('An error occurred while subscribing. Please try again.', 'wpblogmailer');
            }
        } catch (\Exception $e) {
            // Handle subscriber limit or other exceptions
            $this->messages['error'] = $e->getMessage();
        }
    }

    /**
     * Handle email confirmation from confirmation link
     */
    public function handle_confirmation() {
        // Check if this is a confirmation request
        if (!isset($_GET['wpbm_action']) || $_GET['wpbm_action'] !== 'confirm') {
            return;
        }

        // Get confirmation parameters
        $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
        $email = isset($_GET['email']) ? sanitize_email(urldecode($_GET['email'])) : '';

        if (empty($key) || empty($email)) {
            wp_die(__('Invalid confirmation link.', 'wpblogmailer'));
        }

        // Find subscriber by email and key
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpbm_subscribers';
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE email = %s AND unsubscribe_key = %s",
            $email,
            $key
        ));

        if (!$subscriber) {
            wp_die(__('Invalid confirmation link or subscriber not found.', 'wpblogmailer'));
        }

        // Check if already confirmed
        if ($subscriber->status === 'confirmed') {
            wp_die('<h1>' . __('Already Confirmed', 'wpblogmailer') . '</h1><p>' .
                __('Your email address has already been confirmed. Thank you for subscribing!', 'wpblogmailer') . '</p>' .
                '<p><a href="' . esc_url(home_url()) . '">' . __('Go to Homepage', 'wpblogmailer') . '</a></p>');
        }

        // Confirm the subscriber
        $confirmed = $this->service->confirm($subscriber->id);

        if ($confirmed) {
            // Fire action hook
            do_action('wpbm_subscriber_confirmed', $subscriber->id, $subscriber->email);

            // Show success message
            wp_die('<h1>' . __('Subscription Confirmed!', 'wpblogmailer') . '</h1><p>' .
                __('Thank you for confirming your email address. You will now receive our newsletters.', 'wpblogmailer') . '</p>' .
                '<p><a href="' . esc_url(home_url()) . '">' . __('Go to Homepage', 'wpblogmailer') . '</a></p>');
        } else {
            wp_die(__('An error occurred while confirming your subscription. Please try again later.', 'wpblogmailer'));
        }
    }
}

// --- FIX: Remove direct initialization. The Service Container handles this. ---
// new SubscribeForm();