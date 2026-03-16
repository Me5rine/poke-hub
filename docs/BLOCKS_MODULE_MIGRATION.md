# 🔄 Migration vers le module Blocks

Les blocs Gutenberg ont été centralisés dans un nouveau module **Blocks** pour une meilleure organisation.

## ✅ Ce qui a changé

### Avant
- Les blocs étaient dispersés dans les modules `events` et `bonus`
- Structure : `modules/events/blocks/` et `modules/bonus/blocks/`

### Maintenant
- Tous les blocs sont centralisés dans le module `blocks`
- Structure : `modules/blocks/blocks/`
- Gestion des dépendances : chaque bloc déclare les modules requis (souvent uniquement `events` ; plus de dépendance au module Pokémon pour l’enregistrement)
- **Tables de contenu** : les données (œufs, quêtes, bonus, défis de collection, etc.) sont stockées dans des tables avec le scope **`content_source`** — même préfixe que les tables Pokémon (Réglages > Sources > Pokémon table prefix (remote)). Une seule base pour les Pokémon et tous les contenus ; blocs utilisables en mode remote.
- **Bonus** : le bloc Bonus et la metabox « Bonus de l’événement » ne dépendent pas du module Bonus ; ils sont chargés uniquement par le module Blocks. Les types de bonus viennent du site principal (voir [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md)).
- **Metaboxes** : la metabox Bonus est toujours chargée par le module Blocks ; la metabox Eggs est chargée par Blocks lorsque le module Eggs est inactif

## 🚀 Activation

1. Allez dans **Poké HUB → Settings → General**
2. Cochez la case **Blocks**
3. Cliquez sur **Save Changes**

Les blocs seront automatiquement enregistrés selon leurs dépendances (voir `docs/blocks/README.md` pour la liste à jour) :
- La plupart des blocs (dates, quêtes, œufs, wild-pokemon, habitats, défis de collection, études spéciales, nouveaux Pokémon) ne requièrent que le module **events**
- **Bonus** : **aucun** module requis en plus de Blocks ; tout (bloc + metabox + helpers) est chargé par le module Blocks. Les types de bonus viennent du site principal (local ou distant selon le préfixe Pokémon)

## 📁 Structure actuelle

```
modules/blocks/
├── blocks.php                    # Point d'entrée (charge helpers + metabox Bonus ; metabox Eggs si module Eggs inactif)
├── admin/                        # Metaboxes Collection Challenges, Études spéciales
├── functions/
│   ├── blocks-register.php      # Enregistrement centralisé (bonus: requires [])
│   ├── blocks-helpers.php       # Helpers
│   ├── blocks-eggs-helpers.php  # Helpers bloc œufs
│   └── ...
└── blocks/
    ├── event-dates/              # Bloc Dates (requires: events)
    ├── bonus/                    # Bloc Bonus (requires: [] — chargé par Blocks uniquement)
    ├── eggs/                     # Bloc Œufs (requires: events)
    └── ...
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













