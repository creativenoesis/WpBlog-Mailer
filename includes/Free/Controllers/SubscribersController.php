<?php
/**
 * Subscribers Controller
 *
 * Handles all subscriber-related admin requests
 *
 * @package WP_Blog_Mailer
 * @subpackage Free\Controllers
 * @since 2.0.0
 */

namespace WPBlogMailer\Free\Controllers;

use WPBlogMailer\Common\Services\SubscriberService;

if (!defined('ABSPATH')) exit;

class SubscribersController {

    /**
     * Subscriber service instance
     *
     * @var SubscriberService
     */
    private $service;

    /**
     * Constructor
     *
     * @param SubscriberService $subscriber_service Injected subscriber service.
     */
    public function __construct(SubscriberService $subscriber_service) {
        $this->service = $subscriber_service;
        // Hooks are initialized by Plugin.php calling init_hooks()
    }

    /**
     * Initialize hooks
     * Needs to be public so Plugin.php can call it.
     */
    public function init_hooks() {
        // Handle form submissions (Bulk Delete) using admin-post.php
        add_action('admin_post_wpbm_bulk_delete_subscribers', array($this, 'handle_bulk_delete')); // Specific action hook

        // AJAX handlers
        add_action('wp_ajax_wpbm_add_subscriber', array($this, 'handle_ajax_requests'));
        add_action('wp_ajax_wpbm_edit_subscriber', array($this, 'handle_ajax_requests'));
        add_action('wp_ajax_wpbm_get_subscriber', array($this, 'handle_ajax_requests'));
        add_action('wp_ajax_wpbm_delete_subscriber', array($this, 'handle_ajax_requests'));

        // Admin notices
        add_action('admin_notices', array($this, 'display_notices'));

        // Import/Export hooks (ensure these match form actions)
        add_action('admin_post_wpbm_import_subscribers', array($this, 'handle_import_subscribers'));
        add_action('admin_post_wpbm_export_subscribers', array($this, 'handle_export_subscribers'));
    }

