/**
 * Bloc "Oeufs Pokémon" – éditeur
 */
(function() {
    'use strict';

    if (typeof wp === 'undefined' || !wp.blocks) return;

    var registerBlockType = wp.blocks.registerBlockType;
    var __ = wp.i18n.__;
    var el = wp.element.createElement;
    var useBlockProps = wp.blockEditor && wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor && wp.blockEditor.InspectorControls;
    var PanelBody = wp.components && wp.components.PanelBody;
    var SelectControl = wp.components && wp.components.SelectControl;
    var TextControl = wp.components && wp.components.TextControl;

    registerBlockType('pokehub/eggs', {
        edit: function(props) {
            var attributes = props.attributes || {};
            var setAttributes = props.setAttributes || function() {};
            var source = attributes.source || 'post';
            var poolId = attributes.poolId || 0;
            var title = attributes.title || '';

            var blockProps = useBlockProps ? useBlockProps({ className: 'pokehub-block-placeholder' }) : { className: 'pokehub-block-placeholder' };

            var inspector = null;
            if (InspectorControls && PanelBody && SelectControl) {
                inspector = el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Options', 'poke-hub'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Source', 'poke-hub'),
                            value: source,
                            options: [
                                { value: 'post', label: __('From article/event', 'poke-hub') },
                                { value: 'global', label: __('Global pool (current period)', 'poke-hub') }
                            ],
                            onChange: function(val) { setAttributes({ source: val }); }
                        }),
                        source === 'global' ? el(TextControl, {
                            label: __('Pool ID (optional)', 'poke-hub'),
                            type: 'number',
                            value: poolId,
                            onChange: function(val) { setAttributes({ poolId: parseInt(val, 10) || 0 }); },
                            help: __('Leave 0 to use the active pool for the current date.', 'poke-hub')
                        }) : null,
                        el(TextControl, {
                            label: __('Block title', 'poke-hub'),
                            value: title,
                            onChange: function(val) { setAttributes({ title: val || '' }); },
                            help: __('Optional. Default: "Eggs".', 'poke-hub')
                        })
                    )
                );
            }

            return el(
                'div',
                blockProps,
                inspector,
                el('p', {}, __('Pokémon Eggs', 'poke-hub')),
                el('small', {}, __('Displays egg types and Pokémon list (shiny, regional, CP).', 'poke-hub'))
            );
        },
        save: function() {
            return null;
        }
    });
})();
