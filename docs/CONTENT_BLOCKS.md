# 🎨 Blocs de contenu automatiques - Poké HUB

Ce système permet d'automatiser l'affichage des dates d'événements et des bonus dans les articles Poké HUB avec un rendu visuel moderne.

**Note :** Les titres des blocs dans l'éditeur (et dans cette doc) sont en anglais (langue de base du plugin). Voir [TRANSLATION.md](TRANSLATION.md) pour l'internationalisation.

## 📋 Fonctionnalités

### 1. Dates d'événement avec feux verts/rouges

Les dates d'événement s'affichent automatiquement avec des indicateurs visuels :
- 🔵 **Feu vert** : Date de début
- 🔴 **Feu rouge** : Date de fin

### 2. Bonus visuels en cartes

Les bonus s'affichent automatiquement sous forme de cartes modernes avec :
- Icônes des bonus (fichier **`{slug}.svg`** sur le bucket en priorité, SVG inline ; repli raster — voir [INLINE_SVG.md](./INLINE_SVG.md) et [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md))
- Badges de ratio (ex: "1/2", "1/4") détectés automatiquement
- Design sombre et moderne

## 🚀 Utilisation

### Affichage automatique

Par défaut, les dates et bonus s'affichent automatiquement dans les articles si :
- Les dates sont définies dans les meta `_admin_lab_event_start` et `_admin_lab_event_end`
- Les bonus sont associés à l'article via la metabox "Bonus de l'événement"

**Post types supportés par défaut :**
- `post`
- `pokehub_event`

Vous pouvez modifier cette liste via les filtres :
```php
// Pour les dates
add_filter('pokehub_events_dates_auto_post_types', function($types) {
    $types[] = 'mon_custom_post_type';
    return $types;
});

// Pour les bonus
add_filter('pokehub_bonus_auto_post_types', function($types) {
    $types[] = 'mon_custom_post_type';
    return $types;
});
```

### Blocs Gutenberg

Vous pouvez insérer les blocs manuellement via la catégorie **Poké HUB** dans l’éditeur.

### Sélection Pokémon et genres (admin)

Dans les écrans admin qui utilisent les sélecteurs Select2 Pokémon en multi-sélection, les valeurs postées peuvent être :
- un ID normal (`123`)
- ou un token genre forcé (`123|male`, `123|female`)

Les genres sont aussi synchronisés via des champs cachés `pokemon_genders[...]`.

Normalisation côté serveur :
- parseur central : `pokehub_parse_post_pokemon_multiselect_tokens_with_genders()`
- normalisation globale contenu : `pokehub_content_normalize_pokemon_ids_with_genders()`

Couverture appliquée : GO Pass, Day Pokémon Hours (incluant la génération des featured/spotlight), Eggs, Collection Challenges, Special Research, New Pokémon, Habitats, Field Research, Wild Pokémon, Special Events, Event Quests.

#### Bloc "Event Dates" (`pokehub/event-dates`)

**Attributs :**
- `autoDetect` (boolean, défaut: `true`) : Détecte automatiquement les dates depuis les meta
- `startDate` (string) : Date de début (format: "YYYY-MM-DD HH:MM")
- `endDate` (string) : Date de fin (format: "YYYY-MM-DD HH:MM")

**Exemple :** `<!--wp:pokehub/event-dates {"autoDetect":true} /-->`

**Rendu front (wrapper)** : le wrapper `.pokehub-event-dates-block-wrapper` reçoit un habillage type « carte » (fond léger, bordure, ombre, padding) pour mieux séparer le titre et la ligne de chips **sans** séparateur horizontal entre le titre et les tuiles début/fin — les chips elles-mêmes ne sont pas modifiées. Styles : `assets/css/poke-hub-blocks-front.css`.

#### Bloc "Event Quests" (`pokehub/event-quests`)

