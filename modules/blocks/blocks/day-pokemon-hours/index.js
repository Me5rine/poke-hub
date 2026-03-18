/**
 * Bloc Gutenberg "Day Pokémon Hours" – UI éditeur (attributs).
 *
 * Le contenu est rendu côté serveur (render.php).
 */
(function() {
    'use strict';

    if (typeof wp === 'undefined' || !wp.blocks) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var __ = wp.i18n.__;
    var el = wp.element.createElement;
    var useBlockProps = wp.blockEditor && wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor && wp.blockEditor.InspectorControls;
    var PanelBody = wp.components && wp.components.PanelBody;
    var SelectControl = wp.components && wp.components.SelectControl;
    var TextControl = wp.components && wp.components.TextControl;

    registerBlockType('pokehub/day-pokemon-hours', {
        edit: function(props) {
            var attributes = props.attributes || {};
            var setAttributes = props.setAttributes || function() {};

            var contentType = attributes.contentType || 'featured_hours';
            var title = attributes.title || '';

            var blockProps = useBlockProps
                ? useBlockProps({ className: 'pokehub-block-placeholder' })
                : { className: 'pokehub-block-placeholder' };

            var inspectorControls = null;
            if (InspectorControls && PanelBody && SelectControl && TextControl) {
                inspectorControls = el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Options', 'poke-hub'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Content type', 'poke-hub'),
                            value: contentType,
                            options: [
                                { value: 'raids', label: __('Raids', 'poke-hub') },
                                { value: 'eggs', label: __('Eggs', 'poke-hub') },
                                { value: 'incense', label: __('Incense', 'poke-hub') },
                                { value: 'lures', label: __('Lures', 'poke-hub') },
                                { value: 'featured_hours', label: __('Featured Hours', 'poke-hub') },
                                { value: 'quests', label: __('Quests', 'poke-hub') }
                            ],
                            onChange: function(val) { setAttributes({ contentType: val }); }
                        }),
                        el(TextControl, {
                            label: __('Block title', 'poke-hub'),
                            value: title,
                            onChange: function(val) { setAttributes({ title: val || '' }); },
                            help: __('Optional. Default depends on selected content type.', 'poke-hub')
                        })
                    )
                );
            }

            return el(
                'div',
                blockProps,
                inspectorControls,
                el('p', {}, __('Day Pokémon Hours', 'poke-hub')),
                el('small', {}, __('Pick the type and set an optional title.', 'poke-hub'))
            );
        },
        save: function() {
            // Bloc dynamique : pas de save.
            return null;
        }
    });
})();

