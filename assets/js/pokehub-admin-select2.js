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
        var pokemonList = [];
        if (typeof window.pokehubSpecialResearchQuestsData !== 'undefined' && window.pokehubSpecialResearchQuestsData && Array.isArray(window.pokehubSpecialResearchQuestsData.pokemon)) {
            pokemonList = window.pokehubSpecialResearchQuestsData.pokemon;
        } else if (typeof pokehubQuestsData !== 'undefined' && pokehubQuestsData && Array.isArray(pokehubQuestsData.pokemon)) {
            pokemonList = pokehubQuestsData.pokemon;
        }
        pokehubInitQuestPokemonSelect2WithList(context, pokemonList);
    }

    function pokehubInitQuestPokemonSelect2WithList(context, pokemonList) {
        var $ctx = context ? $(context) : $(document);
        var restUrl = (typeof window.pokehubQuestsData !== 'undefined' && window.pokehubQuestsData && window.pokehubQuestsData.rest_pokemon_url) ? window.pokehubQuestsData.rest_pokemon_url : '';
        var useAjax = (restUrl && restUrl.indexOf('pokemon-for-select') !== -1);

        // Map id -> label pour hydrater les options à partir de data-selected-ids
        var pokemonMap = {};
        var list = (pokemonList && pokemonList.length) ? pokemonList : [];
        list.forEach(function(p) {
            var id = p && (p.id != null) ? String(p.id) : '';
            var text = (p && (p.text != null)) ? p.text : (p && p.name ? p.name : (id ? '#' + id : ''));
            if (id) pokemonMap[id] = text;
        });

        $ctx.find('select.pokehub-sr-reward-pokemon, .pokehub-special-research-metabox select.pokehub-select-pokemon').each(function() {
            var $select = $(this);
            if ($select.data('select2')) return;

            // 1) Lire les ids pré-sélectionnés (data-selected-ids, ou fallback : .val() / option:selected déjà présents)
            var raw = ($select.attr('data-selected-ids') || '').trim();
            var ids = raw
                ? raw.split(',').map(function(v) { return String(parseInt(v, 10)); })
                    .filter(function(v) { return v !== 'NaN' && v !== '0'; })
                : [];
            if (ids.length === 0) {
                var currentVal = $select.val();
                var fromOptions = $select.find('option:selected').map(function() { return $(this).val(); }).get();
                if (Array.isArray(currentVal) && currentVal.length) {
                    ids = currentVal.map(function(v) { return String(parseInt(v, 10)); }).filter(function(v) { return v !== 'NaN' && v !== '0'; });
                } else if (fromOptions && fromOptions.length) {
                    ids = fromOptions.map(function(v) { return String(parseInt(v, 10)); }).filter(function(v) { return v !== 'NaN' && v !== '0'; });
                }
                if (ids.length) {
                    $select.attr('data-selected-ids', ids.join(','));
                }
            }

            // 2) Hydrater les options selected AVANT Select2 (ne jamais vider le select)
            if (ids.length) {
                ids.forEach(function(id) {
                    var $opt = $select.find('option[value="' + id + '"]');
                    if (!$opt.length) {
                        $select.append(new Option(pokemonMap[id] || ('#' + id), id, true, true));
                    } else {
                        $opt.prop('selected', true);
                    }
                });
            }

            var placeholder = $select.attr('data-placeholder') || 'Rechercher un Pokémon…';
            var isMultiple = $select.attr('multiple') !== undefined;
            var opts = {
                placeholder: placeholder,
                multiple: isMultiple,
                allowClear: !isMultiple,
                width: '100%',
                language: { noResults: function() { return 'Aucun Pokémon trouvé'; }, searching: function() { return 'Recherche…'; } }
            };

            // 3) Init Select2 sans vider le select (pas de .empty(), pas de data: qui remplace)
            if (useAjax) {
                opts.minimumInputLength = 1;
                var restNonce = (typeof window.pokehubQuestsData !== 'undefined' && window.pokehubQuestsData.rest_nonce) ? window.pokehubQuestsData.rest_nonce : '';
                opts.ajax = {
                    url: restUrl,
                    dataType: 'json',
                    delay: 250,
                    headers: restNonce ? { 'X-WP-Nonce': restNonce } : {},
                    data: function(params) {
                        var t = params.term || '';
                        return { search: t, q: t, term: t };
                    },
                    processResults: function(data) {
                        if (Array.isArray(data)) {
                            return { results: data };
                        }
                        return { results: [] };
                    },
                    cache: true
                };
            }
            $select.select2(opts);

            // 4) Reforcer la valeur après init
            if (ids.length) {
                $select.val(ids).trigger('change.select2');
            }
        });

        // Réappliquer les valeurs sur les selects déjà initialisés par Select2 (au cas où une autre init aurait vidé la sélection)
        $ctx.find('select.pokehub-sr-reward-pokemon, .pokehub-special-research-metabox select.pokehub-select-pokemon').each(function() {
            var $select = $(this);
            if (!$select.data('select2')) return;
            var raw = ($select.attr('data-selected-ids') || '').trim();
            var ids = raw ? raw.split(',').map(function(v) { return String(parseInt(v, 10)); }).filter(function(v) { return v !== 'NaN' && v !== '0'; }) : [];
            if (ids.length === 0) {
                var currentVal = $select.val();
                var fromOptions = $select.find('option:selected').map(function() { return $(this).val(); }).get();
                if (Array.isArray(currentVal) && currentVal.length) {
                    ids = currentVal.map(function(v) { return String(parseInt(v, 10)); }).filter(function(v) { return v !== 'NaN' && v !== '0'; });
                } else if (fromOptions && fromOptions.length) {
                    ids = fromOptions.map(function(v) { return String(parseInt(v, 10)); }).filter(function(v) { return v !== 'NaN' && v !== '0'; });
                }
            }
            if (ids.length) {
                $select.val(ids).trigger('change.select2');
            }
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
            var currentVal = $select.val();
            var selectedId = (currentVal != null && currentVal !== '') ? Number(currentVal) : null;

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

            if (selectedId != null && selectedId > 0) {
                $select.val(selectedId).trigger('change');
            }
            
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

    /**
     * Init Select2 pour les grandes listes Pokémon (sauvages, nouveaux, œufs, habitats, défis de collection).
     * Utilise la recherche AJAX + hydratation depuis les options déjà sélectionnées (sans charger 1000+ options en DOM).
     */
    function pokehubInitLargePokemonSelect2(context) {
        var $ctx = context ? $(context) : $(document);
        var restUrl = (typeof window.pokehubQuestsData !== 'undefined' && window.pokehubQuestsData && window.pokehubQuestsData.rest_pokemon_url) ? window.pokehubQuestsData.rest_pokemon_url : '';
        var useAjax = (restUrl && restUrl.indexOf('pokemon-for-select') !== -1);
        var list = (typeof window.pokehubQuestsData !== 'undefined' && window.pokehubQuestsData && window.pokehubQuestsData.pokemon && Array.isArray(window.pokehubQuestsData.pokemon)) ? window.pokehubQuestsData.pokemon : [];
        var pokemonMap = {};
        list.forEach(function(p) {
            var id = p && (p.id != null) ? String(p.id) : '';
            var text = (p && (p.text != null)) ? p.text : (p && p.name ? p.name : (id ? '#' + id : ''));
            if (id) pokemonMap[id] = text;
        });

        var largeListSelector = '#pokehub-wild-pokemon-select, #pokehub-forced-shiny-select, #pokehub-rare-pokemon-select, #pokehub-new-pokemon-select, select.pokehub-eggs-pokemon-select, select.pokehub-eggs-pool-select, .pokehub-habitats-metabox select.pokehub-select-pokemon, .pokehub-collection-challenges-metabox select.pokehub-select-pokemon';
        $ctx.find(largeListSelector).each(function() {
            var $select = $(this);
            if ($select.data('select2')) return;

            var raw = ($select.attr('data-selected-ids') || '').trim();
            var ids = raw ? raw.split(',').map(function(v) { return String(parseInt(v, 10)); }).filter(function(v) { return v !== 'NaN' && v !== '0'; }) : [];
            if (ids.length === 0) {
                var currentVal = $select.val();
                var fromOptions = $select.find('option:selected').map(function() { return $(this).val(); }).get();
                if (Array.isArray(currentVal) && currentVal.length) {
                    ids = currentVal.map(function(v) { return String(parseInt(v, 10)); }).filter(function(v) { return v !== 'NaN' && v !== '0'; });
                } else if (fromOptions && fromOptions.length) {
                    ids = fromOptions.map(function(v) { return String(parseInt(v, 10)); }).filter(function(v) { return v !== 'NaN' && v !== '0'; });
                }
                if (ids.length) $select.attr('data-selected-ids', ids.join(','));
            }

            if (useAjax && ids.length) {
                var labelMap = {};
                ids.forEach(function(id) {
                    var $o = $select.find('option[value="' + id + '"]');
                    labelMap[id] = ($o.length ? $o.text() : null) || pokemonMap[id] || ('#' + id);
                });
                $select.empty();
                ids.forEach(function(id) {
                    $select.append(new Option(labelMap[id], id, true, true));
                });
            } else if (ids.length) {
                ids.forEach(function(id) {
                    var $opt = $select.find('option[value="' + id + '"]');
                    if (!$opt.length) $select.append(new Option(pokemonMap[id] || ('#' + id), id, true, true));
                    else $opt.prop('selected', true);
                });
            }

            var placeholder = $select.attr('data-placeholder') || 'Rechercher un Pokémon…';
            var isMultiple = $select.attr('multiple') !== undefined;
            var opts = {
                placeholder: placeholder,
                multiple: isMultiple,
                allowClear: !isMultiple,
                width: '100%',
                language: { noResults: function() { return 'Aucun Pokémon trouvé'; }, searching: function() { return 'Recherche…'; } }
            };
            if (useAjax) {
                opts.minimumInputLength = 1;
                var restNonce = (typeof window.pokehubQuestsData !== 'undefined' && window.pokehubQuestsData.rest_nonce) ? window.pokehubQuestsData.rest_nonce : '';
                opts.ajax = {
                    url: restUrl,
                    dataType: 'json',
                    delay: 250,
                    headers: restNonce ? { 'X-WP-Nonce': restNonce } : {},
                    data: function(params) {
                        var t = params.term || '';
                        return { search: t, q: t, term: t };
                    },
                    processResults: function(data) {
                        if (Array.isArray(data)) return { results: data };
                        return { results: [] };
                    },
                    cache: true
                };
            }
            $select.select2(opts);
            if (ids.length) $select.val(ids).trigger('change.select2');
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
    window.pokehubInitLargePokemonSelect2 = pokehubInitLargePokemonSelect2;

    pokehubInitAttackSelect2(document);
    pokehubInitWeatherSelect2(document);
    pokehubInitItemSelect2(document);
    pokehubInitLureSelect2(document);
    pokehubInitPokemonSelect2(document);
    pokehubInitLargePokemonSelect2(document);
    // Études spéciales : n'init que dans la metabox si présente
    var $srMetabox = $('#pokehub-special-research-metabox, .pokehub-special-research-metabox');
    var questCtx = ($srMetabox.length) ? $srMetabox[0] : document;
    pokehubInitQuestPokemonSelect2(questCtx);
    pokehubInitQuestItemSelect2(questCtx);
    
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