Affiche les quêtes et récompenses du contenu courant (article ou événement). Les données viennent des tables de contenu (`content_quests`, `content_quest_lines`), remplies via **Poké HUB → Quêtes** (ensemble lié à un contenu) ou via la metabox quêtes sur le post (module Events).

- **Dépendance d’enregistrement :** module **Events** (le bloc ne dépend pas du module Quêtes).
- **Gestion des quêtes :** menu **Poké HUB → Quêtes** (module Quêtes) ; la metabox sur les articles reste fournie par le module Events.
- **Aperçu replié des récompenses Pokémon :** maximum **3** mini-tuiles, puis un badge **`+N`** pour le nombre de Pokémon supplémentaires non affichés.
- **Compteurs d’aperçu (récompenses non-Pokémon) :** s’il n’y a **qu’une** ligne non-Pokémon, le badge affiche la **quantité réelle** (`×500`, etc.). S’il y en a **plusieurs**, le badge reste compact : **`Other × M`** (nombre de lignes), avec `title` explicite — évite la confusion avec une quantité unique.
- **Lisibilité visuelle :** en mode replié, les lignes sont contraintes pour garder une hauteur homogène ; sur mobile, l’aperçu reste sur une seule ligne avec défilement horizontal si nécessaire.
- **Récompenses non-Pokémon (détail déplié) :** quand une icône / image est affichée (poussière, XP, objet, ressources avec visuel), le texte à côté est réduit à **`×quantité`** pour éviter le doublon avec l’icône.
- **CP min/max :** deux pastilles en **colonne** (label au-dessus de la valeur), **CP min à gauche** puis **CP max à droite** ; le min est visuellement plus discret ; libellés en majuscules courtes (`CP MIN` / `CP MAX`) avec `title` pour le détail niveau 15.

**Attributs :** selon le bloc (souvent `autoDetect`). Voir l’éditeur ou le `block.json` du bloc.

#### Bloc "Bonus" (`pokehub/bonus`)

**Dépendance :** aucun module requis en plus du module **Blocks**. La metabox « Bonus de l’événement » et les helpers sont chargés par le module Blocks ; les types de bonus viennent du site principal (table locale ou distante selon le préfixe Pokémon). Voir [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md).

**Attributs :**
- `autoDetect` (boolean, défaut: `true`) : Détecte automatiquement les bonus depuis les données de l’article (content_bonus / content_bonus_entries)
- `bonusIds` (array) : Liste des IDs de bonus à afficher (IDs du catalogue du site principal)
- `layout` (string, défaut: `cards`) : Layout d'affichage (`cards` ou `list`)

**Exemple :** `<!--wp:pokehub/bonus {"autoDetect":true,"layout":"cards"} /-->`

#### Bloc "GO Pass" (`pokehub/go-pass`)

Affiche le Pass GO lié au contenu courant (carte résumé ou grille complète).

- **Source des données :** `special_events` (pass) + `content_go_pass` (payload JSON).
- **Liaison contenu → pass :** table dédiée `go_pass_host_links` (aucune post meta pour la liaison). La valeur enregistrée est l’**ID** de la ligne `special_events` (type Pass GO) ; le bloc lit cette liaison au rendu.
- **Configuration éditeur :** metabox **GO Pass (block)** sous l’article : liste **Select2 avec recherche AJAX** par nom (titres FR / EN / `title`), sans charger toute la liste en une fois ; mode d’affichage par défaut (résumé / grille). Bouton **Créer un nouveau Pass GO** : crée un événement Pass GO minimal via `pokehub_go_pass_create_empty_special_event(…, …, $post_id)` ; **titre temporaire** (EN = FR) : d’abord le champ titre **Me5rine LAB** (`#admin_lab_event_box`, metas typiques `_admin_lab_event_title_*`, `_event_title`), puis les metas en base via `poke_hub_events_get_event_meta_title()` / filtres, puis le titre d’article WordPress en dernier recours. **Dates** : si l’article a des metas d’événement (`_admin_lab_event_start` / `_admin_lab_event_end`, ou `_event_mode` + `_event_start_local` / `_event_sort_start`… via `poke_hub_events_get_post_dates()`), le Pass GO reprend le même intervalle ; sinon fenêtre par défaut (aujourd’hui → +30 jours). La colonne `mode` (`local` / `fixed`) est alignée sur `_event_mode` du post quand elle existe. Filtre `pokehub_go_pass_host_post_date_range` pour d’autres sources. Enregistrement du payload par défaut dans `content_go_pass`, **liaison immédiate** dans `go_pass_host_links`, lien **Éditer ce Pass GO** : `pokehub_go_pass_admin_edit_url()` — admin **local** si le module Events est actif, sinon URL du site qui porte les tables content_source (`pokehub_content_source_get_remote_wp_base_url()` = `siteurl` dans `{préfixe Pokémon distant}options`), avec repli sur `pokehub_events_get_remote_wp_base_url()` ou le filtre `pokehub_go_pass_admin_edit_base_url`. La sauvegarde de l’article réécrit la liaison à partir du formulaire comme avant.
- **Contrainte de contenu :** un seul bloc `pokehub/go-pass` par article (garde à la sauvegarde).

