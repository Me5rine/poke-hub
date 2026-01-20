# Configuration automatique des Pokémon régionaux à l'import Game Master

Ce système permet de définir automatiquement quels Pokémon sont régionaux et avec quels pays ils sont associés lors de l'import du Game Master.

## Fonctionnement

Lors de l'import du Game Master, le système vérifie automatiquement :
1. Si le Pokémon/form est dans la configuration régionale
2. Si oui, marque automatiquement `is_regional = true`
3. Associe automatiquement les pays définis dans la configuration

## Configuration

Le fichier de configuration se trouve dans : `modules/pokemon/includes/pokemon-regional-auto-config.php`

### Configuration Vivillon (Scatterbug/Vivillon)

Les patterns Vivillon sont automatiquement détectés et leurs pays sont récupérés depuis le mapping Vivillon/pays :

```php
'VIVILLON_' => [
    'countries' => [], // Sera rempli automatiquement depuis le mapping Vivillon
    'auto_detect_countries' => true, // Utilise le mapping Vivillon pour récupérer les pays
],
```

**Note** : Les pays pour Vivillon sont automatiquement récupérés depuis `poke_hub_get_vivillon_pattern_country_mapping()` si disponible.

### Configuration de Pokémon régionaux spécifiques

Pour ajouter un Pokémon régional à la configuration, ajoutez une entrée dans `poke_hub_pokemon_get_regional_auto_config()` :

```php
// Exemple : Mr. Mime (Europe)
'MR_MIME' => [
    'countries' => [
        'France', 'Allemagne', 'Espagne', 'Italie', 'Royaume-Uni', 
        'Belgique', 'Pays-Bas', 'Suisse', 'Autriche', 'Pologne',
        // ... autres pays européens
    ],
],
```

### Exemples complets

```php
function poke_hub_pokemon_get_regional_auto_config() {
    $default_config = [
        // Vivillon patterns (détection automatique)
        'VIVILLON_' => [
            'countries' => [],
            'auto_detect_countries' => true,
        ],
        
        // Pokémon régionaux spécifiques
        'FARFETCHD' => [
            'countries' => ['Japon', 'Corée du Sud', 'Taiwan'],
        ],
        'TAUROS' => [
            'countries' => ['États-Unis', 'Canada'],
        ],
        'MR_MIME' => [
            'countries' => ['France', 'Allemagne', 'Espagne', 'Italie', 'Royaume-Uni', 'Belgique', 'Pays-Bas', 'Suisse', 'Autriche'],
        ],
        'KANGASKHAN' => [
            'countries' => ['Australie'],
        ],
        // ... autres Pokémon régionaux
    ];
    
    return apply_filters('poke_hub_pokemon_regional_auto_config', $default_config);
}
```

## Utilisation via filtre WordPress

Vous pouvez aussi personnaliser la configuration depuis votre thème ou un plugin via le filtre WordPress :

```php
// Dans functions.php de votre thème ou un plugin
add_filter('poke_hub_pokemon_regional_auto_config', function($config) {
    // Ajouter ou modifier des entrées
    $config['PACHIRISU'] = [
        'countries' => ['Canada', 'Russie', 'Alaska (États-Unis)'],
    ];
    
    return $config;
});
```

## Format des pays

**Important** : Les pays doivent être stockés par leur **LABEL** (nom complet), pas par leur **CODE**.

- ✅ **Correct** : `'France'`, `'Allemagne'`, `'États-Unis'`
- ❌ **Incorrect** : `'FR'`, `'DE'`, `'US'`

Les labels doivent correspondre exactement à ceux d'Ultimate Member. Utilisez `poke_hub_get_countries()` pour obtenir la liste complète des labels disponibles.

## Fonctions disponibles

### `poke_hub_pokemon_get_regional_auto_config()`

Retourne la configuration régionale complète.

### `poke_hub_pokemon_get_regional_countries_for_import($template_id, $form_slug, $pokemon_id_proto)`

Récupère les pays associés à un Pokémon/form spécifique lors de l'import.

**Paramètres** :
- `$template_id` : Template ID du Game Master (ex: `'VIVILLON_2021_FORM_0'`)
- `$form_slug` : Form slug (ex: `'continental'`)
- `$pokemon_id_proto` : Pokémon ID proto (ex: `'VIVILLON'`)

**Retourne** : Array de labels de pays, ou array vide si non trouvé

### `poke_hub_pokemon_should_be_regional_on_import($template_id, $form_slug, $pokemon_id_proto)`

Vérifie si un Pokémon/form devrait être marqué comme régional lors de l'import.

**Retourne** : `true` si doit être régional, `false` sinon

## Notes importantes

1. **Préservation des données existantes** : Si un Pokémon a déjà des données régionales (description, map_image_id), elles sont préservées lors de l'import.

2. **Pays manquants** : Si aucun pays n'est trouvé dans la configuration, le champ `countries` sera un array vide `[]`.

3. **Vivillon automatique** : Les patterns Vivillon sont automatiquement détectés et leurs pays sont récupérés depuis le mapping Vivillon/pays si disponible.

4. **Filtres WordPress** : Vous pouvez utiliser les filtres WordPress pour personnaliser le comportement :
   - `poke_hub_pokemon_regional_auto_config` : Pour modifier la configuration
   - `poke_hub_pokemon_get_regional_countries_for_import` : Pour personnaliser les pays pour un Pokémon spécifique
   - `poke_hub_pokemon_should_be_regional_on_import` : Pour personnaliser si un Pokémon doit être régional

## Exemple d'utilisation complète

```php
// Dans votre fichier de configuration personnalisée
add_filter('poke_hub_pokemon_regional_auto_config', function($config) {
    // Ajouter Torkoal (Asie du Sud-Est)
    $config['TORKOAL'] = [
        'countries' => [
            'Inde', 'Thaïlande', 'Indonésie', 'Malaisie', 
            'Singapour', 'Philippines', 'Vietnam'
        ],
    ];
    
    return $config;
});
```

Lors du prochain import Game Master, Torkoal sera automatiquement marqué comme régional avec les pays spécifiés.

