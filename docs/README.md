# Documentation Poké HUB

Ce dossier regroupe la **documentation technique** du plugin (architecture, modules, CSS, données, dépannage). Le **[README à la racine](../README.md)** reste le guide large (installation, configuration générale, historique).

**Contribuer à la doc** : respecter la **[charte rédactionnelle](./REDACTION.md)** (langue, liens, nommage **Poké HUB** / `poke-hub` / `poke_hub_`). **Traductions** : [TRANSLATION.md](./TRANSLATION.md).

Sauf **README.md** (cet index) et **REDACTION.md**, les pages Markdown sous `docs/` et dans `modules/**` se terminent par un **pied de page** renvoyant vers cet index et la charte (généré ou mis à jour via `scripts/harmonize-doc-footers.py`).

## 📌 Changements récents (architecture)

- **Admin — type d’événement (Select2)** : recherche par nom sur tous les selects pertinents (`event[event_type]`, filtre liste `#filter-by-event-type`, metabox article / champs dont le `name` ou l’`id` contient `event_type`). Assets Select2 + `pokehub-special-events-admin.js` aussi sur **`post.php` / `post-new.php`** ; plus d’`wp_add_inline_script` sur le filtre seul. Détail : [events/README.md](./events/README.md#event-type-select2-admin).
- **Outils temporaires (admin)** : page **Poké HUB → Temporary tools** découpée en **onglets** (Pokekalos, heures Fandom, Lundi Max selon modules) ; option **Settings → General → Temporary tools** pour afficher ou masquer le sous-menu (`poke_hub_temporary_tools_enabled`). Documentation : [ADMIN_TEMPORARY_TOOLS.md](./ADMIN_TEMPORARY_TOOLS.md).
- **Événements spéciaux — Pokémon** : la disponibilité **mondiale** (`is_worldwide_override`) n’est proposée en admin que pour les Pokémon **régionaux** en base (`extra.regional.is_regional` via `pokehub_get_all_pokemon_for_select()`). Pas de saisie manuelle des zones sur l’événement ; `region_note` reste géré en arrière-plan pour les données existantes (ex. imports). Détail : [events/README.md](./events/README.md) (section *Événements spéciaux — Pokémon de l’événement*).
- **Cache page Nginx / Redis** : helpers globaux de purge (`poke_hub_purge_nginx_cache`, `poke_hub_purge_module_cache`), suppression complémentaire des fichiers FastCGI (`levels=1:2`), en‑têtes et cron pour les listes `[poke_hub_events]`, purge élargie profil / codes amis. Détail : [CACHE_AND_NGINX_PURGE.md](./CACHE_AND_NGINX_PURGE.md).
- **Admin — formulaires** : après sauvegarde réussie, retour **systématique vers la liste** de la page (Pokémon / quêtes / œufs / bonus / profils, cohérent avec les événements) ; lien **Retour à la liste** homogène et plus visible (`poke_hub_admin_back_to_list_bar()`, styles `pokehub-admin-back-bar` dans `includes/admin-ui.php`). Détail : [ADMIN_FORM_UX.md](./ADMIN_FORM_UX.md).
- **Blocs — titres & CSS** : les titres principaux (`h2.pokehub-block-title`) sont unifiés sur le style **Field Research** (rouge `#b91c1c`, gauche, majuscules, `letter-spacing`). Règles dans `assets/css/poke-hub-blocks-front.css` ; même logique dupliquée dans `poke-hub-events-front.css` ; chargement éditeur via `enqueue_block_editor_assets`. Voir [blocks/BLOCK_STYLES_AND_BEHAVIOR.md](./blocks/BLOCK_STYLES_AND_BEHAVIOR.md).
- **Bloc New Pokémon / évolutions** : pastilles de types (icône stable, extension à droite) ; **bonbons** affichés selon la **famille** (racine de lignée + exceptions bébés Togepi / Tyrogue / Toxel + liste Pichu, Élekid, etc.). Filtre `pokehub_pokemon_slug_uses_parent_line_candy`. Détail : [blocks/BLOCK_STYLES_AND_BEHAVIOR.md](./blocks/BLOCK_STYLES_AND_BEHAVIOR.md).
- **Images / bonbons** : conventions raster (WebP → PNG → JPG), types en SVG depuis les sources — voir [POKEMON_IMAGES.md](./POKEMON_IMAGES.md) et l’onglet Sources en admin.
- **Images items / récompenses quête (XP, Stardust, item)** : chemin dédié **Items** dans Sources (`poke_hub_assets_path_items`), fichiers `slug.webp` puis `slug.png` ; slugs fixes **`xp`** et **`stardust`** ; repli sur le dossier **Objects** ; catalogue items sans URL d’image manuelle — détail : [POKEMON_IMAGES.md](./POKEMON_IMAGES.md#objets-du-catalogue-items-xp-stardust-et-récompenses-de-quête).
- **SVG inline (globaux)** : moteur `pokehub-inline-svg.php`, types Pokémon `pokehub-pokemon-type-icon.php`, bonus via `poke_hub_render_bonus_asset_markup()` — voir [INLINE_SVG.md](./INLINE_SVG.md).
- **Catalogue bonus** : `title_en` / `title_fr`, un seul `slug` (fichier image), SVG prioritaire ; **vignettes** : variables CSS `--pokehub-bonus-icon-*` (thème, pas d’option admin) — voir [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md).

- **Préfixe Pokémon et contenus** : le réglage **Réglages > Poké HUB > Sources > Pokémon table prefix (remote)** sert aux **tables Pokémon** et à **tous les contenus** qui en découlent (quêtes, bonus, habitats, œufs, Pokémon sauvages, field research, nouveaux Pokémon, special research, collection challenges, **boutique avatar**, **stickers en jeu**). Une seule base pour tout ; sur les sites distants, renseigner le préfixe du site principal.
- **Tables de contenu** : les tables (content_eggs, content_quests, content_bonus, **content_shop_avatar**, **content_shop_sticker**, etc.) utilisent le scope **`content_source`** — même préfixe que la source Pokémon. Blocs utilisables en mode remote.
- **Shop (admin + blocs)** : menu **Poké HUB → Shop** — onglets regroupés (Avatar shop : Items / Categories ; In‑game stickers) ; styles `.poke-hub-shop-items-nav*` dans `admin-unified.css` — [ADMIN_CSS.md](./ADMIN_CSS.md), module détaillé [shop-items/README.md](./shop-items/README.md). Blocs **Avatar shop highlights** / **In-game sticker highlights** : accroche (cover + texte) + tuiles carrées + sous-titres ; metaboxes dans `modules/blocks/admin/` — [CONTENT_BLOCKS.md](./CONTENT_BLOCKS.md), [blocks/README.md](./blocks/README.md), [POKEHUB_CSS_CLASSES.md](./POKEHUB_CSS_CLASSES.md).
- **Blocs** : plus de dépendance au module Pokémon pour l’enregistrement ; la plupart ne requièrent que le module **Events**. Voir [blocks/README.md](./blocks/README.md).
- **Module Quêtes** : module autonome avec menu **Quêtes** (Poké HUB > Quêtes), sous-onglets **Quêtes** et **Catégories de quêtes**. Gestion des ensembles de quêtes (pool global ou association à un contenu local : article / événement). Le module **Events** n’enregistre plus aucun menu quêtes (Quests / Quest Groups supprimés). Voir [quests/README.md](./quests/README.md).
- **Enregistrement des modules** : une seule source dans **`includes/settings/settings-modules.php`** (`poke_hub_get_modules_config()`). Liste, libellés et ordre d’affichage (Réglages > General) en découlent.
- **Bonus (bloc + metabox)** : le bloc Bonus et la metabox « Bonus de l’événement » ne dépendent **pas** du module Bonus. Ils sont chargés uniquement par le module **Blocks** ; les types de bonus viennent du site principal (table locale ou distante selon le préfixe Pokémon). Voir [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md).
- **Metaboxes** : la metabox Eggs est chargée par le module Blocks lorsque le module Eggs est inactif ; la metabox Collection Challenges et la metabox Bonus sont gérées par le module Blocks ; les metaboxes **Avatar shop (block)** et **In-game stickers (block)** (création inline d’items, Select2) sont dans le module **Blocks** dès que le **schéma SQL** attendu est présent (helpers chargés depuis `modules/shop-items/includes/`). Le menu **Poké HUB → Shop** et la gestion catalogue complète nécessitent le module **shop-items** actif — [shop-items/README.md](./shop-items/README.md).
- **Form variants (formes)** : la **catégorie** d’une forme est choisie via un **menu déroulant** en admin (Normal, Costume / Event, Clone, Regional, Mega, Alola, Galar, Hisui, Paldea, Shadow, Purified). Si la forme a la catégorie « Costume / Event », les Pokémon avec cette forme sont considérés costumés/événement automatiquement. Voir [COLLECTIONS_AND_FORMS_CATEGORIES.md](./COLLECTIONS_AND_FORMS_CATEGORIES.md).
- **Form mapping supprimé** : la table et l’admin des form mappings ont été retirés ; la **catégorie de la form variant** est la source de vérité (plus de mapping Game Master → forme).
- **Association Pokémon ↔ événements** : lorsqu’un Pokémon est marqué **événement ou costumé** (case à cocher sur la fiche ou forme avec catégorie « Costume / Event »), une section **« Event Association »** apparaît en édition : on peut associer un ou plusieurs événements (même sélecteur que pour les fonds et les formes). Les liaisons sont stockées dans la table **`pokemon_pokemon_events`** ; helper : `poke_hub_get_pokemon_events($pokemon_id)`. Voir [COLLECTIONS_AND_FORMS_CATEGORIES.md](./COLLECTIONS_AND_FORMS_CATEGORIES.md).
- **User Profiles Source** : l’option **Réglages > Poké HUB > Sources > User Profiles Source** (URL de base des liens profils) n’est affichée et utilisable **que si le module User Profiles est actif**. Elle sert aux codes amis et aux liens vers les profils (sites partageant une base de données). Voir [user-profiles/README_USER_PROFILES.md](./user-profiles/README_USER_PROFILES.md#réglages).
- **Import dates de sortie Pokekalos** : **Poké HUB → Temporary tools** (onglet *Dates (Pokekalos)*), activable ou masquable dans **Settings → General**. Import des dates (normal, shiny, shadow, etc.) depuis les fiches Pokédex Pokémon GO de pokekalos.fr ; deux passes : formes de base (`{slug}-{dex}.html`) puis formes Méga (`{slug}-{dex}m.html`). Dates en **YYYY-MM-DD** ; option « ignorer les existants », limite espèces, dry-run. Script CLI : `scripts/import-pokekalos-release-dates.php`. Voir [pokemon/POKEKALOS_RELEASE_DATES.md](./pokemon/POKEKALOS_RELEASE_DATES.md) et [ADMIN_TEMPORARY_TOOLS.md](./ADMIN_TEMPORARY_TOOLS.md).
- **Sécurité des données Pokémon** : import Game Master — fusion **profonde** de `extra`, préservation des noms i18n déjà remplis, **UPDATE SQL** limité aux colonnes modifiées, correction import Méga (formats `$wpdb` + repli slug). Traductions / outils admin : règles détaillées dans [pokemon/DATA_SAFETY.md](./pokemon/DATA_SAFETY.md).
- **Biomes (Pokémon GO)** : onglet admin **Pokémon → Biomes** (CRUD, images de fond multiples, espèces) ; liaison N–N via `pokemon_biome_pokemon_links` ; sur la fiche Pokémon, champ biomes dans la section **Regional availability** (sous-partie *Biomes*, puis *Regional*). Détail : [pokemon/BIOMES.md](./pokemon/BIOMES.md).
- **Événements (sources & routing)** : une seule table **`special_events`** (préfixe Sources) pour les spéciaux SQL ; pas de `remote_special_events`. Liste unifiée avec **`local_event`**, **`remote_event`**, **`special_event`**. Doc : [events/EVENEMENTS-DISTANTS.md](./events/EVENEMENTS-DISTANTS.md).

## 📁 Structure

### Documentation Générale

La documentation générale se trouve à la racine du dossier `docs/` :

- **[REDACTION.md](./REDACTION.md)** — Charte rédactionnelle (langue, liens, structure, nommage)
- **[TRANSLATION.md](./TRANSLATION.md)** - Traductions (i18n) : text domain `poke-hub`, langue de base anglaise, fichiers .po/.mo
- **[ORGANISATION.md](./ORGANISATION.md)** - Organisation générale du plugin
- **[ADMIN_TEMPORARY_TOOLS.md](./ADMIN_TEMPORARY_TOOLS.md)** - Page *Temporary tools* (onglets, option d’activation, lien avec imports Pokekalos / Fandom)
- **[CACHE_AND_NGINX_PURGE.md](./CACHE_AND_NGINX_PURGE.md)** - Cache page Nginx (FastCGI), Redis objet, purge globale, événements, codes amis, filtres et branchement d’URLs
- **[BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md)** - Bonus : source de vérité (site principal), bloc et metabox (module Blocks uniquement), schéma catalogue, SVG / raster, thème CSS (`--pokehub-bonus-icon-*`)
- **[INLINE_SVG.md](./INLINE_SVG.md)** - SVG inline depuis le bucket (helpers globaux, types Pokémon, bonus, filtres)
- **[THEME_INTEGRATION.md](./THEME_INTEGRATION.md)** - Guide d’intégration dans le thème WordPress
- **[PLUGIN_INTEGRATION.md](./PLUGIN_INTEGRATION.md)** - Guide d’intégration pour utiliser les classes CSS dans d’autres plugins
- **[POKEHUB_CSS_CLASSES.md](./POKEHUB_CSS_CLASSES.md)** - Classes `pokehub-*` (blocs, collections, etc.) et lien avec le système *me5rine-lab*
- **[CSS_SYSTEM.md](./CSS_SYSTEM.md)** - Documentation complète du système de classes CSS
- **[CSS_RULES.md](./CSS_RULES.md)** - Règles CSS complètes pour les formulaires
- **[FRONT_CSS.md](./FRONT_CSS.md)** - Règles CSS unifiées pour les éléments front-end
- **[ADMIN_CSS.md](./ADMIN_CSS.md)** - Règles CSS unifiées pour l’administration
- **[ADMIN_FORM_UX.md](./ADMIN_FORM_UX.md)** - Formulaires admin : redirection vers la liste après sauvegarde, lien « Retour à la liste », helpers (`poke_hub_admin_back_to_list_bar`, `pokehub_events_admin_list_url`)
- **[TABLE_CSS.md](./TABLE_CSS.md)** - Règles CSS pour les tableaux
- **[PLUGIN_COPY_GUIDE.md](./PLUGIN_COPY_GUIDE.md)** - Guide : Fichiers à copier pour réutiliser la structure
- **[SELECT2_INITIALIZATION.md](./SELECT2_INITIALIZATION.md)** - Initialisation Select2 (Me5rine LAB + cas **Poké HUB** / types d’événement — lien vers [events/README.md](./events/README.md#event-type-select2-admin))
- **[POKEMON_IMAGES.md](./POKEMON_IMAGES.md)** - Gestion des images Pokémon
- **[COLLECTIONS_MODULE.md](./COLLECTIONS_MODULE.md)** - Module Collections : shortcodes, pool, statuts (possédé / à l’échange / manquant), filtre **Afficher dans la grille** ; styles et classes détaillés dans **modules/collections/COLLECTIONS_THEME_CSS.md** (référencé aussi par **POKEHUB_CSS_CLASSES.md**)
- **[COLLECTIONS_AND_FORMS_CATEGORIES.md](./COLLECTIONS_AND_FORMS_CATEGORIES.md)** - Catégories **formes** (variants) vs catégories **collections** (type de liste)
- **[CONTENT_BLOCKS.md](./CONTENT_BLOCKS.md)** - Blocs de contenu : usage, attributs, exemples, CSS
- **[CONTENT_BLOCKS_TROUBLESHOOTING.md](./CONTENT_BLOCKS_TROUBLESHOOTING.md)** - Dépannage des blocs de contenu
- **[BLOCKS_TROUBLESHOOTING.md](./BLOCKS_TROUBLESHOOTING.md)** - Dépannage du module Blocks
- **[BLOCKS_MODULE_MIGRATION.md](./BLOCKS_MODULE_MIGRATION.md)** - Notes de migration / historique du module Blocks

### Documentation par Module

La documentation spécifique à chaque module se trouve dans des sous-dossiers. Chaque dossier dispose d’un **README.md** d’entrée (**table des matières** + liens vers les guides détaillés), sauf mention contraire.

- **[blocks/](./blocks/)** - Module Blocks
  - [README.md](./blocks/README.md) - Index des blocs et liste des blocs disponibles
  - [ARCHITECTURE.md](./blocks/ARCHITECTURE.md) - Architecture et organisation des blocs Gutenberg
  - [BLOCK_TYPES.md](./blocks/BLOCK_TYPES.md) - Types de blocs (PHP-only vs JS/React)
  - [QUICK_START.md](./blocks/QUICK_START.md) - Guide de création rapide de blocs
  - [BLOCK_STYLES_AND_BEHAVIOR.md](./blocks/BLOCK_STYLES_AND_BEHAVIOR.md) - Titres de blocs, CSS front, bonbons (New Pokémon)
  - Voir aussi [CONTENT_BLOCKS.md](./CONTENT_BLOCKS.md) - Utilisation des blocs (attributs, exemples, CSS)

- **[events/](./events/)** - Module Events
  - [README.md](./events/README.md) - Index (routing, distants, Elementor, **Select2 types d’événement** admin)
  - [README-ROUTING.md](./events/README-ROUTING.md) - Système de routing
  - [INTEGRATION-ELEMENTOR.md](./events/INTEGRATION-ELEMENTOR.md) - Intégration Elementor
  - [EVENEMENTS-DISTANTS.md](./events/EVENEMENTS-DISTANTS.md) - Sources (local / distant / SQL), table `special_events`, Spotlight Day Hours

- **[pokemon/](./pokemon/)** - Module Pokémon
  - [README.md](./pokemon/README.md) - Index (biomes, sécurité données, régional, Pokekalos)
  - [BIOMES.md](./pokemon/BIOMES.md) - Biomes (tables, admin, fiche Pokémon, helpers)
  - [DATA_SAFETY.md](./pokemon/DATA_SAFETY.md) - Règles de sécurité des données (non écrasement)
  - [README-REGIONAL-AUTO-CONFIG.md](./pokemon/README-REGIONAL-AUTO-CONFIG.md) - Pokémon régionaux
  - [POKEKALOS_RELEASE_DATES.md](./pokemon/POKEKALOS_RELEASE_DATES.md) - Import dates de sortie (admin + CLI)

- **[bonus/](./bonus/)** - Module Bonus (catalogue `bonus_types`, shortcodes)
  - [README.md](./bonus/README.md) - Menu admin, source locale / distante, shortcodes, lien avec Blocks
  - [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md) - Bloc, metabox, schéma `content_bonus`, icônes

- **[eggs/](./eggs/)** - Module Œufs
  - [README.md](./eggs/README.md) - Pools admin, metabox, shortcode `[pokehub_all_eggs]`, bloc `pokehub/eggs`, tables `content_eggs`

- **[quests/](./quests/)** - Module Quêtes
  - [README.md](./quests/README.md) - Menu Quêtes, onglets, association contenu local/global, tables de contenu

- **[shop-items/](./shop-items/)** - Module Shop (boutique avatar + stickers en jeu)
  - [README.md](./shop-items/README.md) - Menu Shop, onglets, tables, bucket, Blocks / metaboxes, `content-helpers`

- **[collections/](./collections/)** - Module Pokémon GO Collections
  - [README.md](./collections/README.md) - Index vers [COLLECTIONS_MODULE.md](./COLLECTIONS_MODULE.md), formes vs collections, CSS thème

- **[user-profiles/](./user-profiles/)** - Module User Profiles
  - [README.md](./user-profiles/README.md) - Index des pages du dossier
  - [README_USER_PROFILES.md](./user-profiles/README_USER_PROFILES.md) - Documentation principale
  - [SHORTCODE_USAGE.md](./user-profiles/SHORTCODE_USAGE.md) - Shortcodes
  - [ULTIMATE_MEMBER_SETUP.md](./user-profiles/ULTIMATE_MEMBER_SETUP.md) - Ultimate Member
  - [CUSTOMIZATION.md](./user-profiles/CUSTOMIZATION.md) - Personnalisation des listes
  - [README_DATA_CENTRALIZATION.md](./user-profiles/README_DATA_CENTRALIZATION.md) - Centralisation des données
  - [SYNCHRONIZATION.md](./user-profiles/SYNCHRONIZATION.md) - Synchronisation
  - [COUNTRIES-STORAGE-FORMAT.md](./user-profiles/COUNTRIES-STORAGE-FORMAT.md) - Stockage des pays
  - [FRIEND_CODES_PUBLIC_AND_IP.md](./user-profiles/FRIEND_CODES_PUBLIC_AND_IP.md) - Codes amis, IP, visiteurs

- **Module games** — En cours de développement ; pas de dossier `docs/games/` pour l’instant. Code : `modules/games/`.

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

- [Charte rédactionnelle](./REDACTION.md)
- [Traductions (i18n)](./TRANSLATION.md)
- [Organisation du plugin](./ORGANISATION.md)
- [Cache Nginx / purge](./CACHE_AND_NGINX_PURGE.md)
- [Bonus : source de vérité et bloc](./BONUS_SOURCE_AND_BLOCKS.md)
- [Guide d’intégration dans le thème](./THEME_INTEGRATION.md)
- [Système CSS](./CSS_SYSTEM.md)
- [Classes pokehub-*](./POKEHUB_CSS_CLASSES.md)
- [Blocs de contenu](./CONTENT_BLOCKS.md)
- [Module Blocks](./blocks/)
- [Module Events](./events/)
- [Module Quêtes](./quests/)
- [Module User Profiles](./user-profiles/)
- [Module Pokémon](./pokemon/)
- [Module Collections](./COLLECTIONS_MODULE.md)

