<?php
/**
 * Free Service: Basic Template
 * Handles rendering the free basic newsletter
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Free\Services;

use WPBlogMailer\Common\Services\TemplateService;

/**
 * Basic Template Service Class
 * Migrates logic from old template-basic.php
 */
class BasicTemplateService {

    /**
     * @var TemplateService
     */
    private $template_service;

    public function __construct(TemplateService $template_service) {
        $this->template_service = $template_service;
    }

    /**
     * Render the basic newsletter template.
     *
     * @param array $template_data (['subscriber' => object, 'posts' => array, 'heading' => string])
     * @return string The rendered HTML
     */
    public function render($template_data) {

        // Get plugin settings for post content display and customization
        $settings = get_option('wpbm_settings', []);
        $post_content_type = isset($settings['post_content_type']) ? $settings['post_content_type'] : 'excerpt';
        $excerpt_length = isset($settings['excerpt_length']) ? intval($settings['excerpt_length']) : 40;

        // DEBUG: Log the content type being used
        static $logged_once = false;
        if (!$logged_once) {
            error_log("WPBM: BasicTemplateService rendering with post_content_type = '{$post_content_type}'");
            $logged_once = true;
        }

        // Get template customization settings
        $primary_color = isset($settings['template_primary_color']) ? $settings['template_primary_color'] : '#667eea';
        $bg_color = isset($settings['template_bg_color']) ? $settings['template_bg_color'] : '#f7f7f7';
        $text_color = isset($settings['template_text_color']) ? $settings['template_text_color'] : '#333333';
        $link_color = isset($settings['template_link_color']) ? $settings['template_link_color'] : '#2271b1';
        $heading_font = isset($settings['template_heading_font']) ? $settings['template_heading_font'] : 'Arial, sans-serif';
        $body_font = isset($settings['template_body_font']) ? $settings['template_body_font'] : 'Georgia, serif';

        // Get greeting and site link settings
        $enable_greeting = isset($settings['enable_greeting']) ? $settings['enable_greeting'] : true;
        $greeting_text = isset($settings['greeting_text']) ? $settings['greeting_text'] : 'Hi {first_name},';
        $enable_site_link = isset($settings['enable_site_link']) ? $settings['enable_site_link'] : true;

        // Prepare data for the template file
        $data_for_template = [
            'posts'         => $template_data['posts'],
            'heading'       => $template_data['heading'],
            'subscriber'    => $template_data['subscriber'],
            'unsubscribe_url' => $this->get_unsubscribe_url($template_data['subscriber']),
            'site_name'     => get_bloginfo('name'),
            'site_url'      => home_url(),
            'post_content_type' => $post_content_type,
            'excerpt_length' => $excerpt_length,
            'enable_greeting' => $enable_greeting,
            'greeting_text' => $greeting_text,
            'enable_site_link' => $enable_site_link,
            'primary_color' => $primary_color,
            'bg_color'      => $bg_color,
            'text_color'    => $text_color,
            'link_color'    => $link_color,
            'heading_font'  => $heading_font,
            'body_font'     => $body_font,
        ];

        // Render the actual template file
        // We assume the old 'template-basic.php' content is moved to this new location
        return $this->template_service->render('emails/newsletter-basic.php', $data_for_template);
    }

    /**
     * Generate an unsubscribe URL for a subscriber.
     *
     * @param object $subscriber
     * @return string
     */
    public function get_unsubscribe_url($subscriber) {
        if (!empty($subscriber) && !empty($subscriber->unsubscribe_key)) {
            return add_query_arg(array(
                'wpbm_action' => 'unsubscribe',
                'key' => $subscriber->unsubscribe_key
            ), home_url());
        }
        return '#'; // Fallback
    }
}