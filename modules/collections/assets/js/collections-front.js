(function ($) {
    'use strict';

    var storageKey = 'poke_hub_collections';

    function cycleStatus(current) {
        if (current === 'missing') return 'owned';
        if (current === 'owned') return 'for_trade';
        return 'missing';
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
        return {
            name: document.getElementById('pokehub-collection-name')?.value?.trim() || '',
            category: document.getElementById('pokehub-collection-category')?.value || 'custom',
            is_public: document.getElementById('pokehub-collection-public')?.checked || false,
            options: {
                include_national_dex: document.getElementById('pokehub-collection-include-national')?.checked !== false,
                include_gender: document.getElementById('pokehub-collection-include-gender')?.checked !== false,
                include_forms: document.getElementById('pokehub-collection-include-forms')?.checked !== false,
                include_costumes: document.getElementById('pokehub-collection-include-costumes')?.checked !== false,
                include_mega: document.getElementById('pokehub-collection-include-mega')?.checked !== false,
                include_gigantamax: document.getElementById('pokehub-collection-include-gigantamax')?.checked !== false,
                include_dynamax: document.getElementById('pokehub-collection-include-dynamax')?.checked !== false,
                include_special_attacks: document.getElementById('pokehub-collection-include-special-attacks')?.checked || false,
                one_per_species: document.getElementById('pokehub-collection-one-per-species')?.checked || false,
                group_by_generation: document.getElementById('pokehub-collection-group-by-generation')?.checked !== false,
                generations_collapsed: document.getElementById('pokehub-collection-generations-collapsed')?.checked || false,
                display_mode: document.querySelector('input[name="pokehub-collection-display"]:checked')?.value || 'tiles',
                public: document.getElementById('pokehub-collection-public')?.checked || false,
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
        var canEdit = tileContainer.closest('.pokehub-collection-view-wrap')?.getAttribute('data-can-edit') === '1';
        var collectionId = tileContainer.closest('.pokehub-collection-view-wrap')?.getAttribute('data-collection-id');
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
            var pokemonId = parseInt(tile.getAttribute('data-pokemon-id'), 10);
            var status = tile.getAttribute('data-status') || 'missing';
            var next = cycleStatus(status);
            tile.setAttribute('data-status', next);
            items[pokemonId] = next;

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
    var localWrap = document.querySelector('.pokehub-collection-view-local');
    if (localWrap && typeof pokeHubCollections !== 'undefined') {
        var localSlug = localWrap.getAttribute('data-collection-slug');
        var stored = JSON.parse(localStorage.getItem(storageKey) || '[]');
        var col = stored.find(function (c) { return c.id === localSlug; });
        if (col) {
            var titleEl = localWrap.querySelector('.pokehub-collection-local-title');
            if (titleEl) titleEl.textContent = col.name;
            fetch(pokeHubCollections.restUrl + 'collections/pool?category=' + encodeURIComponent(col.category) + '&options=' + encodeURIComponent(JSON.stringify(col.options || {})))
                .then(function (r) { return r.json(); })
                .then(function (pool) {
                    var items = col.items || {};
                    var tilesEl = localWrap.querySelector('.pokehub-collection-tiles-local');
                    if (!tilesEl) return;
                    var assetsBase = (pokeHubCollections.pokemonIconsBase || '');
                    pool.forEach(function (p) {
                        var status = items[p.id] || 'missing';
                        var imgSrc = assetsBase ? assetsBase + p.id + '.png' : '';
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
                        tile.innerHTML = figureHtml +
                            '<span class="pokehub-collection-tile-name">' + (p.name_fr || p.name_en || '') + '</span>' +
                            '<span class="pokehub-collection-tile-status pokehub-status-' + status + '"></span>';
                        tile.addEventListener('click', function () {
                            var next = cycleStatus(status);
                            status = next;
                            tile.setAttribute('data-status', status);
                            items[p.id] = status;
                            col.items = items;
                            localStorage.setItem(storageKey, JSON.stringify(stored));
                            var total = pool.length;
                            var owned = Object.keys(items).filter(function (k) { return items[k] === 'owned'; }).length;
                            var statsEl = localWrap.querySelector('.pokehub-collection-local-stats');
                            if (statsEl) statsEl.textContent = owned + ' / ' + total;
                        });
                        tilesEl.appendChild(tile);
                    });
                    var total = pool.length;
                    var owned = Object.keys(items).filter(function (k) { return items[k] === 'owned'; }).length;
                    var statsEl = localWrap.querySelector('.pokehub-collection-local-stats');
                    if (statsEl) statsEl.textContent = owned + ' / ' + total;
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
                options.include_costumes = document.getElementById('pokehub-edit-include-costumes') ? document.getElementById('pokehub-edit-include-costumes').checked : true;
                options.include_mega = document.getElementById('pokehub-edit-include-mega') ? document.getElementById('pokehub-edit-include-mega').checked : true;
                options.include_gigantamax = document.getElementById('pokehub-edit-include-gigantamax') ? document.getElementById('pokehub-edit-include-gigantamax').checked : true;
                options.include_dynamax = document.getElementById('pokehub-edit-include-dynamax') ? document.getElementById('pokehub-edit-include-dynamax').checked : true;
            }
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

    // Supprimer la collection (depuis la vue)
    var btnDeleteCollection = document.querySelector('.pokehub-collections-btn-delete-collection');
    if (btnDeleteCollection && viewWrap && typeof pokeHubCollections !== 'undefined') {
        var viewCollectionId = viewWrap.getAttribute('data-collection-id');
        var viewCollectionName = viewWrap.getAttribute('data-edit-name') || viewWrap.querySelector('.pokehub-collection-view-title')?.textContent || '';
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

    // Select2 pour les manquants (mode liste + select) — un seul select avec recherche
    var multiselectWrap = document.querySelector('.pokehub-collection-multiselect-wrap');
    var missingSelect = document.getElementById('pokehub-collection-missing-select');
    var multiselectAddBtn = document.querySelector('.pokehub-collection-multiselect-add');
    var tileContainer = document.querySelector('.pokehub-collection-tiles');
    if (multiselectWrap && missingSelect && tileContainer && typeof pokeHubCollections !== 'undefined') {
        var collectionId = tileContainer.closest('.pokehub-collection-view-wrap')?.getAttribute('data-collection-id');
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

        var missing = getMissingPool();
        var placeholder = missingSelect.getAttribute('data-placeholder') || 'Search by name or #…';
        missing.forEach(function (p) {
            var opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = (p.dex_number ? p.dex_number + ' – ' : '') + getLabel(p);
            missingSelect.appendChild(opt);
        });

        if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
            jQuery(missingSelect).select2({
                placeholder: placeholder,
                width: '100%',
                allowClear: true,
            });
        }

        if (multiselectAddBtn && collectionId) {
            multiselectAddBtn.addEventListener('click', function () {
                var selected = typeof jQuery !== 'undefined' && jQuery(missingSelect).val ? jQuery(missingSelect).val() : [];
                if (!selected || selected.length === 0) return;
                var ids = selected.map(function (v) { return parseInt(v, 10); });
                multiselectAddBtn.disabled = true;
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
                        body: JSON.stringify({ pokemon_id: pokemonId, status: 'owned' }),
                        credentials: 'same-origin',
                    })
                        .then(function () { done++; next(); })
                        .catch(function () {
                            alert('Erreur lors de l\'ajout.');
                            multiselectAddBtn.disabled = false;
                        });
                }
                next();
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
})(jQuery);
