# 📦 Module Blocks

Module centralisé pour tous les blocs Gutenberg du plugin Poké HUB.

> 📖 **Documentation complète** : Voir `docs/blocks/` à la racine du plugin

## 📁 Structure

```
modules/blocks/
├── blocks.php                    # Point d'entrée
├── functions/
│   ├── blocks-register.php      # Enregistrement de tous les blocs
│   ├── blocks-helpers.php       # Helpers génériques
│   └── blocks-debug.php         # Outils de diagnostic
└── blocks/                       # Tous les blocs Gutenberg
    ├── event-dates/             # Dates d'événement
    ├── bonus/                   # Bonus
    └── event-quests/            # Quêtes d'événement
```

## 📚 Documentation

Toute la documentation se trouve dans `docs/blocks/` :

- **[ARCHITECTURE.md](../../docs/blocks/ARCHITECTURE.md)** - Architecture complète et règles d'organisation
- **[BLOCK_TYPES.md](../../docs/blocks/BLOCK_TYPES.md)** - Types de blocs (PHP-only vs JS/React)
- **[QUICK_START.md](../../docs/blocks/QUICK_START.md)** - Guide de création rapide

## 🎯 Principe de séparation

### Blocs Gutenberg → `modules/blocks/`
- Définition et enregistrement des blocs
- Interface éditeur (index.js)
- Rendu serveur (render.php)

### Fonctions de rendu → Modules respectifs
- **Events** : `modules/events/functions/events-render.php`
  - `pokehub_render_event_dates()`
- **Bonus** : `modules/bonus/functions/bonus-helpers.php`
  - `pokehub_render_bonuses_visual()`
- **Quêtes** : `modules/events/functions/events-quests-render.php`
  - `pokehub_render_quests_visual()`

### Helpers → Modules respectifs
- **Events** : `modules/events/functions/events-helpers.php`
- **Bonus** : `modules/bonus/functions/bonus-helpers.php`
