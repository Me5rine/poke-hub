# 🔄 Migration vers le module Blocks

Les blocs Gutenberg ont été centralisés dans un nouveau module **Blocks** pour une meilleure organisation.

## ✅ Ce qui a changé

### Avant
- Les blocs étaient dispersés dans les modules `events` et `bonus`
- Structure : `modules/events/blocks/` et `modules/bonus/blocks/`

### Maintenant
- Tous les blocs sont centralisés dans le module `blocks`
- Structure : `modules/blocks/blocks/`
- Gestion des dépendances : chaque bloc déclare les modules requis

## 🚀 Activation

1. Allez dans **Poké HUB → Settings → General**
2. Cochez la case **Blocks**
3. Cliquez sur **Save Changes**

Les blocs seront automatiquement enregistrés selon leurs dépendances :
- **Dates d'événement** : nécessite le module `events`
- **Bonus** : nécessite le module `bonus`

## 📁 Structure actuelle

```
modules/blocks/
├── blocks.php                    # Point d'entrée
├── functions/
│   ├── blocks-register.php      # Enregistrement centralisé
│   └── blocks-helpers.php       # Helpers
└── blocks/
    ├── event-dates/              # Bloc Dates (dépend de events)
    └── bonus/                    # Bloc Bonus (dépend de bonus)
```

## ➕ Ajouter de nouveaux blocs

Voir `modules/blocks/README.md` pour la documentation complète.

### Exemple : Ajouter un bloc "Pokémon"

1. Créer `modules/blocks/blocks/pokemon/block.json`
2. Créer `modules/blocks/blocks/pokemon/render.php`
3. Ajouter dans `blocks-register.php` :

```php
'pokemon' => [
    'path' => POKE_HUB_BLOCKS_PATH . '/blocks/pokemon',
    'requires' => ['pokemon'],
],
```

## 🔧 Anciens fichiers

Les anciens dossiers `modules/events/blocks/` et `modules/bonus/blocks/` peuvent être supprimés une fois que vous avez vérifié que tout fonctionne.

Les fichiers `events-content-blocks.php` et `bonus-content-blocks.php` gardent uniquement :
- Les fonctions de rendu (`pokehub_render_event_dates`, `pokehub_render_bonuses_visual`)
- L'affichage automatique via `the_content`

L'enregistrement des blocs Gutenberg est maintenant géré par le module `blocks`.













