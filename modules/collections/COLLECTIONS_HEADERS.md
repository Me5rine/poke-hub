# Collections – Headers (référence module)

Référence dédiée aux en-têtes du module Collections.  
Complément de **[COLLECTIONS_THEME_CSS.md](./COLLECTIONS_THEME_CSS.md)** et de **[Contrat Front des Modules](../../docs/FRONT_MODULE_CONTRACT.md)**.

## Objectif

Unifier les headers du module avec le système Me5rine Lab (notamment `me5rine-lab-dashboard-header`) pour :

- garder un rendu cohérent entre modules (Collections, Friend Codes, etc.),
- éviter les régressions lors des refactors CSS/JS,
- clarifier ce qui est structurel (HTML), visuel (CSS) et dynamique (JS).

## 1) Header de la page liste des collections

### Markup source

Dans `modules/collections/public/collections-shortcode.php`, la page liste utilise :

- conteneur : `.pokehub-collections-wrap.me5rine-lab-dashboard`
- header : `.me5rine-lab-dashboard-header`
- texte : `.me5rine-lab-subtitle`
- actions : `.me5rine-lab-dashboard-header-actions`
- CTA principal : `.pokehub-collections-btn-create`

### Règles d’unification

- Conserver la base Me5rine Lab : `.me5rine-lab-dashboard-header`.
- Éviter les styles “isolés module” qui cassent l’uniformité inter-modules.
- Les overrides de `parts/13-collections-front.css` doivent rester minimaux et compatibles avec `css/dashboard.css`.

## 2) Header de la vue collection (single collection)

### Blocs principaux

Dans `modules/collections/public/collections-shortcode.php` :

- wrapper principal : `.pokehub-collection-toolbar-stack`
- header de page : `.me5rine-lab-dashboard-header.pokehub-collection-view-header`
- inner : `.pokehub-collection-header-inner`
- zone gauche : `.pokehub-collection-view-header-left` (bouton back)
- zone centrale : `.pokehub-collection-view-header-main` (titre + progression)
- actions : `.me5rine-lab-dashboard-header-actions.pokehub-collection-view-actions`

### Variantes

- `pokehub-collection-view-header--has-cover` / `--no-cover`
- `pokehub-collection-header-inner--two-cols` (quand pas d’actions d’édition)

## 3) Barre sticky outils sous le header

### Structure

- bloc sticky : `.pokehub-collection-sticky-tools`
- chrome interne : `.pokehub-collection-flow-toolbar-chrome`
- tuiles nav : `[data-flow-tiles-host]`
- recap région active : `[data-flow-active-region]`

### Comportement

Le JS (`modules/collections/assets/js/collections-front.js`) gère :

- la barre “pinned” (`.pokehub-toolbar-stack--pinned`) quand nécessaire,
- la mise à jour de la région active (nom, icône, stats, barre),
- l’ouverture des contenus (`Filters / GO / Gen`) dans le drawer toolbar.

## 4) Contrat CSS/JS à respecter

Pour éviter les régressions, ne pas renommer sans mise à jour JS associée :

- `.pokehub-collection-toolbar-stack`
- `.pokehub-collection-sticky-tools`
- `[data-flow-tiles-host]`
- `[data-flow-active-region]`
- `[data-fixed-active-region-*]`
- `.pokehub-collections-drawer--toolbar`

## 5) Checklist “nouveau module”

Quand un module ajoute un header :

1. Réutiliser `me5rine-lab-dashboard-header` (base commune).
2. Documenter la structure dans un `*_HEADERS.md` au niveau du module.
3. Séparer clairement :
   - classes de structure (HTML),
   - classes de skin (CSS),
   - hooks de comportement (data-attributes / JS).
4. Préciser les classes “contrat” qui ne doivent pas changer sans refactor JS.
5. Lier le document depuis le README/doc module existant.

---

Index docs module Collections : **[COLLECTIONS_THEME_CSS.md](./COLLECTIONS_THEME_CSS.md)**.
