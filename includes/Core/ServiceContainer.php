<?php
/**
 * Service Container for Dependency Injection (Free Version)
 *
 * @package WPBlogMailer
 * @since 1.0.0
 */

namespace WPBlogMailer\Core;

// Common Components
use WPBlogMailer\Common\Database\Database;
use WPBlogMailer\Common\Services\SubscriberService;
use WPBlogMailer\Common\Services\CronService;
use WPBlogMailer\Common\Services\TemplateService;
use WPBlogMailer\Common\Services\NewsletterService;
use WPBlogMailer\Common\Services\SendLogService;
use WPBlogMailer\Common\Utilities\Logger;
use WPBlogMailer\Common\Utilities\Validator;

// Free Components
use WPBlogMailer\Free\SubscribeForm;
use WPBlogMailer\Free\Controllers\SubscribersController;
use WPBlogMailer\Free\Services\BasicTemplateService;
use WPBlogMailer\Free\Services\EmailServiceFree;

defined( 'ABSPATH' ) || exit;

/**
 * Simple Service Container for Dependency Injection (Free Version Only)
 */
class ServiceContainer {

    private $services = [];
    private $definitions = [];

    public function __construct() {
        $this->register_services();
    }

    public function get( $id ) {
        if ( isset( $this->services[ $id ] ) ) {
            return $this->services[ $id ];
        }
        if ( ! isset( $this->definitions[ $id ] ) ) {
            throw new \Exception( "Service definition not found for ID: " . esc_html($id) );
        }
        try {
             $this->services[ $id ] = $this->definitions[ $id ]( $this );
        } catch (\Exception $e) {
             error_log("Error creating service '{$id}': " . $e->getMessage());
             throw new \Exception( "Error creating service '{$id}': " . $e->getMessage(), 0, $e );
        }

        return $this->services[ $id ];
    }

    public function set( $id, $callable ) {
        $this->definitions[ $id ] = $callable;
    }

    /**
     * Register all plugin services (Free Version Only)
     */
    private function register_services() {

        // --- CORE / UTILITIES ---
        $this->set( Database::class, function( $c ) {
            return new Database();
        });

        $this->set( Logger::class, function( $c ) {
            return new Logger();
        });

        $this->set( Validator::class, function( $c ) {
            return new Validator();
        });

        // --- EMAIL SERVICE (Free Only) ---
        $this->set( EmailServiceFree::class, function( $c ) {
            return new EmailServiceFree(
                $c->get( Logger::class ),
                $c->get( Validator::class )
            );
        } );

        // --- COMMON SERVICES ---
        $this->set( SubscriberService::class, function( $c ) {
            return new SubscriberService( $c->get( Database::class ) );
        } );

        $this->set( CronService::class, function( $c ) {
            return new CronService();
        } );

        $this->set( TemplateService::class, function( $c ) {
            return new TemplateService();
        } );

        // --- TEMPLATE SERVICES ---
        $this->set( BasicTemplateService::class, function( $c ) {
            return new BasicTemplateService( $c->get( TemplateService::class ) );
        } );

        // --- SEND LOG SERVICE ---
        $this->set( SendLogService::class, function( $c ) {
            return new SendLogService(
                $c->get( Database::class )
            );
        } );

        // --- NEWSLETTER SERVICE ---
        $this->set( NewsletterService::class, function( $c ) {
            return new NewsletterService(
                $c->get( SubscriberService::class ),
                $c->get( EmailServiceFree::class ),
                $c->get( BasicTemplateService::class ),
                $c->get( Logger::class ),
                null,  // No custom template service in free
                null,  // No template library in free
                null   // No email queue in free
            );
        } );

        // --- CONTROLLERS ---
        $this->set( SubscribersController::class, function( $c ) {
            return new SubscribersController( $c->get( SubscriberService::class ) );
        } );

        $this->set( SubscribeForm::class, function( $c ) {
            return new SubscribeForm(
                $c->get( SubscriberService::class ),
                $c->get( EmailServiceFree::class )
            );
        } );
    }
}
