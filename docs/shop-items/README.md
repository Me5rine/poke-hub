# Module Shop items – Poké HUB

Le module **shop-items** regroupe la **boutique avatar** (catégories + articles) et les **stickers en jeu** (catalogue sans catégorie). Il fournit l’**administration unifiée** (écran **Shop** sous Poké HUB), les **helpers catalogue** (URLs bucket, CRUD, recherche) et s’appuie sur les **tables** créées avec le reste du contenu (scope `content_source`, même préfixe que les tables Pokémon).

Les **blocs Gutenberg** et les **metaboxes** d’article associés sont chargés par le **module Blocks** dès que le **schéma SQL** attendu est présent (voir section *Rapport avec le module Blocks*).

## Activation

1. **Poké HUB → Settings → General**
2. Cochez **Avatar shop items** (slug interne : `shop-items` dans `includes/settings/settings-modules.php`).
3. Enregistrez.

Sans activation, le fichier `modules/shop-items/shop-items.php` ne charge pas : pas de menu Shop, pas de chargement des écrans admin du module (les helpers peuvent toutefois être inclus par Blocks si les tables existent déjà).

## Menu admin

- **Emplacement :** Poké HUB → **Shop**
- **Capability :** `manage_options`
- **Page unique :** `admin.php?page=poke-hub-shop-items` avec **`&tab=`** :
  - **`avatar`** — liste et édition des **articles** boutique avatar (`shop_avatar_items`), liaison optionnelle aux **événements** (`shop_avatar_item_events`).
  - **`categories`** — **catégories** avatar (`shop_avatar_categories`) ; les articles référencent `category_id`.
  - **`stickers`** — **stickers** (`shop_sticker_items`), liaison optionnelle aux événements (`shop_sticker_item_events`).

### Navigation par onglets

Le cadre d’onglets est rendu par `poke_hub_shop_items_admin_render_list_frame_start()` dans `modules/shop-items/shop-items.php` :

- Groupe **Avatar shop** : onglets *Items* et *Categories* (les catégories sont explicitement rattachées à la boutique avatar).
- Groupe **In-game stickers** : onglet *Stickers*.

Styles : classes `.poke-hub-shop-items-nav*` dans `assets/css/admin-unified.css` — voir [ADMIN_CSS.md](../ADMIN_CSS.md).

### Anciennes URLs admin

Une redirection **admin_init** (priorité `0`) mappe les anciennes pages vers la page unifiée :

| Ancienne `page` | Onglet cible |
|-----------------|--------------|
| `poke-hub-shop-avatar` | `tab=avatar` |
| `poke-hub-shop-avatar-categories` | `tab=categories` |
| `poke-hub-shop-stickers` | `tab=stickers` |

Fonction : `poke_hub_shop_items_legacy_admin_redirect()`.

### Titre d’onglet navigateur

Filtre `admin_title` : `poke_hub_shop_items_change_admin_title()` — libellé selon l’onglet (`poke_hub_shop_items_get_tab_section_label()`).

## Arborescence du code

```
modules/shop-items/
├── shop-items.php              # Point d’entrée : constantes, menu, router, onglets, redirect, screen options
├── includes/
│   ├── shop-avatar-helpers.php # Catalogue avatar : bucket URLs, CRUD, recherche, liaison événements
│   └── shop-sticker-helpers.php# Catalogue stickers : idem
└── admin/
    ├── shop-avatar-admin.php           # UI liste / formulaires avatar + catégories, suppressions GET
    ├── shop-avatar-item-form-ajax.php  # AJAX formulaire item avatar
    ├── shop-sticker-admin.php          # UI liste stickers
    ├── shop-sticker-item-form-ajax.php
    ├── class-poke-hub-shop-avatar-items-list-table.php
    ├── class-poke-hub-shop-avatar-categories-list-table.php
    ├── class-poke-hub-shop-sticker-items-list-table.php
    └── js/
        ├── shop-avatar-item-form-events.js
        └── shop-sticker-item-form-events.js
```

## Tables (logique `pokehub_get_table()`)

Création / migration : `includes/pokehub-db.php` (méthode qui exécute les `CREATE TABLE` / `dbDelta` pour le bloc shop dans le cycle d’installation des tables du plugin).

| Clé logique | Rôle |
|-------------|------|
| `shop_avatar_categories` | Catégories boutique avatar |
| `shop_avatar_items` | Articles (noms FR/EN, slug, `category_id`, ordre, etc.) |
| `shop_avatar_item_events` | Liaison N–N item ↔ `special_events` |
| `content_shop_avatar` | Une ligne par contenu (`source_type`, `source_id`) + image de couverture (`hero_attachment_id`) |
| `content_shop_avatar_entries` | Ordre des items sélectionnés pour ce contenu |
| `shop_sticker_items` | Stickers (noms, slug, ordre) |
| `shop_sticker_item_events` | Liaison N–N sticker ↔ `special_events` |
| `content_shop_sticker` | Même idée que `content_shop_avatar` pour les stickers |
| `content_shop_sticker_entries` | Ordre des stickers pour le contenu |

