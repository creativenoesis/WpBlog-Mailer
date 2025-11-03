<?php
/**
 * Main Plugin Class (Free Version)
 *
 * @package WPBlogMailer
 * @since 1.0.0
 */

namespace WPBlogMailer\Core;

// Import Controllers
use WPBlogMailer\Free\Controllers\SubscribersController;
use WPBlogMailer\Free\SubscribeForm;

// Import Services
use WPBlogMailer\Free\Services\EmailServiceFree;
use WPBlogMailer\Common\Services\SubscriberService;
use WPBlogMailer\Common\Services\CronService;
use WPBlogMailer\Common\Services\TemplateService;
use WPBlogMailer\Common\Services\NewsletterService;

/**
 * Plugin Class (Free Version)
 * The core plugin class for the free version
 */
class Plugin {

    /**
     * Plugin version
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * Plugin instance (Singleton)
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Plugin base directory
     * @var string
     */
    private $plugin_path;

    /**
     * Plugin base URL
     * @var string
     */
    private $plugin_url;

    /**
     * The Dependency Injection container.
     * @var ServiceContainer
     */
    public $container;

    // --- SERVICES ---
    /** @var EmailServiceFree */
    private $email_service;

    /** @var TemplateService */
    private $template_service;

    /** @var NewsletterService */
    private $newsletter_service;

    /** @var SubscriberService */
    private $subscriber_service;

    // --- CONTROLLERS ---
    /** @var SubscribersController */
    private $subscribers_controller;

    /** @var SubscribeForm */
    private $form_controller;

    /**
     * Get plugin instance (Singleton pattern)
     *
     * @return Plugin
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor (private for Singleton)
     */
    private function __construct() {
        // Use constants defined in wp-blog-mailer.php
        $this->plugin_path = defined('WPBM_PLUGIN_PATH') ? WPBM_PLUGIN_PATH : plugin_dir_path(dirname(dirname(__FILE__)));
        $this->plugin_url = defined('WPBM_PLUGIN_URL') ? WPBM_PLUGIN_URL : plugin_dir_url(dirname(dirname(__FILE__)));

        // Create the container
        $this->container = new ServiceContainer();

        // Initialize everything
        $this->init();
    }

    /**
     * Initialize the plugin
     */
    private function init() {
        // Initialize cron service (registers custom intervals)
        CronService::init();

        // Load services and controllers from the container
        $this->init_services();
        $this->init_controllers();

        // Register hooks
        $this->register_hooks();
    }

    /**
     * Initialize all services using the container.
     */
    private function init_services() {
        $this->email_service = $this->container->get(EmailServiceFree::class);
        $this->subscriber_service = $this->container->get(SubscriberService::class);
        $this->template_service = $this->container->get(TemplateService::class);
        $this->newsletter_service = $this->container->get(NewsletterService::class);
    }

    /**
     * Initialize all controllers using the container.
     */
    private function init_controllers() {
        $this->subscribers_controller = $this->container->get(SubscribersController::class);
        $this->form_controller = $this->container->get(SubscribeForm::class);
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Initialize admin area
        if (is_admin()) {
            add_action('admin_menu', array($this, 'register_admin_menu'), 5);
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_action('admin_init', array($this, 'maybe_migrate_subscriber_keys'));
            add_action('admin_init', array($this, 'check_database_updates'));
        }

        // Register shortcodes
        add_action('init', array($this, 'register_shortcodes'));

        // Admin AJAX handlers & Hooks (delegated by SubscribersController)
        if ($this->subscribers_controller) {
             $this->subscribers_controller->init_hooks();
        }

        // Send Newsletter Now handler
        add_action('admin_post_wpbm_send_newsletter_now', array($this, 'handle_send_newsletter_now'));

        // Newsletter sending hook (triggered by cron or manual send)
        add_action('wpbm_send_newsletter', array($this, 'handle_newsletter_send'));

        // Template preview AJAX handler
        add_action('wp_ajax_wpbm_preview_template', array($this, 'handle_template_preview'));
    }

