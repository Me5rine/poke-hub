# Module Bonus – Poké HUB

Le module **bonus** fournit le **catalogue des types de bonus** (table `bonus_types` sur le site principal), l’**admin CRUD** lorsque la source est **locale**, les **helpers** de rendu (icônes bucket / SVG) et les **shortcodes** d’affichage.

Le **bloc Gutenberg Bonus** et la **metabox « Bonus de l’événement »** sont chargés uniquement par le **module Blocks** ; ils **ne nécessitent pas** que le module Bonus soit activé pour fonctionner (les types viennent toujours de la table effective, locale ou distante). Voir la doc détaillée bloc / remote : **[BONUS_SOURCE_AND_BLOCKS.md](../BONUS_SOURCE_AND_BLOCKS.md)**.

## Activation

1. **Poké HUB → Settings → General**
2. Cochez **Bonus** (slug : `bonus`).
3. Enregistrez.

## Menu admin (catalogue)

- **Emplacement :** Poké HUB → **Bonus**
- **Page :** `admin.php?page=poke-hub-bonus-types`
- **Rendu :** `pokehub_render_bonus_types_admin_page()` (`modules/bonus/admin/bonus-types-admin.php`)
- **Enregistrement du menu :** `poke_hub_admin_menu_bonus()` dans **`poke-hub.php`** (priorité 12) — uniquement si le module est actif **et** la source bonus n’est **pas** distante.

### Source distante (site miroir)

Si le **préfixe Pokémon** (Réglages → Sources) pointe vers une autre base que le préfixe WordPress local, `pokehub_bonus_use_remote_source()` est vrai : les types sont lus via **`pokehub_get_bonus_types_table()`** (`remote_bonus_types` / table distante selon config) et le **menu Bonus est masqué** — l’édition du catalogue se fait sur le **site principal**.

Helpers : **`includes/functions/pokehub-helpers.php`** — `pokehub_get_bonus_types_table()`, `pokehub_bonus_use_remote_source()`.

## Shortcodes

| Shortcode | Fichier | Rôle |
|-----------|---------|------|
| `[pokehub-bonus bonus="slug1: desc, slug2: desc"]` | `modules/bonus/functions/bonus-shortcodes.php` | Affiche une liste de bonus par **slug** catalogue, avec texte optionnel après `:` |
| `[pokehub-event-bonuses post_id=""]` | idem | Rend les bonus liés au post (ID courant si `post_id` vide) via `pokehub_render_post_bonuses()` |

## Fichiers principaux

```
modules/bonus/
├── bonus.php
├── admin/
│   └── bonus-types-admin.php   # Liste, formulaire, delete GET, URL pokehub_bonus_types_admin_url()
└── functions/
    ├── bonus-helpers.php       # Select, get by id/slug, rendu visuel, contenu post
    └── bonus-shortcodes.php
```

Assets front : `assets/css/poke-hub-bonus-front.css` (enqueue depuis `bonus.php` ; le module Blocks peut aussi l’enqueue pour l’éditeur / cohérence).

## Filtres utiles

- **`pokehub_bonus_description`** — filtre la description HTML enrichie (`bonus-helpers.php`, contexte objet bonus).

## Voir aussi

- [BONUS_SOURCE_AND_BLOCKS.md](../BONUS_SOURCE_AND_BLOCKS.md) — Schéma `content_bonus`, bloc, metabox, icônes, CSS `--pokehub-bonus-icon-*`
- [CONTENT_BLOCKS.md](../CONTENT_BLOCKS.md) — Blocs de contenu
- [blocks/README.md](../blocks/README.md) — Bloc `pokehub/bonus`
- [INLINE_SVG.md](../INLINE_SVG.md) — Rendu SVG / raster bonus
- [ORGANISATION.md](../ORGANISATION.md) — Structure des modules
- [TRANSLATION.md](../TRANSLATION.md) — Chaînes `poke-hub`

---

*Index de la documentation : [README du dossier docs](../README.md) · [Charte rédactionnelle](../REDACTION.md)*
