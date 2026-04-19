(function ($) {
    'use strict';

    function getCfg() {
        return window.pokehubGoPassMetabox || {};
    }

    /**
     * Titre « événement » Me5rine LAB (métabox #admin_lab_event_box), si présent à l’écran.
     */
    function readLabEventTitleFromDom() {
        var $box = $('#admin_lab_event_box');
        if (!$box.length) {
            return '';
        }
        var selectors = [
            'input[name="_event_title"]',
            'textarea[name="_event_title"]',
            'input[name="event_title"]',
            'input[name="_admin_lab_event_title"]',
            'input[name="_admin_lab_event_title_fr"]',
            'input[name="_admin_lab_event_title_en"]',
            'input[name*="admin_lab_event_title"]',
            'input[name*="[event_title]"]',
            '#admin_lab_event_title',
            '#_event_title'
        ];
        var i;
        for (i = 0; i < selectors.length; i++) {
            var $el = $box.find(selectors[i]).first();
            if ($el.length) {
                var v = $.trim(String($el.val() || ''));
                if (v) {
                    return v;
                }
            }
        }
        return '';
    }

    $(function () {
        var c = getCfg();
        var $sel = $('#pokehub_go_pass_special_event_id');
        if (!$sel.length || typeof $sel.select2 !== 'function') {
            return;
        }

        var ajaxUrl = c.ajaxUrl || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
        if (!ajaxUrl) {
            return;
        }

        $sel.select2({
            width: '100%',
            placeholder: c.placeholder || '',
            allowClear: false,
            minimumInputLength: 0,
            ajax: {
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        action: 'pokehub_go_pass_metabox_search',
                        nonce: c.nonce,
                        q: params.term || ''
                    };
                },
                processResults: function (response) {
                    if (!response || !response.success || !response.data || !$.isArray(response.data.results)) {
                        return { results: [] };
                    }
                    return { results: response.data.results };
                }
            },
            language: {
                noResults: function () {
                    return (c.strings && c.strings.noResults) || '';
                },
                searching: function () {
                    return (c.strings && c.strings.searching) || '';
                }
            }
        });

        var $btn = $('#pokehub-go-pass-create-inline');
        if (!$btn.length) {
            return;
        }

        $btn.on('click', function () {
            if ($btn.prop('disabled')) {
                return;
            }
            var postId = parseInt(c.postId, 10) || 0;
            if (postId <= 0) {
                window.alert((c.strings && c.strings.needsPostId) || '');
                return;
            }
            var articleTitle = '';
            if ($('#title').length) {
                articleTitle = $.trim(String($('#title').val() || ''));
            }
            if (!articleTitle && $('textarea[name="post_title"]').length) {
                articleTitle = $.trim(String($('textarea[name="post_title"]').val() || ''));
            }
            if (!articleTitle && $('.editor-post-title__input').length) {
                articleTitle = $.trim(String($('.editor-post-title__input').val() || ''));
            }
            var labEventTitle = readLabEventTitleFromDom();
            $btn.prop('disabled', true);
            $.post(ajaxUrl, {
                action: 'pokehub_go_pass_metabox_create_and_link',
                nonce: c.nonce,
                post_id: postId,
                lab_event_title: labEventTitle,
                article_title: articleTitle,
                display_mode: $('#pokehub_go_pass_display_mode').val() || 'summary'
            })
                .done(function (response) {
                    if (!response || !response.success || !response.data) {
                        var msg =
                            response && response.data && response.data.message
                                ? response.data.message
                                : (c.strings && c.strings.createFailed) || '';
                        window.alert(msg || 'Error');
                        return;
                    }
                    var d = response.data;
                    var idStr = String(parseInt(d.id, 10) || 0);
                    if (idStr === '0') {
                        window.alert((c.strings && c.strings.createFailed) || 'Error');
                        return;
                    }
                    if ($sel.find('option[value="' + idStr + '"]').length) {
                        $sel.val(idStr).trigger('change');
                    } else {
                        var opt = new Option(d.text, idStr, true, true);
                        $sel.append(opt).trigger('change');
                    }
                    var $hint = $('#pokehub-go-pass-create-inline-hint');
                    if ($hint.length && d.edit_url && c.strings && c.strings.editPassLabel) {
                        $hint.empty().show();
                        $('<a>', {
                            href: d.edit_url,
                            target: '_blank',
                            rel: 'noopener noreferrer',
                            text: c.strings.editPassLabel
                        }).appendTo($hint);
                    }
                })
                .fail(function (xhr) {
                    var msg = (c.strings && c.strings.createFailed) || '';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        msg = String(xhr.responseJSON.data.message);
                    }
                    window.alert(msg || 'Error');
                })
                .always(function () {
                    $btn.prop('disabled', false);
                });
        });
    });
})(jQuery);
