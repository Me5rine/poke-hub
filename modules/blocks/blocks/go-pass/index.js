/**
 * Bloc Pass GO — éditeur (réglages : metabox « GO Pass (block) » sous l’article).
 */
(function () {
    'use strict';

    if (typeof wp === 'undefined' || !wp.blocks) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var __ = wp.i18n.__;
    var el = wp.element.createElement;
    var useBlockProps = wp.blockEditor && wp.blockEditor.useBlockProps;

    registerBlockType('pokehub/go-pass', {
        edit: function () {
            var blockProps = useBlockProps
                ? useBlockProps({ className: 'pokehub-block-placeholder' })
                : { className: 'pokehub-block-placeholder' };

            return el(
                'div',
                blockProps,
                el('p', {}, __('GO Pass', 'poke-hub')),
                el(
                    'small',
                    {},
                    __(
                        'Choose the linked GO Pass and display mode in the “GO Pass (block)” box below the editor.',
                        'poke-hub'
                    )
                )
            );
        },
        save: function () {
            return null;
        },
    });
})();
