/**
 * Front-end JavaScript for User Profiles Ultimate Member integration
 * Handles checkbox styling and interactions using generic classes
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Auto-detect country from IP if country field is empty (optional, non-blocking)
        // This runs on profile pages
        // Uses shared detection script: poke-hub-country-detection.js
        function autoDetectCountry() {
            var $countrySelect = $('#poke-hub-profile-form #country');
            if ($countrySelect.length === 0) {
                return; // No country field on this page
            }
            
            // Get saved country value (before auto-detection)
            var savedCountry = $countrySelect.val() || '';
            var $hiddenCountry = $countrySelect.siblings('input[name="country"][type="hidden"]');
            if ($hiddenCountry.length > 0 && savedCountry === '') {
                savedCountry = $hiddenCountry.val() || '';
            }
            
            // Check if field has a saved value (not auto-detected yet)
            var hasSavedValue = savedCountry !== '' && savedCountry !== '0';
            var isAutoDetected = $countrySelect.attr('data-auto-detected') === '1';
            
            // Only attempt auto-detection if field is empty or has placeholder value
            if (hasSavedValue && !isAutoDetected) {
                console.log('[PokeHub UM] Field already has saved value', savedCountry);
                // Check if saved country matches detected country
                checkCountryMismatch($countrySelect, savedCountry);
                return; // Field already has a value, but check for mismatch
            }
            
            // Check if already auto-detected to avoid multiple attempts
            if (isAutoDetected) {
                console.log('[PokeHub UM] Already auto-detected');
                return;
            }
            
            // Store saved country value for later comparison
            var savedCountryBeforeDetection = savedCountry || '';
            
            // Use shared detection function (from poke-hub-country-detection.js)
            if (typeof window.pokeHubDetectCountry !== 'function') {
                return; // Shared script not loaded
            }
            
            // Get country data (from cache or WordPress AJAX endpoint)
            window.pokeHubDetectCountry()
                .then(function(countryData) {
                    if (!countryData || (!countryData.code && !countryData.name)) {
                        return false;
                    }
                    
                    // Try to select the country by code or name (using shared function)
                    if (typeof window.pokeHubSelectCountry === 'function') {
                        return window.pokeHubSelectCountry($countrySelect, countryData.code, countryData.name);
                    }
                    return false;
                })
                .then(function(selected) {
                    if (!selected) {
                        return;
                    }
                    
                    var selectedValue = $countrySelect.val();
                    if (!selectedValue) {
                        return;
                    }
                    
                    // Check if saved country (before auto-detection) differs from detected country
                    if (savedCountryBeforeDetection && savedCountryBeforeDetection !== selectedValue) {
                        // Show warning message with option to update
                        showCountryMismatchWarning($countrySelect, savedCountryBeforeDetection, selectedValue);
                    }
                    
                    // Lock field and show indicator (using shared function)
                    if (typeof window.pokeHubLockCountryField === 'function') {
                        console.log('[PokeHub UM] Locking field');
                        window.pokeHubLockCountryField($countrySelect, selectedValue);
                    }
                    
                    // Apply filtering after country is auto-detected
                    var $form = $countrySelect.closest('form');
                    if ($form.length > 0) {
                        var $patternSelect = $form.find('#scatterbug_pattern');
                        if ($patternSelect.length > 0) {
                            // Save original options first if not already saved
                            if (!$patternSelect.data('original-options')) {
                                var originalPatternOptions = [];
                                $patternSelect.find('option').each(function() {
                                    originalPatternOptions.push({
                                        value: $(this).val(),
                                        text: $(this).text(),
                                        selected: $(this).prop('selected')
                                    });
                                });
                                $patternSelect.data('original-options', originalPatternOptions);
                            }
                            
                            // Apply filtering with delays
                            setTimeout(function() {
                                if (typeof filterVivillonOptions === 'function') {
                                    filterVivillonOptions($countrySelect, $patternSelect);
                                }
                            }, 300);
                            
                            setTimeout(function() {
                                if (typeof filterVivillonOptions === 'function') {
                                    filterVivillonOptions($countrySelect, $patternSelect);
                                }
                            }, 700);
                        }
                    }
                })
                .catch(function(error) {
                    // Silently fail
                });
        }
        
        // Run detection once after a delay to ensure Select2 is initialized
        setTimeout(function() { autoDetectCountry(); }, 500);
        
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
        
        // Ensure checkbox clicks work correctly when clicking on label
        // Prevent event bubbling issues that might interfere with checkbox clicks
        $(document).on('click', '.me5rine-lab-form-checkbox-item', function(e) {
            var $item = $(this);
            var $checkbox = $item.find('input[type="checkbox"]');
            
            // If clicking directly on the checkbox, let it handle normally
            if ($(e.target).is('input[type="checkbox"]')) {
                return;
            }
            
            // If clicking on the label (but not on the checkbox itself), toggle the checkbox
            if ($checkbox.length > 0) {
                // Prevent default to avoid double-toggle if the label is already associated
                e.preventDefault();
                // Toggle the checkbox state
                $checkbox.prop('checked', !$checkbox.prop('checked'));
                // Trigger change event to update styling
                $checkbox.trigger('change');
            }
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
            var $container = $('.me5rine-lab-profile-container');
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

        // Validate vivillon country/pattern combination (reuse from friend codes)
        function validateVivillonCountryPattern(country, pattern) {
            if (!country || !pattern) {
                return true; // If one is empty, don't validate
            }
            
            if (typeof pokeHubFriendCodes === 'undefined' || !pokeHubFriendCodes.vivillonMapping) {
                return true; // Mapping not available, allow submission
            }
            
            var mapping = pokeHubFriendCodes.vivillonMapping;
            var validPatterns = mapping[country];
            
            if (!validPatterns || validPatterns.length === 0) {
                return true; // No patterns defined for this country
            }
            
            return validPatterns.indexOf(pattern) !== -1;
        }
        
        // Show/hide validation error for country/pattern
        function showCountryPatternError($form, message) {
            var $container = $('.me5rine-lab-profile-container');
            if ($container.length === 0) {
                return;
            }
            
            // Remove existing error messages
            $container.prevAll('.me5rine-lab-form-message-error').remove();
            
            var $errorMsg = $('<div class="me5rine-lab-form-message me5rine-lab-form-message-error"><p>' + message + '</p></div>');
            $container.before($errorMsg);
            
            // Scroll to error
            setTimeout(function() {
                var msgOffset = $errorMsg.offset();
                if (msgOffset && msgOffset.top !== undefined) {
                    var headerHeight = $(window).width() <= 768 ? 95 : ($(window).width() <= 1024 ? 123 : 129);
                    $('html, body').animate({
                        scrollTop: msgOffset.top - headerHeight - 20
                    }, 500);
                }
            }, 100);
        }
        
        function hideCountryPatternError() {
            $('.me5rine-lab-form-message-error').remove();
        }
        
        // Filter options dynamically based on country/pattern selection
        function filterVivillonOptions($countrySelect, $patternSelect) {
            if (typeof pokeHubFriendCodes === 'undefined' || !pokeHubFriendCodes.vivillonMapping || !pokeHubFriendCodes.patternToCountriesMapping) {
                return; // Mapping not available
            }
            
            // Prevent infinite loops
            if ($countrySelect.data('filtering') || $patternSelect.data('filtering')) {
                return;
            }
            
            // Check if any Select2 dropdown is open - if so, defer the filtering
            var isCountryOpen = $countrySelect.data('select2') && $countrySelect.data('select2').isOpen();
            var isPatternOpen = $patternSelect.data('select2') && $patternSelect.data('select2').isOpen();
            
            if (isCountryOpen || isPatternOpen) {
                // Defer filtering until dropdowns are closed
                var checkInterval = setInterval(function() {
                    var stillCountryOpen = $countrySelect.data('select2') && $countrySelect.data('select2').isOpen();
                    var stillPatternOpen = $patternSelect.data('select2') && $patternSelect.data('select2').isOpen();
                    
                    if (!stillCountryOpen && !stillPatternOpen) {
                        clearInterval(checkInterval);
                        // Small delay to ensure Select2 has finished closing
                        setTimeout(function() {
                            filterVivillonOptions($countrySelect, $patternSelect);
                        }, 100);
                    }
                }, 50);
                
                // Clear interval after 5 seconds max to avoid infinite wait
                setTimeout(function() {
                    clearInterval(checkInterval);
                }, 5000);
                
                return;
            }
            
            $countrySelect.data('filtering', true);
            $patternSelect.data('filtering', true);
            
            try {
                // Get country value - check hidden input if field is disabled (auto-detected)
                var country = $countrySelect.val();
                if (!country || country === '' || country === '0') {
                    if ($countrySelect.prop('disabled') && $countrySelect.attr('data-auto-detected') === '1') {
                        var $hiddenCountry = $countrySelect.siblings('input[name="country"][type="hidden"]');
                        if ($hiddenCountry.length > 0) {
                            country = $hiddenCountry.val();
                        }
                    }
                }
                
                var pattern = $patternSelect.val();
                var currentPatternValue = $patternSelect.val();
                var currentCountryValue = country || $countrySelect.val();
                var isCountrySelect2 = $countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2');
                var isPatternSelect2 = $patternSelect.hasClass('select2-hidden-accessible') || $patternSelect.data('select2');
                
                // Store all original options for patterns (to restore when needed)
                // Only save once, check if already saved
                if (!$patternSelect.data('original-options')) {
                    var originalPatternOptions = [];
                    $patternSelect.find('option').each(function() {
                        originalPatternOptions.push({
                            value: $(this).val(),
                            text: $(this).text(),
                            selected: $(this).prop('selected')
                        });
                    });
                    $patternSelect.data('original-options', originalPatternOptions);
                }
                
                // Store all original options for countries (to restore when needed)
                // Only save once, check if already saved
                if (!$countrySelect.data('original-options')) {
                    var originalCountryOptions = [];
                    $countrySelect.find('option').each(function() {
                        originalCountryOptions.push({
                            value: $(this).val(),
                            text: $(this).text(),
                            selected: $(this).prop('selected')
                        });
                    });
                    $countrySelect.data('original-options', originalCountryOptions);
                }
            
            // Filter patterns based on selected country
            if (country) {
                var validPatterns = pokeHubFriendCodes.vivillonMapping[country] || [];
                var originalPatternOptions = $patternSelect.data('original-options') || [];
                var selectedPatternValue = currentPatternValue;
                
                // Clear all options except placeholder
                $patternSelect.find('option:not([value=""])').remove();
                
                // Always add placeholder first
                var $placeholder = $patternSelect.find('option[value=""]');
                if ($placeholder.length === 0) {
                    $patternSelect.prepend('<option value="">' + ($patternSelect.find('option').first().text() || '-- Select --') + '</option>');
                }
                
                // Add valid patterns
                originalPatternOptions.forEach(function(opt) {
                    if (!opt.value || opt.value === '' || opt.value === '0') {
                        return; // Skip placeholder, already added
                    }
                    
                    var isValid = validPatterns.length === 0 || validPatterns.indexOf(opt.value) !== -1;
                    if (isValid) {
                        var $newOption = $('<option>').val(opt.value).text(opt.text);
                        if (opt.value === selectedPatternValue) {
                            $newOption.prop('selected', true);
                        }
                        $patternSelect.append($newOption);
                    }
                });
                
                // If current pattern is not valid, clear selection
                if (selectedPatternValue && validPatterns.length > 0 && validPatterns.indexOf(selectedPatternValue) === -1) {
                    $patternSelect.val('');
                    selectedPatternValue = '';
                }
                
                // Reinitialize Select2 if it was initialized
                // But only if the dropdown is not currently open (to avoid closing it while user is selecting)
                if (isPatternSelect2) {
                    var isPatternOpen = $patternSelect.data('select2') && $patternSelect.data('select2').isOpen();
                    $patternSelect.select2('destroy');
                    // Reinitialize with allowClear to enable the clear button (X) only when a value is selected
                    var $parent = $patternSelect.closest('.me5rine-lab-form-field');
                    if (!$parent.length) {
                        $parent = $patternSelect.closest('.me5rine-lab-form-col');
                    }
                    if (!$parent.length) {
                        $parent = $patternSelect.closest('.me5rine-lab-form-section');
                    }
                    if (!$parent.length) {
                        $parent = $patternSelect.parent();
                    }
                    // Get placeholder text from empty option
                    var placeholderText = $patternSelect.find('option[value=""]').first().text() || 'Select...';
                    // Ensure empty option exists for placeholder
                    var $emptyOption = $patternSelect.find('option[value=""]').first();
                    if ($emptyOption.length === 0) {
                        $patternSelect.prepend('<option value="">' + placeholderText + '</option>');
                    }
                    $patternSelect.select2({
                        width: '100%',
                        allowClear: true,
                        placeholder: {
                            id: '',
                            text: placeholderText
                        },
                        dropdownParent: $parent.length ? $parent : $('body')
                    });
                    if (selectedPatternValue) {
                        $patternSelect.val(selectedPatternValue);
                    } else {
                        // Ensure empty value is set to show placeholder
                        $patternSelect.val('');
                    }
                    // If the dropdown was open, reopen it after reinitialization
                    if (isPatternOpen) {
                        setTimeout(function() {
                            $patternSelect.select2('open');
                        }, 10);
                    }
                }
            } else {
                // No country selected, restore all patterns
                var originalPatternOptions = $patternSelect.data('original-options') || [];
                if (originalPatternOptions.length > 0) {
                    $patternSelect.find('option').remove();
                    originalPatternOptions.forEach(function(opt) {
                        var $newOption = $('<option>').val(opt.value).text(opt.text);
                        if (opt.selected || (opt.value === currentPatternValue && currentPatternValue)) {
                            $newOption.prop('selected', true);
                        }
                        $patternSelect.append($newOption);
                    });
                    
                    if (isPatternSelect2) {
                        var isPatternOpen = $patternSelect.data('select2') && $patternSelect.data('select2').isOpen();
                        $patternSelect.select2('destroy');
                        // Reinitialize with allowClear to enable the clear button (X) only when a value is selected
                        var $parent = $patternSelect.closest('.me5rine-lab-form-field');
                        if (!$parent.length) {
                            $parent = $patternSelect.closest('.me5rine-lab-form-col');
                        }
                        if (!$parent.length) {
                            $parent = $patternSelect.closest('.me5rine-lab-form-section');
                        }
                        if (!$parent.length) {
                            $parent = $patternSelect.parent();
                        }
                        // Get placeholder text from empty option
                        var placeholderText = $patternSelect.find('option[value=""]').first().text() || 'Select...';
                        // Ensure empty option exists for placeholder
                        var $emptyOption = $patternSelect.find('option[value=""]').first();
                        if ($emptyOption.length === 0) {
                            $patternSelect.prepend('<option value="">' + placeholderText + '</option>');
                        }
                        $patternSelect.select2({
                            width: '100%',
                            allowClear: true,
                            placeholder: {
                                id: '',
                                text: placeholderText
                            },
                            dropdownParent: $parent.length ? $parent : $('body')
                        });
                        if (currentPatternValue) {
                            $patternSelect.val(currentPatternValue);
                        } else {
                            // Ensure empty value is set to show placeholder
                            $patternSelect.val('');
                        }
                        // If the dropdown was open, reopen it after reinitialization
                        if (isPatternOpen) {
                            setTimeout(function() {
                                $patternSelect.select2('open');
                            }, 10);
                        }
                    }
                }
            }
            
            // NOTE: We do NOT filter countries based on selected pattern anymore
            // The pattern selection should NOT affect which countries are available
            // Only the country selection filters the patterns (see above)
            // Validation will happen on form submission to ensure country/pattern compatibility
        }
        } finally {
            // Always clear the filtering flag
            $countrySelect.removeData('filtering');
            $patternSelect.removeData('filtering');
        }
    }
        }
        
        // Real-time validation and filtering on country/pattern change in profile form
        $(document).on('change', '#poke-hub-profile-form #country, #poke-hub-profile-form #scatterbug_pattern', function() {
            var $form = $('#poke-hub-profile-form');
            if ($form.length === 0) {
                return;
            }
            
            var $countrySelect = $form.find('#country');
            var $patternSelect = $form.find('#scatterbug_pattern');
            
            if ($countrySelect.length === 0 || $patternSelect.length === 0) {
                return;
            }
            
            var $changedSelect = $(this);
            var isCountrySelect2 = $countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2');
            
            // If country changed, update Select2 display
            if ($changedSelect.is('#country') && isCountrySelect2) {
                // Force Select2 to update its display
                setTimeout(function() {
                    $countrySelect.trigger('change.select2');
                }, 10);
            }
            
            // Filter options dynamically
            filterVivillonOptions($countrySelect, $patternSelect);
            
            var country = $countrySelect.val();
            var pattern = $patternSelect.val();
            
            // Remove error styling
            $countrySelect.removeClass('error');
            $patternSelect.removeClass('error');
            hideCountryPatternError();
            
            // Validate if both are filled
            if (country && pattern) {
                var isValid = validateVivillonCountryPattern(country, pattern);
                if (!isValid) {
                    $countrySelect.addClass('error');
                    $patternSelect.addClass('error');
                    var errorMessage = (typeof pokeHubFriendCodes !== 'undefined' && pokeHubFriendCodes.validationError) 
                        ? pokeHubFriendCodes.validationError 
                        : 'The selected country and Vivillon pattern do not match. Please select a valid combination.';
                    showCountryPatternError($form, errorMessage);
                }
            }
        });
        
        // Apply filtering on page load if values are already selected
        $(document).ready(function() {
            function applyInitialFilteringUM() {
                var $form = $('#poke-hub-profile-form');
                if ($form.length > 0) {
                    var $countrySelect = $form.find('#country');
                    var $patternSelect = $form.find('#scatterbug_pattern');
                    
                    if ($countrySelect.length > 0 && $patternSelect.length > 0) {
                        // Always save original options first
                        if (!$patternSelect.data('original-options')) {
                            var originalPatternOptions = [];
                            $patternSelect.find('option').each(function() {
                                originalPatternOptions.push({
                                    value: $(this).val(),
                                    text: $(this).text(),
                                    selected: $(this).prop('selected')
                                });
                            });
                            $patternSelect.data('original-options', originalPatternOptions);
                        }
                        
                        if (!$countrySelect.data('original-options')) {
                            var originalCountryOptions = [];
                            $countrySelect.find('option').each(function() {
                                originalCountryOptions.push({
                                    value: $(this).val(),
                                    text: $(this).text(),
                                    selected: $(this).prop('selected')
                                });
                            });
                            $countrySelect.data('original-options', originalCountryOptions);
                        }
                        
                        // Check if country has a value (including auto-detected, even if disabled)
                        var countryValue = $countrySelect.val();
                        if ((!countryValue || countryValue === '' || countryValue === '0') && $countrySelect.prop('disabled')) {
                            var $hiddenCountry = $countrySelect.siblings('input[name="country"][type="hidden"]');
                            if ($hiddenCountry.length > 0) {
                                countryValue = $hiddenCountry.val();
                            }
                        }
                        
                        if (countryValue && countryValue !== '' && countryValue !== '0') {
                            // Wait a bit for Select2 to initialize if it's used
                            setTimeout(function() {
                                if (typeof filterVivillonOptions === 'function') {
                                    filterVivillonOptions($countrySelect, $patternSelect);
                                }
                            }, 300);
                        }
                    }
                }
            }
            
            // Apply filtering multiple times to catch auto-detected countries
            applyInitialFilteringUM();
            setTimeout(applyInitialFilteringUM, 600);
            setTimeout(applyInitialFilteringUM, 1200);
            setTimeout(applyInitialFilteringUM, 2000);
            
            // Also check for mismatch after a delay if country field has a saved value
            setTimeout(function() {
                var $countrySelect = $('#poke-hub-profile-form #country');
                if ($countrySelect.length > 0) {
                    var savedCountry = $countrySelect.val() || '';
                    var $hiddenCountry = $countrySelect.siblings('input[name="country"][type="hidden"]');
                    if ($hiddenCountry.length > 0 && savedCountry === '') {
                        savedCountry = $hiddenCountry.val() || '';
                    }
                    
                    // Check if field has a saved value (not auto-detected)
                    var hasSavedValue = savedCountry !== '' && savedCountry !== '0';
                    var isAutoDetected = $countrySelect.attr('data-auto-detected') === '1';
                    
                    if (hasSavedValue && !isAutoDetected) {
                        console.log('[PokeHub UM] Checking country mismatch for saved value:', savedCountry);
                        checkCountryMismatch($countrySelect, savedCountry);
                    }
                }
            }, 1000);
        });
        
        /**
         * Check if saved country matches detected country and show warning if not
         */
        function checkCountryMismatch($countrySelect, savedCountry) {
            if (typeof window.pokeHubDetectCountry !== 'function') {
                return;
            }
            
            // Get detected country
            window.pokeHubDetectCountry()
                .then(function(countryData) {
                    if (!countryData || (!countryData.code && !countryData.name)) {
                        return;
                    }
                    
                    // Try to find the detected country name in the select options
                    var detectedCountryName = null;
                    if (countryData.name) {
                        // Normalize apostrophes for comparison
                        var detectedNameLower = countryData.name.trim().toLowerCase();
                        var detectedNameNorm = detectedNameLower.replace(/[''']/g, "'");
                        
                        // Check if the detected country name exists in the select options
                        var $options = $countrySelect.find('option');
                        $options.each(function() {
                            var optionText = $(this).text().trim();
                            var optionValue = $(this).val();
                            
                            if (optionValue && optionValue !== '' && optionValue !== '0') {
                                var optTextLower = optionText.toLowerCase();
                                var optValLower = optionValue.toLowerCase();
                                var optTextNorm = optTextLower.replace(/[''']/g, "'");
                                var optValNorm = optValLower.replace(/[''']/g, "'");
                                
                                // Compare both by text and value (exact and normalized)
                                if (optionText === countryData.name || optionValue === countryData.name ||
                                    optTextLower === detectedNameLower || optValLower === detectedNameLower ||
                                    optTextNorm === detectedNameNorm || optValNorm === detectedNameNorm) {
                                    detectedCountryName = optionValue || optionText;
                                    console.log('[PokeHub UM] Found detected country in select:', detectedCountryName);
                                    return false; // break
                                }
                            }
                        });
                        
                        // If not found by exact match, use the name directly (will be matched when updating)
                        if (!detectedCountryName && countryData.name) {
                            detectedCountryName = countryData.name;
                            console.log('[PokeHub UM] Using detected country name directly:', detectedCountryName);
                        }
                    }
                    
                    // Compare saved country with detected country (normalized comparison)
                    if (detectedCountryName) {
                        var savedCountryNorm = (savedCountry || '').trim().toLowerCase().replace(/[''']/g, "'");
                        var detectedCountryNorm = detectedCountryName.trim().toLowerCase().replace(/[''']/g, "'");
                        
                        // Get the saved country value (may be different from savedCountry text)
                        var savedCountryValue = $countrySelect.val() || '';
                        
                        if (savedCountryNorm !== detectedCountryNorm && savedCountry !== detectedCountryName) {
                            // Show warning message
                            console.log('[PokeHub UM] Country mismatch detected:', savedCountry, 'vs', detectedCountryName);
                            showCountryMismatchWarning($countrySelect, savedCountry, detectedCountryName);
                        } else {
                            // Countries match - lock the field and show indicator to protect it
                            console.log('[PokeHub UM] Countries match - locking field:', savedCountry, '=', detectedCountryName);
                            
                            // Only lock if not already locked
                            var isAlreadyLocked = $countrySelect.attr('data-auto-detected') === '1' && $countrySelect.prop('disabled');
                            if (!isAlreadyLocked) {
                                // Use the saved country value to lock the field
                                var countryValueToLock = savedCountryValue || savedCountry;
                                
                                // Ensure the country is selected in the dropdown
                                if (typeof window.pokeHubSelectCountry === 'function') {
                                    window.pokeHubSelectCountry($countrySelect, null, countryValueToLock);
                                } else {
                                    // Fallback: try to set value directly
                                    $countrySelect.val(countryValueToLock);
                                    if ($countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2')) {
                                        $countrySelect.trigger('change.select2');
                                    }
                                }
                                
                                // Lock field and show indicator (using shared function)
                                if (typeof window.pokeHubLockCountryField === 'function') {
                                    console.log('[PokeHub UM] Locking field based on location match');
                                    window.pokeHubLockCountryField($countrySelect, countryValueToLock);
                                    
                                    // Apply pattern filtering after locking
                                    var $form = $countrySelect.closest('form');
                                    if ($form.length > 0) {
                                        var $patternSelect = $form.find('#scatterbug_pattern');
                                        if ($patternSelect.length > 0 && typeof filterVivillonOptions === 'function') {
                                            setTimeout(function() {
                                                filterVivillonOptions($countrySelect, $patternSelect);
                                            }, 300);
                                        }
                                    }
                                } else {
                                    console.error('[PokeHub UM] pokeHubLockCountryField function not found');
                                }
                            }
                        }
                    } else {
                        console.log('[PokeHub UM] No detected country name to compare');
                    }
                })
                .catch(function(error) {
                    console.error('[PokeHub UM] Error checking country mismatch:', error);
                });
        }
        
        /**
         * Show warning message when saved country doesn't match detected country
         */
        function showCountryMismatchWarning($countrySelect, savedCountry, detectedCountry) {
            var $form = $countrySelect.closest('form');
            if ($form.length === 0) {
                return;
            }
            
            // Remove existing warning if present
            $form.find('.poke-hub-country-mismatch-warning').remove();
            
            // Create warning message
            var mismatchMessage = typeof pokeHubFriendCodes !== 'undefined' && pokeHubFriendCodes.countryMismatchMessage 
                ? pokeHubFriendCodes.countryMismatchMessage 
                : 'Your saved country ("' + savedCountry + '") does not match your detected location ("' + detectedCountry + '").';
            var mismatchSuggestion = typeof pokeHubFriendCodes !== 'undefined' && pokeHubFriendCodes.countryMismatchSuggestion
                ? pokeHubFriendCodes.countryMismatchSuggestion
                : 'Would you like to update your country to match your current location?';
            var buttonText = typeof pokeHubFriendCodes !== 'undefined' && pokeHubFriendCodes.updateCountryButtonText
                ? pokeHubFriendCodes.updateCountryButtonText
                : 'Update to detected country';
            
            var warningHtml = '<div class="me5rine-lab-form-message me5rine-lab-form-message-warning poke-hub-country-mismatch-warning">' +
                '<p><strong>' + mismatchMessage + '</strong><br>' + mismatchSuggestion + '</p>' +
                '<button type="button" class="poke-hub-update-country-btn me5rine-lab-form-button me5rine-lab-form-button-secondary me5rine-lab-form-message-button me5rine-lab-form-message-warning-button" ' +
                    'data-detected-country="' + $('<div>').text(detectedCountry).html() + '">' +
                    buttonText +
                '</button>' +
                '</div>';
            
            // Insert before the form or after existing messages
            var $existingMessages = $form.find('.me5rine-lab-form-message').first();
            if ($existingMessages.length > 0) {
                $existingMessages.after(warningHtml);
            } else {
                $form.prepend(warningHtml);
            }
            
            // Handle update button click
            $form.find('.poke-hub-update-country-btn').on('click', function(e) {
                e.preventDefault();
                var $button = $(this);
                var countryToSet = $button.data('detected-country');
                
                console.log('[PokeHub UM] Update button clicked, country to set:', countryToSet);
                
                // First, restore all original country options if they were filtered
                var originalCountryOptions = $countrySelect.data('original-options') || [];
                if (originalCountryOptions.length > 0) {
                    // Always restore all original options to ensure detected country is available
                    console.log('[PokeHub UM] Restoring all original country options before update');
                    var currentCountryValue = $countrySelect.val();
                    
                    $countrySelect.find('option').remove();
                    originalCountryOptions.forEach(function(opt) {
                        var $newOption = $('<option>').val(opt.value).text(opt.text);
                        $countrySelect.append($newOption);
                    });
                    
                    // Reinitialize Select2 if needed
                    var isCountrySelect2 = $countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2');
                    if (isCountrySelect2) {
                        var isCountryOpen = $countrySelect.data('select2') && $countrySelect.data('select2').isOpen();
                        $countrySelect.select2('destroy');
                        $countrySelect.select2();
                        if (isCountryOpen) {
                            setTimeout(function() {
                                $countrySelect.select2('open');
                            }, 10);
                        }
                    }
                }
                
                // Now, try to find the country in the select options (case-insensitive, with normalization)
                var foundOption = null;
                var countryToSetLower = (countryToSet || '').trim().toLowerCase();
                
                $countrySelect.find('option').each(function() {
                    var $opt = $(this);
                    var optVal = ($opt.val() || '').trim();
                    var optText = ($opt.text() || '').trim();
                    
                    if (optVal && optVal !== '' && optVal !== '0') {
                        // Normalize apostrophes and compare
                        var optValNorm = optVal.toLowerCase().replace(/[''']/g, "'");
                        var optTextNorm = optText.toLowerCase().replace(/[''']/g, "'");
                        var countryNorm = countryToSetLower.replace(/[''']/g, "'");
                        
                        if (optValNorm === countryNorm || optTextNorm === countryNorm || 
                            optVal.toLowerCase() === countryToSetLower || optText.toLowerCase() === countryToSetLower) {
                            foundOption = optVal;
                            console.log('[PokeHub UM] Found matching option:', optVal, 'for detected country:', countryToSet);
                            return false; // break
                        }
                    }
                });
                
                if (foundOption) {
                    // Direct match found, use pokeHubSelectCountry to ensure proper selection
                    if (typeof window.pokeHubSelectCountry === 'function') {
                        window.pokeHubSelectCountry($countrySelect, null, foundOption);
                    } else {
                        $countrySelect.val(foundOption);
                        if ($countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2')) {
                            $countrySelect.trigger('change.select2');
                        }
                    }
                    
                    // Lock field and show indicator
                    if (typeof window.pokeHubLockCountryField === 'function') {
                        window.pokeHubLockCountryField($countrySelect, foundOption);
                    }
                    
                    // Trigger pattern filtering
                    var $patternSelect = $form.find('#scatterbug_pattern');
                    if ($patternSelect.length > 0 && typeof filterVivillonOptions === 'function') {
                        setTimeout(function() {
                            filterVivillonOptions($countrySelect, $patternSelect);
                        }, 300);
                    }
                    
                    // Remove warning message
                    $button.closest('.poke-hub-country-mismatch-warning').fadeOut(300, function() {
                        $(this).remove();
                    });
                    
                    // Show success message briefly
                    var successHtml = '<div class="me5rine-lab-form-message me5rine-lab-form-message-success poke-hub-country-updated-success">' +
                        '<p>' + (typeof pokeHubFriendCodes !== 'undefined' && pokeHubFriendCodes.countryUpdatedMessage
                            ? pokeHubFriendCodes.countryUpdatedMessage
                            : 'Country updated successfully!') + '</p>' +
                        '</div>';
                    $form.find('.poke-hub-country-updated-success').remove();
                    $button.closest('.poke-hub-country-mismatch-warning').after(successHtml);
                    
                    setTimeout(function() {
                        $form.find('.poke-hub-country-updated-success').fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 3000);
                } else {
                    // No direct match, try using detection function
                    console.log('[PokeHub UM] No direct match found, trying detection function');
                    if (typeof window.pokeHubDetectCountry === 'function') {
                        window.pokeHubDetectCountry()
                            .then(function(countryData) {
                                if (countryData && (countryData.code || countryData.name)) {
                                    // Use the select country function to properly match and select
                                    if (typeof window.pokeHubSelectCountry === 'function') {
                                        var selected = window.pokeHubSelectCountry($countrySelect, countryData.code, countryData.name);
                                        if (selected) {
                                            var actualSelectedValue = $countrySelect.val();
                                            
                                            console.log('[PokeHub UM] Selected country via detection function:', actualSelectedValue);
                                            
                                            // Lock field and show indicator
                                            if (typeof window.pokeHubLockCountryField === 'function') {
                                                window.pokeHubLockCountryField($countrySelect, actualSelectedValue);
                                            }
                                            
                                            // Trigger pattern filtering
                                            var $patternSelect = $form.find('#scatterbug_pattern');
                                            if ($patternSelect.length > 0 && typeof filterVivillonOptions === 'function') {
                                                setTimeout(function() {
                                                    filterVivillonOptions($countrySelect, $patternSelect);
                                                }, 300);
                                            }
                                            
                                            // Remove warning message
                                            $button.closest('.poke-hub-country-mismatch-warning').fadeOut(300, function() {
                                                $(this).remove();
                                            });
                                            
                                            // Show success message briefly
                                            var successHtml = '<div class="me5rine-lab-form-message me5rine-lab-form-message-success poke-hub-country-updated-success">' +
                                                '<p>' + (typeof pokeHubFriendCodes !== 'undefined' && pokeHubFriendCodes.countryUpdatedMessage
                                                    ? pokeHubFriendCodes.countryUpdatedMessage
                                                    : 'Country updated successfully!') + '</p>' +
                                                '</div>';
                                            $form.find('.poke-hub-country-updated-success').remove();
                                            $button.closest('.poke-hub-country-mismatch-warning').after(successHtml);
                                            
                                            setTimeout(function() {
                                                $form.find('.poke-hub-country-updated-success').fadeOut(300, function() {
                                                    $(this).remove();
                                                });
                                            }, 3000);
                                        } else {
                                            console.error('[PokeHub UM] Failed to select detected country via detection function');
                                        }
                                    }
                                }
                            })
                            .catch(function(error) {
                                console.error('[PokeHub UM] Error updating country:', error);
                            });
                    }
                }
            });
        }

        // Validate on form submit
        $(document).on('submit', '#poke-hub-profile-form', function(e) {
            var $form = $(this);
            var $friendCodeInput = $('#friend_code');
            var $countrySelect = $form.find('#country');
            var $patternSelect = $form.find('#scatterbug_pattern');
            
            // Ensure empty values are sent for pattern and team to allow deletion
            if ($patternSelect.length > 0) {
                var patternValue = $patternSelect.val() || '';
                // If Select2, ensure the value is correctly set
                if ($patternSelect.hasClass('select2-hidden-accessible') || $patternSelect.data('select2')) {
                    $patternSelect.val(patternValue).trigger('change');
                }
                // Ensure the field has a value (even empty) so it's submitted
                if (patternValue === '') {
                    var $hiddenPattern = $form.find('input[name="scatterbug_pattern"][type="hidden"]');
                    if ($hiddenPattern.length === 0) {
                        $patternSelect.after('<input type="hidden" name="scatterbug_pattern" value="">');
                    }
                }
            }
            
            var $teamSelect = $form.find('#team');
            if ($teamSelect.length > 0) {
                var teamValue = $teamSelect.val() || '';
                // If Select2, ensure the value is correctly set
                if ($teamSelect.hasClass('select2-hidden-accessible') || $teamSelect.data('select2')) {
                    $teamSelect.val(teamValue).trigger('change');
                }
                // Ensure the field has a value (even empty) so it's submitted
                if (teamValue === '') {
                    var $hiddenTeam = $form.find('input[name="team"][type="hidden"]');
                    if ($hiddenTeam.length === 0) {
                        $teamSelect.after('<input type="hidden" name="team" value="">');
                    }
                }
            }
            
            // Before validating, ensure auto-detected country value is included even if select is disabled
            if ($countrySelect.length > 0 && $countrySelect.prop('disabled') && $countrySelect.attr('data-auto-detected') === '1') {
                var countryValue = $countrySelect.val();
                var $hiddenCountry = $form.find('input[name="country"][type="hidden"]');
                if ($hiddenCountry.length > 0) {
                    $hiddenCountry.val(countryValue);
                } else {
                    $countrySelect.after('<input type="hidden" name="country" value="' + countryValue + '">');
                }
            }
            
            // Remove any existing warning/error messages
            removeFormWarningMessage();
            hideCountryPatternError();
            
            // Validate friend code if provided
            if ($friendCodeInput.length > 0) {
                var friendCodeValue = $friendCodeInput.val();
                
                if (friendCodeValue.trim() !== '') {
                    if (!validateFriendCode(friendCodeValue)) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        showFormWarningMessage('The friend code must be exactly 12 digits (e.g., 1234 5678 9012)');
                        $friendCodeInput.addClass('error');
                        setTimeout(function() {
                            $friendCodeInput.focus();
                        }, 400);
                        return false;
                    }
                }
            }
            
            // Validate country/pattern combination if both are provided
            if ($countrySelect.length > 0 && $patternSelect.length > 0) {
                var country = $countrySelect.val();
                var pattern = $patternSelect.val();
                
                if (country && pattern) {
                    var isValid = validateVivillonCountryPattern(country, pattern);
                    if (!isValid) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        
                        $countrySelect.addClass('error');
                        $patternSelect.addClass('error');
                        
                        var errorMessage = (typeof pokeHubFriendCodes !== 'undefined' && pokeHubFriendCodes.validationError) 
                            ? pokeHubFriendCodes.validationError 
                            : 'Le pays et le motif de lépidonille sélectionnés ne correspondent pas. Veuillez sélectionner une combinaison valide.';
                        showCountryPatternError($form, errorMessage);
                        
                        return false;
                    }
                }
            }
            
            // Validation passed, remove any error classes
            $friendCodeInput.removeClass('error');
            $countrySelect.removeClass('error');
            $patternSelect.removeClass('error');
            
            return true;
        });

        // Select2 initialization is now handled by pokehub-front-select2.js
        // No need to initialize here to avoid conflicts and ensure consistent styling
    });

})(jQuery);

