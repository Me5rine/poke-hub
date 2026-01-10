/**
 * Friend Codes JavaScript
 */
(function($) {
    'use strict';
    
    // Ensure DOM is ready
    $(document).ready(function() {
        // Log initialization for debugging
        if (typeof console !== 'undefined' && console.log) {
            console.log('Friend Codes JS initialized');
        }
    });

    // Format friend code input (add spaces every 4 digits)
    $(document).on('input', '#friend_code', function() {
        var $input = $(this);
        var value = $input.val().replace(/\s/g, ''); // Remove existing spaces
        var formatted = '';
        
        for (var i = 0; i < value.length && i < 12; i++) {
            if (i > 0 && i % 4 === 0) {
                formatted += ' ';
            }
            formatted += value[i];
        }
        
        $input.val(formatted);
    });

    // Copy friend code to clipboard
    $(document).on('click', '.poke-hub-friend-code-copy', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $button = $(this);
        
        // Try multiple ways to get the code
        var code = $button.attr('data-code') || 
                   $button.data('code') || 
                   $button.closest('.me5rine-lab-card').find('.poke-hub-friend-code-value').attr('data-code') ||
                   $button.siblings('.poke-hub-friend-code-value').attr('data-code');
        
        // If still no code, try to get it from the text content
        if (!code) {
            var $valueSpan = $button.siblings('.poke-hub-friend-code-value');
            if ($valueSpan.length) {
                code = $valueSpan.text().replace(/\s/g, '');
            } else {
                // Try finding in parent container
                $valueSpan = $button.closest('div').find('.poke-hub-friend-code-value');
                if ($valueSpan.length) {
                    code = $valueSpan.attr('data-code') || $valueSpan.text().replace(/\s/g, '');
                }
            }
        }
        
        if (!code || code.length === 0) {
            if (typeof console !== 'undefined' && console.error) {
                console.error('Friend code not found. Button:', $button);
            }
            alert('Impossible de trouver le code ami à copier.');
            return false;
        }
        
        // Clean code (remove spaces and non-numeric characters)
        code = String(code).replace(/[^0-9]/g, '');
        
        if (code.length !== 12) {
            if (typeof console !== 'undefined' && console.error) {
                console.error('Invalid friend code length:', code, 'Expected 12 digits');
            }
            return false;
        }
        
        // Copy to clipboard
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(function() {
                showCopyFeedback($button);
            }).catch(function(err) {
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Clipboard API failed:', err);
                }
                fallbackCopyTextToClipboard(code, $button);
            });
        } else {
            fallbackCopyTextToClipboard(code, $button);
        }
        
        return false;
    });

    // Fallback copy method for older browsers
    function fallbackCopyTextToClipboard(text, $button) {
        var textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.top = '0';
        textArea.style.left = '0';
        textArea.style.width = '2em';
        textArea.style.height = '2em';
        textArea.style.padding = '0';
        textArea.style.border = 'none';
        textArea.style.outline = 'none';
        textArea.style.boxShadow = 'none';
        textArea.style.background = 'transparent';
        textArea.setAttribute('readonly', '');
        document.body.appendChild(textArea);
        
        // For iOS
        var range = document.createRange();
        textArea.contentEditable = true;
        textArea.readOnly = false;
        range.selectNodeContents(textArea);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        textArea.setSelectionRange(0, 999999);
        
        try {
            var successful = document.execCommand('copy');
            if (successful) {
                showCopyFeedback($button);
            } else {
                console.error('execCommand copy failed');
            }
        } catch (err) {
            console.error('Fallback: Unable to copy', err);
        }
        
        document.body.removeChild(textArea);
    }

    // Show copy feedback
    function showCopyFeedback($button) {
        var originalHtml = $button.html();
        var checkmarkSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        $button.html(checkmarkSvg).addClass('copied');
        
        setTimeout(function() {
            $button.html(originalHtml).removeClass('copied');
        }, 2000);
    }

    // Validate friend code on form submission
    $(document).on('submit', '.friend-codes-dashboard form', function(e) {
        var $form = $(this);
        var $friendCodeInput = $form.find('#friend_code');
        var friendCode = $friendCodeInput.val().replace(/\s/g, '');
        
        if (friendCode.length !== 12 || !/^\d+$/.test(friendCode)) {
            e.preventDefault();
            alert('Friend code must contain exactly 12 digits.');
            $friendCodeInput.focus();
            return false;
        }
        
        // Ensure cleaned code is stored (without spaces)
        // The server will clean it, but we can set a hidden field if needed
        return true;
    });

    // Auto-submit filter form when selects change (optional)
    // Uncomment if you want auto-filter on change
    /*
    $(document).on('change', '.poke-hub-friend-codes-filter-form select', function() {
        $(this).closest('form').submit();
    });
    */

})(jQuery);

