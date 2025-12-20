// assets/js/pokehub-admin-select2.js

jQuery(function($) {
    // Fonction helper pour la recherche multilingue (disponible globalement)
    window.pokehubMultilingualMatcher = function pokehubMultilingualMatcher(params, data) {
        // Si aucun terme de recherche, afficher toutes les options
        if (!params.term || params.term.trim() === '') {
            return data;
        }
        
        var term = params.term.toLowerCase().trim();
        var text = data.text ? data.text.toLowerCase() : '';
        
        // Chercher dans le texte affiché
        if (text.indexOf(term) !== -1) {
            return data;
        }
        
        // Chercher dans les attributs data-name-fr et data-name-en de l'élément option original
        if (data.element) {
            var optionEl = data.element;
            var nameFr = '';
            var nameEn = '';
            
            // Essayer getAttribute d'abord
            if (optionEl.getAttribute) {
                nameFr = (optionEl.getAttribute('data-name-fr') || '').toLowerCase();
                nameEn = (optionEl.getAttribute('data-name-en') || '').toLowerCase();
            }
            // Fallback sur dataset si disponible
            else if (optionEl.dataset) {
                nameFr = (optionEl.dataset.nameFr || '').toLowerCase();
                nameEn = (optionEl.dataset.nameEn || '').toLowerCase();
            }
            // Fallback sur jQuery si l'élément peut être converti
            else if (typeof $ !== 'undefined') {
                var $el = $(optionEl);
                nameFr = ($el.attr('data-name-fr') || '').toLowerCase();
                nameEn = ($el.attr('data-name-en') || '').toLowerCase();
            }
            
            if (nameFr && nameFr.length > 0 && nameFr.indexOf(term) !== -1) {
                return data;
            }
            if (nameEn && nameEn.length > 0 && nameEn.indexOf(term) !== -1) {
                return data;
            }
        }
        
        // Aucune correspondance
        return null;
    };

    function pokehubInitAttackSelect2(context) {
        var $ctx = context ? $(context) : $(document);
        $ctx.find('select.pokehub-move-select').each(function() {
            var $s = $(this);
            if ($s.data('select2')) {
                return;
            }
            var placeholder = (typeof pokehubSelect2Strings !== 'undefined' && pokehubSelect2Strings.selectMove) 
                ? pokehubSelect2Strings.selectMove 
                : 'Select move';
            $s.select2({
                width: '100%',
                placeholder: placeholder,
                matcher: pokehubMultilingualMatcher
            });
        });
    }

    function pokehubInitWeatherSelect2(context) {
        var $ctx = context ? $(context) : $(document);
        $ctx.find('select.pokehub-weather-select2').each(function() {
            var $s = $(this);
            if ($s.data('select2')) {
                return;
            }
            var placeholder = $s.attr('data-placeholder') || 
                (typeof pokehubSelect2Strings !== 'undefined' && pokehubSelect2Strings.selectWeather 
                    ? pokehubSelect2Strings.selectWeather 
                    : 'Select weather (optional)');
            $s.select2({
                width: '100%',
                placeholder: placeholder,
                allowClear: true,
                matcher: pokehubMultilingualMatcher
            });
        });
    }

    function pokehubInitItemSelect2(context) {
        var $ctx = context ? $(context) : $(document);
        $ctx.find('select.pokehub-item-select2').each(function() {
            var $s = $(this);
            if ($s.data('select2')) {
                return;
            }
            var placeholder = $s.attr('data-placeholder') || 
                (typeof pokehubSelect2Strings !== 'undefined' && pokehubSelect2Strings.selectItem 
                    ? pokehubSelect2Strings.selectItem 
                    : 'Select item (optional)');
            $s.select2({
                width: '100%',
                placeholder: placeholder,
                allowClear: true,
                matcher: pokehubMultilingualMatcher
            });
        });
    }

    function pokehubInitLureSelect2(context) {
        var $ctx = context ? $(context) : $(document);
        $ctx.find('select.pokehub-lure-select2').each(function() {
            var $s = $(this);
            if ($s.data('select2')) {
                return;
            }
            var placeholder = $s.attr('data-placeholder') || 
                (typeof pokehubSelect2Strings !== 'undefined' && pokehubSelect2Strings.selectLure 
                    ? pokehubSelect2Strings.selectLure 
                    : 'Select lure (optional)');
            $s.select2({
                width: '100%',
                placeholder: placeholder,
                allowClear: true,
                matcher: pokehubMultilingualMatcher
            });
        });
    }

    function pokehubInitPokemonSelect2(context) {
        var $ctx = context ? $(context) : $(document);
        $ctx.find('select.pokehub-pokemon-select2').each(function() {
            var $s = $(this);
            if ($s.data('select2')) {
                return;
            }
            var placeholder = $s.attr('data-placeholder') || 
                (typeof pokehubSelect2Strings !== 'undefined' && pokehubSelect2Strings.selectTargetPokemon 
                    ? pokehubSelect2Strings.selectTargetPokemon 
                    : 'Select target Pokémon');
            $s.select2({
                width: '100%',
                placeholder: placeholder,
                allowClear: true,
                matcher: pokehubMultilingualMatcher
            });
        });
    }

    // Exposer les fonctions globalement
    window.pokehubInitAttackSelect2 = pokehubInitAttackSelect2;
    window.pokehubInitWeatherSelect2 = pokehubInitWeatherSelect2;
    window.pokehubInitItemSelect2 = pokehubInitItemSelect2;
    window.pokehubInitLureSelect2 = pokehubInitLureSelect2;
    window.pokehubInitPokemonSelect2 = pokehubInitPokemonSelect2;
    
    // Initialiser sur le document
    pokehubInitAttackSelect2(document);
    pokehubInitWeatherSelect2(document);
    pokehubInitItemSelect2(document);
    pokehubInitLureSelect2(document);
    pokehubInitPokemonSelect2(document);
});

