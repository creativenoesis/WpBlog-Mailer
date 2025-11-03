<?php
// FILE: includes/Common/Database/Schema.php

namespace WPBlogMailer\Common\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Handles Plugin Database Schema Creation and Updates.
 */
class Schema {

    // Define table names as constants
    const TABLE_SUBSCRIBERS = 'wpbm_subscribers';
    const TABLE_SEND_HISTORY = 'wpbm_send_history'; // Added based on BasicAnalytics
    const TABLE_ANALYTICS_LOG = 'wpbm_analytics_log'; // Added based on AdvancedAnalytics
    const TABLE_ANALYTICS_LINKS = 'wpbm_analytics_links'; // Added based on AdvancedAnalytics
    const TABLE_TEMPLATES = 'wpbm_templates'; // Added for custom templates storage
    const TABLE_EMAIL_QUEUE = 'wpbm_email_queue'; // Email queue for background processing
    const TABLE_SEND_LOG = 'wpbm_send_log'; // Detailed send log for error tracking
    const TABLE_CRON_LOG = 'wpbm_cron_log'; // Cron execution log for monitoring
    const TABLE_TAGS = 'wpbm_tags'; // Tags for subscriber segmentation
    const TABLE_SUBSCRIBER_TAGS = 'wpbm_subscriber_tags'; // Many-to-many relationship between subscribers and tags

    /**
     * Create or update the necessary database tables.
     *
     * This method should be called during plugin activation.
     */
    public function create_tables() {
        // **FIX 1: Make WordPress DB object available**
        global $wpdb;

        // **FIX 2: Ensure dbDelta function is available**
        // This file is often not loaded by default during activation
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // **FIX 3: Get charset/collate AFTER $wpdb is defined**
        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix; // Use $wpdb->prefix

        // --- Subscribers Table ---
        $table_name_subscribers = $table_prefix . self::TABLE_SUBSCRIBERS;
        $sql_subscribers = "CREATE TABLE {$table_name_subscribers} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            first_name varchar(50) DEFAULT '' NOT NULL,
            last_name varchar(50) DEFAULT '' NOT NULL,
            status varchar(20) DEFAULT 'pending' NOT NULL,
            unsubscribe_key varchar(64) DEFAULT '' NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY unsubscribe_key (unsubscribe_key)
        ) {$charset_collate};";
        // **FIX 4: Call dbDelta() correctly**
        dbDelta( $sql_subscribers );

