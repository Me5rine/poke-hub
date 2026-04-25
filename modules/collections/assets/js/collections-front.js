(function ($) {
    'use strict';

    var storageKey = 'poke_hub_collections';
    var offlineItemsStorageKey = 'poke_hub_collections_offline_items';
    var ownerKeyStoragePrefix = 'poke_hub_collections_owner_key_';

    function getCollectionOwnerKey(shareToken) {
        if (!shareToken) return '';
        try {
            return localStorage.getItem(ownerKeyStoragePrefix + shareToken) || '';
        } catch (e) {
            return '';
        }
    }

    function setCollectionOwnerKey(shareToken, ownerKey) {
        if (!shareToken || !ownerKey) return;
        try {
            localStorage.setItem(ownerKeyStoragePrefix + shareToken, ownerKey);
        } catch (e) {}
        try {
            document.cookie = 'pokehub_col_owner_' + shareToken + '=' + encodeURIComponent(ownerKey) + '; path=/; max-age=31536000; samesite=lax';
        } catch (e2) {}
    }

    function getWrapOwnerKey(wrap) {
        if (!wrap || !wrap.getAttribute) return '';
        var token = wrap.getAttribute('data-share-token') || '';
        return getCollectionOwnerKey(token);
    }

    function buildRestHeaders(base, wrap) {
        var out = base || {};
        if (typeof pokeHubCollections !== 'undefined' && pokeHubCollections && pokeHubCollections.nonce) {
            out['X-WP-Nonce'] = pokeHubCollections.nonce;
        }
        var ownerKey = getWrapOwnerKey(wrap);
        if (ownerKey) {
            out['X-PokeHub-Owner-Key'] = ownerKey;
        }
        return out;
    }

    function readOfflineItemsMap() {
        try {
            return JSON.parse(localStorage.getItem(offlineItemsStorageKey) || '{}') || {};
        } catch (e) {
            return {};
        }
    }

    function writeOfflineItemsMap(map) {
        try {
            localStorage.setItem(offlineItemsStorageKey, JSON.stringify(map || {}));
        } catch (e) {}
    }

    /**
     * L’événement « error » des <img> ne remonte pas au document (pas de bulles) :
     * la délégation sur document + capture ne reçoit rien, contrairement à l’admin
     * qui utilise onerror. On attache ici l’équivalent.
     * @param {Element|Document} root
     */
    function bindCollectionSpriteFallbacks(root) {
        if (!root || !root.querySelectorAll) {
            return;
        }
        var imgs = root.querySelectorAll('img.pokehub-collection-sprite[data-fallback]');
        for (var i = 0; i < imgs.length; i++) {
            var img = imgs[i];
            if (img.getAttribute('data-fallback-binded') === '1') {
                continue;
            }
            img.setAttribute('data-fallback-binded', '1');
            img.addEventListener('error', function onSpriteFallback() {
                var el = this;
                var fb = el.getAttribute('data-fallback');
                if (!fb) {
                    return;
                }
                el.removeAttribute('data-fallback');
                el.removeEventListener('error', onSpriteFallback);
                el.src = fb;
            });
        }
    }

    function escUrlAttr(s) {
        if (s == null) {
            return '';
        }
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function getEl(id) {
        return document.getElementById(id);
    }

    function getCheckedOrDefault(id, defaultValue) {
        var el = getEl(id);
        if (!el) return defaultValue;
        return !!el.checked;
    }

    /**
     * Réinitialisation en deux passes : pas de window.confirm, panneau aligné sur les hints du thème.
     * @param {Element} scope .pokehub-collections-reset-inline or ancestor
     * @param {function} onApply appelé au clic sur « Vider / Clear »
     */
    function setupCollectionResetInline(scope, onApply) {
        if (!scope || typeof onApply !== 'function') {
            return;
        }
        var block = scope.classList && scope.classList.contains('pokehub-collections-reset-inline')
            ? scope
            : scope.querySelector('.pokehub-collections-reset-inline');
        if (!block) {
            return;
        }
        var initial = block.querySelector('.pokehub-collections-reset-step-initial');
        var stepConfirm = block.querySelector('.pokehub-collections-reset-step-confirm');
        var launch = block.querySelector('.pokehub-collections-btn-reset-launch');
        var applyBtn = block.querySelector('.pokehub-collections-btn-reset-apply');
        var dismiss = block.querySelector('.pokehub-collections-btn-reset-dismiss');
        function showConfirm() {
            if (initial) initial.setAttribute('hidden', 'hidden');
            if (stepConfirm) stepConfirm.removeAttribute('hidden');
        }
        function hideConfirm() {
            if (initial) initial.removeAttribute('hidden');
            if (stepConfirm) stepConfirm.setAttribute('hidden', 'hidden');
        }
        if (launch) {
            launch.addEventListener('click', showConfirm);
        }
        if (dismiss) {
            dismiss.addEventListener('click', hideConfirm);
        }
        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                onApply();
                hideConfirm();
            });
        }
    }

    function cycleStatus(current) {
        if (current === 'missing') return 'owned';
        if (current === 'owned') return 'for_trade';
        return 'missing';
    }

    /**
     * Filtre d’affichage : masque ou réaffiche les tuiles selon le bloc « Afficher dans la grille »
     * (cases = statuts visibles : possédé / à l'échange / manquant → données owned / for_trade / missing).
     * @returns {function} fonction apply() à rappeler après changement de statut d’une tuile
     */
    var POKEHUB_TILE_FILTERED_OUT = 'pokehub-tile--filtered-out';
    var POKEHUB_GEN_ALL_FILTERED = 'pokehub-generation--all-filtered';

    function bindCollectionStatusFilters(wrap) {
        var tilesRoot = wrap.querySelector('.pokehub-collection-tiles') || wrap.querySelector('.pokehub-collection-tiles-local');
        var filterRoot = wrap.querySelector('.pokehub-collection-status-filters');
        if (!tilesRoot || !filterRoot) {
            return function () {};
        }
        var emptyHint = filterRoot.querySelector('.pokehub-collection-filter-empty-hint');

        function getShowMap() {
            var show = { owned: true, for_trade: true, missing: true };
            var cbs = filterRoot.querySelectorAll('input.pokehub-collection-filter-status');
            cbs.forEach(function (cb) {
                var st = cb.getAttribute('data-filter-status');
                if (st) show[st] = !!cb.checked;
            });
            return show;
        }

        function statusKey(tile) {
            var raw = (tile.getAttribute('data-status') || 'missing').toString();
            return raw.replace(/-/g, '_');
        }

        function generationHasVisibleChild(details) {
            var nodes = details.querySelectorAll('.pokehub-collection-tile');
            for (var i = 0; i < nodes.length; i++) {
                if (!nodes[i].classList.contains(POKEHUB_TILE_FILTERED_OUT)) {
                    return true;
                }
            }
            return false;
        }

        function apply() {
            var show = getShowMap();
            var any = show.owned || show.for_trade || show.missing;
            var tiles = tilesRoot.querySelectorAll('.pokehub-collection-tile');
            if (!any) {
                if (emptyHint) emptyHint.classList.remove('is-hidden');
                tiles.forEach(function (t) {
                    t.classList.add(POKEHUB_TILE_FILTERED_OUT);
                });
                tilesRoot.querySelectorAll('.pokehub-collection-generation-block').forEach(function (d) {
                    d.classList.add(POKEHUB_GEN_ALL_FILTERED);
                });
                return;
            }
            if (emptyHint) emptyHint.classList.add('is-hidden');
            tilesRoot.querySelectorAll('.pokehub-collection-generation-block').forEach(function (d) {
                d.classList.remove(POKEHUB_GEN_ALL_FILTERED);
            });
            tiles.forEach(function (tile) {
                var st = statusKey(tile);
                var on = show.hasOwnProperty(st) ? !!show[st] : true;
                if (on) {
                    tile.classList.remove(POKEHUB_TILE_FILTERED_OUT);
                } else {
                    tile.classList.add(POKEHUB_TILE_FILTERED_OUT);
                }
            });
            tilesRoot.querySelectorAll('.pokehub-collection-generation-block').forEach(function (details) {
                if (generationHasVisibleChild(details)) {
                    details.classList.remove(POKEHUB_GEN_ALL_FILTERED);
                } else {
                    details.classList.add(POKEHUB_GEN_ALL_FILTERED);
                }
            });
        }

        function onFilterEvent() {
            apply();
        }
        filterRoot.addEventListener('change', onFilterEvent, false);
        filterRoot.addEventListener('input', onFilterEvent, false);
        filterRoot.addEventListener('click', function (e) {
            if (e.target && e.target.closest && e.target.closest('label.pokehub-collection-status-filter-label')) {
                if (window.requestAnimationFrame) {
                    window.requestAnimationFrame(apply);
                } else {
                    setTimeout(apply, 0);
                }
            }
        }, false);

        apply();
        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(apply);
        } else {
            setTimeout(apply, 0);
        }
        return apply;
    }
    var createDrawer = document.getElementById('pokehub-collections-drawer-create');
    var createDrawerBackdrop = document.getElementById('pokehub-collections-drawer-create-backdrop');
    var createDrawerClose = document.getElementById('pokehub-collections-drawer-create-close');
    var btnCreate = document.querySelector('.pokehub-collections-btn-create');
    var btnCancel = document.querySelector('.pokehub-collections-modal-cancel');
    var btnCreateSubmit = document.querySelector('.pokehub-collections-modal-create-btn');

    function openCreateDrawer() {
        if (createDrawer) {
            createDrawer.setAttribute('aria-hidden', 'false');
            createDrawer.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeCreateDrawer() {
        if (createDrawer) {
            createDrawer.setAttribute('aria-hidden', 'true');
            createDrawer.classList.remove('is-open');
            document.body.style.overflow = '';
        }
    }

    if (btnCreate) {
        btnCreate.addEventListener('click', openCreateDrawer);
    }
    if (btnCancel) {
        btnCancel.addEventListener('click', closeCreateDrawer);
    }
    if (createDrawerBackdrop) {
        createDrawerBackdrop.addEventListener('click', closeCreateDrawer);
    }
    if (createDrawerClose) {
        createDrawerClose.addEventListener('click', closeCreateDrawer);
    }

    function getCreateCategoryHiddenList() {
        var m =
            typeof pokeHubCollections !== 'undefined' && pokeHubCollections.settingsHiddenByCategory
                ? pokeHubCollections.settingsHiddenByCategory
                : {};
        var c = (getEl('pokehub-collection-category') || {}).value || 'custom';
        return m[c] || [];
    }

    (function initCreateCategoryToggle() {
        var catSelect = document.getElementById('pokehub-collection-category');
        var poolShowFieldset = document.getElementById('pokehub-collection-pool-show-only-wrap');
        var contentFilterWrap = document.getElementById('pokehub-collection-content-filter-wrap');
        var onlyShinyWrap = document.getElementById('pokehub-collection-only-shiny-wrap');
        var specificCategories = [];
        if (catSelect && catSelect.parentNode) {
            try {
                var raw = catSelect.parentNode.getAttribute('data-specific-categories');
                if (raw) specificCategories = JSON.parse(raw);
            } catch (e) {}
        }
        function updateCreateContentControlsVisibility() {
            var hidden = getCreateCategoryHiddenList();
            if (contentFilterWrap) {
                var labels = contentFilterWrap.querySelectorAll('label[data-collections-control]');
                for (var j = 0; j < labels.length; j++) {
                    var lb = labels[j];
                    var k = lb.getAttribute('data-collections-control') || '';
                    if (hidden.indexOf(k) !== -1) {
                        lb.classList.add('is-hidden');
                    } else {
                        lb.classList.remove('is-hidden');
                    }
                }
            }
            var psel = document.getElementById('pokehub-collection-pool-show-only');
            if (psel) {
                var oopt = psel.querySelectorAll('option[data-collections-control]');
                for (var o = 0; o < oopt.length; o++) {
                    var op = oopt[o];
                    var ok = op.getAttribute('data-collections-control') || '';
                    if (ok && hidden.indexOf(ok) !== -1) {
                        op.setAttribute('hidden', 'hidden');
                    } else {
                        op.removeAttribute('hidden');
                    }
                }
                var v = psel.value;
                if (v) {
                    var curo = psel.querySelector('option[value="' + v + '"]');
                    if (curo && curo.getAttribute('hidden') === 'hidden') {
                        psel.value = '';
                    }
                }
            }
        }
        function updateCreateOptionsVisibility() {
            var category = catSelect ? catSelect.value : '';
            var isSpecific = specificCategories.indexOf(category) !== -1;
            var isLmu = category === 'legendary_mythical_ultra';
            if (poolShowFieldset) poolShowFieldset.classList.toggle('is-hidden', isSpecific || isLmu);
            if (contentFilterWrap) contentFilterWrap.classList.toggle('is-hidden', isSpecific);
            if (onlyShinyWrap) onlyShinyWrap.classList.toggle('is-hidden', category !== 'custom');
            updateCreateContentControlsVisibility();
        }
        if (catSelect) {
            catSelect.addEventListener('change', updateCreateOptionsVisibility);
            updateCreateOptionsVisibility();
        }
    })();

    function getCreateFormData() {
        var nameEl = getEl('pokehub-collection-name');
        var categoryEl = getEl('pokehub-collection-category');
        var publicEl = getEl('pokehub-collection-public');
        var addSelectorsEl = getEl('pokehub-collection-add-selectors');
        var cat = categoryEl && categoryEl.value ? categoryEl.value : 'custom';
        var h = getCreateCategoryHiddenList();
        function cBool(key, elId, def) {
            if (h.indexOf(key) !== -1) {
                if (key === 'include_special_attacks') {
                    return false;
                }
                if (key === 'include_both_sexes_collector') {
                    return false;
                }
                return true;
            }
            return getCheckedOrDefault(elId, def);
        }
        return {
            name: nameEl && typeof nameEl.value === 'string' ? nameEl.value.trim() : '',
            category: cat,
            is_public: !!(publicEl && publicEl.checked),
            options: {
                include_national_dex: getCheckedOrDefault('pokehub-collection-include-national', true),
                include_gender: cBool('include_gender', 'pokehub-collection-include-gender', true),
                include_both_sexes_collector: cBool('include_both_sexes_collector', 'pokehub-collection-both-sexes-collector', false),
                include_regional_forms: cBool('include_regional_forms', 'pokehub-collection-include-regional-forms', true),
                include_costumes: cBool('include_costumes', 'pokehub-collection-include-costumes', true),
                include_mega: cBool('include_mega', 'pokehub-collection-include-mega', true),
                include_gigantamax: cBool('include_gigantamax', 'pokehub-collection-include-gigantamax', true),
                include_dynamax: cBool('include_dynamax', 'pokehub-collection-include-dynamax', true),
                include_backgrounds: cBool('include_backgrounds', 'pokehub-collection-include-backgrounds', true),
                include_special_attacks: cBool('include_special_attacks', 'pokehub-collection-include-special-attacks', false),
                include_baby_pokemon: cBool('include_baby_pokemon', 'pokehub-collection-include-babies', true),
                pool_show_only: (function () {
                    var fs = document.getElementById('pokehub-collection-pool-show-only-wrap');
                    if (fs && fs.classList.contains('is-hidden')) {
                        return '';
                    }
                    var el = document.getElementById('pokehub-collection-pool-show-only');
                    if (!el) {
                        return '';
                    }
                    var v = el.value ? el.value : '';
                    if (!v) {
                        return '';
                    }
                    var opt = el.querySelector('option[value="' + v + '"]');
                    var ckey = opt && opt.getAttribute('data-collections-control');
                    if (ckey && h.indexOf(ckey) !== -1) {
                        return '';
                    }
                    return v;
                }()),
                only_shiny: cat === 'custom' ? getCheckedOrDefault('pokehub-collection-only-shiny', false) : false,
                include_legendary_pokemon: getCheckedOrDefault('pokehub-collection-include-legendary', true),
                include_mythical_pokemon: getCheckedOrDefault('pokehub-collection-include-mythical', true),
                include_ultra_beast_pokemon: getCheckedOrDefault('pokehub-collection-include-ultra-beast', true),
                one_per_species: getCheckedOrDefault('pokehub-collection-one-per-species', false),
                group_by_generation: getCheckedOrDefault('pokehub-collection-group-by-generation', true),
                generations_collapsed: getCheckedOrDefault('pokehub-collection-generations-collapsed', false),
                display_mode: addSelectorsEl && addSelectorsEl.checked ? 'tiles_select' : 'tiles',
                public: !!(publicEl && publicEl.checked),
                show_gender_symbols: cBool('include_gender', 'pokehub-collection-include-gender', true) || cBool('include_both_sexes_collector', 'pokehub-collection-both-sexes-collector', false),
            },
        };
    }

    if (btnCreateSubmit) {
        btnCreateSubmit.addEventListener('click', function () {
            var form = getCreateFormData();
            var isLoggedIn = typeof pokeHubCollections !== 'undefined' && pokeHubCollections.isLoggedIn;

            // Création via API (connecté ou anonyme) : même URL avec token
            var headers = { 'Content-Type': 'application/json' };
            if (pokeHubCollections && pokeHubCollections.nonce) {
                headers['X-WP-Nonce'] = pokeHubCollections.nonce;
            }
            fetch(pokeHubCollections.restUrl + 'collections', {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({
                    name: form.name || (form.category === 'shiny' ? 'Mes Shinies' : (form.category === 'legendary_mythical_ultra' && pokeHubCollections.i18n && pokeHubCollections.i18n.collectionDefaultNameLegendaryMythicalUltra ? pokeHubCollections.i18n.collectionDefaultNameLegendaryMythicalUltra : (form.category === 'legendary_mythical_ultra' ? 'Légendaires, fabuleux et ultra-chimères' : 'Ma collection'))),
                    category: form.category,
                    options: form.options,
                    is_public: isLoggedIn ? form.is_public : false,
                }),
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success && data.share_token) {
                        if (data.owner_key) {
                            setCollectionOwnerKey(data.share_token, data.owner_key);
                        }
                        closeCreateDrawer();
                        var base = window.location.origin + window.location.pathname.replace(/\/$/, '');
                        window.location.href = base + '/' + data.share_token;
                    } else if (data.success && data.collection_id) {
                        closeCreateDrawer();
                        var b = window.location.origin + window.location.pathname.replace(/\/$/, '');
                        window.location.href = b + '/' + (data.share_token || data.collection_id);
                    } else if (data.success) {
                        closeCreateDrawer();
                        window.location.reload();
                    } else {
                        alert(data.message || 'Erreur');
                    }
                })
                .catch(function () {
                    alert('Erreur réseau');
                });
        });
    }

    // Tiles: cycle status on click (missing -> owned -> for_trade -> missing)
    var tileContainer = document.querySelector('.pokehub-collection-tiles');
    if (tileContainer) {
        var viewWrapTiles = tileContainer.closest('.pokehub-collection-view-wrap');
        var applyStatusFilters = viewWrapTiles ? bindCollectionStatusFilters(viewWrapTiles) : function () {};
        var tileWrap = tileContainer.closest('.pokehub-collection-view-wrap');
        var canEdit = tileWrap && tileWrap.getAttribute('data-can-edit') === '1';
        var collectionId = tileWrap ? tileWrap.getAttribute('data-collection-id') : null;
        var isLocal = typeof window.location.search !== 'undefined' && window.location.search.indexOf('local=1') !== -1;
        var pool = [];
        try {
            pool = JSON.parse(tileContainer.getAttribute('data-pool') || '[]');
        } catch (e) {}
        var items = {};
        try {
            items = JSON.parse(tileContainer.getAttribute('data-items') || '{}');
        } catch (e) {}

        tileContainer.addEventListener('click', function (e) {
            var tile = e.target.closest('.pokehub-collection-tile');
            if (!tile || !canEdit) return;
            if (viewWrapTiles && viewWrapTiles.getAttribute('data-local') === '1') return;
            var pokemonId = parseInt(tile.getAttribute('data-pokemon-id'), 10);
            var status = tile.getAttribute('data-status') || 'missing';
            var next = cycleStatus(status);
            tile.setAttribute('data-status', next);
            items[pokemonId] = next;
            try {
                tileContainer.setAttribute('data-items', JSON.stringify(items));
            } catch (err) {}
            var pogoViewWrap = (tile && tile.closest) ? tile.closest('.pokehub-collection-view-wrap') : null;
            if (typeof window.pokeHubPogoSearchRefresh === 'function' && pogoViewWrap) {
                window.pokeHubPogoSearchRefresh(pogoViewWrap);
            } else if (typeof window.pokeHubPogoSearchRefresh === 'function' && viewWrapTiles) {
                window.pokeHubPogoSearchRefresh(viewWrapTiles);
            }
            applyStatusFilters();

            if (isLocal || !collectionId) {
                var localSlug = new URLSearchParams(window.location.search).get('collection');
                var collections = JSON.parse(localStorage.getItem(storageKey) || '[]');
                var col = collections.find(function (c) { return c.id === localSlug; });
                if (col) {
                    col.items = items;
                    localStorage.setItem(storageKey, JSON.stringify(collections));
                }
            } else {
                var offlineMap = readOfflineItemsMap();
                if (!offlineMap[collectionId]) {
                    offlineMap[collectionId] = {};
                }
                offlineMap[collectionId][pokemonId] = next;
                writeOfflineItemsMap(offlineMap);
                fetch(pokeHubCollections.restUrl + 'collections/' + collectionId + '/item', {
                    method: 'POST',
                    headers: buildRestHeaders({
                        'Content-Type': 'application/json',
                    }, tileWrap || viewWrapTiles),
                    body: JSON.stringify({ pokemon_id: pokemonId, status: next }),
                    credentials: 'same-origin',
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data && data.success) {
                        var m = readOfflineItemsMap();
                        if (m[collectionId]) {
                            delete m[collectionId][pokemonId];
                            if (Object.keys(m[collectionId]).length === 0) {
                                delete m[collectionId];
                            }
                            writeOfflineItemsMap(m);
                        }
                    }
                }).catch(function () {});
            }
        });
        if (collectionId) {
            var pending = readOfflineItemsMap();
            var pendingForCol = pending[collectionId] || {};
            var pendingIds = Object.keys(pendingForCol);
            if (pendingIds.length > 0) {
                for (var pi = 0; pi < pendingIds.length; pi++) {
                    var pk = pendingIds[pi];
                    items[pk] = pendingForCol[pk];
                }
                try {
                    tileContainer.setAttribute('data-items', JSON.stringify(items));
                } catch (e4) {}
                tileContainer.querySelectorAll('.pokehub-collection-tile').forEach(function (tileEl) {
                    var pid = String(parseInt(tileEl.getAttribute('data-pokemon-id'), 10));
                    if (pendingForCol[pid]) {
                        var st = pendingForCol[pid];
                        tileEl.setAttribute('data-status', st);
                        var dotEl = tileEl.querySelector('.pokehub-collection-tile-status');
                        if (dotEl) {
                            dotEl.className = 'pokehub-collection-tile-status pokehub-status-' + st;
                        }
                    }
                });
                applyStatusFilters();
            }
        }
    }

    // Local collection view: load from localStorage and fetch pool
    var localWrap = document.querySelector('.pokehub-collection-view-wrap[data-local="1"]');
    if (localWrap && typeof pokeHubCollections !== 'undefined') {
        var localSlug = localWrap.getAttribute('data-collection-slug');
        var stored = JSON.parse(localStorage.getItem(storageKey) || '[]');
        var col = stored.find(function (c) { return c.id === localSlug; });
        if (col) {
            var titleEl = localWrap.querySelector('.pokehub-collection-local-title');
            if (titleEl) titleEl.textContent = col.name;
            var applyLocalFilters = bindCollectionStatusFilters(localWrap);
            var localResetBlock = localWrap.querySelector('.pokehub-collections-reset-inline');
            var localResetLaunch = localWrap.querySelector('.pokehub-collections-btn-reset-launch');
            function localResolveItemStatus(p, items) {
                var m = items || {};
                if (m[p.id] !== undefined && m[p.id] !== null) {
                    return m[p.id];
                }
                if (p.synthetic_sex_base_id && m[p.synthetic_sex_base_id] !== undefined && m[p.synthetic_sex_base_id] !== null) {
                    return m[p.synthetic_sex_base_id];
                }
                return 'missing';
            }
            function updateLocalCollectionStats() {
                var items = col.items || {};
                var total = col._localPool && col._localPool.length ? col._localPool.length : 0;
                if (!col._localPool) return;
                var owned = 0;
                col._localPool.forEach(function (p) {
                    if (localResolveItemStatus(p, items) === 'owned') {
                        owned++;
                    }
                });
                var statsEl = localWrap.querySelector('.pokehub-collection-local-stats');
                if (statsEl) {
                    statsEl.innerHTML = '<span class="pokehub-collection-progress-badge" aria-label="">'
                        + '<span class="pokehub-collection-progress-n">' + owned + '</span>'
                        + '<span class="pokehub-collection-progress-sep" aria-hidden="true">/</span>'
                        + '<span class="pokehub-collection-progress-total">' + total + '</span>'
                        + '</span>';
                }
            }
            fetch(pokeHubCollections.restUrl + 'collections/pool?category=' + encodeURIComponent(col.category) + '&options=' + encodeURIComponent(JSON.stringify(col.options || {})))
                .then(function (r) { return r.json(); })
                .then(function (pool) {
                    col._localPool = pool;
                    var items = col.items || {};
                    var tilesEl = localWrap.querySelector('.pokehub-collection-tiles-local');
                    if (!tilesEl) return;
                    if (localResetLaunch) localResetLaunch.removeAttribute('disabled');
                    setupCollectionResetInline(localResetBlock || localWrap, function () {
                        col.items = {};
                        localStorage.setItem(storageKey, JSON.stringify(stored));
                        tilesEl.querySelectorAll('.pokehub-collection-tile').forEach(function (t) {
                            t.setAttribute('data-status', 'missing');
                            var dot = t.querySelector('.pokehub-collection-tile-status');
                            if (dot) dot.className = 'pokehub-collection-tile-status pokehub-status-missing';
                        });
                        updateLocalCollectionStats();
                        applyLocalFilters();
                    });
                    pool.forEach(function (p) {
                        var status = localResolveItemStatus(p, items);
                        // image_url = primaire ou repli serveur ; data-fallback = 2e URL si 404 (REST).
                        var imgSrc = (p.image_url && typeof p.image_url === 'string') ? p.image_url : '';
                        var imgFallback = (p.image_url_fallback && typeof p.image_url_fallback === 'string' && p.image_url_fallback) ? p.image_url_fallback : '';
                        var imgTag = imgSrc
                            ? '<img class="pokehub-collection-sprite" src="' + escUrlAttr(imgSrc) + '" alt="" loading="lazy"'
                                + (imgFallback ? ' data-fallback="' + escUrlAttr(imgFallback) + '"' : '')
                                + ' />'
                            : '';
                        var bgUrl = (p.background_image_url && typeof p.background_image_url === 'string') ? p.background_image_url.trim() : '';
                        var figureHtml = '<div class="pokehub-collection-tile-figure">' +
                            (bgUrl ? '<div class="pokehub-collection-tile-bg" style="background-image: url(' + bgUrl + ');" aria-hidden="true"></div>' : '') +
                            imgTag +
                            '</div>';
                        var tile = document.createElement('div');
                        tile.className = 'pokehub-collection-tile';
                        tile.setAttribute('data-pokemon-id', p.id);
                        tile.setAttribute('data-status', status);
                        tile.setAttribute('tabindex', '0');
                        tile.setAttribute('role', 'button');
                        var dexN = p.dex_number ? parseInt(p.dex_number, 10) : 0;
                        var dexHtml = dexN > 0 ? '<span class="pokehub-collection-tile-dex">#' + dexN + '</span>' : '';
                        var gsym = '';
                        if (p.gender_display) {
                            var o = col.options || {};
                            var showGSym = (p.synthetic_sex_collector && (o.include_both_sexes_collector || o.include_gender)) ||
                                (!p.synthetic_sex_collector && o.include_gender);
                            if (showGSym) {
                                gsym = '<span class="pokehub-collection-tile-gender" aria-hidden="true">' + p.gender_display + '</span>';
                            }
                        }
                        var primaryName = (p.name_fr || p.name_en || '').trim();
                        var formLbl = (p.form_label && String(p.form_label).trim()) || '';
                        var fs = (p.form_slug && String(p.form_slug).toLowerCase()) || '';
                        if (['normal', 'form-normal', 'form_normal'].indexOf(fs) >= 0) {
                            formLbl = '';
                        }
                        if (formLbl && ['normal', 'normale'].indexOf(formLbl.toLowerCase()) >= 0) {
                            formLbl = '';
                        }
                        var showForm = formLbl !== '' && primaryName !== '' &&
                            formLbl.toLowerCase() !== primaryName.toLowerCase() &&
                            primaryName.toLowerCase().indexOf(formLbl.toLowerCase()) === -1;
                        var formHtml = showForm ? '<span class="pokehub-collection-tile-form-line">' + formLbl + '</span>' : '';
                        var nameLine = '<span class="pokehub-collection-tile-line pokehub-collection-tile-line--name">' +
                            '<span class="pokehub-collection-tile-name-stack">' +
                            '<span class="pokehub-collection-tile-name-row">' +
                            '<span class="pokehub-collection-tile-name">' + (p.name_fr || p.name_en || '') + '</span>' + gsym +
                            '</span>' + formHtml + '</span></span>';
                        tile.innerHTML = figureHtml +
                            '<div class="pokehub-collection-tile-text">' + dexHtml + nameLine + '</div>' +
                            '<span class="pokehub-collection-tile-status pokehub-status-' + status + '"></span>';
                        var statusState = status;
                        tile.addEventListener('click', function () {
                            var next = cycleStatus(statusState);
                            statusState = next;
                            tile.setAttribute('data-status', next);
                            var stEl = tile.querySelector('.pokehub-collection-tile-status');
                            if (stEl) stEl.className = 'pokehub-collection-tile-status pokehub-status-' + next;
                            items[p.id] = next;
                            col.items = items;
                            localStorage.setItem(storageKey, JSON.stringify(stored));
                            if (tilesEl) {
                                var mR = {};
                                (col._localPool || []).forEach(function (p2) {
                                    mR[p2.id] = localResolveItemStatus(p2, col.items);
                                });
                                tilesEl.setAttribute('data-items', JSON.stringify(mR));
                            }
                            if (typeof window.pokeHubPogoSearchRefresh === 'function') {
                                window.pokeHubPogoSearchRefresh(localWrap);
                            }
                            applyLocalFilters();
                            updateLocalCollectionStats();
                        });
                        tilesEl.appendChild(tile);
                    });
                    bindCollectionSpriteFallbacks(tilesEl);
                    (function buildResolved() {
                        var m = {};
                        pool.forEach(function (p) {
                            m[p.id] = localResolveItemStatus(p, col.items || {});
                        });
                        tilesEl.setAttribute('data-items', JSON.stringify(m));
                    }());
                    tilesEl.setAttribute('data-pool', JSON.stringify(pool));
                    if (typeof window.pokeHubPogoSearchRefresh === 'function') {
                        window.pokeHubPogoSearchRefresh(localWrap);
                    }
                    applyLocalFilters();
                    updateLocalCollectionStats();
                })
                .catch(function () {
                    var tilesEl = localWrap.querySelector('.pokehub-collection-tiles-local');
                    if (tilesEl) tilesEl.innerHTML = '<p class="pokehub-collections-not-found">Impossible de charger le pool.</p>';
                });
        } else {
            localWrap.querySelector('.pokehub-collection-local-title').textContent = 'Collection introuvable';
            localWrap.querySelector('.pokehub-collection-tiles-local').innerHTML = '<p class="pokehub-collections-not-found">Cette collection n’existe plus en local.</p>';
        }
    }

    // Share button : URL canonique avec le jeton de partage (ex. /collections/nw98Z3L2UzjXe6)
    var btnShare = document.querySelector('.pokehub-collections-btn-share');
    if (btnShare) {
        btnShare.addEventListener('click', function () {
            var wrap = btnShare.closest('.pokehub-collection-view-wrap');
            var url = wrap && wrap.getAttribute('data-share-url') ? wrap.getAttribute('data-share-url') : window.location.href;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    alert('Lien copié dans le presse-papiers.');
                }).catch(function () {
                    prompt('Copiez ce lien :', url);
                });
            } else {
                prompt('Copiez ce lien :', url);
            }
        });
    }

    // Panneau latéral (drawer) paramètres (vue collection) — différenciant vs popup
    var viewWrap = document.querySelector('.pokehub-collection-view-wrap[data-collection-id]');
    var drawer = document.getElementById('pokehub-collections-drawer');
    var drawerBackdrop = document.getElementById('pokehub-collections-drawer-backdrop');
    var drawerClose = document.getElementById('pokehub-collections-drawer-close');
    var btnEditSettings = document.querySelector('.pokehub-collections-btn-edit-settings');
    var btnEditSave = document.getElementById('pokehub-collections-drawer-save');

    function openDrawer() {
        if (drawer) {
            drawer.setAttribute('aria-hidden', 'false');
            drawer.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeDrawer() {
        if (drawer) {
            drawer.setAttribute('aria-hidden', 'true');
            drawer.classList.remove('is-open');
            document.body.style.overflow = '';
        }
    }

    if (btnEditSettings) {
        btnEditSettings.addEventListener('click', function (e) {
            e.preventDefault();
            openDrawer();
        });
    }
    if (drawerClose) drawerClose.addEventListener('click', closeDrawer);
    if (drawerBackdrop) drawerBackdrop.addEventListener('click', closeDrawer);

    if (viewWrap && drawer && typeof window.URLSearchParams !== 'undefined') {
        var params = new URLSearchParams(window.location.search);
        if (params.get('edit') === '1') openDrawer();
    }

    if (btnEditSave && viewWrap && typeof pokeHubCollections !== 'undefined') {
        var collectionId = viewWrap.getAttribute('data-collection-id');
        btnEditSave.addEventListener('click', function () {
            var nameEl = document.getElementById('pokehub-edit-collection-name');
            var name = nameEl ? nameEl.value.trim() : '';
            var isPublic = document.getElementById('pokehub-edit-collection-public') ? document.getElementById('pokehub-edit-collection-public').checked : false;
            var addSelectorsEdit = document.getElementById('pokehub-edit-add-selectors');
            var displayMode = addSelectorsEdit && addSelectorsEdit.checked ? 'tiles_select' : 'tiles';
            var currentOptions = {};
            try {
                var optsJson = viewWrap.getAttribute('data-edit-options');
                if (optsJson) currentOptions = JSON.parse(optsJson);
            } catch (e) {}
            var category = viewWrap.getAttribute('data-collection-category') || '';
            var specificCategories = [];
            try {
                var raw = viewWrap.getAttribute('data-specific-categories');
                if (raw) specificCategories = JSON.parse(raw);
            } catch (e) {}
            var isSpecificCategory = specificCategories.indexOf(category) !== -1;
            var cardBgEl = document.getElementById('pokehub-edit-card-background-image');
            var cardBgUrl = cardBgEl ? cardBgEl.value.trim() : '';
            var options = Object.assign({}, currentOptions, {
                display_mode: displayMode,
                group_by_generation: document.getElementById('pokehub-edit-group-by-generation') ? document.getElementById('pokehub-edit-group-by-generation').checked : true,
                generations_collapsed: document.getElementById('pokehub-edit-generations-collapsed') ? document.getElementById('pokehub-edit-generations-collapsed').checked : false,
                one_per_species: document.getElementById('pokehub-edit-one-per-species') ? document.getElementById('pokehub-edit-one-per-species').checked : false,
                card_background_image_url: cardBgUrl,
            });
            if (!isSpecificCategory) {
                var hEdit =
                    pokeHubCollections.settingsHiddenByCategory && category
                        ? pokeHubCollections.settingsHiddenByCategory[category] || []
                        : [];
                function editHiddenDefault(key) {
                    if (key === 'include_special_attacks' || key === 'include_both_sexes_collector') {
                        return false;
                    }
                    return true;
                }
                function readEditBool(controlKey, elId, optionKey) {
                    if (hEdit.indexOf(controlKey) !== -1) {
                        return editHiddenDefault(controlKey);
                    }
                    var el = document.getElementById(elId);
                    if (el) {
                        return el.checked;
                    }
                    var prev = currentOptions[optionKey];
                    if (prev === undefined) {
                        if (optionKey === 'include_regional_forms' || optionKey === 'include_baby_pokemon') {
                            return true;
                        }
                        if (optionKey === 'include_special_attacks' || optionKey === 'include_both_sexes_collector') {
                            return false;
                        }
                    }
                    return !!prev;
                }
                options.include_backgrounds = readEditBool('include_backgrounds', 'pokehub-edit-include-backgrounds', 'include_backgrounds');
                options.include_gender = readEditBool('include_gender', 'pokehub-edit-include-gender', 'include_gender');
                options.include_both_sexes_collector = readEditBool('include_both_sexes_collector', 'pokehub-edit-both-sexes-collector', 'include_both_sexes_collector');
                options.show_gender_symbols = !!(options.include_gender || options.include_both_sexes_collector);
                options.include_baby_pokemon = readEditBool('include_baby_pokemon', 'pokehub-edit-include-babies', 'include_baby_pokemon');
                if (category === 'legendary_mythical_ultra') {
                    options.pool_show_only = '';
                } else {
                    var poolShowEl = document.getElementById('pokehub-edit-pool-show-only');
                    var psv = poolShowEl && poolShowEl.value ? poolShowEl.value : '';
                    if (psv === 'baby' && hEdit.indexOf('pool_option_baby') !== -1) {
                        psv = '';
                    }
                    if (psv === 'special_all' && hEdit.indexOf('pool_option_special_all') !== -1) {
                        psv = '';
                    }
                    options.pool_show_only = psv;
                }
                options.include_legendary_pokemon = document.getElementById('pokehub-edit-include-legendary') ? document.getElementById('pokehub-edit-include-legendary').checked : true;
                options.include_mythical_pokemon = document.getElementById('pokehub-edit-include-mythical') ? document.getElementById('pokehub-edit-include-mythical').checked : true;
                options.include_ultra_beast_pokemon = document.getElementById('pokehub-edit-include-ultra-beast') ? document.getElementById('pokehub-edit-include-ultra-beast').checked : true;
                options.include_regional_forms = readEditBool('include_regional_forms', 'pokehub-edit-include-regional-forms', 'include_regional_forms');
                options.include_costumes = readEditBool('include_costumes', 'pokehub-edit-include-costumes', 'include_costumes');
                options.include_mega = readEditBool('include_mega', 'pokehub-edit-include-mega', 'include_mega');
                options.include_gigantamax = readEditBool('include_gigantamax', 'pokehub-edit-include-gigantamax', 'include_gigantamax');
                options.include_dynamax = readEditBool('include_dynamax', 'pokehub-edit-include-dynamax', 'include_dynamax');
                options.include_special_attacks = readEditBool('include_special_attacks', 'pokehub-edit-include-special-attacks', 'include_special_attacks');
                var onlyShinyEdit = document.getElementById('pokehub-edit-only-shiny');
                if (category === 'custom' && onlyShinyEdit) {
                    options.only_shiny = !!onlyShinyEdit.checked;
                }
            }
            options.include_national_dex = document.getElementById('pokehub-edit-include-national') ? document.getElementById('pokehub-edit-include-national').checked : true;
            fetch(pokeHubCollections.restUrl + 'collections/' + collectionId, {
                method: 'PATCH',
                headers: buildRestHeaders({
                    'Content-Type': 'application/json',
                }, viewWrap),
                body: JSON.stringify({
                    name: name || undefined,
                    is_public: isPublic,
                    options: options,
                }),
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        closeDrawer();
                        window.location.reload();
                    } else {
                        alert(data.message || 'Erreur');
                    }
                })
                .catch(function () {
                    alert('Erreur réseau');
                });
        });
    }

    if (viewWrap && typeof pokeHubCollections !== 'undefined') {
        var resetCollectionId = viewWrap.getAttribute('data-collection-id');
        var resetBlock = viewWrap.querySelector('.pokehub-collections-reset-inline[data-reset-context="server"]');
        if (resetBlock && resetCollectionId) {
            setupCollectionResetInline(resetBlock, function () {
                fetch(pokeHubCollections.restUrl + 'collections/' + resetCollectionId + '/reset', {
                    method: 'POST',
                    headers: buildRestHeaders({
                        'Content-Type': 'application/json',
                    }, viewWrap),
                    body: JSON.stringify({}),
                    credentials: 'same-origin',
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Erreur');
                        }
                    })
                    .catch(function () {
                        alert('Erreur réseau');
                    });
            });
        }
    }

    // Supprimer la collection (depuis la vue)
    var btnDeleteCollection = document.querySelector('.pokehub-collections-btn-delete-collection');
    if (btnDeleteCollection && viewWrap && typeof pokeHubCollections !== 'undefined') {
        var viewCollectionId = viewWrap.getAttribute('data-collection-id');
        var viewTitleEl = viewWrap.querySelector('.pokehub-collection-view-title');
        var viewCollectionName = viewWrap.getAttribute('data-edit-name') || (viewTitleEl ? viewTitleEl.textContent : '') || '';
        btnDeleteCollection.addEventListener('click', function () {
            if (!viewCollectionId || !confirm('Supprimer la collection « ' + viewCollectionName + ' » ? Cette action est irréversible.')) {
                return;
            }
            fetch(pokeHubCollections.restUrl + 'collections/' + viewCollectionId, {
                method: 'DELETE',
                headers: buildRestHeaders({}, viewWrap),
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        var path = window.location.pathname.replace(/\/[^/]+$/, '') || '/';
                        window.location.href = window.location.origin + path;
                    } else {
                        alert(data.message || 'Erreur');
                    }
                })
                .catch(function () {
                    alert('Erreur réseau');
                });
        });
    }

    // Supprimer une collection depuis la liste
    var listWrap = document.querySelector('.pokehub-collections-wrap');
    if (listWrap && typeof pokeHubCollections !== 'undefined') {
        listWrap.addEventListener('click', function (e) {
            var btn = e.target.closest('.pokehub-collections-btn-delete-list');
            if (!btn) return;
            e.preventDefault();
            var id = btn.getAttribute('data-collection-id');
            var name = btn.getAttribute('data-collection-name') || '';
            if (!id || !confirm('Supprimer la collection « ' + name + ' » ? Cette action est irréversible.')) {
                return;
            }
            var headers = {};
            if (pokeHubCollections.nonce) headers['X-WP-Nonce'] = pokeHubCollections.nonce;
            fetch(pokeHubCollections.restUrl + 'collections/' + id, {
                method: 'DELETE',
                headers: headers,
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.message || 'Erreur');
                    }
                })
                .catch(function () {
                    alert('Erreur réseau');
                });
        });
    }

    // Select2 : manquants → possédé ou à échanger (mode liste + select)
    var multiselectWrap = document.querySelector('.pokehub-collection-multiselect-wrap');
    var missingSelect = document.getElementById('pokehub-collection-missing-select');
    var forTradeSelect = document.getElementById('pokehub-collection-fortrade-select');
    var tileContainer = document.querySelector('.pokehub-collection-tiles');
    if (multiselectWrap && missingSelect && tileContainer && typeof pokeHubCollections !== 'undefined') {
        var selectWrap = tileContainer.closest('.pokehub-collection-view-wrap');
        var collectionId = selectWrap ? selectWrap.getAttribute('data-collection-id') : null;
        var pool = [];
        var items = {};
        try {
            pool = JSON.parse(tileContainer.getAttribute('data-pool') || '[]');
        } catch (e) {}
        try {
            items = JSON.parse(tileContainer.getAttribute('data-items') || '{}');
        } catch (e) {}

        function getMissingPool() {
            return pool.filter(function (p) {
                var s = items[p.id];
                return s !== 'owned' && s !== 'for_trade';
            });
        }

        function getLabel(p) {
            var fl = p.form_label ? String(p.form_label).trim() : '';
            var fs = (p.form_slug && String(p.form_slug).toLowerCase()) || '';
            if (['normal', 'form-normal', 'form_normal'].indexOf(fs) >= 0) {
                fl = '';
            }
            if (fl && ['normal', 'normale'].indexOf(fl.toLowerCase()) >= 0) {
                fl = '';
            }
            return (p.name_fr || p.name_en || '') + (fl ? ' (' + fl + ')' : '');
        }

        function fillSelectWithMissing(selectEl) {
            if (!selectEl) return;
            var missing = getMissingPool();
            var placeholder = selectEl.getAttribute('data-placeholder') || 'Search by name or #…';
            missing.forEach(function (p) {
                var opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = (p.dex_number ? p.dex_number + ' – ' : '') + getLabel(p);
                selectEl.appendChild(opt);
            });
            if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                jQuery(selectEl).select2({
                    placeholder: placeholder,
                    width: '100%',
                    allowClear: true,
                });
            }
        }

        fillSelectWithMissing(missingSelect);
        fillSelectWithMissing(forTradeSelect);

        function runAddFromSelect(selectEl, status, triggerBtn) {
            if (!selectEl || !collectionId) return;
            var selected = typeof jQuery !== 'undefined' && jQuery(selectEl).val ? jQuery(selectEl).val() : [];
            if (!selected || selected.length === 0) return;
            var ids = selected.map(function (v) { return parseInt(v, 10); });
            if (triggerBtn) triggerBtn.disabled = true;
            var done = 0;
            function next() {
                if (done >= ids.length) {
                    window.location.reload();
                    return;
                }
                var pokemonId = ids[done];
                fetch(pokeHubCollections.restUrl + 'collections/' + collectionId + '/item', {
                    method: 'POST',
                    headers: buildRestHeaders({
                        'Content-Type': 'application/json',
                    }, selectWrap),
                    body: JSON.stringify({ pokemon_id: pokemonId, status: status }),
                    credentials: 'same-origin',
                })
                    .then(function () { done++; next(); })
                    .catch(function () {
                        alert('Erreur lors de l\'ajout.');
                        if (triggerBtn) triggerBtn.disabled = false;
                    });
            }
            next();
        }

        var multiselectAddBtn = multiselectWrap.querySelector('.pokehub-collection-multiselect-add[data-add-status="owned"]') || multiselectWrap.querySelector('.pokehub-collection-multiselect-add');
        if (multiselectAddBtn) {
            multiselectAddBtn.addEventListener('click', function () {
                runAddFromSelect(missingSelect, 'owned', multiselectAddBtn);
            });
        }
        var forTradeAddBtn = multiselectWrap.querySelector('.pokehub-collection-fortrade-add[data-add-status="for_trade"]');
        if (forTradeAddBtn && forTradeSelect) {
            forTradeAddBtn.addEventListener('click', function () {
                runAddFromSelect(forTradeSelect, 'for_trade', forTradeAddBtn);
            });
        }
    }

    // Bandeau « collections anonymes à rattacher » (utilisateur connecté)
    var banner = document.getElementById('pokehub-collections-anonymous-banner');
    if (banner && listWrap && listWrap.getAttribute('data-logged-in') === '1' && typeof pokeHubCollections !== 'undefined') {
        var bannerText = banner.querySelector('.pokehub-collections-anonymous-banner-text');
        var bannerList = banner.querySelector('.pokehub-collections-anonymous-list');
        var claimAllBtn = banner.querySelector('.pokehub-collections-claim-all');
        var dismissBtn = banner.querySelector('.pokehub-collections-dismiss-banner');
        var anonymousCollections = [];

        function showBanner(list) {
            anonymousCollections = list;
            if (list.length === 0) return;
            var n = list.length;
            var msg = (n === 1 && pokeHubCollections.i18n && pokeHubCollections.i18n.anonymousBannerOne)
                ? pokeHubCollections.i18n.anonymousBannerOne
                : (pokeHubCollections.i18n && pokeHubCollections.i18n.anonymousBannerMany)
                    ? pokeHubCollections.i18n.anonymousBannerMany.replace('%d', n)
                    : n === 1
                        ? 'Une collection a été créée depuis cette connexion. Voulez-vous l\'ajouter à votre compte ?'
                        : n + ' collections ont été créées depuis cette connexion. Voulez-vous les ajouter à votre compte ?';
            bannerText.textContent = msg;
            bannerList.innerHTML = '';
            list.forEach(function (c) {
                var li = document.createElement('li');
                li.textContent = c.name || 'Sans nom';
                bannerList.appendChild(li);
            });
            banner.classList.remove('is-hidden');
            banner.setAttribute('aria-hidden', 'false');
        }

        function hideBanner() {
            banner.classList.add('is-hidden');
            banner.setAttribute('aria-hidden', 'true');
            try { sessionStorage.setItem('poke_hub_collections_banner_dismissed', '1'); } catch (e) {}
        }

        if (dismissBtn) dismissBtn.addEventListener('click', hideBanner);

        if (claimAllBtn) {
            claimAllBtn.addEventListener('click', function () {
                if (anonymousCollections.length === 0) return;
                claimAllBtn.disabled = true;
                var done = 0;
                var total = anonymousCollections.length;
                function next() {
                    if (done >= total) {
                        window.location.reload();
                        return;
                    }
                    var c = anonymousCollections[done];
                    fetch(pokeHubCollections.restUrl + 'collections/claim', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': pokeHubCollections.nonce,
                        },
                        body: JSON.stringify({ collection_id: c.id }),
                        credentials: 'same-origin',
                    })
                        .then(function (r) { return r.json(); })
                        .then(function () { done++; next(); })
                        .catch(function () { done++; next(); });
                }
                next();
            });
        }

        try {
            if (sessionStorage.getItem('poke_hub_collections_banner_dismissed')) return;
        } catch (e) {}
        fetch(pokeHubCollections.restUrl + 'collections/anonymous-by-ip', {
            headers: pokeHubCollections.nonce ? { 'X-WP-Nonce': pokeHubCollections.nonce } : {},
            credentials: 'same-origin',
        })
            .then(function (r) { return r.json(); })
            .then(function (list) {
                if (Array.isArray(list) && list.length > 0) showBanner(list);
            })
            .catch(function () {});
    }

    /* --- Recherche in-game (Pokémon GO) : une phrase / un bloc par type (groupe), liste complète en virgules --- */
    function pogoDebugEnabled() {
        try {
            if (typeof pokeHubCollections !== 'undefined' && pokeHubCollections.debugPogo) {
                return true;
            }
            if (typeof window.sessionStorage !== 'undefined' && window.sessionStorage.getItem('pokehub_debug_pogo') === '1') {
                return true;
            }
            if (typeof window.location !== 'undefined' && window.location.search && window.location.search.indexOf('pokehub_debug_pogo=1') !== -1) {
                return true;
            }
        } catch (e0) {}
        return false;
    }
    function pogoDebugLog() {
        if (!pogoDebugEnabled() || typeof console === 'undefined' || !console.log) {
            return;
        }
        var a = ['[poke-hub collections | POGO]'];
        for (var di = 0; di < arguments.length; di++) {
            a.push(arguments[di]);
        }
        console.log.apply(console, a);
    }

    var POGO_GROUP_ORDER = ['base', 'alola', 'galar', 'paldea', 'hisui', 'mega', 'gigamax', 'dynamax', 'male', 'female', 'costume', 'fond', 'fond_dynamax', 'fond_gigamax'];
    var POGO_PREFIX = {
        base: '',
        alola: '',
        galar: '',
        paldea: '',
        hisui: '',
        mega: '',
        gigamax: '',
        dynamax: '',
        male: 'male&',
        female: 'female&',
        costume: '', /* no filtre sûr partout : liste de noms seulement */
        fond: '',
        fond_dynamax: '',
        fond_gigamax: '',
    };
    function pogoGroupPrefix(gkey, tokenMode, sampleRow) {
        var k = tokenMode === 'name_en' ? 'pogo_group_prefix_en' : 'pogo_group_prefix_fr';
        if (gkey === 'fond_dynamax') {
            return (tokenMode === 'name_en') ? 'background&dynamax&' : 'fond&dynamax&';
        }
        if (gkey === 'fond_gigamax') {
            return (tokenMode === 'name_en') ? 'background&gigantamax&' : 'fond&gigamax&';
        }
        if (gkey === 'fond') {
            return (tokenMode === 'name_en') ? 'background&' : 'fond&';
        }
        if (gkey === 'gigamax') {
            return (tokenMode === 'name_en') ? 'gigantamax&' : 'gigamax&';
        }
        if (gkey === 'dynamax') {
            return 'dynamax&';
        }
        if (gkey === 'costume') {
            return (tokenMode === 'name_en') ? 'event&' : 'costume&';
        }
        if (sampleRow && sampleRow[k]) {
            var raw = String(sampleRow[k]).trim();
            if (raw) {
                return raw + '&';
            }
        }
        return POGO_PREFIX[gkey] !== undefined ? POGO_PREFIX[gkey] : '';
    }

    function pogoStripAccents(s) {
        if (!s) return '';
        try {
            return s.toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
        } catch (e) {
            return String(s).toLowerCase();
        }
    }
    function pogoNameToken(row, lang) {
        var raw = lang === 'en' ? (row.name_en || row.name_fr) : (row.name_fr || row.name_en);
        return pogoStripAccents(String(raw).replace(/[.'’\s·\-_]/g, ''));
    }
    /**
     * Après {@see pogoNameToken} : FR « Rattata d'Alola » → rattatadalola ; EN « Alolan Rattata » → alolanrattata.
     * Retire préfixes / suffixes régionaux (FR + EN : alolan, paldean, Gigantamax, « Form », from/of, …).
     */
    function pogoStripRegionalFormTokenNoise(t, gk) {
        if (!t || !gk) {
            return t;
        }
        var s = String(t);
        /* Ordre : d|de puis from|of (obligatoires) — (from|of)? ou (d|de)? seuls laissaient « rattatad » sur rattatadalola. */
        if (gk === 'alola') {
            s = s.replace(/^(alolan|alola)/i, '');
            s = s.replace(/(d|de)(alolan|alola)$/i, '');
            s = s.replace(/(from|of)(alolan|alola)$/i, '');
            s = s.replace(/(^|[^d])(alolan|alola)$/i, '$1');
            s = s.replace(/(alolan|alola)form$/i, '');
            s = s.replace(/form(alolan|alola)$/i, '');
        } else if (gk === 'galar') {
            s = s.replace(/^(galarian|galar)/i, '');
            s = s.replace(/(d|de)(galarian|galar)$/i, '');
            s = s.replace(/(from|of)(galarian|galar)$/i, '');
            s = s.replace(/(^|[^d])(galarian|galar)$/i, '$1');
            s = s.replace(/(galarian|galar)form$/i, '');
            s = s.replace(/form(galarian|galar)$/i, '');
        } else if (gk === 'paldea') {
            s = s.replace(/^(paldean|paldea)/i, '');
            s = s.replace(/(d|de)(paldean|paldea)$/i, '');
            s = s.replace(/(from|of)(paldean|paldea)$/i, '');
            s = s.replace(/(^|[^d])(paldean|paldea)$/i, '$1');
            s = s.replace(/(paldean|paldea)form$/i, '');
            s = s.replace(/form(paldean|paldea)$/i, '');
        } else if (gk === 'hisui') {
            s = s.replace(/^(hisuian|hisui)/i, '');
            s = s.replace(/(d|de)(hisuian|hisui)$/i, '');
            s = s.replace(/(from|of)(hisuian|hisui)$/i, '');
            s = s.replace(/(^|[^d])(hisuian|hisui)$/i, '$1');
            s = s.replace(/(hisuian|hisui)form$/i, '');
            s = s.replace(/form(hisuian|hisui)$/i, '');
        }
        return s;
    }

    /**
     * Pokémon GO n’utilise que le nom d’espèce dans les recherches « méga / gigamax& »
     * (ex. « tortank » pas « mega-tortank », « florizarre » pas « florizarregigamax » ; EN « Gigantamax »).
     * Ne pas tronquer un préfixe/suffixe « mega » sur le jeton d’espèce (Meganium, Yanmega, etc.) :
     * le groupe « méga » ne doit comporter que des variantes fv.category = mega côté pool.
     */
    function pogoSearchFormTokenNamePart(t, gk) {
        if (!t) return t;
        if (gk === 'mega') {
            return t;
        } else if (gk === 'gigamax') {
            t = t.replace(/^gigantamax|^gigamax/i, '');
            t = t.replace(/(gigantamax|gigamax)$/i, '');
            t = t.replace(/^(gigantamax|gigamax)(?!$)/i, '');
            t = t.replace(/form(gigantamax|gigamax)$/i, '');
            t = t.replace(/(gigantamax|gigamax)form$/i, '');
        } else if (gk === 'dynamax') {
            t = t.replace(/^dynamax/i, '');
            t = t.replace(/dynamax$/i, '');
            t = t.replace(/formdynamax$/i, '');
            t = t.replace(/dynamaxform$/i, '');
        } else if (gk === 'alola' || gk === 'galar' || gk === 'paldea' || gk === 'hisui') {
            t = pogoStripRegionalFormTokenNoise(t, gk);
        }
        return t;
    }
    function pogoDexToken(row) {
        var n = row.dex_number;
        if (n === undefined || n === null) return '';
        var d = parseInt(n, 10);
        if (isNaN(d) || d < 1) return '';
        return String(d);
    }
    /**
     * Mâle/femelle : le jeu attend « male&nidoran », pas « male&nidoranmale » (retrait du marqueur de sexe sur le nom).
     * @param {string} t jeton collé, accents retirés côté noms issus de pogo_token_*
     */
    function pogoStripSexDimorphismFromToken(t) {
        if (!t) return t;
        var s = pogoStripAccents(String(t).toLowerCase().replace(/[^a-z0-9]/g, ''));
        if (s.indexOf('nidoran') === 0) {
            return 'nidoran';
        }
        var suff = ['femelle', 'feminine', 'feminin', 'masculin', 'féminin', 'female', 'male'];
        for (var si = 0; si < suff.length; si++) {
            var u = pogoStripAccents(suff[si].toLowerCase().replace(/[^a-z0-9]/g, ''));
            if (u && s.length > u.length + 1 && s.slice(-u.length) === u) {
                return s.slice(0, -u.length) || t;
            }
        }
        return s || t;
    }
    /**
     * @param {string} tokenMode 'name_fr' | 'name_en' | 'number'
     * @param {string} gk clé de groupe pogo (mega, gigamax, …)
     */
    function pogoListToken(row, tokenMode, gk) {
        var out;
        if (tokenMode === 'number') {
            var d = pogoDexToken(row);
            if (d) {
                out = d;
            } else {
                var frTok0 = row.pogo_token_fr ? String(row.pogo_token_fr) : '';
                if (frTok0) {
                    out = pogoSearchFormTokenNamePart(frTok0, gk) || frTok0;
                } else {
                    out = pogoSearchFormTokenNamePart(pogoNameToken(row, 'fr'), gk) || pogoNameToken(row, 'fr');
                }
            }
        } else {
            var lang = tokenMode === 'name_en' ? 'en' : 'fr';
            var preKey = lang === 'en' ? 'pogo_token_en' : 'pogo_token_fr';
            var pre = row[preKey] ? String(row[preKey]) : '';
            if (pre) {
                var tt = pogoSearchFormTokenNamePart(pre, gk);
                out = tt || pre;
            } else {
                var t0 = pogoNameToken(row, lang);
                out = pogoSearchFormTokenNamePart(t0, gk) || t0;
            }
        }
        if (gk === 'male' || gk === 'female') {
            out = pogoStripSexDimorphismFromToken(out);
        }
        return out;
    }
    /** Rétrocompat : ancien HTML (list-by + lang) si pas de menu unifié. Tout le scope vient du bloc .pokehub-collection-pogo-search de cette collection. */
    function pogoGetTokenMode(wrap, pogoBlock) {
        var pogo = pogoBlock || (wrap && wrap.querySelector('.pokehub-collection-pogo-search'));
        if (!pogo) {
            return 'name_fr';
        }
        var modeEl = pogo.querySelector('select.pokehub-pogo-search-token-mode');
        if (modeEl) {
            return (modeEl.value) || 'name_fr';
        }
        var listByEl = pogo.querySelector('.pokehub-pogo-search-list-by');
        var laEl = pogo.querySelector('.pokehub-pogo-search-lang');
        var lb = (listByEl && listByEl.value) || 'name';
        var l = (laEl && laEl.value) || 'fr';
        if (lb === 'number') {
            return 'number';
        }
        return l === 'en' ? 'name_en' : 'name_fr';
    }
    /** Ordre d’affichage des lignes pool : génération → n° Pokédex national → id (aligné sur la grille collection). */
    function pogoComparePoolRows(a, b) {
        var gA = parseInt(a.generation_number, 10);
        var gB = parseInt(b.generation_number, 10);
        if (isNaN(gA)) { gA = 0; }
        if (isNaN(gB)) { gB = 0; }
        if (gA !== gB) {
            return gA - gB;
        }
        var dA = parseInt(a.dex_number, 10);
        var dB = parseInt(b.dex_number, 10);
        if (isNaN(dA)) { dA = 0; }
        if (isNaN(dB)) { dB = 0; }
        if (dA !== dB) {
            return dA - dB;
        }
        var idA = parseInt(a.id, 10) || 0;
        var idB = parseInt(b.id, 10) || 0;
        return idA - idB;
    }
    function pogoGuessRegionalGroupFromSlugs(s) {
        if (!s) return '';
        if (s.indexOf('alola') !== -1 || s.indexOf('alolan') !== -1) return 'alola';
        if (s.indexOf('galar') !== -1 || s.indexOf('galarian') !== -1) return 'galar';
        if (s.indexOf('paldea') !== -1 || s.indexOf('paldean') !== -1) return 'paldea';
        if (s.indexOf('hisui') !== -1 || s.indexOf('hisuian') !== -1) return 'hisui';
        return '';
    }
    /**
     * Même logique d’avant, sans mâle/femelle ni fond : pour placer D/G-Max+ fond / jetons d’assise.
     * @return {string}
     */
    function pogoCoreFormGroup(row) {
        var c = String(row.form_category || 'normal').toLowerCase();
        if (row.synthetic_gigantamax || c === 'gigantamax') {
            return 'gigamax';
        }
        if (row.synthetic_dynamax || c === 'dynamax') {
            return 'dynamax';
        }
        if (c === 'mega' || c === 'megax' || c === 'primal') {
            return 'mega';
        }
        if (c === 'dynamax') {
            return 'dynamax';
        }
        var prk = row.pogo_regional_key ? String(row.pogo_regional_key).toLowerCase() : '';
        if (prk === 'alola' || prk === 'galar' || prk === 'paldea' || prk === 'hisui' || prk === 'other') {
            return prk;
        }
        var s = String(row.form_slug || row.slug || '').toLowerCase();
        var reg = pogoGuessRegionalGroupFromSlugs(s);
        if (reg) {
            return reg;
        }
        if (c === 'regional') {
            return 'other';
        }
        if (s.indexOf('gigantamax') !== -1 || s.indexOf('gigamax') !== -1) {
            return 'gigamax';
        }
        if (c === 'costume' || c === 'costume_shiny' || s.indexOf('costume') !== -1) {
            return 'costume';
        }
        if (c === 'dynamax' || s.indexOf('dynamax') !== -1) {
            return 'dynamax';
        }
        if (c === 'gender') {
            if (s.indexOf('female') !== -1) return 'female';
            if (s.indexOf('male') !== -1) return 'male';
        }
        if (s.indexOf('female') !== -1) {
            return 'female';
        }
        if (s.indexOf('male') !== -1) {
            return 'male';
        }
        if (c !== 'normal' && c !== '' && c !== 'standard') {
            return 'other';
        }
        return 'base';
    }
    /**
     * Jeton (pogoListToken 3ᵉ arg) pour le groupe « fond » hors D/G-Max+ fond.
     * @return {string}
     */
    function pogoFondListTokenGkey(row) {
        var g = pogoCoreFormGroup(row);
        if (g === 'dynamax' || g === 'gigamax') {
            return 'base';
        }
        if (g === 'other') {
            return 'base';
        }
        if (g === 'male' || g === 'female') {
            return 'base';
        }
        return g;
    }
    /**
     * mappe gPOGO → 3ᵉ paramètre de {@see pogoListToken}
     */
    function pogoListTokenGkeyForPogoGroup(gkey, row) {
        if (gkey === 'fond_dynamax') {
            return 'dynamax';
        }
        if (gkey === 'fond_gigamax') {
            return 'gigamax';
        }
        if (gkey === 'fond') {
            return pogoFondListTokenGkey(row);
        }
        return gkey;
    }
    /**
     * Groupes = données BDD (form_category) + pogo_regional_key (formes régionales par région).
     * Fonds GO : 3 clés (fond, fond_dynamax, fond_gigamax) + mêmes jetons d’espèce que D/G-Max (pas bulbasaurdynamax&).
     */
    function pogoDetectGroup(row) {
        if (row.synthetic_sex_collector && row.synthetic_sex) {
            return String(row.synthetic_sex) === 'female' ? 'female' : 'male';
        }
        var hasBg = (row.synthetic_go_background) || (row.background_image_url && String(row.background_image_url).trim() !== '');
        if (hasBg) {
            var u = pogoCoreFormGroup(row);
            if (u === 'dynamax') {
                return 'fond_dynamax';
            }
            if (u === 'gigamax') {
                return 'fond_gigamax';
            }
            return 'fond';
        }
        return pogoCoreFormGroup(row);
    }
    /**
     * Une seule chaîne par groupe (Standard, Alola, etc.) : noms en virgules, un seul champ à copier.
     */
    function pogoBuildSinglePhrasePerGroup(prefix, nameArr) {
        if (!nameArr || nameArr.length === 0) {
            return [];
        }
        var body = nameArr.filter(function (x) {
            return !!x;
        }).join(',');
        if (!body) {
            return [];
        }
        return [(prefix || '') + body];
    }
    function pogoT(key) {
        var t = (typeof pokeHubCollections !== 'undefined' && pokeHubCollections.i18n) ? pokeHubCollections.i18n : {};
        return t[key] || key;
    }
    function pogoGroupLabelKey(gkey, listBy) {
        var k = 'pogoGroup' + (gkey.charAt(0).toUpperCase() + gkey.slice(1));
        if (listBy === 'number') {
            var dexK = k + 'Dex';
            if (pogoT(dexK) !== dexK) {
                return dexK;
            }
        }
        return k;
    }

    /** Tri des jetons d’une liste (même règle qu’avant fusion mâle/femelle). */
    function pogoSortTokenStrings(arr, listBy) {
        if (!arr || !arr.length) {
            return;
        }
        if (listBy === 'number') {
            arr.sort(function (a, b) {
                var na = parseInt(a, 10);
                var nb = parseInt(b, 10);
                if (!isNaN(na) && !isNaN(nb) && String(na) === a && String(nb) === b) {
                    return na - nb;
                }
                return a < b ? -1 : (a > b ? 1 : 0);
            });
        } else {
            arr.sort();
        }
    }

    /**
     * Si le même jeton apparaît en mâle et en femelle (ex. male&12 et female&12), une recherche GO
     * standard suffit : on le retire des deux listes et on l’ajoute au groupe « base » sans préfixe.
     * S’il n’y a qu’un seul sexe pour ce jeton, on garde male&… ou female&….
     *
     * @param {Object<string, string[]>} byGroup
     * @param {string} listBy 'number' | 'name'
     */
    function pogoMergeMaleFemaleDupesIntoBase(byGroup, listBy) {
        var male = byGroup.male;
        var female = byGroup.female;
        if (!male || !male.length || !female || !female.length) {
            return;
        }
        var fset = {};
        for (var fi = 0; fi < female.length; fi++) {
            fset[female[fi]] = true;
        }
        var inBoth = [];
        var inBothSet = {};
        for (var mi = 0; mi < male.length; mi++) {
            var t = male[mi];
            if (t && fset[t] && !inBothSet[t]) {
                inBothSet[t] = true;
                inBoth.push(t);
            }
        }
        if (!inBoth.length) {
            return;
        }
        var rm = inBothSet;
        byGroup.male = male.filter(function (x) {
            return !rm[x];
        });
        byGroup.female = female.filter(function (x) {
            return !rm[x];
        });
        var base = byGroup.base ? byGroup.base.slice() : [];
        var bseen = {};
        for (var bi = 0; bi < base.length; bi++) {
            bseen[base[bi]] = true;
        }
        for (var ii = 0; ii < inBoth.length; ii++) {
            var tok = inBoth[ii];
            if (tok && !bseen[tok]) {
                base.push(tok);
                bseen[tok] = true;
            }
        }
        pogoSortTokenStrings(base, listBy);
        byGroup.base = base;
    }

    function pogoBuildPhrasesForWrap(wrap, pogoBlock) {
        var details = pogoBlock || (wrap && wrap.querySelector('.pokehub-collection-pogo-search'));
        if (!details || !wrap) {
            pogoDebugLog('buildPhrases abort: missing details or wrap', {
                hasDetails: !!details,
                hasWrap: !!wrap,
            });
            return;
        }
        var outEl = details.querySelector('.pokehub-pogo-search-groups');
        var hint = details.querySelector('.pokehub-collection-pogo-search-hint-refresh');
        if (!outEl) {
            pogoDebugLog('buildPhrases abort: .pokehub-pogo-search-groups not found inside details');
            return;
        }
        var root = wrap.querySelector('.pokehub-collection-tiles, .pokehub-collection-tiles-local');
        if (!root) {
            pogoDebugLog('buildPhrases: no .pokehub-collection-tiles (show pogoNoPool hint)');
            outEl.innerHTML = '<p class="me5rine-lab-form-hint">' + pogoT('pogoNoPool') + '</p>';
            return;
        }
        var pool;
        var items;
        try {
            pool = JSON.parse(root.getAttribute('data-pool') || '[]');
            items = JSON.parse(root.getAttribute('data-items') || '{}');
        } catch (e) {
            pogoDebugLog('buildPhrases abort: JSON parse error on data-pool / data-items', e && e.message);
            outEl.textContent = '';
            outEl.appendChild(pogoP(pogoT('pogoNoPool')));
            return;
        }
        if (!pool || pool.length === 0) {
            pogoDebugLog('buildPhrases: empty pool');
            outEl.textContent = '';
            outEl.appendChild(pogoP(pogoT('pogoNoPool')));
            return;
        }
        var stEl = details.querySelector('.pokehub-pogo-search-status');
        var status = (stEl && stEl.value) || 'missing';
        var tokenMode = pogoGetTokenMode(wrap, details);
        var listBy = tokenMode === 'number' ? 'number' : 'name';
        var rowsByGroup = {};
        for (var g = 0; g < POGO_GROUP_ORDER.length; g++) {
            rowsByGroup[POGO_GROUP_ORDER[g]] = [];
        }
        for (var p = 0; p < pool.length; p++) {
            var row = pool[p];
            var st = String(items[String(row.id)] || items[row.id] || 'missing');
            if (st !== status) continue;
            var gk = pogoDetectGroup(row);
            /* Pas de filtre POGO fiable « autres formes » : on les traite comme Standard (noms nus). */
            if (gk === 'other') {
                gk = 'base';
            }
            if (!rowsByGroup[gk]) {
                rowsByGroup[gk] = [];
            }
            rowsByGroup[gk].push(row);
        }
        var byGroup = {};
        var prefixRowByGroup = {};
        for (var gki = 0; gki < POGO_GROUP_ORDER.length; gki++) {
            var k = POGO_GROUP_ORDER[gki];
            var gRows = rowsByGroup[k];
            if (!gRows || !gRows.length) {
                byGroup[k] = [];
                prefixRowByGroup[k] = null;
                continue;
            }
            gRows.sort(pogoComparePoolRows);
            prefixRowByGroup[k] = gRows[0];
            var seenTok = {};
            var toks = [];
            for (var ri = 0; ri < gRows.length; ri++) {
                var grow = gRows[ri];
                var tok = pogoListToken(grow, tokenMode, pogoListTokenGkeyForPogoGroup(k, grow));
                if (!tok || seenTok[tok]) continue;
                seenTok[tok] = true;
                toks.push(tok);
            }
            byGroup[k] = toks;
        }
        pogoMergeMaleFemaleDupesIntoBase(byGroup, listBy);
        outEl.textContent = '';
        var any = false;
        var gidx;
        for (gidx = 0; gidx < POGO_GROUP_ORDER.length; gidx++) {
            var gkey = POGO_GROUP_ORDER[gidx];
            var names = byGroup[gkey];
            if (!names || !names.length) continue;
            any = true;
            var label = pogoT(pogoGroupLabelKey(gkey, listBy));
            var prefix = pogoGroupPrefix(gkey, tokenMode, prefixRowByGroup[gkey]);
            var lines = pogoBuildSinglePhrasePerGroup(prefix, names);
            var groupEl = document.createElement('div');
            groupEl.className = 'pokehub-pogo-search-group';
            groupEl.setAttribute('data-pogo-group', gkey);
            var h = document.createElement('h4');
            h.className = 'pokehub-pogo-search-group-title';
            h.textContent = String(label);
            groupEl.appendChild(h);
            var ul = document.createElement('ul');
            ul.className = 'pokehub-pogo-search-lines';
            for (var L = 0; L < lines.length; L++) {
                var line = lines[L];
                var li = document.createElement('li');
                li.className = 'pokehub-pogo-search-line';
                var inp = document.createElement('input');
                inp.type = 'text';
                inp.readOnly = true;
                inp.className = 'me5rine-lab-form-input pokehub-pogo-search-input';
                inp.value = line;
                var elid = 'pogo-inp-' + gkey + '-' + L + '-' + Math.random().toString(36).slice(2, 9);
                inp.id = elid;
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'button me5rine-lab-form-button-secondary pokehub-pogo-search-copy';
                btn.setAttribute('data-target', elid);
                btn.textContent = pogoT('pogoCopy');
                li.appendChild(inp);
                li.appendChild(btn);
                ul.appendChild(li);
            }
            groupEl.appendChild(ul);
            outEl.appendChild(groupEl);
        }
        if (!any) {
            outEl.appendChild(pogoP(pogoT('pogoEmptyStatus')));
        }
        if (hint) {
            hint.textContent = pogoT('pogoNudge') || '';
        }
        pogoDebugLog('buildPhrases done', {
            status: status,
            tokenMode: tokenMode,
            poolSize: pool.length,
            anyGroup: any,
            stElFound: !!stEl,
            stElValue: stEl ? stEl.value : null,
        });
    }
    function pogoP(text) {
        var el = document.createElement('p');
        el.className = 'me5rine-lab-form-hint';
        el.textContent = text;
        return el;
    }
    function pogoCopyLine(btn) {
        var id = btn.getAttribute('data-target');
        var inp = id ? getEl(id) : null;
        if (!inp) return;
        var t = inp.value;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(t);
        } else {
            inp.select();
            document.execCommand('copy');
        }
        btn.setAttribute('data-copied', '1');
        var o = pogoT('pogoCopied');
        btn.textContent = o;
        setTimeout(function () { btn.textContent = pogoT('pogoCopy'); btn.removeAttribute('data-copied'); }, 1800);
    }
    function pogoOnBodyClickCopy(ev) {
        var c = ev.target && ev.target.closest && ev.target.closest('.pokehub-pogo-search-copy');
        if (c) {
            ev.preventDefault();
            pogoCopyLine(c);
        }
    }
    function pogoIsToolbarSelectNode(t) {
        if (!t || !t.classList || (t.nodeName || '').toLowerCase() !== 'select') {
            return false;
        }
        if (t.classList.contains('pokehub-pogo-search-status') ||
            t.classList.contains('pokehub-pogo-search-token-mode') ||
            t.classList.contains('pokehub-pogo-search-list-by') ||
            t.classList.contains('pokehub-pogo-search-lang')) {
            return true;
        }
        return !!(t.getAttribute('class') || '').match(/pokehub-pogo-search/);
    }

    /**
     * Délégation unique sur document : fonctionne même si le bloc POGO est rendu après ce script
     * (ordre wp_footer / shortcode) ou si les écouteurs par nœud n’ont jamais été posés.
     */
    function pogoBindToolbarDelegationOnce() {
        if (pogoBindToolbarDelegationOnce._done) {
            return;
        }
        pogoBindToolbarDelegationOnce._done = true;
        function onDocToolbar(ev) {
            var t = ev.target;
            if (!pogoIsToolbarSelectNode(t)) {
                return;
            }
            var wrap = t.closest && t.closest('.pokehub-collection-view-wrap');
            if (wrap) {
                pogoScheduleRefreshForWrap(wrap);
            } else {
                pogoDebugLog('toolbar ' + ev.type + ': select sans .pokehub-collection-view-wrap ascendant');
            }
        }
        document.addEventListener('change', onDocToolbar, false);
        document.addEventListener('input', onDocToolbar, false);
    }

    /** Première construction + toggle <details> : répétable (blocs apparus tard). */
    function pogoScanAndInitBoxes() {
        document.querySelectorAll('.pokehub-collection-pogo-search').forEach(function (box) {
            if (box.getAttribute('data-pogo-initialized') === '1') {
                return;
            }
            var wrap = box.closest && box.closest('.pokehub-collection-view-wrap');
            if (!wrap) {
                pogoDebugLog('pogoScanAndInitBoxes: bloc sans .pokehub-collection-view-wrap');
                return;
            }
            box.setAttribute('data-pogo-initialized', '1');
            box.addEventListener('toggle', function () {
                if (box.open) {
                    pogoScheduleRefreshForWrap(wrap);
                }
            }, false);
            pogoDebugLog('pogoScanAndInitBoxes: premier build', wrap.getAttribute && wrap.getAttribute('data-collection-id'));
            pogoBuildPhrasesForWrap(wrap, box);
        });
    }

    /**
     * La .value du <select> est parfois encore l’ancienne dans le même tour que « change ».
     */
    function pogoScheduleRefreshForWrap(wrap) {
        if (!wrap) {
            pogoDebugLog('scheduleRefresh: wrap is null');
            return;
        }
        pogoDebugLog('scheduleRefresh queued', {
            collectionId: wrap.getAttribute && wrap.getAttribute('data-collection-id'),
            hasRefreshFn: typeof window.pokeHubPogoSearchRefresh === 'function',
        });
        var run = function () {
            if (typeof window.pokeHubPogoSearchRefresh !== 'function') {
                pogoDebugLog('scheduleRefresh run: pokeHubPogoSearchRefresh missing (timing / script error)');
                return;
            }
            pogoDebugLog('scheduleRefresh run: calling pokeHubPogoSearchRefresh');
            window.pokeHubPogoSearchRefresh(wrap);
        };
        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    setTimeout(run, 0);
                });
            });
        } else {
            setTimeout(run, 0);
        }
    }
    function initPogoSearch() {
        if (!initPogoSearch._pogoCopyOnBody) {
            initPogoSearch._pogoCopyOnBody = true;
            document.body.addEventListener('click', pogoOnBodyClickCopy);
        }
        pogoBindToolbarDelegationOnce();
        pogoScanAndInitBoxes();
        if (!initPogoSearch._pogoRescanScheduled) {
            initPogoSearch._pogoRescanScheduled = true;
            [50, 200, 600].forEach(function (ms) {
                setTimeout(pogoScanAndInitBoxes, ms);
            });
            window.addEventListener('load', pogoScanAndInitBoxes);
        }
        pogoDebugLog('initPogoSearch done; blocs .pokehub-collection-pogo-search =', document.querySelectorAll('.pokehub-collection-pogo-search').length);
    }
    window.pokeHubPogoSearchRefresh = function (wrap) {
        pogoDebugLog('pokeHubPogoSearchRefresh called', !!wrap);
        if (wrap) {
            var pogo = wrap.querySelector && wrap.querySelector('.pokehub-collection-pogo-search');
            if (!pogo) {
                pogoDebugLog('pokeHubPogoSearchRefresh: no .pokehub-collection-pogo-search inside wrap');
            }
            pogoBuildPhrasesForWrap(wrap, pogo);
        } else {
            document.querySelectorAll('.pokehub-collection-view-wrap').forEach(function (w) {
                var p = w.querySelector('.pokehub-collection-pogo-search');
                pogoBuildPhrasesForWrap(w, p);
            });
        }
    };
    function initCollectionsAndPogo() {
        bindCollectionSpriteFallbacks(document);
        initPogoSearch();
    }
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(initCollectionsAndPogo, 0);
    } else {
        document.addEventListener('DOMContentLoaded', initCollectionsAndPogo);
    }
})(jQuery);
