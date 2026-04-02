(() => {
    const cfg = window.PokeHubPokemonImageFallback;
    if (!cfg || !cfg.primaryBase || !cfg.fallbackBase) {
        return;
    }

    const normalizeBase = (value) => String(value || '').replace(/\/+$/, '');
    const primaryBase = normalizeBase(cfg.primaryBase);
    const fallbackBase = normalizeBase(cfg.fallbackBase);

    if (!primaryBase || !fallbackBase || primaryBase === fallbackBase) {
        return;
    }

    document.addEventListener('error', (event) => {
        const img = event.target;
        if (!img || img.tagName !== 'IMG') {
            return;
        }

        const currentSrc = img.currentSrc || img.src || '';
        if (!currentSrc || img.dataset.pokehubFallbackApplied === '1') {
            return;
        }

        // Ne traite que les images Pokémon qui viennent de la base primaire.
        if (!currentSrc.startsWith(primaryBase + '/')) {
            return;
        }

        const relativePath = currentSrc.slice(primaryBase.length + 1);
        if (!relativePath) {
            return;
        }

        img.dataset.pokehubFallbackApplied = '1';
        event.preventDefault();
        event.stopImmediatePropagation();
        img.src = fallbackBase + '/' + relativePath;
    }, true);
})();
