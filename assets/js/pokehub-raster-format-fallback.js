/**
 * Repli format raster bucket : ordre défini dans data-ph-raster (JSON d’URLs), en général WebP → PNG → JPG.
 * Écoute en capture pour devancer d’autres handlers d’erreur sur la même image.
 */
(function () {
    'use strict';

    document.addEventListener(
        'error',
        function (e) {
            var img = e.target;
            if (!img || img.tagName !== 'IMG') {
                return;
            }

            var raw = img.getAttribute('data-ph-raster');
            if (!raw) {
                return;
            }

            var urls;
            try {
                urls = JSON.parse(raw);
            } catch (err) {
                return;
            }

            if (!Array.isArray(urls) || urls.length < 2) {
                return;
            }

            var step = parseInt(img.getAttribute('data-ph-raster-step') || '0', 10);
            step += 1;
            if (step >= urls.length) {
                img.removeAttribute('data-ph-raster');
                img.removeAttribute('data-ph-raster-step');
                return;
            }

            e.preventDefault();
            e.stopImmediatePropagation();
            img.setAttribute('data-ph-raster-step', String(step));
            img.src = urls[step];
        },
        true
    );

    /** Helpers Select2 : data-ph-raster (JSON) ou data-icon legacy */
    (function (w, $) {
        if (!$) {
            return;
        }

        function pokeHubIconFromOption($option) {
            var raster = $option.attr('data-ph-raster');
            var legacy = $option.attr('data-icon');
            if (raster) {
                try {
                    var urls = JSON.parse(raster);
                    if (urls && urls.length) {
                        return { src: urls[0], raster: raster };
                    }
                } catch (err) {}
            }
            if (legacy) {
                return { src: legacy, raster: '' };
            }
            return null;
        }

        w.pokeHubSpanWithSelectIcon = function (text, $option) {
            var icon = pokeHubIconFromOption($option);
            if (!icon) {
                return text;
            }
            var $img = $('<img alt="" />')
                .attr('src', icon.src)
                .css({
                    width: '20px',
                    height: '20px',
                    marginRight: '8px',
                    verticalAlign: 'middle',
                    objectFit: 'contain'
                });
            if (icon.raster) {
                $img.attr('data-ph-raster', icon.raster);
            }
            return $('<span></span>').append($img).append(document.createTextNode(text));
        };

        /**
         * Templates Select2 (templateResult / templateSelection) pour options avec data-ph-raster ou data-icon.
         *
         * @param {JQuery} $select Élément &lt;select&gt; concerné
         * @return {{templateResult: Function, templateSelection: Function}}
         */
        w.pokeHubSelect2RasterTemplatesForSelect = function ($select) {
            function templateForData(data) {
                if (!data || data.id === '' || data.id === null || typeof data.id === 'undefined') {
                    return data ? data.text : '';
                }
                var id = String(data.id);
                var $option = $select.find('option').filter(function () {
                    return $(this).val() === id;
                }).first();
                if ($option.length && typeof w.pokeHubSpanWithSelectIcon === 'function') {
                    return w.pokeHubSpanWithSelectIcon(data.text, $option);
                }
                var raster = $option.attr('data-ph-raster');
                if (raster) {
                    try {
                        var urls = JSON.parse(raster);
                        if (urls && urls.length && urls[0]) {
                            var $img = $('<img alt="" />')
                                .attr('src', urls[0])
                                .attr('data-ph-raster', raster)
                                .css({
                                    width: '20px',
                                    height: '20px',
                                    marginRight: '8px',
                                    verticalAlign: 'middle',
                                    objectFit: 'contain'
                                });
                            return $('<span></span>').append($img).append(document.createTextNode(data.text));
                        }
                    } catch (err) {}
                }
                var legacy = $option.attr('data-icon');
                if (legacy) {
                    return $('<span></span>').append(
                        $('<img alt="" />')
                            .attr('src', legacy)
                            .css({
                                width: '20px',
                                height: '20px',
                                marginRight: '8px',
                                verticalAlign: 'middle',
                                objectFit: 'contain'
                            })
                    ).append(document.createTextNode(data.text));
                }
                return data.text;
            }

            return {
                templateResult: function (data) {
                    return templateForData(data);
                },
                templateSelection: function (data) {
                    return templateForData(data);
                }
            };
        };
    })(window, window.jQuery);

})();
