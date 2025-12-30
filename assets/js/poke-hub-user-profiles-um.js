/**
 * Front-end JavaScript for User Profiles Ultimate Member integration
 * Handles checkbox styling and interactions using generic classes
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Update checkbox styling when checked/unchecked
        function updateCheckboxStyle($checkbox) {
            var $item = $checkbox.closest('.me5rine-lab-form-checkbox-item');
            if ($item.length === 0) {
                return; // Element not found
            }
            
            if ($checkbox.is(':checked')) {
                $item.addClass('checked');
                // Update icon to checked
                $item.find('.me5rine-lab-form-checkbox-icon i').removeClass('um-icon-android-checkbox-outline-blank').addClass('um-icon-android-checkbox');
            } else {
                $item.removeClass('checked');
                // Update icon to unchecked
                $item.find('.me5rine-lab-form-checkbox-icon i').removeClass('um-icon-android-checkbox').addClass('um-icon-android-checkbox-outline-blank');
            }
        }

        // Initialize checkbox states on page load
        $('.me5rine-lab-form-checkbox-item input[type="checkbox"]').each(function() {
            updateCheckboxStyle($(this));
        });

        // Update checkbox styling on change (using generic classes)
        $(document).on('change', '.me5rine-lab-form-checkbox-item input[type="checkbox"]', function() {
            updateCheckboxStyle($(this));
        });

        // Format friend code while typing (add spaces every 4 digits)
        function formatFriendCode(value) {
            // Remove all non-digit characters
            var cleaned = value.replace(/[^0-9]/g, '');
            
            // Limit to 12 digits maximum
            if (cleaned.length > 12) {
                cleaned = cleaned.substring(0, 12);
            }
            
            // Add space every 4 digits
            var formatted = cleaned.match(/.{1,4}/g);
            if (formatted) {
                return formatted.join(' ');
            }
            return cleaned;
        }

        // Calculate new cursor position after formatting
        function getNewCursorPosition(oldValue, newValue, oldPosition) {
            // Count digits before cursor in old value
            var digitsBefore = (oldValue.substring(0, oldPosition).match(/\d/g) || []).length;
            
            // Find position in new value where we have the same number of digits before
            var digitCount = 0;
            for (var i = 0; i < newValue.length; i++) {
                if (/\d/.test(newValue[i])) {
                    digitCount++;
                    if (digitCount === digitsBefore) {
                        return i + 1;
                    }
                }
            }
            return newValue.length;
        }

        // Handle friend code input formatting
        $(document).on('input', '#friend_code', function() {
            var $input = $(this);
            var input = $input[0];
            var cursorPosition = input.selectionStart || 0;
            var value = $input.val();
            var formatted = formatFriendCode(value);
            
            // Only update if formatting changed
            if (formatted !== value) {
                $input.val(formatted);
                
                // Restore cursor position
                var newCursorPosition = getNewCursorPosition(value, formatted, cursorPosition);
                input.setSelectionRange(newCursorPosition, newCursorPosition);
            }
        });

        // Also handle paste event
        $(document).on('paste', '#friend_code', function(e) {
            var $input = $(this);
            setTimeout(function() {
                var value = $input.val();
                var formatted = formatFriendCode(value);
                if (formatted !== value) {
                    $input.val(formatted);
                }
            }, 0);
        });

        // Format XP while typing (add spaces every 3 digits, French format)
        function formatXP(value) {
            // Remove all non-digit characters
            var cleaned = value.replace(/[^0-9]/g, '');
            
            // Add space every 3 digits from right to left (French format)
            var formatted = cleaned.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            return formatted;
        }

        // Calculate new cursor position after XP formatting
        function getNewCursorPositionXP(oldValue, newValue, oldPosition) {
            // Count digits before cursor in old value
            var digitsBefore = (oldValue.substring(0, oldPosition).match(/\d/g) || []).length;
            
            // Find position in new value where we have the same number of digits before
            var digitCount = 0;
            for (var i = 0; i < newValue.length; i++) {
                if (/\d/.test(newValue[i])) {
                    digitCount++;
                    if (digitCount === digitsBefore) {
                        return i + 1;
                    }
                }
            }
            return newValue.length;
        }

        // Handle XP input formatting
        $(document).on('input', '#xp', function() {
            var $input = $(this);
            var input = $input[0];
            var cursorPosition = input.selectionStart || 0;
            var value = $input.val();
            var formatted = formatXP(value);
            
            // Only update if formatting changed
            if (formatted !== value) {
                $input.val(formatted);
                
                // Restore cursor position
                var newCursorPosition = getNewCursorPositionXP(value, formatted, cursorPosition);
                input.setSelectionRange(newCursorPosition, newCursorPosition);
            }
        });

        // Also handle paste event for XP
        $(document).on('paste', '#xp', function(e) {
            var $input = $(this);
            setTimeout(function() {
                var value = $input.val();
                var formatted = formatXP(value);
                if (formatted !== value) {
                    $input.val(formatted);
                }
            }, 0);
        });

        // Validate friend code (must be exactly 12 digits)
        function validateFriendCode(value) {
            // Remove all non-digit characters
            var cleaned = value.replace(/[^0-9]/g, '');
            return cleaned.length === 12;
        }

        // Show warning message in the notification block at the top
        function showFormWarningMessage(message) {
            var $container = $('.me5rine-lab-form-container');
            if ($container.length === 0) {
                return;
            }
            
            // Remove all existing messages (success, error, warning) before the container
            $container.prevAll('.me5rine-lab-form-message').remove();
            
            // Create warning message with ID
            var $warningMsg = $('<div id="poke-hub-profile-message" class="me5rine-lab-form-message me5rine-lab-form-message-warning"><p>' + message + '</p></div>');
            
            // Insert before the container
            $container.before($warningMsg);
            
            // Scroll to the message element by ID, accounting for fixed header
            // Use requestAnimationFrame to ensure DOM is updated before scrolling
            requestAnimationFrame(function() {
                var $messageEl = $('#poke-hub-profile-message');
                if ($messageEl.length > 0 && $messageEl.is(':visible')) {
                    var msgOffset = $messageEl.offset();
                    if (msgOffset && msgOffset.top !== undefined) {
                        // Get header height based on screen size
                        var headerHeight = 129; // Default: PC (desktop)
                        var windowWidth = $(window).width();
                        
                        if (windowWidth <= 768) {
                            // Mobile
                            headerHeight = 95;
                        } else if (windowWidth <= 1024) {
                            // Tablet
                            headerHeight = 123;
                        }
                        // else: Desktop (129px, default)
                        
                        // Add some padding (20px) plus header height
                        var scrollOffset = msgOffset.top - headerHeight - 20;
                        
                        // Stop any existing scroll animations first
                        $('html, body').stop(true, false);
                        
                        // Single smooth scroll animation
                        $('html, body').animate({
                            scrollTop: scrollOffset
                        }, 500, 'swing');
                    }
                }
            });
        }

        // Remove warning messages
        function removeFormWarningMessage() {
            $('.me5rine-lab-form-message-warning').remove();
        }

        // Validate friend code on input (for real-time feedback) - just visual feedback on field
        $(document).on('blur', '#friend_code', function() {
            var $input = $(this);
            var value = $input.val();
            
            // Remove visual error class
            $input.removeClass('error');
            
            // Only validate if something was entered (just visual feedback, no blocking)
            if (value.trim() !== '') {
                if (!validateFriendCode(value)) {
                    $input.addClass('error');
                }
            }
        });

        // Validate on form submit
        $(document).on('submit', '#poke-hub-profile-form', function(e) {
            var $form = $(this);
            var $friendCodeInput = $('#friend_code');
            
            // Remove any existing warning messages
            removeFormWarningMessage();
            
            // Check if friend code field exists
            if ($friendCodeInput.length === 0) {
                // Field doesn't exist, allow submission
                return true;
            }
            
            var friendCodeValue = $friendCodeInput.val();
            
            // Only validate if friend code is provided
            if (friendCodeValue.trim() !== '') {
                if (!validateFriendCode(friendCodeValue)) {
                    // Prevent form submission
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    
                    // Show warning message in notification block
                    showFormWarningMessage('The friend code must be exactly 12 digits (e.g., 1234 5678 9012)');
                    
                    // Also add visual feedback on the field
                    $friendCodeInput.addClass('error');
                    setTimeout(function() {
                        $friendCodeInput.focus();
                    }, 400);
                    
                    return false;
                }
            }
            
            // Validation passed, remove any error classes
            $friendCodeInput.removeClass('error');
            // Allow form submission to proceed normally
            return true;
        });
    });

})(jQuery);

