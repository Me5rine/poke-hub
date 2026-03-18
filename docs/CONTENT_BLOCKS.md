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
- Icônes des bonus
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

#### Bloc "Event Dates" (`pokehub/event-dates`)

**Attributs :**
- `autoDetect` (boolean, défaut: `true`) : Détecte automatiquement les dates depuis les meta
- `startDate` (string) : Date de début (format: "YYYY-MM-DD HH:MM")
- `endDate` (string) : Date de fin (format: "YYYY-MM-DD HH:MM")

**Exemple :** `<!--wp:pokehub/event-dates {"autoDetect":true} /-->`

#### Bloc "Event Quests" (`pokehub/event-quests`)

Affiche les quêtes et récompenses du contenu courant (article ou événement). Les données viennent des tables de contenu (`content_quests`, `content_quest_lines`), remplies via **Poké HUB → Quêtes** (ensemble lié à un contenu) ou via la metabox quêtes sur le post (module Events).

- **Dépendance d’enregistrement :** module **Events** (le bloc ne dépend pas du module Quêtes).
- **Gestion des quêtes :** menu **Poké HUB → Quêtes** (module Quêtes) ; la metabox sur les articles reste fournie par le module Events.

**Attributs :** selon le bloc (souvent `autoDetect`). Voir l’éditeur ou le `block.json` du bloc.

#### Bloc "Bonus" (`pokehub/bonus`)

**Dépendance :** aucun module requis en plus du module **Blocks**. La metabox « Bonus de l’événement » et les helpers sont chargés par le module Blocks ; les types de bonus viennent du site principal (table locale ou distante selon le préfixe Pokémon). Voir [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md).

**Attributs :**
- `autoDetect` (boolean, défaut: `true`) : Détecte automatiquement les bonus depuis les données de l’article (content_bonus / content_bonus_entries)
- `bonusIds` (array) : Liste des IDs de bonus à afficher (IDs du catalogue du site principal)
- `layout` (string, défaut: `cards`) : Layout d'affichage (`cards` ou `list`)

**Exemple :** `<!--wp:pokehub/bonus {"autoDetect":true,"layout":"cards"} /-->`

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

**Attributs :**
- `autoDetect` (boolean, défaut: `true`) : Données depuis les meta du post
- `pokemonIds` (array) : IDs de Pokémon à afficher (si pas en auto)

**Styles :** `assets/css/poke-hub-new-pokemon-evolutions-front.css`

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

## 🎨 Styles CSS

Les styles sont chargés par le module Blocks :
- **Dates / Quêtes / Habitats / Wild** : `assets/css/poke-hub-events-front.css` (et assets liés aux événements)
- **Bonus** : `assets/css/poke-hub-bonus-front.css`
- **Pokémon Eggs** : `assets/css/poke-hub-eggs-front.css`
- **New Pokémon - Evolution Lines** : `assets/css/poke-hub-new-pokemon-evolutions-front.css`
- **Collection Challenges** : `assets/css/poke-hub-collection-challenges-front.css`
- **Special Research** : `assets/css/poke-hub-special-research-front.css`

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
.pokehub-bonuses-visual           /* Conteneur principal */
.pokehub-bonus-card               /* Carte de bonus */
.pokehub-bonus-card-inner         /* Contenu de la carte */
.pokehub-bonus-card-header        /* En-tête */
.pokehub-bonus-card-title         /* Titre du bonus */
.pokehub-bonus-card-icon-wrapper  /* Conteneur de l'icône */
.pokehub-bonus-card-icon          /* Icône du bonus */
.pokehub-bonus-card-badge         /* Badge de ratio */
.pokehub-bonus-card-description   /* Description */
```

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

Tout le contenu des blocs (quêtes, bonus, habitats, œufs, Pokémon dans la nature, field research, nouveaux Pokémon, special research, collection challenges) est enregistré dans des **tables de contenu** communes (`content_eggs`, `content_quests`, `content_bonus`, etc.), avec `source_type` = `post`, `special_event` ou `global_pool` et `source_id` = ID du post, de l’événement ou 0 pour un pool global.

**Même préfixe que la source Pokémon :** ces tables utilisent le scope **`content_source`** : elles sont lues/écrites avec le **même préfixe** que les tables Pokémon. Le réglage à utiliser est **Réglages > Poké HUB > Sources > Pokémon table prefix (remote)** — il sert explicitement aux Pokémon et à tous les contenus (quêtes, bonus, habitats, œufs, etc.). Une seule base pour tout. Sur un site distant, renseigner ce préfixe avec celui du site principal pour que les blocs sauvegardent et affichent les données depuis le site principal.

**Blocs indépendants du module Pokémon :** les blocs (œufs, quêtes, défis de collection, bonus, etc.) ne dépendent plus du module Pokémon pour être enregistrés ; ils ne requièrent que le module **Events** (ou **Bonus** pour le bloc bonus). Ils restent utilisables en mode remote dès que le module Blocks et Events sont actifs.

**Metaboxes chargées par le module Blocks :** pour que les blocs restent configurables même sans activer tous les modules, le module Blocks charge lui-même les metaboxes nécessaires lorsque les modules dédiés sont inactifs : metabox **Bonus** (si module Bonus inactif), metabox **Eggs** (si module Eggs inactif). La metabox **Collection Challenges** est toujours gérée par le module Blocks et enregistre ses assets (Select2) si besoin.

## 📖 Voir aussi

- **Liste complète des blocs** et dépendances : [docs/blocks/README.md](blocks/README.md)
- **Architecture du module Blocs** : [docs/blocks/ARCHITECTURE.md](blocks/ARCHITECTURE.md)
- **Créer un nouveau bloc** : [docs/blocks/QUICK_START.md](blocks/QUICK_START.md)
- **Dépannage** : [docs/BLOCKS_TROUBLESHOOTING.md](BLOCKS_TROUBLESHOOTING.md)













