/**
 * Bloc Pass GO — éditeur (réglages : métabox « GO Pass (block) » sous l’article).
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

			return el('div', blockProps, el('p', {}, __('GO Pass', 'poke-hub')));
		},
		save: function () {
			return null;
		},
	});
})();
