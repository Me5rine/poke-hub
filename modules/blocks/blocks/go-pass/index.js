/**
 * Bloc Pass GO — éditeur (sélection + création brouillon via REST).
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
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useBlockProps = wp.blockEditor && wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor && wp.blockEditor.InspectorControls;
    var PanelBody = wp.components && wp.components.PanelBody;
    var SelectControl = wp.components && wp.components.SelectControl;
    var TextControl = wp.components && wp.components.TextControl;
    var Button = wp.components && wp.components.Button;
    var Spinner = wp.components && wp.components.Spinner;
    var apiFetch = wp.apiFetch;

    function GoPassEdit(props) {
        var attributes = props.attributes || {};
        var setAttributes = props.setAttributes || function () {};
        var specialEventId = attributes.specialEventId || 0;
        var displayMode = attributes.displayMode || 'summary';

        var blockProps = useBlockProps
            ? useBlockProps({ className: 'pokehub-block-placeholder pokehub-go-pass-block-editor' })
            : { className: 'pokehub-block-placeholder pokehub-go-pass-block-editor' };

        var stateItems = useState([]);
        var items = stateItems[0];
        var setItems = stateItems[1];

        var stateLoad = useState({ loading: false, err: '' });
        var loadState = stateLoad[0];
        var setLoadState = stateLoad[1];

        var stateCreate = useState({ busy: false, err: '' });
        var createState = stateCreate[0];
        var setCreateState = stateCreate[1];

        var newTitleEn = useState('');
        var newTitleFr = useState('');

        useEffect(
            function () {
                var cancelled = false;
                setLoadState({ loading: true, err: '' });
                apiFetch({ path: '/poke-hub/v1/go-pass-special-events' })
                    .then(function (data) {
                        if (!cancelled) {
                            setItems(Array.isArray(data) ? data : []);
                            setLoadState({ loading: false, err: '' });
                        }
                    })
                    .catch(function () {
                        if (!cancelled) {
                            setItems([]);
                            setLoadState({
                                loading: false,
                                err: __('Could not load GO Pass list. You need administrator rights.', 'poke-hub'),
                            });
                        }
                    });
                return function () {
                    cancelled = true;
                };
            },
            []
        );

        var selectOptions = [{ value: '0', label: __('— Select a GO Pass —', 'poke-hub') }];
        items.forEach(function (row) {
            selectOptions.push({
                value: String(row.id),
                label: row.label ? row.label + ' (#' + row.id + ')' : '#' + row.id,
            });
        });

        var inspector = null;
        if (InspectorControls && PanelBody && SelectControl) {
            inspector = el(
                InspectorControls,
                null,
                el(
                    PanelBody,
                    { title: __('GO Pass', 'poke-hub'), initialOpen: true },
                    loadState.loading
                        ? el(Spinner, null)
                        : loadState.err
                          ? el('p', { style: { color: '#b32d2e' } }, loadState.err)
                          : null,
                    el(SelectControl, {
                        label: __('Linked event', 'poke-hub'),
                        value: String(specialEventId || 0),
                        options: selectOptions,
                        onChange: function (val) {
                            var n = parseInt(val, 10) || 0;
                            setAttributes({ specialEventId: n });
                        },
                    }),
                    el(SelectControl, {
                        label: __('Display', 'poke-hub'),
                        value: displayMode,
                        options: [
                            { value: 'summary', label: __('Summary card', 'poke-hub') },
                            { value: 'full', label: __('Full reward grid', 'poke-hub') },
                        ],
                        onChange: function (val) {
                            setAttributes({ displayMode: val || 'summary' });
                        },
                    }),
                    specialEventId > 0 && items.length
                        ? (function () {
                              var row = items.filter(function (r) {
                                  return r.id === specialEventId;
                              })[0];
                              if (!row || !row.edit_url) {
                                  return null;
                              }
                              return el(
                                  'p',
                                  { className: 'pokehub-go-pass-editor-editlink' },
                                  el(
                                      'a',
                                      { href: row.edit_url, target: '_blank', rel: 'noreferrer' },
                                      __('Edit this GO Pass (admin)', 'poke-hub')
                                  )
                              );
                          })()
                        : null
                ),
                el(
                    PanelBody,
                    { title: __('Create empty GO Pass', 'poke-hub'), initialOpen: false },
                    el(TextControl, {
                        label: __('Title (EN)', 'poke-hub'),
                        value: newTitleEn[0],
                        onChange: newTitleEn[1],
                    }),
                    el(TextControl, {
                        label: __('Title (FR)', 'poke-hub'),
                        value: newTitleFr[0],
                        onChange: newTitleFr[1],
                    }),
                    createState.err
                        ? el('p', { style: { color: '#b32d2e' } }, createState.err)
                        : null,
                    el(
                        Button,
                        {
                            variant: 'secondary',
                            isBusy: createState.busy,
                            disabled: createState.busy,
                            onClick: function () {
                                setCreateState({ busy: true, err: '' });
                                apiFetch({
                                    path: '/poke-hub/v1/go-pass-special-events/new',
                                    method: 'POST',
                                    data: {
                                        title_en: newTitleEn[0] || '',
                                        title_fr: newTitleFr[0] || '',
                                    },
                                })
                                    .then(function (res) {
                                        var id = res && res.id ? parseInt(res.id, 10) : 0;
                                        if (id <= 0) {
                                            throw new Error('no_id');
                                        }
                                        setAttributes({ specialEventId: id });
                                        return apiFetch({ path: '/poke-hub/v1/go-pass-special-events' });
                                    })
                                    .then(function (data) {
                                        setItems(Array.isArray(data) ? data : []);
                                        setCreateState({ busy: false, err: '' });
                                    })
                                    .catch(function () {
                                        setCreateState({
                                            busy: false,
                                            err: __('Creation failed (administrator rights required).', 'poke-hub'),
                                        });
                                    });
                            },
                        },
                        __('Create draft & select', 'poke-hub')
                    )
                )
            );
        }

        var previewHint = __('Preview matches the front (summary or full grid).', 'poke-hub');

        return el(
            Fragment,
            null,
            inspector,
            el(
                'div',
                blockProps,
                el('p', { style: { fontWeight: 600 } }, __('GO Pass', 'poke-hub')),
                specialEventId > 0
                    ? el(
                          'small',
                          null,
                          displayMode === 'full'
                              ? __('Mode: full grid', 'poke-hub')
                              : __('Mode: summary', 'poke-hub'),
                          ' · ',
                          __('Event ID:', 'poke-hub'),
                          ' ',
                          String(specialEventId)
                      )
                    : el('small', null, __('Choose a Pass or create an empty one in the block sidebar.', 'poke-hub')),
                el('p', { className: 'pokehub-go-pass-editor-hint' }, previewHint)
            )
        );
    }

    registerBlockType('pokehub/go-pass', {
        edit: GoPassEdit,
        save: function () {
            return null;
        },
    });
})();
