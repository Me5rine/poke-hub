(function ($) {
    'use strict';

    var storageKey = 'poke_hub_collections';

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

    (function initCreateCategoryToggle() {
        var catSelect = document.getElementById('pokehub-collection-category');
        var additiveBlock = document.getElementById('pokehub-collection-options-additive');
        var specificHint = document.getElementById('pokehub-collection-options-specific-hint');
        var specificCategories = [];
        if (catSelect && catSelect.parentNode) {
            try {
                var raw = catSelect.parentNode.getAttribute('data-specific-categories');
                if (raw) specificCategories = JSON.parse(raw);
            } catch (e) {}
        }
        function updateCreateOptionsVisibility() {
            var category = catSelect ? catSelect.value : '';
            var isSpecific = specificCategories.indexOf(category) !== -1;
            if (additiveBlock) additiveBlock.classList.toggle('is-hidden', isSpecific);
            if (specificHint) specificHint.classList.toggle('is-hidden', !isSpecific);
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
        var displayModeEl = document.querySelector('input[name="pokehub-collection-display"]:checked');
        return {
            name: nameEl && typeof nameEl.value === 'string' ? nameEl.value.trim() : '',
            category: categoryEl && categoryEl.value ? categoryEl.value : 'custom',
            is_public: !!(publicEl && publicEl.checked),
            options: {
                include_national_dex: getCheckedOrDefault('pokehub-collection-include-national', true),
                include_gender: getCheckedOrDefault('pokehub-collection-include-gender', true),
                include_forms: getCheckedOrDefault('pokehub-collection-include-forms', true),
                include_costumes: getCheckedOrDefault('pokehub-collection-include-costumes', true),
                include_mega: getCheckedOrDefault('pokehub-collection-include-mega', true),
                include_gigantamax: getCheckedOrDefault('pokehub-collection-include-gigantamax', true),
                include_dynamax: getCheckedOrDefault('pokehub-collection-include-dynamax', true),
                include_backgrounds: getCheckedOrDefault('pokehub-collection-include-backgrounds', true),
                include_special_attacks: getCheckedOrDefault('pokehub-collection-include-special-attacks', false),
                show_gender_symbols: getCheckedOrDefault('pokehub-collection-show-gender-symbols', true),
                only_final_evolution: getCheckedOrDefault('pokehub-collection-only-final', false),
                include_baby_pokemon: getCheckedOrDefault('pokehub-collection-include-babies', true),
                one_per_species: getCheckedOrDefault('pokehub-collection-one-per-species', false),
                group_by_generation: getCheckedOrDefault('pokehub-collection-group-by-generation', true),
                generations_collapsed: getCheckedOrDefault('pokehub-collection-generations-collapsed', false),
                display_mode: displayModeEl && displayModeEl.value ? displayModeEl.value : 'tiles',
                public: !!(publicEl && publicEl.checked),
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
                    name: form.name || (form.category === 'shiny' ? 'Mes Shinies' : 'Ma collection'),
                    category: form.category,
                    options: form.options,
                    is_public: isLoggedIn ? form.is_public : false,
                }),
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success && data.share_token) {
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
            if (typeof window.pokeHubPogoSearchRefresh === 'function' && viewWrapTiles) {
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
                fetch(pokeHubCollections.restUrl + 'collections/' + collectionId + '/item', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': pokeHubCollections.nonce,
                    },
                    body: JSON.stringify({ pokemon_id: pokemonId, status: next }),
                    credentials: 'same-origin',
                }).catch(function () {});
            }
        });
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
            function updateLocalCollectionStats() {
                var items = col.items || {};
                var total = col._localPool && col._localPool.length ? col._localPool.length : 0;
                if (!col._localPool) return;
                var owned = Object.keys(items).filter(function (k) { return items[k] === 'owned'; }).length;
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
                        var status = items[p.id] || 'missing';
                        // Image URL calculée côté PHP via le helper global (slug + suffixes).
                        var imgSrc = (p.image_url && typeof p.image_url === 'string') ? p.image_url : '';
                        var bgUrl = (p.background_image_url && typeof p.background_image_url === 'string') ? p.background_image_url.trim() : '';
                        var figureHtml = '<div class="pokehub-collection-tile-figure">' +
                            (bgUrl ? '<div class="pokehub-collection-tile-bg" style="background-image: url(' + bgUrl + ');" aria-hidden="true"></div>' : '') +
                            (imgSrc ? '<img src="' + imgSrc + '" alt="" loading="lazy" />' : '') +
                            '</div>';
                        var tile = document.createElement('div');
                        tile.className = 'pokehub-collection-tile';
                        tile.setAttribute('data-pokemon-id', p.id);
                        tile.setAttribute('data-status', status);
                        tile.setAttribute('tabindex', '0');
                        tile.setAttribute('role', 'button');
                        var dexN = p.dex_number ? parseInt(p.dex_number, 10) : 0;
                        var dexHtml = dexN > 0 ? '<span class="pokehub-collection-tile-dex">#' + dexN + '</span>' : '';
                        var optG = (col.options && col.options.show_gender_symbols !== false);
                        var gsym = (optG && p.gender_display) ? '<span class="pokehub-collection-tile-gender" aria-hidden="true">' + p.gender_display + '</span>' : '';
                        var nameLine = '<span class="pokehub-collection-tile-line pokehub-collection-tile-line--name"><span class="pokehub-collection-tile-name">'
                            + (p.name_fr || p.name_en || '') + '</span>' + gsym + '</span>';
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
                            if (tilesEl) tilesEl.setAttribute('data-items', JSON.stringify(items));
                            if (typeof window.pokeHubPogoSearchRefresh === 'function') {
                                window.pokeHubPogoSearchRefresh(localWrap);
                            }
                            applyLocalFilters();
                            updateLocalCollectionStats();
                        });
                        tilesEl.appendChild(tile);
                    });
                    tilesEl.setAttribute('data-pool', JSON.stringify(pool));
                    tilesEl.setAttribute('data-items', JSON.stringify(col.items || {}));
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
            var displayModeEl = document.querySelector('input[name="pokehub-edit-collection-display"]:checked');
            var displayMode = displayModeEl ? displayModeEl.value : 'tiles';
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
                one_per_species: document.getElementById('pokehub-edit-one-per-species') ? document.getElementById('pokehub-edit-one-per-species').checked : false,
                group_by_generation: document.getElementById('pokehub-edit-group-by-generation') ? document.getElementById('pokehub-edit-group-by-generation').checked : true,
                generations_collapsed: document.getElementById('pokehub-edit-generations-collapsed') ? document.getElementById('pokehub-edit-generations-collapsed').checked : false,
                card_background_image_url: cardBgUrl,
            });
            if (!isSpecificCategory) {
                options.include_backgrounds = document.getElementById('pokehub-edit-include-backgrounds') ? document.getElementById('pokehub-edit-include-backgrounds').checked : true;
                options.include_gender = document.getElementById('pokehub-edit-include-gender') ? document.getElementById('pokehub-edit-include-gender').checked : true;
                options.show_gender_symbols = document.getElementById('pokehub-edit-show-gender-symbols') ? document.getElementById('pokehub-edit-show-gender-symbols').checked : true;
                options.only_final_evolution = document.getElementById('pokehub-edit-only-final') ? document.getElementById('pokehub-edit-only-final').checked : false;
                options.include_baby_pokemon = document.getElementById('pokehub-edit-include-babies') ? document.getElementById('pokehub-edit-include-babies').checked : true;
                options.include_forms = document.getElementById('pokehub-edit-include-forms') ? document.getElementById('pokehub-edit-include-forms').checked : true;
                options.include_costumes = document.getElementById('pokehub-edit-include-costumes') ? document.getElementById('pokehub-edit-include-costumes').checked : true;
                options.include_mega = document.getElementById('pokehub-edit-include-mega') ? document.getElementById('pokehub-edit-include-mega').checked : true;
                options.include_gigantamax = document.getElementById('pokehub-edit-include-gigantamax') ? document.getElementById('pokehub-edit-include-gigantamax').checked : true;
                options.include_dynamax = document.getElementById('pokehub-edit-include-dynamax') ? document.getElementById('pokehub-edit-include-dynamax').checked : true;
                options.include_special_attacks = document.getElementById('pokehub-edit-include-special-attacks') ? document.getElementById('pokehub-edit-include-special-attacks').checked : false;
            }
            options.include_national_dex = document.getElementById('pokehub-edit-include-national') ? document.getElementById('pokehub-edit-include-national').checked : true;
            fetch(pokeHubCollections.restUrl + 'collections/' + collectionId, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': pokeHubCollections.nonce,
                },
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
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': pokeHubCollections.nonce,
                    },
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
                headers: { 'X-WP-Nonce': pokeHubCollections.nonce },
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
            return (p.name_fr || p.name_en || '') + (p.form_label ? ' (' + p.form_label + ')' : '');
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
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': pokeHubCollections.nonce,
                    },
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
    var POGO_GROUP_ORDER = ['base', 'alola', 'galar', 'paldea', 'hisui', 'mega', 'gigamax', 'dynamax', 'male', 'female', 'costume', 'other'];
    var POGO_PREFIX = {
        base: '',
        alola: 'alola&',
        galar: 'galar&',
        paldea: 'paldea&',
        hisui: 'hisuian&',
        mega: '',
        gigamax: 'gigamax&',
        dynamax: 'dynamax&',
        male: 'male&',
        female: 'female&',
        costume: '', /* no filtre sûr partout : liste de noms seulement */
        other: '',
    };

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
     * Pokémon GO n’utilise que le nom d’espèce dans les recherches « méga / gigamax& »
     * (ex. « tortank » pas « mega-tortank », « florizarre » pas « florizarregigamax »).
     */
    function pogoSearchFormTokenNamePart(t, gk) {
        if (!t) return t;
        if (gk === 'mega') {
            t = t.replace(/^mega-?/i, '');
        } else if (gk === 'gigamax') {
            t = t.replace(/(gigantamax|gigamax)$/i, '');
            t = t.replace(/^(gigantamax|gigamax)(?!$)/i, '');
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
     * @param {string} tokenMode 'name_fr' | 'name_en' | 'number'
     * @param {string} gk clé de groupe pogo (mega, gigamax, …)
     */
    function pogoListToken(row, tokenMode, gk) {
        if (tokenMode === 'number') {
            var d = pogoDexToken(row);
            if (d) return d;
            return pogoSearchFormTokenNamePart(pogoNameToken(row, 'fr'), gk) || pogoNameToken(row, 'fr');
        }
        var lang = tokenMode === 'name_en' ? 'en' : 'fr';
        var t = pogoNameToken(row, lang);
        t = pogoSearchFormTokenNamePart(t, gk);
        return t || pogoNameToken(row, lang);
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
    function pogoSortTokens(arr, listBy) {
        if (!arr || !arr.length) return;
        if (listBy === 'number') {
            arr.sort(function (a, b) {
                var na = parseInt(a, 10);
                var nb = parseInt(b, 10);
                if (!isNaN(na) && !isNaN(nb) && String(na) === a && String(nb) === b) {
                    return na - nb;
                }
                return a < b ? -1 : a > b ? 1 : 0;
            });
        } else {
            arr.sort();
        }
    }
    function pogoDetectGroup(row) {
        var s = String(row.form_slug || row.slug || '').toLowerCase();
        var c = String(row.form_category || 'normal').toLowerCase();
        if (c === 'gigantamax' || s.indexOf('gigantamax') !== -1) return 'gigamax';
        if (c === 'mega' || s.indexOf('mega') !== -1) return 'mega';
        if (c === 'dynamax' || s.indexOf('dynamax') !== -1) return 'dynamax';
        if (s.indexOf('alola') !== -1) return 'alola';
        if (s.indexOf('galar') !== -1) return 'galar';
        if (s.indexOf('paldea') !== -1) return 'paldea';
        if (s.indexOf('hisui') !== -1 || s.indexOf('hisuian') !== -1) return 'hisui';
        if (c === 'costume' || c === 'costume_shiny' || s.indexOf('costume') !== -1) return 'costume';
        if (c === 'gender') {
            if (s.indexOf('female') !== -1) return 'female';
            if (s.indexOf('male') !== -1) return 'male';
        }
        if (s.indexOf('female') !== -1) return 'female';
        if (s.indexOf('male') !== -1) return 'male';
        if (c !== 'normal' && c !== '' && c !== 'standard') return 'other';
        return 'base';
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
    function pogoBuildPhrasesForWrap(wrap, pogoBlock) {
        var details = pogoBlock || (wrap && wrap.querySelector('.pokehub-collection-pogo-search'));
        if (!details || !wrap) {
            return;
        }
        var outEl = details.querySelector('.pokehub-pogo-search-groups');
        var hint = details.querySelector('.pokehub-collection-pogo-search-hint-refresh');
        if (!outEl) {
            return;
        }
        var root = wrap.querySelector('.pokehub-collection-tiles, .pokehub-collection-tiles-local');
        if (!root) {
            outEl.innerHTML = '<p class="me5rine-lab-form-hint">' + pogoT('pogoNoPool') + '</p>';
            return;
        }
        var pool;
        var items;
        try {
            pool = JSON.parse(root.getAttribute('data-pool') || '[]');
            items = JSON.parse(root.getAttribute('data-items') || '{}');
        } catch (e) {
            outEl.textContent = '';
            outEl.appendChild(pogoP(pogoT('pogoNoPool')));
            return;
        }
        if (!pool || pool.length === 0) {
            outEl.textContent = '';
            outEl.appendChild(pogoP(pogoT('pogoNoPool')));
            return;
        }
        var stEl = details.querySelector('.pokehub-pogo-search-status');
        var status = (stEl && stEl.value) || 'missing';
        var tokenMode = pogoGetTokenMode(wrap, details);
        var listBy = tokenMode === 'number' ? 'number' : 'name';
        var byGroup = {};
        for (var g = 0; g < POGO_GROUP_ORDER.length; g++) {
            byGroup[POGO_GROUP_ORDER[g]] = [];
        }
        for (var p = 0; p < pool.length; p++) {
            var row = pool[p];
            var st = String(items[String(row.id)] || items[row.id] || 'missing');
            if (st !== status) continue;
            var gk = pogoDetectGroup(row);
            if (!byGroup[gk]) byGroup[gk] = [];
            var tok = pogoListToken(row, tokenMode, gk);
            if (tok) byGroup[gk].push(tok);
        }
        for (var k in byGroup) {
            if (Object.prototype.hasOwnProperty.call(byGroup, k) && byGroup[k] && byGroup[k].length) {
                byGroup[k] = byGroup[k].filter(function (x, i, a) {
                    return x && a.indexOf(x) === i;
                });
                pogoSortTokens(byGroup[k], listBy);
            }
        }
        outEl.textContent = '';
        var any = false;
        var gidx;
        for (gidx = 0; gidx < POGO_GROUP_ORDER.length; gidx++) {
            var gkey = POGO_GROUP_ORDER[gidx];
            var names = byGroup[gkey];
            if (!names || !names.length) continue;
            any = true;
            var label = pogoT(pogoGroupLabelKey(gkey, listBy));
            var prefix = POGO_PREFIX[gkey] !== undefined ? POGO_PREFIX[gkey] : '';
            var lines = pogoBuildSinglePhrasePerGroup(prefix, names);
            var groupEl = document.createElement('div');
            groupEl.className = 'pokehub-pogo-search-group';
            groupEl.setAttribute('data-pogo-group', gkey);
            var h = document.createElement('h3');
            h.className = 'me5rine-lab-title-small';
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
    /**
     * Les <select> du bloc « phrases GO » : certains navigateurs ne remontent pas change/input
     * jusqu’au parent <details>, et la .value n’est parfois fiable qu’au frame suivant.
     * On écoute en phase capture sur document + requestAnimationFrame.
     */
    function pogoScheduleRefreshForWrap(wrap) {
        if (!wrap) {
            return;
        }
        var run = function () {
            if (typeof window.pokeHubPogoSearchRefresh === 'function') {
                window.pokeHubPogoSearchRefresh(wrap);
            }
        };
        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(run);
        } else {
            setTimeout(run, 0);
        }
    }
    function initPogoSearch() {
        if (!initPogoSearch._pogoCopyOnBody) {
            initPogoSearch._pogoCopyOnBody = true;
            document.body.addEventListener('click', pogoOnBodyClickCopy);
        }
        if (!initPogoSearch._pogoDocToolbar) {
            initPogoSearch._pogoDocToolbar = true;
            function onPogoToolbarSelect(ev) {
                var t = ev.target;
                if (!t || t.nodeName !== 'SELECT' || !t.classList) {
                    return;
                }
                if (!t.classList.contains('pokehub-pogo-search-status') &&
                    !t.classList.contains('pokehub-pogo-search-token-mode') &&
                    !t.classList.contains('pokehub-pogo-search-list-by') &&
                    !t.classList.contains('pokehub-pogo-search-lang')) {
                    return;
                }
                var w = t.closest && t.closest('.pokehub-collection-view-wrap');
                if (w) {
                    pogoScheduleRefreshForWrap(w);
                }
            }
            document.addEventListener('change', onPogoToolbarSelect, true);
            document.addEventListener('input', onPogoToolbarSelect, true);
        }
        document.querySelectorAll('.pokehub-collection-pogo-search').forEach(function (box) {
            if (box.getAttribute('data-pogo-initialized') === '1') {
                return;
            }
            var wrap = box.closest && box.closest('.pokehub-collection-view-wrap');
            if (!wrap) {
                return;
            }
            box.setAttribute('data-pogo-initialized', '1');
            function refreshPogoFromBlock() {
                pogoScheduleRefreshForWrap(wrap);
            }
            box.addEventListener('toggle', function () {
                if (box.open) {
                    refreshPogoFromBlock();
                }
            }, false);
            pogoBuildPhrasesForWrap(wrap, box);
        });
    }
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(initPogoSearch, 0);
    } else {
        document.addEventListener('DOMContentLoaded', initPogoSearch);
    }
    window.pokeHubPogoSearchRefresh = function (wrap) {
        if (wrap) {
            var pogo = wrap.querySelector && wrap.querySelector('.pokehub-collection-pogo-search');
            pogoBuildPhrasesForWrap(wrap, pogo);
        } else {
            document.querySelectorAll('.pokehub-collection-view-wrap').forEach(function (w) {
                var p = w.querySelector('.pokehub-collection-pogo-search');
                pogoBuildPhrasesForWrap(w, p);
            });
        }
    };
})(jQuery);
