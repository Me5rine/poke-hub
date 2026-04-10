# Documentation Poké HUB

Ce dossier contient toute la documentation du plugin Poké HUB.

## 📌 Changements récents (architecture)

- **Blocs — titres & CSS** : les titres principaux (`h2.pokehub-block-title`) sont unifiés sur le style **Field Research** (rouge `#b91c1c`, gauche, majuscules, `letter-spacing`). Règles dans `assets/css/poke-hub-blocks-front.css` ; même logique dupliquée dans `poke-hub-events-front.css` ; chargement éditeur via `enqueue_block_editor_assets`. Voir [blocks/BLOCK_STYLES_AND_BEHAVIOR.md](./blocks/BLOCK_STYLES_AND_BEHAVIOR.md).
- **Bloc New Pokémon / évolutions** : pastilles de types (icône stable, extension à droite) ; **bonbons** affichés selon la **famille** (racine de lignée + exceptions bébés Togepi / Tyrogue / Toxel + liste Pichu, Élekid, etc.). Filtre `pokehub_pokemon_slug_uses_parent_line_candy`. Détail : [blocks/BLOCK_STYLES_AND_BEHAVIOR.md](./blocks/BLOCK_STYLES_AND_BEHAVIOR.md).
- **Images / bonbons** : conventions raster (WebP → PNG → JPG), types en SVG depuis les sources — voir [POKEMON_IMAGES.md](./POKEMON_IMAGES.md) et l’onglet Sources en admin.
- **SVG inline (globaux)** : moteur `pokehub-inline-svg.php`, types Pokémon `pokehub-pokemon-type-icon.php`, bonus via `poke_hub_render_bonus_asset_markup()` — voir [INLINE_SVG.md](./INLINE_SVG.md).
- **Catalogue bonus** : `title_en` / `title_fr`, un seul `slug` (fichier image), SVG prioritaire ; **vignettes** : variables CSS `--pokehub-bonus-icon-*` (thème, pas d’option admin) — voir [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md).

