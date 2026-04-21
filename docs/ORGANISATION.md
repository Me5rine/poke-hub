# 🗂️ Guide d'Organisation du Code

## 📋 Vue d'ensemble

Ce guide explique comment organiser le code dans le plugin Poké HUB pour maintenir une structure claire et cohérente.

## 🏗️ Structure générale

```
poke-hub/
├── includes/              # Code partagé entre modules
│   ├── admin-tools.php   # Page admin « Temporary tools » (imports ponctuels : Pokekalos, onglets vers outils events) — voir docs/ADMIN_TEMPORARY_TOOLS.md
│   ├── admin-ui.php      # UI admin partagée (ex. barre « retour à la liste », styles `pokehub-admin-back-bar`) — voir docs/ADMIN_FORM_UX.md
│   ├── functions/        # Helpers globaux (toujours chargés avec le plugin, hors modules) : ex. pokehub-helpers.php (purge Nginx / cache page), pokehub-inline-svg.php, pokehub-pokemon-type-icon.php, pokemon-public-helpers.php — voir docs/INLINE_SVG.md, docs/CACHE_AND_NGINX_PURGE.md
│   ├── settings/         # Gestion des paramètres (modules : source unique dans settings-modules.php)
│   ├── content/          # Helpers tables de contenu (content_eggs, content_quests, etc.) + éditeur quêtes partagé
│   └── ...
├── modules/              # Modules fonctionnels
│   ├── events/           # Module Événements — voir docs/events/README.md
│   ├── bonus/            # Module Bonus (catalogue types, shortcodes) — voir docs/bonus/README.md + docs/BONUS_SOURCE_AND_BLOCKS.md
│   ├── blocks/           # Module Blocs Gutenberg — voir docs/blocks/README.md
│   ├── pokemon/          # Module Pokémon — voir docs/pokemon/README.md
│   ├── quests/           # Module Quêtes — voir docs/quests/README.md
│   ├── eggs/             # Module Œufs — voir docs/eggs/README.md (metabox aussi chargée par Blocks si inactif)
│   ├── collections/      # Collections GO — voir docs/collections/README.md
│   ├── shop-items/       # Boutique avatar (items + catégories) + stickers en jeu ; écran admin unifié Shop ; données des blocs shop highlights — voir docs/shop-items/README.md
│   ├── user-profiles/    # Profils UM, codes amis — voir docs/user-profiles/README.md
│   ├── games/            # Pokedle, leaderboard (module en évolution ; pas de docs/ dédié pour l’instant)
│   └── ...
├── assets/               # Ressources statiques
│   ├── css/
│   └── js/
└── docs/                 # Documentation
```

## 📦 Organisation des modules

**Enregistrement (source unique)** : la liste des modules (slug, chemin, libellé) est définie une seule fois dans **`includes/settings/settings-modules.php`** via `poke_hub_get_modules_config()`. Réglages > General, chargement des modules et sanitize s’appuient sur cette source. Pour ajouter un module, ajouter une entrée dans ce tableau uniquement.

Chaque module suit cette structure standard :

```
modules/{module}/
├── {module}.php          # Point d'entrée du module
├── admin/                # Interface d'administration
│   ├── {module}-admin.php
│   └── forms/            # Formulaires admin
├── functions/            # Fonctions du module
│   ├── {module}-helpers.php    # Helpers de données
│   ├── {module}-render.php     # Fonctions de rendu HTML
│   ├── {module}-queries.php    # Requêtes base de données
│   └── ...
├── public/               # Front-end
│   ├── shortcode-{module}.php
│   └── {module}-front-routing.php
└── README.md             # Documentation du module
```

## 🎯 Règles d'organisation

### 1. Blocs Gutenberg

**Tous les blocs** → `modules/blocks/blocks/{block-name}/`

```
modules/blocks/
├── blocks/
│   ├── event-dates/      # Bloc "Dates d'événement"
│   │   ├── block.json    # Métadonnées
│   │   ├── index.js      # Script éditeur (minimal)
│   │   └── render.php    # Rendu serveur
│   ├── bonus/            # Bloc "Bonus"
│   └── event-quests/     # Bloc "Quêtes"
```

**Fonctions de rendu** → Dans les modules respectifs

