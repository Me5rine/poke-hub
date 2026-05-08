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

- **Barre fixe (clone, hors flux)** : `.pokehub-collection-fixed-toolbar` `[data-collection-fixed-toolbar]` — hero dupliqué (`.pokehub-collection-fixed-toolbar-hero`), **`[data-fixed-tiles-host]`**, **`[data-fixed-expand]`** > **`[data-fixed-expand-inner]`**. Réparentée sous `document.body` par le JS pour un positionnement fiable.
- **Pile flux** : `.pokehub-collection-toolbar-stack` — **`.pokehub-collection-toolbar-header`** puis **`.pokehub-collection-toolbar-tools`** avec **`[data-flow-tiles-host]`** et les slots **`[data-collection-toolbar-slot]`** (panneaux **`data-collection-fixed-tile`**). Le bloc message pool vide (`$total === 0`), le slot **selectors** (si applicable) et la fermeture de la pile restent **dans** ce conteneur.
- **Menu tiroir barre d’outils** : **`[data-toolbar-menu-drawer]`** (`.pokehub-collections-drawer--toolbar`) est un **frère** de **`.pokehub-collection-toolbar-stack`** (immédiatement après sa balise fermante, avant **`.pokehub-collection-tiles`**), pas un enfant de la pile — pour un `position: fixed` plein viewport et un `z-index` cohérent avec le thème (voir **`COLLECTIONS_THEME_CSS.md`**).
- header de page : `.me5rine-lab-dashboard-header.pokehub-collection-view-header`
- inner : `.pokehub-collection-header-inner`
- zone gauche : `.pokehub-collection-view-header-left` (bouton retour)
- zone centrale : `.pokehub-collection-view-header-main` (titre + progression)
- actions : `.me5rine-lab-dashboard-header-actions.pokehub-collection-view-actions`

### Variantes

- `pokehub-collection-view-header--has-cover` / `--no-cover`
- `pokehub-collection-header-inner--two-cols` (quand pas d’actions d’édition)

## 3) Barre d’outils : flux, tuiles et expand

### Structure (attributs « contrat »)

| Élément | Rôle |
|--------|------|
| `[data-flow-tiles-host]` | Rangée de tuiles dans le flux (`.pokehub-collection-toolbar-tools`) |
| `[data-fixed-tiles-host]` | Rangée de tuiles sous la barre fixe |
| `[data-collection-toolbar-slot]` | Slot d’un panneau (`filters`, `pogo`, `generations`, `selectors`) |
| `data-collection-fixed-tile` | Clé du panneau (même valeur pour le rendu flux et pour le déplacement vers l’expand) |
| `[data-fixed-expand]` / `[data-fixed-expand-inner]` | Zone où le panneau ouvert est injecté (barre fixe) |
| `[data-toolbar-menu-drawer]` / `[data-toolbar-menu-body]` | Tiroir menu (petit écran) pouvant accueillir le même panneau ; nœuds **hors** de **`.pokehub-collection-toolbar-stack`** (voir § 2 ci-dessus) |

### Comportement (JS)

`collections-front.js` — **`initCollectionFixedToolbar()`** : affiche la barre **`[data-collection-fixed-toolbar]`** selon le scroll, reconstruit les tuiles visibles, ouvre **un seul** panneau à la fois dans l’expand actif ; **`sectionBodyFor()`** retrouve le panneau dans le wrap **ou** dans les inner d’expand après reparentage. Lorsque le tiroir menu est utilisé comme expand, **`document.body.style.overflow`** est passé à **`hidden`** tant qu’il est ouvert (`is-open`), puis rétabli à la fermeture si le menu était bien affiché.

## 4) Contrat CSS/JS à respecter

Pour éviter les régressions, ne pas renommer sans mise à jour JS associée :

- `.pokehub-collection-view-wrap`, `.pokehub-collection-toolbar-stack`
- `[data-collection-fixed-toolbar]`, `[data-flow-tiles-host]`, `[data-fixed-tiles-host]`, `[data-fixed-expand]`, `[data-fixed-expand-inner]`
- `[data-collection-toolbar-slot]`, `data-collection-fixed-tile`
- `[data-toolbar-menu-drawer]`, `[data-toolbar-menu-body]` (si le drawer toolbar est présent dans le HTML)

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
