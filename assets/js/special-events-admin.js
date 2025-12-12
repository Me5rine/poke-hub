// assets/js/special-events-admin.js

jQuery(function ($) {

    function addPokemonRow() {
        const $wrapper = $('#pokehub-event-pokemon-wrapper');
        const $tmpl = $wrapper.find('.pokehub-event-pokemon-row.template').first();
        const $row = $tmpl.clone().removeClass('template').show();
        $wrapper.append($row);
    }

    function addBonusRow() {
        const $wrapper = $('#pokehub-event-bonuses-wrapper');
        const $tmpl = $wrapper.find('.pokehub-event-bonus-row.template').first();
        const $row = $tmpl.clone().removeClass('template').show();
        $wrapper.append($row);
    }

    // Délégation : changement de Pokémon → charger les attaques spéciales
    $('#pokehub-event-pokemon-wrapper').on('change', '.pokehub-pokemon-select', function () {
        const $select = $(this);
        const pokemonId = $select.val();
        const $row = $select.closest('.pokehub-event-pokemon-row');
        const $attacksContainer = $row.find('.pokehub-pokemon-attacks');

        $attacksContainer.empty();

        if (!pokemonId) {
            return;
        }

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
    $('#pokehub-add-pokemon-row').on('click', function () {
        addPokemonRow();
    });

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

            pokemons.push({
                pokemon_id: pokemonId,
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
