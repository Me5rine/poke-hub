/**
 * Bloc Pass GO — éditeur (réglages : métabox + liaisons admin + optionnellement hôte explicite).
 */
(function () {
	'use strict';

	if (typeof wp === 'undefined' || !wp.blocks) {
		return;
	}

	var registerBlockType = wp.blocks.registerBlockType;
	var __ = wp.i18n.__;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useBlockProps =
		wp.blockEditor && wp.blockEditor.useBlockProps;
	var InspectorControls =
		wp.blockEditor && wp.blockEditor.InspectorControls;
	var PanelBody = wp.components && wp.components.PanelBody;
	var SelectControl = wp.components && wp.components.SelectControl;
	var TextControl = wp.components && wp.components.TextControl;

	registerBlockType('pokehub/go-pass', {
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps
				? useBlockProps({ className: 'pokehub-block-placeholder' })
				: { className: 'pokehub-block-placeholder' };

			var hostKind = attributes.hostKind || '';
			var hostId = attributes.hostId || 0;

			var inspector =
				InspectorControls && PanelBody && SelectControl && TextControl
					? el(
							InspectorControls,
							null,
							el(
								PanelBody,
								{ title: __('Host context (optional)', 'poke-hub'), initialOpen: false },
								el(SelectControl, {
									label: __('Host kind', 'poke-hub'),
									value: hostKind,
									options: [
										{ label: __('Auto (post + filters)', 'poke-hub'), value: '' },
										{ label: __('Local post ID', 'poke-hub'), value: 'local_post' },
										{ label: __('Remote post ID (JV Actu)', 'poke-hub'), value: 'remote_post' },
										{ label: __('Special event id', 'poke-hub'), value: 'special_event' },
									],
									onChange: function (v) {
										setAttributes({ hostKind: v || '' });
									},
								}),
								el(TextControl, {
									label: __('Host ID', 'poke-hub'),
									type: 'number',
									value: hostId ? String(hostId) : '',
									onChange: function (v) {
										var n = parseInt(v, 10);
										setAttributes({ hostId: isNaN(n) || n < 1 ? 0 : n });
									},
									help: __(
										'Leave “Auto” to use the metabox under the editor (this post, JV Actu id, or special event id). The pokehub_go_pass_host_from_context filter can still adjust resolution.',
										'poke-hub'
									),
								})
							)
					  )
					: null;

			return el(
				Fragment,
				null,
				inspector,
				el(
					'div',
					blockProps,
					el('p', {}, __('GO Pass', 'poke-hub')),
					el(
						'small',
						{},
						__(
							'Choose the linked GO Pass and host (this post, JV Actu ID, or special event id) in the “GO Pass (block)” box below the editor. Optional: override host in the block sidebar.',
							'poke-hub'
						)
					)
				)
			);
		},
		save: function () {
			return null;
		},
	});
})();
