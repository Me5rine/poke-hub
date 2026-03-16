# Collections – CSS et thème

Le module Collections **réutilise au maximum les classes et variables du système CSS commun** du plugin. Voir en priorité :

- **docs/FRONT_CSS.md** – variables `--me5rine-lab-*`, boutons, cartes, titres, dashboard, formulaires
- **docs/CSS_RULES.md** – champs formulaire, labels, inputs, selects
- **docs/CSS_SYSTEM.md** – structure des conteneurs (dashboard, form-block, etc.)

## Classes utilisées (système commun)

Collections applique notamment : `me5rine-lab-dashboard`, `me5rine-lab-title-large`, `me5rine-lab-subtitle`, `me5rine-lab-dashboard-header`, `me5rine-lab-form-button`, `me5rine-lab-form-button-secondary`, `me5rine-lab-form-button-remove`, `me5rine-lab-form-block`, `me5rine-lab-form-field`, `me5rine-lab-form-label`, `me5rine-lab-form-input`, `me5rine-lab-form-select`, `me5rine-lab-form-message`, `me5rine-lab-form-message-warning`, `me5rine-lab-state-message`, `me5rine-lab-card`, `me5rine-lab-card-name`, `me5rine-lab-card-meta`, `me5rine-lab-card-actions`, `me5rine-lab-sr-only`.

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

Exemple dans le thème pour raccorder les cartes à la couleur primaire :

```css
:root {
    --pokehub-collections-card-gradient-start: var(--me5rine-lab-primary);
    --pokehub-collections-card-gradient-mid: var(--me5rine-lab-secondary);
    --pokehub-collections-card-gradient-end: var(--me5rine-lab-secondary);
}
```

## Image de fond par type de collection

Vous pouvez définir **une image de fond par catégorie** (shiny, costume, background, etc.) pour les cartes de la liste des collections, via le filtre PHP **`poke_hub_collections_card_background_image_url`**.

**Paramètres :** `( string $url, string $category )`  
**Retour :** l’URL de l’image à utiliser pour cette catégorie (chaîne vide = dégradé par défaut).

Exemple dans le thème (functions.php ou fichier dédié) :

```php
add_filter('poke_hub_collections_card_background_image_url', function ($url, $category) {
    $images = [
        'shiny'           => 'https://exemple.com/images/collections-shiny.jpg',
        'costume'         => 'https://exemple.com/images/collections-costume.jpg',
        'costume_shiny'   => 'https://exemple.com/images/collections-costume-shiny.jpg',
        'background'      => 'https://exemple.com/images/collections-background.jpg',
        'background_shiny'=> 'https://exemple.com/images/collections-background-shiny.jpg',
        'perfect_4'       => 'https://exemple.com/images/collections-perfect.jpg',
        'lucky'           => 'https://exemple.com/images/collections-lucky.jpg',
        'shadow'          => 'https://exemple.com/images/collections-shadow.jpg',
        'purified'        => 'https://exemple.com/images/collections-purified.jpg',
        'gigantamax'     => 'https://exemple.com/images/collections-gigantamax.jpg',
        'dynamax'        => 'https://exemple.com/images/collections-dynamax.jpg',
        'custom'         => 'https://exemple.com/images/collections-custom.jpg',
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

## Légende des tuiles et couleurs de statut

En vue collection (mode tuiles), une **légende** indique le sens du clic sur les tuiles (possédé / à échanger / manquant). Les couleurs réutilisent les variables du thème (notices) pour rester cohérentes :

| Élément | Variable / classe | Rôle |
|--------|-------------------|------|
| Vert (possédé) | `--admin-lab-color-notice-sucess-border` | Pastille et bordure tuile « possédé » |
| Orange (à échanger) | `--admin-lab-color-notice-warning` | Pastille et bordure tuile « à l’échange » |
| Gris (manquant) | `--admin-lab-color-borders` ou `--me5rine-lab-border` | Pastille « manquant » |

Classes : `.pokehub-collection-legend`, `.pokehub-collection-legend-item`, `.pokehub-collection-legend-dot`, `.pokehub-legend-owned`, `.pokehub-legend-for-trade`, `.pokehub-legend-missing`. Les tuiles utilisent `data-status="owned"|"for_trade"|"missing"` et `.pokehub-collection-tile-status`.

## Paramètres adaptatifs (création / édition)

Pour les **catégories spécifiques** (Gigantamax, Dynamax, Costume, etc.), le bloc « Contenu à afficher » (Méga, Gigantamax, Dynamax, costumes) est masqué et remplacé par un message. Classes utilisées :

- `.pokehub-collections-options-additive` — bloc des options « en plus » (masqué si type spécifique).
- `.pokehub-collections-options-specific-hint` — message « Cette collection n’affiche que ce type ».
- `.pokehub-collections-options-additive.is-hidden`, `.pokehub-collections-options-specific-hint.is-hidden` — affichage conditionnel (avec `.is-hidden { display: none }`).

## Style inline (cartes uniquement)

Quand une image de fond est fournie par le filtre ci‑dessus, elle est appliquée en **inline** sur `.pokehub-collections-card-bg` (background-image, background-size: cover, background-position: center). Sans image, le dégradé des variables CSS est utilisé. La bannière « collections anonymes » est masquée via la classe `.is-hidden`.
