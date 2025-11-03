<?php
/**
 * Subscriber Service
 * * Handles all database operations for subscribers
 * * @package WP_Blog_Mailer
 * @subpackage Common\Services
 * @since 2.0.0
 */

// Correct namespace
namespace WPBlogMailer\Common\Services;

// Import the Database class for dependency injection
use WPBlogMailer\Common\Database\Database;

if (!defined('ABSPATH')) exit;

// Correct class name
class SubscriberService {
    
    /**
     * WordPress database object
     *
     * @var \wpdb
     */
    public $wpdb; // Property to hold the $wpdb object

    /**
     * Database table name
     *
     * @var string
     */
    private $table_name;
    
    /**
     * Constructor
     *
     * Accept the injected Database object
     *
     * @param Database $db The Database service
     */
    public function __construct(Database $db) {
        $this->wpdb = $db->wpdb; 
        $this->table_name = $this->wpdb->prefix . 'wpbm_subscribers';
    }
    
    /**
     * Get subscriber by ID
     *
     * @param int $id Subscriber ID
     * @return object|null Subscriber object or null
     */
    public function find($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
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
        return $this->wpdb->get_row($this->wpdb->prepare(
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
            $search_term = '%' . $this->wpdb->esc_like($args['search']) . '%';
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
        
        // Get total count using the corrected count method
        $total = $this->count($args['status'], $args['search']); // Pass search term to count
        
        // Calculate pagination
        $total_pages = $args['per_page'] > 0 ? ceil($total / $args['per_page']) : 1;
        $offset = isset($args['offset']) ? $args['offset'] : (($args['page'] - 1) * $args['per_page']);
        
        // Validate orderby
        $allowed_orderby = array('id', 'first_name', 'last_name', 'email', 'created_at', 'status');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'id';
        
        // Validate order
        $order = in_array(strtoupper($args['order']), array('ASC', 'DESC')) ? strtoupper($args['order']) : 'DESC';
        
        // Build main query
        $query = "SELECT * FROM {$this->table_name} 
                  WHERE {$where_clause} 
                  ORDER BY {$orderby} {$order}";

        // Add LIMIT and OFFSET only if per_page is positive
        if ($args['per_page'] > 0) {
            $query .= " LIMIT %d OFFSET %d";
            $query_values = array_merge($where_values, array($args['per_page'], $offset));
        } else {
             $query_values = $where_values; // No limit/offset values needed
        }
        
        $subscribers = $this->wpdb->get_results($this->wpdb->prepare($query, $query_values));
        
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
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE status = 'confirmed' ORDER BY id ASC" // Use status column
        );
    }
    
    /**
     * Create a new subscriber
     *
     * @param array $data Subscriber data
     * @return int|false Subscriber ID on success, false on failure
     * @throws \Exception if subscriber limit is reached on free plan
     */
    public function create($data) {
        // Check subscriber limit for free plan
        if (function_exists('wpbm_is_free') && wpbm_is_free()) {
            $current_count = $this->count();
            if ($current_count >= 250) {
                throw new \Exception(__('Subscriber limit reached. The free version allows up to 250 subscribers. Please upgrade to add more subscribers.', 'wpblogmailer'));
            }
        }

        $current_time = current_time('mysql');

        // Generate unique unsubscribe/confirmation key if not provided
        $unsubscribe_key = isset($data['unsubscribe_key']) && !empty($data['unsubscribe_key'])
            ? $data['unsubscribe_key']
            : wp_generate_password(32, false, false);

        $insert_data = array(
            'email' => sanitize_email($data['email']),
            'first_name' => isset($data['first_name']) ? sanitize_text_field($data['first_name']) : '',
            'last_name' => isset($data['last_name']) ? sanitize_text_field($data['last_name']) : '',
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'pending',
            'unsubscribe_key' => $unsubscribe_key,
            'created_at' => isset($data['created_at']) ? $data['created_at'] : $current_time,
            'updated_at' => $current_time,
        );

        $result = $this->wpdb->insert(
            $this->table_name,
            $insert_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        return $result ? $this->wpdb->insert_id : false;
    }
    
    /**
     * Update an existing subscriber
     *
     * @param int $id Subscriber ID
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public function update($id, $data) {
        $update_data = array();
        $format = array();
        
        if (isset($data['first_name'])) {
            $update_data['first_name'] = sanitize_text_field($data['first_name']);
            $format[] = '%s';
        }
        if (isset($data['last_name'])) {
            $update_data['last_name'] = sanitize_text_field($data['last_name']);
            $format[] = '%s';
        }
        if (isset($data['email'])) {
            $update_data['email'] = sanitize_email($data['email']);
            $format[] = '%s';
        }
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
            $format[] = '%s';
        }

        if (empty($update_data)) { // No actual data changed
            return false; 
        }

        // Always update the updated_at timestamp if other data changed
        $update_data['updated_at'] = current_time('mysql');
        $format[] = '%s';
        
        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => (int)$id),
            $format,
            array('%d')
        );
        
        // update returns number of rows affected (0 if no change, false on error)
        return $result !== false; 
    }
    
    /**
     * Delete a subscriber
     *
     * @param int $id Subscriber ID
     * @return bool True on success, false on failure
     */
    public function delete($id) {
        $result = $this->wpdb->delete(
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
     * @return int Number of deleted subscribers (0 on failure or if none deleted)
     */
    public function delete_many($ids) {
        if (empty($ids)) {
            return 0;
        }
        
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        
        $query = "DELETE FROM {$this->table_name} WHERE id IN ({$placeholders})";
        $result = $this->wpdb->query($this->wpdb->prepare($query, $ids));
        
        // query returns number of rows affected or false on error
        return $result === false ? 0 : $result; 
    }
    
    /**
     * Check if email already exists
     *
     * @param string $email Email address
     * @param int|null $exclude_id Exclude this ID from check (for updates)
     * @return bool True if exists, false otherwise
     */
    public function email_exists($email, $exclude_id = null) {
        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE email = %s";
        $values = array(sanitize_email($email));
        
        if ($exclude_id !== null) {
            $query .= " AND id != %d";
            $values[] = (int)$exclude_id;
        }
        
        $count = $this->wpdb->get_var($this->wpdb->prepare($query, $values));
        
        return $count > 0;
    }
    
    /**
     * Get total subscriber count, optionally filtered by status and search.
     *
     * @param string $status Optional status filter ('confirmed', 'pending', etc.)
     * @param string $search Optional search term.
     * @return int Count
     */
    // --- START FIX: Use 'status' column and handle search ---
    public function count($status = '', $search = '') {
        $where = array('1=1');
        $values = array();

        // Status filter
        if (!empty($status)) {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        // Search filter
        if (!empty($search)) {
            $where[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)';
            $search_term = '%' . $this->wpdb->esc_like($search) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where);
        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        
        if (!empty($values)) {
             $query = $this->wpdb->prepare($query, $values);
        }
        
        return (int)$this->wpdb->get_var($query);
    }
    // --- END FIX ---
    
    /**
     * Get subscriber statistics
     *
     * @return array Statistics array (Matches keys expected by dashboard.php)
     */
    // --- START FIX: Use 'status' column ---
    public function get_stats() {
        return array(
            'total' => $this->count(),
            'active' => $this->count('confirmed'),      // 'active' is 'confirmed'
            'unconfirmed' => $this->count('pending')   // 'unconfirmed' is 'pending'
        );
    }
    // --- END FIX ---
    
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
     * Generate unsubscribe keys for subscribers that don't have them
     * Useful for migration from older versions
     *
     * @return int Number of subscribers updated
     */
    public function generate_missing_keys() {
        // Find subscribers without keys
        $subscribers = $this->wpdb->get_results(
            "SELECT id FROM {$this->table_name} WHERE unsubscribe_key = '' OR unsubscribe_key IS NULL"
        );

        $updated = 0;
        foreach ($subscribers as $subscriber) {
            $key = wp_generate_password(32, false, false);
            $result = $this->wpdb->update(
                $this->table_name,
                array('unsubscribe_key' => $key),
                array('id' => $subscriber->id),
                array('%s'),
                array('%d')
            );

            if ($result !== false) {
                $updated++;
            }
        }

        return $updated;
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
    
    /**
     * Import subscribers from array (Handles DB interaction)
     *
     * @param array $subscribers Array of subscriber data from CSV
     * @param bool $skip_duplicates Whether to skip duplicate emails
     * @return array Result with success (bool), message (string), and counts
     */
    public function import_subscribers($subscribers, $skip_duplicates = true) {
        $counts = array(
            'success' => 0,
            'failed' => 0,
            'duplicates' => 0,
            'errors' => array() 
        );
        
        foreach ($subscribers as $index => $subscriber) {
            $email = $subscriber['Email'] ?? null;
            $full_name = $subscriber['Name'] ?? '';
            
            if (empty($email) || !is_email($email)) {
                 $counts['failed']++;
                 $counts['errors'][] = "Row " . ($index + 2) . ": Missing or invalid email.";
                 continue;
            }

            if ($this->email_exists($email)) {
                if ($skip_duplicates) {
                    $counts['duplicates']++;
                    continue;
                } else {
                     $counts['failed']++;
                     $counts['errors'][] = "Row " . ($index + 2) . ": Email {$email} already exists.";
                     continue;
                }
            }
            
            $first_name = '';
            $last_name = '';
            if (!empty($full_name)) {
                $name_parts = explode(' ', trim($full_name), 2);
                $first_name = $name_parts[0];
                if (isset($name_parts[1])) {
                    $last_name = $name_parts[1];
                }
            }
            
            $create_data = [
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'status' => $subscriber['status'] ?? 'confirmed', 
                'created_at' => $subscriber['created_at'] ?? current_time('mysql'),
            ];

            $subscriber_id = $this->create($create_data);
            
            if ($subscriber_id) {
                $counts['success']++;
            } else {
                $counts['failed']++;
                 $counts['errors'][] = "Row " . ($index + 2) . ": Database error creating {$email}.";
            }
        }
        
        $messages = [];
        if ($counts['success'] > 0) {
            $messages[] = $counts['success'] . ' ' . ($counts['success'] > 1 ? 'subscribers imported successfully.' : 'subscriber imported successfully.');
        }
        if ($counts['duplicates'] > 0) {
            $messages[] = $counts['duplicates'] . ' ' . ($counts['duplicates'] > 1 ? 'duplicate emails were skipped.' : 'duplicate email was skipped.');
        }
        if ($counts['failed'] > 0) {
            $messages[] = $counts['failed'] . ' ' . ($counts['failed'] > 1 ? 'rows failed to import.' : 'row failed to import.');
            // Add specific errors if desired: $messages[] = 'Details: ' . implode('; ', $counts['errors']);
        }
        
        if (empty($messages)) {
            $messages[] = 'No subscribers were imported.';
        }
        
        $is_success = $counts['failed'] === 0;
        
        return [
            'success' => $is_success,
            'message' => implode(' ', $messages),
            'counts'  => $counts
        ];
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
            'fields' => array('email', 'first_name', 'last_name', 'status', 'created_at') // Removed updated_at as it wasn't shown
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Get all subscribers (no pagination)
        $result = $this->get_all(array(
            'page' => 1,
            'per_page' => -1, // Use -1 or a very large number to get all
            'status' => $args['status']
        ));
        
        $export_data = array();
        
        if (!empty($result['subscribers'])) {
            foreach ($result['subscribers'] as $subscriber) {
                $row = array();
                foreach ($args['fields'] as $field) {
                    if (property_exists($subscriber, $field)) {
                         $row[$field] = $subscriber->$field;
                    } else {
                         $row[$field] = ''; 
                    }
                }
                $export_data[] = $row;
            }
        }
        
        return $export_data;
    }
}