<?php
/**
 * Newsletter Service
 * Handles automated newsletter sending
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Common\Services;

use WPBlogMailer\Common\Utilities\Logger;
use WPBlogMailer\Free\Services\BasicTemplateService;
use WPBlogMailer\Pro\Services\CustomTemplateService;
use WPBlogMailer\Pro\Services\TemplateLibraryService;

/**
 * NewsletterService Class
 *
 * Manages the newsletter sending process:
 * - Retrieves recent posts
 * - Gets confirmed subscribers
 * - Renders the selected template
 * - Sends emails to all subscribers
 */
class NewsletterService {

    /**
     * @var SubscriberService
     */
    private $subscriber_service;

    /**
     * @var BaseEmailService
     */
    private $email_service;

    /**
     * @var BasicTemplateService
     */
    private $basic_template_service;

    /**
     * @var CustomTemplateService|null
     */
    private $custom_template_service;

    /**
     * @var TemplateLibraryService|null
     */
    private $template_library_service;

    /**
     * @var \WPBlogMailer\Common\Utilities\Logger
     */
    private $logger;

    /**
     * @var EmailQueueService
     */
    private $queue_service;

    /**
     * Constructor
     *
     * @param SubscriberService $subscriber_service
     * @param BaseEmailService $email_service
     * @param BasicTemplateService $basic_template_service
     * @param \WPBlogMailer\Common\Utilities\Logger $logger
     * @param CustomTemplateService|null $custom_template_service
     * @param TemplateLibraryService|null $template_library_service
     * @param EmailQueueService $queue_service
     */
    public function __construct(
        SubscriberService $subscriber_service,
        BaseEmailService $email_service,
        BasicTemplateService $basic_template_service,
        \WPBlogMailer\Common\Utilities\Logger $logger,
        $custom_template_service = null,
        $template_library_service = null,
        EmailQueueService $queue_service = null
    ) {
        $this->subscriber_service = $subscriber_service;
        $this->email_service = $email_service;
        $this->basic_template_service = $basic_template_service;
        $this->logger = $logger;
        $this->custom_template_service = $custom_template_service;
        $this->template_library_service = $template_library_service;
        $this->queue_service = $queue_service;
    }

    /**
     * Send the newsletter to all confirmed subscribers
     *
     * @param bool $manual_send Whether this is a manual send (ignores date check)
     * @return array Results array with 'success', 'failed', 'total' counts
     */
    public function send_newsletter($manual_send = false) {
        $this->logger->info('Newsletter sending initiated' . ($manual_send ? ' (manual)' : ' (automated)'));

        // Get plugin settings
        $settings = get_option('wpbm_settings', []);
        $settings = wp_parse_args($settings, [
            'posts_per_email' => 5,
            'post_types' => ['post'],
            'subject_line' => '[{site_name}] New Posts: {date}',
            'from_name' => get_bloginfo('name'),
            'from_email' => get_bloginfo('admin_email'),
        ]);

        // Get recent posts (manual send ignores date check)
        $posts = $this->get_recent_posts($settings, $manual_send);

        // DEBUG: Log fetched posts
        if (!empty($posts)) {
            $post_ids = array_map(function($p) { return $p->ID . ':' . $p->post_title; }, $posts);
            error_log('WPBM: Fetched ' . count($posts) . ' posts: ' . implode(', ', $post_ids));
        }

        if (empty($posts)) {
            $this->logger->info('No recent posts found. Newsletter not sent.');
            return [
                'success' => 0,
                'failed' => 0,
                'total' => 0,
                'message' => 'No recent posts to include in newsletter'
            ];
        }

        // Get confirmed subscribers
        $subscribers = $this->subscriber_service->get_confirmed();

        if (empty($subscribers)) {
            $this->logger->info('No confirmed subscribers found. Newsletter not sent.');
            return [
                'success' => 0,
                'failed' => 0,
                'total' => 0,
                'message' => 'No confirmed subscribers'
            ];
        }

        // Prepare email subject
        $subject = $this->prepare_subject_line($settings['subject_line'], count($posts));

        // Get template type
        $template_type = get_option('wpbm_template_type', 'basic');

        // Add emails to queue or send directly
        $queued_count = 0;
        $failed_count = 0;

        // Prepare headers (used for all emails)
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
        ];

