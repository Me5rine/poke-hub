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
        $ctx.find('select.admin-lab-field-select').filter(function() {
            return $(this).closest('form, .admin-lab-form-section').length > 0;
        }).each(function() {
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
        $ctx.find('select.admin-lab-field-select').filter(function() {
            return $(this).closest('form, .admin-lab-form-section').length > 0;
        }).each(function() {
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
        $ctx.find('select.admin-lab-field-select').filter(function() {
            return $(this).closest('form, .admin-lab-form-section').length > 0;
        }).each(function() {
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
        $ctx.find('select.admin-lab-field-select').filter(function() {
            return $(this).closest('form, .admin-lab-form-section').length > 0;
        }).each(function() {
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
        $ctx.find('select.admin-lab-field-select').filter(function() {
            return $(this).closest('form, .admin-lab-form-section').length > 0;
        }).each(function() {
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

    // Initialisation Select2 pour les quêtes (Pokémon et Items)
    function pokehubInitQuestPokemonSelect2(context) {
        var $ctx = context ? $(context) : $(document);
        var pokemonList = typeof pokehubQuestsData !== 'undefined' ? pokehubQuestsData.pokemon : [];
        
        // Multiselect pour les Pokémon (récompenses Pokémon)
        $ctx.find('select.pokehub-select-pokemon').each(function() {
            var $select = $(this);
            if ($select.data('select2')) {
                return;
            }
            
            var placeholder = $select.attr('data-placeholder') || 'Select Pokémon';
            var isMultiple = $select.attr('multiple') !== undefined;
            
            $select.select2({
                data: pokemonList,
                placeholder: placeholder,
                allowClear: true,
                multiple: isMultiple,
                width: '100%',
                language: {
                    noResults: function() { return 'No Pokémon found'; },
                    searching: function() { return 'Searching...'; }
                }
            });
        });
        
        // Select simple pour les Pokémon resources (candy, mega energy)
        $ctx.find('select.pokehub-select-pokemon-resource').each(function() {
            var $select = $(this);
            if ($select.data('select2')) {
                return;
            }
            
            // Déterminer quelle liste utiliser selon le contexte
            var $rewardEditor = $select.closest('.pokehub-quest-reward-editor');
            var rewardType = $rewardEditor.find('.pokehub-reward-type').val();
            var isMegaEnergy = rewardType === 'mega_energy';
            var isCandy = rewardType === 'candy';
            
            var resourceList = [];
            if (isMegaEnergy && typeof pokehubQuestsData !== 'undefined' && pokehubQuestsData.mega_pokemon) {
                resourceList = pokehubQuestsData.mega_pokemon;
            } else if (isCandy && typeof pokehubQuestsData !== 'undefined' && pokehubQuestsData.base_pokemon) {
                resourceList = pokehubQuestsData.base_pokemon;
            } else {
                resourceList = pokemonList;
            }
            
            var placeholder = $select.attr('data-placeholder') || 'Select a Pokémon';
            
            $select.select2({
                data: resourceList,
                placeholder: placeholder,
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() { return 'No Pokémon found'; },
                    searching: function() { return 'Searching...'; }
                }
            });
            
            // Re-initialiser quand le type de récompense change
            $rewardEditor.find('.pokehub-reward-type').on('change', function() {
                var rewardType = $(this).val();
                var isMegaEnergyNow = rewardType === 'mega_energy';
                var isCandyNow = rewardType === 'candy';
                
                var newList = [];
                if (isMegaEnergyNow && typeof pokehubQuestsData !== 'undefined' && pokehubQuestsData.mega_pokemon) {
                    newList = pokehubQuestsData.mega_pokemon;
                } else if (isCandyNow && typeof pokehubQuestsData !== 'undefined' && pokehubQuestsData.base_pokemon) {
                    newList = pokehubQuestsData.base_pokemon;
                } else {
                    newList = pokemonList;
                }
                
                // Mettre à jour les données du Select2
                $select.empty();
                $select.select2('destroy');
                $select.select2({
                    data: newList,
                    placeholder: placeholder,
                    allowClear: true,
                    width: '100%',
                    language: {
                        noResults: function() { return 'No Pokémon found'; },
                        searching: function() { return 'Searching...'; }
                    }
                });
            });
        });
    }
    
    function pokehubInitQuestItemSelect2(context) {
        var $ctx = context ? $(context) : $(document);
        var itemsList = typeof pokehubQuestsData !== 'undefined' ? pokehubQuestsData.items : [];
        
        $ctx.find('select.pokehub-select-item').each(function() {
            var $select = $(this);
            if ($select.data('select2')) {
                return;
            }
            
            var placeholder = $select.attr('data-placeholder') || 'Select an item';
            
            $select.select2({
                data: itemsList,
                placeholder: placeholder,
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() { return 'No item found'; },
                    searching: function() { return 'Searching...'; }
                }
            }).on('change', function() {
                var $nameField = $select.closest('.pokehub-reward-other-fields').find('.pokehub-item-name-field');
                var selectedId = $select.val();
                if (selectedId && itemsList.length > 0) {
                    var item = itemsList.find(function(i) { return i.id == selectedId; });
                    if (item) {
                        $nameField.val(item.name_fr || item.name_en || '');
                    }
                } else {
                    $nameField.val('');
                }
            });
        });
    }

    // Exposer les fonctions globalement
    window.pokehubInitAttackSelect2 = pokehubInitAttackSelect2;
    window.pokehubInitWeatherSelect2 = pokehubInitWeatherSelect2;
    window.pokehubInitItemSelect2 = pokehubInitItemSelect2;
    window.pokehubInitLureSelect2 = pokehubInitLureSelect2;
    window.pokehubInitPokemonSelect2 = pokehubInitPokemonSelect2;
    window.pokehubInitQuestPokemonSelect2 = pokehubInitQuestPokemonSelect2;
    window.pokehubInitQuestItemSelect2 = pokehubInitQuestItemSelect2;
    
    // Initialiser sur le document
    pokehubInitAttackSelect2(document);
    pokehubInitWeatherSelect2(document);
    pokehubInitItemSelect2(document);
    pokehubInitLureSelect2(document);
    pokehubInitPokemonSelect2(document);
    pokehubInitQuestPokemonSelect2(document);
    pokehubInitQuestItemSelect2(document);
    
    // Initialiser Select2 pour le champ multiselect des pays régionaux
    $('#regional_countries').each(function() {
        var $select = $(this);
        if ($select.length && !$select.data('select2')) {
            $select.select2({
                width: '100%',
                placeholder: 'Sélectionner des pays...',
                allowClear: true
            });
        }
    });
    
    // Initialiser Select2 pour le champ multiselect des régions géographiques
    $('#regional_regions').each(function() {
        var $select = $(this);
        if ($select.length && !$select.data('select2')) {
            $select.select2({
                width: '100%',
                placeholder: 'Sélectionner des régions...',
                allowClear: true
            });
        }
    });
});

