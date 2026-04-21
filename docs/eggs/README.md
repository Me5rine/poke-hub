# Module Œufs – Poké HUB

Le module **eggs** gère les **pools d’œufs** (contenus `content_eggs` + lignes `content_egg_pokemon`), la **metabox Œufs** sur les articles / événements, le **shortcode** agrégé et les helpers d’affichage (tri par distance, Adventure Sync, rareté). Les **types d’œuf** (2 km, 5 km, etc.) viennent du **module Pokémon** (`pokemon_egg_types`).

Le **bloc Gutenberg** « œufs » et le **CSS front** associés sont fournis par le **module Blocks** et **ne dépendent pas** de l’activation du module Eggs.

## Activation

1. **Poké HUB → Settings → General**
2. Cochez **Eggs** (slug interne : `eggs` dans `includes/settings/settings-modules.php`).
3. Enregistrez.

Sans activation, `modules/eggs/eggs.php` ne charge pas : pas de menu **Eggs**, pas de shortcode `[pokehub_all_eggs]`, pas de chargement direct de `eggs-metabox.php` par le module.

## Menu admin

- **Emplacement :** Poké HUB → **Eggs**
- **Capability :** `manage_options`
- **Page :** `admin.php?page=poke-hub-eggs` (`poke_hub_admin_menu_eggs_register()` dans `modules/eggs/admin/eggs-admin.php`)

L’admin permet de créer / éditer des **pools globaux** (`source_type` = `global_pool`, `source_id` = 0) avec nom, période (month / season + valeur), dates optionnelles, et d’associer des **Pokémon** par type d’œuf et rareté (voir formulaires dans le même fichier).

## Metabox et module Blocks

- **Module Eggs actif :** `modules/eggs/admin/eggs-metabox.php` est chargé par `eggs.php` ; metabox **Eggs** sur les post types filtrés par `pokehub_eggs_post_types` (défaut : `post`, `pokehub_event`).
- **Module Eggs inactif :** le module **Blocks** charge quand même la metabox (et une partie des helpers) pour que le **bloc** œufs reste éditable sur le site (`modules/blocks/blocks.php`, commentaire explicite).

Sauvegarde / lecture des données par contenu : **`includes/content/content-helpers.php`** — `pokehub_content_get_eggs()`, `pokehub_content_save_eggs()`, `pokehub_content_get_eggs_row()`, agrégation `pokehub_content_get_all_eggs_aggregated_at()`, etc.

## Shortcode front

| Shortcode | Fichier | Rôle |
|-----------|---------|------|
| `[pokehub_all_eggs]` | `modules/eggs/public/eggs-shortcode.php` | Affiche tous les œufs **actifs** à l’instant T, sections par type d’œuf (distance, normaux puis Adventure Sync, rareté). Attribut optionnel `title`. Enqueue `poke-hub-eggs-front.css`. |

## Bloc Gutenberg

- Slug bloc : **`pokehub/eggs`** (enregistrement dans `modules/blocks/functions/blocks-register.php`, `requires` vide — pas de dépendance au module Eggs).
- Helpers dédiés : `modules/blocks/functions/blocks-eggs-helpers.php`
- Styles front partagés avec le shortcode : `assets/css/poke-hub-eggs-front.css`

## Tables (logique `pokehub_get_table()`)

Scope **`content_source`** (même préfixe que Réglages → Sources → Pokémon table prefix).

| Clé logique | Rôle |
|-------------|------|
| `content_eggs` | Une ligne par source ou pool (`source_type`, `source_id`, `name`, `start_ts`, `end_ts`, …) |
| `content_egg_pokemon` | Pokémon dans un pool / contenu (`content_egg_id`, `egg_type_id`, rareté, shiny forcé, ordre, …) |

Les types d’œuf affichés utilisent la table **`pokemon_egg_types`** (module Pokémon, scope préfixe Pokémon).

Création des tables : cycle d’installation dans **`includes/pokehub-db.php`** lorsque le module Eggs (ou certains autres modules de contenu) est concerné.

## Arborescence du code

```
modules/eggs/
├── eggs.php
├── functions/
│   ├── eggs-helpers.php    # Types d’œuf, agrégation affichage, rendu section
│   └── eggs-pages.php
├── admin/
│   ├── eggs-admin.php      # Menu, pools globaux, UI
│   └── eggs-metabox.php    # Metabox article / événement
└── public/
    └── eggs-shortcode.php  # [pokehub_all_eggs]
```

## Voir aussi

- [CONTENT_BLOCKS.md](../CONTENT_BLOCKS.md) — Bloc œufs, contenu distant, tables
- [blocks/README.md](../blocks/README.md) — Liste des blocs
- [blocks/ARCHITECTURE.md](../blocks/ARCHITECTURE.md) — Chargement conditionnel metabox Eggs
- [ORGANISATION.md](../ORGANISATION.md) — Arborescence `modules/`
- [TRANSLATION.md](../TRANSLATION.md) — Text domain `poke-hub`

---

*Index de la documentation : [README du dossier docs](../README.md) · [Charte rédactionnelle](../REDACTION.md)*
