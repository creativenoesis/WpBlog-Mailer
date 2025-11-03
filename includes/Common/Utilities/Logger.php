<?php
/**
 * Utility: Logger
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Common\Utilities; // Correct namespace

/**
 * Logger Class
 * Basic logger utility. (Can be expanded later)
 */
class Logger {
    
    /**
     * Log an info message.
     *
     * @param string $message
     */
    public function info($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WPBM INFO] ' . $message);
        }
    }
    
    /**
     * Log an error message.
     *
     * @param string $message
     * @param \Exception $exception (Optional)
     */
    public function error($message, $exception = null) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $log_message = '[WPBM ERROR] ' . $message;
            if ($exception) {
                $log_message .= ' | Exception: ' . $exception->getMessage();
            }
            error_log($log_message);
        }
    }
}