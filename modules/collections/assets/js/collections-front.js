(function ($) {
    'use strict';

    var storageKey = 'poke_hub_collections';

    function cycleStatus(current) {
        if (current === 'missing') return 'owned';
        if (current === 'owned') return 'for_trade';
        return 'missing';
    }
    var modal = document.querySelector('.poke-hub-collections-modal-create');
    var btnCreate = document.querySelector('.poke-hub-collections-btn-create');
    var btnCancel = document.querySelector('.poke-hub-collections-modal-cancel');
    var btnCreateSubmit = document.querySelector('.poke-hub-collections-modal-create-btn');
    var backdrop = document.querySelector('.poke-hub-collections-modal-backdrop');

    function openModal() {
        if (modal) {
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal() {
        if (modal) {
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
    }

    if (btnCreate) {
        btnCreate.addEventListener('click', openModal);
    }
    if (btnCancel) {
        btnCancel.addEventListener('click', closeModal);
    }
    if (backdrop) {
        backdrop.addEventListener('click', closeModal);
    }

    function getCreateFormData() {
        return {
            name: document.getElementById('poke-hub-collection-name')?.value?.trim() || '',
            category: document.getElementById('poke-hub-collection-category')?.value || 'custom',
            is_public: document.getElementById('poke-hub-collection-public')?.checked || false,
            options: {
                include_national_dex: document.getElementById('poke-hub-collection-include-national')?.checked !== false,
                include_gender: document.getElementById('poke-hub-collection-include-gender')?.checked !== false,
                include_forms: document.getElementById('poke-hub-collection-include-forms')?.checked !== false,
                include_costumes: document.getElementById('poke-hub-collection-include-costumes')?.checked !== false,
                include_special_attacks: document.getElementById('poke-hub-collection-include-special-attacks')?.checked || false,
                exclude_mega: document.getElementById('poke-hub-collection-exclude-mega')?.checked || false,
                one_per_species: document.getElementById('poke-hub-collection-one-per-species')?.checked || false,
                group_by_generation: document.getElementById('poke-hub-collection-group-by-generation')?.checked !== false,
                display_mode: document.querySelector('input[name="poke-hub-collection-display"]:checked')?.value || 'tiles',
                public: document.getElementById('poke-hub-collection-public')?.checked || false,
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
                        closeModal();
                        var base = window.location.origin + window.location.pathname.replace(/\/$/, '');
                        window.location.href = base + '/' + data.share_token;
                    } else if (data.success && data.collection_id) {
                        closeModal();
                        var b = window.location.origin + window.location.pathname.replace(/\/$/, '');
                        window.location.href = b + '/' + (data.share_token || data.collection_id);
                    } else if (data.success) {
                        closeModal();
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
    var tileContainer = document.querySelector('.poke-hub-collection-tiles');
    if (tileContainer) {
        var canEdit = tileContainer.closest('.poke-hub-collection-view-wrap')?.getAttribute('data-can-edit') === '1';
        var collectionId = tileContainer.closest('.poke-hub-collection-view-wrap')?.getAttribute('data-collection-id');
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
            var tile = e.target.closest('.poke-hub-collection-tile');
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
    var localWrap = document.querySelector('.poke-hub-collection-view-local');
    if (localWrap && typeof pokeHubCollections !== 'undefined') {
        var localSlug = localWrap.getAttribute('data-collection-slug');
        var stored = JSON.parse(localStorage.getItem(storageKey) || '[]');
        var col = stored.find(function (c) { return c.id === localSlug; });
        if (col) {
            var titleEl = localWrap.querySelector('.poke-hub-collection-local-title');
            if (titleEl) titleEl.textContent = col.name;
            fetch(pokeHubCollections.restUrl + 'collections/pool?category=' + encodeURIComponent(col.category) + '&options=' + encodeURIComponent(JSON.stringify(col.options || {})))
                .then(function (r) { return r.json(); })
                .then(function (pool) {
                    var items = col.items || {};
                    var tilesEl = localWrap.querySelector('.poke-hub-collection-tiles-local');
                    if (!tilesEl) return;
                    var assetsBase = (pokeHubCollections.pokemonIconsBase || '');
                    pool.forEach(function (p) {
                        var status = items[p.id] || 'missing';
                        var imgSrc = assetsBase ? assetsBase + p.id + '.png' : '';
                        var tile = document.createElement('div');
                        tile.className = 'poke-hub-collection-tile';
                        tile.setAttribute('data-pokemon-id', p.id);
                        tile.setAttribute('data-status', status);
                        tile.setAttribute('tabindex', '0');
                        tile.setAttribute('role', 'button');
                        tile.innerHTML = (imgSrc ? '<img src="' + imgSrc + '" alt="" loading="lazy" />' : '') +
                            '<span class="poke-hub-collection-tile-name">' + (p.name_fr || p.name_en || '') + '</span>' +
                            '<span class="poke-hub-collection-tile-status poke-hub-status-' + status + '"></span>';
                        tile.addEventListener('click', function () {
                            var next = cycleStatus(status);
                            status = next;
                            tile.setAttribute('data-status', status);
                            items[p.id] = status;
                            col.items = items;
                            localStorage.setItem(storageKey, JSON.stringify(stored));
                            var total = pool.length;
                            var owned = Object.keys(items).filter(function (k) { return items[k] === 'owned'; }).length;
                            var statsEl = localWrap.querySelector('.poke-hub-collection-local-stats');
                            if (statsEl) statsEl.textContent = owned + ' / ' + total;
                        });
                        tilesEl.appendChild(tile);
                    });
                    var total = pool.length;
                    var owned = Object.keys(items).filter(function (k) { return items[k] === 'owned'; }).length;
                    var statsEl = localWrap.querySelector('.poke-hub-collection-local-stats');
                    if (statsEl) statsEl.textContent = owned + ' / ' + total;
                })
                .catch(function () {
                    var tilesEl = localWrap.querySelector('.poke-hub-collection-tiles-local');
                    if (tilesEl) tilesEl.innerHTML = '<p class="poke-hub-collections-not-found">Impossible de charger le pool.</p>';
                });
        } else {
            localWrap.querySelector('.poke-hub-collection-local-title').textContent = 'Collection introuvable';
            localWrap.querySelector('.poke-hub-collection-tiles-local').innerHTML = '<p class="poke-hub-collections-not-found">Cette collection n’existe plus en local.</p>';
        }
    }

    // Share button : URL canonique avec le jeton de partage (ex. /collections/nw98Z3L2UzjXe6)
    var btnShare = document.querySelector('.poke-hub-collections-btn-share');
    if (btnShare) {
        btnShare.addEventListener('click', function () {
            var wrap = btnShare.closest('.poke-hub-collection-view-wrap');
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

    // Edit settings modal (vue collection)
    var viewWrap = document.querySelector('.poke-hub-collection-view-wrap[data-collection-id]');
    var modalEdit = document.querySelector('.poke-hub-collections-modal-edit');
    var btnEditSettings = document.querySelector('.poke-hub-collections-btn-edit-settings');
    var btnEditCancel = document.querySelector('.poke-hub-collections-modal-edit-cancel');
    var btnEditSave = document.querySelector('.poke-hub-collections-modal-edit-save');
    var editBackdrop = modalEdit && modalEdit.querySelector('.poke-hub-collections-modal-backdrop');

    function openEditModal() {
        if (modalEdit) {
            modalEdit.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeEditModal() {
        if (modalEdit) {
            modalEdit.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
    }

    if (btnEditSettings) {
        btnEditSettings.addEventListener('click', openEditModal);
    }
    if (btnEditCancel) {
        btnEditCancel.addEventListener('click', closeEditModal);
    }
    if (editBackdrop) {
        editBackdrop.addEventListener('click', closeEditModal);
    }

    // Ouvrir la modal d'édition si ?edit=1
    if (viewWrap && modalEdit && typeof window.URLSearchParams !== 'undefined') {
        var params = new URLSearchParams(window.location.search);
        if (params.get('edit') === '1') {
            openEditModal();
        }
    }

    if (btnEditSave && viewWrap && typeof pokeHubCollections !== 'undefined') {
        var collectionId = viewWrap.getAttribute('data-collection-id');
        btnEditSave.addEventListener('click', function () {
            var nameEl = document.getElementById('poke-hub-edit-collection-name');
            var name = nameEl ? nameEl.value.trim() : '';
            var isPublic = document.getElementById('poke-hub-edit-collection-public') ? document.getElementById('poke-hub-edit-collection-public').checked : false;
            var displayModeEl = document.querySelector('input[name="poke-hub-edit-collection-display"]:checked');
            var displayMode = displayModeEl ? displayModeEl.value : 'tiles';
            var currentOptions = {};
            try {
                var optsJson = viewWrap.getAttribute('data-edit-options');
                if (optsJson) currentOptions = JSON.parse(optsJson);
            } catch (e) {}
            var options = Object.assign({}, currentOptions, {
                display_mode: displayMode,
                exclude_mega: document.getElementById('poke-hub-edit-exclude-mega') ? document.getElementById('poke-hub-edit-exclude-mega').checked : false,
                one_per_species: document.getElementById('poke-hub-edit-one-per-species') ? document.getElementById('poke-hub-edit-one-per-species').checked : false,
                group_by_generation: document.getElementById('poke-hub-edit-group-by-generation') ? document.getElementById('poke-hub-edit-group-by-generation').checked : true,
            });
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
                        closeEditModal();
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
    var btnDeleteCollection = document.querySelector('.poke-hub-collections-btn-delete-collection');
    if (btnDeleteCollection && viewWrap && typeof pokeHubCollections !== 'undefined') {
        var viewCollectionId = viewWrap.getAttribute('data-collection-id');
        var viewCollectionName = viewWrap.getAttribute('data-edit-name') || viewWrap.querySelector('.poke-hub-collection-view-title')?.textContent || '';
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
    var listWrap = document.querySelector('.poke-hub-collections-wrap');
    if (listWrap && typeof pokeHubCollections !== 'undefined') {
        listWrap.addEventListener('click', function (e) {
            var btn = e.target.closest('.poke-hub-collections-btn-delete-list');
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

    // Multiselect avec recherche pour les manquants (mode liste + select)
    var multiselectWrap = document.querySelector('.poke-hub-collection-multiselect-wrap');
    var missingSearch = document.getElementById('poke-hub-collection-missing-search');
    var multiselectList = document.getElementById('poke-hub-collection-multiselect-list');
    var multiselectAddBtn = document.querySelector('.poke-hub-collection-multiselect-add');
    var tileContainer = document.querySelector('.poke-hub-collection-tiles');
    if (multiselectWrap && missingSearch && multiselectList && tileContainer && typeof pokeHubCollections !== 'undefined') {
        var collectionId = tileContainer.closest('.poke-hub-collection-view-wrap')?.getAttribute('data-collection-id');
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

        function matchSearch(p, q) {
            if (!q) return true;
            q = q.toLowerCase();
            var label = getLabel(p).toLowerCase();
            var dex = String(p.dex_number || '').toLowerCase();
            return label.indexOf(q) !== -1 || dex.indexOf(q) !== -1;
        }

        function renderList() {
            var q = (missingSearch.value || '').trim();
            var missing = getMissingPool();
            var toShow = q ? missing.filter(function (p) { return matchSearch(p, q); }) : missing;
            multiselectList.innerHTML = '';
            toShow.forEach(function (p) {
                var id = 'poke-hub-ms-' + p.id;
                var div = document.createElement('div');
                div.className = 'poke-hub-collection-multiselect-item';
                div.setAttribute('role', 'option');
                div.setAttribute('aria-selected', 'false');
                div.setAttribute('data-pokemon-id', String(p.id));
                div.innerHTML = '<input type="checkbox" id="' + id + '" value="' + p.id + '" class="poke-hub-collection-multiselect-cb" /> <label for="' + id + '">' + (p.dex_number ? p.dex_number + ' – ' : '') + getLabel(p) + '</label>';
                div.querySelector('input').addEventListener('change', function () {
                    div.setAttribute('aria-selected', this.checked ? 'true' : 'false');
                });
                multiselectList.appendChild(div);
            });
            if (toShow.length === 0) {
                var empty = document.createElement('p');
                empty.className = 'poke-hub-collection-multiselect-empty';
                empty.textContent = q ? 'Aucun Pokémon ne correspond à la recherche.' : 'Aucun Pokémon manquant.';
                multiselectList.appendChild(empty);
            }
        }

        missingSearch.addEventListener('input', renderList);

        if (multiselectAddBtn && collectionId) {
            multiselectAddBtn.addEventListener('click', function () {
                var checked = multiselectList.querySelectorAll('.poke-hub-collection-multiselect-cb:checked');
                if (checked.length === 0) {
                    return;
                }
                var ids = Array.prototype.map.call(checked, function (cb) { return parseInt(cb.value, 10); });
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

        renderList();
    }

    // Bandeau « collections anonymes à rattacher » (utilisateur connecté)
    var banner = document.getElementById('poke-hub-collections-anonymous-banner');
    if (banner && listWrap && listWrap.getAttribute('data-logged-in') === '1' && typeof pokeHubCollections !== 'undefined') {
        var bannerText = banner.querySelector('.poke-hub-collections-anonymous-banner-text');
        var bannerList = banner.querySelector('.poke-hub-collections-anonymous-list');
        var claimAllBtn = banner.querySelector('.poke-hub-collections-claim-all');
        var dismissBtn = banner.querySelector('.poke-hub-collections-dismiss-banner');
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
            banner.style.display = 'block';
        }

        function hideBanner() {
            banner.style.display = 'none';
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
