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
- **Récompenses non-Pokémon :** une seule ligne → badge **`×quantité`** réelle ; plusieurs lignes → badge **`Other × M`** (nombre de lignes) + `title` explicatif.
- La zone d’aperçu est forcée sur **une seule ligne** pour éviter les variations de hauteur entre quêtes.
- Sur petit écran, l’aperçu reste mono-ligne avec défilement horizontal si besoin.

### Récompenses non-Pokémon (détail déplié)

- Si une **icône / image** est affichée à côté du libellé (poussière, XP, objet, ressources avec visuel), le texte affiché est **`×quantité`** uniquement (pas de répétition du nom de la ressource à côté de l’icône).

### Règles CP (détail de récompense)

- Deux pastilles **`CP min`** puis **`CP max`** (ordre gauche → droite), chacune en **colonne** : label au-dessus, valeur en dessous.
- Le **min** est visuellement atténué (fond / texte plus clairs) pour mettre le max en avant.
- Libellés courts en **majuscules** (`CP MIN` / `CP MAX`) ; le détail niveau 15 reste dans les attributs `title`.

### Fichiers concernés

- `modules/blocks/functions/blocks-field-research.php` (rendu HTML + compteurs d’aperçu)
- `assets/css/poke-hub-blocks-front.css` (styles front communs)
- `assets/css/poke-hub-events-front.css` (styles doublon pour contextes Events)

---

## Bloc **New Pokémon – Evolution Lines** (`pokehub/new-pokemon-evolutions`)

### Fichiers

- Rendu : `modules/blocks/blocks/new-pokemon-evolutions/render.php`
- Styles : `assets/css/poke-hub-new-pokemon-evolutions-front.css` (lignées, cartes, **pastilles de types** interactives)

### Sélection des lignes d’évolution (`in` / `out`)

Les helpers **`pokehub_get_pokemon_evolutions_in`** et **`pokehub_get_pokemon_evolutions_out`** joignent la table **`pokemon`** sur la **cible** lorsque pertinent, avec **`target_form_variant_id`** aligné sur **`COALESCE(form_variant_id, 0)`** de la ligne cible, pour coller au comportement admin et éviter les arêtes où la variante de forme ne correspond pas à une fiche existante.

Les lignes **`pokemon.slug`** se terminant par le suffixe réservé **`-family`** (placeholders métier créés à l’import GM pour certaines espèces) sont **exclues** au niveau des jointures : aucune évolution entrante ou sortante n’est affichée si la base ou la cible jointe est un tel placeholder — cela évite les doublons avec la « vraie » forme canonique dans la lignée (détail : [../pokemon/GAME_MASTER_IMPORT.md](../pokemon/GAME_MASTER_IMPORT.md), § **Hors import**).

Si **`extra.evolution_source === 'game_master'`** et que le filtre **`pokehub_filter_evolutions_by_game_master_proto`** renvoie **`true`** (défaut), les évolutions ainsi marquées ne sont gardées que si **`pokehub_np_evo_game_master_row_keeps_relation()`** trouve encore une correspondance entre les protos **`base_id_proto`** et **`target_id_proto`** en **`pokemon_evolutions.extra`** et **`extra.pokemon_id_proto`** sur les fiches **`pokemon`** base et cible. Le champ **`target_form_proto`** est stocké lors du PASS 3 pour tracer la variante GM ; il n’est **pas** utilisé dans ce contrôle proto (la cohérence de forme repose sur la jointure SQL **`target_form_variant_id`** décrite ci-dessus).

```php
// Désactiver le filtrage proto (cas maintenance / jeu de données mixte uniquement).
add_filter( 'pokehub_filter_evolutions_by_game_master_proto', '__return_false' );
```

Les **types** affichés par pastille viennent de **`pokehub_get_pokemon_types_for_display()`** (liaisons **`pokemon_type_links`** / taxonomie) : des liens périmés sur une ligne donnée se reflètent directement dans l’UI jusqu’à correction des données ou resync.

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