        foreach ($subscribers as $subscriber) {
            try {
                // Render the template
                $html_content = $this->render_newsletter_template($template_type, $posts, $subject, $subscriber);

                // Use queue if available (Starter+ feature), otherwise send directly
                if ($this->queue_service) {
                    $queue_id = $this->queue_service->add_to_queue(
                        $subscriber->email,
                        $subject,
                        $html_content,
                        [
                            'headers' => $headers,
                            'template_type' => $template_type,
                            'campaign_type' => EmailQueueService::CAMPAIGN_NEWSLETTER,
                            'priority' => 5,
                            'subscriber_id' => $subscriber->id,
                            'scheduled_for' => current_time('mysql'),
                        ]
                    );

                    if ($queue_id) {
                        $queued_count++;
                    } else {
                        $failed_count++;
                        $this->logger->error('Failed to queue email for: ' . $subscriber->email);
                    }
                } else {
                    // Fallback to direct sending (Free tier or if queue service not available)
                    $sent = $this->email_service->send(
                        $subscriber->email,
                        $subject,
                        $html_content,
                        $headers,
                        [
                            'subscriber_id' => $subscriber->id,
                            'template' => $template_type,
                            'campaign_type' => 'newsletter',
                        ]
                    );

                    if ($sent) {
                        $queued_count++;
                    } else {
                        $failed_count++;
                        $this->logger->error('Failed to send newsletter to: ' . $subscriber->email);
                    }
                }

            } catch (\Exception $e) {
                $failed_count++;
                $this->logger->error('Exception processing newsletter for ' . $subscriber->email . ': ' . $e->getMessage());
            }
        }

        $total_count = count($subscribers);

