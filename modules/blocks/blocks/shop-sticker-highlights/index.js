(function () {
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
    var ToggleControl = wp.components && wp.components.ToggleControl;

    registerBlockType('pokehub/shop-sticker-highlights', {
        edit: function (props) {
            var attributes = props.attributes || {};
            var setAttributes = props.setAttributes || function () {};
            var autoDetect = attributes.autoDetect !== undefined ? attributes.autoDetect : true;
            var blockProps = useBlockProps
                ? useBlockProps({ className: 'pokehub-block-placeholder' })
                : { className: 'pokehub-block-placeholder' };

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
                            onChange: function (value) {
                                setAttributes({ autoDetect: value });
                            },
                            help: __(
                                'Uses the In-game stickers metabox on this post (hero image + items).',
                                'poke-hub'
                            )
                        })
                    )
                );
            }

            return el(
                'div',
                blockProps,
                inspectorControls,
                el('p', {}, __('In-game sticker highlights', 'poke-hub')),
                el(
                    'small',
                    {},
                    __(
                        'Displays the hero image and sticker items configured in the post metabox.',
                        'poke-hub'
                    )
                )
            );
        },
        save: function () {
            return null;
        }
    });
})();
