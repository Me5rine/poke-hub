(function () {
    "use strict";

    if (typeof wp === "undefined" || !wp.blocks) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var __ = wp.i18n.__;
    var el = wp.element.createElement;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;
    var useState = wp.element.useState;
    var useBlockProps = wp.blockEditor && wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor && wp.blockEditor.InspectorControls;
    var PanelBody = wp.components && wp.components.PanelBody;
    var BaseControl = wp.components && wp.components.BaseControl;
    var TextControl = wp.components && wp.components.TextControl;
    var Button = wp.components && wp.components.Button;
    var apiFetch = wp.apiFetch;

    function normalizeEntry(raw) {
        raw = raw || {};
        var pokemonId = parseInt(raw.pokemonId || 0, 10) || 0;
        var attackIds = Array.isArray(raw.specialAttackIds) ? raw.specialAttackIds : [];
        return {
            pokemonId: pokemonId,
            specialAttackIds: attackIds.map(function (id) { return parseInt(id, 10) || 0; }).filter(function (id) { return id > 0; })
        };
    }

    function ensureEntries(attributes) {
        var entries = Array.isArray(attributes.entries) ? attributes.entries.map(normalizeEntry) : [];
        if (entries.length === 0) {
            var legacyPokemon = parseInt(attributes.pokemonId || 0, 10) || 0;
            var legacyAttack = parseInt(attributes.specialAttackId || 0, 10) || 0;
            entries = [{ pokemonId: legacyPokemon, specialAttackIds: legacyAttack > 0 ? [legacyAttack] : [] }];
        }
        return entries;
    }

    registerBlockType("pokehub/community-day", {
        edit: function (props) {
            var attributes = props.attributes || {};
            var setAttributes = props.setAttributes || function () {};
            var clientId = props.clientId || "";
            var entries = ensureEntries(attributes);
            var evolutionStart = attributes.evolutionStart || "";
            var evolutionEnd = attributes.evolutionEnd || "";
            var blockProps = useBlockProps ? useBlockProps({ className: "pokehub-block-placeholder" }) : { className: "pokehub-block-placeholder" };

            var attackListsState = useState({});
            var attackListsByIndex = attackListsState[0];
            var setAttackListsByIndex = attackListsState[1];
            var pokemonRefs = useRef({});
            var attackRefs = useRef({});

            function setEntries(nextEntries) {
                var normalized = (nextEntries || []).map(normalizeEntry);
                setAttributes({
                    entries: normalized,
                    pokemonId: normalized[0] ? normalized[0].pokemonId : 0,
                    specialAttackId: normalized[0] && normalized[0].specialAttackIds[0] ? normalized[0].specialAttackIds[0] : 0
                });
            }

            function updateEntryAt(index, patch) {
                var next = entries.map(function (entry, idx) {
                    if (idx !== index) {
                        return entry;
                    }
                    return normalizeEntry({
                        pokemonId: patch && Object.prototype.hasOwnProperty.call(patch, "pokemonId") ? patch.pokemonId : entry.pokemonId,
                        specialAttackIds: patch && Object.prototype.hasOwnProperty.call(patch, "specialAttackIds") ? patch.specialAttackIds : entry.specialAttackIds
                    });
                });
                setEntries(next);
            }

            useEffect(function () {
                if (!apiFetch) {
                    return;
                }
                entries.forEach(function (entry, idx) {
                    var pid = parseInt(entry.pokemonId || 0, 10) || 0;
                    if (pid <= 0) {
                        setAttackListsByIndex(function (prev) {
                            var copy = Object.assign({}, prev);
                            copy[idx] = [];
                            return copy;
                        });
                        return;
                    }
                    apiFetch({ path: "/poke-hub/v1/pokemon-special-attacks?include_family=1&pokemon_id=" + encodeURIComponent(String(pid)) })
                        .then(function (data) {
                            var list = Array.isArray(data) ? data : [];
                            setAttackListsByIndex(function (prev) {
                                var copy = Object.assign({}, prev);
                                copy[idx] = list;
                                return copy;
                            });
                            if (list.length > 0) {
                                var keep = {};
                                list.forEach(function (a) {
                                    keep[parseInt(a.id, 10) || 0] = true;
                                });
                                var filtered = (entry.specialAttackIds || []).filter(function (aid) {
                                    return !!keep[parseInt(aid, 10) || 0];
                                });
                                if (filtered.length !== (entry.specialAttackIds || []).length) {
                                    updateEntryAt(idx, { specialAttackIds: filtered });
                                }
                            }
                        })
                        .catch(function () {
                            setAttackListsByIndex(function (prev) {
                                var copy = Object.assign({}, prev);
                                copy[idx] = [];
                                return copy;
                            });
                        });
                });
            }, [JSON.stringify(entries.map(function (entry) { return entry.pokemonId || 0; }))]);

            var pokemonSig = JSON.stringify(entries.map(function (entry) { return entry.pokemonId || 0; }));
            var attackListsSig = entries
                .map(function (_, idx) {
                    var L = attackListsByIndex[idx];
                    return (Array.isArray(L) ? L : [])
                        .map(function (a) {
                            return String(parseInt(a.id, 10) || 0);
                        })
                        .filter(function (s) {
                            return s !== "0";
                        })
                        .sort()
                        .join(",");
                })
                .join("|");
            var attackSelSig = JSON.stringify(entries.map(function (entry) { return entry.specialAttackIds || []; }));

            useEffect(function () {
                var cancelled = false;
                var attempts = 0;

                function bindPokemonRows() {
                    if (cancelled) {
                        return;
                    }
                    var S = window.pokehubCommunityDayEditorSelects;
                    var $ = window.jQuery;
                    if (!S || !$ || !$.fn || !$.fn.select2) {
                        if (attempts++ < 160) {
                            window.setTimeout(bindPokemonRows, 40);
                        }
                        return;
                    }

                    entries.forEach(function (entry, idx) {
                        var pokemonEl = pokemonRefs.current[idx];
                        if (pokemonEl) {
                            S.bindPokemon(pokemonEl, {
                                selectedId: parseInt(entry.pokemonId || 0, 10) || 0,
                                placeholder: __("Rechercher un Pokémon…", "poke-hub"),
                                onChange: function (nextId) {
                                    updateEntryAt(idx, { pokemonId: nextId || 0, specialAttackIds: [] });
                                }
                            });
                        }
                    });
                }

                bindPokemonRows();

                return function () {
                    cancelled = true;
                    var S = window.pokehubCommunityDayEditorSelects;
                    if (S) {
                        entries.forEach(function (_, idx) {
                            S.destroy(pokemonRefs.current[idx], null);
                        });
                    }
                };
            }, [clientId, pokemonSig]);

            useEffect(function () {
                var cancelled = false;
                var attempts = 0;

                function bindAttackRows() {
                    if (cancelled) {
                        return;
                    }
                    var S = window.pokehubCommunityDayEditorSelects;
                    var $ = window.jQuery;
                    if (!S || !$ || !$.fn || !$.fn.select2) {
                        if (attempts++ < 160) {
                            window.setTimeout(bindAttackRows, 40);
                        }
                        return;
                    }

                    entries.forEach(function (entry, idx) {
                        var attackEl = attackRefs.current[idx];
                        if (!attackEl) {
                            return;
                        }
                        if ((parseInt(entry.pokemonId || 0, 10) || 0) <= 0) {
                            S.destroy(null, attackEl);
                        } else {
                            S.bindAttackLocalMulti(
                                attackEl,
                                Array.isArray(attackListsByIndex[idx]) ? attackListsByIndex[idx] : [],
                                entry.specialAttackIds || [],
                                __("Choisir une ou plusieurs attaques…", "poke-hub"),
                                function (nextAttackIds) {
                                    updateEntryAt(idx, { specialAttackIds: nextAttackIds || [] });
                                }
                            );
                        }
                    });
                }

                bindAttackRows();

                return function () {
                    cancelled = true;
                    var S = window.pokehubCommunityDayEditorSelects;
                    if (S) {
                        entries.forEach(function (_, idx) {
                            S.destroy(null, attackRefs.current[idx]);
                        });
                    }
                };
            }, [clientId, pokemonSig, attackListsSig, attackSelSig]);

            var inspector = InspectorControls && PanelBody && TextControl
                ? el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __("Community Day", "poke-hub"), initialOpen: true },
                        entries.map(function (entry, idx) {
                            var pid = parseInt(entry.pokemonId || 0, 10) || 0;
                            return el(
                                "div",
                                { key: "cd-row-" + idx, className: "pokehub-community-day-row", style: { marginBottom: "16px", paddingBottom: "12px", borderBottom: "1px solid #e0e0e0" } },
                                el("strong", null, __("Pokemon du Community Day", "poke-hub") + " #" + (idx + 1)),
                                el(
                                    BaseControl,
                                    { label: __("Pokemon", "poke-hub") },
                                    el("select", {
                                        ref: function (node) { pokemonRefs.current[idx] = node; },
                                        className: "pokehub-community-day-select pokehub-community-day-select--pokemon widefat",
                                        "aria-label": __("Pokemon", "poke-hub")
                                    })
                                ),
                                el(
                                    BaseControl,
                                    {
                                        label: __("Attaques speciales (lignee d'evolution)", "poke-hub"),
                                        help: pid ? __("Selection multiple autorisee.", "poke-hub") : __("Choisis un Pokemon d'abord.", "poke-hub")
                                    },
                                    el("select", {
                                        multiple: true,
                                        ref: function (node) { attackRefs.current[idx] = node; },
                                        className: "pokehub-community-day-select pokehub-community-day-select--attack widefat",
                                        disabled: !pid,
                                        "aria-label": __("Attaques speciales", "poke-hub")
                                    })
                                ),
                                Button ? el(Button, { isDestructive: true, isSecondary: true, onClick: function () {
                                    var next = entries.filter(function (_, i) { return i !== idx; });
                                    if (next.length === 0) {
                                        next = [{ pokemonId: 0, specialAttackIds: [] }];
                                    }
                                    setEntries(next);
                                } }, __("Supprimer ce Pokemon", "poke-hub")) : null
                            );
                        }),
                        Button ? el(Button, { isPrimary: true, onClick: function () {
                            var next = entries.slice();
                            next.push({ pokemonId: 0, specialAttackIds: [] });
                            setEntries(next);
                        } }, __("Ajouter un autre Pokemon", "poke-hub")) : null,
                        el(TextControl, {
                            label: __("Debut periode evolution", "poke-hub"),
                            type: "datetime-local",
                            value: evolutionStart,
                            onChange: function (value) { setAttributes({ evolutionStart: value || "" }); }
                        }),
                        el(TextControl, {
                            label: __("Fin periode evolution", "poke-hub"),
                            type: "datetime-local",
                            value: evolutionEnd,
                            onChange: function (value) { setAttributes({ evolutionEnd: value || "" }); }
                        })
                    )
                )
                : null;

            var configuredCount = entries.filter(function (entry) {
                return (parseInt(entry.pokemonId || 0, 10) || 0) > 0;
            }).length;
            var label = configuredCount > 0
                ? __("Bloc Community Day configure.", "poke-hub")
                : __("Choisis un Pokemon pour generer le bloc Community Day.", "poke-hub");

            return el(
                "div",
                blockProps,
                inspector,
                el("p", null, label),
                el("small", null, __("Le front affichera la lignee shiny, l'attaque speciale, les mega boost et les IV PvP rank 1.", "poke-hub"))
            );
        },
        save: function () {
            return null;
        }
    });
})();
