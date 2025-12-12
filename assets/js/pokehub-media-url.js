// assets/js/pokehub-media-url.js
(function($){

    // 1) State pour l’onglet URL
    const PokeHubTypesUrlState = wp.media.controller.State.extend({
        defaults: {
            id: 'pokehub-types-url',
            title: (window.pokemonTypesMedia && pokemonTypesMedia.tabUrl) || 'Insert from URL',
            priority: 200,
            menu: 'default',
            menuItem: {
                text: (window.pokemonTypesMedia && pokemonTypesMedia.tabUrl) || 'Insert from URL',
                priority: 200
            },
            content: 'pokehub-types-url'
        },

        initialize: function(){
            this.props = new Backbone.Model({
                url: ''
            });
        }
    });

    // 2) Vue du contenu de l’onglet URL (champ + preview)
    wp.media.view.PokeHubTypesUrlContent = wp.media.View.extend({
        className: 'pokehub-types-url-content',

        events: {
            'input  .pokehub-url-input':  'updateUrl',
            'keydown .pokehub-url-input': 'maybeInsert'
        },

        render: function() {
            const labels = window.pokemonTypesMedia || {};
            const inputLabel = labels.inputLabel || 'Image URL:';
            const inputDesc  = labels.inputDesc  || 'Enter a direct image URL.';

            this.$el.html(
                '<div class="pokehub-url-wrap">' +
                    '<label>' + inputLabel + '</label>' +
                    '<input type="text" class="widefat pokehub-url-input" />' +
                    '<p class="description">' + inputDesc + '</p>' +
                    '<div class="pokehub-url-preview-wrap" style="margin-top:10px;display:none;">' +
                        '<img class="pokehub-url-preview" src="" ' +
                             'style="max-width:100%;height:auto;border:1px solid #ddd;padding:2px;border-radius:3px;" />' +
                    '</div>' +
                '</div>'
            );

            this.delegateEvents();
            this.ready();
            return this;
        },

        ready: function() {
            const state = this.controller.state();
            const url   = state.props.get('url') || '';

            const $input   = this.$('.pokehub-url-input');
            const $preview = this.$('.pokehub-url-preview');
            const $wrap    = this.$('.pokehub-url-preview-wrap');

            if (url) {
                $input.val(url);
                if (/^(https?:)\/\//i.test(url)) {
                    $preview.attr('src', url);
                    $wrap.show();
                }
            }
        },

        updateUrl: function(e) {
            const val   = $(e.currentTarget).val().trim();
            const state = this.controller.state();
            state.props.set('url', val);

            const isValid = /^(https?:)\/\//i.test(val);
            const btn     = this.controller.$el.find('.media-button-select');
            if (!btn.length) return;

            const $preview = this.$('.pokehub-url-preview');
            const $wrap    = this.$('.pokehub-url-preview-wrap');

            if (isValid) {
                btn.removeAttr('disabled');
                btn.off('click.pokeHubTypes').on('click.pokeHubTypes', () => {
                    const url = state.props.get('url');
                    if (/^(https?:)\/\//i.test(url)) {
                        this.controller.trigger('insert', state);
                        this.controller.close();
                    }
                });

                $preview.attr('src', val);
                $wrap.show();
            } else {
                btn.attr('disabled', 'disabled').off('click.pokeHubTypes');
                $wrap.hide();
                $preview.attr('src', '');
            }
        },

        maybeInsert: function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const state = this.controller.state();
                const url   = state.props.get('url');
                if (/^(https?:)\/\//i.test(url)) {
                    this.controller.trigger('insert', state);
                    this.controller.close();
                }
            }
        }
    });

    // 3) Frame Media avec onglet URL
    wp.media.view.MediaFrame.PokeHubTypes = wp.media.view.MediaFrame.Select.extend({
        initialize: function() {
            wp.media.view.MediaFrame.Select.prototype.initialize.apply(this, arguments);

            // Ajout de notre state custom
            this.states.add(new PokeHubTypesUrlState({ frame: this }));

            // Création du contenu de l’onglet
            this.on('content:create:pokehub-types-url', this.createUrlContent, this);
        },

        createUrlContent: function(region) {
            region.view = new wp.media.view.PokeHubTypesUrlContent({
                controller: this,
                model: this.state().props
            });
        }
    });

    // 4) Initialisation sur l’admin Poké HUB (Types + Events spéciaux)
    $(document).ready(function(){

        if (typeof pokemonTypesMedia === 'undefined' &&
            typeof pokemonEventsMedia === 'undefined') {
            return;
        }

        /**
         * === TYPES ===
         * Bouton "Choisir dans la médiathèque" pour les types (icône de type)
         */
        $(document).on('click', '.pokehub-type-icon-select', function(e){
            e.preventDefault();

            const $field   = $(this).closest('.pokehub-type-icon-field');
            const $input   = $field.find('.pokehub-type-icon-url');
            const $preview = $field.find('.pokehub-type-icon-preview');

            const frame = new wp.media.view.MediaFrame.PokeHubTypes({
                title: pokemonTypesMedia.selectTitle || 'Select or Upload Image',
                button: {
                    text: pokemonTypesMedia.buttonText || 'Use this image'
                },
                multiple: false
            });

            // Pré-remplir l’onglet URL avec la valeur actuelle
            frame.on('open', function(){
                const state = frame.state('pokehub-types-url');
                if (state) {
                    state.props.set({
                        url: $input.val() || ''
                    });
                }
            });

            // Sélection depuis la médiathèque
            frame.on('select', function(){
                const attachment = frame.state().get('selection').first();
                if (!attachment) return;

                const data = attachment.toJSON();
                $input.val(data.url);
                $preview
                    .attr('src', data.url)
                    .show();
            });

            // Insertion via l’onglet URL
            frame.on('insert', function(state){
                if (!state || state.id !== 'pokehub-types-url') return;
                const url = state.props.get('url');
                if (!url) return;

                $input.val(url);
                $preview
                    .attr('src', url)
                    .show();
            });

            frame.open();
        });

        /**
         * === ÉVÉNEMENTS SPÉCIAUX ===
         * On ne gère QUE une URL (hidden), aucune notion d'image_id ici.
         */
        $(document).on('click', '.pokehub-select-event-image', function(e){
            e.preventDefault();

            const $field    = $('#pokehub-special-event-image-field');
            const $urlInput = $field.find('#event_image_url'); // hidden input
            let   $preview  = $field.find('.pokehub-event-image-preview');
            const $remove   = $field.find('.pokehub-remove-event-image');

            const frame = new wp.media.view.MediaFrame.PokeHubTypes({
                title: (window.pokemonEventsMedia && pokemonEventsMedia.selectTitle) || 'Select or Upload Image',
                button: {
                    text: (window.pokemonEventsMedia && pokemonEventsMedia.buttonText) || 'Use this image'
                },
                multiple: false
            });

            // Pré-remplir l’onglet URL avec la valeur actuelle
            frame.on('open', function(){
                const state = frame.state('pokehub-types-url');
                if (state) {
                    state.props.set({
                        url: $urlInput.val() || ''
                    });
                }
            });

            // Sélection depuis la médiathèque (attachment classique)
            frame.on('select', function(){
                const attachment = frame.state().get('selection').first();
                if (!attachment) return;

                const data = attachment.toJSON();
                if (!data.url) return;

                $urlInput.val(data.url);

                if (!$preview.length) {
                    // Si le markup de base n’a pas encore d’img, on l’injecte
                    $field.find('.image-preview').html(
                        '<img src="' + data.url + '" class="pokehub-event-image-preview" style="max-width:100%;height:auto;display:block;">'
                    );
                    $preview = $field.find('.pokehub-event-image-preview');
                } else {
                    $preview
                        .attr('src', data.url)
                        .show();
                }

                $remove.show();
            });

            // Insertion via l’onglet URL (onglet custom "Insert from URL")
            frame.on('insert', function(state){
                if (!state || state.id !== 'pokehub-types-url') return;

                const url = state.props.get('url');
                if (!url) return;

                $urlInput.val(url);

                if (!$preview.length) {
                    $field.find('.image-preview').html(
                        '<img src="' + url + '" class="pokehub-event-image-preview" style="max-width:100%;height:auto;display:block;">'
                    );
                    $preview = $field.find('.pokehub-event-image-preview');
                } else {
                    $preview
                        .attr('src', url)
                        .show();
                }

                $remove.show();
            });

            frame.open();
        });

        // Bouton "Remove image" pour les événements spéciaux
        $(document).on('click', '.pokehub-remove-event-image', function(e){
            e.preventDefault();

            const $field    = $('#pokehub-special-event-image-field');
            const $urlInput = $field.find('#event_image_url');
            const $preview  = $field.find('.pokehub-event-image-preview');

            $urlInput.val('');

            if ($preview.length) {
                $preview
                    .attr('src', '')
                    .hide();
            }

            // On remet un petit texte "No image selected yet."
            const noImageText =
                (window.pokemonEventsMedia && pokemonEventsMedia.noImage) ||
                (window.pokemonTypesMedia && pokemonTypesMedia.noImage) ||
                'No image selected yet.';

            $field.find('.image-preview').html(
                '<p class="description" style="margin:0;">' + noImageText + '</p>'
            );

            $(this).hide();
        });

        // Bouton "Remove image" pour les types
        $(document).on('click', '.pokehub-type-icon-remove', function(e){
            e.preventDefault();

            const $field   = $(this).closest('.pokehub-type-icon-field');
            const $input   = $field.find('.pokehub-type-icon-url');
            const $preview = $field.find('.pokehub-type-icon-preview');

            $input.val('');
            $preview
                .attr('src', '')
                .hide();

            $(this).prop('disabled', true);
        });

    });

})(jQuery);
