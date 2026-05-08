# Collections – CSS et thème

Référence technique complémentaire à **[docs/README.md](../../docs/README.md)**, à **[docs/THEME_FRONT_CSS.md](../../docs/THEME_FRONT_CSS.md)** (ordre thème / plugin), à la **[charte doc](../../docs/REDACTION.md)** et à **[COLLECTIONS_HEADERS.md](./COLLECTIONS_HEADERS.md)** (contrat des headers du module).

**Emplacement du CSS (production Me5rine Lab)** : les styles spécifiques modules sont dans le thème enfant — **`css/poke-hub/parts/13-collections-front.css`** (chargé comme les autres `parts/` avec son propre `?ver=filemtime`). La liste officielle inclut encore **`poke-hub-front.css`** (`@import` des `parts/`) comme index / éditeur. Les surcharges de variables (dégradés) sont dans **`parts/14-collections-theme.css`**. Un correctif de cascade final peut exister dans **`css/poke-hub/poke-hub-late-overrides.css`**. Aucun fichier CSS dans le dossier du module : tout passe par l’enchaînement documenté dans THEME_FRONT_CSS.

**Bloc phrases de recherche Pokémon GO (in-game)** : toute la présentation (grille à deux colonnes de groupes, titres de groupe, champs copiables, `<summary>` du `<details>`, barre d’outils des deux selects) est définie dans **`parts/13-collections-front.css`**, section commentée *Recherche in-game Pokémon GO*. Utiliser les **variables globales front** `css/variables.css` (`--me5rine-lab-*`, `--me5rine-lab-font`) — pas de `--admin-lab-*` sur ce bloc (réservé à l’admin / notices). Les correctifs de cascade (liste collections, `<details>` avancé, tuiles filtrées, bannière reset `[hidden]`) sont dans **`poke-hub-late-overrides.css`** (voir `docs/THEME_FRONT_CSS.md`).

**Mode sans thème embarqué** : si `poke_hub_load_default_plugin_front_css` vaut `true` et que `assets/css/poke-hub-collections-front.css` est présent dans le plugin, il est enqueued comme auparavant.

Le module Collections **réutilise au maximum les classes et variables du système CSS commun**. Voir en priorité :

- **docs/FRONT_CSS.md** – variables `--me5rine-lab-*`, boutons, cartes, titres, dashboard, formulaires
- **docs/CSS_RULES.md** – champs formulaire, labels, inputs, selects
- **docs/CSS_SYSTEM.md** – structure des conteneurs (dashboard, form-block, etc.)

## Classes utilisées (système commun)

Collections applique notamment : `me5rine-lab-dashboard`, `me5rine-lab-title-large`, `me5rine-lab-subtitle`, `me5rine-lab-dashboard-header`, `me5rine-lab-dashboard-header-actions`, `me5rine-lab-form-button`, `me5rine-lab-form-button-secondary`, `me5rine-lab-form-button-remove`, `me5rine-lab-form-block`, `me5rine-lab-form-field`, `me5rine-lab-form-label`, `me5rine-lab-form-input`, `me5rine-lab-form-select`, `me5rine-lab-form-message`, `me5rine-lab-form-message-warning`, `me5rine-lab-form-hint`, `me5rine-lab-state-message`, `me5rine-lab-card`, `me5rine-lab-card-name`, `me5rine-lab-card-meta`, `me5rine-lab-card-actions`, `me5rine-lab-sr-only`. Layout vue collection : `pokehub-collection-view-header-main` (groupe titre à côté des actions). Bloc **phrases de recherche GO** : `pokehub-collection-pogo-search`, `pokehub-collection-pogo-search-summary`, `pokehub-collection-pogo-search-body`, `pokehub-collection-pogo-search-toolbar`, `pokehub-collection-pogo-search-toolbar-field`, `pokehub-collection-pogo-search-hint-refresh`, préfixe `pokehub-pogo-search-*` pour la zone générée en JS (voir **docs/POKEHUB_CSS_CLASSES.md** et section précédente).

Comportement fonctionnel du pool et des options (genre Nidoran, symboles ♂/♀, doublon mâle/femelle collectionneur, résolution des statuts, phrases GO, **options masquées par catégorie**) : **[docs/COLLECTIONS_MODULE.md](../../docs/COLLECTIONS_MODULE.md)** (*Options masquées par catégorie (UI)*).