    /**
     * Handle bulk delete action submitted via admin-post.php
     *
     * @return void
     */
    public function handle_bulk_delete() {
         // Check if the correct bulk action is set
        $action = $this->get_current_bulk_action(); // Checks 'bulk_action' or 'bulk_action2'

        if ($action !== 'delete' || empty($_POST['subscriber_ids'])) {
             // Redirect back if action is not delete or no IDs selected
            wp_redirect(add_query_arg('page', 'wpbm-subscribers', admin_url('admin.php')));
            exit;
        }

        // Check nonce (matches the nonce in the form)
        if (!isset($_POST['wpbm_bulk_nonce']) || !wp_verify_nonce($_POST['wpbm_bulk_nonce'], 'wpbm_bulk_delete_subscribers')) {
            wp_die(__('Security check failed', 'wpblogmailer'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to delete subscribers.', 'wpblogmailer'));
        }

        // Sanitize IDs
        $ids = array_map('intval', $_POST['subscriber_ids']);

        // Call the service method 'delete_many'
        $deleted_count = $this->service->delete_many($ids);

        // Prepare feedback message
        if ($deleted_count > 0) {
            $message = sprintf(_n('%s subscriber deleted.', '%s subscribers deleted.', $deleted_count, 'wpblogmailer'), $deleted_count);
            $type = 'success';
        } else {
             // This could happen if IDs were invalid or deletion failed in DB
            $message = __('No subscribers were deleted. They may have already been removed.', 'wpblogmailer');
            $type = 'warning';
        }


        // Redirect back to the subscribers list page
        $redirect_url = add_query_arg([
            'page' => 'wpbm-subscribers',
            'message' => urlencode($message),
            'type' => $type
        ], admin_url('admin.php'));

        wp_redirect($redirect_url);
        exit;
    }


    /**
     * Render the main subscribers page (loads the list table)
     *
     * @return void
     */
    public function render_page() {
        // --- START FIX ---
        // Ensure the $service variable is explicitly available for the include.
        $service = $this->service;
        // --- END FIX ---

        // Define WPBM_PLUGIN_PATH if it's not already defined elsewhere (e.g., in the main plugin file)
        if (!defined('WPBM_PLUGIN_PATH')) {
            define('WPBM_PLUGIN_PATH', plugin_dir_path(dirname(dirname(__FILE__)))); // Adjust levels if needed
        }

        $view_file = WPBM_PLUGIN_PATH . 'includes/Free/Views/subscribers-list.php';

        if (file_exists($view_file)) {
            // Check if $service is valid before including
            if (!$service instanceof SubscriberService) {
                 echo '<div class="wrap"><h1>Error</h1><p>Subscriber service is not available.</p></div>';
                 return;
            }
            include $view_file; // $service variable defined above is available here
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Subscribers view file not found at: ' . esc_html($view_file) . '</p></div>';
        }
    }

    /**
     * Handle all AJAX requests for subscribers
     *
     * @return void
     */
    public function handle_ajax_requests() {
        $action = isset($_POST['action']) ? sanitize_key($_POST['action']) : '';
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
        $nonce_action = 'wpbm_subscriber_nonce'; // Centralized nonce action name

        // Verify nonce
        if (!wp_verify_nonce($nonce, $nonce_action)) {
            wp_send_json_error(['message' => __('Nonce verification failed.', 'wpblogmailer')], 403);
            return;
        }

        // Permission check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'wpblogmailer')], 403);
            return;
        }

        try {
            $data = $this->sanitize_post_data($_POST); // Sanitize incoming data

            switch ($action) {
                case 'wpbm_add_subscriber':
                    if (empty($data['email'])) {
                        throw new \Exception(__('Email is required.', 'wpblogmailer'));
                    }
                    if ($this->service->email_exists($data['email'])) {
                        throw new \Exception(__('This email address is already subscribed.', 'wpblogmailer'));
                    }

                    $new_id = $this->service->create($data);
                    if (!$new_id) {
                        throw new \Exception(__('Could not add subscriber. Please try again.', 'wpblogmailer'));
                    }

                    // Handle tags for Pro users
                    if (wpbm_is_pro() && !empty($data['tags']) && is_array($data['tags'])) {
                        $segment_service = new \WPBlogMailer\Pro\Services\SegmentService();
                        $segment_service->sync_subscriber_tags($new_id, array_map('intval', $data['tags']));
                    }

                    wp_send_json_success([
                        'message' => __('Subscriber added successfully.', 'wpblogmailer'),
                        'subscriber_id' => $new_id
                    ]);
                    break;

                case 'wpbm_edit_subscriber':
                     if (empty($data['id'])) {
                        throw new \Exception(__('Invalid subscriber ID.', 'wpblogmailer'));
                    }
                    if (empty($data['email'])) {
                        throw new \Exception(__('Email is required.', 'wpblogmailer'));
                    }
                    // Check if email exists for *another* subscriber
                    if ($this->service->email_exists($data['email'], $data['id'])) {
                        throw new \Exception(__('This email address is already in use by another subscriber.', 'wpblogmailer'));
                    }

                    $success = $this->service->update($data['id'], $data);
                    // update returns true/false based on success, 0 rows affected is not an error here
                     if ($success === false) { // Check specifically for false (DB error)
                        throw new \Exception(__('Could not update subscriber. Please try again.', 'wpblogmailer'));
                    }

                    // Handle tags for Pro users
                    if (wpbm_is_pro()) {
                        $segment_service = new \WPBlogMailer\Pro\Services\SegmentService();
                        $tag_ids = isset($data['tags']) && is_array($data['tags']) ? array_map('intval', $data['tags']) : array();
                        $segment_service->sync_subscriber_tags($data['id'], $tag_ids);
                    }

                    wp_send_json_success(['message' => __('Subscriber updated successfully.', 'wpblogmailer')]);
                    break;

                case 'wpbm_get_subscriber':
                    if (empty($data['id'])) {
                        throw new \Exception(__('Invalid subscriber ID.', 'wpblogmailer'));
                    }
                    $subscriber = $this->service->find(intval($data['id']));
                    if (!$subscriber) {
                         throw new \Exception(__('Subscriber not found.', 'wpblogmailer'));
                    }

                    $response_data = ['subscriber' => $subscriber];

                    // Include tags for Pro users
                    if (wpbm_is_pro()) {
                        $tag_model = new \WPBlogMailer\Common\Models\Tag();
                        $tags = $tag_model->get_subscriber_tags(intval($data['id']));
                        $response_data['tags'] = $tags;
                    }

                    wp_send_json_success($response_data);
                    break;

                case 'wpbm_delete_subscriber':
                    if (empty($data['id'])) {
                        throw new \Exception(__('Invalid subscriber ID.', 'wpblogmailer'));
                    }
                    $success = $this->service->delete(intval($data['id']));
                     if (!$success) {
                        throw new \Exception(__('Could not delete subscriber. Please try again.', 'wpblogmailer'));
                    }
                    wp_send_json_success(['message' => __('Subscriber deleted successfully.', 'wpblogmailer')]);
                    break;

                default:
                    wp_send_json_error(['message' => __('Unknown AJAX action.', 'wpblogmailer')], 400);
            }
        } catch (\Exception $e) {
            // Send error message back to AJAX request
            wp_send_json_error(['message' => $e->getMessage()], 400);
        }
        // wp_die(); // Required for AJAX handlers
    }

    /**
     * Sanitize POST data for subscriber operations
     */
    private function sanitize_post_data($post) {
        $data = [];
        if (isset($post['id'])) {
            $data['id'] = intval($post['id']);
        }
        if (isset($post['email'])) {
            $data['email'] = sanitize_email($post['email']);
        }
        if (isset($post['first_name'])) {
            $data['first_name'] = sanitize_text_field($post['first_name']);
        }
        if (isset($post['last_name'])) {
            $data['last_name'] = sanitize_text_field($post['last_name']);
        }
        if (isset($post['status'])) {
             // Validate against allowed statuses
            $allowed_statuses = ['pending', 'confirmed', 'unsubscribed'];
            $data['status'] = in_array($post['status'], $allowed_statuses) ? $post['status'] : 'pending';
        }

        // Handle the single 'name' field if still used by older JS/forms
        if (!empty($post['name']) && empty($data['first_name']) && empty($data['last_name'])) {
            $name_parts = explode(' ', trim(sanitize_text_field($post['name'])), 2);
            $data['first_name'] = $name_parts[0];
            $data['last_name'] = isset($name_parts[1]) ? $name_parts[1] : '';
        }

        return $data;
    }


    /**
     * Get current bulk action from list table POST data
     */
     private function get_current_bulk_action() {
        $action = -1;
        // Check both top and bottom dropdowns, prioritize the one that's not -1
        if (isset($_POST['bulk_action']) && $_POST['bulk_action'] != -1) {
            $action = sanitize_key($_POST['bulk_action']);
        } elseif (isset($_POST['bulk_action2']) && $_POST['bulk_action2'] != -1) {
            $action = sanitize_key($_POST['bulk_action2']);
        }
        return $action;
    }

    /**
     * Display admin notices based on URL parameters
     *
     * @return void
     */
    public function display_notices() {
        if (isset($_GET['message']) && isset($_GET['type'])) {
            $message = urldecode(sanitize_text_field($_GET['message']));
            $type = sanitize_key($_GET['type']); // 'success', 'error', 'warning', 'info'

            // Ensure type is valid
            if (!in_array($type, ['success', 'error', 'warning', 'info'])) {
                $type = 'info';
            }

            // Output the notice
            printf(
                '<div class="notice notice-%s is-dismissible wpbm-notice" style="margin-top: 15px;"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message)
            );

            // Remove query args from URL using JS to prevent notice reappearing on refresh
            add_action('admin_footer', function() {
                ?>
                <script type="text/javascript">
                    (function() {
                        if (window.history && window.history.replaceState) {
                            var url = new URL(window.location.href);
                            url.searchParams.delete('message');
                            url.searchParams.delete('type');
                            window.history.replaceState({path: url.href}, '', url.href);
                        }
                    })();
                </script>
                <?php
            });
        }
    }

    // --- IMPORT/EXPORT METHODS ---

    /**
     * Handles the subscriber import from a CSV file via admin-post.php
     */
    public function handle_import_subscribers() {
        // Security Checks
        if (!isset($_POST['wpbm_import_nonce']) || !wp_verify_nonce($_POST['wpbm_import_nonce'], 'wpbm_import_subscribers')) {
            wp_die('Security check failed.');
        }
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to import subscribers.');
        }

        // File Validation
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK || empty($_FILES['csv_file']['tmp_name'])) {
             $error_code = $_FILES['csv_file']['error'] ?? 'unknown';
             $this->redirect_with_message('No file uploaded or upload error occurred (Code: ' . $error_code . ').', 'error', 'wpbm-import-export');
            return;
        }