        // --- Send History Table (For Starter Tier Analytics) ---
        $table_name_history = $table_prefix . self::TABLE_SEND_HISTORY;
        $sql_history = "CREATE TABLE {$table_name_history} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email_subject varchar(255) DEFAULT '' NOT NULL,
            recipient_count int(11) DEFAULT 0 NOT NULL,
            sent_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            status varchar(20) DEFAULT 'completed' NOT NULL,
            PRIMARY KEY  (id)
        ) {$charset_collate};";
        dbDelta( $sql_history );

        // --- Analytics Log Table (For Pro Tier) ---
        $table_name_analytics_log = $table_prefix . self::TABLE_ANALYTICS_LOG;
        $sql_analytics_log = "CREATE TABLE {$table_name_analytics_log} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email_id bigint(20) UNSIGNED NOT NULL,
            subscriber_id mediumint(9) NOT NULL,
            event_type varchar(10) NOT NULL,
            link_id bigint(20) UNSIGNED DEFAULT NULL,
            event_timestamp datetime NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY email_id (email_id),
            KEY subscriber_id (subscriber_id),
            KEY event_type (event_type),
            KEY link_id (link_id)
        ) {$charset_collate};";
        dbDelta( $sql_analytics_log );


        // --- Analytics Links Table (For Pro Tier Click Tracking) ---
        $table_name_analytics_links = $table_prefix . self::TABLE_ANALYTICS_LINKS;
        $sql_analytics_links = "CREATE TABLE {$table_name_analytics_links} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            original_url text NOT NULL,
            url_hash char(32) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY url_hash (url_hash)
        ) {$charset_collate};";
        dbDelta( $sql_analytics_links );

        // --- Templates Table (For Pro Tier Custom Templates) ---
        $table_name_templates = $table_prefix . self::TABLE_TEMPLATES;
        $sql_templates = "CREATE TABLE {$table_name_templates} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            content longtext NOT NULL,
            template_type varchar(50) DEFAULT 'custom' NOT NULL,
            category varchar(50) DEFAULT 'newsletter' NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY template_type (template_type),
            KEY category (category)
        ) {$charset_collate};";
        dbDelta( $sql_templates );

        // --- Email Queue Table (For Background Email Processing) ---
        $table_name_queue = $table_prefix . self::TABLE_EMAIL_QUEUE;
        $sql_queue = "CREATE TABLE {$table_name_queue} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            recipient_email varchar(100) NOT NULL,
            subscriber_id mediumint(9) DEFAULT NULL,
            subject varchar(255) NOT NULL,
            message longtext NOT NULL,
            headers text DEFAULT NULL,
            template_type varchar(50) DEFAULT 'basic' NOT NULL,
            campaign_type varchar(50) DEFAULT 'newsletter' NOT NULL,
            status varchar(20) DEFAULT 'pending' NOT NULL,
            priority int(11) DEFAULT 5 NOT NULL,
            attempts int(11) DEFAULT 0 NOT NULL,
            max_attempts int(11) DEFAULT 3 NOT NULL,
            error_message text DEFAULT NULL,
            scheduled_for datetime NOT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY scheduled_for (scheduled_for),
            KEY priority (priority),
            KEY subscriber_id (subscriber_id),
            KEY campaign_type (campaign_type)
        ) {$charset_collate};";
        dbDelta( $sql_queue );

        // --- Send Log Table (Detailed email send tracking with errors) ---
        $table_name_send_log = $table_prefix . self::TABLE_SEND_LOG;
        $sql_send_log = "CREATE TABLE {$table_name_send_log} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            recipient_email varchar(100) NOT NULL,
            recipient_name varchar(100) DEFAULT '' NOT NULL,
            subscriber_id mediumint(9) DEFAULT NULL,
            subject varchar(255) NOT NULL,
            template_type varchar(50) DEFAULT 'basic' NOT NULL,
            campaign_type varchar(50) DEFAULT 'newsletter' NOT NULL,
            status varchar(20) DEFAULT 'success' NOT NULL,
            error_message text DEFAULT NULL,
            sent_at datetime NOT NULL,
            queue_id bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY recipient_email (recipient_email),
            KEY subscriber_id (subscriber_id),
            KEY status (status),
            KEY sent_at (sent_at),
            KEY campaign_type (campaign_type),
            KEY queue_id (queue_id)
        ) {$charset_collate};";
        dbDelta( $sql_send_log );

        // --- Cron Log Table (For cron execution monitoring and health checks) ---
        $table_name_cron_log = $table_prefix . self::TABLE_CRON_LOG;
        $sql_cron_log = "CREATE TABLE {$table_name_cron_log} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            hook varchar(255) NOT NULL,
            status varchar(50) NOT NULL,
            message text DEFAULT NULL,
            details longtext DEFAULT NULL,
            executed_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY hook (hook),
            KEY status (status),
            KEY executed_at (executed_at)
        ) {$charset_collate};";
        dbDelta( $sql_cron_log );

        // --- Tags Table (For subscriber segmentation - Pro feature) ---
        $table_name_tags = $table_prefix . self::TABLE_TAGS;
        $sql_tags = "CREATE TABLE {$table_name_tags} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            description text DEFAULT NULL,
            color varchar(7) DEFAULT '#0073aa' NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY name (name)
        ) {$charset_collate};";
        dbDelta( $sql_tags );

        // --- Subscriber Tags Table (Many-to-many relationship - Pro feature) ---
        $table_name_subscriber_tags = $table_prefix . self::TABLE_SUBSCRIBER_TAGS;
        $sql_subscriber_tags = "CREATE TABLE {$table_name_subscriber_tags} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subscriber_id mediumint(9) NOT NULL,
            tag_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY subscriber_tag (subscriber_id, tag_id),
            KEY subscriber_id (subscriber_id),
            KEY tag_id (tag_id)
        ) {$charset_collate};";
        dbDelta( $sql_subscriber_tags );

        // Store a version number for future migrations
        update_option('wpbm_db_version', '2.5'); // Update this when schema changes
    }

    /**
     * Optional: Method to handle database schema updates/migrations.
     * This could compare the stored version with the current plugin version.
     */
    public function check_updates() {
        $current_db_version = get_option('wpbm_db_version', '1.0'); // Default to 1.0 if not set

        // If DB version is less than 2.5, run create_tables to add new tables/columns
        if (version_compare($current_db_version, '2.5', '<')) {
            $this->create_tables();
        }

        // Example: Add alter table statements for future versions
        // if (version_compare($current_db_version, '2.6', '<')) {
        //     global $wpdb;
        //     $table_name = $wpdb->prefix . self::TABLE_SUBSCRIBERS;
        //     $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN source VARCHAR(50) DEFAULT 'unknown' NOT NULL;");
        //     update_option('wpbm_db_version', '2.6');
        // }
    }

} // End Class