### Masquage des lignes du filtre (création)

À la **création**, le script front ajoute **`is-hidden`** sur les `<label data-collections-control="…">` dans `#pokehub-collection-content-filter-wrap` lorsque la catégorie exclut ce réglage. Le thème **`parts/13-collections-front.css`** doit définir `display: none` pour `#pokehub-collection-content-filter-wrap label[data-collections-control].is-hidden` (sinon les cases restent visibles). Une règle équivalente sur **`.pokehub-collections-form-edit label[data-collections-control].is-hidden`** permet d’aligner le drawer d’édition si la même classe est utilisée ; en pratique l’édition **retire souvent les lignes du HTML** (PHP) plutôt que de les masquer par classe — voir **COLLECTIONS_MODULE.md** (*Options masquées par catégorie*).

Pour harmoniser avec le thème, **surcharger les variables `--me5rine-lab-*`** dans le thème (définies dans FRONT_CSS.md). Une seule modification de variable change le style partout.

## Variables spécifiques Collections

Seules les variables suivantes sont propres au module (dégradé des cartes de la liste) ; elles ont un fallback sur le système :

| Variable | Rôle |
|----------|------|
| `--pokehub-collections-card-gradient-start` | Début du dégradé (fallback : `--me5rine-lab-primary`) |
| `--pokehub-collections-card-gradient-mid` | Milieu du dégradé (fallback : `--me5rine-lab-secondary`) |
| `--pokehub-collections-card-gradient-end` | Fin du dégradé (fallback : `--me5rine-lab-secondary`) |
| `--pokehub-collections-card-overlay` | Overlay sombre sur la carte (rgba(0,0,0,0.7)) |
| `--pokehub-collections-modal-backdrop` | Fond du modal (rgba(0,0,0,0.5)) |

Les variables suivantes concernent **l’alignement sous le header du site** (typiquement Elementor) et sous la barre d’administration WordPress, pour que les bandeaux **`position: sticky`** de la vue collection ne recouvrent pas le masthead et restent calés :

| Variable | Rôle |
|----------|------|
| `--pokehub-elementor-header-offset` | Hauteur réservée pour le header global du site (**valeurs de référence** dans Me5rine : environ **129px** bureau, **123px** `max-width: 1024px`, **95px** `max-width: 767px` — à ajuster dans le thème si le header réel diffère). |
| `--pokehub-adminbar-offset` | **0px** par défaut ; **32px** / **46px** (`max-width: 782px`) sur `body.admin-bar`. |
| `--pokehub-collection-fixed-toolbar-height` | Définie en **JavaScript** sur `.pokehub-collection-view-wrap` lorsque la barre **`[data-collection-fixed-toolbar]`** est affichée : hauteur en pixels de cette barre (tuiles + panneau déplié éventuel). Sert au calage du layout / scroll ; retombée à `0px` lorsque la barre fixe est masquée. |
| `--pokehub-collection-header-sticky-height` | Référence résiduelle possible dans le thème pour la zone titre (à ne pas confondre avec `--pokehub-collection-fixed-toolbar-height`). |

**Barre d’outils fixe au scroll** — styles dans **`parts/13-collections-front.css`** : lorsque `.pokehub-collection-view-wrap` porte **`pokehub-collection--fixed-toolbar`**, la pile **`.pokehub-collection-toolbar-stack`** peut être épinglée (`pokehub-toolbar-stack--pinned`) et la barre **`[data-collection-fixed-toolbar]`** reste sous le header du site. Les tuiles flux / fixe utilisent **`[data-flow-tiles-host]`** et **`[data-fixed-tiles-host]`** ; l’aire de contenu ouverte est **`[data-fixed-expand-inner]`** (ou le corps du tiroir **`[data-toolbar-menu-body]`** selon le breakpoint). **Jump to generation** : ruban horizontal défilant avec flèches uniquement en **`max-width: 1024px`** ; au-delà, grille comme le panneau dans le flux (y compris le panneau ouvert dans `.pokehub-collection-fixed-expand`).