        if ($this->queue_service) {
            $this->logger->info("Newsletter queued. Queued: {$queued_count}, Failed: {$failed_count}, Total: {$total_count}");
            return [
                'success' => $queued_count,
                'failed' => $failed_count,
                'total' => $total_count,
                'message' => "Newsletter queued successfully for {$queued_count} of {$total_count} subscribers. Emails will be sent in the background."
            ];
        } else {
            $this->logger->info("Newsletter sending completed. Sent: {$queued_count}, Failed: {$failed_count}, Total: {$total_count}");
            return [
                'success' => $queued_count,
                'failed' => $failed_count,
                'total' => $total_count,
                'message' => "Newsletter sent successfully to {$queued_count} of {$total_count} subscribers"
            ];
        }
    }

    /**
     * Get recent posts based on settings
     *
     * @param array $settings Plugin settings
     * @param bool $ignore_date Whether to ignore last send date (for manual sends)
     * @return array Array of WP_Post objects
     */
    private function get_recent_posts($settings, $ignore_date = false) {
        $args = [
            'post_type' => $settings['post_types'],
            'post_status' => 'publish',
            'posts_per_page' => $settings['posts_per_email'],
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,  // Performance: Don't calculate total rows
            'update_post_meta_cache' => false,  // Performance: Don't load meta
            'update_post_term_cache' => false,  // Performance: Don't load terms
            'cache_results' => false,  // CRITICAL: Don't use cached results - get fresh posts
        ];

        // For automated sends, only get posts since last send
        // For manual "Send Now", get the most recent posts regardless of date
        if (!$ignore_date) {
            $last_send = get_option('wpbm_last_newsletter_send', 0);
            if ($last_send > 0) {
                $args['date_query'] = [
                    [
                        'after' => date('Y-m-d H:i:s', $last_send),
                        'inclusive' => false,
                    ],
                ];
            }
        }

        $query = new \WP_Query($args);

        // DEBUG: Log query details
        error_log('WPBM: Post query - Posts per email: ' . $settings['posts_per_email'] . ', Ignore date: ' . ($ignore_date ? 'yes' : 'no'));

        return $query->posts;
    }

    /**
     * Render the newsletter template
     *
     * @param string $template_type Template type identifier
     * @param array $posts Array of WP_Post objects
     * @param string $subject Email subject
     * @param object $subscriber Subscriber object
     * @return string Rendered HTML content
     */
    private function render_newsletter_template($template_type, $posts, $subject, $subscriber) {
        // Prepare template data
        $template_data = [
            'posts' => $posts,
            'heading' => $subject,
            'subscriber' => $subscriber,
        ];

        // Determine which template service to use
        if ($template_type === 'basic' || empty($template_type)) {
            // Use basic template
            return $this->basic_template_service->render($template_data);
        } elseif (strpos($template_type, 'custom-') === 0 && $this->custom_template_service) {
            // Custom template
            $template_id = str_replace('custom-', '', $template_type);
            $custom_template = $this->custom_template_service->get_template($template_id);

            if ($custom_template) {
                // Process template content with placeholders
                return $this->process_template_placeholders($custom_template->content, $template_data);
            }

            // Fallback to basic if custom template not found
            $this->logger->warning("Custom template {$template_id} not found. Using basic template.");
            return $this->basic_template_service->render($template_data);
        } elseif (strpos($template_type, 'library-') === 0 && $this->template_library_service) {
            // Library template - get saved customization settings
            $template_id = str_replace('library-', '', $template_type);
            $settings = get_option('wpbm_settings', []);

            // Pass customization settings to template
            $library_content = $this->template_library_service->get_template_content($template_id, $settings);

            if ($library_content) {
                return $this->process_template_placeholders($library_content, $template_data);
            }

            // Fallback to basic if library template not found
            $this->logger->warning("Library template {$template_id} not found. Using basic template.");
            return $this->basic_template_service->render($template_data);
        }

        // Default fallback to basic template
        return $this->basic_template_service->render($template_data);
    }

    /**
     * Process template placeholders
     *
     * @param string $content Template content with placeholders
     * @param array $data Template data
     * @return string Processed content
     */
    private function process_template_placeholders($content, $data) {
        // Basic placeholder replacement
        $replacements = [
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => home_url(),
            '{{heading}}' => isset($data['heading']) ? $data['heading'] : '',
            '{{unsubscribe_url}}' => $this->basic_template_service->get_unsubscribe_url($data['subscriber']),
        ];

        // Add subscriber data
        if (isset($data['subscriber'])) {
            $replacements['{{subscriber_name}}'] = $data['subscriber']->first_name ?: 'Subscriber';
            $replacements['{{subscriber_email}}'] = $data['subscriber']->email;
        }

        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        // Process posts loop if present
        if (isset($data['posts']) && is_array($data['posts']) && !empty($data['posts'])) {
            // Get post content type setting
            $settings = get_option('wpbm_settings', []);
            $post_content_type = isset($settings['post_content_type']) ? $settings['post_content_type'] : 'excerpt';
            $excerpt_length = isset($settings['excerpt_length']) ? intval($settings['excerpt_length']) : 40;

            // DEBUG: Log the content type being used
            static $logged_once = false;
            if (!$logged_once) {
                error_log("WPBM: Rendering newsletter with post_content_type = '{$post_content_type}'");
                $logged_once = true;
            }

            $posts_html = '';
            foreach ($data['posts'] as $post) {
                if ($post_content_type === 'full') {
                    // Show FULL post content with HTML
                    $post_content = $post->post_content;

                    // Strip WordPress block comments but keep HTML
                    $post_content = preg_replace('/<!--\s*\/?wp:.*?-->/', '', $post_content);

                    // Apply the content (keeps safe HTML)
                    $post_text = wp_kses_post($post_content);

                    // Make images responsive for email clients (inline styles)
                    $post_text = $this->make_images_responsive($post_text);
                } else {
                    // Show excerpt only
                    $excerpt = !empty($post->post_excerpt) ? $post->post_excerpt : $post->post_content;

                    // Strip WordPress block comments
                    $excerpt = preg_replace('/<!--\s*\/?wp:.*?-->/', '', $excerpt);

                    // Strip all HTML tags for clean excerpt
                    $excerpt = wp_strip_all_tags($excerpt);

                    // Trim to desired length
                    $post_text = wp_trim_words($excerpt, $excerpt_length, '...');
                }

                $posts_html .= '<div class="post-item">';
                $posts_html .= '<h2><a href="' . esc_url(get_permalink($post->ID)) . '">' . esc_html($post->post_title) . '</a></h2>';
                $posts_html .= '<div>' . $post_text . '</div>';
                $posts_html .= '</div>';
            }
            $content = str_replace('{{posts_loop}}', $posts_html, $content);
        }

        return $content;
    }

    /**
     * Make images responsive for email clients by adding inline styles
     *
     * @param string $html HTML content with images
     * @return string HTML with responsive inline styles on images
     */
    private function make_images_responsive($html) {
        // Add inline styles to all img tags for email client compatibility
        // Email clients often strip <style> blocks, so inline styles are required
        $html = preg_replace_callback(
            '/<img([^>]*)>/i',
            function($matches) {
                $img_tag = $matches[0];
                $attributes = $matches[1];

                // Remove existing width/height attributes that would override responsive styles
                $attributes = preg_replace('/\s*width=(["\'])[^"\']*\1/i', '', $attributes);
                $attributes = preg_replace('/\s*height=(["\'])[^"\']*\1/i', '', $attributes);

                // Add or merge inline styles
                if (preg_match('/style=(["\'])([^"\']*)\1/i', $attributes, $style_match)) {
                    // Style attribute exists, append our styles
                    $existing_style = $style_match[2];
                    $new_style = $existing_style;
                    if (!preg_match('/max-width\s*:/i', $existing_style)) {
                        $new_style .= ' max-width: 100%;';
                    }
                    if (!preg_match('/height\s*:/i', $existing_style)) {
                        $new_style .= ' height: auto;';
                    }
                    if (!preg_match('/display\s*:/i', $existing_style)) {
                        $new_style .= ' display: block;';
                    }
                    $attributes = preg_replace('/style=(["\'])[^"\']*\1/i', 'style="' . trim($new_style) . '"', $attributes);
                } else {
                    // No style attribute, add it
                    $attributes .= ' style="max-width: 100%; height: auto; display: block;"';
                }

                return '<img' . $attributes . '>';
            },
            $html
        );

        return $html;
    }

    /**
     * Prepare the subject line with replacements
     *
     * @param string $template Subject line template
     * @param int $post_count Number of posts
     * @return string Processed subject line
     */
    private function prepare_subject_line($template, $post_count = 0) {
        $replacements = [
            '{site_name}' => get_bloginfo('name'),
            '{date}' => date_i18n(get_option('date_format')),
            '{post_count}' => $post_count,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
