/**
 * Consolidated Analytics JavaScript for WP Blog Mailer
 * * Merges functionality from:
 * - assets/js/custom-analytics.js (Modal logic)
 * - assets/js/pro-admin.js (Dashboard, date-picker, filtering, AJAX logic)
 */

(function($) {
    'use strict';
    
    // Make function global for onclick access from tables
    window.wpbmLoadCampaignDetails = function(campaignId) {
        loadCampaignDetails(campaignId);
    };
    
    $(document).ready(function() {
        
        // --- Initialize Modal ---
        // From custom-analytics.js
        initModal();
        
        // --- Initialize Advanced Analytics Dashboard ---
        // From pro-admin.js
        
        // Date range picker
        $('#analytics-date-from, #analytics-date-to').on('change', function() {
            var dateFrom = $('#analytics-date-from').val();
            var dateTo = $('#analytics-date-to').val();
            
            if (dateFrom && dateTo) {
                loadAnalyticsData(dateFrom, dateTo);
            }
        });

        // Table filtering (Search)
        $('#analytics-filter-search').on('keyup', function() {
            var searchTerm = $(this).val().toLowerCase();
            
            $('.wpbm-analytics-table tbody tr').each(function() {
                var rowText = $(this).text().toLowerCase();
                $(this).toggle(rowText.indexOf(searchTerm) > -1);
            });
        });
        
        // Table filtering (Status)
        $('#analytics-filter-status').on('change', function() {
            var filterStatus = $(this).val();
            
            $('.wpbm-analytics-table tbody tr').each(function() {
                if (filterStatus === 'all') {
                    $(this).show();
                } else {
                    var rowStatus = $(this).find('.status-badge').data('status');
                    $(this).toggle(rowStatus === filterStatus);
                }
            });
        });
        
        // Export analytics data
        $('#export-analytics').on('click', function(e) {
            e.preventDefault();
            
            var dateFrom = $('#analytics-date-from').val();
            var dateTo = $('#analytics-date-to').val();
            
            // Assumes wpbmPro (or a new wpbmAnalytics) object is localized
            if (typeof wpbmPro === 'undefined' || typeof wpbmPro.ajax_url === 'undefined') {
                 // Fallback or error if wpbmPro is not defined. 
                 // For the new structure, you should localize a 'wpbmAnalytics' object.
                 // Let's assume wpbmCustomAnalytics is localized for the modal,
                 // and wpbmPro was for the dashboard. We'll need to unify this.
                 // For now, we'll try to use wpbmPro if it exists.
                 
                 // NOTE: You MUST localize a single object, e.g., 'wpbmAnalytics'
                 // with ajax_url, nonce, and strings.
                 
                 // This code block is just for compatibility during merge.
                 var ajaxUrl = (typeof wpbmPro !== 'undefined') ? wpbmPro.ajax_url : wpbmCustomAnalytics.ajax_url;
                 var nonce = (typeof wpbmPro !== 'undefined') ? wpbmPro.nonce : wpbmCustomAnalytics.nonce;
                 
                 var downloadUrl = ajaxUrl + '?action=wpbm_export_analytics&nonce=' + nonce;
                 
                 if (dateFrom) downloadUrl += '&date_from=' + dateFrom;
                 if (dateTo) downloadUrl += '&date_to=' + dateTo;
            
                 window.location.href = downloadUrl;
            } else {
                 var downloadUrl = wpbmPro.ajax_url + '?action=wpbm_export_analytics&nonce=' + wpbmPro.nonce;
        
                if (dateFrom) downloadUrl += '&date_from=' + dateFrom;
                if (dateTo) downloadUrl += '&date_to=' + dateTo;
                
                window.location.href = downloadUrl;
            }
        });

    });
    
    /**
     * Initialize modal functionality
     * From: custom-analytics.js
     */
    function initModal() {
        // Close modal on X click
        $('.wpbm-modal-close').on('click', function() {
            closeModal();
        });
        
        // Close modal on outside click
        $(window).on('click', function(event) {
            if ($(event.target).is('#wpbm-campaign-modal')) {
                closeModal();
            }
        });
        
        // Close modal on ESC key
        $(document).on('keydown', function(event) {
            if (event.key === 'Escape' && $('#wpbm-campaign-modal').is(':visible')) {
                closeModal();
            }
        });
    }
    
    /**
     * Load campaign details via AJAX
     * From: custom-analytics.js
     */
    function loadCampaignDetails(campaignId) {
        // Show modal with loading state
        openModal();
        
        // Reset content to loading state
        $('#wpbm-campaign-details-content').html(
            '<div class="wpbm-loading" style="text-align: center; padding: 40px;">' +
            '<span class="spinner is-active" style="float: none;"></span><br>' +
            wpbmCustomAnalytics.strings.loading +
            '</div>'
        );
        
        // Load data
        $.ajax({
            url: wpbmCustomAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'wpbm_get_custom_campaign_details',
                nonce: wpbmCustomAnalytics.nonce,
                campaign_id: campaignId
            },
            success: function(response) {
                if (response.success) {
                    $('#wpbm-campaign-details-content').html(response.data.html);
                } else {
                    showError(response.data.message || wpbmCustomAnalytics.strings.error);
                }
            },
            error: function(xhr, status, error) {
                showError(wpbmCustomAnalytics.strings.error + ': ' + error);
            }
        });
    }
    
    /**
     * Open modal
     * From: custom-analytics.js
     */
    function openModal() {
        $('#wpbm-campaign-modal').fadeIn(200);
        $('body').css('overflow', 'hidden');
    }
    
    /**
     * Close modal
     * From: custom-analytics.js
     */
    function closeModal() {
        $('#wpbm-campaign-modal').fadeOut(200);
        $('body').css('overflow', 'auto');
    }
    
    /**
     * Show error message in modal
     * From: custom-analytics.js
     */
    function showError(message) {
        $('#wpbm-campaign-details-content').html(
            '<div class="notice notice-error" style="margin: 20px 0;">' +
            '<p><strong>Error:</strong> ' + message + '</p>' +
            '</div>'
        );
    }
    
    /**
     * Load analytics data for dashboard
     * From: pro-admin.js
     */
    function loadAnalyticsData(dateFrom, dateTo) {
        var $container = $('.wpbm-analytics-container');
        
        // Show loading state
        $container.css('opacity', '0.5');
        
        // NOTE: You must localize 'wpbmAnalytics' with ajax_url and nonce
        $.ajax({
            url: wpbmAnalytics.ajax_url, // Using unified object
            type: 'POST',
            data: {
                action: 'wpbm_get_analytics_data',
                nonce: wpbmAnalytics.nonce, // Using unified object
                date_from: dateFrom,
                date_to: dateTo
            },
            success: function(response) {
                if (response.success) {
                    updateAnalyticsDisplay(response.data);
                    $container.css('opacity', '1');
                } else {
                    showNotification(response.data.message || wpbmAnalytics.strings.error, 'error');
                    $container.css('opacity', '1');
                }
            },
            error: function() {
                showNotification(wpbmAnalytics.strings.error, 'error');
                $container.css('opacity', '1');
            }
        });
    }
    
    /**
     * Update analytics display on dashboard
     * From: pro-admin.js
     */
    function updateAnalyticsDisplay(data) {
        // Update stat cards
        $('#stat-total-sent').text(data.total_sent || 0);
        $('#stat-total-opened').text(data.total_opened || 0);
        $('#stat-total-clicked').text(data.total_clicked || 0);
        $('#stat-open-rate').text((data.open_rate || 0) + '%');
        $('#stat-click-rate').text((data.click_rate || 0) + '%');
        
        // Update charts if chart library is loaded
        if (typeof Chart !== 'undefined') {
            updateCharts(data);
        }
    }
    
    /**
     * Update charts (stub)
     * From: pro-admin.js
     */
    function updateCharts(data) {
        // This is where your Chart.js update logic would go
        // e.g.,
        // if (data.open_rate_trend && $('#open-rate-chart').length) {
        //     // Update chart...
        // }
    }
    
    /**
     * Show notification
     * From: pro-admin.js (utility)
     */
    function showNotification(message, type) {
        type = type || 'success';
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($notice);
        
        setTimeout(function() {
            $notice.fadeOut(400, function() { $(this).remove(); });
        }, 3000);
    }
    
})(jQuery);