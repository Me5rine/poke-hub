# Biomes (Pokémon GO) — admin et base de données

Les **biomes** décrivent les habitats en jeu (où certains Pokémon apparaissent), avec noms FR/EN, slug, description, images de fond et liste d’espèces associées. La relation Pokémon ↔ biomes est **many-to-many** : un Pokémon peut être lié à plusieurs biomes et un biome à plusieurs Pokémon.

## Administration

### Onglet dédié

**Poké HUB → Pokémon → Biomes**

- Liste (recherche, tri par nom ou slug, actions groupées).
- **Ajout / édition** : `name_en` et `name_fr` (obligatoires), **slug** (optionnel, généré à partir du nom anglais via `sanitize_title()` si vide), **description** (éditeur WordPress), **plusieurs images de fond** (URL + médiathèque, ordre conservé), **Pokémon** du biome (Select2, comme pour les fonds).

### Fiche Pokémon

Dans la section **« Regional availability »** (disponibilité régionale), le formulaire est découpé en **deux sous-parties** :

1. **Biomes** — sélection multiple des biomes où l’espèce peut apparaître (Pokémon GO).
2. **Regional** — contenu existant : case « Is regional? », ID de carte, description, pays, régions géographiques (`pokemon_regional_mappings`), etc.

La liste des Pokémon (onglet **Pokémon**) propose un filtre **« All biomes »** / biome précis (jointure sur la table de liens).

## Tables SQL

Créées par `dbDelta` dans `Pokehub_DB::createPokemonTables()` lorsque le module **Pokémon** est actif (`includes/pokehub-db.php`).

| Table | Rôle |
|--------|------|
| `{prefix}pokehub_pokemon_biomes` | Entité biome : `slug` (unique), `name_en`, `name_fr`, `description`, `extra`, dates. |
| `{prefix}pokehub_pokemon_biome_images` | Une ligne par image de fond : `biome_id`, `image_url`, `sort_order`. |
| `{prefix}pokehub_pokemon_biome_pokemon_links` | Liaison N–N : `biome_id`, `pokemon_id`, contrainte unique sur la paire, index sur les deux clés. |

### Interrogation typique

- Pokémon d’un biome :  
  `SELECT pokemon_id FROM …_pokemon_biome_pokemon_links WHERE biome_id = ?`
- Biomes d’un Pokémon :  
  `SELECT biome_id FROM …_pokemon_biome_pokemon_links WHERE pokemon_id = ?`

## Clés `pokehub_get_table()`

Définies dans `includes/functions/pokehub-helpers.php` :

- `pokemon_biomes` (alias logique : `biomes`)
- `pokemon_biome_images`
- `pokemon_biome_pokemon_links`

## Helpers PHP

Fichier : `modules/pokemon/includes/pokemon-biomes-helpers.php` (chargé depuis `modules/pokemon/pokemon.php`).

| Fonction | Usage |
|----------|--------|
| `poke_hub_pokemon_get_pokemon_biome_ids( int $pokemon_id )` | IDs des biomes liés à un Pokémon. |
| `poke_hub_pokemon_sync_pokemon_biome_links( int $pokemon_id, array $biome_ids )` | Remplace les liens côté fiche Pokémon. |
| `poke_hub_pokemon_get_biome_image_urls( int $biome_id )` | URLs d’images triées. |
| `poke_hub_pokemon_sync_biome_images( int $biome_id, array $image_urls )` | Remplace les images d’un biome (ordre = ordre du tableau). |
| `poke_hub_pokemon_sync_biome_pokemon_links( int $biome_id, array $pokemon_ids )` | Remplace les Pokémon d’un biome (formulaire biome). |

## Fichiers principaux (référence code)

- `includes/pokehub-db.php` — schéma des trois tables + `dbDelta`
- `includes/functions/pokehub-helpers.php` — mapping des noms de tables
- `modules/pokemon/admin/pokemon-admin.php` — onglet **Biomes**, écran liste, formulaire add/edit
- `modules/pokemon/admin/sections/biomes.php` — liste, handlers POST / suppression
- `modules/pokemon/admin/forms/biome-form.php` — formulaire biome
- `modules/pokemon/admin/forms/pokemon-form.php` — section *Regional availability* (Biomes + Regional)
- `modules/pokemon/admin/sections/pokemon.php` — enregistrement `pokemon_biome_ids`, filtre liste, nettoyage des liens à la suppression d’un Pokémon

## Select2 et médias

Comme pour les fonds (**Backgrounds**), l’onglet **Biomes** et la fiche Pokémon enregistrent Select2 via `poke_hub_pokemon_admin_enqueue_assets` ; la médiathèque est disponible sur les formulaires concernés (`wp_enqueue_media`, frame `MediaFrame.PokeHubTypes` où applicable).

## Voir aussi

- [README-REGIONAL-AUTO-CONFIG.md](./README-REGIONAL-AUTO-CONFIG.md) — import Game Master et Pokémon régionaux (indépendant des biomes, mais la fiche admin regroupe biomes et champs régionaux dans la même section).

---

*Index de la documentation : [README du dossier docs](../README.md) · [Charte rédactionnelle](../REDACTION.md)*
