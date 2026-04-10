# 📦 Module Blocks

Module centralisé pour tous les blocs Gutenberg du plugin Poké HUB.

> 📖 **Documentation complète** : Voir `docs/blocks/` à la racine du plugin

## 📁 Structure

```
modules/blocks/
├── blocks.php                    # Point d'entrée
├── admin/                        # Meta boxes (défis de collection, études spéciales)
├── functions/
│   ├── blocks-register.php      # Enregistrement de tous les blocs
│   ├── blocks-helpers.php       # Helpers génériques
│   ├── blocks-quests-helpers.php
│   ├── blocks-collection-challenges-helpers.php
│   ├── blocks-special-research-helpers.php
│   └── blocks-debug.php         # Outils de diagnostic
└── blocks/                       # Tous les blocs Gutenberg
    ├── event-dates/             # Dates d'événement
    ├── event-quests/            # Quêtes d'événement
    ├── bonus/                   # Bonus
    ├── wild-pokemon/            # Pokémon Sauvages
    ├── habitats/                # Habitats
    ├── new-pokemon-evolutions/  # New Pokemon
    ├── day-pokemon-hours/       # Day Pokémon Hours
    ├── collection-challenges/   # Défis de Collection
    ├── special-research/        # Études Spéciales
    └── eggs/                    # Pokémon Eggs
```

## 📚 Documentation

Toute la documentation se trouve dans `docs/blocks/` :

- **[README.md](../../docs/blocks/README.md)** - Index des blocs et résumé
- **[ARCHITECTURE.md](../../docs/blocks/ARCHITECTURE.md)** - Architecture complète et règles d'organisation
- **[BLOCK_TYPES.md](../../docs/blocks/BLOCK_TYPES.md)** - Types de blocs (PHP-only vs JS/React)
- **[QUICK_START.md](../../docs/blocks/QUICK_START.md)** - Guide de création rapide
- **[BLOCK_STYLES_AND_BEHAVIOR.md](../../docs/blocks/BLOCK_STYLES_AND_BEHAVIOR.md)** - Titres unifiés, CSS front, bonbons (New Pokémon)

Voir aussi **[CONTENT_BLOCKS.md](../../docs/CONTENT_BLOCKS.md)** pour l’utilisation des blocs (attributs, exemples, CSS).

## 🎯 Principe de séparation

### Blocs Gutenberg → `modules/blocks/`
- Définition et enregistrement des blocs
- Interface éditeur (index.js)
- Rendu serveur (render.php)

### Fonctions de rendu → Modules respectifs
- **Events** : `modules/events/functions/events-render.php` — dates, quêtes, habitats, wild
- **Bonus** : `modules/bonus/functions/bonus-helpers.php` — `pokehub_render_bonuses_visual()`
- **Quêtes** : `modules/events/functions/events-quests-render.php` — `pokehub_render_quests_visual()`
- **Défis / Études** : helpers dans `modules/blocks/functions/` + rendu délégué aux modules

### Helpers → Modules respectifs + module blocks
- **Events** : `modules/events/functions/events-helpers.php`, `events-queries.php`
- **Bonus** : `modules/bonus/functions/bonus-helpers.php`
- **Blocks** : `blocks-quests-helpers.php`, `blocks-collection-challenges-helpers.php`, `blocks-special-research-helpers.php`