- **Préfixe Pokémon et contenus** : le réglage **Réglages > Poké HUB > Sources > Pokémon table prefix (remote)** sert aux **tables Pokémon** et à **tous les contenus** qui en découlent (quêtes, bonus, habitats, œufs, Pokémon sauvages, field research, nouveaux Pokémon, special research, collection challenges). Une seule base pour tout ; sur les sites distants, renseigner le préfixe du site principal.
- **Tables de contenu** : les tables (content_eggs, content_quests, content_bonus, etc.) utilisent le scope **`content_source`** — même préfixe que la source Pokémon. Blocs utilisables en mode remote.
- **Blocs** : plus de dépendance au module Pokémon pour l’enregistrement ; la plupart ne requièrent que le module **Events**. Voir [blocks/README.md](./blocks/README.md).
- **Module Quêtes** : module autonome avec menu **Quêtes** (Poké HUB > Quêtes), sous-onglets **Quêtes** et **Catégories de quêtes**. Gestion des ensembles de quêtes (pool global ou association à un contenu local : article / événement). Le module **Events** n’enregistre plus aucun menu quêtes (Quests / Quest Groups supprimés). Voir [quests/README.md](./quests/README.md).
- **Enregistrement des modules** : une seule source dans **`includes/settings/settings-modules.php`** (`poke_hub_get_modules_config()`). Liste, libellés et ordre d’affichage (Réglages > General) en découlent.
- **Bonus (bloc + metabox)** : le bloc Bonus et la metabox « Bonus de l’événement » ne dépendent **pas** du module Bonus. Ils sont chargés uniquement par le module **Blocks** ; les types de bonus viennent du site principal (table locale ou distante selon le préfixe Pokémon). Voir [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md).
- **Metaboxes** : la metabox Eggs est chargée par le module Blocks lorsque le module Eggs est inactif ; la metabox Collection Challenges et la metabox Bonus sont gérées par le module Blocks.
- **Form variants (formes)** : la **catégorie** d’une forme est choisie via un **menu déroulant** en admin (Normal, Costume / Event, Clone, Regional, Mega, Alola, Galar, Hisui, Paldea, Shadow, Purified). Si la forme a la catégorie « Costume / Event », les Pokémon avec cette forme sont considérés costumés/événement automatiquement. Voir [COLLECTIONS_AND_FORMS_CATEGORIES.md](./COLLECTIONS_AND_FORMS_CATEGORIES.md).
- **Form mapping supprimé** : la table et l’admin des form mappings ont été retirés ; la **catégorie de la form variant** est la source de vérité (plus de mapping Game Master → forme).
- **Association Pokémon ↔ événements** : lorsqu’un Pokémon est marqué **événement ou costumé** (case à cocher sur la fiche ou forme avec catégorie « Costume / Event »), une section **« Event Association »** apparaît en édition : on peut associer un ou plusieurs événements (même sélecteur que pour les fonds et les formes). Les liaisons sont stockées dans la table **`pokemon_pokemon_events`** ; helper : `poke_hub_get_pokemon_events($pokemon_id)`. Voir [COLLECTIONS_AND_FORMS_CATEGORIES.md](./COLLECTIONS_AND_FORMS_CATEGORIES.md).
- **User Profiles Source** : l'option **Réglages > Poké HUB > Sources > User Profiles Source** (URL de base des liens profils) n'est affichée et utilisable **que si le module User Profiles est actif**. Elle sert aux codes amis et aux liens vers les profils (sites partageant une base de données). Voir [user-profiles/README_USER_PROFILES.md](./user-profiles/README_USER_PROFILES.md#réglages).
- **Import dates de sortie Pokekalos** : menu **Poké HUB > Outils temporaires** (temporaire) pour importer les dates de sortie (normal, shiny, shadow, etc.) depuis les fiches Pokédex Pokémon GO de pokekalos.fr. Deux passes : formes de base (`{slug}-{dex}.html`) puis formes Méga (`{slug}-{dex}m.html`). Dates stockées en **YYYY-MM-DD** ; option « ignorer les existants », limite espèces, dry-run. Script CLI : `scripts/import-pokekalos-release-dates.php`. Voir [pokemon/POKEKALOS_RELEASE_DATES.md](./pokemon/POKEKALOS_RELEASE_DATES.md).
- **Biomes (Pokémon GO)** : onglet admin **Pokémon → Biomes** (CRUD, images de fond multiples, espèces) ; liaison N–N via `pokemon_biome_pokemon_links` ; sur la fiche Pokémon, champ biomes dans la section **Regional availability** (sous-partie *Biomes*, puis *Regional*). Détail : [pokemon/BIOMES.md](./pokemon/BIOMES.md).

## 📁 Structure

### Documentation Générale

La documentation générale se trouve à la racine du dossier `docs/` :

- **[TRANSLATION.md](./TRANSLATION.md)** - Traductions (i18n) : text domain `poke-hub`, langue de base anglaise, fichiers .po/.mo
- **[ORGANISATION.md](./ORGANISATION.md)** - Organisation générale du plugin
- **[BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md)** - Bonus : source de vérité (site principal), bloc et metabox (module Blocks uniquement), schéma catalogue, SVG / raster, thème CSS (`--pokehub-bonus-icon-*`)
- **[INLINE_SVG.md](./INLINE_SVG.md)** - SVG inline depuis le bucket (helpers globaux, types Pokémon, bonus, filtres)
- **[THEME_INTEGRATION.md](./THEME_INTEGRATION.md)** - Guide d'intégration dans le thème WordPress
- **[PLUGIN_INTEGRATION.md](./PLUGIN_INTEGRATION.md)** - Guide d'intégration pour utiliser les classes CSS dans d'autres plugins
- **[CSS_SYSTEM.md](./CSS_SYSTEM.md)** - Documentation complète du système de classes CSS
- **[CSS_RULES.md](./CSS_RULES.md)** - Règles CSS complètes pour les formulaires
- **[FRONT_CSS.md](./FRONT_CSS.md)** - Règles CSS unifiées pour les éléments front-end
- **[ADMIN_CSS.md](./ADMIN_CSS.md)** - Règles CSS unifiées pour l'administration
- **[TABLE_CSS.md](./TABLE_CSS.md)** - Règles CSS pour les tableaux
- **[PLUGIN_COPY_GUIDE.md](./PLUGIN_COPY_GUIDE.md)** - Guide : Fichiers à copier pour réutiliser la structure
- **[SELECT2_INITIALIZATION.md](./SELECT2_INITIALIZATION.md)** - Documentation de l'initialisation Select2
- **[POKEMON_IMAGES.md](./POKEMON_IMAGES.md)** - Gestion des images Pokémon

### Documentation par Module

La documentation spécifique à chaque module se trouve dans des sous-dossiers :

- **[blocks/](./blocks/)** - Documentation du module Blocks
  - [README.md](./blocks/README.md) - Index des blocs et liste des blocs disponibles
  - [ARCHITECTURE.md](./blocks/ARCHITECTURE.md) - Architecture et organisation des blocs Gutenberg
  - [BLOCK_TYPES.md](./blocks/BLOCK_TYPES.md) - Types de blocs (PHP-only vs JS/React)
  - [QUICK_START.md](./blocks/QUICK_START.md) - Guide de création rapide de blocs
  - [BLOCK_STYLES_AND_BEHAVIOR.md](./blocks/BLOCK_STYLES_AND_BEHAVIOR.md) - Titres de blocs, CSS front, bonbons (New Pokémon)
  - Voir aussi [CONTENT_BLOCKS.md](./CONTENT_BLOCKS.md) - Utilisation des blocs (attributs, exemples, CSS)

- **[events/](./events/)** - Documentation du module Events
  - [README-ROUTING.md](./events/README-ROUTING.md) - Documentation du système de routing
  - [INTEGRATION-ELEMENTOR.md](./events/INTEGRATION-ELEMENTOR.md) - Intégration avec Elementor
  - [EVENEMENTS-DISTANTS.md](./events/EVENEMENTS-DISTANTS.md) - Gestion des événements distants

- **[pokemon/](./pokemon/)** - Documentation du module Pokémon
  - [BIOMES.md](./pokemon/BIOMES.md) - Biomes (tables, admin, fiche Pokémon, helpers, filtres)
  - [README-REGIONAL-AUTO-CONFIG.md](./pokemon/README-REGIONAL-AUTO-CONFIG.md) - Configuration automatique des Pokémon régionaux
  - [POKEKALOS_RELEASE_DATES.md](./pokemon/POKEKALOS_RELEASE_DATES.md) - Import des dates de sortie depuis Pokekalos (admin Outils temporaires + CLI)

- **[quests/](./quests/)** - Documentation du module Quêtes
  - [README.md](./quests/README.md) - Menu Quêtes, onglets Quêtes / Catégories de quêtes, association contenu local/global, tables de contenu

- **[user-profiles/](./user-profiles/)** - Documentation du module User Profiles
  - [README_USER_PROFILES.md](./user-profiles/README_USER_PROFILES.md) - Documentation principale
  - [SHORTCODE_USAGE.md](./user-profiles/SHORTCODE_USAGE.md) - Documentation du shortcode
  - [ULTIMATE_MEMBER_SETUP.md](./user-profiles/ULTIMATE_MEMBER_SETUP.md) - Configuration Ultimate Member
  - [CUSTOMIZATION.md](./user-profiles/CUSTOMIZATION.md) - Personnalisation des listes
  - [README_DATA_CENTRALIZATION.md](./user-profiles/README_DATA_CENTRALIZATION.md) - Architecture de centralisation
  - [SYNCHRONIZATION.md](./user-profiles/SYNCHRONIZATION.md) - Synchronisation des données
  - [COUNTRIES-STORAGE-FORMAT.md](./user-profiles/COUNTRIES-STORAGE-FORMAT.md) - Format de stockage des pays
  - [FRIEND_CODES_PUBLIC_AND_IP.md](./user-profiles/FRIEND_CODES_PUBLIC_AND_IP.md) - Codes amis publics, IP, visiteurs non connectés

## 📖 Utilisation

### Pour les développeurs

**Pour intégrer les styles dans votre thème WordPress :**
1. Consultez [THEME_INTEGRATION.md](./THEME_INTEGRATION.md) - Guide complet étape par étape

**Pour utiliser les classes dans votre plugin/thème :**
1. Lisez [PLUGIN_INTEGRATION.md](./PLUGIN_INTEGRATION.md) pour comprendre la structure HTML
2. Consultez [CSS_SYSTEM.md](./CSS_SYSTEM.md) pour la liste complète des classes
3. Copiez le contenu de [CSS_RULES.md](./CSS_RULES.md) dans votre thème pour les formulaires
4. Copiez le contenu de [FRONT_CSS.md](./FRONT_CSS.md) dans votre thème pour les éléments front-end

### Pour la configuration des modules

Consultez la documentation dans le dossier correspondant au module (ex: `blocks/`, `events/`, `user-profiles/`, etc.).

## 🔗 Liens rapides

- [Traductions (i18n)](./TRANSLATION.md)
- [Organisation du plugin](./ORGANISATION.md)
- [Bonus : source de vérité et bloc](./BONUS_SOURCE_AND_BLOCKS.md)
- [Guide d'intégration dans le thème](./THEME_INTEGRATION.md)
- [Système CSS](./CSS_SYSTEM.md)
- [Module Blocks](./blocks/)
- [Module Events](./events/)
- [Module Quêtes](./quests/)
- [Module User Profiles](./user-profiles/)
- [Module Pokémon](./pokemon/)

