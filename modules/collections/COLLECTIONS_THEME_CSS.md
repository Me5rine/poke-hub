# Collections – CSS et thème

**Emplacement du CSS** : le CSS front des collections est dans le **CSS global du plugin** : `assets/css/poke-hub-collections-front.css` (aucun fichier CSS dans le module). Un **fichier à inclure dans le thème** est fourni : `assets/theme/poke-hub-collections-theme.css` (voir ci‑dessous).

Le module Collections **réutilise au maximum les classes et variables du système CSS commun** du plugin. Voir en priorité :

- **docs/FRONT_CSS.md** – variables `--me5rine-lab-*`, boutons, cartes, titres, dashboard, formulaires
- **docs/CSS_RULES.md** – champs formulaire, labels, inputs, selects
- **docs/CSS_SYSTEM.md** – structure des conteneurs (dashboard, form-block, etc.)

## Classes utilisées (système commun)

Collections applique notamment : `me5rine-lab-dashboard`, `me5rine-lab-title-large`, `me5rine-lab-subtitle`, `me5rine-lab-dashboard-header`, `me5rine-lab-dashboard-header-actions`, `me5rine-lab-form-button`, `me5rine-lab-form-button-secondary`, `me5rine-lab-form-button-remove`, `me5rine-lab-form-block`, `me5rine-lab-form-field`, `me5rine-lab-form-label`, `me5rine-lab-form-input`, `me5rine-lab-form-select`, `me5rine-lab-form-message`, `me5rine-lab-form-message-warning`, `me5rine-lab-form-hint`, `me5rine-lab-state-message`, `me5rine-lab-card`, `me5rine-lab-card-name`, `me5rine-lab-card-meta`, `me5rine-lab-card-actions`, `me5rine-lab-sr-only`. Layout vue collection : `pokehub-collection-view-header-main` (groupe titre à côté des actions).

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

**Inclure directement dans le thème** : le plugin fournit `assets/theme/poke-hub-collections-theme.css`, à enqueue après `poke-hub-collections-front` (dépendance), ou à copier dans le thème. Ce fichier ne contient que les variables à surcharger.

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
| **Disponible à l'échange** | Orange (notice warning) | `--admin-lab-color-notice-warning` |
| **Manquant** | Gris (comme actuellement) | `--me5rine-lab-border` |

Aucune couleur dédiée : tout repose sur les variables notice. Pour harmoniser, surchargez-les dans le thème (comme pour les notices admin). Classes layout : `.pokehub-collection-legend`, `.pokehub-collection-legend-item`, `.pokehub-collection-legend-dot`, `.pokehub-legend-owned`, `.pokehub-legend-for-trade`, `.pokehub-legend-missing`. Tuiles : `data-status="owned"|"for_trade"|"missing"` et `.pokehub-collection-tile-status`.

## Paramètres adaptatifs (création / édition)

Pour les **catégories spécifiques** (Gigantamax, Dynamax, Costume, etc.), le bloc « Contenu à afficher » (Méga, Gigantamax, Dynamax, costumes) est masqué et remplacé par un message. Classes utilisées :

- `.pokehub-collections-options-additive` — bloc des options « en plus » (masqué si type spécifique).
- `.pokehub-collections-options-specific-hint` — message « Cette collection n’affiche que ce type ».
- `.pokehub-collections-options-additive.is-hidden`, `.pokehub-collections-options-specific-hint.is-hidden` — affichage conditionnel (avec `.is-hidden { display: none }`).

## Style inline (cartes uniquement)

Quand une image de fond est fournie (personnalisée ou via le filtre), elle est appliquée en **inline** sur `.pokehub-collections-card-bg` (background-image, background-size: cover, background-position: center). Sans image, le dégradé des variables CSS est utilisé. La bannière « collections anonymes » est masquée via la classe `.is-hidden`.
