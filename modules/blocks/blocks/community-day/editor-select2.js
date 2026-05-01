/**
 * Select2 pour le bloc Community Day (éditeur Gutenberg) : Pokémon en AJAX, attaques en liste locale.
 */
(function (window, $) {
    "use strict";

    function getCfg() {
        var cfg = window.pokehubCommunityDayEditorCfg || {};
        if (!cfg.nonce && window.wpApiSettings && typeof window.wpApiSettings.nonce === "string") {
            cfg.nonce = window.wpApiSettings.nonce;
        }
        if (!cfg.restPokemon && window.wpApiSettings && typeof window.wpApiSettings.root === "string") {
            cfg.restPokemon = String(window.wpApiSettings.root).replace(/\/?$/, "/") + "poke-hub/v1/pokemon-for-select";
        }
        return cfg;
    }

    function destroyIfAny(el) {
        if (!el) {
            return;
        }
        var $el = $(el);
        if ($el.length && $el.data("select2")) {
            $el.select2("destroy");
        }
    }

    function finalizePokemonSelect($el, opts, nonce, restUrl, selId, placeholder) {
        var cfg = getCfg();

        $el.select2({
            width: "100%",
            allowClear: true,
            placeholder: placeholder,
            minimumInputLength: 1,
            language: cfg.select2Language || {
                searching: function () {
                    return cfg.searching || "…";
                },
                inputTooShort: function () {
                    return cfg.inputTooShort || cfg.searching || "…";
                }
            },
            ajax: {
                url: restUrl,
                dataType: "json",
                delay: 250,
                headers: nonce ? { "X-WP-Nonce": nonce } : {},
                data: function (params) {
                    return { search: params.term || "" };
                },
                processResults: function (data) {
                    if (!Array.isArray(data)) {
                        return { results: [] };
                    }
                    return {
                        results: data.map(function (p) {
                            var id = p.id != null ? String(p.id) : "";
                            var txt = (p.text != null) ? String(p.text) : String(p.name_fr || p.name_en || id);
                            return { id: id, text: txt };
                        })
                    };
                },
                cache: true
            }
        });

        if (selId > 0) {
            $el.val(String(selId)).trigger("change.select2");
        }

        $el.off("change.pokehubCdPokemon").on("change.pokehubCdPokemon", function () {
            var v = parseInt($el.val(), 10) || 0;
            if (typeof opts.onChange === "function") {
                opts.onChange(v);
            }
        });
    }

    /**
     * @param {HTMLSelectElement|null} el
     * @param {object} opts
     */
    window.pokehubCommunityDayEditorSelects = {
        destroy: function (pokemonEl, attackEl) {
            destroyIfAny(pokemonEl);
            destroyIfAny(attackEl);
        },

        bindPokemon: function (el, opts) {
            if (!el || !$ || !$.fn.select2) {
                return;
            }
            opts = opts || {};
            var cfg = getCfg();
            var nonce = opts.nonce || cfg.nonce || "";
            var restUrl = opts.restUrl || cfg.restPokemon || "";
            var placeholder = opts.placeholder || "";
            var $el = $(el);
            destroyIfAny(el);
            $el.empty();
            if (!restUrl) {
                return;
            }

            var selId = parseInt(opts.selectedId, 10) || 0;

            function run() {
                if (!$el.closest("body").length) {
                    return;
                }
                finalizePokemonSelect($el, opts, nonce, restUrl, selId, placeholder);
            }

            if (selId > 0) {
                $.ajax({
                    url: restUrl.replace(/\/?$/, "") + "?ids=" + encodeURIComponent(String(selId)),
                    headers: nonce ? { "X-WP-Nonce": nonce } : {},
                    dataType: "json"
                }).done(function (rows) {
                    $el.empty();
                    if (!rows || !Array.isArray(rows) || rows.length === 0) {
                        return;
                    }
                    var row = rows[0];
                    var text = row.text || row.name_fr || row.name_en || ("#" + selId);
                    $el.append(new Option(text, String(selId), true, true));
                }).always(run);
                return;
            }

            run();
        },

        bindAttackLocal: function (el, items, selectedId, placeholder, onChange) {
            if (!el || !$ || !$.fn.select2) {
                return;
            }
            destroyIfAny(el);
            var $el = $(el);
            $el.empty();
            $el.append(new Option(placeholder || "—", "", false, false));

            (items || []).forEach(function (a) {
                var id = parseInt(a.id, 10) || 0;
                if (id <= 0) {
                    return;
                }
                var name = a.name || ("#" + id);
                $el.append(new Option(name, String(id)));
            });

            var sel = parseInt(selectedId, 10) || 0;
            if (sel > 0) {
                $el.val(String(sel));
            }

            $el.select2({
                width: "100%",
                allowClear: true,
                placeholder: placeholder
            });

            $el.off("change.pokehubCdAttack").on("change.pokehubCdAttack", function () {
                var v = parseInt($el.val(), 10) || 0;
                if (typeof onChange === "function") {
                    onChange(v);
                }
            });
        },

        bindAttackLocalMulti: function (el, items, selectedIds, placeholder, onChange) {
            if (!el || !$ || !$.fn.select2) {
                return;
            }
            destroyIfAny(el);
            var $el = $(el);
            $el.empty();

            (items || []).forEach(function (a) {
                var id = parseInt(a.id, 10) || 0;
                if (id <= 0) {
                    return;
                }
                var name = a.name || ("#" + id);
                $el.append(new Option(name, String(id)));
            });

            var selected = Array.isArray(selectedIds) ? selectedIds : [];
            var normalizedSelected = selected.map(function (id) {
                return String(parseInt(id, 10) || 0);
            }).filter(function (id) {
                return id !== "0";
            });
            if (normalizedSelected.length > 0) {
                $el.val(normalizedSelected);
            }

            $el.select2({
                width: "100%",
                allowClear: true,
                multiple: true,
                placeholder: placeholder
            });

            $el.off("change.pokehubCdAttackMulti").on("change.pokehubCdAttackMulti", function () {
                var raw = $el.val();
                var values = Array.isArray(raw) ? raw : [];
                var out = values.map(function (id) {
                    return parseInt(id, 10) || 0;
                }).filter(function (id) {
                    return id > 0;
                });
                if (typeof onChange === "function") {
                    onChange(out);
                }
            });
        }
    };
})(window, window.jQuery);