**Attributs :** `specialEventId` (number, fallback), `displayMode` (`summary` ou `full`).

#### Bloc "Wild Pokémon" (`pokehub/wild-pokemon`)

Affiche la liste des Pokémon disponibles dans la nature (images, shiny, régional).

**Attributs :**
- `autoDetect` (boolean, défaut: `true`) : Données depuis les meta de l’événement
- `pokemonIds` (array) : IDs de Pokémon à afficher
- `rarePokemonIds` (array) : IDs de Pokémon rares (section dédiée)
- `forcedShinyIds` (array) : IDs de Pokémon à afficher en shiny
- `showRareSection` (boolean, défaut: `true`) : Afficher la section « Pokémon rares »

#### Bloc "Habitats" (`pokehub/habitats`)

Affiche les habitats avec leurs Pokémon et horaires (données événement).

**Attributs :**
- `autoDetect` (boolean, défaut: `true`) : Données depuis les meta du post

#### Bloc "New Pokémon - Evolution Lines" (`pokehub/new-pokemon-evolutions`)

Affiche les nouveaux Pokémon avec lignée d’évolution et conditions (bonbons, objets, etc.).  
**Bonbons** : l’icône / le libellé suivent la **famille de bonbons** (racine de lignée + règles bébés GO), pas uniquement l’espèce précédente dans la chaîne — voir [blocks/BLOCK_STYLES_AND_BEHAVIOR.md](blocks/BLOCK_STYLES_AND_BEHAVIOR.md).

**Tuiles Pokémon (carte d’étape)** : chaque carte d’espèce est **carrée** (`aspect-ratio: 1/1`), avec une taille de tuile un peu plus grande que l’historique pour mettre en valeur les nouveautés ; disposition proche des cartes « Pokémon sauvage » (image + nom, espacement bas du nom dans la tuile). Styles : `assets/css/poke-hub-new-pokemon-evolutions-front.css`.

**Attributs :**
- `autoDetect` (boolean, défaut: `true`) : Données depuis les meta du post
- `pokemonIds` (array) : IDs de Pokémon à afficher (si pas en auto)

**Styles :** `assets/css/poke-hub-new-pokemon-evolutions-front.css` (détail des titres : feuille commune `poke-hub-blocks-front.css`, voir doc ci-dessus).

#### Bloc « Day Pokémon Hours » (`pokehub/day-pokemon-hours`)

Affiche les Pokémon par jour avec horaires (données de la metabox **Day Pokémon Hours** / *Featured Pokémon Hours*).

**Attributs :** `contentType` (défaut `featured_hours` pour les heures vedette / Spotlight), `title` (titre du bloc, optionnel) — voir `block.json`.

**Mode `featured_hours` (front)** — piste horizontale de tuiles type carte Pokémon sauvage + bandeau date/heure sous la carte :

