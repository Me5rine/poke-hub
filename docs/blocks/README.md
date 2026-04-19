# 📦 Module Blocs Gutenberg - Poké HUB

Index de la documentation du module Blocs et liste des blocs disponibles.

## 📚 Documentation

| Fichier | Description |
|--------|-------------|
| **[README.md](./README.md)** | Ce fichier — index et liste des blocs |
| **[ARCHITECTURE.md](./ARCHITECTURE.md)** | Architecture, structure des fichiers, règles d’organisation |
| **[BLOCK_TYPES.md](./BLOCK_TYPES.md)** | Types de blocs (PHP dynamique vs JavaScript/React) |
| **[QUICK_START.md](./QUICK_START.md)** | Créer un nouveau bloc (PHP ou JS) |
| **[BLOCK_STYLES_AND_BEHAVIOR.md](./BLOCK_STYLES_AND_BEHAVIOR.md)** | Titres unifiés (Field Research), CSS, bonbons / New Pokémon |

Voir aussi :
- **[BONUS_SOURCE_AND_BLOCKS.md](../BONUS_SOURCE_AND_BLOCKS.md)** — Bonus : source de vérité (site principal), bloc et metabox (module Blocks uniquement)
- **[quests/README.md](../quests/README.md)** — Module Quêtes : menu Quêtes, gestion des ensembles et catégories (indépendant d’Events)

À la racine de `docs/` :
- **[CONTENT_BLOCKS.md](../CONTENT_BLOCKS.md)** — Utilisation des blocs (attributs, exemples, CSS)
- **[BLOCKS_TROUBLESHOOTING.md](../BLOCKS_TROUBLESHOOTING.md)** — Dépannage
- **[BLOCKS_MODULE_MIGRATION.md](../BLOCKS_MODULE_MIGRATION.md)** — Migration depuis l’ancien système

## 📋 Liste des blocs

Tous les blocs sont dans la catégorie **Poké HUB** dans l’éditeur. Ils sont enregistrés dès que les modules indiqués dans `requires` sont actifs. La sauvegarde et la lecture se font dans les tables de contenu (scope `content_source`) : **même préfixe** que les tables Pokémon (Réglages > Sources > Pokémon table prefix (remote)) — une seule base pour les Pokémon et tous les contenus (quêtes, bonus, habitats, œufs, etc.).

### Convention IDs Pokémon + genres

Pour les metaboxes/blocs avec multi-sélection Pokémon, les valeurs peuvent être postées en token :
- `123` (standard)
- `123|male` / `123|female` (genre forcé)

Le backend normalise ces valeurs avec `pokehub_parse_post_pokemon_multiselect_tokens_with_genders()` (et, selon le flux, `pokehub_content_normalize_pokemon_ids_with_genders()`), puis conserve `pokemon_genders` de façon cohérente.

Cette convention est appliquée aux principaux flux contenu : GO Pass, Day Pokémon Hours (et featured/spotlight générés), Eggs, Collection Challenges, Special Research, New Pokémon, Habitats, Field Research, Wild Pokémon, Special Events, Event Quests.

| Bloc | Nom (éditeur) | Modules requis | Description |
|------|----------------|----------------|-------------|
| `pokehub/event-dates` | Event Dates | events | Dates de début/fin avec feux verts/rouges |
| `pokehub/event-quests` | Event Quests | events | Quêtes et récompenses du post. Gestion des données : **Poké HUB → Quêtes** (module Quêtes) ou metabox sur l’article (Events). Le bloc ne dépend pas du module Quêtes. |
| `pokehub/bonus` | Bonus | **Aucun** | Cartes de bonus (auto ou liste d’IDs). Tout (helpers + metabox) est chargé par le module Blocks ; aucune dépendance au module Bonus. Types de bonus : site principal (local ou distant selon préfixe Pokémon). |
| `pokehub/go-pass` | Pass GO | events | Affiche un Pass GO (résumé ou grille complète) lié au contenu courant via la table `go_pass_host_links` ; configuration depuis la metabox « GO Pass (block) ». |
| `pokehub/wild-pokemon` | Wild Pokémon | events | Liste des Pokémon dans la nature (shiny, rare, régional) |
| `pokehub/habitats` | Habitats | events | Habitats avec Pokémon et horaires |
| `pokehub/day-pokemon-hours` | Day Pokémon Hours | events | Pokémon par jour avec horaires (ex. featured hours) |
| `pokehub/new-pokemon-evolutions` | New Pokémon - Evolution Lines | events | Lignées d’évolution et conditions |
| `pokehub/collection-challenges` | Collection Challenges | events | Défis de collection (tables de contenu) |
| `pokehub/special-research` | Special Research | events | Études ponctuelles / spéciales / magistrales |
| `pokehub/eggs` | Pokémon Eggs | events | Œufs par type (2 km, 5 km, etc.) et Pokémon ; post ou pool global |

## 🎯 Dépendances

L’enregistrement est géré dans `modules/blocks/functions/blocks-register.php` : chaque bloc déclare un tableau `requires` (souvent uniquement `events`). Si un module requis est désactivé, le bloc n’apparaît pas dans l’éditeur. Les données sont stockées dans les tables de contenu (même préfixe que les tables Pokémon — Réglages > Sources), ce qui permet d’utiliser les blocs en mode remote sans activer le module Pokémon.

## 🔗 Où est le code ?

- **Définition des blocs** : `modules/blocks/blocks/{nom-du-bloc}/` (block.json, index.js, render.php)
- **Rendu / données** : selon le bloc — events, bonus, pokemon, ou helpers dans `modules/blocks/functions/`
- **Meta boxes** : `modules/blocks/admin/` (GO Pass, Collection Challenges, Études spéciales, etc.). La metabox **GO Pass** (`blocks-go-pass-metabox.php`) utilise Select2 + **admin-ajax** (`pokehub_go_pass_metabox_search`, `pokehub_go_pass_metabox_create_and_link` dans `blocks-admin-ajax.php`) et le script `assets/js/pokehub-go-pass-metabox-admin.js`. La metabox **Bonus** est chargée **uniquement** par le module Blocks (`modules/bonus/admin/bonus-metabox.php`), ainsi que les helpers bonus — le bloc Bonus ne dépend pas du module Bonus. La metabox **Eggs** est dans `modules/eggs/admin/eggs-metabox.php` et est aussi chargée par Blocks lorsque le module Eggs est inactif.

Pour la source de vérité des types de bonus (site principal, local/distant), le rendu SVG/raster et les **variables CSS des vignettes** (`--pokehub-bonus-icon-*` dans `poke-hub-bonus-front.css`), voir **[BONUS_SOURCE_AND_BLOCKS.md](../BONUS_SOURCE_AND_BLOCKS.md)** et **[POKEHUB_CSS_CLASSES.md](../POKEHUB_CSS_CLASSES.md)**.

Pour le détail des attributs et exemples d’utilisation, voir **[CONTENT_BLOCKS.md](../CONTENT_BLOCKS.md)**.

---

*Index de la documentation : [README du dossier docs](../README.md) · [Charte rédactionnelle](../REDACTION.md)*
