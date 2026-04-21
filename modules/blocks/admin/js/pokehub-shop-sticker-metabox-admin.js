(function ($) {
    'use strict';

    function init() {
        var c = window.pokehubShopStickerMetabox;
        if (!c || !c.ajaxUrl || !$.fn.select2) {
            return;
        }

        var $sel = $('#pokehub_shop_sticker_item_ids');
        if (!$sel.length) {
            return;
        }

        $sel.select2({
            width: '100%',
            placeholder: c.i18n && c.i18n.searchPlaceholder ? c.i18n.searchPlaceholder : '',
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: c.ajaxUrl,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        action: 'pokehub_shop_sticker_items_search',
                        nonce: c.nonce,
                        q: params.term || ''
                    };
                },
                processResults: function (response) {
                    if (!response || !response.success || !response.data || !response.data.results) {
                        return { results: [] };
                    }
                    return {
                        results: response.data.results.map(function (r) {
                            return { id: r.id, text: r.text };
                        })
                    };
                }
            }
        });

        var $btnCreate = $('#pokehub-shop-sticker-create-submit');
        var $inEn = $('#pokehub-shop-sticker-create-name-en');
        var $inFr = $('#pokehub-shop-sticker-create-name-fr');
        var $hint = $('#pokehub-shop-sticker-create-inline-hint');
        var hintTimer;

        function showCreateHint(msg, isError) {
            if (hintTimer) {
                window.clearTimeout(hintTimer);
                hintTimer = null;
            }
            $hint.text(msg).css('color', isError ? '#b32d2e' : '#50575e').show();
            if (!isError) {
                hintTimer = window.setTimeout(function () {
                    $hint.fadeOut(200, function () {
                        $hint.text('').show().css('color', '');
                    });
                }, 5000);
            }
        }

        function failMessage(xhr) {
            var msg = (c.i18n && c.i18n.createFailed) || '';
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                msg = xhr.responseJSON.data.message;
            }
            return msg;
        }

        if ($btnCreate.length) {
            $btnCreate.on('click', function () {
                var nameEn = $inEn.length ? String($inEn.val() || '').trim() : '';
                if (!nameEn) {
                    showCreateHint((c.i18n && c.i18n.nameEnRequired) || '', true);
                    if ($inEn.length) {
                        $inEn.trigger('focus');
                    }
                    return;
                }
                var nameFr = $inFr.length ? String($inFr.val() || '').trim() : '';
                var postId = parseInt(c.postId, 10) || 0;
                $btnCreate.prop('disabled', true);
                if (hintTimer) {
                    window.clearTimeout(hintTimer);
                    hintTimer = null;
                }
                $hint.hide().text('').css('color', '');
                $.post(c.ajaxUrl, {
                    action: 'pokehub_shop_sticker_metabox_create_item',
                    nonce: c.nonce,
                    post_id: postId,
                    name_en: nameEn,
                    name_fr: nameFr
                })
                    .done(function (response) {
                        if (!response || !response.success || !response.data) {
                            showCreateHint((c.i18n && c.i18n.createFailed) || '', true);
                            return;
                        }
                        var d = response.data;
                        var idStr = String(parseInt(d.id, 10) || 0);
                        if (idStr === '0') {
                            showCreateHint((c.i18n && c.i18n.createFailed) || '', true);
                            return;
                        }
                        var opt = new Option(d.text, idStr, true, true);
                        $sel.append(opt).trigger('change');
                        $inEn.val('');
                        $inFr.val('');
                        showCreateHint((c.i18n && c.i18n.createSuccess) || '', false);
                        $inEn.trigger('focus');
                    })
                    .fail(function (xhr) {
                        showCreateHint(failMessage(xhr), true);
                    })
                    .always(function () {
                        $btnCreate.prop('disabled', false);
                    });
            });
        }

        var frame;
        var $heroId = $('#pokehub_shop_sticker_hero_id');
        var $prevWrap = $('#pokehub-shop-sticker-hero-preview-wrap');
        var $prevImg = $('#pokehub-shop-sticker-hero-preview');
        var $btnClear = $('#pokehub-shop-sticker-hero-clear');

        $('#pokehub-shop-sticker-hero-pick').on('click', function (e) {
            e.preventDefault();
            if (frame) {
                frame.open();
                return;
            }
            frame = wp.media({
                title: c.i18n && c.i18n.selectHero ? c.i18n.selectHero : '',
                button: { text: c.i18n && c.i18n.useHero ? c.i18n.useHero : '' },
                multiple: false
            });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                if (!att || !att.id) {
                    return;
                }
                $heroId.val(String(att.id));
                var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
                if (url) {
                    $prevImg.attr('src', url);
                    $prevWrap.show();
                    $btnClear.show();
                }
            });
            frame.open();
        });

        $btnClear.on('click', function (e) {
            e.preventDefault();
            $heroId.val('0');
            $prevWrap.hide();
            $prevImg.attr('src', '');
            $btnClear.hide();
        });
    }

    $(init);
})(jQuery);
