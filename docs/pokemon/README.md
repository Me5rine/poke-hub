# Module Pokémon – Poké HUB (index)

Le module **pokemon** est le plus large : taxonomies (types, régions, générations, …), fiches espèces / formes, import **Game Master**, images, **biomes**, outils admin et helpers partagés par le reste du plugin.

Ce **README** regroupe les liens vers les guides déjà rédigés dans `docs/pokemon/`.

## Pages de documentation

| Document | Contenu principal |
|----------|-------------------|
| [BIOMES.md](./BIOMES.md) | Biomes GO : tables, admin, fiche Pokémon, helpers |
| [DATA_SAFETY.md](./DATA_SAFETY.md) | Import GM, non-écrasement des données, règles de fusion |
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
