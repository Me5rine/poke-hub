# Format de stockage des pays en base de données

## Règle générale

**Les pays sont TOUJOURS stockés par leur LABEL (nom complet), JAMAIS par leur CODE.**

Exemples :
- ✅ **Correct** : `'France'`, `'Allemagne'`, `'États-Unis'`
- ❌ **Incorrect** : `'FR'`, `'DE'`, `'US'`

---

## 1. Dans les fiches Pokémon (table `pokemon`)

### Structure
Les pays sont stockés dans le champ `extra` (type JSON) :
```json
{
  "regional": {
    "is_regional": true,
    "description": "...",
    "map_image_id": 123,
    "countries": ["France", "Allemagne", "Espagne", "Italie"]
  }
}
```

### Code PHP (sauvegarde)
```php
$extra['regional'] = [
    'is_regional'  => $regional_is_regional,
    'description'  => $regional_description,
    'map_image_id' => $regional_map_image_id,
    'countries'    => $regional_countries, // Array of country names (labels)
];
```

### Format
- **Type** : Tableau de chaînes de caractères (array of strings)
- **Valeurs** : Labels des pays (noms complets) depuis Ultimate Member
- **Exemple** : `['France', 'Allemagne', 'Espagne']`

### Emplacement en base
- **Table** : `{prefix}_pokemon`
- **Colonne** : `extra` (LONGTEXT, format JSON)
- **Chemin JSON** : `extra['regional']['countries']`

---

## 2. Dans les profils utilisateur (Ultimate Member usermeta)

### Structure
Le pays est stocké dans `wp_usermeta` :

| user_id | meta_key | meta_value |
|---------|----------|------------|
| 123     | country  | France     |

### Format
- **Type** : Chaîne de caractères (string)
- **Valeur** : Label du pays (nom complet) depuis Ultimate Member
- **Exemple** : `'France'` (pas `'FR'`)

### Code PHP (récupération)
```php
// Récupération depuis Ultimate Member
$country = get_user_meta($user_id, 'country', true);
// Retourne : 'France' (label), pas 'FR' (code)
```

### Code PHP (sauvegarde)
```php
// Sauvegarde dans Ultimate Member
update_user_meta($user_id, 'country', 'France'); // Label, pas code
```

### Emplacement en base
- **Table** : `wp_usermeta`
- **meta_key** : `'country'`
- **meta_value** : Label du pays (ex: `'France'`)

---

## 3. Dans le mapping Vivillon (mapping pattern/pays)

### Structure
Le mapping est soit statique dans le code PHP, soit récupéré depuis les fiches Pokémon :

```php
[
    'continental' => ['France', 'Allemagne', 'Espagne', ...],
    'garden'      => ['Royaume-Uni', 'Irlande', ...],
    'elegant'     => ['Japon'],
    'modern'      => ['États-Unis', 'Canada'],
    // ...
]
```

### Format
- **Type** : Tableau associatif où :
  - **Clé** : Pattern slug (ex: `'continental'`)
  - **Valeur** : Tableau de labels de pays (ex: `['France', 'Allemagne']`)

### Code PHP (récupération depuis DB)
```php
// Depuis les fiches Pokémon Vivillon/Scatterbug
$extra = json_decode($row->extra, true);
$countries = $extra['regional']['countries']; 
// Retourne : ['France', 'Allemagne', ...] (labels, pas codes)
```

### Emplacement
- **Statique** : Dans `includes/functions/pokemon-public-helpers.php` (fonctions globales disponibles même si le module user-profiles n'est pas actif)
- **Dynamique** : Récupéré depuis `{prefix}_pokemon.extra['regional']['countries']`

---

## Comment obtenir la liste des labels de pays

### Méthode 1 : Via Ultimate Member
```php
if (function_exists('UM') && is_object(UM())) {
    $countries = UM()->builtin()->get('countries');
    // Retourne : ['FR' => 'France', 'DE' => 'Allemagne', ...]
    // Pour obtenir uniquement les labels :
    $country_labels = array_values($countries);
}
```

### Méthode 2 : Via la fonction helper
```php
if (function_exists('poke_hub_get_countries')) {
    $countries = poke_hub_get_countries();
    // Retourne : ['FR' => 'France', 'DE' => 'Allemagne', ...]
    // Pour obtenir uniquement les labels :
    $country_labels = array_values($countries);
}
```

### Méthode 3 : Via WP-CLI (personnalisé)
Créez un script temporaire si nécessaire ou utilisez directement les fonctions PHP dans votre code.

---

## Exemple complet d'insertion en base

### Exemple 1 : Mettre à jour une fiche Pokémon Vivillon

```php
// 1. Récupérer les pays depuis Ultimate Member
$countries_um = UM()->builtin()->get('countries');
$country_labels = array_values($countries_um); // ['France', 'Allemagne', ...]

// 2. Filtrer pour obtenir uniquement les pays pour le pattern "continental"
$continental_countries = [
    'France', 'Allemagne', 'Autriche', 'Belgique', 
    'Bulgarie', 'Croatie', 'Danemark', 'Espagne'
];

// 3. Récupérer l'extra existant
$pokemon = $wpdb->get_row($wpdb->prepare(
    "SELECT extra FROM {$table} WHERE id = %d",
    $pokemon_id
));
$extra = json_decode($pokemon->extra, true);

// 4. Mettre à jour les pays
$extra['regional']['countries'] = $continental_countries;

// 5. Sauvegarder en base
$extra_json = wp_json_encode($extra);
$wpdb->update(
    $table,
    ['extra' => $extra_json],
    ['id' => $pokemon_id],
    ['%s'],
    ['%d']
);
```

### Exemple 2 : Insérer directement via SQL

```sql
-- Exemple : Mettre à jour l'extra JSON d'un Pokémon Vivillon
UPDATE wp_poke_hub_pokemon
SET extra = JSON_SET(
    extra,
    '$.regional.countries',
    JSON_ARRAY('France', 'Allemagne', 'Espagne', 'Italie')
)
WHERE dex_number = 666 
  AND form_variant_id = (SELECT id FROM wp_poke_hub_pokemon_form_variants WHERE form_slug = 'continental')
LIMIT 1;
```

---

## Vérification de cohérence

Pour vérifier que tous les pays utilisés dans le mapping existent dans Ultimate Member :

```php
// Récupérer tous les pays du mapping Vivillon
$mapping = poke_hub_get_vivillon_pattern_country_mapping();
$all_mapping_countries = [];
foreach ($mapping as $pattern => $countries) {
    $all_mapping_countries = array_merge($all_mapping_countries, $countries);
}
$all_mapping_countries = array_unique($all_mapping_countries);

// Récupérer tous les pays d'Ultimate Member
$um_countries = UM()->builtin()->get('countries');
$um_country_labels = array_values($um_countries);

// Vérifier les différences
$missing = array_diff($all_mapping_countries, $um_country_labels);
if (!empty($missing)) {
    echo "Pays manquants dans Ultimate Member : " . implode(', ', $missing);
}
```

---

## Résumé

| Emplacement | Format | Type | Exemple |
|-------------|--------|------|---------|
| Fiches Pokémon (`extra` JSON) | Array de strings | `['France', 'Allemagne']` | Labels |
| Profils utilisateur (usermeta) | String | `'France'` | Label |
| Mapping Vivillon | Array associatif | `['continental' => ['France', ...]]` | Labels |

**⚠️ IMPORTANT** : Toujours utiliser les **labels** (noms complets), jamais les **codes** (FR, DE, US, etc.)

