# Titres de blocs, CSS front et comportements (New Pokémon / bonbons)

Ce document décrit les règles **actuelles** pour l’apparence des blocs Gutenberg Poké HUB et la logique **bonbons** sur le bloc **New Pokémon – Evolution Lines**.

## Titres principaux des blocs (référence Field Research)

Tous les blocs qui affichent un titre de section avec la classe **`pokehub-block-title`** (souvent un `<h2>`) partagent le **même rendu** que le bloc Field Research (`pokehub/event-quests`) :

| Propriété | Valeur |
|-----------|--------|
| Couleur | `#b91c1c` |
| Alignement | gauche |
| Taille | `1.5rem`, graisse `700` |
| Casing | `uppercase` |
| Espacement des lettres | `0.05em` |
| Interligne | `1.25` |

### Fichiers CSS

- **Source principale (front + éditeur)** : `assets/css/poke-hub-blocks-front.css`  
  Sélecteurs :
  - `[class*="wp-block-pokehub-"] .pokehub-block-title` — tout bloc enregistré sous le namespace `pokehub/*` ;
  - `[class*="pokehub-"][class*="block-wrapper"] .pokehub-block-title` — wrappers `*-block-wrapper` (contenus existants, aperçus).
- **Doublon pour pages événements** : `assets/css/poke-hub-events-front.css` (mêmes règles, pour les sites qui chargent surtout ce fichier).  
  Si le module **Blocks** est actif, ce fichier est enqueued **après** `poke-hub-blocks-front.css` lorsque le handle `pokehub-blocks-front-style` est déjà enregistré (`modules/events/events.php`).

### Éditeur Gutenberg

Pour que l’**aperçu** des blocs dynamiques (ServerSideRender) affiche les mêmes titres qu’en front, `poke-hub-blocks-front.css` (+ `global-colors.css`) est chargé sur **`enqueue_block_editor_assets`** (`modules/blocks/blocks.php`).

### Ordre de chargement (module Blocks)

`pokehub-blocks-front-style` est enregistré **avant** les feuilles par bloc (new-pokemon, bonus, collection-challenges, special-research, eggs) qui déclarent une **dépendance** sur ce handle, afin que la base « titres » soit toujours présente.

### Nouveau bloc `pokehub/*`

Pour un nouveau bloc : utiliser un wrapper dont la classe contient **`block-wrapper`** (ex. `pokehub-mon-bloc-block-wrapper`) et un titre `<h2 class="pokehub-block-title">`. Aucune liste manuelle de sélecteurs n’est nécessaire si ces conventions sont respectées.

---

## Bloc **Event Quests** (`pokehub/event-quests`) - aperçu compact

Le rendu replié des quêtes Field Research suit des contraintes visuelles pour garder des cartes homogènes et lisibles, même avec beaucoup de récompenses.

### Règles d’aperçu (mode replié)

- L’aperçu Pokémon affiche **3 mini-tuiles maximum**.
- Si plus de 3 Pokémon sont présents, un badge **`+N`** indique les Pokémon supplémentaires non affichés.
- Le badge **`×M`** continue d’indiquer le nombre de lignes de récompenses non-Pokémon.
- La zone d’aperçu est forcée sur **une seule ligne** pour éviter les variations de hauteur entre quêtes.
- Sur petit écran, l’aperçu reste mono-ligne avec défilement horizontal si besoin.

### Règles CP (détail de récompense)

- Les libellés CP sont compacts : **`CP max`** et **`CP min`**.
- Les pastilles CP sont alignées en ligne (label + valeur) pour limiter la largeur.
- Le libellé complet reste disponible via `title` (ex. « Maximum CP at level 15 »), pour conserver le contexte sans casser la mise en page.

### Fichiers concernés

- `modules/blocks/functions/blocks-field-research.php` (rendu HTML + compteurs d’aperçu)
- `assets/css/poke-hub-blocks-front.css` (styles front communs)
- `assets/css/poke-hub-events-front.css` (styles doublon pour contextes Events)

---

## Bloc **New Pokémon – Evolution Lines** (`pokehub/new-pokemon-evolutions`)

### Fichiers

- Rendu : `modules/blocks/blocks/new-pokemon-evolutions/render.php`
- Styles : `assets/css/poke-hub-new-pokemon-evolutions-front.css` (lignées, cartes, **pastilles de types** interactives)

### Pastilles de types

Les types sont affichés en pastilles rondes avec **icône SVG** (sources / types) ; au survol ou au focus clavier, la pastille s’étend vers la **droite** avec le libellé, sans déplacer l’icône (colonne icône = diamètre fixe).

### Affichage des **bonbons** d’évolution

L’image et le libellé d’accessibilité du bonbon ne doivent **pas** utiliser l’espèce immédiatement avant l’évolution dans la chaîne (ex. Mélofée entre deux évolutions), mais l’espèce qui **porte la famille de bonbons** dans Pokémon GO.

#### Règle implémentée

1. À partir du `base_pokemon_id` de la ligne d’évolution, on remonte à la **racine** de la lignée (même logique que `pokehub_find_base_pokemon()` : Pokémon qui n’est cible d’aucune évolution entrante).
2. Si la racine est un **bébé** dont les bonbons portent le nom de la **première évolution** (ex. Pichu → famille Pikachu), on utilise l’ID de cette **première cible** d’évolution pour `pokehub_render_pokemon_candy_reward_html()`.
3. **Exceptions** : Togepi, Tyrogue, Toxel — le bonbon porte le nom du **bébé** ; on ne décale pas vers la cible. Slugs reconnus : `togepi`, `tyrogue`, `toxel`.

#### Filtre WordPress

Pour forcer le comportement « bonbon = évolution » ou l’inverse sur un slug précis :

```php
add_filter('pokehub_pokemon_slug_uses_parent_line_candy', function ($forced, $slug) {
    if ($slug === 'mon-slug') {
        return true;  // utiliser la première évolution pour l’asset bonbon
    }
    if ($slug === 'autre-slug') {
        return false; // rester sur la racine
    }
    return $forced; // null → liste intégrée du plugin
}, 10, 2);
```

Fonctions utiles (définies dans `render.php` du bloc, chargées avec le rendu) :  
`pokehub_pokemon_slug_uses_parent_line_candy()`, `pokehub_resolve_candy_display_pokemon_id()`.

---

## Assets bonbons et images (rappel)

- Fichiers raster bonbons : convention **`{slug}-candy`** et **`{slug}-xl-candy`** ; formats **WebP → PNG → JPG** selon la configuration **Réglages > Poké HUB > Sources** (voir l’interface et [POKEMON_IMAGES.md](../POKEMON_IMAGES.md)).
- Icônes de **types** : SVG par slug dans les sources configurées (pas d’image type en base).

---

## Voir aussi

- [README.md](./README.md) — liste des blocs  
- [CONTENT_BLOCKS.md](../CONTENT_BLOCKS.md) — attributs et utilisation  
- [POKEMON_IMAGES.md](../POKEMON_IMAGES.md) — URLs sprites, fallback, filtres

---

*Index de la documentation : [README du dossier docs](../README.md) · [Charte rédactionnelle](../REDACTION.md)*
