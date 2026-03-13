/**
 * Enregistrement côté client du bloc "Pokémon Sauvages"
 * 
 * Même si le bloc est entièrement rendu côté serveur (render.php),
 * WordPress a besoin de ce fichier JavaScript pour afficher le bloc dans l'éditeur.
 */
(function() {
    'use strict';

    // Vérifier que wp.blocks est disponible
    if (typeof wp === 'undefined' || !wp.blocks) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var __ = wp.i18n.__;
    var el = wp.element.createElement;
    var useBlockProps = wp.blockEditor && wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor && wp.blockEditor.InspectorControls;
    var PanelBody = wp.components && wp.components.PanelBody;
    var ToggleControl = wp.components && wp.components.ToggleControl;

    // Enregistrer le bloc (le rendu est géré par render.php)
    registerBlockType('pokehub/wild-pokemon', {
        edit: function(props) {
            var attributes = props.attributes || {};
            var setAttributes = props.setAttributes || function() {};
            var autoDetect = attributes.autoDetect !== undefined ? attributes.autoDetect : true;
            var showRareSection = attributes.showRareSection !== undefined ? attributes.showRareSection : true;
            
            var blockProps = useBlockProps ? useBlockProps({ className: 'pokehub-block-placeholder' }) : { className: 'pokehub-block-placeholder' };

            var inspectorControls = null;
            if (InspectorControls && PanelBody && ToggleControl) {
                inspectorControls = el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Options', 'poke-hub'), initialOpen: true },
                        el(ToggleControl, {
                            label: __('Auto detection', 'poke-hub'),
                            checked: autoDetect,
                            onChange: function(value) { setAttributes({ autoDetect: value }); },
                            help: __('Automatically retrieves Pokémon from the event', 'poke-hub')
                        }),
                        el(ToggleControl, {
                            label: __('Show rare section', 'poke-hub'),
                            checked: showRareSection,
                            onChange: function(value) { setAttributes({ showRareSection: value }); }
                        })
                    )
                );
            }

            return el(
                'div',
                blockProps,
                inspectorControls,
                el('p', {}, __('Pokémon in the wild', 'poke-hub')),
                el('small', {}, __('This block automatically displays wild Pokémon from the event.', 'poke-hub'))
            );
        },
        save: function() {
            // Bloc dynamique, pas de save
            return null;
        }
    });
})();