**Empilement (`z-index`) et menu tiroir** : **`.pokehub-collection-toolbar-stack`** combine un **`z-index`** modéré (variable **`--pokehub-collection-toolbar-stack-z`**, typiquement **9** pour rester **sous** le header global du thème, ex. `z-index: 10`) et **`isolation: isolate`**. Tout descendant y est donc cantonné à ce **contexte d’empilement** : le menu **`.pokehub-collections-drawer--toolbar`** (`[data-toolbar-menu-drawer]`) doit être rendu **en dehors** de cette pile dans le HTML (voir **`collections-shortcode.php`** et **COLLECTIONS_HEADERS.md**). Les règles **`.pokehub-collections-drawer`** du thème (`position: fixed`, **`z-index: 100000`**, plein viewport) s’appliquent alors correctement au-dessus du contenu de la page.

**Personnalisation dans le thème** : éditer `parts/14-collections-theme.css` (déjà importé par `poke-hub-front.css`) ou surcharger en CSS dans une couche chargée **après** `poke-hub-late-overrides` si besoin.

Exemple dans le thème (ou via le fichier fourni) pour raccorder les cartes à la couleur primaire :

```css
:root {
    --pokehub-collections-card-gradient-start: var(--me5rine-lab-primary);
    --pokehub-collections-card-gradient-mid: var(--me5rine-lab-secondary);
    --pokehub-collections-card-gradient-end: var(--me5rine-lab-secondary);
}
```

## Image de fond des cartes (liste des collections)

### Priorité d’affichage

1. **Image personnalisée** : si l’utilisateur a renseigné une image de couverture dans les paramètres de la collection (option `card_background_image_url`), cette URL est utilisée.
2. **Image par défaut par catégorie** : sinon, le filtre **`poke_hub_collections_card_background_image_url`** est appelé avec la catégorie de la collection ; le thème peut ainsi fournir une image par défaut pour chaque type (shiny, costume, **custom**, etc.).
3. **Dégradé** : si aucune URL n’est fournie, la carte utilise le dégradé défini par les variables `--pokehub-collections-card-gradient-*`.

### Image par défaut pour les collections « personnalisées » (custom)

Pour les collections de type **« Liste personnalisée »** (`custom`), vous pouvez définir une **image par défaut** via le même filtre, en retournant une URL lorsque `$category === 'custom'`. Les utilisateurs peuvent ensuite éventuellement remplacer cette image en personnalisant la couverture dans les paramètres de leur collection.

### Personnalisation par l’utilisateur

Dans la **vue d’une collection**, le panneau **Paramètres** (drawer) propose le champ **« Image de couverture (carte sur la liste des collections) »**. L’URL saisie est enregistrée dans `options.card_background_image_url` et utilisée en priorité sur la liste des collections. Vide = utilisation du défaut par catégorie (filtre) ou du dégradé.

### Filtre PHP (défauts par catégorie)

**Filtre :** `poke_hub_collections_card_background_image_url`  
**Paramètres :** `( string $url, string $category )`  
**Retour :** l’URL de l’image à utiliser pour cette catégorie (chaîne vide = dégradé).

Exemple dans le thème (functions.php ou fichier dédié) :

```php
add_filter('poke_hub_collections_card_background_image_url', function ($url, $category) {
    $images = [
        'shiny'           => 'https://exemple.com/images/collections-shiny.jpg',
        'costume'         => 'https://exemple.com/images/collections-costume.jpg',
        'custom'         => 'https://exemple.com/images/collections-custom-default.jpg', // défaut listes personnalisées
        // … autres catégories
    ];
    return $images[$category] ?? '';
}, 10, 2);
```

Ou avec des images du thème :

```php
add_filter('poke_hub_collections_card_background_image_url', function ($url, $category) {
    return get_stylesheet_directory_uri() . '/images/collections-' . $category . '.jpg';
}, 10, 2);
```

Chaque carte a aussi l’attribut **`data-category`** (ex. `data-category="shiny"`) pour un ciblage CSS éventuel.

## Légende des tuiles et couleurs (comme les notices)

En vue collection (mode tuiles), les couleurs reprennent **celles des notices** (global-colors.css). Le module charge `global-colors.css` en front lorsque la page collections est affichée.

