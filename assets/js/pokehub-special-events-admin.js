// assets/js/pokehub-special-events-admin.js

jQuery(function ($) {

    function initEventTypeSelect2($select) {
        if (!$select.length || typeof $.fn.select2 === 'undefined') {
            return;
        }

        // Eviter la double initialisation.
        if ($select.hasClass('select2-hidden-accessible')) {
            return;
        }

        var placeholder = $select.data('placeholder') || 'Search event type...';
        var width = $select.attr('id') === 'filter-by-event-type' ? '200px' : '100%';

        $select.select2({
            placeholder: placeholder,
            allowClear: true,
            width: width,
            language: {
                noResults: function () {
                    return 'No results found';
                },
                searching: function () {
                    return 'Searching...';
                }
            }
        });
    }

    function initAllEventTypeSelect2() {
        // Formulaire d'ajout/édition des événements spéciaux.
        initEventTypeSelect2($('#event_type.pokehub-event-type-select2'));

        // Filtre de type sur la liste des événements.
        initEventTypeSelect2($('#filter-by-event-type.pokehub-event-type-select'));

        // Couverture large en admin : tout select lié à event_type.
        $('select[name*="event_type"], select[id*="event_type"], .pokehub-event-type-select2, .pokehub-event-type-select').each(function () {
            initEventTypeSelect2($(this));
        });
    }

    initAllEventTypeSelect2();

    // Fonction helper pour la recherche multilingue (utiliser la version globale si disponible)
    var pokehubMultilingualMatcher = window.pokehubMultilingualMatcher || function(params, data) {
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

    // Initialiser Select2 pour tous les selects de Pokémon existants
    function initPokemonSelect2($select) {
        if (!$select.length || typeof $.fn.select2 === 'undefined') {
            return;
        }
        
        // Vérifier si Select2 est déjà initialisé sur cet élément
        if ($select.hasClass('select2-hidden-accessible')) {
            // Détruire l'instance existante avant de réinitialiser
            try {
                $select.select2('destroy');
            } catch(e) {
                // Ignorer les erreurs de destruction
            }
        }
        
        // Initialiser Select2 uniquement si l'élément est visible
        if ($select.is(':visible')) {
            $select.select2({
                placeholder: 'Select a Pokémon',
                allowClear: true,
                width: '100%',
                matcher: pokehubMultilingualMatcher,
                language: {
                    noResults: function() {
                        return "No results found";
                    },
                    searching: function() {
                        return "Searching...";
                    }
                }
            });
        }
    }

    // Initialiser Select2 pour tous les selects de Pokémon au chargement
    // Exclure le template (caché) de l'initialisation
    // Utiliser un délai pour s'assurer que Select2 est chargé
    setTimeout(function() {
        $('.pokehub-event-pokemon-row:not(.template) .pokehub-pokemon-select').each(function() {
            const $select = $(this);
            // Double vérification : ne pas initialiser si c'est dans un template
            if (!$select.closest('.pokehub-event-pokemon-row').hasClass('template')) {
                initPokemonSelect2($select);
            }
        });
        $('.pokehub-event-pokemon-row:not(.template)').each(function () {
            pokehubSyncRegionalOverrideRow($(this));
        });
    }, 100);

    // Certains champs du metabox article peuvent être injectés dynamiquement.
    if (window.MutationObserver && document.getElementById('admin_lab_event_box')) {
        var eventTypeObserver = new MutationObserver(function () {
            initAllEventTypeSelect2();
        });
        eventTypeObserver.observe(document.getElementById('admin_lab_event_box'), {
            childList: true,
            subtree: true
        });
    }

    function addPokemonRow() {
        const $wrapper = $('#pokehub-event-pokemon-wrapper');
        const $tmpl = $wrapper.find('.pokehub-event-pokemon-row.template').first();
        
        if (!$tmpl.length) {
            console.error('Template not found');
            return;
        }
        
        // Cloner le template et retirer la classe template
        const $row = $tmpl.clone(true, true); // Cloner avec les données et événements
        $row.removeClass('template');
        $row.show();
        $wrapper.append($row);
        
        // Initialiser Select2 pour le nouveau select après un court délai
        // pour s'assurer que l'élément est bien visible dans le DOM
        setTimeout(function() {
            const $newSelect = $row.find('.pokehub-pokemon-select');
            
            // Vérifier que ce n'est pas le template
            if ($row.hasClass('template')) {
                console.warn('Trying to initialize Select2 on template');
                return;
            }
            
            // Détruire Select2 s'il existe déjà (au cas où)
            if ($newSelect.hasClass('select2-hidden-accessible')) {
                try {
                    $newSelect.select2('destroy');
                } catch(e) {
                    // Ignorer les erreurs
                }
            }
            
            initPokemonSelect2($newSelect);
            pokehubSyncRegionalOverrideRow($row);
        }, 50);
    }

    function addBonusRow() {
        const $wrapper = $('#pokehub-event-bonuses-wrapper');
        const $tmpl = $wrapper.find('.pokehub-event-bonus-row.template').first();
        const $row = $tmpl.clone().removeClass('template').show();
        $wrapper.append($row);
    }

    /**
     * Affiche la case « disponibilité mondiale » uniquement pour les Pokémon marqués régionaux en base.
     */
    function pokehubSyncRegionalOverrideRow($row) {
        const $sel = $row.find('.pokehub-pokemon-select');
        const $wrap = $row.find('.pokehub-worldwide-override-wrap');
        const el = $sel[0];
        const opt = el && el.selectedIndex >= 0 ? el.options[el.selectedIndex] : null;
        const pokemonId = $sel.val();
        if (!pokemonId || !opt) {
            $wrap.hide();
            $wrap.find('.pokehub-worldwide-override').prop('checked', false);
            return;
        }
        const regional = opt.getAttribute('data-is-regional') === '1';
        if (regional) {
            $wrap.show();
        } else {
            $wrap.hide();
            $wrap.find('.pokehub-worldwide-override').prop('checked', false);
        }
    }

    // Délégation : changement de Pokémon → charger les attaques spéciales et afficher/masquer le champ gender
    $('#pokehub-event-pokemon-wrapper').on('change', '.pokehub-pokemon-select', function () {
        const $select = $(this);
        const pokemonId = $select.val();
        const $row = $select.closest('.pokehub-event-pokemon-row');
        const $attacksContainer = $row.find('.pokehub-pokemon-attacks');
        const $genderContainer = $row.find('.pokehub-pokemon-gender');

        pokehubSyncRegionalOverrideRow($row);

        $attacksContainer.empty();

        if (!pokemonId) {
            $genderContainer.hide();
            return;
        }
        
        // Vérifier si le pokémon a un dysmorphisme de genre
        // On va faire une requête AJAX pour récupérer cette info
        $.post(PokeHubSpecialEvents.ajax_url, {
            action: 'pokehub_check_pokemon_gender_dimorphism',
            nonce: PokeHubSpecialEvents.nonce,
            pokemon_id: pokemonId
        }, function (resp) {
            const data = resp && resp.success && resp.data ? resp.data : null;
            const available = data && Array.isArray(data.available_genders) ? data.available_genders : ['male', 'female'];
            const canChooseGender = !!(data && data.has_gender_dimorphism && available.length > 1);

            if (canChooseGender) {
                $genderContainer.show();
            } else {
                $genderContainer.hide();
            }
        });

        $.post(PokeHubSpecialEvents.ajax_url, {
            action: 'pokehub_get_pokemon_special_attacks',
            nonce: PokeHubSpecialEvents.nonce,
            pokemon_id: pokemonId
        }, function (resp) {
            if (!resp || !resp.success || !resp.data) {
                $attacksContainer.text('No special moves found for this Pokémon.');
                return;
            }

            const attacks = resp.data; // [{id, name}]
            if (!attacks.length) {
                $attacksContainer.text('No special moves found for this Pokémon.');
                return;
            }

            const $list = $('<div class="pokehub-pokemon-attacks-list"></div>');
            attacks.forEach(function (atk) {
                const id = atk.id;
                const label = atk.name;

                const $line = $('<label style="display:block; margin-bottom:3px;"></label>');
                const $cb = $('<input type="checkbox" class="pokehub-attack-checkbox">')
                    .attr('data-attack-id', id);

                const $forced = $('<input type="checkbox" class="pokehub-attack-forced">')
                    .attr('data-attack-id', id)
                    .css('margin-left', '8px');

                $line.append($cb).append(' ' + label + ' ');
                $line.append($('<span>').text('(special)'));
                $line.append($('<span style="margin-left:8px;">Forced:</span>'));
                $line.append($forced);

                $list.append($line);
            });

            $attacksContainer.append($list);
        });
    });

    // Délégation : suppression de ligne Pokémon
    $('#pokehub-event-pokemon-wrapper').on('click', '.pokehub-remove-pokemon-row', function () {
        $(this).closest('.pokehub-event-pokemon-row').remove();
    });

    // Délégation : suppression de ligne bonus
    $('#pokehub-event-bonuses-wrapper').on('click', '.pokehub-remove-bonus-row', function () {
        $(this).closest('.pokehub-event-bonus-row').remove();
    });

    // Boutons "Ajouter"
    $('#pokehub-add-pokemon-row').on('click', function (e) {
        e.preventDefault();
        addPokemonRow();
    });
    
    // Si aucun select n'est visible au chargement, ajouter automatiquement une ligne
    // (pour améliorer l'UX en mode "add")
    if ($('.pokehub-event-pokemon-row:not(.template)').length === 0) {
        // Ne pas ajouter automatiquement, laisser l'utilisateur cliquer sur "Ajouter un Pokémon"
        // Mais s'assurer que le premier ajout fonctionne bien
    }

    $('#pokehub-add-bonus-row').on('click', function () {
        addBonusRow();
    });

    // Sérialiser avant submit
    $('#pokehub-special-event-form').on('submit', function () {
        const pokemons = [];
        $('#pokehub-event-pokemon-wrapper .pokehub-event-pokemon-row:not(.template)').each(function () {
            const $row = $(this);
            const pokemonId = $row.find('.pokehub-pokemon-select').val();
            if (!pokemonId) {
                return;
            }

            const attacks = [];
            $row.find('.pokehub-pokemon-attacks-list .pokehub-attack-checkbox').each(function () {
                const $cb = $(this);
                if (!$cb.is(':checked')) {
                    return;
                }
                const attackId = $cb.data('attack-id');
                const forced = $row.find('.pokehub-attack-forced[data-attack-id="' + attackId + '"]').is(':checked');

                attacks.push({
                    id: attackId,
                    forced: forced ? 1 : 0
                });
            });

            const gender = $row.find('.pokehub-pokemon-gender-select').val() || null;

            pokemons.push({
                pokemon_id: pokemonId,
                gender: gender,
                is_forced_shadow: $row.find('.pokehub-force-shadow').is(':checked') ? 1 : 0,
                is_forced_shiny: $row.find('.pokehub-force-shiny').is(':checked') ? 1 : 0,
                is_worldwide_override: $row.find('.pokehub-worldwide-override').is(':checked') ? 1 : 0,
                region_note: ($row.find('.pokehub-region-note-legacy').val() || '').trim(),
                attacks: attacks
            });
        });

        const bonuses = [];
        $('#pokehub-event-bonuses-wrapper .pokehub-event-bonus-row:not(.template)').each(function () {
            const $row = $(this);
            const bonusId = $row.find('.pokehub-bonus-select').val();
            const desc = $row.find('.pokehub-bonus-description').val();
            if (!bonusId) {
                return;
            }

            bonuses.push({
                bonus_id: bonusId,
                description: desc
            });
        });

        $('#pokehub-pokemon-payload').val(JSON.stringify(pokemons));
        $('#pokehub-bonuses-payload').val(JSON.stringify(bonuses));
    });

});
