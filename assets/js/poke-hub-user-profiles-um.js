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
    });

})(jQuery);