        $file_path = $_FILES['csv_file']['tmp_name'];
        $file_name = $_FILES['csv_file']['name'];

        // Validate file extension first
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if ($file_extension !== 'csv') {
            $this->redirect_with_message('Invalid file extension. Please upload a CSV file (.csv).', 'error', 'wpbm-import-export');
            return;
        }

        // Validate MIME type using finfo (modern, more reliable method)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $file_type = finfo_file($finfo, $file_path);
            finfo_close($finfo);

            $allowed_types = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];

            if (!in_array($file_type, $allowed_types)) {
                $this->redirect_with_message('Invalid file type detected: ' . esc_html($file_type) . '. Please upload a valid CSV file.', 'error', 'wpbm-import-export');
                return;
            }
        }

        // Additional validation: Check if file actually contains CSV data by reading first line
        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            $this->redirect_with_message('Unable to read uploaded file.', 'error', 'wpbm-import-export');
            return;
        }

        $first_line = fgets($handle);
        fclose($handle);

        // Very basic CSV validation - should contain commas
        if ($first_line === false || strpos($first_line, ',') === false) {
            $this->redirect_with_message('File does not appear to be a valid CSV file.', 'error', 'wpbm-import-export');
            return;
        }

        // Parse CSV
        $csv_data = $this->parse_csv_file($file_path);
        if ($csv_data === false) { // Check for false indicating read error
             $this->redirect_with_message('Could not read the uploaded CSV file.', 'error', 'wpbm-import-export');
             return;
        }
        if (empty($csv_data)) {
            $this->redirect_with_message('The CSV file is empty or does not contain valid data.', 'warning', 'wpbm-import-export');
            return;
        }


        // Process Import via the Service
        try {
            $skip_duplicates = isset($_POST['skip_duplicates']) && $_POST['skip_duplicates'] === '1';
            $result = $this->service->import_subscribers($csv_data, $skip_duplicates);

            $this->redirect_with_message($result['message'], $result['success'] ? 'success' : 'error', 'wpbm-import-export');

        } catch (\Exception $e) {
            $this->redirect_with_message('An error occurred during import: ' . esc_html($e->getMessage()), 'error', 'wpbm-import-export');
        }
    }

    /**
     * Handles the subscriber export to a CSV file via admin-post.php
     */
    public function handle_export_subscribers() {
        // Security Checks
        if (!isset($_POST['wpbm_export_nonce']) || !wp_verify_nonce($_POST['wpbm_export_nonce'], 'wpbm_export_subscribers')) {
            wp_die('Security check failed.');
        }
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to export subscribers.');
        }

        // Get Data Args
        $status = isset($_POST['export_status']) ? sanitize_key($_POST['export_status']) : 'all';
        $args = [
            'status' => ($status === 'all' ? '' : $status),
            'fields' => ['email', 'first_name', 'last_name', 'status', 'created_at'] // Define fields to export
        ];

        try {
            $data_to_export = $this->service->export($args);

            if (empty($data_to_export)) {
                $this->redirect_with_message('There are no subscribers to export matching the selected criteria.', 'warning', 'wpbm-import-export');
                return;
            }

            // Output CSV
            $filename = 'wpbm_subscribers_' . $status . '_' . date('Y-m-d_H-i-s') . '.csv';
            $this->output_csv($filename, $data_to_export);
            exit; // Stop execution after sending file

        } catch (\Exception $e) {
            $this->redirect_with_message('An error occurred during export: ' . esc_html($e->getMessage()), 'error', 'wpbm-import-export');
        }
    }

    /**
     * Helper to parse a CSV file into an associative array.
     * Returns false on failure to open file.
     */
    private function parse_csv_file($file_path) {
        $data = [];
        $header = [];

        if (($handle = fopen($file_path, 'r')) !== FALSE) {
            // Get the header row, trim whitespace from headers
            if (($header_row = fgetcsv($handle, 1000, ',')) !== FALSE) {
                 $header = array_map('trim', $header_row);
                 // Basic validation: Check if essential headers like 'Email' exist (case-insensitive)
                 $lower_header = array_map('strtolower', $header);
                 if (!in_array('email', $lower_header)) {
                      fclose($handle);
                      $this->redirect_with_message('CSV file must contain an "Email" column header.', 'error', 'wpbm-import-export');
                      return false; // Indicate error
                 }
            } else {
                 fclose($handle);
                 return $data; // Empty file or header only
            }

            // Get the rest of the rows
            while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
                // Skip completely empty rows
                if (empty(array_filter($row, function($value) { return $value !== null && $value !== ''; }))) {
                    continue;
                }

                $row_count = count($row);
                $header_count = count($header);

                // Try to combine, handling potential mismatches gracefully
                try {
                     if ($row_count == $header_count) {
                         $data[] = array_combine($header, $row);
                    } elseif ($row_count > $header_count) {
                        // More data columns than headers, truncate data row
                         $data[] = array_combine($header, array_slice($row, 0, $header_count));
                    } else {
                        // Fewer data columns than headers, pad data row
                         $data[] = array_combine($header, array_pad($row, $header_count, ''));
                    }
                } catch (\ValueError $e) {
                     // Catch array_combine errors (e.g., if counts still mismatch unexpectedly)
                      // Optionally log this error
                      fclose($handle);
                      $this->redirect_with_message('Error processing CSV row. Ensure column count matches header.', 'error', 'wpbm-import-export');
                      return false;
                }
            }
            fclose($handle);
        } else {
             return false; // Could not open file
        }
        return $data;
    }


    /**
     * Helper to send CSV data to the browser as a download.
     */
    private function output_csv($filename, $data) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"'); // Sanitize filename
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM for better Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Add header row
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
        }

        // Add data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit; // Ensure no further output
    }

    /**
     * Helper to redirect back to a specific admin page with a message.
     */
    private function redirect_with_message($message, $type = 'success', $page = 'wpbm-subscribers') {
        $url = add_query_arg([
            'page' => $page,
            'message' => urlencode($message),
            'type' => $type
        ], admin_url('admin.php'));

        wp_redirect($url);
        exit;
    }

} // End Class