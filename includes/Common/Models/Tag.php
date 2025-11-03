<?php
/**
 * Tag Model
 * Handles all database operations for tags and tag-subscriber relationships
 *
 * @package WP_Blog_Mailer
 * @subpackage Common\Models
 * @since 2.1.0
 */

namespace WPBlogMailer\Common\Models;

if (!defined('ABSPATH')) exit;

class Tag {

    /**
     * Database table names
     *
     * @var string
     */
    private $table_name;
    private $subscriber_tags_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wpbm_tags';
        $this->subscriber_tags_table = $wpdb->prefix . 'wpbm_subscriber_tags';
    }

    /**
     * Get tag by ID
     *
     * @param int $id Tag ID
     * @return object|null Tag object or null
     */
    public function find($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }

    /**
     * Get tag by slug
     *
     * @param string $slug Tag slug
     * @return object|null Tag object or null
     */
    public function find_by_slug($slug) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE slug = %s",
            sanitize_title($slug)
        ));
    }

    /**
     * Get all tags with filtering and pagination
     *
     * @param array $args Query arguments
     * @return array Array with tags, total, total_pages, current_page
     */
    public function get_all($args = array()) {
        global $wpdb;

        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'search' => '',
            'orderby' => 'name',
            'order' => 'ASC',
            'offset' => null
        );

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where = array('1=1');
        $where_values = array();

        // Search filter
        if (!empty($args['search'])) {
            $where[] = '(name LIKE %s OR description LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
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
        $allowed_orderby = array('id', 'name', 'slug', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'name';

        // Validate order
        $order = in_array(strtoupper($args['order']), array('ASC', 'DESC')) ? strtoupper($args['order']) : 'ASC';

        // Build main query
        $query = "SELECT * FROM {$this->table_name}
                  WHERE {$where_clause}
                  ORDER BY {$orderby} {$order}
                  LIMIT %d OFFSET %d";

        $query_values = array_merge($where_values, array($args['per_page'], $offset));
        $tags = $wpdb->get_results($wpdb->prepare($query, $query_values));

        return array(
            'tags' => $tags,
            'total' => $total,
            'total_pages' => $total_pages,
            'current_page' => $args['page']
        );
    }

    /**
     * Create a new tag
     *
     * @param array $data Tag data
     * @return int|false Tag ID on success, false on failure
     */
    public function create($data) {
        global $wpdb;

        $current_time = current_time('mysql');

        // Generate slug from name if not provided
        $slug = isset($data['slug']) && !empty($data['slug'])
            ? sanitize_title($data['slug'])
            : sanitize_title($data['name']);

        // Ensure unique slug
        $original_slug = $slug;
        $counter = 1;
        while ($this->slug_exists($slug)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        $insert_data = array(
            'name' => sanitize_text_field($data['name']),
            'slug' => $slug,
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'color' => isset($data['color']) ? sanitize_hex_color($data['color']) : '#0073aa',
            'created_at' => $current_time,
            'updated_at' => $current_time,
        );

        $result = $wpdb->insert(
            $this->table_name,
            $insert_data,
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update an existing tag
     *
     * @param int $id Tag ID
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public function update($id, $data) {
        global $wpdb;

        $update_data = array();
        $format = array();

        // Name
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }

        // Slug
        if (isset($data['slug'])) {
            $slug = sanitize_title($data['slug']);
            // Ensure unique slug (excluding current tag)
            if ($this->slug_exists($slug, $id)) {
                return false; // Slug already exists
            }
            $update_data['slug'] = $slug;
            $format[] = '%s';
        }

        // Description
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $format[] = '%s';
        }

        // Color
        if (isset($data['color'])) {
            $update_data['color'] = sanitize_hex_color($data['color']);
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
     * Delete a tag and all its associations
     *
     * @param int $id Tag ID
     * @return bool True on success, false on failure
     */
    public function delete($id) {
        global $wpdb;

        // First, delete all subscriber-tag associations
        $wpdb->delete(
            $this->subscriber_tags_table,
            array('tag_id' => (int)$id),
            array('%d')
        );

        // Then delete the tag itself
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => (int)$id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Check if slug already exists
     *
     * @param string $slug Tag slug
     * @param int|null $exclude_id Exclude this ID from check (for updates)
     * @return bool True if exists, false otherwise
     */
    public function slug_exists($slug, $exclude_id = null) {
        global $wpdb;

        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE slug = %s";
        $values = array(sanitize_title($slug));

        if ($exclude_id !== null) {
            $query .= " AND id != %d";
            $values[] = (int)$exclude_id;
        }

        $count = $wpdb->get_var($wpdb->prepare($query, $values));

        return $count > 0;
    }

    /**
     * Get total tag count
     *
     * @return int Count
     */
    public function count() {
        global $wpdb;

        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }

    // --- Tag-Subscriber Relationship Methods ---

    /**
     * Assign a tag to a subscriber
     *
     * @param int $subscriber_id Subscriber ID
     * @param int $tag_id Tag ID
     * @return int|false Relationship ID on success, false on failure
     */
    public function assign_to_subscriber($subscriber_id, $tag_id) {
        global $wpdb;

        // Check if relationship already exists
        if ($this->subscriber_has_tag($subscriber_id, $tag_id)) {
            return false; // Already assigned
        }

        $result = $wpdb->insert(
            $this->subscriber_tags_table,
            array(
                'subscriber_id' => (int)$subscriber_id,
                'tag_id' => (int)$tag_id,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s')
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Remove a tag from a subscriber
     *
     * @param int $subscriber_id Subscriber ID
     * @param int $tag_id Tag ID
     * @return bool True on success, false on failure
     */
    public function remove_from_subscriber($subscriber_id, $tag_id) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->subscriber_tags_table,
            array(
                'subscriber_id' => (int)$subscriber_id,
                'tag_id' => (int)$tag_id
            ),
            array('%d', '%d')
        );

        return $result !== false;
    }

    /**
     * Check if subscriber has a specific tag
     *
     * @param int $subscriber_id Subscriber ID
     * @param int $tag_id Tag ID
     * @return bool True if has tag, false otherwise
     */
    public function subscriber_has_tag($subscriber_id, $tag_id) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->subscriber_tags_table}
             WHERE subscriber_id = %d AND tag_id = %d",
            $subscriber_id,
            $tag_id
        ));

        return $count > 0;
    }

    /**
     * Get all tags for a subscriber
     *
     * @param int $subscriber_id Subscriber ID
     * @return array Array of tag objects
     */
    public function get_subscriber_tags($subscriber_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.* FROM {$this->table_name} t
             INNER JOIN {$this->subscriber_tags_table} st ON t.id = st.tag_id
             WHERE st.subscriber_id = %d
             ORDER BY t.name ASC",
            $subscriber_id
        ));
    }

    /**
     * Get all subscribers with a specific tag
     *
     * @param int $tag_id Tag ID
     * @param string $status Filter by subscriber status (optional)
     * @return array Array of subscriber IDs
     */
    public function get_tagged_subscribers($tag_id, $status = '') {
        global $wpdb;
        $subscribers_table = $wpdb->prefix . 'wpbm_subscribers';

        $query = "SELECT s.id FROM {$subscribers_table} s
                  INNER JOIN {$this->subscriber_tags_table} st ON s.id = st.subscriber_id
                  WHERE st.tag_id = %d";

        $values = array($tag_id);

        if (!empty($status)) {
            $query .= " AND s.status = %s";
            $values[] = $status;
        }

        return $wpdb->get_col($wpdb->prepare($query, $values));
    }

    /**
     * Get subscriber count for a tag
     *
     * @param int $tag_id Tag ID
     * @param string $status Filter by subscriber status (optional)
     * @return int Subscriber count
     */
    public function get_tag_subscriber_count($tag_id, $status = '') {
        global $wpdb;
        $subscribers_table = $wpdb->prefix . 'wpbm_subscribers';

        $query = "SELECT COUNT(*) FROM {$subscribers_table} s
                  INNER JOIN {$this->subscriber_tags_table} st ON s.id = st.subscriber_id
                  WHERE st.tag_id = %d";

        $values = array($tag_id);

        if (!empty($status)) {
            $query .= " AND s.status = %s";
            $values[] = $status;
        }

        return (int)$wpdb->get_var($wpdb->prepare($query, $values));
    }

    /**
     * Bulk assign tags to subscribers
     *
     * @param array $subscriber_ids Array of subscriber IDs
     * @param int $tag_id Tag ID
     * @return int Number of assignments made
     */
    public function bulk_assign($subscriber_ids, $tag_id) {
        $count = 0;

        foreach ($subscriber_ids as $subscriber_id) {
            if ($this->assign_to_subscriber($subscriber_id, $tag_id)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Bulk remove tags from subscribers
     *
     * @param array $subscriber_ids Array of subscriber IDs
     * @param int $tag_id Tag ID
     * @return int Number of removals made
     */
    public function bulk_remove($subscriber_ids, $tag_id) {
        global $wpdb;

        if (empty($subscriber_ids)) {
            return 0;
        }

        $subscriber_ids = array_map('intval', $subscriber_ids);
        $placeholders = implode(',', array_fill(0, count($subscriber_ids), '%d'));

        $query = "DELETE FROM {$this->subscriber_tags_table}
                  WHERE tag_id = %d AND subscriber_id IN ({$placeholders})";

        $values = array_merge(array($tag_id), $subscriber_ids);

        $result = $wpdb->query($wpdb->prepare($query, $values));

        return $result !== false ? $result : 0;
    }
}