**Préfixe :** scope **`content_source`** (Réglages → Sources → *Pokémon table prefix (remote)*). Même règle que les autres contenus (quêtes, bonus, etc.).

## Contenu article / événement (sauvegarde & lecture)

Les helpers de **liaison post ↔ données** sont dans **`includes/content/content-helpers.php`** (préfixe `pokehub_content_*`), par exemple :

- `pokehub_content_get_shop_avatar()` / `pokehub_content_save_shop_avatar()`
- `pokehub_content_get_shop_sticker()` / `pokehub_content_save_shop_sticker()`

Les **metaboxes** sous l’éditeur enregistrent dans ces tables ; les **blocs** front lisent les mêmes données.

## Images (bucket)

Les vignettes catalogue attendent des fichiers **`{slug}.webp`** puis repli **`{slug}.png`** sur le bucket.

- **Base URL :** option `poke_hub_assets_bucket_base_url` (onglet Sources).
- **Sous-dossiers :**
  - Avatar shop : `poke_hub_assets_path_avatar_shop` (défaut `/pokemon-go/avatar-shop/`).
  - Stickers : `poke_hub_assets_path_in_game_stickers` (défaut `/pokemon-go/in-game-stickers/`).

Saisie : **Poké HUB → Settings → Sources** (`includes/settings/tabs/settings-tab-sources.php`).

Helpers : `poke_hub_shop_avatar_get_assets_base_url()`, `poke_hub_shop_sticker_get_assets_base_url()`, puis `poke_hub_shop_*_get_item_image_urls()`.

## Rapport avec le module Blocks

- **Enregistrement des blocs** : `modules/blocks/functions/blocks-register.php` — blocs `pokehub/shop-avatar-highlights` et `pokehub/shop-sticker-highlights` ; garde-fou `pokehub_blocks_shop_avatar_schema_ready()` / `pokehub_blocks_shop_sticker_schema_ready()` dans `modules/blocks/functions/blocks-helpers.php`.
- **Metaboxes + AJAX** : `modules/blocks/blocks.php` — si le module **Blocks** est actif **et** le schéma avatar ou sticker est prêt, le plugin charge les helpers depuis `modules/shop-items/includes/*.php`, les fichiers `blocks-shop-*-metabox.php` et, en admin, les `*-metabox-ajax.php`. *Le module shop-items peut être désactivé tant que les tables existent encore ; l’admin catalogue complet (menu Shop) nécessite en pratique d’activer shop-items.*

Filtres utiles pour les post types des metaboxes :

- `pokehub_shop_avatar_metabox_post_types` (défaut : `post`, `pokehub_event`)
- `pokehub_shop_sticker_metabox_post_types`

Documentation détaillée des blocs (texte d’accroche, tuiles, CSS) : [CONTENT_BLOCKS.md](../CONTENT_BLOCKS.md), [blocks/README.md](../blocks/README.md), [POKEHUB_CSS_CLASSES.md](../POKEHUB_CSS_CLASSES.md).

## Helpers utiles (références rapides)

| Zone | Fichier | Exemples |
|------|---------|----------|
| Onglets / URLs admin | `shop-items.php` | `poke_hub_shop_items_admin_tab()`, `poke_hub_shop_items_admin_url()` |
| Avatar catalogue | `includes/shop-avatar-helpers.php` | `poke_hub_shop_avatar_get_item_image_urls()`, `poke_hub_shop_avatar_create_item()`, `poke_hub_shop_avatar_search_items()` |
| Stickers catalogue | `includes/shop-sticker-helpers.php` | équivalents `poke_hub_shop_sticker_*` |

## Voir aussi

- [CONTENT_BLOCKS.md](../CONTENT_BLOCKS.md) — Blocs shop highlights, metaboxes, tables de contenu
- [blocks/README.md](../blocks/README.md) — Liste des blocs et dépendances
- [blocks/ARCHITECTURE.md](../blocks/ARCHITECTURE.md) — Arborescence Blocks + cas shop
- [TRANSLATION.md](../TRANSLATION.md) — Chaînes en anglais dans le code (`poke-hub`)
- [ADMIN_CSS.md](../ADMIN_CSS.md) — Styles onglets Shop (`.poke-hub-shop-items-nav*`)
- [ORGANISATION.md](../ORGANISATION.md) — Vue d’ensemble des dossiers `modules/`

---

*Index de la documentation : [README du dossier docs](../README.md) · [Charte rédactionnelle](../REDACTION.md)*
