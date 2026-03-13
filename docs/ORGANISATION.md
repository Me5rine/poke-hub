# 🗂️ Guide d'Organisation du Code

## 📋 Vue d'ensemble

Ce guide explique comment organiser le code dans le plugin Poké HUB pour maintenir une structure claire et cohérente.

## 🏗️ Structure générale

```
poke-hub/
├── includes/              # Code partagé entre modules
│   ├── settings/         # Gestion des paramètres
│   └── ...
├── modules/              # Modules fonctionnels
│   ├── events/           # Module Événements
│   ├── bonus/            # Module Bonus
│   ├── blocks/           # Module Blocs Gutenberg
│   ├── pokemon/          # Module Pokémon
│   └── ...
├── assets/               # Ressources statiques
│   ├── css/
│   └── js/
└── docs/                 # Documentation
```

## 📦 Organisation des modules

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
- `pokehub_render_bonuses_visual()` → `modules/bonus/functions/bonus-helpers.php`
- `pokehub_render_quests_visual()` → `modules/events/functions/events-quests-render.php`

**Pourquoi ?** Les fonctions de rendu sont spécifiques à chaque domaine métier.

### 2. Helpers de données

**Helpers** → Dans les modules respectifs

- `poke_hub_events_get_post_dates()` → `modules/events/functions/events-helpers.php`
- `pokehub_get_bonuses_for_post()` → `modules/bonus/functions/bonus-helpers.php`

**Pourquoi ?** Les helpers manipulent les données spécifiques à chaque module.

### 3. Fonctions de rendu HTML

**Rendu** → Dans les modules respectifs

- `modules/events/functions/events-render.php` - Rendu des événements
- `modules/bonus/functions/bonus-helpers.php` - Rendu des bonus
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

Chaque module doit avoir :

- **README.md** à la racine du module
- **Documentation dans `docs/{module}/`** pour les détails
- **PHPDoc** sur toutes les fonctions publiques

## 🔍 Références

- [Architecture des Blocs](./modules/blocks/docs/ARCHITECTURE.md)
- [Types de Blocs](./modules/blocks/docs/BLOCK_TYPES.md)
- [Guide de Création Rapide](./modules/blocks/docs/QUICK_START.md)













