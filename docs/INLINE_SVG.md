# SVG inline depuis le bucket (helpers globaux)

Les assets **SVG** du bucket (types Pokémon, bonus, etc.) sont rendus en **markup inline** (`<span>` + `<svg>`), pas en `<img>`, après fetch HTTP(S) ou lecture fichier local et sanitisation `wp_kses`.

Ces helpers sont **toujours chargés avec le plugin** (`plugins_loaded`, priorité 15), **sans dépendre d’un module** activé.

## Fichiers et ordre de chargement

Défini dans `poke-hub.php`, fonction `poke_hub_load_pokemon_public_helpers()` :

1. **`includes/functions/pokehub-inline-svg.php`** — moteur générique  
   - Préfixe des fonctions internes : `pokehub_inline_svg_*` (KSES, fetch, résolution URL locale, etc.).  
   - **API publique** : `pokehub_render_inline_svg_from_url( string $svg_url, array $args = [] ): string`  
   - Arguments courants : `class` (défaut `pokehub-inline-svg`), `color` (ex. `currentColor`), `aria_hidden` (bool).

2. **`includes/functions/pokemon-public-helpers.php`** — bucket, chemins d’assets, Pokémon partagés, **et** rendu bonus :  
   - `poke_hub_render_bonus_asset_markup( string $slug, array $args )` : essaie d’abord `{slug}.svg` via `pokehub_render_inline_svg_from_url()`, puis repli **raster** WebP → PNG → JPG (`poke_hub_render_bucket_raster_img( 'bonus', … )`).  
   - `poke_hub_get_bonus_icon_url( string $slug )` : URL du `.svg` bonus.  
   - **Front** : la couleur / le cadre des vignettes bonus se pilote en **CSS** (variables `--pokehub-bonus-icon-*` dans `poke-hub-bonus-front.css`) — voir [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md) (section Thème / CSS).

3. **`includes/functions/pokehub-pokemon-type-icon.php`** — couche **types Pokémon** uniquement :  
   - `poke_hub_get_type_icon_url( string $slug )` : URL `.svg` du type (chemin `types` dans Sources).  
   - `pokehub_render_pokemon_type_icon_html( string $icon_url, array $args )` : délègue à `pokehub_render_inline_svg_from_url()` avec les classes UI types (`pokehub-type-icon`, etc.).  
   - Enregistrement de la feuille **`pokehub-type-icons`** (`assets/css/poke-hub-type-icons.css`) sur `init`.

## Filtres

- **`pokehub_inline_svg_remote_sslverify`** — booléen pour `wp_remote_get` lors du fetch d’une URL distante (recommandé pour les extensions).

- **`pokehub_type_icon_remote_sslverify`** — *déprécié* ; toujours appliqué après le précédent pour compatibilité ascendante.

## Documentation liée

- **Bonus** (catalogue, slug unique, priorité SVG) : [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md)  
- **Images raster Pokémon / bucket** : [POKEMON_IMAGES.md](./POKEMON_IMAGES.md)

---

*Index de la documentation : [README du dossier docs](README.md) · [Charte rédactionnelle](REDACTION.md)*