- **Une seule piste** : tous les créneaux (jours différents ou horaires différents) s’enchaînent dans la même grille, avec **espacement homogène** (gauche / droite / entre tuiles) via `gap` + `padding` sur `.pokehub-day-pokemon-hours-featured-track` (`assets/css/poke-hub-blocks-front.css`).
- **Plusieurs Pokémon sur un même créneau** : une tuile **plus large** (span 2, 3 ou 4 colonnes selon le nombre d’images affichées), **hauteur de carte alignée** sur la tuile solo (variable `--pokehub-featured-tile-size`). À l’intérieur : **répétition du duo image + nom** par Pokémon (même logique visuelle que la tuile solo), icônes **shiny / régional par Pokémon** sur chaque image si besoin ; au-delà des emplacements visibles, badge **`+N`** sur le dernier slot.
- **Taille d’image** : fixée de façon identique pour solo et multi dans ce contexte (surcharge `.pokehub-day-pokemon-hours-spotlight-tile .pokehub-wild-pokemon-image-wrapper`).

**Données « featured » / Spotlight :**

- Si les tables **`special_events`** et **`special_event_pokemon`** existent : les créneaux **featured_hours** sont lus depuis ces tables (liaison **`content_source_type` = `post`** et **`content_source_id`** = ID du post courant, avec rétrocompatibilité sur d’anciens slugs). Type d’événement en base le plus souvent **`pokemon-spotlight-hour`** (résolution dans `pokehub_resolve_spotlight_event_type_slug()`).
- Sinon : repli sur les tables de contenu **`content_day_pokemon_hours`** / **`content_day_pokemon_hour_entries`** (`source_type` / `source_id` du post, `content_type` = `featured_hours`).
- À l’enregistrement depuis la metabox : titres **`title_en`** = `{Nom EN} Spotlight Hour`, **`title_fr`** = `Heure vedette {Nom FR}` ; slug = **`{slug-pokemon}-spotlight-hour`** avec unicité via **`pokehub_generate_unique_event_slug()`** ; colonne **`mode`** en base = **`local`** (fuseau WordPress `wp_timezone()`), pour rester cohérent avec l’édition des mêmes créneaux sous **Poké HUB → Special events** (champs date/heure en heure locale du site, pas en UTC « fixed »). En cas de réenregistrement, les titres existants **EN/FR** sont conservés séparément pour éviter l’écrasement du `title_fr` par l’anglais. Voir [events/EVENEMENTS-DISTANTS.md](events/EVENEMENTS-DISTANTS.md) § Spotlight.

Fichiers utiles : `modules/blocks/blocks/day-pokemon-hours/render.php`, `modules/blocks/admin/blocks-featured-pokemon-hours-metabox.php`, `includes/content/content-helpers.php` (`pokehub_content_get_featured_hours_classic_events_entries_for_parent`, `pokehub_content_save_day_pokemon_hours_featured_hours_classic_events`).

#### Bloc "Pokémon Eggs" (`pokehub/eggs`)

Affiche les œufs (par type : 2 km, 5 km, 10 km, etc.) et les Pokémon associés. Données saisies dans la metabox « Eggs » sur le post, ou depuis un pool global.

**Attributs :**
- `source` (string, défaut: `post`) : `post` = données de l’article, `global` = pool actif à la date du jour
- `poolId` (number, optionnel) : si `source` = `global` et > 0, utilise ce pool
- `blockTitle` (string) : titre affiché au-dessus du bloc

**Metabox :** « Eggs » — affichée sur les articles/événements. Chargée par le module Blocks même si le module Eggs est inactif (bloc utilisable en mode remote).

**Styles :** `assets/css/poke-hub-eggs-front.css`

#### Bloc "Collection Challenges" (`pokehub/collection-challenges`)

Affiche les défis de collection (capturer, éclore, évoluer, etc.). Données saisies dans la metabox « Collection Challenges » sur le post (tables de contenu, pas les meta).

