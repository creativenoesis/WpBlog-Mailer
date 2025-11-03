<?php
/**
 * Common Service: Template
 * Handles rendering email templates
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Common\Services;

/**
 * Template Service Class
 * Renders a template file with provided data.
 */
class TemplateService {

    /**
     * The base path to the templates directory.
     *
     * @var string
     */
    private $template_path;

    public function __construct() {
        $this->template_path = plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'templates/';
    }

    /**
     * Render a template file with data.
     *
     * @param string $template_name The name of the template file (e.g., 'emails/newsletter-basic.php')
     * @param array $data Data to extract into the template
     * @return string The rendered HTML
     */
    public function render($template_name, $data = []) {
        $template_file = $this->template_path . $template_name;

        if (!file_exists($template_file)) {
            // Optionally log this error
            // $logger->error('Template file not found: ' . $template_name);
            return '<p>Error: Template file not found.</p>';
        }

        // Extract data variables into the local scope (e.g., $data['posts'] becomes $posts)
        extract($data);

        ob_start();
        include $template_file;
        return ob_get_clean();
    }
}