    /**
     * Register admin menu items (Free Version Only)
     */
    public function register_admin_menu() {
        // Main menu
        add_menu_page(
            __('WP Blog Mailer', 'wpblogmailer'),
            __('Blog Mailer', 'wpblogmailer'),
            'manage_options',
            'wpbm-newsletter',
            array($this, 'render_dashboard'),
            'dashicons-email-alt',
            30
        );

        // Dashboard
        add_submenu_page(
            'wpbm-newsletter',
            __('Dashboard', 'wpblogmailer'),
            __('Dashboard', 'wpblogmailer'),
            'manage_options',
            'wpbm-newsletter',
            array($this, 'render_dashboard')
        );

        // Subscribers
        add_submenu_page(
            'wpbm-newsletter',
            __('Subscribers', 'wpblogmailer'),
            __('Subscribers', 'wpblogmailer'),
            'manage_options',
            'wpbm-subscribers',
            array($this->subscribers_controller, 'render_page')
        );

        // Send Log
        add_submenu_page(
            'wpbm-newsletter',
            __('Send Log', 'wpblogmailer'),
            __('Send Log', 'wpblogmailer'),
            'manage_options',
            'wpbm-send-log',
            array($this, 'render_send_log_page')
        );

        // Cron Status
        add_submenu_page(
            'wpbm-newsletter',
            __('Cron Status', 'wpblogmailer'),
            __('Cron Status', 'wpblogmailer'),
            'manage_options',
            'wpbm-cron-status',
            array($this, 'render_cron_status_page')
        );

        // Settings
        add_submenu_page(
            'wpbm-newsletter',
            __('Settings', 'wpblogmailer'),
            __('Settings', 'wpblogmailer'),
            'manage_options',
            'wpbm-settings',
            array($this, 'render_settings')
        );

        // Upgrade to Pro page
        add_submenu_page(
            'wpbm-newsletter',
            __('Upgrade to Pro', 'wpblogmailer'),
            '<span style="color:#f18500">★ ' . __('Upgrade to Pro', 'wpblogmailer') . '</span>',
            'manage_options',
            'wpbm-upgrade',
            array($this, 'render_upgrade_page')
        );
    }

    // --- RENDER PAGE METHODS ---