| Statut | Couleur | Variable (global-colors) |
|--------|---------|---------------------------|
| **Possédé** | Vert : **contour et bulle** = `--admin-lab-color-var-green` (#42af13), fond = notice success | `--admin-lab-color-var-green` (contour + pastille), `--admin-lab-color-notice-sucess-background` (fond tuile) |
| **À l'échange** | Orange (notice warning) | `--admin-lab-color-notice-warning` |
| **Manquant** | Gris (comme actuellement) | `--me5rine-lab-border` |

Aucune couleur dédiée : tout repose sur les variables notice. Pour harmoniser, surchargez-les dans le thème (comme pour les notices admin). Classes layout : `.pokehub-collection-legend`, `.pokehub-collection-legend-item`, `.pokehub-collection-legend-dot`, `.pokehub-legend-owned`, `.pokehub-legend-for-trade`, `.pokehub-legend-missing`. Tuiles : `data-status="owned"|"for_trade"|"missing"` et `.pokehub-collection-tile-status`.

## Filtre d’affichage par statut (vue collection)

En vue collection (**compte connecté** ou **collection locale**), le bloc **Include in grid** permet de masquer ou réafficher des tuiles selon le statut, **sans modifier** les données (REST / base / `localStorage`). Chaîne source anglaise actuelle dans le code : **`Include in grid`** ; les traductions peuvent refléter l’usage métier (« Afficher… », « Inclure… », etc.) selon vos fichiers .po/.mo .

Trois cases correspondent aux statuts techniques suivants (voir aussi **docs/COLLECTIONS_MODULE.md**, section *Statuts d’une entrée*) :

- **Possédé** — `owned`
- **À l'échange** — `for_trade` (chaîne source *For trade*)
- **Manquant** — `missing`

Classes dédiées :

- `.pokehub-collection-status-filters` — conteneur du filtre.
- `.pokehub-collection-status-filters-inner` — ligne titre + cases.
- `.pokehub-collection-status-filters-heading` — libellé du bloc (titre du groupe).
- `.pokehub-collection-status-filters-checkboxes` — groupe des cases.
- `.pokehub-collection-status-filter-label` — libellé d’une case.
- `.pokehub-collection-filter-status` — case à cocher pilotée par JS (`data-filter-status`).
- `.pokehub-collection-status-filters-note` — aide *Click a tile to cycle status* sous la ligne de cases.
- `.pokehub-collection-generation-jump` / `.pokehub-collection-generation-jump-links` — ancres rapides par génération ou région ; rendues **dans** le slot **`[data-collection-toolbar-slot="generations"]`** lorsqu’il existe.
- **`[data-flow-tiles-host]`**, **`[data-fixed-tiles-host]`**, **`[data-fixed-tile-key]`**, `.pokehub-collection-fixed-tile-btn` — tuiles de navigation vers les panneaux (`initCollectionFixedToolbar`, `collections-front.js`).

- `.pokehub-collection-filter-empty-hint` — message si aucun statut n’est coché.

Le script applique l’attribut HTML `hidden` aux tuiles dont le statut est décoché. Si la collection est regroupée par génération (`.pokehub-collection-generation-block`), les sections sans aucune tuile visible sont masquées.

## Paramètres adaptatifs (création / édition)

Pour les **catégories spécifiques** (Gigantamax, Dynamax, Costume, etc.), le bloc « Contenu à afficher » (Méga, Gigantamax, Dynamax, costumes) est masqué et remplacé par un message. Classes utilisées :

- `.pokehub-collections-options-additive` — bloc des options « en plus » (masqué si type spécifique).
- `.pokehub-collections-options-specific-hint` — message « Cette collection n’affiche que ce type ».
- `.pokehub-collections-options-additive.is-hidden`, `.pokehub-collections-options-specific-hint.is-hidden`, `.pokehub-collection-filter-empty-hint.is-hidden` — affichage conditionnel (avec `.is-hidden { display: none }`).

## Style inline (cartes uniquement)

Quand une image de fond est fournie (personnalisée ou via le filtre), elle est appliquée en **inline** sur `.pokehub-collections-card-bg` (background-image, background-size: cover, background-position: center). Sans image, le dégradé des variables CSS est utilisé. La bannière « collections anonymes » est masquée via la classe `.is-hidden`.

---

*Index de la documentation : [README du dossier docs](../../docs/README.md) · [Charte rédactionnelle](../../docs/REDACTION.md)*
