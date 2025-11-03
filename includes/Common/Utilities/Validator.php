<?php
/**
 * Utility: Validator
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Common\Utilities; // Correct namespace

/**
 * Validator Class
 * Basic data validation utility. (Can be expanded later)
 */
class Validator {
    
    /**
     * Validate an email address.
     *
     * @param string $email
     * @return bool
     */
    public function is_email($email) {
        return is_email($email) !== false;
    }
    
    /**
     * Check if a value is not empty.
     *
     * @param string $value
     * @return bool
     */
    public function is_not_empty($value) {
        return !empty(trim($value));
    }
}