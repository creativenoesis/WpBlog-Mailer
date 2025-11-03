<?php
// FILE: includes/Common/Database/Database.php

namespace WPBlogMailer\Common\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Database Abstraction Layer.
 *
 * Handles all direct database interactions for the plugin.
 */
class Database {

    /**
     * @var \wpdb
     */
    // --- FIX: Ensure this is 'public' ---
    public $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get a subscriber by their email.
     *
     * @param string $email
     * @return object|null
     */
    public function get_subscriber_by_email( $email ) {
        // Assuming Schema::TABLE_SUBSCRIBERS is defined elsewhere
        $table = $this->wpdb->prefix . Schema::TABLE_SUBSCRIBERS;
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE email = %s",
                $email
            )
        );
    }

    /**
     * Get all subscribers with pagination.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get_subscribers( $limit = 20, $offset = 0 ) {
         // Assuming Schema::TABLE_SUBSCRIBERS is defined elsewhere
        $table = $this->wpdb->prefix . Schema::TABLE_SUBSCRIBERS;
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    /**
     * Get the total count of subscribers.
     *
     * @return int
     */
    public function get_subscribers_count() {
         // Assuming Schema::TABLE_SUBSCRIBERS is defined elsewhere
        $table = $this->wpdb->prefix . Schema::TABLE_SUBSCRIBERS;
        return (int) $this->wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );
    }

    /**
     * Insert a new subscriber.
     *
     * @param array $data (e.g., ['email' => '...', 'first_name' => '...'])
     * @return int|false The new subscriber ID or false on error.
     */
    public function insert_subscriber( $data ) {
         // Assuming Schema::TABLE_SUBSCRIBERS is defined elsewhere
        $table = $this->wpdb->prefix . Schema::TABLE_SUBSCRIBERS;
        
        // Use the actual columns from SubscriberService->create
        $formats = [
            'email'      => '%s',
            'first_name' => '%s',
            'last_name'  => '%s',
            'status'     => '%s',
            'created_at' => '%s',
            'updated_at' => '%s', // Added updated_at
        ];
        
        // Filter data to only include keys present in formats
        $filtered_data = array_intersect_key($data, $formats);
        
        // Ensure timestamps if not provided
        $current_time = current_time( 'mysql', true );
        if ( empty( $filtered_data['created_at'] ) ) {
            $filtered_data['created_at'] = $current_time;
        }
         if ( empty( $filtered_data['updated_at'] ) ) {
            $filtered_data['updated_at'] = $current_time;
        }

        // Filter formats to match the data being inserted
        $filtered_formats = array_intersect_key($formats, $filtered_data);


        $result = $this->wpdb->insert(
            $table,
            $filtered_data,
            array_values($filtered_formats) // Pass formats as a simple array
        );

        return $result ? $this->wpdb->insert_id : false;
    }
    
    /**
     * Update a subscriber's details.
     *
     * @param int $subscriber_id
     * @param array $data
     * @return bool|int False on error, number of rows updated otherwise.
     */
    public function update_subscriber( $subscriber_id, $data ) {
         // Assuming Schema::TABLE_SUBSCRIBERS is defined elsewhere
        $table = $this->wpdb->prefix . Schema::TABLE_SUBSCRIBERS;

         // Use the actual columns from SubscriberService->update
        $formats = [
            'email'      => '%s',
            'first_name' => '%s',
            'last_name'  => '%s',
            'status'     => '%s',
            'updated_at' => '%s',
        ];

        // Filter data to only include keys present in formats
        $filtered_data = array_intersect_key($data, $formats);

        // Always add updated_at timestamp
        $filtered_data['updated_at'] = current_time('mysql', true);
        
        // Filter formats to match the data being updated
        $filtered_formats = array_intersect_key($formats, $filtered_data);
        
        $result = $this->wpdb->update(
            $table,
            $filtered_data,
            [ 'id' => $subscriber_id ], // WHERE
            array_values($filtered_formats), // Data formats
            [ '%d' ]  // WHERE format
        );

        return $result; // Returns number of rows updated or false
    }

    // --- Other query methods like delete, etc., would go here ---
    // Make sure they align with the methods actually used in SubscriberService

}