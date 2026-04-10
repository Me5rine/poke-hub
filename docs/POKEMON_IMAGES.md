# Structure des Images Pokémon

## Vue d'ensemble

Le système de gestion des images Pokémon est déjà en place dans le plugin. Ce document explique le fonctionnement actuel et la structure mise en œuvre.

## Fichiers principaux

### `includes/functions/pokemon-public-helpers.php`

Ce fichier contient toutes les fonctions de gestion des images Pokémon (disponibles même si le module Pokémon n'est pas actif) :

#### Fonctions disponibles

1. **`poke_hub_pokemon_get_assets_base_url()`**
   - URL de base principale des sprites Pokémon
   - Construite uniquement à partir des réglages Sources :
     - `poke_hub_assets_bucket_base_url` (bucket commun)
     - `poke_hub_assets_path_pokemon` (chemin Pokémon, ex. `/pokemon-go/pokemon/`)
   - Retourne une chaîne vide si le bucket n'est pas renseigné

2. **`poke_hub_pokemon_get_assets_fallback_base_url()`**
   - URL de base de secours (même clés de fichiers que la source principale, ex. `pikachu.png`)
   - Option WordPress : `poke_hub_pokemon_assets_fallback_base_url`
   - Retourne une chaîne vide si l'option n'est pas renseignée

   **Bascule automatique (navigateur)** : si la source principale et le fallback sont tous deux configurés et différents, le plugin charge `assets/js/pokehub-pokemon-image-fallback.js` (front et admin). Un écouteur en phase de capture sur l’événement `error` des `<img>` : si l’URL commence par la base principale, elle est remplacée par la même ressource relative sur la base fallback (une seule tentative par image, marquée via l’attribut `data-pokehub-fallback-applied`).

3. **`poke_hub_pokemon_build_image_key_from_slug($slug, array $args = [])`**
   - Construit une clé d'image à partir du slug du Pokémon
   - Paramètres :
     - `$slug` : Le slug du Pokémon (ex: "pikachu", "noibat-headband")
     - `$args` : Tableau d'options :
       - `shiny` (bool) : Si vrai, ajoute "-shiny" à la clé
       - `gender` (string|null) : "male" ou "female" pour ajouter le suffixe de genre
   - Exemples :
     - `pikachu` → `pikachu`
     - `pikachu` + shiny → `pikachu-shiny`
     - `noibat-headband` + male + shiny → `noibat-headband-male-shiny`

4. **`poke_hub_pokemon_get_image_url($pokemon, array $args = [])`**
   - Version simple : retourne uniquement l'URL principale
   - Paramètres :
     - `$pokemon` : Objet Pokémon (doit avoir `slug` ou `dex_number`)
     - `$args` : Tableau d'options (même que `build_image_key_from_slug`)
   - Retourne l'URL complète de l'image principale

5. **`poke_hub_pokemon_get_image_sources($pokemon, array $args = [])`**
   - Version complète : retourne les URLs `primary` et `fallback`
   - Si aucun fallback n'est configuré, `fallback` est identique à `primary`
   - Paramètres :
     - `$pokemon` : Objet Pokémon (doit avoir `slug` ou `dex_number`)
     - `$args` : Tableau d'options :
       - `shiny` (bool) : Si vrai, cherche l'image shiny
       - `gender` (string|null) : "male" ou "female"
       - `variant` (string) : Type de variant (par défaut "sprite", pour futurs sous-dossiers)
   - Retourne un tableau :
     ```php
     [
       'primary'  => string,  // URL principale (peut être vide)
      'fallback' => string,  // URL de secours si configurée, sinon identique à `primary`
     ]
     ```
   - Applique le filtre `poke_hub_pokemon_image_sources` pour personnalisation

## SVG inline (types Pokémon, bonus)

Les fichiers **`.svg`** sur le bucket (chemins **types** et **bonus** dans Sources) sont affichés en **markup inline** (pas en `<img>`) : fetch, sanitisation, classes CSS. Les fonctions sont dans `includes/functions/` et chargées pour tout le site — voir **[INLINE_SVG.md](./INLINE_SVG.md)**.

- **Types** : `poke_hub_get_type_icon_url()`, `pokehub_render_pokemon_type_icon_html()`.
- **Bonus** : `poke_hub_render_bonus_asset_markup()` (SVG d’abord, repli raster WebP → PNG → JPG). **Personnalisation visuelle** (couleur des icônes, fond des vignettes) : variables CSS `--pokehub-bonus-icon-*` dans `assets/css/poke-hub-bonus-front.css` — détail [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md).

## Bonbons (assets raster)

Les images **bonbon** et **bonbon XL** utilisent les clés `{slug}-candy` et `{slug}-xl-candy` dans le dossier configuré pour les candies (Réglages > Poké HUB > Sources). Les formats **WebP, PNG et JPG** peuvent être proposés ; un script front peut enchaîner les extensions si l’URL ne répond pas (voir l’aide dans l’admin Sources).

Pour **quel slug** est utilisé sur les lignées d’évolution (famille de bonbons, bébés), voir [blocks/BLOCK_STYLES_AND_BEHAVIOR.md](./blocks/BLOCK_STYLES_AND_BEHAVIOR.md).

## Configuration

### Paramètres dans l'administration

Les URLs sont configurées dans :
**Réglages > Poke Hub > Sources** (onglet "Sources")

- **Assets bucket base URL** + chemin **Pokémon** (section *Image Sources*) : seule source principale des sprites (`{clé}.png`). Sans bucket renseigné, les helpers renvoient une URL vide.
- **Pokémon assets fallback base URL** (section *Pokémon Sources*) : racine alternative ; les chemins et noms de fichiers doivent correspondre à ceux de la source principale.

Il n’existe plus d’option ni de constante dédiée « URL Pokémon seule » : tout passe par le bucket + le chemin Pokémon. Une ancienne option `poke_hub_pokemon_assets_base_url` éventuellement présente en base n’est plus lue par le plugin ; vous pouvez la supprimer manuellement si besoin.

## Structure des fichiers images

### Conventions de nommage

Le système construit les noms de fichiers selon ce pattern :

```
{slug}-{gender?}-{shiny?}.png
```

### Exemples

| Slug Pokémon | Genre | Shiny | Fichier généré |
|--------------|-------|-------|----------------|
| `pikachu` | - | Non | `pikachu.png` |
| `pikachu` | - | Oui | `pikachu-shiny.png` |
| `noibat-headband` | male | Non | `noibat-headband-male.png` |
| `noibat-headband` | male | Oui | `noibat-headband-male-shiny.png` |
| `001` (dex_number) | - | Non | `001.png` |

### Structure des dossiers (actuelle)

Actuellement, toutes les images sont à la racine de l'URL de base :
```
https://cdn.example.com/pokemon/pikachu.png
https://cdn.example.com/pokemon/pikachu-shiny.png
https://cdn.example.com/pokemon/noibat-headband-male-shiny.png
```

**Note** : Le paramètre `variant` existe mais n'est pas encore utilisé pour créer des sous-dossiers. Un commentaire dans le code suggère :
```php
// Si tu rajoutes des sous-dossiers par variant, adapte ici :
// $path = 'sprites/' . $key . '.png';
```

## Utilisation

### Exemple simple : Récupérer l'URL d'une image normale

```php
// Supposons que $pokemon est un objet avec ->slug ou ->dex_number
$image_url = poke_hub_pokemon_get_image_url($pokemon);
echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($pokemon->name_fr) . '" />';
```

### Exemple avec Shiny

```php
$shiny_url = poke_hub_pokemon_get_image_url($pokemon, ['shiny' => true]);
```

### Exemple avec genre et Shiny

```php
$image_url = poke_hub_pokemon_get_image_url($pokemon, [
    'gender' => 'male',
    'shiny' => true
]);
```

### Exemple avec `fallback` (version complète)

```php
$sources = poke_hub_pokemon_get_image_sources($pokemon, ['shiny' => true]);

$image_url = $sources['primary'];

// Ou avec gestion d'erreur côté client (HTML)
if (!empty($sources['primary'])) {
    echo '<img src="' . esc_url($sources['primary']) . '" 
                onerror="this.src=\'' . esc_js($sources['fallback']) . '\'" />';
}
```

### Exemple avec filtre WordPress

Vous pouvez personnaliser les URLs pour certains Pokémon via le filtre :

```php
add_filter('poke_hub_pokemon_image_sources', function($sources, $pokemon, $args) {
    // Exemple : personnaliser l'URL pour un Pokémon spécifique
    if ($pokemon->slug === 'pikachu-special') {
        $sources['primary'] = 'https://example.com/custom/pikachu.png';
    }
    
    return $sources;
}, 10, 3);
```

## Gestion des slugs manquants

Si un Pokémon n'a pas de `slug`, le système utilise le numéro du Pokédex formaté sur 3 chiffres :

```php
if ($slug === '') {
    $slug = sprintf('%03d', (int) $pokemon->dex_number);
}
```

Exemple : Si `dex_number = 1`, le fichier sera `001.png`

## État actuel et recommandations

### ✅ Ce qui est fait

- ✅ Fonctions helper pour générer les URLs
- ✅ Support des variantes shiny
- ✅ Support des variantes de genre (male/female)
- ✅ fallback optionnel (même chemins relatifs que la source principale)
- ✅ Configuration via l'interface d'administration
- ✅ Filtre WordPress pour personnalisation
- ✅ Construction automatique des clés d'image

### 🔄 Ce qui pourrait être amélioré

1. **Sous-dossiers par type** : Le paramètre `variant` existe mais n'est pas utilisé pour créer des sous-dossiers (`sprites/`, `icons/`, `full/`, etc.)

2. **Support de formats multiples** : Actuellement, seul `.png` est supporté. Possibilité d'ajouter `.jpg`, `.webp`, etc.

3. **Taille d'image** : Pas de système pour demander différentes tailles (thumbnail, medium, large)

4. **Vérification d'existence** : Pas de fonction pour vérifier si une image existe avant de l'afficher

5. **Lazy loading** : Pas de support natif pour le lazy loading des images

6. **Cache** : Pas de système de cache pour les URLs générées

### 📝 Utilisation dans le code

Les helpers sont utilisés par les blocs (sauvages, quêtes, évolutions, défis de collection, etc.), le module collections, les shortcodes jeux, et d’autres écrans qui affichent des miniatures Pokémon. Pour toute nouvelle intégration, préférer `poke_hub_pokemon_get_image_url()` ou `poke_hub_pokemon_get_image_sources()`.

## Exemple d'intégration complète

```php
// Dans un template ou une fonction
function display_pokemon_image($pokemon, $options = []) {
    $args = wp_parse_args($options, [
        'shiny' => false,
        'gender' => null,
        'size' => 'medium', // Pour futur usage
    ]);
    
    $sources = poke_hub_pokemon_get_image_sources($pokemon, $args);
    
    if (empty($sources['primary']) && empty($sources['fallback'])) {
        return '<span class="pokemon-no-image">Pas d\'image disponible</span>';
    }
    
    $primary_url = $sources['primary'];
    $fallback_url = $sources['fallback'];
    
    $alt = sprintf(
        '%s%s%s',
        $pokemon->name_fr ?? '',
        $args['shiny'] ? ' (Shiny)' : '',
        $args['gender'] ? ' (' . ucfirst($args['gender']) . ')' : ''
    );
    
    $img_attrs = [
        'src' => esc_url($primary_url),
        'alt' => esc_attr($alt),
        'class' => 'pokemon-image',
    ];
    
    if (!empty($fallback_url)) {
        $img_attrs['data-fallback'] = esc_url($fallback_url);
        $img_attrs['onerror'] = 'if(this.dataset.fallback){this.src=this.dataset.fallback;}';
    }
    
    $attributes = '';
    foreach ($img_attrs as $key => $value) {
        $attributes .= sprintf(' %s="%s"', esc_attr($key), $value);
    }
    
    return sprintf('<img%s />', $attributes);
}
```

## Questions fréquentes

### Comment changer la structure des dossiers ?

Pour ajouter des sous-dossiers (ex: `sprites/`), modifiez la fonction `poke_hub_pokemon_get_image_sources()` :

```php
// Au lieu de :
$path = $key . '.png';

// Utilisez :
$variant = $args['variant'] ?? 'sprite';
$path = $variant . '/' . $key . '.png';
```

### Comment supporter d'autres formats d'image ?

Modifiez la fonction pour accepter un paramètre `format` :

```php
$format = $args['format'] ?? 'png';
$path = $key . '.' . $format;
```

### Comment vérifier si une image existe ?

Ajoutez une fonction helper qui fait une requête HTTP HEAD :

```php
function poke_hub_pokemon_image_exists($url) {
    $response = wp_remote_head($url);
    return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
}
```





