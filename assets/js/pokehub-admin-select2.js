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
        var list = (pokemonList && pokemonList.length) ? pokemonList : [];
        // Liste complète déjà fournie (ex. Pass GO, quêtes) : Select2 en local = même Pokémon partout, recherche sans API.
        var useLocalList = list.length > 0;
        var useAjax = (restUrl && restUrl.indexOf('pokemon-for-select') !== -1) && !useLocalList;

        // Map id -> label pour hydrater les options à partir de data-selected-ids
        var pokemonMap = {};
        list.forEach(function(p) {
            var id = p && (p.id != null) ? String(p.id) : '';
            var text = (p && (p.text != null)) ? p.text : (p && p.name ? p.name : (id ? '#' + id : ''));
            if (id) pokemonMap[id] = text;
        });

        $ctx.find('select.pokehub-sr-reward-pokemon, .pokehub-special-research-metabox select.pokehub-select-pokemon, select.pokehub-quest-pokemon-select').each(function() {
            var $select = $(this);
            if ($select.data('select2')) return;
            var dimorphicOnly = String($select.attr('data-gender-dimorphic-only') || '') === '1';
            var isMultiple = $select.attr('multiple') !== undefined;
            var inlineGenderMode = isMultiple && $select.hasClass('pokehub-gender-driven-select');
            var existingGenderMap = pokehubNormalizeGenderMap($select.attr('data-existing-genders'));

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

            // 2) Mode AJAX seulement si aucune liste locale : sinon on garde toutes les entrées pour réutiliser le même Pokémon partout.
            if (useAjax) {
                if (ids.length) {
                    var labelMap = {};
                    ids.forEach(function(id) {
                        var $o = $select.find('option[value="' + id + '"]');
                        if ($o.length) {
                            labelMap[id] = $o.text();
                            return;
                        }
                        var parsed = pokehubParsePokemonGenderToken(id);
                        if (!parsed) {
                            labelMap[id] = '#' + id;
                            return;
                        }
                        var base = pokemonMap[parsed.id] || ('#' + parsed.id);
                        labelMap[id] = parsed.gender ? pokehubInlineGenderOptionLabel(base, parsed.gender) : base;
                    });
                    $select.empty();
                    ids.forEach(function(id) {
                        $select.append(new Option(labelMap[id], id, true, true));
                    });
                } else {
                    $select.empty();
                }
            } else if (!useLocalList && ids.length) {
                ids.forEach(function(id) {
                    var $opt = $select.find('option[value="' + id + '"]');
                    if (!$opt.length) {
                        $select.append(new Option(pokemonMap[id] || ('#' + id), id, true, true));
                    } else {
                        $opt.prop('selected', true);
                    }
                });
            }

            ids = pokehubApplyInlineGenderTokensToIds(ids, existingGenderMap, inlineGenderMode);

            var placeholder = $select.attr('data-placeholder') || 'Rechercher un Pokémon…';
            var opts = {
                placeholder: placeholder,
                multiple: isMultiple,
                allowClear: !isMultiple,
                width: '100%',
                language: { noResults: function() { return 'Aucun Pokémon trouvé'; }, searching: function() { return 'Recherche…'; } }
            };

            if (useLocalList) {
                $select.empty();
                var dataList = list.map(function(p) {
                    var pid = p && (p.id != null) ? p.id : '';
                    var ptext = (p && (p.text != null)) ? p.text : (p && p.name ? p.name : (pid ? '#' + pid : ''));
                    return {
                        id: pid,
                        text: ptext,
                        name_fr: p.name_fr,
                        name_en: p.name_en,
                        has_gender_dimorphism: p && p.has_gender_dimorphism ? 1 : 0
                    };
                });
                if (dimorphicOnly) {
                    dataList = dataList.filter(function(row) {
                        return Number(row.has_gender_dimorphism) === 1;
                    });
                }
                dataList = pokehubExpandPokemonOptionsWithGenderVariants(dataList, inlineGenderMode);
                ids.forEach(function(idStr) {
                    var parsedId = pokehubParsePokemonGenderToken(idStr);
                    if (!parsedId) {
                        return;
                    }
                    var idNum = parseInt(parsedId.id, 10);
                    if (!idNum || isNaN(idNum)) {
                        return;
                    }
                    var exists = dataList.some(function(row) {
                        return String(row.id) === idStr;
                    });
                    if (!exists) {
                        var baseLabel = pokemonMap[String(idNum)] || ('#' + idNum);
                        dataList.push({
                            id: idStr,
                            text: parsedId.gender ? pokehubInlineGenderOptionLabel(baseLabel, parsedId.gender) : baseLabel
                        });
                    }
                });
                opts.data = dataList;
                if (typeof pokehubMultilingualMatcher !== 'undefined') {
                    opts.matcher = pokehubMultilingualMatcher;
                }
            } else if (useAjax) {
                opts.minimumInputLength = 1;
                var restNonce = (typeof window.pokehubQuestsData !== 'undefined' && window.pokehubQuestsData.rest_nonce) ? window.pokehubQuestsData.rest_nonce : '';
                opts.ajax = {
                    url: restUrl,
                    dataType: 'json',
                    delay: 250,
                    headers: restNonce ? { 'X-WP-Nonce': restNonce } : {},
                    data: function(params) {
                        var t = params.term || '';
                        return {
                            search: t,
                            q: t,
                            term: t,
                            dimorphic_only: dimorphicOnly ? 1 : 0
                        };
                    },
                    processResults: function(data) {
                        if (Array.isArray(data)) {
                            return { results: pokehubExpandPokemonOptionsWithGenderVariants(data, inlineGenderMode) };
                        }
                        return { results: [] };
                    },
                    cache: true
                };
            }
            $select.select2(opts);
            if (inlineGenderMode) {
                $select.off('change.pokehubInlineGender').on('change.pokehubInlineGender', function() {
                    pokehubSyncInlineGenderHiddenInputs($select);
                });
            }

            // 4) Reforcer la valeur après init
            if (ids.length) {
                if (isMultiple) {
                    $select.val(ids).trigger('change.select2');
                } else {
                    $select.val(String(parseInt(ids[0], 10))).trigger('change.select2');
                }
            }
            if (inlineGenderMode) {
                pokehubSyncInlineGenderHiddenInputs($select);
            }
        });

        // Réappliquer les valeurs sur les selects déjà initialisés par Select2 (au cas où une autre init aurait vidé la sélection)
        $ctx.find('select.pokehub-sr-reward-pokemon, .pokehub-special-research-metabox select.pokehub-select-pokemon, select.pokehub-quest-pokemon-select').each(function() {
            var $select = $(this);
            if (!$select.data('select2')) return;
            var isMultiple2 = $select.attr('multiple') !== undefined;
            var inlineGenderMode2 = isMultiple2 && $select.hasClass('pokehub-gender-driven-select');
            var existingGenderMap2 = pokehubNormalizeGenderMap($select.attr('data-existing-genders'));
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
            ids = pokehubApplyInlineGenderTokensToIds(ids, existingGenderMap2, inlineGenderMode2);
            if (ids.length) {
                if (isMultiple2) {
                    $select.val(ids).trigger('change.select2');
                } else {
                    $select.val(String(parseInt(ids[0], 10))).trigger('change.select2');
                }
            }
            if (inlineGenderMode2) {
                pokehubSyncInlineGenderHiddenInputs($select);
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
            var isCandy = rewardType === 'candy' || rewardType === 'xl_candy';
            
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
                var isCandyNow = rewardType === 'candy' || rewardType === 'xl_candy';
                
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

    function pokehubResolvePokemonGenderConfig() {
        var candidates = [
            window.pokehubPokemonGenderConfig,
            window.pokehubQuestsGender,
            window.pokehubHabitatsGender,
            window.pokehubWildPokemonGender,
            window.pokehubNewPokemonGender
        ];
        for (var i = 0; i < candidates.length; i++) {
            var cfg = candidates[i];
            if (cfg && cfg.ajax_url && cfg.nonce) {
                return cfg;
            }
        }
        return null;
    }

    function pokehubNormalizeGenderMap(raw) {
        var out = {};
        if (!raw) {
            return out;
        }
        var map = raw;
        if (typeof raw === 'string') {
            try {
                map = JSON.parse(raw);
            } catch (e) {
                map = {};
            }
        }
        if (!map || typeof map !== 'object') {
            return out;
        }
        Object.keys(map).forEach(function(k) {
            var pid = String(parseInt(k, 10));
            var g = String(map[k] || '');
            if ((g === 'male' || g === 'female') && pid !== 'NaN' && pid !== '0') {
                out[pid] = g;
            }
        });
        return out;
    }

    function pokehubParsePokemonGenderToken(rawValue) {
        var raw = String(rawValue || '').trim();
        if (!raw) {
            return null;
        }
        var forced = raw.match(/^(\d+)\|(male|female)$/);
        if (forced) {
            return {
                id: String(parseInt(forced[1], 10)),
                gender: forced[2]
            };
        }
        var pid = String(parseInt(raw, 10));
        if (!pid || pid === 'NaN' || pid === '0') {
            return null;
        }
        return {
            id: pid,
            gender: ''
        };
    }

    function pokehubGenderSuffixLabel(gender) {
        return gender === 'female' ? 'Femelle' : 'Male';
    }

    function pokehubInlineGenderOptionLabel(baseLabel, gender) {
        return String(baseLabel || '') + ' (' + pokehubGenderSuffixLabel(gender) + ')';
    }

    function pokehubExpandPokemonOptionsWithGenderVariants(entries, enabled) {
        if (!enabled || !Array.isArray(entries)) {
            return Array.isArray(entries) ? entries : [];
        }
        var out = [];
        entries.forEach(function(row) {
            if (!row) {
                return;
            }
            var pid = String(parseInt(row.id, 10));
            if (!pid || pid === 'NaN' || pid === '0') {
                return;
            }
            var baseText = (row.text != null) ? row.text : ('#' + pid);
            var baseRow = $.extend({}, row, { id: pid, text: baseText });
            out.push(baseRow);
            if (Number(row.has_gender_dimorphism) === 1) {
                out.push($.extend({}, row, { id: pid + '|male', text: pokehubInlineGenderOptionLabel(baseText, 'male') }));
                out.push($.extend({}, row, { id: pid + '|female', text: pokehubInlineGenderOptionLabel(baseText, 'female') }));
            }
        });
        return out;
    }

    function pokehubApplyInlineGenderTokensToIds(ids, genderMap, enabled) {
        if (!enabled || !Array.isArray(ids)) {
            return ids;
        }
        return ids.map(function(rawId) {
            var id = String(parseInt(rawId, 10));
            if (!id || id === 'NaN' || id === '0') {
                return rawId;
            }
            var g = genderMap[id] || '';
            return (g === 'male' || g === 'female') ? (id + '|' + g) : id;
        });
    }

    function pokehubSyncInlineGenderHiddenInputs($select) {
        if (!$select || !$select.length || $select.attr('multiple') === undefined) {
            return;
        }
        var nameTemplate = $select.attr('data-gender-name-template') || '';
        if (!nameTemplate) {
            return;
        }
        var wrapperSelector = $select.attr('data-gender-wrapper-selector') || '';
        var $wrapper = wrapperSelector ? $select.closest('.pokehub-gender-field-group').find(wrapperSelector).first() : $select.closest('.pokehub-gender-field-group').find('.pokehub-pokemon-gender-options').first();
        if (!$wrapper.length) {
            return;
        }

        var selected = $select.val();
        var values = Array.isArray(selected) ? selected.slice() : [];
        var byId = {};
        values.forEach(function(v) {
            var parsed = pokehubParsePokemonGenderToken(v);
            if (!parsed) {
                return;
            }
            // Priorité à la dernière sélection pour un Pokémon donné.
            byId[parsed.id] = parsed.gender ? (parsed.id + '|' + parsed.gender) : parsed.id;
        });
        var normalizedValues = Object.keys(byId).map(function(pid) { return byId[pid]; });
        if (!$select.data('pokehub-inline-gender-syncing')) {
            if (normalizedValues.join(',') !== values.join(',')) {
                $select.data('pokehub-inline-gender-syncing', true);
                $select.val(normalizedValues).trigger('change.select2');
                $select.data('pokehub-inline-gender-syncing', false);
                return;
            }
        }

        var genderMap = {};
        normalizedValues.forEach(function(v) {
            var parsed = pokehubParsePokemonGenderToken(v);
            if (parsed && (parsed.gender === 'male' || parsed.gender === 'female')) {
                genderMap[parsed.id] = parsed.gender;
            }
        });
        $select.attr('data-existing-genders', JSON.stringify(genderMap));

        $wrapper.empty().hide();
        Object.keys(genderMap).forEach(function(pid) {
            var name = nameTemplate.indexOf('__POKEMON_ID__') !== -1
                ? nameTemplate.replace(/__POKEMON_ID__/g, pid)
                : nameTemplate;
            $('<input>', {
                type: 'hidden',
                name: name,
                value: genderMap[pid]
            }).appendTo($wrapper);
        });
    }

    var pokehubGenderProfileCache = {};
    function pokehubFetchGenderProfile(pokemonId, cfg) {
        var deferred = $.Deferred();
        var key = String(parseInt(pokemonId, 10));
        if (!cfg || !cfg.ajax_url || !cfg.nonce || !key || key === 'NaN' || key === '0') {
            deferred.resolve(null);
            return deferred.promise();
        }
        if (Object.prototype.hasOwnProperty.call(pokehubGenderProfileCache, key)) {
            deferred.resolve(pokehubGenderProfileCache[key]);
            return deferred.promise();
        }

        $.post(cfg.ajax_url, {
            action: 'pokehub_check_pokemon_gender_dimorphism',
            pokemon_id: key,
            nonce: cfg.nonce
        }).done(function(resp) {
            var profile = null;
            if (resp && resp.success && resp.data && typeof resp.data === 'object') {
                profile = resp.data;
            }
            pokehubGenderProfileCache[key] = profile;
            deferred.resolve(profile);
        }).fail(function() {
            pokehubGenderProfileCache[key] = null;
            deferred.resolve(null);
        });

        return deferred.promise();
    }

    function pokehubBuildGenderSelectHtml(pokemonId, profile, selectedGender, nameTemplate, scope) {
        if (!profile || !profile.has_gender_dimorphism) {
            return '';
        }
        var source = (scope === 'spawn') ? profile.spawn_available_genders : profile.available_genders;
        var genders = Array.isArray(source) ? source.filter(function(g) { return g === 'male' || g === 'female'; }) : [];
        if (genders.length < 2) {
            return '';
        }

        var pid = String(parseInt(pokemonId, 10));
        var safeTemplate = String(nameTemplate || '');
        if (!safeTemplate) {
            return '';
        }
        var name = safeTemplate.indexOf('__POKEMON_ID__') !== -1
            ? safeTemplate.replace(/__POKEMON_ID__/g, pid)
            : safeTemplate;
        var def = profile.default_gender === 'female' ? 'female' : 'male';
        var defaultLabel = def === 'female' ? 'Defaut (Femelle)' : 'Defaut (Male)';
        var current = (selectedGender === 'male' || selectedGender === 'female') ? selectedGender : '';

        var html = '<div class="pokehub-pokemon-gender-row" style="margin:6px 0;">';
        html += '<label style="display:flex;align-items:center;gap:8px;">';
        html += '<span>Sexe #' + pid + ' :</span>';
        html += '<select name="' + name + '" data-pokemon-id="' + pid + '" style="min-width:170px;">';
        html += '<option value="">' + defaultLabel + '</option>';
        for (var i = 0; i < genders.length; i++) {
            var g = genders[i];
            var label = g === 'female' ? 'Femelle' : 'Male';
            html += '<option value="' + g + '"' + (current === g ? ' selected' : '') + '>' + label + '</option>';
        }
        html += '</select>';
        html += '</label>';
        html += '</div>';
        return html;
    }

    function pokehubRefreshGenderSelector($select) {
        if (!$select || !$select.length) {
            return;
        }
        if ($select.attr('multiple') !== undefined) {
            pokehubSyncInlineGenderHiddenInputs($select);
            return;
        }
        var cfg = pokehubResolvePokemonGenderConfig();
        if (!cfg) {
            return;
        }
        var nameTemplate = $select.attr('data-gender-name-template') || '';
        if (!nameTemplate) {
            return;
        }
        var scope = ($select.attr('data-gender-scope') || 'available') === 'spawn' ? 'spawn' : 'available';
        var wrapperSelector = $select.attr('data-gender-wrapper-selector') || '';
        var $wrapper = wrapperSelector ? $select.closest('.pokehub-gender-field-group').find(wrapperSelector).first() : $select.closest('.pokehub-gender-field-group').find('.pokehub-pokemon-gender-options').first();
        if (!$wrapper.length) {
            return;
        }

        var rawSelected = $select.val();
        var selectedList = [];
        if (Array.isArray(rawSelected)) {
            selectedList = rawSelected;
        } else if (rawSelected !== null && rawSelected !== undefined && String(rawSelected) !== '') {
            selectedList = [rawSelected];
        }
        var selectedIds = selectedList.map(function(v) {
            return String(parseInt(v, 10));
        }).filter(function(v) {
            return v !== 'NaN' && v !== '0';
        });

        if (!selectedIds.length) {
            $wrapper.empty().hide();
            return;
        }

        var currentMap = {};
        $wrapper.find('select[data-pokemon-id]').each(function() {
            var pid = String($(this).attr('data-pokemon-id') || '');
            var g = String($(this).val() || '');
            if ((g === 'male' || g === 'female') && pid) {
                currentMap[pid] = g;
            }
        });
        var initialMap = pokehubNormalizeGenderMap($select.attr('data-existing-genders'));
        Object.keys(initialMap).forEach(function(pid) {
            if (!Object.prototype.hasOwnProperty.call(currentMap, pid)) {
                currentMap[pid] = initialMap[pid];
            }
        });

        var requestId = Date.now().toString() + Math.random().toString(16).slice(2);
        $select.attr('data-gender-request-id', requestId);

        var calls = selectedIds.map(function(pid) {
            return pokehubFetchGenderProfile(pid, cfg);
        });

        $.when.apply($, calls).done(function() {
            if ($select.attr('data-gender-request-id') !== requestId) {
                return;
            }

            var profiles = [];
            if (calls.length === 1) {
                profiles = [arguments[0]];
            } else {
                profiles = Array.prototype.slice.call(arguments);
            }

            var html = '';
            for (var i = 0; i < selectedIds.length; i++) {
                var pid = selectedIds[i];
                var profile = profiles[i] || null;
                html += pokehubBuildGenderSelectHtml(pid, profile, currentMap[pid] || '', nameTemplate, scope);
            }

            if (html) {
                $wrapper.html(html).show();
            } else {
                $wrapper.empty().hide();
            }
        });
    }

    function pokehubInitPokemonGenderSelectors(context) {
        var $ctx = context ? $(context) : $(document);
        $ctx.find('select.pokehub-gender-driven-select').each(function() {
            pokehubRefreshGenderSelector($(this));
        });
    }
    
    function pokehubInitGoPassBonusRewardSelect2(context) {
        var $ctx = context ? $(context) : $(document);
        var l10n = typeof window.pokehubGoPassL10n !== 'undefined' ? window.pokehubGoPassL10n : {};
        var placeholder = l10n.bonusNone || '—';
        $ctx.find('select.pokehub-go-pass-bonus-reward-select').each(function() {
            var $select = $(this);
            if ($select.data('select2')) {
                $select.select2('destroy');
            }
            $select.select2({
                width: '100%',
                placeholder: $select.attr('data-placeholder') || placeholder,
                allowClear: true,
                matcher: typeof pokehubMultilingualMatcher !== 'undefined' ? pokehubMultilingualMatcher : undefined,
                language: {
                    noResults: function() { return 'Aucun résultat'; },
                    searching: function() { return 'Recherche…'; }
                }
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

        var largeListSelector = '#pokehub-wild-pokemon-select, #pokehub-forced-shiny-select, #pokehub-rare-pokemon-select, #pokehub-new-pokemon-select, select.pokehub-eggs-pokemon-select, select.pokehub-eggs-pool-select, .pokehub-habitats-metabox select.pokehub-select-pokemon, .pokehub-collection-challenges-metabox select.pokehub-select-pokemon, .pokehub-featured-pokemon-hours-metabox select.pokehub-select-pokemon';
        $ctx.find(largeListSelector).each(function() {
            var $select = $(this);
            if ($select.data('select2')) return;
            var dimorphicOnly = String($select.attr('data-gender-dimorphic-only') || '') === '1';
            var isMultiple = $select.attr('multiple') !== undefined;
            var inlineGenderMode = isMultiple && $select.hasClass('pokehub-gender-driven-select');
            var existingGenderMap = pokehubNormalizeGenderMap($select.attr('data-existing-genders'));

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
            ids = pokehubApplyInlineGenderTokensToIds(ids, existingGenderMap, inlineGenderMode);

            if (useAjax && ids.length) {
                var labelMap = {};
                ids.forEach(function(id) {
                    var $o = $select.find('option[value="' + id + '"]');
                    if ($o.length) {
                        labelMap[id] = $o.text();
                        return;
                    }
                    var parsed = pokehubParsePokemonGenderToken(id);
                    if (!parsed) {
                        labelMap[id] = '#' + id;
                        return;
                    }
                    var base = pokemonMap[parsed.id] || ('#' + parsed.id);
                    labelMap[id] = parsed.gender ? pokehubInlineGenderOptionLabel(base, parsed.gender) : base;
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
                        return {
                            search: t,
                            q: t,
                            term: t,
                            dimorphic_only: dimorphicOnly ? 1 : 0
                        };
                    },
                    processResults: function(data) {
                        if (Array.isArray(data)) return { results: pokehubExpandPokemonOptionsWithGenderVariants(data, inlineGenderMode) };
                        return { results: [] };
                    },
                    cache: true
                };
            } else if (inlineGenderMode && list.length) {
                opts.data = pokehubExpandPokemonOptionsWithGenderVariants(list, true);
                if (typeof pokehubMultilingualMatcher !== 'undefined') {
                    opts.matcher = pokehubMultilingualMatcher;
                }
            }
            $select.select2(opts);
            if (inlineGenderMode) {
                $select.off('change.pokehubInlineGender').on('change.pokehubInlineGender', function() {
                    pokehubSyncInlineGenderHiddenInputs($select);
                });
            }
            if (ids.length) {
                $select.val(ids).trigger('change.select2');
            }
            if (inlineGenderMode) {
                pokehubSyncInlineGenderHiddenInputs($select);
            }
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
    window.pokehubInitGoPassBonusRewardSelect2 = pokehubInitGoPassBonusRewardSelect2;
    window.pokehubInitLargePokemonSelect2 = pokehubInitLargePokemonSelect2;
    window.pokehubInitPokemonGenderSelectors = pokehubInitPokemonGenderSelectors;

    pokehubInitAttackSelect2(document);
    pokehubInitWeatherSelect2(document);
    pokehubInitItemSelect2(document);
    pokehubInitLureSelect2(document);
    pokehubInitPokemonSelect2(document);
    pokehubInitLargePokemonSelect2(document);
    // Field Research / quêtes : toujours document (les selects ne sont pas dans la metabox études spéciales).
    // Si on limitait au conteneur SR, les quêtes sur l’article ne recevaient pas Select2 et affichaient la liste HTML brute.
    pokehubInitQuestPokemonSelect2(document);
    pokehubInitQuestItemSelect2(document);
    pokehubInitPokemonGenderSelectors(document);

    $(document).on('change', 'select.pokehub-gender-driven-select', function() {
        pokehubRefreshGenderSelector($(this));
    });
    
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

