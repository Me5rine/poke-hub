/**
 * Friend Codes JavaScript
 */

window.pokeHubFriendCodesLoaded = true;

(function($) {
    'use strict';
    
    // Ensure DOM is ready
    $(document).ready(function() {
        // Auto-detect country from IP if country field is empty (optional, non-blocking)
        // This runs on all pages where the friend codes script is loaded
        // Uses shared detection script: poke-hub-country-detection.js
        function autoDetectCountry() {
            var $countrySelect = $('#country');
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
                // Check if saved country matches detected country
                checkCountryMismatch($countrySelect, savedCountry);
                return; // Field already has a value, but check for mismatch
            }
            
            // Check if already auto-detected to avoid multiple attempts
            if ($countrySelect.attr('data-auto-detected') === '1') {
                return;
            }
            
            // Use shared detection function (from poke-hub-country-detection.js)
            if (typeof window.pokeHubDetectCountry !== 'function') {
                return; // Shared script not loaded
            }
            
            // Store saved country value for later comparison
            var savedCountryBeforeDetection = savedCountry || '';
            
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
                        window.pokeHubLockCountryField($countrySelect, selectedValue);
                    }
                    
                    // Apply filtering after country is auto-detected and locked
                    // Wait longer to ensure Select2 is fully initialized and field is locked
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
                            
                            // Apply filtering with multiple delays to ensure it works
                            // Use the selected value for filtering
                            var selectedCountryForFilter = selectedValue;
                            
                            setTimeout(function() {
                                // Ensure country select has the value set before filtering
                                if ($countrySelect.val() !== selectedCountryForFilter) {
                                    $countrySelect.val(selectedCountryForFilter);
                                    if ($countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2')) {
                                        $countrySelect.trigger('change.select2');
                                    }
                                }
                                
                                if (typeof filterVivillonOptions === 'function') {
                                    filterVivillonOptions($countrySelect, $patternSelect);
                                }
                            }, 300);
                            
                            setTimeout(function() {
                                // Ensure country select has the value set before filtering
                                if ($countrySelect.val() !== selectedCountryForFilter) {
                                    $countrySelect.val(selectedCountryForFilter);
                                    if ($countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2')) {
                                        $countrySelect.trigger('change.select2');
                                    }
                                }
                                
                                if (typeof filterVivillonOptions === 'function') {
                                    filterVivillonOptions($countrySelect, $patternSelect);
                                }
                            }, 700);
                            
                            setTimeout(function() {
                                // Ensure country select has the value set before filtering
                                if ($countrySelect.val() !== selectedCountryForFilter) {
                                    $countrySelect.val(selectedCountryForFilter);
                                    if ($countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2')) {
                                        $countrySelect.trigger('change.select2');
                                    }
                                }
                                
                                if (typeof filterVivillonOptions === 'function') {
                                    filterVivillonOptions($countrySelect, $patternSelect);
                                }
                            }, 1200);
                        }
                    }
                })
                .catch(function(error) {
                    // Silently fail
                });
        }
        
        // Run detection once after a delay to ensure Select2 is initialized
        setTimeout(function() { 
            autoDetectCountry();
        }, 500);
        
        // Also try after a shorter delay
        setTimeout(function() {
            autoDetectCountry();
        }, 100);
        
        // Also check for mismatch after a delay if country field has a saved value
        setTimeout(function() {
            var $countrySelect = $('#country');
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
                    checkCountryMismatch($countrySelect, savedCountry);
                }
            }
        }, 1000);
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
        
        // Trim to remove any trailing space (shouldn't happen but safety measure)
        $input.val(formatted.trim());
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
            alert('Unable to find friend code to copy.');
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

    // Validate vivillon country/pattern combination
    function validateVivillonCountryPattern(country, pattern) {
        if (!country || !pattern) {
            return true; // If one is empty, don't validate (required field validation happens elsewhere)
        }
        
        if (typeof pokeHubFriendCodes === 'undefined' || !pokeHubFriendCodes.vivillonMapping) {
            return true; // Mapping not available, allow submission
        }
        
        var mapping = pokeHubFriendCodes.vivillonMapping;
        var validPatterns = mapping[country];
        
        // If no patterns defined for this country, allow (for new countries not yet in mapping)
        if (!validPatterns || validPatterns.length === 0) {
            return true;
        }
        
        return validPatterns.indexOf(pattern) !== -1;
    }
    
    // Show/hide validation error message (uses the same notification system as server-side errors)
    function showVivillonValidationError($form, message) {
        // Find the form block container
        var $formBlock = $form.closest('.me5rine-lab-form-block');
        if ($formBlock.length === 0) {
            return;
        }
        
        // Remove existing error messages in this form block
        $formBlock.find('.me5rine-lab-form-message-error').remove();
        
        // Add error message in the same position as server-side messages (after the h3 title)
        var $error = $('<div class="me5rine-lab-form-message me5rine-lab-form-message-error"><p>' + message + '</p></div>');
        $formBlock.find('h3').first().after($error);
        
        // Scroll to error message after a small delay to ensure DOM is updated
        setTimeout(function() {
            var msgOffset = $error.offset();
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
                
                // Smooth scroll to message
                $('html, body').animate({
                    scrollTop: scrollOffset
                }, 500, 'swing');
            }
        }, 100);
    }
    
    function hideVivillonValidationError($form) {
        var $formBlock = $form.closest('.me5rine-lab-form-block');
        if ($formBlock.length > 0) {
            $formBlock.find('.me5rine-lab-form-message-error').remove();
        }
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
            
            // Check if country is auto-detected and locked
            var isCountryLocked = $countrySelect.prop('disabled') && $countrySelect.attr('data-auto-detected') === '1';
            
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
            
            // Find the exact key in mapping - try multiple methods
            var mappingKey = country;
            var validPatterns = pokeHubFriendCodes.vivillonMapping[mappingKey] || [];
            
            // Method 1: Try exact match with trimmed country
            if (validPatterns.length === 0) {
                var normalizedCountry = country.trim();
                if (normalizedCountry !== country && pokeHubFriendCodes.vivillonMapping[normalizedCountry]) {
                    mappingKey = normalizedCountry;
                    validPatterns = pokeHubFriendCodes.vivillonMapping[mappingKey];
                }
            }
            
            // Method 2: Try case-insensitive exact match
            if (validPatterns.length === 0) {
                var countryKeys = Object.keys(pokeHubFriendCodes.vivillonMapping || {});
                for (var i = 0; i < countryKeys.length; i++) {
                    var key = countryKeys[i];
                    if (key === country || 
                        key.toLowerCase() === country.toLowerCase() || 
                        key.trim().toLowerCase() === country.trim().toLowerCase()) {
                        mappingKey = key;
                        validPatterns = pokeHubFriendCodes.vivillonMapping[mappingKey];
                        break;
                    }
                }
            }
            
            // Method 3: Try fuzzy match (contains) - for cases with special characters
            if (validPatterns.length === 0) {
                var countryKeys = Object.keys(pokeHubFriendCodes.vivillonMapping || {});
                var countryLower = country.toLowerCase();
                for (var i = 0; i < countryKeys.length; i++) {
                    var key = countryKeys[i];
                    // Check if one contains the other (normalized)
                    var keyNormalized = key.toLowerCase().replace(/[^\w\s]/g, '');
                    var countryNormalized = countryLower.replace(/[^\w\s]/g, '');
                    if (keyNormalized === countryNormalized || 
                        (keyNormalized.indexOf(countryNormalized) !== -1 && Math.abs(key.length - country.length) <= 3) ||
                        (countryNormalized.indexOf(keyNormalized) !== -1 && Math.abs(key.length - country.length) <= 3)) {
                        mappingKey = key;
                        validPatterns = pokeHubFriendCodes.vivillonMapping[mappingKey];
                        break;
                    }
                }
            }
            
            // Try to find similar country names if no patterns found
            if (validPatterns.length === 0) {
                var countryKeys = Object.keys(pokeHubFriendCodes.vivillonMapping || {});
                var similarKeys = countryKeys.filter(function(key) {
                    var keyLower = key.toLowerCase();
                    var countryLower = country.toLowerCase();
                    return keyLower.indexOf(countryLower) !== -1 || 
                           countryLower.indexOf(keyLower) !== -1 ||
                           (keyLower.indexOf('états') !== -1 && countryLower.indexOf('états') !== -1);
                });
                if (similarKeys.length > 0) {
                    // Use the first similar key that matches closely
                    for (var j = 0; j < similarKeys.length; j++) {
                        var similarKey = similarKeys[j];
                        // Prefer exact length match or very close
                        if (Math.abs(similarKey.length - country.length) <= 2) {
                            mappingKey = similarKey;
                            validPatterns = pokeHubFriendCodes.vivillonMapping[mappingKey];
                            break;
                        }
                    }
                }
            }
            
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
            // Check if the mapping key exists in the mapping (anti-false-positive)
            var hasMappingKey = Object.prototype.hasOwnProperty.call(pokeHubFriendCodes.vivillonMapping, mappingKey);
            originalPatternOptions.forEach(function(opt) {
                if (!opt.value || opt.value === '' || opt.value === '0') {
                    return; // Skip placeholder, already added
                }
                
                // If key doesn't exist in mapping → all patterns valid (fallback)
                // If key exists but array is empty → all patterns valid (or empty, but we choose all for UX)
                // If key exists and has values → filter normally
                var isValid = !hasMappingKey || validPatterns.length === 0 || validPatterns.indexOf(opt.value) !== -1;
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
            
            // Update Select2 if it was initialized - try to avoid destroy/reinitialize if possible
            if (isPatternSelect2) {
                var isPatternOpen = $patternSelect.data('select2') && $patternSelect.data('select2').isOpen();
                
                // Only destroy/reinitialize if dropdown is not open (to avoid breaking user interaction)
                if (!isPatternOpen) {
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
                    // Trigger change to update Select2 display
                    $patternSelect.trigger('change.select2');
                } else {
                    // If dropdown is open, just update the value and trigger change
                    if (selectedPatternValue) {
                        $patternSelect.val(selectedPatternValue);
                    } else {
                        $patternSelect.val('');
                    }
                    $patternSelect.trigger('change.select2');
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
                    
                    // Only destroy/reinitialize if dropdown is not open
                    if (!isPatternOpen) {
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
                        // Trigger change to update Select2 display
                        $patternSelect.trigger('change.select2');
                    } else {
                        // If dropdown is open, just update the value and trigger change
                        if (currentPatternValue) {
                            $patternSelect.val(currentPatternValue);
                        } else {
                            $patternSelect.val('');
                        }
                        $patternSelect.trigger('change.select2');
                    }
                }
            }
        }
        
        // NOTE: We do NOT filter countries based on selected pattern anymore
        // The pattern selection should NOT affect which countries are available
        // Only the country selection filters the patterns (see above)
        // Validation will happen on form submission to ensure country/pattern compatibility
        } finally {
            // Always clear the filtering flag
            $countrySelect.removeData('filtering');
            $patternSelect.removeData('filtering');
        }
    }
    
    // Real-time validation and filtering on country/pattern change
    $(document).on('change', '#country, #scatterbug_pattern', function() {
        var $form = $(this).closest('form');
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
        
        // IMPORTANT: Only validate if pattern changed, not if country changed
        // When country changes, the pattern might still be the old one, which would cause false validation errors
        // Validation will happen on form submission anyway
        var $changedSelect = $(this);
        if ($changedSelect.is('#scatterbug_pattern')) {
            // Pattern changed - validate only if both are filled
            if (country && pattern) {
                var isValid = validateVivillonCountryPattern(country, pattern);
                if (!isValid) {
                    $countrySelect.addClass('error');
                    $patternSelect.addClass('error');
                    if (typeof pokeHubFriendCodes !== 'undefined' && pokeHubFriendCodes.validationError) {
                        showVivillonValidationError($form, pokeHubFriendCodes.validationError);
                    }
                } else {
                    hideVivillonValidationError($form);
                }
            } else {
                hideVivillonValidationError($form);
            }
        } else {
            // Country changed - clear any existing errors (pattern might not match new country yet)
            hideVivillonValidationError($form);
        }
    });
    
    // Apply filtering on page load if values are already selected
    $(document).ready(function() {
        function applyInitialFiltering() {
            $('.friend-codes-dashboard form, .vivillon-dashboard form').each(function() {
                var $form = $(this);
                var $countrySelect = $form.find('#country');
                var $patternSelect = $form.find('#scatterbug_pattern');
                
                if ($countrySelect.length > 0 && $patternSelect.length > 0) {
                    // Always save original options first, even if no country is selected
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
                    // Also check hidden input if country field is disabled (auto-detected)
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
            });
        }
        
        // Apply filtering immediately
        applyInitialFiltering();
        
        // Also apply after delays to catch auto-detected countries
        setTimeout(applyInitialFiltering, 600);
        setTimeout(applyInitialFiltering, 1200);
        setTimeout(applyInitialFiltering, 2000);
    });
    
    // Validate friend code on form submission
    $(document).on('submit', '.friend-codes-dashboard form, .vivillon-dashboard form', function(e) {
        var $form = $(this);
        var $friendCodeInput = $form.find('#friend_code');
        var friendCode = $friendCodeInput.val().replace(/\s/g, '');
        
        // Before submitting, ensure auto-detected country value is included even if select is disabled
        var $countrySelect = $form.find('#country');
        if ($countrySelect.length > 0 && $countrySelect.prop('disabled') && $countrySelect.attr('data-auto-detected') === '1') {
            var countryValue = $countrySelect.val();
            // Ensure hidden input exists with correct value
            var $hiddenCountry = $form.find('input[name="country"][type="hidden"]');
            if ($hiddenCountry.length > 0) {
                $hiddenCountry.val(countryValue);
            } else {
                $countrySelect.after('<input type="hidden" name="country" value="' + countryValue + '">');
            }
        }
        
        // Ensure empty values are sent for pattern and team to allow deletion
        var $patternSelect = $form.find('#scatterbug_pattern');
        if ($patternSelect.length > 0) {
            var patternValue = $patternSelect.val() || '';
            // If Select2, ensure the value is correctly set
            if ($patternSelect.hasClass('select2-hidden-accessible') || $patternSelect.data('select2')) {
                $patternSelect.val(patternValue).trigger('change');
            }
            // Ensure the field has a value (even empty) so it's submitted
            if (patternValue === '') {
                // Make sure empty value is in the form data
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
                // Make sure empty value is in the form data
                var $hiddenTeam = $form.find('input[name="team"][type="hidden"]');
                if ($hiddenTeam.length === 0) {
                    $teamSelect.after('<input type="hidden" name="team" value="">');
                }
            }
        }
        
        // Remove any existing error messages first
        hideVivillonValidationError($form);
        $friendCodeInput.removeClass('error');
        
        // Validate friend code
        if (friendCode.length !== 12 || !/^\d+$/.test(friendCode)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            
            // Show error in notification system (same format as server-side)
            var $formBlock = $form.closest('.me5rine-lab-form-block');
            if ($formBlock.length > 0) {
                var $error = $('<div class="me5rine-lab-form-message me5rine-lab-form-message-error"><p>Friend code must contain exactly 12 digits.</p></div>');
                $formBlock.find('h3').first().after($error);
                
                // Scroll to error
                setTimeout(function() {
                    var msgOffset = $error.offset();
                    if (msgOffset && msgOffset.top !== undefined) {
                        var headerHeight = $(window).width() <= 768 ? 95 : ($(window).width() <= 1024 ? 123 : 129);
                        $('html, body').animate({
                            scrollTop: msgOffset.top - headerHeight - 20
                        }, 500);
                    }
                }, 100);
            }
            
            $friendCodeInput.addClass('error').focus();
            return false;
        }
        
        // Validate vivillon country/pattern combination
        var $countrySelect = $form.find('#country');
        var $patternSelect = $form.find('#scatterbug_pattern');
        
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
                    
                    // Show error in notification system (same format as server-side)
                    var errorMessage = (typeof pokeHubFriendCodes !== 'undefined' && pokeHubFriendCodes.validationError) 
                        ? pokeHubFriendCodes.validationError 
                        : 'The selected country and Vivillon pattern do not match. Please select a valid combination.';
                    
                    showVivillonValidationError($form, errorMessage);
                    
                    return false;
                }
            }
        }
        
        // Clear any error styling if validation passes
        $countrySelect.removeClass('error');
        $patternSelect.removeClass('error');
        
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
                                return false; // break
                            }
                        }
                    });
                    
                    // If not found by exact match, use the name directly (will be matched when updating)
                    if (!detectedCountryName && countryData.name) {
                        detectedCountryName = countryData.name;
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
                        showCountryMismatchWarning($countrySelect, savedCountry, detectedCountryName);
                    } else {
                        // Countries match - lock the field and show indicator to protect it
                        
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
                            }
                        }
                    }
                }
            })
            .catch(function(error) {
                // Silently fail
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
            
            // First, restore all original country options if they were filtered
            var originalCountryOptions = $countrySelect.data('original-options') || [];
            if (originalCountryOptions.length > 0) {
                // Check if country select has been filtered (has fewer options than original)
                var currentOptionsCount = $countrySelect.find('option').length;
                if (currentOptionsCount < originalCountryOptions.length) {
                    // Restore all original options
                    var currentCountryValue = $countrySelect.val();
                    
                    $countrySelect.find('option').remove();
                    originalCountryOptions.forEach(function(opt) {
                        var $newOption = $('<option>').val(opt.value).text(opt.text);
                        // Try to preserve current selection
                        if (opt.value === currentCountryValue || (opt.selected && !currentCountryValue)) {
                            $newOption.prop('selected', true);
                        }
                        $countrySelect.append($newOption);
                    });
                    
                    // Reinitialize Select2 if needed
                    var isCountrySelect2 = $countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2');
                    if (isCountrySelect2) {
                        var isCountryOpen = $countrySelect.data('select2') && $countrySelect.data('select2').isOpen();
                        $countrySelect.select2('destroy');
                        $countrySelect.select2();
                        if (currentCountryValue) {
                            $countrySelect.val(currentCountryValue);
                            $countrySelect.trigger('change.select2');
                        }
                        if (isCountryOpen) {
                            setTimeout(function() {
                                $countrySelect.select2('open');
                            }, 10);
                        }
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
                        return false; // break
                    }
                }
            });
            
            if (foundOption) {
                // Direct match found, use it
                var actualSelectedValue = foundOption;
                
                // Set the value first
                $countrySelect.val(actualSelectedValue);
                
                // Update Select2 display without destroying/reinitializing (to avoid breaking other selects)
                var isCountrySelect2 = $countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2');
                if (isCountrySelect2) {
                    // Just trigger change to update Select2 display without destroying
                    $countrySelect.trigger('change.select2');
                } else {
                    $countrySelect.trigger('change');
                }
                
                // Update hidden input if exists
                var $hiddenInput = $countrySelect.siblings('input[name="country"][type="hidden"]');
                if ($hiddenInput.length > 0) {
                    $hiddenInput.val(actualSelectedValue);
                } else if ($countrySelect.prop('disabled')) {
                    $countrySelect.after('<input type="hidden" name="country" value="' + $('<div>').text(actualSelectedValue).html() + '">');
                }
                
                // Clear pattern selection first (since country changed, pattern may not be valid anymore)
                var $patternSelect = $form.find('#scatterbug_pattern');
                if ($patternSelect.length > 0) {
                    $patternSelect.val('');
                    if ($patternSelect.hasClass('select2-hidden-accessible') || $patternSelect.data('select2')) {
                        $patternSelect.trigger('change.select2');
                    }
                }
                
                // Mark that we're updating from the mismatch warning button to prevent country filtering
                $countrySelect.data('updating-from-mismatch', true);
                
                // Then trigger pattern filtering to update patterns based on new country
                // Use a longer delay to ensure the country value is properly set before filtering
                if ($patternSelect.length > 0 && typeof filterVivillonOptions === 'function') {
                    setTimeout(function() {
                        // Double-check that country value is set before filtering
                        var currentCountryValue = $countrySelect.val();
                        if (!currentCountryValue && $countrySelect.prop('disabled')) {
                            var $hiddenCountry = $countrySelect.siblings('input[name="country"][type="hidden"]');
                            if ($hiddenCountry.length > 0) {
                                currentCountryValue = $hiddenCountry.val();
                            }
                        }
                        
                        // Only filter if we have a country value
                        if (currentCountryValue) {
                            // Ensure country select has the value before filtering
                            if ($countrySelect.val() !== currentCountryValue) {
                                $countrySelect.val(currentCountryValue);
                                if (isCountrySelect2) {
                                    $countrySelect.trigger('change.select2');
                                }
                            }
                            
                            filterVivillonOptions($countrySelect, $patternSelect);
                        }
                        
                        // Remove the flag after filtering
                        setTimeout(function() {
                            $countrySelect.removeData('updating-from-mismatch');
                        }, 100);
                    }, 200);
                } else {
                    // Remove the flag if no pattern select
                    setTimeout(function() {
                        $countrySelect.removeData('updating-from-mismatch');
                    }, 100);
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
                if (typeof window.pokeHubDetectCountry === 'function') {
                    window.pokeHubDetectCountry()
                        .then(function(countryData) {
                            if (countryData && (countryData.code || countryData.name)) {
                                // Use the select country function to properly match and select
                                if (typeof window.pokeHubSelectCountry === 'function') {
                                    var selected = window.pokeHubSelectCountry($countrySelect, countryData.code, countryData.name);
                                    if (selected) {
                                        var actualSelectedValue = $countrySelect.val();
                                        
                                        // Update hidden input if exists
                                        var $hiddenInput = $countrySelect.siblings('input[name="country"][type="hidden"]');
                                        if ($hiddenInput.length > 0) {
                                            $hiddenInput.val(actualSelectedValue);
                                        } else if ($countrySelect.prop('disabled')) {
                                            $countrySelect.after('<input type="hidden" name="country" value="' + $('<div>').text(actualSelectedValue).html() + '">');
                                        }
                                        
                                        // Clear pattern selection first (since country changed)
                                        var $patternSelect = $form.find('#scatterbug_pattern');
                                        if ($patternSelect.length > 0) {
                                            $patternSelect.val('');
                                            if ($patternSelect.hasClass('select2-hidden-accessible') || $patternSelect.data('select2')) {
                                                $patternSelect.trigger('change.select2');
                                            }
                                            
                                            // Trigger pattern filtering with proper delay
                                            if (typeof filterVivillonOptions === 'function') {
                                                setTimeout(function() {
                                                    // Double-check that country value is set before filtering
                                                    var currentCountryValue = $countrySelect.val();
                                                    if (!currentCountryValue && $countrySelect.prop('disabled')) {
                                                        var $hiddenCountry = $countrySelect.siblings('input[name="country"][type="hidden"]');
                                                        if ($hiddenCountry.length > 0) {
                                                            currentCountryValue = $hiddenCountry.val();
                                                        }
                                                    }
                                                    
                                                    // Only filter if we have a country value
                                                    if (currentCountryValue) {
                                                        // Ensure country select has the value before filtering
                                                        if ($countrySelect.val() !== currentCountryValue) {
                                                            $countrySelect.val(currentCountryValue);
                                                            if ($countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2')) {
                                                                $countrySelect.trigger('change.select2');
                                                            }
                                                        }
                                                        
                                                        filterVivillonOptions($countrySelect, $patternSelect);
                                                    }
                                                }, 200);
                                            }
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
                                    }
                                }
                            }
                        })
                        .catch(function(error) {
                            // Silently fail
                        });
                } else {
                    // Fallback: try direct value setting if detection function not available
                    var optionExists = false;
                    $countrySelect.find('option').each(function() {
                        if ($(this).val() === countryToSet || $(this).text() === countryToSet) {
                            optionExists = true;
                            return false;
                        }
                    });
                    
                    if (optionExists) {
                        $countrySelect.val(countryToSet);
                        
                        // Trigger change to update Select2 if needed
                        if ($countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2')) {
                            $countrySelect.trigger('change.select2');
                        }
                        
                        // Update hidden input if exists
                        var $hiddenInput = $countrySelect.siblings('input[name="country"][type="hidden"]');
                        if ($hiddenInput.length > 0) {
                            $hiddenInput.val(countryToSet);
                        } else if ($countrySelect.prop('disabled')) {
                            $countrySelect.after('<input type="hidden" name="country" value="' + $('<div>').text(countryToSet).html() + '">');
                        }
                        
                        // Trigger pattern filtering
                        var $patternSelect = $form.find('#scatterbug_pattern');
                        if ($patternSelect.length > 0 && typeof filterVivillonOptions === 'function') {
                            filterVivillonOptions($countrySelect, $patternSelect);
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
                    }
                }
            }
        });
    }

})(jQuery);

