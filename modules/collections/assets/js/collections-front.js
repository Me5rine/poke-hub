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

    /** Libellés longs / courts des tuiles barre collection fixe (voir data-collection-fixed-label-short). */
    window.pokehubRefreshFixedCollectionTileLabels = function () {
        var short = typeof window.matchMedia === 'function' && window.matchMedia('(max-width: 640px)').matches;
        document.querySelectorAll('.pokehub-collection-fixed-tile-btn[data-label-full]').forEach(function (btn) {
            var span = btn.querySelector('.pokehub-collection-fixed-tile-btn-label');
            var full = btn.getAttribute('data-label-full') || '';
            var sh = btn.getAttribute('data-label-short') || full;
            if (span) span.textContent = short ? sh : full;
            btn.setAttribute('aria-label', full);
        });
    };

    /** Resize (ex. mode inspection) : évite des centaines d’updates — même effet après la fin du drag. */
    window.pokehubRefreshFixedCollectionTileLabelsDebounced = (function () {
        var t = null;
        return function () {
            if (t) {
                clearTimeout(t);
            }
            t = setTimeout(function () {
                t = null;
                window.pokehubRefreshFixedCollectionTileLabels();
            }, 120);
        };
    })();

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

    function pokehubCollectionsI18n(key, fallback) {
        var pack = (typeof pokeHubCollections !== 'undefined' && pokeHubCollections.i18n) ? pokeHubCollections.i18n : {};
        var v = pack[key];
        return (typeof v === 'string' && v !== '') ? v : (fallback || '');
    }

    function pokehubCloseSharePopover(pop) {
        if (!pop) return;
        pop.hidden = true;
        pop.setAttribute('aria-hidden', 'true');
    }

    function pokehubFillShareCover(mount, imgUrl) {
        if (!mount) return;
        mount.innerHTML = '';
        var url = (imgUrl || '').trim();
        if (url) {
            var img = document.createElement('img');
            img.src = url;
            img.alt = pokehubCollectionsI18n('shareCoverAlt', 'Collection cover image');
            img.className = 'pokehub-share-popover__cover-img';
            img.referrerPolicy = 'no-referrer';
            mount.appendChild(img);
        } else {
            var pEl = document.createElement('p');
            pEl.className = 'me5rine-lab-form-hint pokehub-share-popover__cover-empty';
            pEl.textContent = pokehubCollectionsI18n('shareCoverEmpty', '');
            mount.appendChild(pEl);
        }
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
     * Réinitialisation : pas de window.confirm — affiche une bannière me5rine-lab-form-message-error
     * sous le header (.pokehub-collections-reset-banner). Le bouton « Reset progress » reste en place.
     * @param {Element} scope .pokehub-collections-reset-inline or ancestor
     * @param {function} onApply appelé au clic sur confirmer
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
        var wrap = block.closest('.pokehub-collection-view-wrap');
        var banner = wrap ? wrap.querySelector('.pokehub-collections-reset-banner') : null;
        var legacyConfirm = block.querySelector('.pokehub-collections-reset-step-confirm');
        var applyBtn = banner
            ? banner.querySelector('.pokehub-collections-btn-reset-apply')
            : block.querySelector('.pokehub-collections-btn-reset-apply');
        var dismiss = banner
            ? banner.querySelector('.pokehub-collections-btn-reset-dismiss')
            : block.querySelector('.pokehub-collections-btn-reset-dismiss');
        var launch = block.querySelector('.pokehub-collections-btn-reset-launch');

        function undockResetBannerIfNeeded() {
            if (!banner || !wrap) {
                return;
            }
            if (!banner._pokehubDockedInFixed) {
                return;
            }
            var parentEl = banner._pokehubRevertParent;
            var nextEl = banner._pokehubRevertNext;
            if (parentEl) {
                if (nextEl && nextEl.parentNode === parentEl) {
                    parentEl.insertBefore(banner, nextEl);
                } else {
                    parentEl.appendChild(banner);
                }
            }
            banner._pokehubDockedInFixed = false;
            banner._pokehubRevertParent = null;
            banner._pokehubRevertNext = null;
            banner.classList.remove('pokehub-collections-reset-banner--docked-fixed');
            var fixedSlot = wrap.querySelector('[data-pokehub-reset-fixed-slot]');
            if (fixedSlot) {
                fixedSlot.setAttribute('hidden', 'hidden');
                fixedSlot.setAttribute('aria-hidden', 'true');
            }
        }

        function showConfirm() {
            if (banner) {
                var fixedSlot = wrap && wrap.querySelector('[data-pokehub-reset-fixed-slot]');
                var useFixedDock =
                    fixedSlot &&
                    wrap.classList.contains('pokehub-collection--fixed-toolbar');
                if (useFixedDock) {
                    if (banner.parentNode !== fixedSlot) {
                        undockResetBannerIfNeeded();
                        banner._pokehubRevertParent = banner.parentNode;
                        banner._pokehubRevertNext = banner.nextSibling;
                        banner._pokehubDockedInFixed = true;
                        fixedSlot.appendChild(banner);
                    }
                    fixedSlot.removeAttribute('hidden');
                    fixedSlot.setAttribute('aria-hidden', 'false');
                    banner.classList.add('pokehub-collections-reset-banner--docked-fixed');
                    banner.removeAttribute('hidden');
                } else {
                    undockResetBannerIfNeeded();
                    banner.removeAttribute('hidden');
                    try {
                        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    } catch (eIgnore) {}
                }
                window.setTimeout(function () {
                    if (applyBtn && typeof applyBtn.focus === 'function') {
                        applyBtn.focus();
                    }
                }, useFixedDock ? 80 : 220);
            } else if (legacyConfirm) {
                var initial = block.querySelector('.pokehub-collections-reset-step-initial');
                if (initial) initial.setAttribute('hidden', 'hidden');
                legacyConfirm.removeAttribute('hidden');
            }
        }
        function hideConfirm() {
            if (banner) {
                undockResetBannerIfNeeded();
                banner.setAttribute('hidden', 'hidden');
            }
            if (legacyConfirm) {
                legacyConfirm.setAttribute('hidden', 'hidden');
                var initEl = block.querySelector('.pokehub-collections-reset-step-initial');
                if (initEl) initEl.removeAttribute('hidden');
            }
        }

        if (banner && applyBtn && dismiss && !banner.getAttribute('data-pokehub-reset-actions-bound')) {
            banner.setAttribute('data-pokehub-reset-actions-bound', '1');
            applyBtn.addEventListener('click', function () {
                onApply();
                hideConfirm();
            });
            dismiss.addEventListener('click', hideConfirm);
        } else if (!banner && applyBtn && dismiss) {
            dismiss.addEventListener('click', hideConfirm);
            applyBtn.addEventListener('click', function () {
                onApply();
                hideConfirm();
            });
        }

        if (launch) {
            launch.addEventListener('click', showConfirm);
        }
    }

    function cycleStatus(current) {
        if (current === 'missing') return 'owned';
        if (current === 'owned') return 'for_trade';
        return 'missing';
    }

    /**
     * Recalcule progression par région / génération (même logique que le PHP) et met à jour
     * compteurs en-tête, liens « jump », résumés <summary> et images tuile (missing vs actif).
     */
    function pokehubItemStatusForId(items, id) {
        if (!items) return undefined;
        var k = String(id);
        if (Object.prototype.hasOwnProperty.call(items, k) && items[k] != null) return String(items[k]);
        if (Object.prototype.hasOwnProperty.call(items, id) && items[id] != null) return String(items[id]);
        return undefined;
    }

    function pokehubPoolRowGenerationGroupLabel(p, i18n) {
        i18n = i18n || {};
        var region = (p.pokemon_region_name && String(p.pokemon_region_name).trim()) || '';
        if (region) return region;
        var fromGen = (p.generation_region_fallback_name && String(p.generation_region_fallback_name).trim()) || '';
        if (fromGen) return fromGen;
        var genNum = parseInt(p.generation_number, 10) || 0;
        var genName = (p.generation_name && String(p.generation_name).trim()) || '';
        if (genName) return genName;
        if (genNum > 0) {
            var tpl = i18n.generationNumber || 'Generation %d';
            return tpl.replace('%d', String(genNum));
        }
        return '';
    }

    function pokehubResolvedStatusForRow(p, items) {
        var id = parseInt(p.id, 10) || 0;
        var st = pokehubItemStatusForId(items, id);
        if (st !== undefined) {
            return (st === 'owned' || st === 'for_trade' || st === 'missing') ? st : 'missing';
        }
        var bid = parseInt(p.synthetic_sex_base_id, 10) || 0;
        if (p.synthetic_sex_collector && bid > 0) {
            st = pokehubItemStatusForId(items, bid);
            if (st !== undefined) {
                return (st === 'owned' || st === 'for_trade' || st === 'missing') ? st : 'missing';
            }
        }
        return 'missing';
    }

    function pokehubForTradeCountsAsOwnedFromOpts(options) {
        var o = options && typeof options === 'object' ? options : {};
        return o.for_trade_counts_as_owned !== false;
    }

    /** @param {Element|null|undefined} wrap .pokehub-collection-view-wrap */
    function pokehubForTradeCountsAsOwnedFromWrap(wrap) {
        if (!wrap || !wrap.getAttribute) return true;
        return wrap.getAttribute('data-for-trade-counts-as-owned') !== '0';
    }

    /**
     * Met à jour data-for-trade-counts-as-owned et le texte de légende (.pokehub-collection-for-trade-rule-hint).
     * @param {Element|null|undefined} wrap
     * @param {object} [options]
     */
    function pokehubSyncForTradeOwnedRuleUI(wrap, options) {
        if (!wrap || !wrap.querySelectorAll) return;
        var on = pokehubForTradeCountsAsOwnedFromOpts(options);
        wrap.setAttribute('data-for-trade-counts-as-owned', on ? '1' : '0');
        var i18n = (typeof pokeHubCollections !== 'undefined' && pokeHubCollections.i18n) ? pokeHubCollections.i18n : {};
        var text = on
            ? (i18n.forTradeProgressLegendCounted || '')
            : (i18n.forTradeProgressLegendSeparate || '');
        wrap.querySelectorAll('.pokehub-collection-for-trade-rule-hint').forEach(function (el) {
            el.textContent = text;
        });
    }

    /** @param {Element|null|undefined} wrap ascendant collection view (attribut data-for-trade-counts-as-owned). */
    function pokehubStatusCountsAsOwned(status, wrap) {
        var includeForTrade = pokehubForTradeCountsAsOwnedFromWrap(wrap);
        return status === 'owned' || (includeForTrade && status === 'for_trade');
    }

    /** @param {Element|null|undefined} wrap */
    function pokehubGenerationProgressFromPool(pool, items, wrap) {
        var map = {};
        var i18n = (typeof pokeHubCollections !== 'undefined' && pokeHubCollections.i18n) ? pokeHubCollections.i18n : {};
        pool.forEach(function (p) {
            if (!p || typeof p !== 'object') return;
            var label = pokehubPoolRowGenerationGroupLabel(p, i18n);
            if (!Object.prototype.hasOwnProperty.call(map, label)) {
                map[label] = { owned: 0, for_trade: 0, total: 0 };
            }
            map[label].total++;
            var st = pokehubResolvedStatusForRow(p, items);
            if (st === 'for_trade') {
                map[label].for_trade++;
            }
            if (pokehubStatusCountsAsOwned(st, wrap)) {
                map[label].owned++;
            }
        });
        return map;
    }

    function pokehubSetTileMediaBackgroundFromAttrs(mediaEl, allMissing) {
        if (!mediaEl) return;
        var uMiss = mediaEl.getAttribute('data-pokehub-gen-tile-bg-missing') || '';
        var uAct = mediaEl.getAttribute('data-pokehub-gen-tile-bg-active') || '';
        var pick = allMissing ? (uMiss || uAct) : (uAct || uMiss);
        if (!pick) return;
        var urlCss = 'url(' + JSON.stringify(pick).slice(1, -1) + ')';
        if (mediaEl.classList && mediaEl.classList.contains('pokehub-collection-generation-jump-link--has-media') && mediaEl.classList.contains('pokehub-collection-region-tile--jump')) {
            mediaEl.style.setProperty('--pokehub-gen-jump-bg', urlCss);
            return;
        }
        mediaEl.style.backgroundImage = urlCss;
    }

    function pokehubUpdateRegionTileProgressUI(tileRoot, prog, o) {
        var gOwned = prog.owned;
        var gTotal = prog.total;
        var gForTrade = prog.for_trade || 0;
        var gAllMissing = gTotal > 0 && gOwned === 0 && gForTrade === 0;
        var gDone = gTotal > 0 && gOwned >= gTotal;
        var gPct = gTotal > 0 ? Math.min(100, Math.round((gOwned / gTotal) * 100)) : 0;
        var gjShowBar = gTotal > 0 && !gDone;
        var genLabel = o.genLabel;

        if (o.isJump) {
            tileRoot.classList.toggle('pokehub-collection-generation-jump-link--empty', gTotal === 0);
            tileRoot.classList.toggle('pokehub-collection-generation-jump-link--zero-progress', gTotal > 0 && gAllMissing);
            tileRoot.classList.toggle('pokehub-collection-region-tile--owned-shared', gTotal > 0 && gOwned > 0);
            if (o.ariaJumpTpl) {
                var ariaj = o.ariaJumpTpl.replace(/%1\$s/g, genLabel).replace(/%2\$d/g, String(gOwned)).replace(/%3\$d/g, String(gTotal));
                tileRoot.setAttribute('aria-label', ariaj);
            }
        } else {
            tileRoot.classList.toggle('pokehub-collection-region-tile--empty-region', gTotal === 0);
            tileRoot.classList.toggle('pokehub-collection-region-tile--zero-progress', gTotal > 0 && gAllMissing);
            tileRoot.classList.toggle('pokehub-collection-region-tile--owned-shared', gTotal > 0 && gOwned > 0);
        }

        var body = tileRoot.querySelector('.pokehub-collection-region-tile__body');
        if (!body) return;
        var titleEl = body.querySelector('.pokehub-collection-region-tile__title');
        var finishedText = o.finishedText || 'Finished';

        var completeEl = body.querySelector('.pokehub-collection-region-tile__complete');
        if (gDone) {
            if (!completeEl && titleEl) {
                var sp = document.createElement('span');
                sp.className = 'pokehub-collection-region-tile__complete';
                sp.textContent = finishedText;
                titleEl.insertAdjacentElement('afterend', sp);
            }
        } else if (completeEl) {
            completeEl.remove();
        }

        var statsEl = body.querySelector('.pokehub-collection-region-tile__stats');
        if (gTotal > 0) {
            if (!statsEl) {
                statsEl = document.createElement('span');
                statsEl.className = 'pokehub-collection-region-tile__stats';
                if (o.isJump) statsEl.setAttribute('aria-hidden', 'true');
                body.appendChild(statsEl);
            }
            statsEl.textContent = String(gOwned) + ' / ' + String(gTotal);
        } else if (statsEl) {
            statsEl.remove();
        }

        var barWrap = body.querySelector('.pokehub-collection-region-tile__bar');
        if (gjShowBar) {
            if (!barWrap) {
                barWrap = document.createElement('span');
                barWrap.className = 'pokehub-collection-region-tile__bar';
                barWrap.setAttribute('role', 'progressbar');
                barWrap.setAttribute('aria-valuemin', '0');
                body.appendChild(barWrap);
            }
            var fill = barWrap.querySelector('.pokehub-collection-region-tile__bar-fill');
            if (!fill) {
                fill = document.createElement('span');
                fill.className = 'pokehub-collection-region-tile__bar-fill';
                barWrap.appendChild(fill);
            }
            fill.style.width = String(gPct) + '%';
            barWrap.setAttribute('aria-valuemax', String(gTotal));
            barWrap.setAttribute('aria-valuenow', String(gOwned));
            if (o.isJump && o.ariaJumpTpl) {
                var aBar = o.ariaJumpTpl.replace(/%1\$s/g, genLabel).replace(/%2\$d/g, String(gOwned)).replace(/%3\$d/g, String(gTotal));
                barWrap.setAttribute('aria-label', aBar);
            } else if (!o.isJump && o.ariaSummaryTpl) {
                var aBar2 = o.ariaSummaryTpl.replace(/%1\$s/g, genLabel).replace(/%2\$d/g, String(gOwned)).replace(/%3\$d/g, String(gTotal));
                barWrap.setAttribute('aria-label', aBar2);
            }
            if (statsEl && statsEl.nextSibling !== barWrap) {
                statsEl.insertAdjacentElement('afterend', barWrap);
            } else if (!statsEl && body.lastElementChild !== barWrap) {
                body.appendChild(barWrap);
            }
        } else if (barWrap) {
            barWrap.remove();
        }

        var mediaTarget = (o.isJump && tileRoot.classList && tileRoot.classList.contains('pokehub-collection-generation-jump-link--has-media'))
            ? tileRoot
            : tileRoot.querySelector('.pokehub-collection-region-tile__media');
        pokehubSetTileMediaBackgroundFromAttrs(mediaTarget, gAllMissing);
    }

    function pokehubRefreshCollectionProgressUIFromItems(viewWrap, tileContainer, items) {
        if (!viewWrap || !tileContainer) return;
        var pool = [];
        try {
            pool = JSON.parse(tileContainer.getAttribute('data-pool') || '[]');
        } catch (e0) {}
        if (!pool.length) return;

        var genProg = pokehubGenerationProgressFromPool(pool, items, viewWrap);
        var ownedAll = 0;
        pool.forEach(function (p) {
            if (pokehubStatusCountsAsOwned(pokehubResolvedStatusForRow(p, items), viewWrap)) ownedAll++;
        });
        var totalPool = pool.length;

        var ariaProg = viewWrap.getAttribute('data-pokehub-aria-progress') || '';
        var ariaJump = viewWrap.getAttribute('data-pokehub-aria-jump') || '';
        var ariaSum = viewWrap.getAttribute('data-pokehub-aria-summary') || '';
        var finishedText = (typeof pokeHubCollections !== 'undefined' && pokeHubCollections.i18n && pokeHubCollections.i18n.finished)
            ? pokeHubCollections.i18n.finished
            : 'Finished';

        viewWrap.querySelectorAll('.pokehub-collection-progress-n').forEach(function (n) {
            n.textContent = String(ownedAll);
        });
        viewWrap.querySelectorAll('.pokehub-collection-progress-total').forEach(function (t) {
            t.textContent = String(totalPool);
        });
        if (ariaProg) {
            var ariaBadge = ariaProg.replace(/%1\$d/g, String(ownedAll)).replace(/%2\$d/g, String(totalPool));
            viewWrap.querySelectorAll('.pokehub-collection-progress-badge').forEach(function (b) {
                b.setAttribute('aria-label', ariaBadge);
            });
        }

        viewWrap.querySelectorAll('.pokehub-collection-generation-block[data-generation]').forEach(function (details) {
            var genKey = details.getAttribute('data-generation') || '';
            var prog = genProg[genKey] || { owned: 0, for_trade: 0, total: 0 };
            details.classList.toggle('pokehub-collection-generation-block--empty', prog.total === 0);

            var summary = details.querySelector('summary.pokehub-collection-region-tile--summary');
            if (summary) {
                pokehubUpdateRegionTileProgressUI(summary, prog, {
                    isJump: false,
                    genLabel: genKey,
                    ariaJumpTpl: ariaJump,
                    ariaSummaryTpl: ariaSum,
                    finishedText: finishedText,
                });
            }

            var anchor = details.getAttribute('id') || '';
            if (!anchor) return;
            var jump = null;
            viewWrap.querySelectorAll('.pokehub-collection-generation-jump-link[data-generation-anchor]').forEach(function (a) {
                if (!jump && a.getAttribute('data-generation-anchor') === anchor) jump = a;
            });
            if (jump) {
                pokehubUpdateRegionTileProgressUI(jump, prog, {
                    isJump: true,
                    genLabel: genKey,
                    ariaJumpTpl: ariaJump,
                    ariaSummaryTpl: ariaSum,
                    finishedText: finishedText,
                });
            }
        });
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
                var on;
                if (st === 'for_trade') {
                    on = !!show.for_trade || (!!show.owned && pokehubStatusCountsAsOwned('for_trade', wrap));
                } else if (st === 'owned') {
                    on = !!show.owned;
                } else {
                    on = show.hasOwnProperty(st) ? !!show[st] : true;
                }
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
            if (typeof pokehubSyncCollectionCreateWizard === 'function') {
                pokehubSyncCollectionCreateWizard();
            }
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

    function pokehubCollectionCreatePresetNoShiny(preset) {
        return (
            preset === 'lucky' ||
            preset === 'shadow_purified' ||
            preset === 'regional_go' ||
            preset === 'gigantamax' ||
            preset === 'dynamax' ||
            preset === 'mega_primal' ||
            preset === 'legendary_mythical_ultra' ||
            preset === 'babies'
        );
    }

    /** Canonical category slugs (see POKE_HUB_COLLECTIONS_CAT_* in collections-helpers.php). */
    var POKEHUB_COLLECTIONS_CAT_ALL_POKEMON = 'all_pokemon';
    var POKEHUB_COLLECTIONS_CAT_POGO_GEO_EXCLUSIVE = 'pogo_geo_exclusive';
    var POKEHUB_COLLECTIONS_CAT_BABIES_ONLY = 'babies_only';

    function pokehubSyncCollectionCreateWizard() {
        var presetEl = document.getElementById('pokehub-collection-preset');
        var appEl = document.getElementById('pokehub-collection-appearance');
        var catHidden = document.getElementById('pokehub-collection-category');
        var refineBlock = document.getElementById('pokehub-collection-refine-block');
        var bgWrap = document.getElementById('pokehub-collection-bg-variant-wrap');
        var shWrap = document.getElementById('pokehub-collection-shadow-variant-wrap');
        var lkWrap = document.getElementById('pokehub-collection-lucky-variant-wrap');
        var lmuWrap = document.getElementById('pokehub-collection-lmu-variant-wrap');
        if (!presetEl || !catHidden) {
            return;
        }
        var preset = presetEl.value || '';
        var wantShiny = appEl && appEl.value === 'shiny';
        if (pokehubCollectionCreatePresetNoShiny(preset)) {
            wantShiny = false;
            if (appEl) {
                appEl.value = 'normal';
            }
        }
        if (appEl && appEl.options && appEl.options[1]) {
            appEl.options[1].disabled = pokehubCollectionCreatePresetNoShiny(preset);
        }

        var needsRefineBlock = (
            preset === 'backgrounds' ||
            preset === 'shadow_purified' ||
            preset === 'lucky' ||
            preset === 'legendary_mythical_ultra'
        );
        if (refineBlock) {
            refineBlock.classList.toggle('is-hidden', !needsRefineBlock);
            refineBlock.setAttribute('aria-hidden', needsRefineBlock ? 'false' : 'true');
        }
        if (bgWrap) {
            bgWrap.classList.toggle('is-hidden', preset !== 'backgrounds');
        }
        if (shWrap) {
            shWrap.classList.toggle('is-hidden', preset !== 'shadow_purified');
        }
        if (lkWrap) {
            lkWrap.classList.toggle('is-hidden', preset !== 'lucky');
        }
        if (lmuWrap) {
            lmuWrap.classList.toggle('is-hidden', preset !== 'legendary_mythical_ultra');
        }

        if (preset === '') {
            catHidden.value = '';
            catHidden.setAttribute('data-only-shiny-custom', '0');
            return;
        }

        var category = POKEHUB_COLLECTIONS_CAT_ALL_POKEMON;
        var onlyShinyCustom = false;
        if (preset === 'all') {
            category = wantShiny ? 'shiny' : POKEHUB_COLLECTIONS_CAT_ALL_POKEMON;
        } else if (preset === 'costumes') {
            category = wantShiny ? 'costume_shiny' : 'costume';
        } else if (preset === 'backgrounds') {
            var bgvEl = document.getElementById('pokehub-collection-bg-variant');
            var bgv = bgvEl ? bgvEl.value : 'all';
            if (wantShiny) {
                if (bgv === 'places') {
                    category = 'background_shiny_places';
                } else if (bgv === 'special') {
                    category = 'background_shiny_special';
                } else {
                    category = 'background_shiny';
                }
            } else if (bgv === 'places') {
                category = 'background_places';
            } else if (bgv === 'special') {
                category = 'background_special';
            } else {
                category = 'background';
            }
        } else if (preset === 'lucky') {
            var lkEl = document.getElementById('pokehub-collection-lucky-variant');
            category = lkEl && lkEl.value === 'lucky_dex' ? 'lucky_dex' : 'lucky';
        } else if (preset === 'shadow_purified') {
            var sv = document.getElementById('pokehub-collection-shadow-variant');
            category = sv && sv.value === 'purified' ? 'purified' : 'shadow';
        } else if (preset === 'regional_go') {
            category = POKEHUB_COLLECTIONS_CAT_POGO_GEO_EXCLUSIVE;
        } else if (preset === 'gigantamax') {
            category = 'gigantamax';
        } else if (preset === 'dynamax') {
            category = 'dynamax';
        } else if (preset === 'mega_primal') {
            category = 'mega';
        } else if (preset === 'legendary_mythical_ultra') {
            category = 'legendary_mythical_ultra';
        } else if (preset === 'babies') {
            category = POKEHUB_COLLECTIONS_CAT_BABIES_ONLY;
        } else {
            category = 'custom';
            onlyShinyCustom = wantShiny;
        }
        catHidden.value = category;
        catHidden.setAttribute('data-only-shiny-custom', onlyShinyCustom ? '1' : '0');
    }

    /**
     * Comme pour la barre POGO : la value du select peut encore être l’ancienne dans le même tour que « change »
     * (navigateurs / Select2). On recalcule au tick suivant pour afficher tout de suite le bloc « Refine » (ex. Lucky).
     */
    function pokehubScheduleCollectionCreateWizardSync() {
        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(function () {
                setTimeout(pokehubSyncCollectionCreateWizard, 0);
            });
        } else {
            setTimeout(pokehubSyncCollectionCreateWizard, 0);
        }
    }

    (function initCreateCollectionWizard() {
        var ids = [
            'pokehub-collection-preset',
            'pokehub-collection-appearance',
            'pokehub-collection-bg-variant',
            'pokehub-collection-shadow-variant',
            'pokehub-collection-lucky-variant',
            'pokehub-collection-lmu-variant',
        ];
        for (var i = 0; i < ids.length; i++) {
            var el = document.getElementById(ids[i]);
            if (!el) {
                continue;
            }
            el.addEventListener('change', pokehubScheduleCollectionCreateWizardSync, false);
            el.addEventListener('input', pokehubScheduleCollectionCreateWizardSync, false);
            if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                jQuery(el).on('change.pokehubCreateWizard select2:select.pokehubCreateWizard select2:clear.pokehubCreateWizard', pokehubScheduleCollectionCreateWizardSync);
            }
        }
        pokehubSyncCollectionCreateWizard();
    })();

    function getCreateFormData() {
        pokehubSyncCollectionCreateWizard();
        var nameEl = getEl('pokehub-collection-name');
        var categoryEl = getEl('pokehub-collection-category');
        var publicEl = getEl('pokehub-collection-public');
        var cardBgEl = getEl('pokehub-collection-card-background-image');
        var addSelectorsEl = getEl('pokehub-collection-add-selectors');
        var cat = categoryEl && categoryEl.value ? categoryEl.value : '';
        var onlyShinyCustom = !!(categoryEl && categoryEl.getAttribute('data-only-shiny-custom') === '1');
        var lmuSel = document.getElementById('pokehub-collection-lmu-variant');
        var lmuScope = 'all';
        if (cat === 'legendary_mythical_ultra' && lmuSel && lmuSel.value) {
            lmuScope = lmuSel.value;
        }
        var loggedCreate = !!(typeof pokeHubCollections !== 'undefined' && pokeHubCollections.isLoggedIn);
        var form = {
            name: nameEl && typeof nameEl.value === 'string' ? nameEl.value.trim() : '',
            category: cat,
            is_public: !!(loggedCreate && publicEl && publicEl.checked && !publicEl.disabled),
            options: {
                include_national_dex: getCheckedOrDefault('pokehub-collection-include-national', true),
                include_gender: getCheckedOrDefault('pokehub-collection-include-gender', true),
                include_both_sexes_collector: getCheckedOrDefault('pokehub-collection-both-sexes-collector', false),
                include_regional_forms: getCheckedOrDefault('pokehub-collection-include-regional-forms', true),
                include_costumes: getCheckedOrDefault('pokehub-collection-include-costumes', true),
                include_mega: getCheckedOrDefault('pokehub-collection-include-mega', true),
                include_gigantamax: getCheckedOrDefault('pokehub-collection-include-gigantamax', true),
                include_dynamax: getCheckedOrDefault('pokehub-collection-include-dynamax', true),
                include_backgrounds: getCheckedOrDefault('pokehub-collection-include-backgrounds', true),
                include_special_attacks: getCheckedOrDefault('pokehub-collection-include-special-attacks', false),
                include_baby_pokemon: getCheckedOrDefault('pokehub-collection-include-babies', true),
                pool_show_only: '',
                only_shiny: cat === 'custom' ? onlyShinyCustom : false,
                include_legendary_pokemon: getCheckedOrDefault('pokehub-collection-include-legendary', true),
                include_mythical_pokemon: getCheckedOrDefault('pokehub-collection-include-mythical', true),
                include_ultra_beast_pokemon: getCheckedOrDefault('pokehub-collection-include-ultra-beast', true),
                one_per_species: getCheckedOrDefault('pokehub-collection-one-per-species', false),
                group_by_generation: getCheckedOrDefault('pokehub-collection-group-by-generation', true),
                generations_collapsed: getCheckedOrDefault('pokehub-collection-generations-collapsed', false),
                display_mode: addSelectorsEl && addSelectorsEl.checked ? 'tiles_select' : 'tiles',
                show_gender_symbols:
                    getCheckedOrDefault('pokehub-collection-include-gender', true) ||
                    getCheckedOrDefault('pokehub-collection-both-sexes-collector', false),
                for_trade_counts_as_owned: getCheckedOrDefault('pokehub-collection-for-trade-counts-as-owned', true),
                only_final_evolution: getCheckedOrDefault('pokehub-collection-only-final-evolution', false),
                only_base_evolution_stage: getCheckedOrDefault('pokehub-collection-only-base-evolution', false),
                lmu_scope: lmuScope,
                card_background_image_url: cardBgEl && typeof cardBgEl.value === 'string' ? cardBgEl.value.trim() : '',
            },
        };
        return form;
    }

    if (btnCreateSubmit) {
        btnCreateSubmit.addEventListener('click', function () {
            var presetEl = document.getElementById('pokehub-collection-preset');
            if (presetEl && !presetEl.value) {
                window.alert(pokehubCollectionsI18n('selectListPresetRequired', 'Please select a list preset.'));
                return;
            }
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
                    name: form.name || (function () {
                        var cat = form.category;
                        var i18n = pokeHubCollections && pokeHubCollections.i18n ? pokeHubCollections.i18n : {};
                        if (cat === 'shiny') {
                            return i18n.collectionDefaultNameShiny || 'My Shinies';
                        }
                        if (cat === POKEHUB_COLLECTIONS_CAT_ALL_POKEMON) {
                            return i18n.collectionDefaultNameAllPokemon || 'My full collection';
                        }
                        if (cat === POKEHUB_COLLECTIONS_CAT_POGO_GEO_EXCLUSIVE) {
                            return i18n.collectionDefaultNameGoRegional || 'Pokémon GO regionals';
                        }
                        if (cat === POKEHUB_COLLECTIONS_CAT_BABIES_ONLY) {
                            return i18n.collectionDefaultNameBabies || 'Baby Pokémon';
                        }
                        if (cat === 'lucky') {
                            return i18n.collectionDefaultNameLucky || 'Lucky Pokémon';
                        }
                        if (cat === 'mega') {
                            return i18n.collectionDefaultNameMega || 'Mega & Primal';
                        }
                        if (cat === 'legendary_mythical_ultra') {
                            return i18n.collectionDefaultNameLegendaryMythicalUltra || 'Legendary, Mythical & Ultra Beasts';
                        }
                        if (cat === 'lucky_dex') {
                            return i18n.collectionDefaultNameLuckyDex || 'Lucky National Dex';
                        }
                        return i18n.collectionDefaultNameFallback || 'My collection';
                    }()),
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
                        window.alert(data.message || pokehubCollectionsI18n('genericError', 'Something went wrong.'));
                    }
                })
                .catch(function () {
                    window.alert(pokehubCollectionsI18n('networkError', 'Network error. Try again.'));
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
            if (pogoViewWrap) {
                pogoScheduleRefreshForWrap(pogoViewWrap);
            } else if (viewWrapTiles) {
                pogoScheduleRefreshForWrap(viewWrapTiles);
            }
            applyStatusFilters();
            pokehubRefreshCollectionProgressUIFromItems(viewWrapTiles || tileWrap, tileContainer, items);
            if (typeof tile.focus === 'function') {
                try {
                    tile.focus({ preventScroll: true });
                } catch (errFocus) {
                    tile.focus();
                }
            }

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
                pokehubRefreshCollectionProgressUIFromItems(viewWrapTiles || tileWrap, tileContainer, items);
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
            pokehubSyncForTradeOwnedRuleUI(localWrap, col.options);
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
                    if (pokehubStatusCountsAsOwned(localResolveItemStatus(p, items), localWrap)) {
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
                            var showGSym = !!(p.slug_binary_sex_display) ||
                                (p.synthetic_sex_collector && (o.include_both_sexes_collector || o.include_gender)) ||
                                (!p.synthetic_sex_collector && o.include_gender);
                            if (showGSym) {
                                gsym = '<span class="pokehub-collection-tile-gender" aria-hidden="true">' + p.gender_display + '</span>';
                            }
                        }
                        var nameLine = '<span class="pokehub-collection-tile-line pokehub-collection-tile-line--name">' +
                            '<span class="pokehub-collection-tile-name-stack">' +
                            '<span class="pokehub-collection-tile-name-row">' +
                            '<span class="pokehub-collection-tile-name">' + (p.name_fr || p.name_en || '') + '</span>' + gsym +
                            '</span></span></span>';
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
                            pogoScheduleRefreshForWrap(localWrap);
                            applyLocalFilters();
                            updateLocalCollectionStats();
                            if (typeof tile.focus === 'function') {
                                try {
                                    tile.focus({ preventScroll: true });
                                } catch (errLf) {
                                    tile.focus();
                                }
                            }
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
                    pogoScheduleRefreshForWrap(localWrap);
                    applyLocalFilters();
                    updateLocalCollectionStats();
                })
                .catch(function () {
                    var tilesEl = localWrap.querySelector('.pokehub-collection-tiles-local');
                    if (tilesEl) {
                        tilesEl.innerHTML = '<p class="pokehub-collections-not-found">' + pokehubCollectionsI18n('loadPoolFailed', 'Could not load the Pokémon list.') + '</p>';
                    }
                });
        } else {
            localWrap.querySelector('.pokehub-collection-local-title').textContent = pokehubCollectionsI18n('collectionNotFoundTitle', 'Collection not found');
            localWrap.querySelector('.pokehub-collection-tiles-local').innerHTML = '<p class="pokehub-collections-not-found">' + pokehubCollectionsI18n('localCollectionMissing', 'This local collection could not be found.') + '</p>';
        }
    }

    // Panneau latéral (drawer) paramètres (vue collection) — différenciant vs popup
    var viewWrap = document.querySelector('.pokehub-collection-view-wrap[data-collection-id]');
    var drawer = document.getElementById('pokehub-collections-drawer');
    var drawerBackdrop = document.getElementById('pokehub-collections-drawer-backdrop');
    var drawerClose = document.getElementById('pokehub-collections-drawer-close');
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

    /**
     * Share + Settings : deux en-têtes (flux + barre fixe) portent les mêmes classes.
     * Ne pas utiliser document.querySelector une seule fois (le premier nœud peut être [hidden]).
     */
    document.querySelectorAll('.pokehub-collection-view-wrap[data-collection-id]').forEach(function (collectionVw) {
        var sharePopover = collectionVw.querySelector('.pokehub-share-popover');
        if (sharePopover && !sharePopover.dataset.pokehubShareBound) {
            sharePopover.dataset.pokehubShareBound = '1';
            sharePopover.addEventListener('click', function (ev) {
                if (ev.target.closest('[data-share-popover-dismiss]')) {
                    pokehubCloseSharePopover(sharePopover);
                    return;
                }
                if (ev.target.closest('[data-share-copy-link]')) {
                    ev.preventDefault();
                    var shareUrl = collectionVw.getAttribute('data-share-url') || window.location.href;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(shareUrl).then(function () {
                            window.alert(pokehubCollectionsI18n('linkCopied', 'Link copied to clipboard.'));
                        }).catch(function () {
                            window.prompt(pokehubCollectionsI18n('copyLinkManually', 'Copy this link:'), shareUrl);
                        });
                    } else {
                        window.prompt(pokehubCollectionsI18n('copyLinkManually', 'Copy this link:'), shareUrl);
                    }
                }
            });
        }
        collectionVw.addEventListener('click', function (ev) {
            if (!ev.target || !ev.target.closest) return;
            var shareBtn = ev.target.closest('.pokehub-collections-btn-share');
            if (shareBtn && collectionVw.contains(shareBtn)) {
                ev.preventDefault();
                if (!sharePopover) return;
                var coverUrl = collectionVw.getAttribute('data-share-card-image-url') || '';
                pokehubFillShareCover(sharePopover.querySelector('[data-share-cover-mount]'), coverUrl);
                sharePopover.hidden = false;
                sharePopover.setAttribute('aria-hidden', 'false');
                return;
            }
            var editBtn = ev.target.closest('.pokehub-collections-btn-edit-settings');
            if (editBtn && collectionVw.contains(editBtn)) {
                ev.preventDefault();
                openDrawer();
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (!e.key || e.key !== 'Escape') return;
        document.querySelectorAll('.pokehub-share-popover:not([hidden])').forEach(function (p) {
            pokehubCloseSharePopover(p);
        });
    });
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
            var publicCheckbox = document.getElementById('pokehub-edit-collection-public');
            var accountLinkedEdit = viewWrap.getAttribute('data-collection-account-linked') === '1';
            var rawPublicUi = !!(publicCheckbox && publicCheckbox.checked);
            var isPublic = accountLinkedEdit && publicCheckbox && !publicCheckbox.disabled
                ? rawPublicUi
                : (viewWrap.getAttribute('data-edit-is-public') === '1');
            var addSelectorsEdit = document.getElementById('pokehub-edit-add-selectors');
            var displayMode = addSelectorsEdit && addSelectorsEdit.checked ? 'tiles_select' : 'tiles';
            var currentOptions = {};
            try {
                var optsJson = viewWrap.getAttribute('data-edit-options');
                if (optsJson) currentOptions = JSON.parse(optsJson);
            } catch (e) {}
            var category = viewWrap.getAttribute('data-collection-category') || '';
            var cardBgEl = document.getElementById('pokehub-edit-card-background-image');
            var cardBgUrl = cardBgEl ? cardBgEl.value.trim() : '';
            /* toolbar_decoration / generation_jump_* : pas dans le tiroir utilisateur ; conservées depuis currentOptions (admin / défauts). */
            var onePerEl = document.getElementById('pokehub-edit-one-per-species');
            var options = Object.assign({}, currentOptions, {
                display_mode: displayMode,
                group_by_generation: document.getElementById('pokehub-edit-group-by-generation')
                    ? document.getElementById('pokehub-edit-group-by-generation').checked
                    : true,
                generations_collapsed: document.getElementById('pokehub-edit-generations-collapsed')
                    ? document.getElementById('pokehub-edit-generations-collapsed').checked
                    : false,
                one_per_species: onePerEl ? onePerEl.checked : !!currentOptions.one_per_species,
                pool_show_only: '',
                card_background_image_url: cardBgUrl,
            });
            options.include_backgrounds = document.getElementById('pokehub-edit-include-backgrounds')
                ? document.getElementById('pokehub-edit-include-backgrounds').checked
                : !!currentOptions.include_backgrounds;
            options.include_gender = document.getElementById('pokehub-edit-include-gender')
                ? document.getElementById('pokehub-edit-include-gender').checked
                : !!currentOptions.include_gender;
            options.include_both_sexes_collector = document.getElementById('pokehub-edit-both-sexes-collector')
                ? document.getElementById('pokehub-edit-both-sexes-collector').checked
                : !!currentOptions.include_both_sexes_collector;
            options.show_gender_symbols = !!(options.include_gender || options.include_both_sexes_collector);
            options.include_baby_pokemon = document.getElementById('pokehub-edit-include-babies')
                ? document.getElementById('pokehub-edit-include-babies').checked
                : currentOptions.include_baby_pokemon !== false;
            options.include_legendary_pokemon = document.getElementById('pokehub-edit-include-legendary')
                ? document.getElementById('pokehub-edit-include-legendary').checked
                : true;
            options.include_mythical_pokemon = document.getElementById('pokehub-edit-include-mythical')
                ? document.getElementById('pokehub-edit-include-mythical').checked
                : true;
            options.include_ultra_beast_pokemon = document.getElementById('pokehub-edit-include-ultra-beast')
                ? document.getElementById('pokehub-edit-include-ultra-beast').checked
                : true;
            options.include_regional_forms = document.getElementById('pokehub-edit-include-regional-forms')
                ? document.getElementById('pokehub-edit-include-regional-forms').checked
                : currentOptions.include_regional_forms !== false;
            options.include_costumes = document.getElementById('pokehub-edit-include-costumes')
                ? document.getElementById('pokehub-edit-include-costumes').checked
                : !!currentOptions.include_costumes;
            options.include_mega = document.getElementById('pokehub-edit-include-mega')
                ? document.getElementById('pokehub-edit-include-mega').checked
                : !!currentOptions.include_mega;
            options.include_gigantamax = document.getElementById('pokehub-edit-include-gigantamax')
                ? document.getElementById('pokehub-edit-include-gigantamax').checked
                : !!currentOptions.include_gigantamax;
            options.include_dynamax = document.getElementById('pokehub-edit-include-dynamax')
                ? document.getElementById('pokehub-edit-include-dynamax').checked
                : !!currentOptions.include_dynamax;
            options.include_special_attacks = document.getElementById('pokehub-edit-include-special-attacks')
                ? document.getElementById('pokehub-edit-include-special-attacks').checked
                : !!currentOptions.include_special_attacks;
            options.only_final_evolution = document.getElementById('pokehub-edit-only-final-evolution')
                ? document.getElementById('pokehub-edit-only-final-evolution').checked
                : !!currentOptions.only_final_evolution;
            options.only_base_evolution_stage = document.getElementById('pokehub-edit-only-base-evolution')
                ? document.getElementById('pokehub-edit-only-base-evolution').checked
                : !!currentOptions.only_base_evolution_stage;
            if (category === 'legendary_mythical_ultra') {
                var lmuScopeEl = document.getElementById('pokehub-edit-lmu-scope');
                options.lmu_scope = lmuScopeEl && lmuScopeEl.value ? lmuScopeEl.value : 'all';
            } else {
                options.lmu_scope = 'all';
            }
            var onlyShinyEdit = document.getElementById('pokehub-edit-only-shiny');
            if (category === 'custom' && onlyShinyEdit) {
                options.only_shiny = !!onlyShinyEdit.checked;
            }
            options.include_national_dex = document.getElementById('pokehub-edit-include-national')
                ? document.getElementById('pokehub-edit-include-national').checked
                : true;
            options.for_trade_counts_as_owned = document.getElementById('pokehub-edit-for-trade-counts-as-owned')
                ? document.getElementById('pokehub-edit-for-trade-counts-as-owned').checked
                : pokehubForTradeCountsAsOwnedFromOpts(currentOptions);
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
                        window.alert(data.message || pokehubCollectionsI18n('genericError', 'Something went wrong.'));
                    }
                })
                .catch(function () {
                    window.alert(pokehubCollectionsI18n('networkError', 'Network error. Try again.'));
                });
        });
    }

    if (typeof pokeHubCollections !== 'undefined') {
        document.querySelectorAll('.pokehub-collection-view-wrap[data-collection-id]').forEach(function (vw) {
            var resetCollectionId = vw.getAttribute('data-collection-id');
            if (!resetCollectionId) return;
            vw.querySelectorAll('.pokehub-collections-reset-inline[data-reset-context="server"]').forEach(function (resetBlock) {
                setupCollectionResetInline(resetBlock, function () {
                    fetch(pokeHubCollections.restUrl + 'collections/' + resetCollectionId + '/reset', {
                        method: 'POST',
                        headers: buildRestHeaders({
                            'Content-Type': 'application/json',
                        }, vw),
                        body: JSON.stringify({}),
                        credentials: 'same-origin',
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.success) {
                                window.location.reload();
                            } else {
                                window.alert(data.message || pokehubCollectionsI18n('genericError', 'Something went wrong.'));
                            }
                        })
                        .catch(function () {
                            window.alert(pokehubCollectionsI18n('networkError', 'Network error. Try again.'));
                        });
                });
            });
        });
    }

    // Supprimer la collection (depuis la vue)
    var btnDeleteCollection = document.querySelector('.pokehub-collections-btn-delete-collection');
    if (btnDeleteCollection && viewWrap && typeof pokeHubCollections !== 'undefined') {
        var viewCollectionId = viewWrap.getAttribute('data-collection-id');
        var viewTitleEl = viewWrap.querySelector('.pokehub-collection-toolbar-header .pokehub-collection-view-title')
            || viewWrap.querySelector('.pokehub-collection-view-title');
        var viewCollectionName = viewWrap.getAttribute('data-edit-name') || (viewTitleEl ? viewTitleEl.textContent : '') || '';
        btnDeleteCollection.addEventListener('click', function () {
            var delMsg = pokehubCollectionsI18n('deleteCollectionConfirm', 'Delete this collection? This cannot be undone.').replace('%s', viewCollectionName);
            if (!viewCollectionId || !window.confirm(delMsg)) {
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
                        window.alert(data.message || pokehubCollectionsI18n('genericError', 'Something went wrong.'));
                    }
                })
                .catch(function () {
                    window.alert(pokehubCollectionsI18n('networkError', 'Network error. Try again.'));
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
            var delConfirm = pokehubCollectionsI18n('deleteCollectionConfirm', 'Delete this collection? This cannot be undone.').replace('%s', name);
            if (!id || !window.confirm(delConfirm)) {
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
                        window.alert(data.message || pokehubCollectionsI18n('genericError', 'Something went wrong.'));
                    }
                })
                .catch(function () {
                    window.alert(pokehubCollectionsI18n('networkError', 'Network error. Try again.'));
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
                        window.alert(pokehubCollectionsI18n('batchAddError', 'Could not add Pokémon. Try again.'));
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
            var msg;
            if (n === 1) {
                msg = pokehubCollectionsI18n(
                    'anonymousBannerOne',
                    'A collection was created from this connection (this device). Do you want to add it to your account?'
                );
            } else {
                msg = pokehubCollectionsI18n(
                    'anonymousBannerMany',
                    '%d collections were created from this connection. Do you want to add them to your account?'
                ).replace('%d', String(n));
            }
            bannerText.textContent = msg;
            bannerList.innerHTML = '';
            list.forEach(function (c) {
                var li = document.createElement('li');
                li.textContent = c.name || pokehubCollectionsI18n('unnamedCollection', 'Untitled collection');
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

    var POGO_GROUP_ORDER = [
        'base',
        'shiny', 'shinyMale', 'shinyFemale',
        'alola', 'alolaMale', 'alolaFemale',
        'galar', 'galarMale', 'galarFemale',
        'paldea', 'paldeaMale', 'paldeaFemale',
        'hisui', 'hisuiMale', 'hisuiFemale',
        'shadow', 'shadowMale', 'shadowFemale',
        'purified', 'purifiedMale', 'purifiedFemale',
        'mega', 'megaMale', 'megaFemale',
        'gigamax', 'gigamaxMale', 'gigamaxFemale',
        'dynamax', 'dynamaxMale', 'dynamaxFemale',
        'costume', 'costumeMale', 'costumeFemale',
        'male', 'female',
        'fond', 'fond_lieu', 'fond_special', 'fond_dynamax', 'fond_gigamax',
    ];
    /** Groupes « variante + sexe » (jeton listé une fois : la dédup retire le parent nu et les listes mâle/femelle hors variante). */
    var POGO_SEX_COMPOUND_GROUPS = [
        'shinyMale', 'shinyFemale',
        'alolaMale', 'alolaFemale', 'galarMale', 'galarFemale', 'paldeaMale', 'paldeaFemale', 'hisuiMale', 'hisuiFemale',
        'shadowMale', 'shadowFemale', 'purifiedMale', 'purifiedFemale',
        'megaMale', 'megaFemale', 'gigamaxMale', 'gigamaxFemale', 'dynamaxMale', 'dynamaxFemale',
        'costumeMale', 'costumeFemale',
    ];
    var POGO_PREFIX = {
        base: '',
        shiny: '',
        shadow: '',
        purified: '',
        alola: '',
        galar: '',
        paldea: '',
        hisui: '',
        mega: '',
        gigamax: '',
        dynamax: '',
        dynamaxMale: '',
        dynamaxFemale: '',
        male: 'male&',
        female: 'female&',
        costume: '', /* no filtre sûr partout : liste de noms seulement */
        fond: '',
        fond_lieu: '',
        fond_special: '',
        fond_dynamax: '',
        fond_gigamax: '',
    };
    /** Préfixes de recherche FR / EN (sélecteur « langue » du bloc GO). */
    function pogoSearchLangIsFr(tokenMode) {
        return tokenMode === 'name_fr';
    }
    function pogoSearchKeywordsMap() {
        return (typeof pokeHubCollections !== 'undefined' && pokeHubCollections.pogoSearchKeywords)
            ? pokeHubCollections.pogoSearchKeywords
            : {};
    }
    function pogoKwRaw(key, fallback) {
        var map = pogoSearchKeywordsMap();
        var v = map[key];
        if (v !== undefined && v !== null) {
            var t = String(v).trim();
            if (t !== '') {
                return t;
            }
        }
        return fallback !== undefined && fallback !== null ? String(fallback) : '';
    }
    function pogoKwAmp(key, fallback) {
        var raw = pogoKwRaw(key, fallback);
        return raw !== '' ? raw + '&' : '';
    }
    function pogoSexPrefix(tokenMode, female) {
        var isFr = pogoSearchLangIsFr(tokenMode);
        if (female) {
            return pogoKwAmp(isFr ? 'female_fr' : 'female_en', isFr ? 'femelle' : 'female');
        }
        return pogoKwAmp(isFr ? 'male_fr' : 'male_en', isFr ? 'mâle' : 'male');
    }
    function pogoSexCompoundParentGkey(gkey) {
        var m = /^(shiny|shadow|purified|dynamax|costume|mega|gigamax|alola|galar|paldea|hisui)(Male|Female)$/.exec(String(gkey || ''));
        return m ? m[1] : '';
    }
    function pogoGroupPrefix(gkey, tokenMode, sampleRow) {
        var k = tokenMode === 'name_en' ? 'pogo_group_prefix_en' : 'pogo_group_prefix_fr';
        var isFr = pogoSearchLangIsFr(tokenMode);
        if (gkey === 'shiny') {
            return pogoKwAmp(isFr ? 'shiny_fr' : 'shiny_en', isFr ? 'chromatique' : 'shiny');
        }
        if (gkey === 'shadow') {
            return pogoKwAmp(isFr ? 'shadow_fr' : 'shadow_en', isFr ? 'obscur' : 'shadow');
        }
        if (gkey === 'purified') {
            return pogoKwAmp(isFr ? 'purified_fr' : 'purified_en', isFr ? 'purifié' : 'purified');
        }
        if (gkey === 'fond_dynamax') {
            return pogoKwRaw(isFr ? 'fond_dynamax_fr' : 'fond_dynamax_en', isFr ? 'fond&dynamax' : 'background&dynamax') + '&';
        }
        if (gkey === 'fond_gigamax') {
            return pogoKwRaw(isFr ? 'fond_gigamax_fr' : 'fond_gigamax_en', isFr ? 'fond&gigamax' : 'background&gigantamax') + '&';
        }
        if (gkey === 'fond') {
            return pogoKwAmp(isFr ? 'fond_fr' : 'fond_en', isFr ? 'fond' : 'background');
        }
        if (gkey === 'fond_lieu') {
            return pogoKwAmp(isFr ? 'fond_lieu_fr' : 'fond_lieu_en', isFr ? 'fonddelieu' : 'locationbackground');
        }
        if (gkey === 'fond_special') {
            return pogoKwAmp(isFr ? 'fond_special_fr' : 'fond_special_en', isFr ? 'fonspécial' : 'specialbackground');
        }
        if (gkey === 'gigamax') {
            return pogoKwAmp(isFr ? 'gigamax_fr' : 'gigamax_en', isFr ? 'gigamax' : 'gigantamax');
        }
        var compoundParent = pogoSexCompoundParentGkey(gkey);
        if (compoundParent) {
            var sexBit = pogoSexPrefix(tokenMode, /Female$/.test(gkey));
            return pogoGroupPrefix(compoundParent, tokenMode, sampleRow) + sexBit;
        }
        if (gkey === 'dynamax') {
            return pogoKwAmp(isFr ? 'dynamax_fr' : 'dynamax_en', 'dynamax');
        }
        if (gkey === 'mega') {
            return pogoKwAmp(isFr ? 'mega_fr' : 'mega_en', isFr ? 'méga' : 'mega');
        }
        if (gkey === 'costume') {
            return pogoKwAmp(isFr ? 'costume_fr' : 'costume_en', isFr ? 'événement' : 'event');
        }
        /* Mâle / femelle : préfixe langue ; pas le préfixe variante (pogo_group_prefix_*) de la ligne d’exemple. */
        if (gkey === 'male' || gkey === 'female') {
            return pogoSexPrefix(tokenMode, gkey === 'female');
        }
        if (sampleRow && sampleRow[k]) {
            var raw = String(sampleRow[k]).trim();
            if (raw) {
                return raw + '&';
            }
        }
        return POGO_PREFIX[gkey] !== undefined ? POGO_PREFIX[gkey] : '';
    }
    /**
     * Préfixes de contexte collection (recherche cumulée) : chanceux, œufs uniquement, etc.
     * @return {string} chaîne pouvant contenir plusieurs jetons « …& »
     */
    function pogoWrapCollectionScopePrefix(wrap, tokenMode) {
        var parts = [];
        var isFr = pogoSearchLangIsFr(tokenMode);
        try {
            if (wrap && wrap.getAttribute && wrap.getAttribute('data-collection-category') === 'lucky') {
                parts.push(isFr ? pogoKwRaw('lucky_fr', 'chanceux') : pogoKwRaw('lucky_en', 'lucky'));
            }
            var jo = wrap && wrap.getAttribute ? wrap.getAttribute('data-edit-options') : '';
            if (jo) {
                var o = JSON.parse(jo);
                if (o && o.pool_show_only === 'baby') {
                    parts.push(isFr ? pogoKwRaw('eggsonly_fr', 'oeufseulement') : pogoKwRaw('eggsonly_en', 'eggsonly'));
                }
            }
        } catch (eScope) {}
        if (!parts.length) {
            return '';
        }
        var out = '';
        for (var si = 0; si < parts.length; si++) {
            out += parts[si] + '&';
        }
        return out;
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
            /* Lignes mâle/fem. « classiques » : retirer un suffixe Dynamax resté sur le jeton (ex. venusaurdynamax). */
            out = pogoSearchFormTokenNamePart(String(out || ''), 'dynamax') || out;
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
     * Groupe « parent » pour une ligne collector sexe synthétique : sans bruit slug mâle/femelle (évite Nidoran♀/♂ → female/male).
     * @return {string} dynamax|gigamax|mega|costume|alola|galar|paldea|hisui|base
     */
    function pogoSexVariantParentGroup(row) {
        if (!row) {
            return 'base';
        }
        if (row.synthetic_gigantamax || String(row.form_category || '').toLowerCase() === 'gigantamax') {
            return 'gigamax';
        }
        if (row.synthetic_dynamax || String(row.form_category || '').toLowerCase() === 'dynamax') {
            return 'dynamax';
        }
        var c = String(row.form_category || 'normal').toLowerCase();
        if (c === 'shiny') {
            return 'shiny';
        }
        if (c === 'shadow') {
            return 'shadow';
        }
        if (c === 'purified') {
            return 'purified';
        }
        if (c === 'mega' || c === 'primal') {
            return 'mega';
        }
        var prk = row.pogo_regional_key ? String(row.pogo_regional_key).toLowerCase() : '';
        if (prk === 'alola' || prk === 'galar' || prk === 'paldea' || prk === 'hisui') {
            return prk;
        }
        if (c === 'costume' || c === 'costume_shiny') {
            return 'costume';
        }
        var slug = String(row.form_slug || row.slug || '').toLowerCase();
        var reg = pogoGuessRegionalGroupFromSlugs(slug);
        if (reg) {
            return reg;
        }
        if (slug.indexOf('costume') !== -1) {
            return 'costume';
        }
        return 'base';
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
        if (c === 'shiny') {
            return 'shiny';
        }
        if (c === 'shadow') {
            return 'shadow';
        }
        if (c === 'purified') {
            return 'purified';
        }
        if (c === 'mega' || c === 'primal') {
            return 'mega';
        }
        if (c === 'dynamax') {
            return 'dynamax';
        }
        var prk = row.pogo_regional_key ? String(row.pogo_regional_key).toLowerCase() : '';
        if (prk === 'alola' || prk === 'galar' || prk === 'paldea' || prk === 'hisui') {
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
        /* Forme Dynamax : le slug peut rester celui de l’espèce (ex. venusaur) ; le jeton GO fusionne déjà « …dynamax ». */
        if (!row.synthetic_gigantamax) {
            var tokBlob = pogoStripAccents(String(
                (row.pogo_token_fr || '') + ' ' + (row.pogo_token_en || '') + ' ' + pogoNameToken(row, 'fr') + ' ' + pogoNameToken(row, 'en')
            ).toLowerCase().replace(/[^a-z0-9]/g, ''));
            if (tokBlob.indexOf('dynamax') !== -1) {
                return 'dynamax';
            }
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
        if (gkey === 'fond' || gkey === 'fond_lieu' || gkey === 'fond_special') {
            return pogoFondListTokenGkey(row);
        }
        var pc = pogoSexCompoundParentGkey(gkey);
        if (pc) {
            return pc;
        }
        return gkey;
    }
    /**
     * Groupes = données BDD (form_category) + pogo_regional_key (formes régionales par région).
     * Fonds GO : fond / lieu / spécial + D/G-Max + fond, mêmes jetons d’espèce que D/G-Max (pas bulbasaurdynamax&).
     */
    function pogoDetectGroup(row) {
        if (row.synthetic_sex_collector && row.synthetic_sex) {
            var sexLow = String(row.synthetic_sex).toLowerCase();
            var isFemale = sexLow === 'female';
            var suf = isFemale ? 'Female' : 'Male';
            var parent = pogoSexVariantParentGroup(row);
            if (parent === 'dynamax') {
                return 'dynamax' + suf;
            }
            if (parent === 'costume') {
                return 'costume' + suf;
            }
            if (parent === 'mega') {
                return 'mega' + suf;
            }
            if (parent === 'gigamax') {
                return 'gigamax' + suf;
            }
            if (parent === 'shiny') {
                return 'shiny' + suf;
            }
            if (parent === 'shadow') {
                return 'shadow' + suf;
            }
            if (parent === 'purified') {
                return 'purified' + suf;
            }
            if (parent === 'alola' || parent === 'galar' || parent === 'paldea' || parent === 'hisui') {
                return parent + suf;
            }
            return isFemale ? 'female' : 'male';
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
            if (row.synthetic_go_background) {
                var bt = String(row.synthetic_go_background_background_type || '').toLowerCase();
                if (bt === 'special') {
                    return 'fond_special';
                }
                if (bt === 'location') {
                    return 'fond_lieu';
                }
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
     * Jeton déjà listé en « variante + sexe » (ex. costume&female&, mega&male&, dynamax&male&) : ne pas le répéter
     * dans la phrase du parent nu ni en classique / mâle / femelle hors cette variante (même jeton = une seule ligne utile).
     *
     * @param {Object<string, string[]>} byGroup
     */
    function pogoDedupeTokensForSexCompoundVariants(byGroup) {
        var pri = {};
        for (var ci = 0; ci < POGO_SEX_COMPOUND_GROUPS.length; ci++) {
            var ck = POGO_SEX_COMPOUND_GROUPS[ci];
            (byGroup[ck] || []).forEach(function (t) {
                if (t) {
                    pri[t] = true;
                }
            });
        }
        if (!Object.keys(pri).length) {
            return;
        }
        var stripFrom = ['base', 'shiny', 'shadow', 'purified', 'male', 'female', 'dynamax', 'mega', 'gigamax', 'costume', 'alola', 'galar', 'paldea', 'hisui', 'fond', 'fond_lieu', 'fond_special'];
        stripFrom.forEach(function (g) {
            var arr = byGroup[g];
            if (!arr || !arr.length) {
                return;
            }
            byGroup[g] = arr.filter(function (t) {
                return t && !pri[t];
            });
        });
    }

    /**
     * Même jeton en variante+mâle et variante+femelle (ex. dynamax&male&venusaur et dynamax&female&venusaur) :
     * une seule recherche sans sexe suffit → on retire des deux listes composées et on place le jeton dans le parent (dynamax&, …).
     *
     * @param {Object<string, string[]>} byGroup
     * @param {string} listBy 'number' | 'name'
     */
    function pogoMergeSexCompoundDupesIntoParent(byGroup, listBy) {
        var parents = ['shiny', 'shadow', 'purified', 'dynamax', 'costume', 'mega', 'gigamax', 'alola', 'galar', 'paldea', 'hisui'];
        for (var pi = 0; pi < parents.length; pi++) {
            var p = parents[pi];
            var gm = p + 'Male';
            var gf = p + 'Female';
            var maleArr = byGroup[gm] || [];
            var femaleArr = byGroup[gf] || [];
            if (!maleArr.length || !femaleArr.length) {
                continue;
            }
            var fset = {};
            for (var fi = 0; fi < femaleArr.length; fi++) {
                fset[femaleArr[fi]] = true;
            }
            var inBoth = [];
            var inBothSet = {};
            for (var mi = 0; mi < maleArr.length; mi++) {
                var t = maleArr[mi];
                if (t && fset[t] && !inBothSet[t]) {
                    inBothSet[t] = true;
                    inBoth.push(t);
                }
            }
            if (!inBoth.length) {
                continue;
            }
            var rm = inBothSet;
            byGroup[gm] = maleArr.filter(function (x) {
                return !rm[x];
            });
            byGroup[gf] = femaleArr.filter(function (x) {
                return !rm[x];
            });
            var parentArr = byGroup[p] ? byGroup[p].slice() : [];
            var bseen = {};
            for (var bi = 0; bi < parentArr.length; bi++) {
                bseen[parentArr[bi]] = true;
            }
            for (var ii = 0; ii < inBoth.length; ii++) {
                var tok = inBoth[ii];
                if (tok && !bseen[tok]) {
                    parentArr.push(tok);
                    bseen[tok] = true;
                }
            }
            pogoSortTokenStrings(parentArr, listBy);
            byGroup[p] = parentArr;
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
    function pogoSexSpecificGroupParent(gkey) {
        if (gkey === 'male' || gkey === 'female') {
            return 'base';
        }
        return pogoSexCompoundParentGkey(gkey);
    }
    /**
     * Si un jeton a une ligne sexe spécifique, on le retire du groupe parent non-sexe.
     * Ex: base + male => on garde male uniquement ; alola + alolaFemale => on garde alolaFemale uniquement.
     */
    function pogoRemoveSexSpecificTokensFromParent(byGroup) {
        var parentToSexTokens = {};
        for (var i = 0; i < POGO_GROUP_ORDER.length; i++) {
            var g = POGO_GROUP_ORDER[i];
            var parent = pogoSexSpecificGroupParent(g);
            if (!parent) {
                continue;
            }
            var arr = byGroup[g] || [];
            if (!arr.length) {
                continue;
            }
            if (!parentToSexTokens[parent]) {
                parentToSexTokens[parent] = {};
            }
            for (var j = 0; j < arr.length; j++) {
                var t = arr[j];
                if (t) {
                    parentToSexTokens[parent][t] = true;
                }
            }
        }
        var parents = Object.keys(parentToSexTokens);
        for (var pi = 0; pi < parents.length; pi++) {
            var p = parents[pi];
            var parentArr = byGroup[p] || [];
            if (!parentArr.length) {
                continue;
            }
            var rm = parentToSexTokens[p];
            byGroup[p] = parentArr.filter(function (t) {
                return t && !rm[t];
            });
        }
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
        var scopePrefix = pogoWrapCollectionScopePrefix(wrap, tokenMode);
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
        pogoDedupeTokensForSexCompoundVariants(byGroup);
        pogoMergeSexCompoundDupesIntoParent(byGroup, listBy);
        pogoMergeMaleFemaleDupesIntoBase(byGroup, listBy);
        pogoRemoveSexSpecificTokensFromParent(byGroup);
        outEl.textContent = '';
        var any = false;
        var gidx;
        for (gidx = 0; gidx < POGO_GROUP_ORDER.length; gidx++) {
            var gkey = POGO_GROUP_ORDER[gidx];
            var names = byGroup[gkey];
            if (!names || !names.length) continue;
            any = true;
            var label = pogoT(pogoGroupLabelKey(gkey, listBy));
            var prefix = scopePrefix + pogoGroupPrefix(gkey, tokenMode, prefixRowByGroup[gkey]);
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

    /**
     * Hauteur depuis le haut du viewport jusqu’au bas de la zone qui masque le contenu.
     * Si la barre collection fixe est visible : mesure live (bas du bloc = sous header site + barre).
     * Si elle est encore [hidden] (clic jump dans la zone d’outils du flux) : header + dernière hauteur
     * de barre connue (tick) ou hauteur de .pokehub-collection-toolbar-tools comme repli —
     * le scroll lissé fait ensuite apparaître la barre : une correction de scroll compense (voir jump).
     */
    function pokeHubCollectionJumpOcclusionBottomPx(wrap) {
        /** La barre est déplacée sous document.body (initCollectionFixedToolbar) : ne plus la chercher avec wrap.querySelector. */
        var fixedTb = document.querySelector('[data-collection-fixed-toolbar]');
        if (fixedTb && !fixedTb.hasAttribute('hidden')) {
            return fixedTb.getBoundingClientRect().bottom;
        }
        var rootStyle = window.getComputedStyle(document.documentElement);
        var elOffset = parseFloat(rootStyle.getPropertyValue('--pokehub-elementor-header-offset')) || 0;
        var adminOffset = parseFloat(rootStyle.getPropertyValue('--pokehub-adminbar-offset')) || 0;
        var base = elOffset + adminOffset;
        var lastBar = parseInt(wrap.getAttribute('data-pokehub-last-fixed-toolbar-h') || '', 10);
        var reserve = 0;
        if (!isNaN(lastBar) && lastBar > 0) {
            reserve = lastBar;
        } else {
            var st = wrap.querySelector('.pokehub-collection-toolbar-tools');
            if (st) {
                reserve = Math.ceil(st.getBoundingClientRect().height);
            }
        }
        var fallbackBottom = base + reserve;
        /**
         * Le header mono-stack peut être pinné (position: fixed) : sa hauteur réelle visible
         * est la meilleure référence pour éviter de masquer le <summary> ciblé.
         */
        var stack = wrap.querySelector('.pokehub-collection-toolbar-stack');
        if (stack) {
            var stackRect = stack.getBoundingClientRect();
            if (stackRect.height > 0) {
                fallbackBottom = Math.max(fallbackBottom, Math.ceil(stackRect.bottom));
            }
        }
        return fallbackBottom;
    }

    /** Après scroll lissé : la barre fixe peut apparaître et recouvrir la cible sans avoir été prise en compte au 1er calcul. */
    function pokeHubCollectionJumpScrollAdjust(wrap, scrollInto, gapPx) {
        var occBottom = pokeHubCollectionJumpOcclusionBottomPx(wrap);
        var want = occBottom + gapPx;
        var have = scrollInto.getBoundingClientRect().top;
        if (Math.abs(have - want) < 6) {
            return;
        }
        var y = window.pageYOffset || document.documentElement.scrollTop || 0;
        var nextTop = scrollInto.getBoundingClientRect().top + y - occBottom - gapPx;
        var maxY = Math.max(0, (document.documentElement.scrollHeight || document.body.scrollHeight || 0) - window.innerHeight);
        if (nextTop > maxY) {
            nextTop = maxY;
        }
        if (nextTop < 0) {
            nextTop = 0;
        }
        window.scrollTo({ top: nextTop, behavior: 'auto' });
    }

    function initCollectionGenerationJump() {
        var wraps = document.querySelectorAll('.pokehub-collection-view-wrap');
        wraps.forEach(function (wrap) {
            var links = wrap.querySelectorAll('.pokehub-collection-generation-jump-link[data-generation-anchor]');
            if (!links || links.length === 0) {
                return;
            }
            var sections = [];
            links.forEach(function (link) {
                var id = link.getAttribute('data-generation-anchor');
                if (!id) return;
                var node = wrap.querySelector('#' + id);
                if (node) {
                    sections.push({ id: id, node: node, link: link });
                }
            });
            links.forEach(function (link) {
                link.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    var anchor = link.getAttribute('data-generation-anchor') || '';
                    if (!anchor) return;
                    var target = wrap.querySelector('#' + anchor);
                    if (!target) return;
                    /**
                     * Cible visuelle : le <summary> (tuile région), pas le <details> (évite décalage marges / bordures).
                     * Occlusion : bas de la barre fixe collection (mesure live = nouveau header + tuiles + panneau ouvert le cas échéchant),
                     * pas elOffset + hauteur cumulée (évite écart avec le rendu réel).
                     */
                    var scrollInto = (target.querySelector && target.querySelector('summary'))
                        ? target.querySelector('summary')
                        : target;
                    var occBottom = pokeHubCollectionJumpOcclusionBottomPx(wrap);
                    var gapPx = 8;
                    var y = window.pageYOffset || document.documentElement.scrollTop || 0;
                    var top = scrollInto.getBoundingClientRect().top + y - occBottom - gapPx;
                    var maxY = Math.max(0, (document.documentElement.scrollHeight || document.body.scrollHeight || 0) - window.innerHeight);
                    if (top > maxY) {
                        top = maxY;
                    }
                    links.forEach(function (l) { l.classList.remove('is-active'); });
                    link.classList.add('is-active');
                    window.scrollTo({ top: top, behavior: 'smooth' });
                    // Ferme automatiquement le menu/panneau après clic sur une tuile "jump to".
                    var toolbarDrawer = wrap.querySelector('[data-toolbar-menu-drawer]')
                        || document.querySelector('[data-toolbar-menu-drawer]');
                    if (toolbarDrawer) {
                        var tbWasOpen = toolbarDrawer.classList.contains('is-open');
                        toolbarDrawer.setAttribute('hidden', 'hidden');
                        toolbarDrawer.setAttribute('aria-hidden', 'true');
                        toolbarDrawer.classList.remove('is-open');
                        if (tbWasOpen) {
                            document.body.style.overflow = '';
                        }
                        var toolbarDrawerBody = toolbarDrawer.querySelector('[data-toolbar-menu-body]');
                        if (toolbarDrawerBody) {
                            toolbarDrawerBody.removeAttribute('data-active-key');
                        }
                    }
                    var flowExpand = wrap.querySelector('[data-flow-expand]');
                    if (flowExpand) {
                        flowExpand.setAttribute('hidden', 'hidden');
                    }
                    var fixedExpand = wrap.querySelector('[data-fixed-expand]');
                    if (fixedExpand) {
                        fixedExpand.setAttribute('hidden', 'hidden');
                    }
                    wrap.querySelectorAll('.pokehub-collection-fixed-tile-btn.is-active').forEach(function (btn) {
                        btn.classList.remove('is-active');
                        btn.setAttribute('aria-pressed', 'false');
                    });
                    [320, 700].forEach(function (ms) {
                        window.setTimeout(function () {
                            pokeHubCollectionJumpScrollAdjust(wrap, scrollInto, gapPx);
                        }, ms);
                    });
                });
            });

            function updateActiveFromScroll() {
                if (!sections.length) return;
                var occBottom = pokeHubCollectionJumpOcclusionBottomPx(wrap);
                var y = window.pageYOffset || document.documentElement.scrollTop || 0;
                var pivot = y + occBottom + 8;
                var current = sections[0];
                for (var i = 0; i < sections.length; i++) {
                    var r = sections[i].node.getBoundingClientRect();
                    var topAbs = r.top + y;
                    var bottomAbs = topAbs + r.height;
                    if (pivot >= topAbs && pivot < bottomAbs) {
                        current = sections[i];
                        break;
                    }
                    if (topAbs <= pivot) {
                        current = sections[i];
                    } else {
                        break;
                    }
                }
                links.forEach(function (l) { l.classList.remove('is-active'); });
                if (current && current.link) {
                    current.link.classList.add('is-active');
                }
            }
            var raf = 0;
            var resizeJumpTimer = null;
            function onScroll() {
                if (raf) return;
                raf = window.requestAnimationFrame(function () {
                    raf = 0;
                    updateActiveFromScroll();
                });
            }
            function onResizeDebounced() {
                if (resizeJumpTimer) {
                    clearTimeout(resizeJumpTimer);
                }
                resizeJumpTimer = setTimeout(function () {
                    resizeJumpTimer = null;
                    onScroll();
                }, 100);
            }
            window.addEventListener('scroll', onScroll, { passive: true });
            window.addEventListener('resize', onResizeDebounced);
            updateActiveFromScroll();
        });
    }

    /** Tablette / mobile : défilement horizontal des raccourcis génération + repères < > */
    function initCollectionGenerationJumpScroll() {
        function bindScrollRoot(scrollRoot) {
            if (!scrollRoot || scrollRoot.getAttribute('data-pokehub-gen-jump-scroll-bound') === '1') {
                return;
            }
            var track = scrollRoot.querySelector('[data-pokehub-gen-jump-scroll-track]');
            var prev = scrollRoot.querySelector('[data-pokehub-gen-jump-scroll-prev]');
            var next = scrollRoot.querySelector('[data-pokehub-gen-jump-scroll-next]');
            if (!track || !prev || !next) {
                return;
            }
            scrollRoot.setAttribute('data-pokehub-gen-jump-scroll-bound', '1');
            var layoutTimer = null;
            function update() {
                var max = track.scrollWidth - track.clientWidth;
                if (max <= 6) {
                    scrollRoot.classList.add('pokehub-collection-generation-jump-scroll--no-overflow');
                    scrollRoot.classList.add('is-at-start');
                    scrollRoot.classList.add('is-at-end');
                } else {
                    scrollRoot.classList.remove('pokehub-collection-generation-jump-scroll--no-overflow');
                    scrollRoot.classList.toggle('is-at-start', track.scrollLeft <= 4);
                    scrollRoot.classList.toggle('is-at-end', track.scrollLeft >= max - 4);
                }
            }
            function updateLayoutDebounced() {
                if (layoutTimer) {
                    clearTimeout(layoutTimer);
                }
                layoutTimer = setTimeout(function () {
                    layoutTimer = null;
                    update();
                }, 80);
            }
            function scrollByDir(dir) {
                if (scrollRoot.classList.contains('pokehub-collection-generation-jump-scroll--no-overflow')) {
                    return;
                }
                var max = track.scrollWidth - track.clientWidth;
                if (max <= 6) return;
                var delta = Math.max(140, Math.floor(track.clientWidth * 0.72));
                var target = track.scrollLeft + dir * delta;
                if (target < 0) target = 0;
                if (target > max) target = max;
                track.scrollTo({ left: target, behavior: 'smooth' });
            }
            prev.addEventListener('click', function () {
                scrollByDir(-1);
            });
            next.addEventListener('click', function () {
                scrollByDir(1);
            });
            track.addEventListener('scroll', update, { passive: true });
            if (typeof ResizeObserver !== 'undefined') {
                var ro = new ResizeObserver(updateLayoutDebounced);
                ro.observe(track);
            } else {
                window.addEventListener('resize', updateLayoutDebounced);
            }
            update();
        }
        document.querySelectorAll('.pokehub-collection-view-wrap').forEach(function (wrap) {
            wrap.querySelectorAll('[data-pokehub-gen-jump-scroll]').forEach(bindScrollRoot);
        });
    }
    /**
     * Barre d’outils GO (embed) : ouvrir/fermer le <details> depuis tout le panneau (titre H3, illustration, marges),
     * pas seulement la ligne summary.
     */
    function initCollectionToolbarPogoWholePanelClick() {
        if (initCollectionToolbarPogoWholePanelClick._done) {
            return;
        }
        initCollectionToolbarPogoWholePanelClick._done = true;
        document.addEventListener(
            'click',
            function (ev) {
                var panel = ev.target && ev.target.closest && ev.target.closest('.pokehub-collection-toolbar-panel--pogo');
                if (!panel) {
                    return;
                }
                var details = panel.querySelector('details.pokehub-collection-pogo-search--toolbar-embed');
                if (!details) {
                    return;
                }
                var t = ev.target;
                if (t.closest && (t.closest('select') || t.closest('button') || t.closest('a') || t.closest('input') || t.closest('textarea'))) {
                    return;
                }
                if (t.closest && t.closest('.select2-container')) {
                    return;
                }
                if (details.open && t.closest && t.closest('.pokehub-collection-pogo-search-body')) {
                    return;
                }
                if (t.closest && t.closest('summary')) {
                    return;
                }
                var summary = details.querySelector('summary');
                if (!summary) {
                    return;
                }
                summary.click();
            },
            false
        );
    }

    /** Barre hors flux sous le header du site ; tuiles lorsque les blocs filtres / GO / gén. / selects défilent sous cette barre. */
    function initCollectionFixedToolbar() {
        var defaultOrder = ['filters', 'pogo', 'generations', 'selectors'];

        function fixedToolbarReflowSelects(collectionWrap) {
            if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                collectionWrap.querySelectorAll('.pokehub-collection-missing-select, .pokehub-collection-fortrade-select').forEach(function (el) {
                    if (el.id && jQuery(el).hasClass && jQuery(el).hasClass('select2-hidden-accessible')) {
                        try {
                            jQuery(el).trigger('select2:close');
                        } catch (eIgnore) {}
                    }
                });
            }
            window.dispatchEvent(new Event('resize'));
        }

        var wraps = document.querySelectorAll('.pokehub-collection-view-wrap');
        wraps.forEach(function (wrap) {
            var fixedHost = wrap.querySelector('[data-collection-fixed-toolbar]');
            var toolbarStack = wrap.querySelector('.pokehub-collection-toolbar-stack');
            var fixedTilesHost = wrap.querySelector('[data-fixed-tiles-host]');
            var fixedExpandWrap = wrap.querySelector('[data-fixed-expand]');
            var fixedExpandInner = wrap.querySelector('[data-fixed-expand-inner]');
            var flowTilesHost = wrap.querySelector('[data-flow-tiles-host]');
            var flowExpandWrap = wrap.querySelector('[data-flow-expand]');
            var flowExpandInner = wrap.querySelector('[data-flow-expand-inner]');
            var toolbarMenuDrawer = wrap.querySelector('[data-toolbar-menu-drawer]');
            var toolbarMenuBackdrop = wrap.querySelector('[data-toolbar-menu-backdrop]');
            var toolbarMenuClose = wrap.querySelector('[data-toolbar-menu-close]');
            var toolbarMenuBody = wrap.querySelector('[data-toolbar-menu-body]');
            var toolbarMenuTitle = wrap.querySelector('[data-toolbar-menu-title]');
            var flowHeader = wrap.querySelector('.pokehub-collection-toolbar-header');
            if (!fixedHost || !toolbarStack || !fixedTilesHost || !fixedExpandWrap || !fixedExpandInner || !flowTilesHost) {
                return;
            }

            /** Hors du wrap : un ancêtre (p. ex. colonne Elementor avec transform) recréerait un bloc de positionnement pour les descendants en position:fixed — header site + barre semblent « décollés » du viewport. */
            try {
                if (fixedHost.parentNode && fixedHost.parentNode !== document.body) {
                    document.body.appendChild(fixedHost);
                }
            } catch (eTbReparent) {}

            var orderedKeys = [];
            defaultOrder.forEach(function (k) {
                if (wrap.querySelector('[data-collection-toolbar-slot="' + k + '"]')) {
                    orderedKeys.push(k);
                }
            });

            /** Position Y document (scroll) — offsetParent est faux dès qu’il y a transform / structure WP. */
            function getElementDocumentTop(node) {
                if (!node || !node.getBoundingClientRect) return 0;
                var y = window.pageYOffset || document.documentElement.scrollTop || 0;
                return node.getBoundingClientRect().top + y;
            }

            var anchorsSlots = {};
            var anchorsDirty = true;

            function computeAnchors() {
                orderedKeys.forEach(function (k) {
                    var slot = wrap.querySelector('[data-collection-toolbar-slot="' + k + '"]');
                    anchorsSlots[k] = slot ? getElementDocumentTop(slot) : Number.POSITIVE_INFINITY;
                });
                anchorsDirty = false;
            }

            function ensureAnchors() {
                if (anchorsDirty) {
                    computeAnchors();
                }
            }

            function syncFixedToolbarToWrapBox() {
                if (!fixedHost) return;
                if (fixedHost.hasAttribute('hidden')) {
                    fixedHost.style.left = '';
                    fixedHost.style.width = '';
                    fixedHost.style.right = '';
                    return;
                }
                var r = wrap.getBoundingClientRect();
                fixedHost.style.left = Math.max(0, r.left) + 'px';
                fixedHost.style.width = r.width + 'px';
                fixedHost.style.right = 'auto';
            }

            function syncPinnedToolbarStackToWrapBox() {
                if (!toolbarStack || !toolbarStack.classList.contains('pokehub-toolbar-stack--pinned')) {
                    return;
                }
                var r = wrap.getBoundingClientRect();
                toolbarStack.style.left = Math.max(0, r.left) + 'px';
                toolbarStack.style.width = r.width + 'px';
                toolbarStack.style.right = 'auto';
            }

            function stickyViewportOffset() {
                var y = window.pageYOffset || document.documentElement.scrollTop || 0;
                var rootStyle = window.getComputedStyle(document.documentElement);
                var elOffset = parseFloat(rootStyle.getPropertyValue('--pokehub-elementor-header-offset')) || 0;
                var adminOffset = parseFloat(rootStyle.getPropertyValue('--pokehub-adminbar-offset')) || 0;
                return {
                    maskLine: y + elOffset + adminOffset,
                    barOffset: elOffset + adminOffset
                };
            }

            function currentMaskLine() {
                return stickyViewportOffset().maskLine;
            }

            var fixedToolbarOn = false;
            var visibleTiles = {};
            var openExpandedKey = '';
            var openExpandedMode = '';
            var lastTilesFingerprint = '';
            /** Ancre scroll (Y doc) pour décoller la barre sans osciller avec position:fixed — ne pas invalider au resize. */
            var toolbarStackPinScrollAnchorY = null;
            var eConInner = (function () {
                if (!wrap || !wrap.closest) {
                    return null;
                }
                return wrap.closest('.e-con-inner');
            })();
            orderedKeys.forEach(function (k) {
                visibleTiles[k] = false;
            });

            function visibleTilesFingerprint() {
                return orderedKeys.filter(function (k) {
                    return visibleTiles[k];
                }).join('|');
            }

            function activeTilesHost() {
                return fixedToolbarOn ? fixedTilesHost : flowTilesHost;
            }
            function useToolbarMenuDrawer() {
                return !!(toolbarMenuDrawer && toolbarMenuBody);
            }
            function activeExpandWrap() {
                if (useToolbarMenuDrawer()) {
                    return toolbarMenuDrawer;
                }
                return fixedToolbarOn ? fixedExpandWrap : flowExpandWrap;
            }
            function activeExpandInner() {
                if (useToolbarMenuDrawer()) {
                    return toolbarMenuBody;
                }
                return fixedToolbarOn ? fixedExpandInner : flowExpandInner;
            }

            function isEConInnerFullyVisible(barOffPx) {
                if (!eConInner || !eConInner.getBoundingClientRect) {
                    return false;
                }
                var r = eConInner.getBoundingClientRect();
                return r.top >= (barOffPx - 1) && r.bottom <= (window.innerHeight + 1);
            }

            /**
             * Panneau filtres / GO / gén. / selectors : en flux il est sous .pokehub-collection-view-wrap ;
             * une fois la barre fixe reparentée sous document.body, le panneau ouvert vit dans [data-fixed-expand-inner],
             * donc wrap.querySelector seul ne le trouve plus — d’où accumulations dans l’expand et tuiles « fantômes ».
             */
            function sectionBodyFor(type) {
                if (!type) {
                    return null;
                }
                var sel = '[data-collection-fixed-tile="' + type + '"]';
                var inWrap = wrap.querySelector(sel);
                if (inWrap) {
                    return inWrap;
                }
                var inFixed = fixedExpandInner && fixedExpandInner.querySelector ? fixedExpandInner.querySelector(sel) : null;
                if (inFixed) {
                    return inFixed;
                }
                return flowExpandInner && flowExpandInner.querySelector ? flowExpandInner.querySelector(sel) : null;
            }

            function slotFor(type) {
                return wrap.querySelector('[data-collection-toolbar-slot="' + type + '"]');
            }

            function closeExpand(reason) {
                if (!openExpandedKey) return;
                var k = openExpandedKey;
                var body = sectionBodyFor(k);
                var slot = slotFor(k);
                var usingDrawer = useToolbarMenuDrawer();
                openExpandedKey = '';
                var aWrap = activeExpandWrap();
                var aInner = activeExpandInner();
                if (fixedExpandWrap) {
                    fixedExpandWrap.setAttribute('hidden', 'hidden');
                }
                if (flowExpandWrap) {
                    flowExpandWrap.setAttribute('hidden', 'hidden');
                }
                if (toolbarMenuDrawer) {
                    var menuWasOpen = toolbarMenuDrawer.classList.contains('is-open');
                    toolbarMenuDrawer.setAttribute('hidden', 'hidden');
                    toolbarMenuDrawer.setAttribute('aria-hidden', 'true');
                    toolbarMenuDrawer.classList.remove('is-open');
                    if (menuWasOpen) {
                        document.body.style.overflow = '';
                    }
                }
                if (aInner) {
                    aInner.removeAttribute('data-active-key');
                }
                if (slot && body && (body.parentNode === fixedExpandInner || body.parentNode === flowExpandInner)) {
                    slot.appendChild(body);
                    slot.style.minHeight = '';
                } else if (slot && body && usingDrawer && body.parentNode === toolbarMenuBody) {
                    slot.appendChild(body);
                    slot.style.minHeight = '';
                }
                [fixedTilesHost, flowTilesHost].forEach(function (host) {
                    if (!host) return;
                    var btn = host.querySelector('[data-fixed-tile-key="' + k + '"]');
                    if (btn) {
                        btn.classList.remove('is-active');
                        btn.setAttribute('aria-pressed', 'false');
                    }
                });
                if (reason !== 'preserve-select2') {
                    fixedToolbarReflowSelects(wrap);
                }
                if (fixedToolbarOn && fixedHost) {
                    var tbClose = Math.ceil(fixedHost.getBoundingClientRect().height);
                    wrap.style.setProperty('--pokehub-collection-fixed-toolbar-height', tbClose + 'px');
                    if (tbClose > 0) {
                        wrap.setAttribute('data-pokehub-last-fixed-toolbar-h', String(tbClose));
                    }
                }
                anchorsDirty = true;
            }

            function openExpand(type) {
                var body = sectionBodyFor(type);
                var slot = slotFor(type);
                if (!body || !slot) return;
                var usingDrawer = useToolbarMenuDrawer();
                var aWrap = activeExpandWrap();
                var aInner = activeExpandInner();
                if (!aWrap || !aInner) {
                    return;
                }
                /* Rattrapage si l’état DOM a dérivé (anciennes versions ou race) : un seul panneau dans l’expand. */
                if (aInner && aInner.querySelectorAll) {
                    aInner.querySelectorAll('[data-collection-fixed-tile]').forEach(function (node) {
                        var nk = (node.getAttribute('data-collection-fixed-tile') || '').trim();
                        if (!nk || nk === type || node.parentNode !== aInner) {
                            return;
                        }
                        var sl = slotFor(nk);
                        if (sl) {
                            sl.appendChild(node);
                            sl.style.minHeight = '';
                        }
                    });
                }
                if (openExpandedKey && openExpandedKey !== type) {
                    closeExpand('preserve-select2');
                }
                if (openExpandedKey === type) {
                    return;
                }
                aInner.appendChild(body);
                if (!usingDrawer) {
                    var h = Math.max(body.offsetHeight, body.getBoundingClientRect().height);
                    slot.style.minHeight = Math.ceil(h) + 'px';
                } else {
                    slot.style.minHeight = '';
                }
                openExpandedKey = type;
                openExpandedMode = fixedToolbarOn ? 'fixed' : 'flow';
                aInner.setAttribute('data-active-key', type);
                aWrap.removeAttribute('hidden');
                if (usingDrawer && toolbarMenuDrawer) {
                    var t = '';
                    var activeBtn = activeTilesHost() && activeTilesHost().querySelector
                        ? activeTilesHost().querySelector('[data-fixed-tile-key="' + type + '"]')
                        : null;
                    if (activeBtn) {
                        t = (activeBtn.getAttribute('data-label-full') || '').trim();
                    }
                    if (!t && body && body.getAttribute) {
                        t = (body.getAttribute('data-collection-fixed-label') || '').trim();
                    }
                    if (toolbarMenuTitle) {
                        toolbarMenuTitle.textContent = t || 'Menu';
                    }
                    toolbarMenuDrawer.setAttribute('aria-hidden', 'false');
                    toolbarMenuDrawer.classList.add('is-open');
                    document.body.style.overflow = 'hidden';
                }
                activeTilesHost().querySelectorAll('.pokehub-collection-fixed-tile-btn').forEach(function (b) {
                    var on = (b.getAttribute('data-fixed-tile-key') || '') === type;
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
                fixedToolbarReflowSelects(wrap);
                var tbOpen = Math.ceil(fixedHost.getBoundingClientRect().height);
                wrap.style.setProperty('--pokehub-collection-fixed-toolbar-height', tbOpen + 'px');
                if (tbOpen > 0) {
                    wrap.setAttribute('data-pokehub-last-fixed-toolbar-h', String(tbOpen));
                }
                anchorsDirty = true;
            }

            function syncOpenExpandAcrossModes() {
                if (!openExpandedKey) {
                    return;
                }
                var body = sectionBodyFor(openExpandedKey);
                var slot = slotFor(openExpandedKey);
                var aWrap = activeExpandWrap();
                var aInner = activeExpandInner();
                if (!body || !slot || !aWrap || !aInner) {
                    return;
                }
                if (body.parentNode !== aInner) {
                    aInner.appendChild(body);
                }
                var h = Math.max(body.offsetHeight, body.getBoundingClientRect().height);
                if (useToolbarMenuDrawer()) {
                    slot.style.minHeight = '';
                } else {
                    slot.style.minHeight = Math.ceil(h) + 'px';
                }
                aInner.setAttribute('data-active-key', openExpandedKey);
                aWrap.removeAttribute('hidden');
                if (fixedExpandWrap !== aWrap) {
                    fixedExpandWrap.setAttribute('hidden', 'hidden');
                }
                if (flowExpandWrap !== aWrap) {
                    if (flowExpandWrap) {
                        flowExpandWrap.setAttribute('hidden', 'hidden');
                    }
                }
                if (toolbarMenuDrawer && aWrap === toolbarMenuDrawer) {
                    toolbarMenuDrawer.setAttribute('aria-hidden', 'false');
                    toolbarMenuDrawer.classList.add('is-open');
                    document.body.style.overflow = 'hidden';
                } else if (toolbarMenuDrawer) {
                    var syncMenuWasOpen = toolbarMenuDrawer.classList.contains('is-open');
                    toolbarMenuDrawer.setAttribute('hidden', 'hidden');
                    toolbarMenuDrawer.setAttribute('aria-hidden', 'true');
                    toolbarMenuDrawer.classList.remove('is-open');
                    if (syncMenuWasOpen) {
                        document.body.style.overflow = '';
                    }
                }
            }

            if (toolbarMenuClose) {
                toolbarMenuClose.addEventListener('click', function () {
                    closeExpand('');
                });
            }
            if (toolbarMenuBackdrop) {
                toolbarMenuBackdrop.addEventListener('click', function () {
                    closeExpand('');
                });
            }
            /*
             * Clic sur une tuile "Jump to": refermer le panneau/menu ouvert
             * (sinon l'état openExpandedKey peut le rouvrir au tick suivant).
             */
            wrap.addEventListener('click', function (ev) {
                var jumpLink = ev.target && ev.target.closest
                    ? ev.target.closest('.pokehub-collection-generation-jump-link[data-generation-anchor]')
                    : null;
                if (!jumpLink || !wrap.contains(jumpLink)) {
                    return;
                }
                if (openExpandedKey === 'generations') {
                    closeExpand('');
                }
            });

            function clearTilesUi() {
                [fixedTilesHost, flowTilesHost].forEach(function (host) {
                    if (!host) return;
                    host.innerHTML = '';
                    host.removeAttribute('data-fixed-visible-count');
                });
            }

            function pokehubUseShortFixedTileLabels() {
                return typeof window.matchMedia === 'function' && window.matchMedia('(max-width: 640px)').matches;
            }

            /** Extrait la première URL d’un `background-image` CSS (url("…") ou url(…)). */
            function pokehubParseCssBackgroundImageUrl(bi) {
                var s = String(bi || '').trim();
                if (!s || s === 'none') {
                    return '';
                }
                var dq = s.match(/url\s*\(\s*"([^"]*)"\s*\)/i);
                if (dq) {
                    return dq[1].trim();
                }
                var sq = s.match(/url\s*\(\s*'([^']*)'\s*\)/i);
                if (sq) {
                    return sq[1].trim();
                }
                var uq = s.match(/url\s*\(\s*([^)]+)\s*\)/i);
                if (!uq) {
                    return '';
                }
                var inner = uq[1].trim();
                if ((inner.charAt(0) === '"' && inner.charAt(inner.length - 1) === '"') || (inner.charAt(0) === "'" && inner.charAt(inner.length - 1) === "'")) {
                    inner = inner.slice(1, -1).trim();
                }
                return inner;
            }

            /** URL décor barre fixe : attribut data, sinon même image que la carte flux (.toolbar-panel-media). */
            function pokehubFixedToolbarDecorationUrl(panelEl) {
                if (!panelEl) {
                    return '';
                }
                var u = (panelEl.getAttribute('data-toolbar-decoration-url') || '').trim();
                if (u) {
                    return u;
                }
                var hel = panelEl.querySelector('.pokehub-collection-toolbar-panel-media');
                if (!hel) {
                    return '';
                }
                var bi = (hel.style && hel.style.backgroundImage) ? String(hel.style.backgroundImage).trim() : '';
                if (!bi || bi === 'none') {
                    try {
                        bi = window.getComputedStyle ? String(window.getComputedStyle(hel).backgroundImage || '').trim() : '';
                    } catch (eBg) {
                        bi = '';
                    }
                }
                return pokehubParseCssBackgroundImageUrl(bi);
            }

            function rebuildTiles(buttonActiveKey) {
                var tilesHost = activeTilesHost();
                if (!tilesHost) return;
                tilesHost.innerHTML = '';
                tilesHost.removeAttribute('data-fixed-visible-count');
                var subtitleByKey = {
                    filters: 'Choose which tiles to show or hide.',
                    pogo: 'Copy search strings to find the Pokémon directly in your game.',
                    generations: 'Move easily between regions.',
                    selectors: 'Add / mark quickly'
                };
                var added = [];
                orderedKeys.forEach(function (k) {
                    if (!visibleTiles[k]) return;
                    var body = sectionBodyFor(k);
                    if (!body) return;
                    added.push(k);
                    var labelFull = (body.getAttribute('data-collection-fixed-label') || k).trim();
                    var labelShort = (body.getAttribute('data-collection-fixed-label-short') || labelFull).trim();
                    var decoUrl = pokehubFixedToolbarDecorationUrl(body);
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'pokehub-collection-fixed-tile-btn' + (decoUrl ? ' pokehub-collection-fixed-tile-btn--has-media' : '');
                    btn.setAttribute('data-fixed-tile-key', k);
                    btn.setAttribute('role', 'tab');
                    btn.setAttribute('aria-pressed', 'false');
                    btn.setAttribute('data-label-full', labelFull);
                    btn.setAttribute('data-label-short', labelShort);
                    btn.setAttribute('aria-label', labelFull);
                    var labelSpan = document.createElement('span');
                    labelSpan.className = 'pokehub-collection-fixed-tile-btn-label';
                    labelSpan.textContent = pokehubUseShortFixedTileLabels() ? labelShort : labelFull;
                    btn.appendChild(labelSpan);
                    var subtitleSpan = document.createElement('span');
                    subtitleSpan.className = 'pokehub-collection-fixed-tile-btn-subtitle';
                    subtitleSpan.textContent = subtitleByKey[k] || '';
                    btn.appendChild(subtitleSpan);
                    if (decoUrl) {
                        btn.style.setProperty('--pokehub-fixed-tile-bg', 'url(' + JSON.stringify(decoUrl) + ')');
                        var media = document.createElement('span');
                        media.className = 'pokehub-collection-fixed-tile-btn-media';
                        media.setAttribute('aria-hidden', 'true');
                        btn.appendChild(media);
                    }
                    btn.addEventListener('click', function () {
                        var key = btn.getAttribute('data-fixed-tile-key') || '';
                        if (openExpandedKey === key) {
                            closeExpand('');
                        } else {
                            openExpand(key);
                        }
                    });
                    tilesHost.appendChild(btn);
                    if (buttonActiveKey === k) {
                        btn.classList.add('is-active');
                        btn.setAttribute('aria-pressed', 'true');
                    }
                });
                if (added.length > 0) {
                    tilesHost.setAttribute('data-fixed-visible-count', String(added.length));
                }
            }

            function updateTilesFromMaskLine(maskLine) {
                /** Masque ≈ scrollY + bar site. Plus l’offset est bas, plus la tuile apparaît tôt quand on descend (aligné avec header fixe qui saute plus haut). */
                var tileMaskEnter = -64;
                var tileMaskExit = -96;
                orderedKeys.forEach(function (k) {
                    var anch = anchorsSlots[k];
                    if (anch >= Number.POSITIVE_INFINITY / 2) {
                        visibleTiles[k] = false;
                        return;
                    }
                    if (!visibleTiles[k] && maskLine >= (anch + tileMaskEnter)) {
                        visibleTiles[k] = true;
                    } else if (visibleTiles[k] && maskLine <= (anch + tileMaskExit)) {
                        visibleTiles[k] = false;
                    }
                });
                if (openExpandedKey && !visibleTiles[openExpandedKey]) {
                    closeExpand('');
                }
            }

            function setFixedToolbar(active) {
                fixedToolbarOn = false;
                wrap.classList.remove('pokehub-collection--fixed-toolbar');
                if (fixedHost) {
                    fixedHost.setAttribute('hidden', 'hidden');
                    fixedHost.setAttribute('aria-hidden', 'true');
                }
                if (flowHeader) {
                    flowHeader.removeAttribute('inert');
                }
            }

            function tick() {
                ensureAnchors();
                var barOffPx = stickyViewportOffset().barOffset;
                var y = window.pageYOffset || document.documentElement.scrollTop || 0;
                var stackStyle = window.getComputedStyle(toolbarStack);
                var stickyGapPx = parseFloat(stackStyle.getPropertyValue('--pokehub-toolbar-sticky-gap'));
                if (!Number.isFinite(stickyGapPx)) {
                    stickyGapPx = 25;
                }
                var pinLineViewportTop = barOffPx + stickyGapPx;
                var stackRect = toolbarStack.getBoundingClientRect();
                var stackH = Math.max(1, Math.ceil(stackRect.height));
                var wrapRect = wrap.getBoundingClientRect();
                var footerGuardPx = 16;
                var hasRoomToPin = wrapRect.bottom >= pinLineViewportTop + stackH + footerGuardPx;
                var isPinned = toolbarStack.classList.contains('pokehub-toolbar-stack--pinned');
                var shouldPinStack;
                if (isPinned) {
                    if (!hasRoomToPin) {
                        shouldPinStack = false;
                        toolbarStackPinScrollAnchorY = null;
                    } else if (toolbarStackPinScrollAnchorY !== null && Number.isFinite(toolbarStackPinScrollAnchorY)) {
                        shouldPinStack = y + 8 >= toolbarStackPinScrollAnchorY;
                    } else {
                        shouldPinStack = true;
                    }
                } else {
                    if (stackRect.top < pinLineViewportTop - 2 && hasRoomToPin) {
                        toolbarStackPinScrollAnchorY = stackRect.top + y - pinLineViewportTop;
                        shouldPinStack = true;
                    } else {
                        shouldPinStack = false;
                        toolbarStackPinScrollAnchorY = null;
                    }
                }
                if (shouldPinStack) {
                    if (!toolbarStack.classList.contains('pokehub-toolbar-stack--pinned')) {
                        var h = Math.ceil(toolbarStack.getBoundingClientRect().height);
                        wrap.style.setProperty('--pokehub-toolbar-stack-pin-spacer', h + 'px');
                        wrap.classList.add('pokehub-toolbar-stack-pinned');
                        toolbarStack.classList.add('pokehub-toolbar-stack--pinned');
                        if (!Number.isFinite(toolbarStackPinScrollAnchorY)) {
                            toolbarStackPinScrollAnchorY =
                                toolbarStack.getBoundingClientRect().top + y - pinLineViewportTop;
                        }
                    }
                    syncPinnedToolbarStackToWrapBox();
                } else if (toolbarStack.classList.contains('pokehub-toolbar-stack--pinned')) {
                    toolbarStack.classList.remove('pokehub-toolbar-stack--pinned');
                    toolbarStack.style.left = '';
                    toolbarStack.style.width = '';
                    toolbarStack.style.right = '';
                    wrap.classList.remove('pokehub-toolbar-stack-pinned');
                    wrap.style.setProperty('--pokehub-toolbar-stack-pin-spacer', '0px');
                    toolbarStackPinScrollAnchorY = null;
                }
                setFixedToolbar(false);
                orderedKeys.forEach(function (k) {
                    visibleTiles[k] = !!slotFor(k);
                });
                var fpFlow = visibleTilesFingerprint() + '|flow';
                if (fpFlow !== lastTilesFingerprint) {
                    lastTilesFingerprint = fpFlow;
                    rebuildTiles(openExpandedKey);
                }
                syncOpenExpandAcrossModes();
                if (openExpandedKey && sectionBodyFor(openExpandedKey) && sectionBodyFor(openExpandedKey).parentNode === flowExpandInner) {
                    if (flowExpandWrap) {
                        flowExpandWrap.removeAttribute('hidden');
                    }
                }
                wrap.style.setProperty('--pokehub-collection-fixed-toolbar-height', '0px');
                syncFixedToolbarToWrapBox();
            }

            var raf = 0;
            var resizeSyncRaf = 0;
            var resizeTickTimer = null;
            function onScrollTick() {
                if (raf) return;
                raf = window.requestAnimationFrame(function () {
                    raf = 0;
                    tick();
                });
            }
            function onWindowResize() {
                if (fixedToolbarOn) {
                    if (!resizeSyncRaf) {
                        resizeSyncRaf = window.requestAnimationFrame(function () {
                            resizeSyncRaf = 0;
                            syncFixedToolbarToWrapBox();
                            syncPinnedToolbarStackToWrapBox();
                        });
                    }
                }
                if (resizeTickTimer) {
                    clearTimeout(resizeTickTimer);
                }
                resizeTickTimer = window.setTimeout(function () {
                    resizeTickTimer = null;
                    anchorsDirty = true;
                    tick();
                }, 100);
            }
            window.addEventListener('scroll', onScrollTick, { passive: true });
            window.addEventListener('resize', onWindowResize);
            window.setTimeout(onScrollTick, 250);
            tick();
        });
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
        initCollectionGenerationJump();
        initCollectionGenerationJumpScroll();
        initCollectionToolbarPogoWholePanelClick();
        initCollectionFixedToolbar();
        if (!window._pokehubFixedTileLabelsResizeHook) {
            window._pokehubFixedTileLabelsResizeHook = true;
            window.addEventListener('resize', function () {
                if (typeof window.pokehubRefreshFixedCollectionTileLabelsDebounced === 'function') {
                    window.pokehubRefreshFixedCollectionTileLabelsDebounced();
                }
            });
        }
        window.setTimeout(function () {
            if (typeof window.pokehubRefreshFixedCollectionTileLabels === 'function') {
                window.pokehubRefreshFixedCollectionTileLabels();
            }
        }, 400);
    }
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(initCollectionsAndPogo, 0);
    } else {
        document.addEventListener('DOMContentLoaded', initCollectionsAndPogo);
    }
})(jQuery);