**Attributs :**
- `autoDetect` (boolean, défaut: `true`) : Données depuis les tables de contenu liées au post

**Metabox :** « Collection Challenges » — toujours chargée par le module Blocks ; les assets (Select2) sont enregistrés par la metabox si besoin.

**Styles :** `assets/css/poke-hub-collection-challenges-front.css`

#### Bloc "Special Research" (`pokehub/special-research`)

Affiche les études ponctuelles, spéciales ou magistrales (étapes, chemins, quêtes). Données saisies dans la meta box « Études spéciales » sur le post.

**Attributs :**
- `autoDetect` (boolean, défaut: `true`) : Données depuis les meta du post

**Styles :** `assets/css/poke-hub-special-research-front.css`

#### Bloc « Avatar shop highlights » (`pokehub/shop-avatar-highlights`)

Affiche une **couverture** (média WordPress), un **paragraphe d’accroche** (liste des noms d’articles boutique avatar + nom d’événement résolu), puis une **grille de tuiles** (même gabarit carré que Wild Pokémon : `.pokehub-wild-pokemon-card`, etc.).

- **Dépendances :** module **shop-items** actif (tables catalogue + contenu) ; le bloc n’est enregistré que si le schéma requis existe (`pokehub_blocks_shop_avatar_schema_ready()` dans `modules/blocks/functions/blocks-helpers.php`).
- **Données :** `content_shop_avatar` + `content_shop_avatar_entries` + `shop_avatar_items` (lecture via `pokehub_content_get_shop_avatar()`, `poke_hub_shop_avatar_get_items_by_ids()`).
- **Éditeur :** metabox **Avatar shop (block)** (`modules/blocks/admin/blocks-shop-avatar-metabox.php`) — sélection d’items (Select2 + AJAX), **création rapide** inline (champs EN / FR optionnel + bouton, pas de `window.prompt`), image de couverture, lien optionnel vers **Poké HUB → Shop** pour les administrateurs. AJAX : `modules/blocks/admin/blocks-shop-avatar-metabox-ajax.php`. JS : `modules/blocks/admin/js/pokehub-shop-avatar-metabox-admin.js`.
- **Texte d’accroche (front) :** chaînes en **anglais** dans `__()` ; le **nom d’événement** est résolu par `pokehub_shop_highlights_resolve_event_label()` (`pokehub_event`, liaison Pass GO → `special_events`, sinon titre du post, repli `this event`) — voir [TRANSLATION.md](TRANSLATION.md).
- **Sous-titre au-dessus des tuiles :** `Avatar items in this event` (traduisible).

**Attributs :** `autoDetect` (boolean, défaut `true`) — aligné sur les autres blocs « contenu post ».

**Styles :** `assets/css/poke-hub-blocks-front.css` (`.pokehub-shop-highlights-*`, panneau, ligne d’accroche, zone tuiles).

#### Bloc « In-game sticker highlights » (`pokehub/shop-sticker-highlights`)

Même principe que le bloc avatar, pour les **stickers en jeu** : couverture, paragraphe (noms + disponibilité boutique / PokéStops / Gifts + événement), tuiles carrées.

- **Dépendances :** module **shop-items** ; schéma `pokehub_blocks_shop_sticker_schema_ready()`.
- **Données :** `content_shop_sticker` + `content_shop_sticker_entries` + `shop_sticker_items`.
- **Éditeur :** metabox **In-game stickers (block)** (`blocks-shop-sticker-metabox.php` + AJAX + `pokehub-shop-sticker-metabox-admin.js`).
- **Sous-titre des tuiles :** `Stickers in this event`.

**Styles :** même feuille `poke-hub-blocks-front.css`.

## 🎨 Styles CSS

Les styles sont chargés par le module **Blocks** (`modules/blocks/blocks.php`).

