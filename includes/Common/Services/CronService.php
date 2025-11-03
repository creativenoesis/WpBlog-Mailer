<?php
/**
 * Cron Service
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Common\Services;

/**
 * CronService Class
 * 
 * Handles WordPress cron job scheduling and management
 */
class CronService {

    /**
     * Cron hook names
     */
    const HOOK_SEND_EMAILS = 'wpbm_send_scheduled_emails';
    const HOOK_PROCESS_QUEUE = 'wpbm_process_email_queue';
    const HOOK_CLEANUP = 'wpbm_cleanup_old_data';
    const HOOK_WEEKLY_REPORT = 'wpbm_send_weekly_report';

    /**
     * Initialize cron service
     * Registers custom intervals - should be called early
     *
     * @return void
     */
    public static function init() {
        self::register_custom_intervals();
    }

    /**
     * Schedule all cron jobs
     *
     * @return void
     */
    public static function schedule() {
        // Ensure custom intervals are registered
        self::register_custom_intervals();

        // Send scheduled emails (when new post published) - Available in all tiers
        if (!wp_next_scheduled(self::HOOK_SEND_EMAILS)) {
            wp_schedule_event(time(), 'hourly', self::HOOK_SEND_EMAILS);
        }

        // Process email queue (Starter+ feature ONLY)
        if (function_exists('wpbm_is_starter') && wpbm_is_starter()) {
            if (!wp_next_scheduled(self::HOOK_PROCESS_QUEUE)) {
                wp_schedule_event(time(), 'every_five_minutes', self::HOOK_PROCESS_QUEUE);
            }
        }

        // Cleanup old data (weekly) - Available in all tiers
        if (!wp_next_scheduled(self::HOOK_CLEANUP)) {
            wp_schedule_event(time(), 'weekly', self::HOOK_CLEANUP);
        }

        // Send weekly analytics report (Pro feature ONLY)
        if (function_exists('wpbm_is_pro') && wpbm_is_pro()) {
            if (!wp_next_scheduled(self::HOOK_WEEKLY_REPORT)) {
                // Schedule for Monday at 9:00 AM by default
                $settings = get_option('wpbm_weekly_report_settings', []);
                $send_day = isset($settings['send_day']) ? $settings['send_day'] : 'monday';
                $send_time = isset($settings['send_time']) ? $settings['send_time'] : '09:00';

                // Calculate next occurrence
                $next_run = strtotime("next {$send_day} {$send_time}");
                if ($next_run === false) {
                    $next_run = strtotime("next monday 09:00");
                }

                wp_schedule_event($next_run, 'weekly', self::HOOK_WEEKLY_REPORT);
            }
        }
    }
    
    /**
     * Clear all cron jobs
     *
     * @return void
     */
    public static function clear() {
        wp_clear_scheduled_hook(self::HOOK_SEND_EMAILS);
        wp_clear_scheduled_hook(self::HOOK_PROCESS_QUEUE);
        wp_clear_scheduled_hook(self::HOOK_CLEANUP);
        wp_clear_scheduled_hook(self::HOOK_WEEKLY_REPORT);
    }

    /**
     * Schedule all cron jobs (alias for schedule)
     * Used by plugin activation hook
     *
     * @return void
     */
    public static function schedule_events() {
        self::schedule();
    }

    /**
     * Clear all cron jobs (alias for clear)
     * Used by plugin deactivation hook
     *
     * @return void
     */
    public static function unschedule_events() {
        self::clear();
    }

    /**
     * Register custom cron intervals
     *
     * @return void
     */
    private static function register_custom_intervals() {
        add_filter('cron_schedules', function($schedules) {
            // Every 5 minutes
            $schedules['every_five_minutes'] = array(
                'interval' => 300,
                'display'  => __('Every 5 Minutes', 'wpblogmailer')
            );
            
            // Every 15 minutes
            $schedules['every_fifteen_minutes'] = array(
                'interval' => 900,
                'display'  => __('Every 15 Minutes', 'wpblogmailer')
            );
            
            return $schedules;
        });
    }
}