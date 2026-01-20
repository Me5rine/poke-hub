/**
 * Shared country detection script for all user profile forms
 * Uses WordPress AJAX endpoint to avoid CORS issues
 */

// Immediate execution test - this should ALWAYS run
(function() {
    'use strict';
    console.log('[PokeHub Country Detection] Script file executing NOW');
    window.pokeHubCountryDetectionLoaded = true;
})();

// Wait for jQuery and DOM
(function() {
    'use strict';
    
    function initWhenReady() {
        if (typeof jQuery === 'undefined') {
            console.log('[PokeHub Country Detection] Waiting for jQuery...');
            setTimeout(initWhenReady, 50);
            return;
        }
        
        console.log('[PokeHub Country Detection] jQuery found, initializing');
        
        (function($) {
            console.log('[PokeHub Country Detection] Inside jQuery wrapper');
    
    // Global lock to prevent multiple simultaneous API calls (shared across all scripts)
    window.phCountryDetectPromise = window.phCountryDetectPromise || null;
    
    // Cache country data in localStorage (7 days)
    function getCachedCountryData() {
        try {
            var raw = localStorage.getItem('ph_country_data_v1');
            if (!raw) return null;
            var obj = JSON.parse(raw);
            if (!obj || !obj.ts) return null;
            
            // Cache 7 days
            if (Date.now() - obj.ts > 7 * 24 * 60 * 60 * 1000) {
                localStorage.removeItem('ph_country_data_v1');
                return null;
            }
            return { code: obj.code || null, name: obj.name || null };
        } catch(e) {
            return null;
        }
    }
    
    function setCachedCountryData(code, name) {
        try {
            localStorage.setItem('ph_country_data_v1', JSON.stringify({ 
                code: code || null, 
                name: name || null, 
                ts: Date.now() 
            }));
        } catch(e) {
            // Ignore localStorage errors (private browsing, etc.)
        }
    }
    
    // Detect country via WordPress AJAX endpoint (server-side, no CORS issues)
    window.pokeHubDetectCountry = function() {
        if (typeof console !== 'undefined' && console.log) {
            console.log('[PokeHub Country Detection] pokeHubDetectCountry called');
        }
        var cached = getCachedCountryData();
        if (cached && (cached.code || cached.name)) {
            if (typeof console !== 'undefined' && console.log) {
                console.log('[PokeHub Country Detection] Using cached data', cached);
            }
            return Promise.resolve(cached);
        }
        
        // If already fetching, return the same promise
        if (window.phCountryDetectPromise) {
            if (typeof console !== 'undefined' && console.log) {
                console.log('[PokeHub Country Detection] Already fetching, reusing promise');
            }
            return window.phCountryDetectPromise;
        }
        
        // Get AJAX URL
        var ajaxUrl = (typeof pokeHubAjax !== 'undefined' && pokeHubAjax.ajaxurl) 
            ? pokeHubAjax.ajaxurl 
            : ((typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php');
        
        if (typeof console !== 'undefined' && console.log) {
            console.log('[PokeHub Country Detection] Fetching from', ajaxUrl);
        }
        
        // Build form data (compatible with older browsers)
        var formData = 'action=poke_hub_detect_country';
        if (typeof pokeHubAjax !== 'undefined' && pokeHubAjax.nonce) {
            formData += '&nonce=' + encodeURIComponent(pokeHubAjax.nonce);
        }
        
        window.phCountryDetectPromise = fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function(response) {
            if (typeof console !== 'undefined' && console.log) {
                console.log('[PokeHub Country Detection] API response', response);
                if (response && !response.success && response.data) {
                    console.log('[PokeHub Country Detection] Error details:', response.data);
                }
            }
            if (response && response.success && response.data) {
                var code = response.data.code || null;
                var name = response.data.name || null;
                if (code || name) {
                    setCachedCountryData(code, name);
                    if (typeof console !== 'undefined' && console.log) {
                        console.log('[PokeHub Country Detection] Success', { code: code, name: name });
                    }
                    return { code: code, name: name };
                }
            }
            if (typeof console !== 'undefined' && console.log) {
                console.log('[PokeHub Country Detection] No data in response');
            }
            return { code: null, name: null };
        })
        .catch(function(error) {
            if (typeof console !== 'undefined' && console.error) {
                console.error('[PokeHub Country Detection] Error', error);
            }
            return { code: null, name: null };
        })
        .finally(function() {
            window.phCountryDetectPromise = null;
        });
        
        return window.phCountryDetectPromise;
    };
    
    // Function to select country by code or name
    window.pokeHubSelectCountry = function($select, countryCode, countryName) {
        if (!$select || !$select.length) {
            if (typeof console !== 'undefined' && console.log) {
                console.log('[PokeHub Country Detection] Select country: no select element found');
            }
            return false;
        }
        
        if (typeof console !== 'undefined' && console.log) {
            console.log('[PokeHub Country Detection] Select country: trying to match', { code: countryCode, name: countryName });
        }
        
        var selectedValue = null;
        
        // Priority 1: Try to match by code ISO (if options have codes as values)
        if (countryCode) {
            var codeUpper = (countryCode || '').trim().toUpperCase();
            $select.find('option').each(function() {
                var $opt = $(this);
                var optVal = ($opt.val() || '').trim().toUpperCase();
                if (optVal && optVal === codeUpper) {
                    selectedValue = $opt.val();
                    return false; // break
                }
            });
        }
        
        // Priority 2: Match by country name (text or value)
        if (!selectedValue && countryName) {
            var nameLower = (countryName || '').trim().toLowerCase();
            // Normalize apostrophes (typographic vs straight)
            var nameNormalized = nameLower.replace(/[''']/g, "'");
            
            $select.find('option').each(function() {
                var $opt = $(this);
                var optVal = ($opt.val() || '').trim();
                var optText = ($opt.text() || '').trim();
                
                if (optVal && optVal !== '' && optVal !== '0') {
                    var optValLower = optVal.toLowerCase();
                    var optTextLower = optText.toLowerCase();
                    var optValNorm = optValLower.replace(/[''']/g, "'");
                    var optTextNorm = optTextLower.replace(/[''']/g, "'");
                    
                    // Try exact match first
                    if (optValLower === nameLower || optTextLower === nameLower) {
                        selectedValue = optVal;
                        return false; // break
                    }
                    // Try normalized match (handles apostrophe differences)
                    if (optValNorm === nameNormalized || optTextNorm === nameNormalized) {
                        selectedValue = optVal;
                        return false; // break
                    }
                }
            });
        }
        
        if (selectedValue) {
            $select.val(selectedValue);
            // Trigger change for Select2 if initialized
            if ($select.hasClass('select2-hidden-accessible') || $select.data('select2')) {
                $select.trigger('change.select2');
            } else {
                $select.trigger('change');
            }
            if (typeof console !== 'undefined' && console.log) {
                console.log('[PokeHub Country Detection] Select country: matched and selected', selectedValue);
            }
            return true;
        }
        
        if (typeof console !== 'undefined' && console.log) {
            console.log('[PokeHub Country Detection] Select country: no match found');
        }
        return false;
    };
    
    // Function to lock field and show indicator
    window.pokeHubLockCountryField = function($countrySelect, selectedValue) {
        if (!$countrySelect || !$countrySelect.length || !selectedValue) {
            return;
        }
        
        // Lock field after a short delay to ensure Select2 is updated
        setTimeout(function() {
            // Double-check the value is still set (check both val() and Select2 value)
            var currentVal = $countrySelect.val();
            if (currentVal !== selectedValue) {
                // Try to restore the value
                $countrySelect.val(selectedValue);
                // If Select2 is initialized, update it
                if ($countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2')) {
                    $countrySelect.trigger('change.select2');
                }
                currentVal = $countrySelect.val();
            }
            
            if (currentVal === selectedValue || selectedValue) {
                // Use selectedValue to ensure we have the right value
                var finalValue = selectedValue || currentVal;
                $countrySelect.prop('disabled', true).attr('data-auto-detected', '1');
                
                // Ensure Select2 shows the correct value even when disabled
                if ($countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2')) {
                    $countrySelect.val(finalValue);
                    $countrySelect.trigger('change.select2');
                }
                
                var countryValueToSubmit = finalValue;
                var $hiddenInput = $countrySelect.siblings('input[name="country"][type="hidden"]');
                if ($hiddenInput.length === 0) {
                    $countrySelect.after('<input type="hidden" name="country" value="' + countryValueToSubmit + '">');
                } else {
                    $hiddenInput.val(countryValueToSubmit);
                }
                var $field = $countrySelect.closest('.me5rine-lab-form-field');
                if ($field.length > 0 && $field.find('.country-auto-detected-indicator').length === 0) {
                    var $indicatorDiv = $('<div class="country-auto-detected-indicator me5rine-lab-form-description"><i class="um-icon-location-on"></i> Automatically detected from your location <a href="#" class="country-unlock-link">Modify</a></div>');
                    $field.append($indicatorDiv);
                    
                    // Trigger pattern filtering after country is locked
                    var $form = $countrySelect.closest('form');
                    if ($form && $form.length > 0) {
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
                            
                            // Apply filtering with delays to ensure it works
                            // Use the locked country value for filtering
                            var lockedCountryValue = finalValue || selectedValue;
                            
                            setTimeout(function() {
                                // Ensure country select has the value set before filtering
                                if ($countrySelect.val() !== lockedCountryValue) {
                                    $countrySelect.val(lockedCountryValue);
                                    if ($countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2')) {
                                        $countrySelect.trigger('change.select2');
                                    }
                                }
                                
                                if (typeof window.filterVivillonOptions === 'function') {
                                    window.filterVivillonOptions($countrySelect, $patternSelect);
                                } else if (typeof filterVivillonOptions === 'function') {
                                    filterVivillonOptions($countrySelect, $patternSelect);
                                }
                            }, 300);
                            
                            setTimeout(function() {
                                // Ensure country select has the value set before filtering
                                if ($countrySelect.val() !== lockedCountryValue) {
                                    $countrySelect.val(lockedCountryValue);
                                    if ($countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2')) {
                                        $countrySelect.trigger('change.select2');
                                    }
                                }
                                
                                if (typeof window.filterVivillonOptions === 'function') {
                                    window.filterVivillonOptions($countrySelect, $patternSelect);
                                } else if (typeof filterVivillonOptions === 'function') {
                                    filterVivillonOptions($countrySelect, $patternSelect);
                                }
                            }, 700);
                            
                            setTimeout(function() {
                                // Ensure country select has the value set before filtering
                                if ($countrySelect.val() !== lockedCountryValue) {
                                    $countrySelect.val(lockedCountryValue);
                                    if ($countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2')) {
                                        $countrySelect.trigger('change.select2');
                                    }
                                }
                                
                                if (typeof window.filterVivillonOptions === 'function') {
                                    window.filterVivillonOptions($countrySelect, $patternSelect);
                                } else if (typeof filterVivillonOptions === 'function') {
                                    filterVivillonOptions($countrySelect, $patternSelect);
                                }
                            }, 1200);
                        }
                    }
                    
                    // Handle unlock link click
                    $indicatorDiv.find('.country-unlock-link').on('click', function(e) {
                        e.preventDefault();
                        
                        // Restore all original country options before unlocking
                        var originalCountryOptions = $countrySelect.data('original-options') || [];
                        var currentValue = $countrySelect.val();
                        
                        if (originalCountryOptions.length > 0) {
                            $countrySelect.find('option').remove();
                            originalCountryOptions.forEach(function(opt) {
                                var $newOption = $('<option>').val(opt.value).text(opt.text);
                                // Try to preserve current selection if it exists in original options
                                if (opt.value === currentValue) {
                                    $newOption.prop('selected', true);
                                } else if (opt.selected && !currentValue) {
                                    $newOption.prop('selected', true);
                                }
                                $countrySelect.append($newOption);
                            });
                        }
                        
                        var isSelect2Unlock = $countrySelect.hasClass('select2-hidden-accessible') || $countrySelect.data('select2');
                        if (isSelect2Unlock) {
                            $countrySelect.select2('destroy');
                            $countrySelect.select2();
                            if (currentValue) {
                                $countrySelect.val(currentValue);
                                // Force Select2 to update its display
                                $countrySelect.trigger('change.select2');
                            }
                        }
                        
                        $countrySelect.prop('disabled', false).removeAttr('data-auto-detected');
                        $countrySelect.siblings('input[name="country"][type="hidden"]').remove();
                        $indicatorDiv.remove();
                        
                        // Update pattern filtering without triggering recursive change
                        var $form = $countrySelect.closest('form');
                        if ($form && $form.length > 0) {
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
                                
                                if (typeof window.filterVivillonOptions === 'function') {
                                    window.filterVivillonOptions($countrySelect, $patternSelect);
                                } else if (typeof filterVivillonOptions === 'function') {
                                    filterVivillonOptions($countrySelect, $patternSelect);
                                }
                            }
                        }
                        
                        // Listen for country changes to update Select2 display and filter patterns
                        $countrySelect.off('change.select2-country-update').on('change.select2-country-update', function() {
                            var newValue = $(this).val();
                            if (newValue && isSelect2Unlock) {
                                // Force Select2 to update its display
                                $(this).trigger('change.select2');
                            }
                            // Filter patterns based on new country
                            if ($form && $form.length > 0) {
                                var $patternSelect = $form.find('#scatterbug_pattern');
                                if ($patternSelect.length > 0) {
                                    if (typeof window.filterVivillonOptions === 'function') {
                                        window.filterVivillonOptions($countrySelect, $patternSelect);
                                    } else if (typeof filterVivillonOptions === 'function') {
                                        filterVivillonOptions($countrySelect, $patternSelect);
                                    }
                                }
                            }
                        });
                    });
                }
            }
        }, 200);
    };
    
        })(jQuery);
    }
    
    // Start initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWhenReady);
    } else {
        initWhenReady();
    }
})();