- `pokehub_render_event_dates()` → `modules/events/functions/events-render.php`
- `pokehub_render_bonuses_visual()` → `modules/bonus/functions/bonus-helpers.php` (chargé par le module Blocks)
- `pokehub_blocks_render_event_quests()` → `modules/blocks/functions/blocks-field-research.php` (Field Research / bloc `event-quests`)

**Pourquoi ?** Les fonctions de rendu sont spécifiques à chaque domaine métier. La gestion des quêtes (menus, CRUD) est dans le module Quêtes ; le bloc event-quests et la metabox sont côté Events/Blocks.

### 2. Helpers de données

**Helpers** → Dans les modules respectifs

- `poke_hub_events_get_post_dates()` → `modules/events/functions/events-helpers.php`
- `pokehub_get_bonuses_for_post()` → `modules/bonus/functions/bonus-helpers.php` (chargé par le module Blocks ; types de bonus depuis site principal, voir docs/BONUS_SOURCE_AND_BLOCKS.md)

**Pourquoi ?** Les helpers manipulent les données spécifiques à chaque module.

### 3. Fonctions de rendu HTML

**Rendu** → Dans les modules respectifs

- `modules/events/functions/events-render.php` - Rendu des événements
- `modules/bonus/functions/bonus-helpers.php` - Rendu des bonus (chargé par le module Blocks)
- `modules/events/functions/events-quests-render.php` - Rendu des quêtes

**Pourquoi ?** Le rendu est spécifique à chaque domaine métier.

## ✅ Checklist pour ajouter une fonctionnalité

### Nouveau bloc Gutenberg

- [ ] Créer `modules/blocks/blocks/{mon-bloc}/`
- [ ] Créer `block.json` avec les métadonnées
- [ ] Créer `index.js` (minimal pour PHP-only)
- [ ] Créer `render.php` qui appelle les helpers et fonctions de rendu
- [ ] Enregistrer dans `modules/blocks/functions/blocks-register.php`
- [ ] Ajouter la fonction de rendu dans le module concerné (events/bonus/etc.)

### Nouvelle fonction de rendu

- [ ] Ajouter dans le fichier `{module}-render.php` ou `{module}-helpers.php`
- [ ] Utiliser les helpers du module pour récupérer les données
- [ ] Retourner du HTML propre et échappé

### Nouveau helper

- [ ] Ajouter dans `{module}-helpers.php`
- [ ] Préfixer avec le nom du module (ex: `pokehub_`, `poke_hub_events_`)
- [ ] Documenter avec PHPDoc

## 🚫 À éviter

1. ❌ **Dupliquer les fonctions** - Utiliser `function_exists()` si nécessaire
2. ❌ **Mélanger les responsabilités** - Helpers ≠ Rendu ≠ Blocs
3. ❌ **Créer des fichiers "content-blocks"** - Obsolète, utiliser le module `blocks`
4. ❌ **Mettre la logique métier dans `render.php`** - Utiliser les helpers
5. ❌ **Créer des fichiers isolés** - Toujours dans un module approprié

## 📚 Documentation

- **Index** : tout fichier de référence significatif est répertorié dans **[docs/README.md](./README.md)** (éviter les « îlots » non listés).
- **Conventions** : **[docs/REDACTION.md](./REDACTION.md)** (langue, liens, nommage **Poké HUB** / `poke-hub` / `poke_hub_`).
- **Par module** : une entrée dans `docs/` (racine ou sous-dossier `docs/{domaine}/`) et/ou un `README.md` **dans** le module lorsque c’est pertinent ; pas d’obligation de dupliquer les deux.
- **Code** : **PHPDoc** sur les fonctions publiques et points d’extension utiles.

## 🔍 Références

- [Index de la documentation](./README.md)
- [Formulaires admin (UX liste / retour)](./ADMIN_FORM_UX.md)
- [Charte rédactionnelle](./REDACTION.md)
- [Architecture des Blocs](./blocks/ARCHITECTURE.md)
- [Types de Blocs](./blocks/BLOCK_TYPES.md)
- [Guide de Création Rapide](./blocks/QUICK_START.md)
- [Module Quêtes](./quests/README.md)

---

*Index de la documentation : [README du dossier docs](README.md) · [Charte rédactionnelle](REDACTION.md)*