- **Base commune (titres de tous les blocs `pokehub/*`, alignés sur Field Research)** : `assets/css/poke-hub-blocks-front.css` — voir [blocks/BLOCK_STYLES_AND_BEHAVIOR.md](blocks/BLOCK_STYLES_AND_BEHAVIOR.md).
- **Pages / module Événements** : `assets/css/poke-hub-events-front.css` (reprend les titres + layout dates, quêtes, wild, habitats, etc.).
- **Bonus** : `assets/css/poke-hub-bonus-front.css` (grille cartes, shortcode, bonus d’événement ; variables `--pokehub-bonus-icon-color`, `--pokehub-bonus-icon-bg`, `--pokehub-bonus-icon-radius` pour le thème — voir [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md))
- **Pokémon Eggs** : `assets/css/poke-hub-eggs-front.css`
- **New Pokémon - Evolution Lines** : `assets/css/poke-hub-new-pokemon-evolutions-front.css`
- **Collection Challenges** : `assets/css/poke-hub-collection-challenges-front.css`
- **Special Research** : `assets/css/poke-hub-special-research-front.css`
- **Avatar shop / Sticker highlights** : règles dans `assets/css/poke-hub-blocks-front.css` (blocs `pokehub/shop-avatar-highlights`, `pokehub/shop-sticker-highlights` — panneau, accroche, tuiles ; réutilisation des classes Wild Pokémon pour les cartes).
- **Icônes types / bonbons** : `pokehub-type-icons`, `poke-hub-candy-display.css` (selon contexte)

### Titres principaux (`.pokehub-block-title`)

Tous les blocs listés ci-dessus utilisent la même charte pour le **titre de section** (couleur, uppercase, alignement). Détail technique : [blocks/BLOCK_STYLES_AND_BEHAVIOR.md](blocks/BLOCK_STYLES_AND_BEHAVIOR.md).

### Classes CSS disponibles

#### Dates d'événement
```css
.pokehub-event-dates-block       /* Conteneur principal */
.event-dates-row                  /* Ligne des dates */
.event-date-chip                  /* Chip de date */
.event-date-chip--start           /* Chip de date de début */
.event-date-chip--end             /* Chip de date de fin */
.event-date-dot                   /* Point indicateur */
.event-date-dot--start            /* Point vert (début) */
.event-date-dot--end              /* Point rouge (fin) */
.event-date-text                  /* Texte de la date */
.event-date-middle                /* Séparateur (···) */
```

#### Bonus
```css
.pokehub-bonus-block-wrapper      /* Wrapper du bloc Gutenberg Bonus (+ h2.pokehub-block-title) */
.pokehub-bonuses-grid             /* Grille des cartes bonus */
.pokehub-bonus-card               /* Carte */
.pokehub-bonus-card-inner         /* Contenu interne de la carte */
.pokehub-bonus-image-wrapper      /* Zone icône + badge ratio */
.pokehub-bonus-icon-wrap          /* Pastille icône (SVG inline ou img) */
.pokehub-bonus-icon--svg          /* Conteneur du SVG inline */
.pokehub-bonus-image              /* Image raster si pas de SVG inline */
.pokehub-bonus-badge              /* Badge ratio (ex. 1/2) */
.pokehub-bonus-description        /* Texte sous l’icône */
.pokehub-bonuses-shortcode        /* Shortcode [pokehub-bonus] */
.pokehub-bonus-item               /* Ligne shortcode */
.pokehub-event-bonuses            /* Liste bonus injectés (the_content / helper) */
.pokehub-event-bonus              /* Une ligne événement */
```

Référence détaillée et variables de thème : [POKEHUB_CSS_CLASSES.md](./POKEHUB_CSS_CLASSES.md), [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md).

## 🔧 Personnalisation

### Désactiver l'affichage automatique

```php
// Pour les dates
remove_filter('the_content', 'pokehub_events_append_dates_to_content', 10);

// Pour les bonus
remove_filter('the_content', 'pokehub_bonus_append_to_content_visual', 20);
```

