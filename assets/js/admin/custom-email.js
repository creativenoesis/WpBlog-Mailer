/**
 * Consolidated Custom Email JavaScript
 * * Merges functionality from:
 * - assets/js/custom-email.js (Drafts, modal preview, char counter, v1 logic)
 * - assets/js/custom-email-enhanced.js (Template selection, AJAX load, v2 logic)
 */

(function($) {
    'use strict';
    
    let selectedTemplate = 'blank';
    let autoSaveTimeout;
    
    $(document).ready(function() {
        
        // --- From custom-email-enhanced.js ---
        initTemplateSelection();
        initNewWindowPreview();
        
        // --- From custom-email.js ---
        initEditorToggle();
        initModalPreview();
        initFormSubmission();
        initAutoSave();
        initCharCounter();
        
        // Check for draft after a short delay to ensure TinyMCE is ready
        setTimeout(restoreDraft, 500);
        
        // Handle success/error messages from URL
        handleUrlNotices();
    });
    
    // ==========================================
    // INITIALIZATION FUNCTIONS
    // ==========================================
    
    /**
     * Initialize template selection cards
     * From: custom-email-enhanced.js
     */
    function initTemplateSelection() {
        $('.wpbm-template-card').on('click', function() {
            const templateId = $(this).data('template');
            
            $('.wpbm-template-card').removeClass('selected');
            $(this).addClass('selected');
            
            $('#selected_template').val(templateId);
            selectedTemplate = templateId;
            
            if (templateId !== 'blank') {
                loadTemplate(templateId);
            } else {
                // Clear editors for blank template
                if (typeof tinymce !== 'undefined' && tinymce.get('email_content_visual')) {
                    tinymce.get('email_content_visual').setContent('');
                }
                $('#email_content_html').val('');
                $('#email_subject').val('');
            }
        });
    }
    
    /**
     * Initialize editor toggle (Visual/HTML)
     * From: custom-email.js (More robust version)
     */
    function initEditorToggle() {
        $('.wpbm-toggle-btn').on('click', function() {
            var editorType = $(this).data('editor');
            
            $('.wpbm-toggle-btn').removeClass('active');
            $(this).addClass('active');
            
            if (editorType === 'visual') {
                $('#visual-editor-container').show();
                $('#html-editor-container').hide();
                
                // Sync content from HTML to Visual
                var htmlContent = $('#email_content_html').val();
                if (htmlContent && typeof tinymce !== 'undefined' && tinymce.get('email_content_visual')) {
                    tinymce.get('email_content_visual').setContent(htmlContent);
                }
            } else {
                $('#visual-editor-container').hide();
                $('#html-editor-container').show();
                
                // Sync content from Visual to HTML
                if (typeof tinymce !== 'undefined' && tinymce.get('email_content_visual')) {
                    var visualContent = tinymce.get('email_content_visual').getContent();
                    $('#email_content_html').val(visualContent);
                }
            }
        });
    }
    
    /**
     * Initialize IN-PAGE MODAL preview
     * From: custom-email.js
     */
    function initModalPreview() {
        $('#wpbm-preview-email').on('click', function() {
            var subject = $('#email_subject').val();
            var content = getActiveEditorContent();
            
            if (!subject || !content) {
                alert('Please enter both subject and content before previewing.');
                return;
            }
            
            // Replace template variables with sample data
            // NOTE: You MUST localize 'wpbmCustomEmail' with siteInfo and strings
            var previewContent = content
                .replace(/\{\{subscriber_name\}\}/g, 'John Doe')
                .replace(/\{\{subscriber_email\}\}/g, 'john@example.com')
                .replace(/\{\{site_name\}\}/g, wpbmCustomEmail.siteInfo?.name || 'Your Site')
                .replace(/\{\{site_url\}\}/g, wpbmCustomEmail.siteInfo?.url || window.location.origin)
                .replace(/\{\{unsubscribe_url\}\}/g, '#unsubscribe');
            
            // Show preview modal
            $('#wpbm-preview-subject-text').text(subject);
            $('#wpbm-preview-content').html(previewContent);
            $('#wpbm-preview-modal').fadeIn(200);
        });
        
        // Close modal
        $('.wpbm-modal-close, .wpbm-modal-overlay').on('click', function() {
            $('#wpbm-preview-modal').fadeOut(200);
        });
        
        // Prevent modal close when clicking inside modal content
        $('.wpbm-modal-content').on('click', function(e) {
            e.stopPropagation();
        });
    }

    /**
     * Initialize NEW WINDOW preview
     * From: custom-email-enhanced.js
     */
    function initNewWindowPreview() {
        $('#preview-email-btn').on('click', function(e) { // Assumes a different button ID
            e.preventDefault();
            
            const subject = $('#email_subject').val();
            let content = getActiveEditorContent();
            
            if (!subject || !content) {
                alert('Please enter both subject and content before previewing.');
                return;
            }
            
            // Open preview in new window
            const previewWindow = window.open('', 'Email Preview', 'width=800,height=600');
            previewWindow.document.write('<html><head><title>Email Preview</title></head><body>');
            previewWindow.document.write('<h2>' + subject + '</h2>');
            previewWindow.document.write('<hr>');
            previewWindow.document.write(content);
            previewWindow.document.write('</body></html>');
            previewWindow.document.close();
        });
    }
    
    /**
     * Initialize form submission confirmation and spinner
     * From: custom-email.js
     */
    function initFormSubmission() {
        $('#wpbm-custom-email-form, #wpbm-custom-email-form-v2').on('submit', function(e) {
            // Find the submit button, assuming one is #wpbm-send-custom
            var $submitButton = $('#wpbm-send-custom, #send-email-btn').first();
            var subscriberCount = $submitButton.text().match(/\d+/);
            var count = subscriberCount ? subscriberCount[0] : 0;
            
            var confirmMessage = (wpbmCustomEmail || wpbmCustomEmailV2).strings.confirmSend;
            confirmMessage = confirmMessage.replace('%d', count);
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            $submitButton
                .prop('disabled', true)
                .html('<span class="dashicons dashicons-update dashicons-spin"></span> ' + (wpbmCustomEmail || wpbmCustomEmailV2).strings.sending);
            
            $('.wpbm-custom-email-page').addClass('wpbm-sending');
        });
    }
    
    /**
     * Initialize auto-save draft functionality
     * From: custom-email.js
     */
    function initAutoSave() {
        $('#email_subject').on('input', triggerAutoSave);
        
        if (typeof tinymce !== 'undefined') {
            tinymce.on('AddEditor', function(e) {
                if (e.editor.id === 'email_content_visual') {
                    e.editor.on('input change', triggerAutoSave);
                }
            });
        }
        
        $('#email_content_html').on('input', triggerAutoSave);
    }
    
    /**
     * Initialize subject line character counter
     * From: custom-email.js
     */
    function initCharCounter() {
        $('#email_subject').on('input', function() {
            var length = $(this).val().length;
            var counter = $(this).siblings('.wpbm-char-counter');
            
            if (counter.length === 0) {
                counter = $('<span class="wpbm-char-counter"></span>');
                $(this).after(counter);
            }
            
            counter.text(length + ' characters');
            
            if (length > 50) {
                counter.css('color', '#d63638');
            } else {
                counter.css('color', '#666');
            }
        }).trigger('input'); // Trigger on load
    }
    
    // ==========================================
    // HELPER FUNCTIONS
    // ==========================================
    
    /**
     * Load template content via AJAX
     * From: custom-email-enhanced.js
     */
    function loadTemplate(templateId) {
        const $loadingMsg = $('<div class="notice notice-info"><p>' + wpbmCustomEmailV2.strings.loadingTemplate + '</p></div>');
        $('.wpbm-email-composer').prepend($loadingMsg);
        
        $.ajax({
            url: wpbmCustomEmailV2.ajax_url,
            type: 'POST',
            data: {
                action: 'wpbm_load_template',
                nonce: wpbmCustomEmailV2.nonce,
                template_id: templateId
            },
            success: function(response) {
                if (response.success) {
                    $('#email_subject').val(response.data.subject).trigger('input'); // Trigger char count
                    
                    const content = response.data.content;
                    
                    if ($('#html-editor-container').is(':visible')) {
                        $('#email_content_html').val(content);
                    } else {
                        if (typeof tinymce !== 'undefined' && tinymce.get('email_content_visual')) {
                            tinymce.get('email_content_visual').setContent(content);
                        } else {
                            $('#email_content_visual').val(content);
                        }
                    }
                    
                    $loadingMsg.removeClass('notice-info').addClass('notice-success').find('p').text('Template loaded successfully!');
                    
                    setTimeout(function() {
                        $loadingMsg.fadeOut(function() { $(this).remove(); });
                    }, 2000);
                } else {
                    $loadingMsg.removeClass('notice-info').addClass('notice-error').find('p').text('Error: ' + (response.data.message || 'Failed to load template'));
                }
            },
            error: function() {
                $loadingMsg.removeClass('notice-info').addClass('notice-error').find('p').text('Error: Failed to load template');
            }
        });
    }
    
    /**
     * Get content from the currently active editor
     * @returns {string}
     */
    function getActiveEditorContent() {
        if ($('.wpbm-toggle-btn[data-editor="visual"]').hasClass('active')) {
            if (typeof tinymce !== 'undefined' && tinymce.get('email_content_visual')) {
                return tinymce.get('email_content_visual').getContent();
            }
        }
        return $('#email_content_html').val();
    }

    /**
     * Debounce for auto-save
     * From: custom-email.js
     */
    function triggerAutoSave() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(autoSave, 1500);
    }
    
    /**
     * Auto-save draft to localStorage
     * From: custom-email.js
     */
    function autoSave() {
        var subject = $('#email_subject').val();
        var content = getActiveEditorContent();
        
        if (subject || content) {
            localStorage.setItem('wpbm_custom_email_draft', JSON.stringify({
                subject: subject,
                content: content,
                timestamp: Date.now()
            }));
        }
    }
    
    /**
     * Restore draft from localStorage
     * From: custom-email.js
     */
    function restoreDraft() {
        var draft = localStorage.getItem('wpbm_custom_email_draft');
        
        if (draft) {
            try {
                draft = JSON.parse(draft);
                var hoursSinceSave = (Date.now() - draft.timestamp) / (1000 * 60 * 60);
                
                if (hoursSinceSave < 24) {
                    if (confirm('A draft email was found. Would you like to restore it?')) {
                        $('#email_subject').val(draft.subject).trigger('input'); // Trigger char count
                        
                        if (typeof tinymce !== 'undefined' && tinymce.get('email_content_visual')) {
                            tinymce.get('email_content_visual').setContent(draft.content);
                        }
                        $('#email_content_html').val(draft.content);
                    } else {
                        localStorage.removeItem('wpbm_custom_email_draft');
                    }
                } else {
                    localStorage.removeItem('wpbm_custom_email_draft');
                }
            } catch (e) {
                console.error('Error restoring draft:', e);
            }
        }
    }
    
    /**
     * Show admin notice
     * From: custom-email.js
     */
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wpbm-custom-email-page h1').after(notice);
        
        setTimeout(function() {
            notice.fadeOut(400, function() { $(this).remove(); });
        }, 5000);
        
        notice.on('click', '.notice-dismiss', function() {
            notice.fadeOut(400, function() { $(this).remove(); });
        });
    }
    
    /**
     * Show success/error messages from URL parameters
     * From: custom-email.js
     */
    function handleUrlNotices() {
        var urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.get('success') === 'sent') {
            var sent = urlParams.get('sent');
            var failed = urlParams.get('failed');
            var message = 'Email sent successfully to ' + sent + ' subscriber(s).';
            
            if (parseInt(failed) > 0) {
                message += ' ' + failed + ' failed.';
            }
            
            showNotice('success', message);
            
            // Clear form and draft
            $('#email_subject').val('').trigger('input');
            if (typeof tinymce !== 'undefined' && tinymce.get('email_content_visual')) {
                tinymce.get('email_content_visual').setContent('');
            }
            $('#email_content_html').val('');
            localStorage.removeItem('wpbm_custom_email_draft');
        }
        
        if (urlParams.get('error') === 'empty_fields') {
            showNotice('error', 'Please fill in all required fields.');
        }
        
        if (urlParams.get('error') === 'no_subscribers') {
            showNotice('error', 'No subscribers found. Please add subscribers before sending emails.');
        }
    }
    
})(jQuery);