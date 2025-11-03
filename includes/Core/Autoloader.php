<?php
/**
 * PSR-4 Autoloader for WP Blog Mailer
 *
 * @package WPBlogMailer
 * @since 2.0.0
 */

namespace WPBlogMailer\Core;

/**
 * Autoloader Class
 * 
 * Handles automatic loading of plugin classes following PSR-4 standard.
 * Falls back to manual loading if Composer autoloader is not available.
 */
class Autoloader {
    
    /**
     * Namespace prefix for the plugin
     *
     * @var string
     */
    private $namespace_prefix = 'WPBlogMailer\\';
    
    /**
     * Base directory for the namespace prefix
     *
     * @var string
     */
    private $base_directory;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->base_directory = plugin_dir_path(dirname(dirname(__FILE__)));
    }
    
    /**
     * Register the autoloader
     *
     * @return void
     */
    public function register() {
        spl_autoload_register(array($this, 'autoload'));
    }
    
    /**
     * Autoload classes
     *
     * @param string $class The fully-qualified class name.
     * @return void
     */
    public function autoload($class) {
        // Check if the class uses our namespace
        $len = strlen($this->namespace_prefix);
        if (strncmp($this->namespace_prefix, $class, $len) !== 0) {
            // Not our namespace, let other autoloaders handle it
            return;
        }
        
        // Get the relative class name
        $relative_class = substr($class, $len);
        
        // Replace namespace separators with directory separators
        // Replace underscores with hyphens (WordPress convention)
        $file = $this->base_directory . 'includes/' . str_replace('\\', '/', $relative_class) . '.php';
        
        // If the file exists, require it
        if (file_exists($file)) {
            require_once $file;
        }
    }
    
    /**
     * Initialize Composer autoloader if available
     *
     * @return bool True if Composer autoloader was loaded, false otherwise
     */
    public static function init_composer() {
        $composer_autoload = plugin_dir_path(dirname(dirname(__FILE__))) . 'vendor/autoload.php';
        
        if (file_exists($composer_autoload)) {
            require_once $composer_autoload;
            return true;
        }
        
        return false;
    }
    
    /**
     * Load all autoloaders (Composer + Custom)
     *
     * @return void
     */
    public static function init() {
        // Try to load Composer autoloader first
        $composer_loaded = self::init_composer();
        
        // If Composer is not available, use our custom autoloader
        if (!$composer_loaded) {
            $autoloader = new self();
            $autoloader->register();
        }
    }
}