### Utiliser les fonctions directement

```php
// Afficher les dates
$start_ts = get_post_meta($post_id, '_admin_lab_event_start', true);
$end_ts = get_post_meta($post_id, '_admin_lab_event_end', true);
echo pokehub_render_event_dates((int) $start_ts, (int) $end_ts);

// Afficher les bonus
$bonuses = pokehub_get_bonuses_for_post($post_id);
echo pokehub_render_bonuses_visual($bonuses, 'cards'); // ou 'list'
```

## 📝 Détection automatique des ratios

Le système détecte automatiquement les ratios (ex: "1/2", "1/4") dans :
- Le titre du bonus
- La description du bonus
- La description spécifique à l'événement

Ces ratios sont affichés dans un badge circulaire rouge sur l'icône du bonus.

## 🗄️ Tables de contenu et source Pokémon (scope `content_source`)

Tout le contenu des blocs (quêtes, bonus, habitats, œufs, Pokémon dans la nature, field research, nouveaux Pokémon, special research, collection challenges, **boutique avatar**, **stickers en jeu**) est enregistré dans des **tables de contenu** communes (`content_eggs`, `content_quests`, `content_bonus`, `content_shop_avatar`, `content_shop_sticker`, etc.), avec `source_type` = `post`, `special_event` ou `global_pool` et `source_id` = ID du post, de l’événement ou 0 pour un pool global.

**Même préfixe que la source Pokémon :** ces tables utilisent le scope **`content_source`** : elles sont lues/écrites avec le **même préfixe** que les tables Pokémon. Le réglage à utiliser est **Réglages > Poké HUB > Sources > Pokémon table prefix (remote)** — il sert explicitement aux Pokémon et à tous les contenus (quêtes, bonus, habitats, œufs, etc.). Une seule base pour tout. Sur un site distant, renseigner ce préfixe avec celui du site principal pour que les blocs sauvegardent et affichent les données depuis le site principal.

**Blocs indépendants du module Pokémon :** les blocs (œufs, quêtes, défis de collection, bonus, etc.) ne dépendent plus du module Pokémon pour être enregistrés ; ils requièrent en général le module **Events** (le bloc **Bonus** n’exige que le module **Blocks**). Les blocs **shop-avatar-highlights** et **shop-sticker-highlights** exigent le module **shop-items** (tables catalogue + contenu). Utilisables en mode remote dès que la configuration Sources / préfixe est correcte.

**Metaboxes chargées par le module Blocks :** pour que les blocs restent configurables même sans activer tous les modules, le module Blocks charge lui-même les metaboxes nécessaires lorsque les modules dédiés sont inactifs : metabox **Bonus** (si module Bonus inactif), metabox **Eggs** (si module Eggs inactif). La metabox **Collection Challenges** est toujours gérée par le module Blocks et enregistre ses assets (Select2) si besoin. Les metaboxes **Avatar shop (block)** et **In-game stickers (block)** sont dans `modules/blocks/admin/` et supposent le module **shop-items** (catalogue + tables de contenu) pour la création d’items et l’affichage des blocs.

## 📖 Voir aussi

- **Liste complète des blocs** et dépendances : [docs/blocks/README.md](blocks/README.md)
- **Architecture du module Blocs** : [docs/blocks/ARCHITECTURE.md](blocks/ARCHITECTURE.md)
- **Créer un nouveau bloc** : [docs/blocks/QUICK_START.md](blocks/QUICK_START.md)
- **Titres, CSS front, bonbons (New Pokémon)** : [docs/blocks/BLOCK_STYLES_AND_BEHAVIOR.md](blocks/BLOCK_STYLES_AND_BEHAVIOR.md)
- **Dépannage** : [docs/BLOCKS_TROUBLESHOOTING.md](BLOCKS_TROUBLESHOOTING.md)

---

*Index de la documentation : [README du dossier docs](README.md) · [Charte rédactionnelle](REDACTION.md)*
