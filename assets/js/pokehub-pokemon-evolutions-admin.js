// assets/js/pokehub-pokemon-evolutions-admin.js

jQuery(function($) {
    // Fonction pour gérer l'affichage conditionnel des champs d'évolution
    function toggleEvolutionFields(selectElement) {
        if (!selectElement) return;
        
        var $select = $(selectElement);
        var $row = $select.closest('tr');
        
        if (!$row.length) {
            $row = $select.closest('td');
        }
        
        if (!$row.length) {
            $row = $select.parent();
        }
        
        var method = $select.val() || '';
        
        // Cacher tous les champs conditionnels
        $row.find('.pokehub-evolution-conditional').css('display', 'none');
        
        // Afficher les champs selon la méthode
        if (method === 'item') {
            $row.find('.pokehub-evo-method-item').css('display', 'block');
        } else if (method === 'lure') {
            $row.find('.pokehub-evo-method-lure').css('display', 'block');
        } else if (method === 'quest') {
            $row.find('.pokehub-evo-method-quest').css('display', 'block');
        } else if (method === 'stats') {
            $row.find('.pokehub-evo-method-stats').css('display', 'block');
        }
        
        // Time of day : s'affiche si une valeur est sélectionnée
        var timeOfDaySelect = $row.find('select[name*="[time_of_day]"]');
        if (timeOfDaySelect.length && timeOfDaySelect.val()) {
            $row.find('.pokehub-evo-method-time').css('display', 'block');
        } else {
            $row.find('.pokehub-evo-method-time').css('display', 'none');
        }
    }
    
    // Exposer la fonction globalement
    window.pokehubToggleEvolutionFields = toggleEvolutionFields;
    
    // Écouter les changements sur les selects de méthode
    $(document).on('change', '.pokehub-evolution-method', function() {
        toggleEvolutionFields(this);
    });
    
    // Gérer l'affichage du time_of_day quand il change
    $(document).on('change', 'select[name*="[time_of_day]"]', function() {
        var $row = $(this).closest('tr');
        if (!$row.length) {
            $row = $(this).closest('td');
        }
        if ($(this).val()) {
            $row.find('.pokehub-evo-method-time').css('display', 'block');
        } else {
            $row.find('.pokehub-evo-method-time').css('display', 'none');
        }
    });
    
    // Initialiser au chargement pour tous les selects existants
    $('.pokehub-evolution-method').each(function() {
        toggleEvolutionFields(this);
    });
    
    // Réinitialiser après un court délai au cas où certains éléments sont ajoutés dynamiquement
    setTimeout(function() {
        $('.pokehub-evolution-method').each(function() {
            toggleEvolutionFields(this);
        });
    }, 300);
});

