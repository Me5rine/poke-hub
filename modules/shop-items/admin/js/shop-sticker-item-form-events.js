(function ($) {
    'use strict';

    function init() {
        var c = window.pokehubShopStickerItemForm;
        var $sel = $('#pokehub_shop_sticker_item_events');
        if (!c || !$sel.length || !$.fn.select2) {
            return;
        }

        $sel.select2({
            width: '100%',
            placeholder: c.placeholder || '',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: c.ajaxUrl,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        action: 'pokehub_shop_sticker_special_events_search',
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
    }

    $(init);
})(jQuery);
