/**
 * Common Admin JavaScript for WP Blog Mailer
 * * Contains shared utilities used across all admin pages,
 * such as modal wrappers, notice handlers, and spinners.
 * * Extracted from admin.js, starter-admin.js, and pro-admin.js.
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // --- COMMON MODAL HANDLING ---
        // Generic close buttons
        $(document).on('click', '.wpbm-modal-close, .wpbm-modal-close-button', function() {
            $(this).closest('.wpbm-modal').fadeOut(200);
            $('body').css('overflow', 'auto'); // Ensure body scroll is restored
        });

        // Close modal by clicking on the overlay
        $(document).on('click', '.wpbm-modal', function(e) {
            if (e.target === this) {
                $(this).fadeOut(200);
                $('body').css('overflow', 'auto');
            }
        });

        // Close modal with ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('.wpbm-modal:visible').length) {
                $('.wpbm-modal:visible').fadeOut(200);
                $('body').css('overflow', 'auto');
            }
        });
        
        // --- COMMON NOTICE DISMISS ---
        // Handle dismiss clicks for dynamically added notices
        $(document).on('click', '.wpbm-notice .notice-dismiss', function() {
            $(this).closest('.wpbm-notice').fadeOut(200, function() { 
                $(this).remove(); 
            });
        });

    });

    // --- COMMON HELPER FUNCTIONS ---
    
    /**
     * Reusable function to show an admin notice.
     * This is the master version from admin.js.
     * * @param {string} message The message to display.
     * @param {string} type 'success' or 'error'.
     */
    window.wpbmShowNotice = function(message, type) {
        $('.wpbm-notice').remove();
        const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        const icon = type === 'error' ? 'dashicons-dismiss' : 'dashicons-yes-alt';
        
        const $notice = $(
            '<div class="wpbm-notice notice ' + noticeClass + ' is-dismissible">' +
            '<p><span class="dashicons ' + icon + '" style="font-size:16px;"></span> ' + message + '</p>' +
            '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
            '</div>'
        );
        
        // Insert after the main h1 tag
        $('.wrap h1').first().after($notice);
        
        // Auto-dismiss after 5 seconds
        const noticeTimeout = setTimeout(function() {
            $notice.fadeOut(300, function() { $(this).remove(); });
        }, 5000);

        // Clear timeout if dismissed manually
        $notice.on('click', '.notice-dismiss', function() {
            clearTimeout(noticeTimeout);
            // The click handler in document.ready will handle the fadeOut.
        });
    }

    // --- COMMON ASSETS ---
    
    // Add CSS for rotation animation used by spinners
    const style = document.createElement('style');
    style.textContent = '@keyframes wpbm-rotation { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
    document.head.appendChild(style);

})(jQuery);