# Module Pokémon – Poké HUB (index)

Le module **pokemon** est le plus large : taxonomies (types, régions, générations, …), fiches espèces / formes, import **Game Master**, images, **biomes**, outils admin et helpers partagés par le reste du plugin.

L’**import Game Master**, l’onglet **Translation** (données multilingues Pokémon) et **Images sync** (manifest CSV d’icônes, **sans** filtre sur les dates de sortie — voir lien § *Images sync*) sont sous **Poké HUB → Temporary tools** — [ADMIN_TEMPORARY_TOOLS.md](../ADMIN_TEMPORARY_TOOLS.md).

Ce **README** regroupe les liens vers les guides déjà rédigés dans `docs/pokemon/`.

## Pages de documentation

| Document | Contenu principal |
|----------|-------------------|
| [GAME_MASTER_IMPORT.md](./GAME_MASTER_IMPORT.md) | Import GM : passes, PASS 3 `pokemon_evolutions` (résolution hors `*-family`, `extra` protos / `evolution_source`), placeholders `*-family` (écriture + usage hors collections), Gigamax, variants (`category`, **Forces / Amovénus** avant `ibfc`, alias granular → registre, filtres WP, garde **slugs supprimés**), squelettes **`gm_skeleton`**, liens Collections |
| [BIOMES.md](./BIOMES.md) | Biomes GO : tables, admin, fiche Pokémon, helpers |
| [DATA_SAFETY.md](./DATA_SAFETY.md) | Import GM, non-écrasement des données, règles de fusion, heuristique **génération ↔ dex** ; `pokemon_evolutions.extra` GM ; suppression admin (**liaisons** avant `DELETE` Pokémon) |
| [README-REGIONAL-AUTO-CONFIG.md](./README-REGIONAL-AUTO-CONFIG.md) | Configuration auto des Pokémon régionaux |
| [POKEKALOS_RELEASE_DATES.md](./POKEKALOS_RELEASE_DATES.md) | Import dates de sortie (admin *Temporary tools* / CLI) |

## Documentation transverse (Pokémon + assets)

- [POKEMON_IMAGES.md](../POKEMON_IMAGES.md) — Images espèces, items, conventions WebP / PNG
- [COLLECTIONS_AND_FORMS_CATEGORIES.md](../COLLECTIONS_AND_FORMS_CATEGORIES.md) — Formes vs collections

## Rappels code

- Point d’entrée : `modules/pokemon/pokemon.php`
- Préfixe tables : Réglages → Sources → **Pokémon table prefix** (local ou remote) — sert aussi aux **contenus** (`content_*`) sur les sites distants

## Voir aussi

- [ORGANISATION.md](../ORGANISATION.md) — Arborescence `modules/pokemon/`
- [blocks/README.md](../blocks/README.md) — Blocs qui consomment les données Pokémon

---

*Index de la documentation : [README du dossier docs](../README.md) · [Charte rédactionnelle](../REDACTION.md)*
