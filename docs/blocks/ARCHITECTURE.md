# 🏗️ Architecture des Blocs Gutenberg

## 📁 Organisation des fichiers

### Structure générale

```
modules/blocks/
├── blocks.php                    # Point d'entrée du module
├── admin/                        # Meta boxes pour certains blocs
│   ├── collection-challenges-metabox.php   # Défis de collection (post meta)
│   └── special-research-metabox.php       # Études spéciales (post meta)
├── functions/
│   ├── blocks-register.php      # Enregistrement de tous les blocs
│   ├── blocks-helpers.php       # Helpers génériques pour les blocs
│   ├── blocks-quests-helpers.php           # Helpers quêtes (éditeur)
│   ├── blocks-collection-challenges-helpers.php  # Helpers défis de collection
│   ├── blocks-special-research-helpers.php      # Helpers études spéciales
│   └── blocks-debug.php         # Outils de diagnostic
├── blocks/                       # Tous les blocs Gutenberg
│   ├── event-dates/             # Bloc "Dates d'événement"
│   ├── event-quests/            # Bloc "Quêtes d'événement"
│   ├── bonus/                   # Bloc "Bonus"
│   ├── wild-pokemon/            # Bloc "Pokémon Sauvages"
│   ├── habitats/                # Bloc "Habitats"
│   ├── new-pokemon-evolutions/  # Bloc "Nouveaux Pokémon - Lignées d'évolution"
│   ├── collection-challenges/   # Bloc "Défis de Collection"
│   └── special-research/        # Bloc "Études Spéciales"
└── docs/
    ├── README.md                # Index des blocs (ce dossier)
    ├── ARCHITECTURE.md          # Ce fichier
    ├── BLOCK_TYPES.md           # Types de blocs (PHP-only vs JS/React)
    └── QUICK_START.md           # Guide de création rapide
```

Chaque bloc contient typiquement : `block.json`, `index.js` (éditeur), `render.php` (rendu serveur). Certains ont aussi `index.asset.php` (dépendances build).

## 🎯 Principe de séparation des responsabilités

### 1. **Blocs Gutenberg** (`modules/blocks/blocks/`)
- **Rôle** : Définition et enregistrement des blocs Gutenberg
- **Contenu** : `block.json`, `index.js` (minimal), `render.php`
- **Responsabilité** : Interface utilisateur dans l'éditeur + structure du bloc

### 2. **Fonctions de rendu** (dans les modules respectifs)
- **Events** : `modules/events/functions/events-render.php`
  - `pokehub_render_event_dates()` - Rendu des dates avec feux verts/rouges
  - `pokehub_render_quests_visual()` - Rendu des quêtes
- **Events (habitats, wild)** : `modules/events/functions/events-render.php` et helpers Pokémon
- **Bonus** : `modules/bonus/functions/bonus-helpers.php`
  - `pokehub_render_bonuses_visual()` - Rendu des cartes de bonus
- **Quêtes** : `modules/events/functions/events-quests-render.php`
  - `pokehub_render_quests_visual()` - Rendu des quêtes et récompenses
- **Défis de collection / Études spéciales** : helpers dans `modules/blocks/functions/` + rendu dans les modules events/pokemon

**Pourquoi ?** Les fonctions de rendu restent dans leurs modules respectifs car elles sont spécifiques à chaque domaine métier.

### 3. **Helpers** (dans les modules respectifs + module blocks)
- **Events** : `modules/events/functions/events-helpers.php`, `events-queries.php`
  - `poke_hub_events_get_post_dates()` - Récupération des dates d'événement
- **Bonus** : `modules/bonus/functions/bonus-helpers.php`
  - `pokehub_get_bonuses_for_post()` - Récupération des bonus d'un post
- **Blocks** : `modules/blocks/functions/`
  - `blocks-quests-helpers.php` - Données quêtes pour l’éditeur
  - `blocks-collection-challenges-helpers.php` - Données défis de collection (post meta + rendu)
  - `blocks-special-research-helpers.php` - Données études spéciales (post meta + rendu)

**Pourquoi ?** Les helpers manipulent les données spécifiques à chaque module ; ceux du module blocks centralisent la logique liée aux meta et au rendu des blocs « événement » (défis, études).

## 🔄 Flux de données

```
┌─────────────────────────────────────────────────────────┐
│  Éditeur Gutenberg                                       │
│  ┌───────────────────────────────────────────────────┐   │
│  │  Bloc "Dates d'événement" (index.js)             │   │
│  │  → Affiche un placeholder dans l'éditeur        │   │
│  └───────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│  Front-end (render.php)                                  │
│  ┌───────────────────────────────────────────────────┐   │
│  │  1. Récupère les attributs du bloc               │   │
│  │  2. Appelle le helper (events-helpers.php)      │   │
│  │     → poke_hub_events_get_post_dates()          │   │
│  │  3. Appelle la fonction de rendu (events-render)│   │
│  │     → pokehub_render_event_dates()              │   │
│  │  4. Wrappe dans get_block_wrapper_attributes()   │   │
│  └───────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

## 📝 Règles d'organisation

### ✅ À faire

1. **Tous les blocs Gutenberg** → `modules/blocks/blocks/{block-name}/`
2. **Fonctions de rendu HTML** → Dans le module concerné (`events-render.php`, `bonus-helpers.php`)
3. **Helpers de données** → Dans le module concerné (`events-helpers.php`, `bonus-helpers.php`)
4. **Enregistrement des blocs** → `modules/blocks/functions/blocks-register.php`

### ❌ À éviter

1. ❌ Mettre les fonctions de rendu dans `modules/blocks/` (sauf si vraiment génériques)
2. ❌ Dupliquer les fonctions de rendu dans plusieurs fichiers
3. ❌ Mettre la logique métier dans `render.php` (utiliser les helpers)
4. ❌ Créer des fichiers "content-blocks" séparés (obsolète)

## 🆕 Créer un nouveau bloc

Voir le guide [QUICK_START.md](./QUICK_START.md) pour les étapes détaillées.

### Résumé rapide

1. Créer le dossier : `modules/blocks/blocks/{mon-bloc}/`
2. Créer `block.json` avec les métadonnées
3. Créer `index.js` (minimal pour PHP-only) ou avec éditeur React si besoin
4. Créer `render.php` qui :
   - Récupère les données via les helpers du module concerné
   - Appelle la fonction de rendu du module concerné
   - Wrappe dans `get_block_wrapper_attributes()`
5. Enregistrer dans `blocks-register.php` avec les modules requis :
   ```php
   'mon-bloc' => [
       'requires' => ['events'],  // modules qui doivent être actifs
   ],
   ```
   Si un module requis est désactivé, le bloc n’est pas enregistré.

## 🔍 Fichiers obsolètes (à supprimer)

Ces fichiers ne sont plus utilisés et peuvent être supprimés :

- ❌ `modules/events/functions/events-content-blocks.php`
- ❌ `modules/events/functions/events-content-blocks-debug.php`
- ❌ `modules/bonus/functions/bonus-content-blocks.php`
- ❌ `modules/bonus/functions/bonus-content-blocks-debug.php`

**Raison** : Les blocs sont maintenant gérés par le module `blocks`, et les fonctions de rendu sont dans les modules respectifs.

## 📚 Références

- [WordPress Block Editor Handbook](https://developer.wordpress.org/block-editor/)
- [BLOCK_TYPES.md](./BLOCK_TYPES.md) - Types de blocs (PHP-only vs JS/React)
- [QUICK_START.md](./QUICK_START.md) - Guide de création rapide


