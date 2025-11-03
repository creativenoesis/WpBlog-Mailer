/**
 * Subscribe Form JavaScript
 * 
 * Handles frontend form interactions
 * 
 * @package WP_Blog_Mailer
 * @since 2.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        WPBM_SubscribeForm.init();
    });

    const WPBM_SubscribeForm = {
        
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('.wpbm-subscribe-form').on('submit', this.handleSubmit.bind(this));
        },

        handleSubmit: function(e) {
            const $form = $(e.target);
            const $button = $form.find('.wpbm-button');
            
            // Prevent double submission
            if ($button.hasClass('loading')) {
                e.preventDefault();
                return false;
            }
            
            // Add loading state
            $button.addClass('loading').prop('disabled', true);
            
            // Form will submit normally - no AJAX needed as we're using POST
            // The loading state will be visible until page reloads
        }
    };

})(jQuery);