    public function render_dashboard() {
        $view_file = $this->plugin_path . 'includes/Free/Views/dashboard.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Dashboard view file not found.</p></div>';
        }
    }

    public function render_settings() {
        $view_file = $this->plugin_path . 'includes/Free/Views/settings.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Settings view file not found.</p></div>';
        }
    }

    public function render_send_log_page() {
        $view_file = $this->plugin_path . 'includes/Free/Views/send-log.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Send Log view file not found.</p></div>';
        }
    }

    public function render_cron_status_page() {
        $view_file = $this->plugin_path . 'includes/Free/Views/cron-status.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Cron Status view file not found.</p></div>';
        }
    }

    public function render_upgrade_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Upgrade to WP Blog Mailer Pro', 'wpblogmailer'); ?></h1>

            <div style="background: #fff; padding: 30px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2><?php esc_html_e('Unlock Powerful Features', 'wpblogmailer'); ?></h2>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 30px 0;">
                    <div style="padding: 20px; border-left: 4px solid #2271b1;">
                        <h3>📊 <?php esc_html_e('Advanced Analytics', 'wpblogmailer'); ?></h3>
                        <p><?php esc_html_e('Track opens, clicks, and subscriber engagement in detail.', 'wpblogmailer'); ?></p>
                    </div>

                    <div style="padding: 20px; border-left: 4px solid #2271b1;">
                        <h3>🎨 <?php esc_html_e('Custom Templates', 'wpblogmailer'); ?></h3>
                        <p><?php esc_html_e('Create beautiful custom email templates with drag-and-drop builder.', 'wpblogmailer'); ?></p>
                    </div>

                    <div style="padding: 20px; border-left: 4px solid #2271b1;">
                        <h3>🏷️ <?php esc_html_e('Subscriber Tags', 'wpblogmailer'); ?></h3>
                        <p><?php esc_html_e('Organize subscribers with tags and send targeted campaigns.', 'wpblogmailer'); ?></p>
                    </div>

                    <div style="padding: 20px; border-left: 4px solid #2271b1;">
                        <h3>📥 <?php esc_html_e('Import/Export', 'wpblogmailer'); ?></h3>
                        <p><?php esc_html_e('Easily import subscribers from CSV or export your list.', 'wpblogmailer'); ?></p>
                    </div>

                    <div style="padding: 20px; border-left: 4px solid #2271b1;">
                        <h3>⚡ <?php esc_html_e('Email Queue', 'wpblogmailer'); ?></h3>
                        <p><?php esc_html_e('Send large campaigns reliably with intelligent queue management.', 'wpblogmailer'); ?></p>
                    </div>

                    <div style="padding: 20px; border-left: 4px solid #2271b1;">
                        <h3>🎯 <?php esc_html_e('Priority Support', 'wpblogmailer'); ?></h3>
                        <p><?php esc_html_e('Get help fast from our dedicated support team.', 'wpblogmailer'); ?></p>
                    </div>
                </div>

                <div style="text-align: center; margin: 40px 0 20px;">
                    <a href="https://creativenoesis.com/wp-blog-mailer/" target="_blank" class="button button-primary button-hero">
                        <?php esc_html_e('Upgrade to Pro Now →', 'wpblogmailer'); ?>
                    </a>
                </div>

                <p style="text-align: center; color: #666;">
                    <?php esc_html_e('30-day money-back guarantee. Cancel anytime.', 'wpblogmailer'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Handle Send Newsletter Now action
     */
    public function handle_send_newsletter_now() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wpblogmailer'));
        }

        // Verify nonce
        if (!isset($_POST['wpbm_send_now_nonce']) || !wp_verify_nonce($_POST['wpbm_send_now_nonce'], 'wpbm_send_newsletter_now')) {
            wp_die(__('Security check failed.', 'wpblogmailer'));
        }

        // Check if this is a test email request
        if (isset($_POST['send_test']) && $_POST['send_test'] == '1') {
            $test_email = isset($_POST['test_email_address']) ? sanitize_email($_POST['test_email_address']) : '';

            if (empty($test_email) || !is_email($test_email)) {
                wp_redirect(add_query_arg([
                    'page' => 'wpbm-newsletter',
                    'test_email_error' => '1'
                ], admin_url('admin.php')));
                exit;
            }

            // Send test email
            if ($this->newsletter_service && $this->email_service) {
                $settings = get_option('wpbm_settings', []);
                $subject = isset($settings['subject_line']) ? $settings['subject_line'] : 'Test Newsletter';
                $subject = str_replace('{site_name}', get_bloginfo('name'), $subject);
                $subject = str_replace('{date}', date('F j, Y'), $subject);

                $template_service = $this->container->get(\WPBlogMailer\Free\Services\BasicTemplateService::class);

                $posts = get_posts([
                    'numberposts' => isset($settings['posts_per_email']) ? intval($settings['posts_per_email']) : 5,
                    'post_status' => 'publish',
                    'post_type' => isset($settings['post_types']) ? $settings['post_types'] : ['post'],
                    'orderby' => 'date',
                    'order' => 'DESC',
                ]);

                $test_subscriber = (object) [
                    'id' => 0,
                    'email' => $test_email,
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'unsubscribe_key' => 'test_key_' . time(),
                ];

                $template_data = [
                    'posts' => $posts,
                    'heading' => $subject,
                    'subscriber' => $test_subscriber,
                ];

                $html_content = $template_service->render($template_data);

                $headers = [
                    'Content-Type: text/html; charset=UTF-8',
                    'From: ' . (isset($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name')) . ' <' . (isset($settings['from_email']) ? $settings['from_email'] : get_option('admin_email')) . '>',
                ];

                $sent = $this->email_service->send(
                    $test_email,
                    $subject,
                    $html_content,
                    $headers,
                    ['campaign_type' => 'test']
                );

                if ($sent) {
                    wp_redirect(add_query_arg([
                        'page' => 'wpbm-newsletter',
                        'test_email_sent' => '1',
                        'test_email' => urlencode($test_email)
                    ], admin_url('admin.php')));
                    exit;
                } else {
                    wp_redirect(add_query_arg([
                        'page' => 'wpbm-newsletter',
                        'test_email_failed' => '1'
                    ], admin_url('admin.php')));
                    exit;
                }
            }
        }

        // Send newsletter manually
        if ($this->newsletter_service) {
            set_time_limit(600);
            $result = $this->newsletter_service->send_newsletter(true);

            if (isset($result['success']) && $result['success'] > 0) {
                update_option('wpbm_last_newsletter_send', current_time('timestamp'));

                wp_redirect(add_query_arg([
                    'page' => 'wpbm-newsletter',
                    'newsletter_sent' => '1',
                    'sent_count' => $result['success'],
                    'failed_count' => $result['failed']
                ], admin_url('admin.php')));
                exit;
            } else {
                $error_message = isset($result['message']) ? urlencode($result['message']) : urlencode('No emails were sent');
                wp_redirect(add_query_arg([
                    'page' => 'wpbm-newsletter',
                    'newsletter_error' => '1',
                    'error_message' => $error_message
                ], admin_url('admin.php')));
                exit;
            }
        }

        wp_redirect(add_query_arg([
            'page' => 'wpbm-newsletter',
            'newsletter_error' => '1',
            'error_message' => urlencode('Newsletter service not available')
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle newsletter sending (called by cron)
     */
    public function handle_newsletter_send() {
        $cron_status_service = new \WPBlogMailer\Common\Services\CronStatusService();

        $log_id = $cron_status_service->log_execution('wpbm_send_newsletter', 'started', 'Newsletter sending started');

        if (!$this->newsletter_service) {
            $error_msg = 'NewsletterService not initialized';
            error_log('WPBM Error: ' . $error_msg);
            $cron_status_service->log_execution('wpbm_send_newsletter', 'failed', $error_msg);
            return;
        }

        try {
            $result = $this->newsletter_service->send_newsletter(false);

            if (isset($result['success']) && $result['success'] > 0) {
                update_option('wpbm_last_newsletter_send', current_time('timestamp'));
            }

            $message = isset($result['message']) ? $result['message'] : 'Newsletter sent';
            if (isset($result['message'])) {
                error_log('WPBM Newsletter: ' . $result['message']);
            }

            $status = 'success';
            if (isset($result['error']) && $result['error'] > 0) {
                $status = isset($result['success']) && $result['success'] > 0 ? 'success' : 'failed';
            }

            $cron_status_service->log_execution('wpbm_send_newsletter', $status, $message, $result);
        } catch (\Exception $e) {
            $error_msg = 'Exception during newsletter send: ' . $e->getMessage();
            error_log('WPBM Error: ' . $error_msg);
            $cron_status_service->log_execution('wpbm_send_newsletter', 'failed', $error_msg);
        }
    }

    /**
     * Handle template preview AJAX request
     */
    public function handle_template_preview() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpbm_preview_template')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        // Preview logic here - simplified for free version
        wp_send_json_success(['html' => '<p>Preview functionality</p>']);
    }

    // --- ASSET ENQUEUEING ---

    public function enqueue_admin_assets( $hook ) {
        $screen = get_current_screen();
        if (!$screen) return;

        $is_plugin_page = ( strpos( $screen->id, 'wpbm-' ) !== false );
        if (!$is_plugin_page) return;

        // Common Assets
        wp_enqueue_style( 'wpbm-admin-common', $this->plugin_url . 'assets/css/admin/common.css', [], self::VERSION );
        wp_enqueue_script( 'wpbm-admin-common', $this->plugin_url . 'assets/js/admin/common.js', [ 'jquery' ], self::VERSION, true );

        // Settings Page - Enqueue color picker
        if ( strpos( $hook, 'wpbm-settings' ) !== false ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
        }
    }

    // --- SHORTCODES ---

    public function register_shortcodes() {
        if ($this->form_controller) {
            add_shortcode('wpbm_subscribe_form', array($this->form_controller, 'render_form'));
        }
    }

    // --- GETTERS & HELPERS ---

    public function get_version() { return self::VERSION; }
    public function get_plugin_path() { return $this->plugin_path; }
    public function get_plugin_url() { return $this->plugin_url; }

    public function get_email_service() { return $this->email_service; }
    public function get_template_service() { return $this->template_service; }
    public function get_subscriber_service() { return $this->subscriber_service; }

    /**
     * One-time migration to generate keys for existing subscribers
     */
    public function maybe_migrate_subscriber_keys() {
        $migration_done = get_option('wpbm_subscriber_keys_migrated', false);

        if ($migration_done) {
            return;
        }

        if ($this->subscriber_service) {
            $updated = $this->subscriber_service->generate_missing_keys();
            update_option('wpbm_subscriber_keys_migrated', true);

            if ($updated > 0) {
                error_log("WPBM: Migrated {$updated} subscribers with missing unsubscribe keys");
            }
        }
    }

    /**
     * Check for database schema updates
     */
    public function check_database_updates() {
        if (!class_exists('\WPBlogMailer\Common\Database\Schema')) {
            return;
        }

        $schema = new \WPBlogMailer\Common\Database\Schema();
        $schema->check_updates();
    }
}
