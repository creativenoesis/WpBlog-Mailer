<?php
/**
 * Subscriber Model
 * * Handles all database operations for subscribers
 * * @package WP_Blog_Mailer
 * @subpackage Common\Models
 * @since 2.0.0
 */

namespace WPBlogMailer\Common\Models;

if (!defined('ABSPATH')) exit;

class Subscriber {
    
    /**
     * Database table name
     *
     * @var string
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wpbm_subscribers';
    }
    
    /**
     * Get subscriber by ID
     *
     * @param int $id Subscriber ID
     * @return object|null Subscriber object or null
     */
    public function find($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get subscriber by email
     *
     * @param string $email Email address
     * @return object|null Subscriber object or null
     */
    public function find_by_email($email) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE email = %s",
            sanitize_email($email)
        ));
    }
    
    /**
     * Get all subscribers with filtering and pagination
     *
     * @param array $args Query arguments
     * @return array Array with subscribers, total, total_pages, current_page
     */
    public function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'search' => '',
            'status' => '', // 'confirmed', 'pending', 'unsubscribed', or '' for all
            'orderby' => 'id',
            'order' => 'DESC',
            'offset' => null // Can override pagination
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where = array('1=1');
        $where_values = array();
        
        // Search filter
        if (!empty($args['search'])) {
            $where[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        // Status filter
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare($count_query, $where_values);
        }
        $total = (int)$wpdb->get_var($count_query);
        
        // Calculate pagination
        $total_pages = ceil($total / $args['per_page']);
        $offset = isset($args['offset']) ? $args['offset'] : (($args['page'] - 1) * $args['per_page']);
        
        // Validate orderby
        $allowed_orderby = array('id', 'first_name', 'last_name', 'email', 'created_at', 'status');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'id';
        
        // Validate order
        $order = in_array(strtoupper($args['order']), array('ASC', 'DESC')) ? strtoupper($args['order']) : 'DESC';
        
        // Build main query
        $query = "SELECT * FROM {$this->table_name} 
                  WHERE {$where_clause} 
                  ORDER BY {$orderby} {$order} 
                  LIMIT %d OFFSET %d";
        
        $query_values = array_merge($where_values, array($args['per_page'], $offset));
        $subscribers = $wpdb->get_results($wpdb->prepare($query, $query_values));
        
        return array(
            'subscribers' => $subscribers,
            'total' => $total,
            'total_pages' => $total_pages,
            'current_page' => $args['page']
        );
    }
    
    /**
     * Get only confirmed subscribers
     *
     * @return array Array of subscriber objects
     */
    public function get_confirmed() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE status = 'confirmed' ORDER BY id ASC"
        );
    }
    
    /**
     * Create a new subscriber
     *
     * @param array $data Subscriber data
     * @return int|false Subscriber ID on success, false on failure
     */
    public function create($data) {
        global $wpdb;

        $current_time = current_time('mysql');

        // Generate unique unsubscribe/confirmation key if not provided
        $unsubscribe_key = isset($data['unsubscribe_key']) && !empty($data['unsubscribe_key'])
            ? $data['unsubscribe_key']
            : wp_generate_password(32, false, false);

        // Prepare data based on new schema
        $insert_data = array(
            'email' => sanitize_email($data['email']),
            'first_name' => isset($data['first_name']) ? sanitize_text_field($data['first_name']) : '',
            'last_name' => isset($data['last_name']) ? sanitize_text_field($data['last_name']) : '',
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'pending',
            'unsubscribe_key' => $unsubscribe_key,
            'created_at' => isset($data['created_at']) ? $data['created_at'] : $current_time,
            'updated_at' => $current_time,
        );

        $result = $wpdb->insert(
            $this->table_name,
            $insert_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }
    
    /**
     * Update an existing subscriber
     *
     * @param int $id Subscriber ID
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        // First Name
        if (isset($data['first_name'])) {
            $update_data['first_name'] = sanitize_text_field($data['first_name']);
            $format[] = '%s';
        }

        // Last Name
        if (isset($data['last_name'])) {
            $update_data['last_name'] = sanitize_text_field($data['last_name']);
            $format[] = '%s';
        }
        
        // Email
        if (isset($data['email'])) {
            $update_data['email'] = sanitize_email($data['email']);
            $format[] = '%s';
        }
        
        // Status
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
            $format[] = '%s';
        }

        // Always update the updated_at timestamp
        $update_data['updated_at'] = current_time('mysql');
        $format[] = '%s';
        
        if (count($update_data) <= 1) { // Only updated_at, no real data change
            return false;
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => (int)$id),
            $format,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a subscriber
     *
     * @param int $id Subscriber ID
     * @return bool True on success, false on failure
     */
    public function delete($id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => (int)$id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete multiple subscribers
     *
     * @param array $ids Array of subscriber IDs
     * @return int Number of deleted subscribers
     */
    public function delete_many($ids) {
        global $wpdb;
        
        if (empty($ids)) {
            return 0;
        }
        
        // Sanitize IDs
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        
        $query = "DELETE FROM {$this->table_name} WHERE id IN ({$placeholders})";
        $result = $wpdb->query($wpdb->prepare($query, $ids));
        
        return $result !== false ? $result : 0;
    }
    
    /**
     * Check if email already exists
     *
     * @param string $email Email address
     * @param int|null $exclude_id Exclude this ID from check (for updates)
     * @return bool True if exists, false otherwise
     */
    public function email_exists($email, $exclude_id = null) {
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE email = %s";
        $values = array(sanitize_email($email));
        
        if ($exclude_id !== null) {
            $query .= " AND id != %d";
            $values[] = (int)$exclude_id;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($query, $values));
        
        return $count > 0;
    }
    
    /**
     * Get total subscriber count
     *
     * @param string $status Optional status filter ('confirmed', 'pending', etc.)
     * @return int Count
     */
    public function count($status = '') {
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM {$this->table_name}";
        
        if (!empty($status)) {
             // Use prepare for security, although $status is internal
            $query .= $wpdb->prepare(" WHERE status = %s", $status);
        }
        
        return (int)$wpdb->get_var($query);
    }
    
    /**
     * Get subscriber statistics
     *
     * @return array Statistics array (Matches keys expected by dashboard.php)
     */
    public function get_stats() {
        return array(
            'total' => $this->count(),
            'active' => $this->count('confirmed'),      // 'active' is 'confirmed'
            'unconfirmed' => $this->count('pending')   // 'unconfirmed' is 'pending'
        );
    }
    
    /**
     * Confirm a subscriber (for double opt-in)
     *
     * @param int $id Subscriber ID
     * @return bool True on success, false on failure
     */
    public function confirm($id) {
        return $this->update($id, array(
            'status' => 'confirmed'
        ));
    }
    
    /**
     * Get user's IP address
     *
     * @return string IP address
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        }
        return sanitize_text_field($ip);
    }
    
    // --- START: RESTORED IMPORT/EXPORT METHODS ---

    /**
     * Import subscribers from array (Handles DB interaction)
     *
     * @param array $subscribers Array of subscriber data from CSV
     * @param bool $skip_duplicates Whether to skip duplicate emails
     * @return array Result with success, failed, and duplicate counts
     */
    public function import($subscribers, $skip_duplicates = true) {
        $result = array(
            'success' => 0,
            'failed' => 0,
            'duplicates' => 0,
            'errors' => array() // Keep track of specific errors if needed
        );
        
        foreach ($subscribers as $index => $subscriber) {
            
            // Assume validation happened in Service/Controller
            $email = $subscriber['email'] ?? null;
            if (empty($email)) {
                 $result['failed']++;
                 $result['errors'][] = "Row " . ($index + 1) . ": Missing email";
                 continue;
            }

            // Check for duplicates
            if ($this->email_exists($email)) {
                if ($skip_duplicates) {
                    $result['duplicates']++;
                    continue;
                } else {
                    // Decide if duplicates should be an error or update (here, we treat as error)
                     $result['failed']++;
                     $result['errors'][] = "Row " . ($index + 1) . ": Email {$email} already exists";
                     continue;
                }
            }
            
            // Prepare data for creation (already formatted by Controller/Service)
            $create_data = [
                'email' => $email,
                'first_name' => $subscriber['first_name'] ?? '',
                'last_name' => $subscriber['last_name'] ?? '',
                'status' => $subscriber['status'] ?? 'confirmed', // Service might set this
                'created_at' => $subscriber['created_at'] ?? current_time('mysql'),
            ];

            // Create subscriber using the model's own create method
            $subscriber_id = $this->create($create_data);
            
            if ($subscriber_id) {
                $result['success']++;
            } else {
                $result['failed']++;
                 $result['errors'][] = "Row " . ($index + 1) . ": Database error creating {$email}";
            }
        }
        
        return $result;
    }
    
    /**
     * Export subscribers to array (Handles DB interaction)
     *
     * @param array $args Export arguments (like status, fields)
     * @return array Array of subscriber data
     */
    public function export($args = array()) {
        $defaults = array(
            'status' => '', // '', 'confirmed', 'pending', 'unsubscribed'
            'fields' => array('email', 'first_name', 'last_name', 'status', 'created_at', 'updated_at')
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Get all subscribers (no pagination) using the model's get_all
        $result = $this->get_all(array(
            'page' => 1,
            'per_page' => 999999, // Effectively get all
            'status' => $args['status']
        ));
        
        $export_data = array();
        
        // Format the data according to specified fields
        if (!empty($result['subscribers'])) {
            foreach ($result['subscribers'] as $subscriber) {
                $row = array();
                foreach ($args['fields'] as $field) {
                    if (property_exists($subscriber, $field)) {
                        $value = $subscriber->$field;
                        
                        // Basic formatting (can be enhanced)
                        if (in_array($field, array('created_at', 'updated_at')) && !empty($value)) {
                             // Assuming wpbm_format_date helper exists (optional)
                             $value = function_exists('wpbm_format_date') ? wpbm_format_date($value) : $value;
                        }
                        
                        $row[$field] = $value;
                    } else {
                         $row[$field] = ''; // Add empty value if field doesn't exist on object
                    }
                }
                $export_data[] = $row;
            }
        }
        
        return $export_data;
    }
    
    // --- END: RESTORED METHODS ---

}