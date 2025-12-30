// assets/js/poke-hub-user-profiles-admin.js

(function($) {
    'use strict';

    $(document).ready(function() {
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

        // Remove existing error messages
        function removeFriendCodeError() {
            $('#friend_code').removeClass('error');
            $('#friend_code').closest('td').find('.description.error').remove();
        }

        // Show error message for friend code
        function showFriendCodeError(message) {
            var $td = $('#friend_code').closest('td');
            $('#friend_code').addClass('error');
            if ($td.find('.description.error').length === 0) {
                $td.find('.description').after('<p class="description error" style="color: #dc3232;">' + message + '</p>');
            } else {
                $td.find('.description.error').text(message);
            }
        }

        // Validate friend code on input (for real-time feedback)
        $(document).on('blur', '#friend_code', function() {
            var $input = $(this);
            var value = $input.val();
            
            removeFriendCodeError();
            
            // Only validate if something was entered
            if (value.trim() !== '') {
                if (!validateFriendCode(value)) {
                    showFriendCodeError('The friend code must be exactly 12 digits (e.g., 1234 5678 9012)');
                }
            }
        });

        // Validate on form submit
        $(document).on('submit', '#poke-hub-profile-admin-form', function(e) {
            var $friendCodeInput = $('#friend_code');
            var friendCodeValue = $friendCodeInput.val();
            
            // Only validate if friend code is provided
            if (friendCodeValue.trim() !== '') {
                if (!validateFriendCode(friendCodeValue)) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    removeFriendCodeError();
                    showFriendCodeError('The friend code must be exactly 12 digits (e.g., 1234 5678 9012)');
                    
                    // Scroll to error
                    $('html, body').animate({
                        scrollTop: $friendCodeInput.offset().top - 100
                    }, 500);
                    
                    $friendCodeInput.focus();
                    return false;
                }
            }
            
            removeFriendCodeError();
            return true;
        });
    });

})(jQuery);

