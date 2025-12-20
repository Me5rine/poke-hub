# Poké HUB - Documentation

**Version:** 1.6.5  
**Auteur:** Me5rine  
**Description:** Plugin modulaire WordPress pour le site Poké HUB (Pokémon GO, Pokédex, événements, actualités, outils...)

---

## Table des matières

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Modules](#modules)
5. [Fonctionnalités](#fonctionnalités)
6. [Shortcodes](#shortcodes)
7. [Base de données](#base-de-données)
8. [Hooks personnalisés](#hooks-personnalisés)
9. [Dépendances](#dépendances)
10. [Développement](#développement)

---

## Introduction

Poké HUB est un plugin WordPress modulaire conçu pour gérer toutes les données liées à Pokémon GO. Il permet de gérer un Pokédex complet, des événements spéciaux, des bonus, et bien plus encore.

### Caractéristiques principales

- **Architecture modulaire** : Activez uniquement les modules dont vous avez besoin
- **Base de données personnalisée** : Tables optimisées pour les données Pokémon
- **Import Game Master** : Import automatique depuis les fichiers JSON du Game Master
- **Intégration S3** : Archivage automatique des Game Master sur AWS S3
- **Sources distantes** : Support pour récupérer des données depuis d'autres sites WordPress
- **Système de traduction** : Récupération automatique des noms officiels depuis Bulbapedia (6 langues supportées)
- **Shortcodes** : Affichage facile des événements et bonus sur le front-end

---

## Installation

### Prérequis

- WordPress 5.0 ou supérieur
- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Composer (pour les dépendances)

### Étapes d'installation

1. **Télécharger le plugin**
   ```bash
   cd wp-content/plugins
   git clone [url-du-repo] pokehub
   ```

2. **Installer les dépendances Composer**
   ```bash
   cd pokehub
   composer install
   ```

3. **Activer le plugin**
   - Allez dans **Extensions** > **Extensions installées**
   - Activez **Poké HUB**

4. **Configuration initiale**
   - Allez dans **Poké HUB** > **Settings**
   - Activez les modules souhaités
   - Configurez les sources de données

---

## Configuration

### Configuration générale

Accédez à **Poké HUB** > **Settings** > **General** pour :

- **Activer/Désactiver les modules** : Choisissez les modules à utiliser
- **Suppression des données** : Option pour supprimer toutes les données à la désinstallation

### Configuration AWS (Game Master)

Pour utiliser l'upload S3 du Game Master, ajoutez ces constantes dans `wp-config.php` :

```php
/* Configuration Poké HUB */
define('POKE_HUB_GM_AWS_KEY', 'votre-clé-aws');
define('POKE_HUB_GM_AWS_SECRET', 'votre-secret-aws');
```

### Configuration des sources

Accédez à **Poké HUB** > **Settings** > **Sources** pour configurer :

#### Sources Events (JV Actu)

- **Préfixe de table Events** : Préfixe utilisé pour les tables d'événements sur le site distant (ex: `jvactu_`)
- **Préfixe de table Event Types** : Préfixe pour les taxonomies d'événements (optionnel, réutilise le préfixe Events si vide)

#### Sources Pokémon

- **URL de base des assets Pokémon** : URL de base pour charger les images/sprites des Pokémon depuis votre CDN/bucket
- **URL de fallback** : URL de secours si la source principale est indisponible

### Configuration des traductions

Accédez à **Poké HUB** > **Settings** > **Translation** pour :

- **Gérer les traductions manquantes** : Visualiser et éditer les traductions manquantes pour Pokémon, Attaques et Types
- **Récupération en masse depuis Bulbapedia** : Récupérer automatiquement les noms officiels depuis Bulbapedia via l'API MediaWiki
- **Statistiques** : Voir le nombre de traductions manquantes par langue et par type

Le système supporte 6 langues :
- Français (fr)
- Allemand (de)
- Italien (it)
- Espagnol (es)
- Japonais (ja)
- Coréen (ko)

Les traductions sont stockées dans le champ `extra` (JSON) avec la structure `names[lang]` et le français est également stocké dans la colonne `name_fr` pour la rétrocompatibilité.

---

## Modules

### Module Pokémon

Gestion complète du Pokédex et des données Pokémon.

#### Fonctionnalités

- **Gestion du Pokédex** : Ajout, modification et organisation des Pokémon
- **Types** : Gestion des types (Eau, Feu, Plante, etc.) avec icônes et couleurs
- **Régions et Générations** : Organisation par régions (Kanto, Johto, etc.) et générations
- **Attaques** : Gestion des attaques rapides et chargées avec leurs statistiques
- **Formes** : Support des différentes formes de Pokémon (costumes, variantes, etc.)
- **Évolutions** : Gestion des branches d'évolution avec conditions (candies, objets, météo, etc.)
- **Météos** : Gestion des conditions météorologiques et leurs effets sur les types
- **Items** : Gestion des objets (objets d'évolution, leurres, etc.)

#### Import Game Master

Le module Pokémon permet d'importer les données depuis un fichier Game Master JSON :

1. Allez dans **Poké HUB** > **Settings** > **Game Master**
2. Configurez les paramètres S3 (optionnel)
3. Uploadez un fichier JSON Game Master
4. Cliquez sur **Import now** pour synchroniser les données

L'import gère :
- Les Pokémon et leurs statistiques
- Les attaques et leurs stats (PvE et PvP)
- Les liens Pokémon ↔ Types
- Les liens Attaques ↔ Types
- Les liens Pokémon ↔ Attaques
- Les formes et variantes
- Les évolutions
- Les items

#### Interface d'administration

Accédez à **Poké HUB** > **Pokémon** pour gérer :
- Types
- Régions
- Générations
- Attaques
- Items
- Météos
- Formes
- Mappings de formes

#### Système de traduction

Le module Pokémon intègre un système de traduction automatique depuis Bulbapedia :

- **Récupération automatique** : Lors de l'ajout ou de l'édition d'un Pokémon, Attaque ou Type, les noms officiels sont automatiquement récupérés depuis Bulbapedia
- **API MediaWiki** : Utilise l'API officielle de Bulbapedia pour récupérer les noms dans toutes les langues supportées
- **Gestion des traductions manquantes** : Interface dédiée pour visualiser et compléter les traductions manquantes
- **Récupération en masse** : Possibilité de récupérer les traductions par lots (recommandé : 5-10 items à la fois pour respecter les limites de taux de Bulbapedia)
- **Normalisation** : Gestion spéciale des cas particuliers (Nidoran♀/Nidoran♂, formes Mega, etc.)

Les traductions sont automatiquement récupérées depuis la section "In other languages" des pages Bulbapedia via l'API MediaWiki, en évitant de parser des pages HTML complètes pour des performances optimales.

### Module Events

Gestion des événements spéciaux Pokémon GO.

#### Fonctionnalités

- **Événements spéciaux** : Création et gestion d'événements avec dates de début/fin
- **Types d'événements** : Organisation hiérarchique des types d'événements
- **Pokémon d'événement** : Association de Pokémon à des événements
- **Bonus d'événement** : Association de bonus à des événements
- **Attaques spéciales** : Gestion des attaques exclusives aux événements

#### Interface d'administration

Accédez à **Poké HUB** > **Events** pour :
- Voir la liste des événements spéciaux
- Créer/Modifier/Supprimer des événements
- Gérer les Pokémon et bonus associés

#### Sources distantes

Le module Events peut récupérer des données depuis un autre site WordPress (JV Actu) en configurant les préfixes de tables dans **Settings** > **Sources**.

### Module Bonus

Gestion des bonus disponibles dans Pokémon GO.

#### Fonctionnalités

- **Custom Post Type** : Les bonus sont gérés via un CPT `pokehub_bonus`
- **Métadonnées** : Images, descriptions, etc.
- **Association aux événements** : Les bonus peuvent être associés aux événements via le module Events

#### Interface d'administration

Accédez à **Poké HUB** > **Bonus** pour gérer les bonus comme des articles WordPress classiques.

---

## Fonctionnalités

### Import Game Master

Le plugin permet d'importer les données du Game Master de Pokémon GO depuis un fichier JSON.

#### Processus d'import

1. **Upload du fichier** : Uploadez un fichier JSON Game Master
2. **Copie locale** : Le fichier est sauvegardé localement dans `wp-content/uploads/poke-hub/gamemaster/latest.json`
3. **Archivage S3** (optionnel) : Si configuré, le fichier est également uploadé sur S3 avec un timestamp
4. **Import des données** : Lancez l'import pour synchroniser les données dans la base

#### Détection des changements

L'import vérifie automatiquement si le fichier a changé depuis le dernier import (via `mtime`). Vous pouvez forcer l'import même si le fichier n'a pas changé en cochant **Force import**.

#### Résumé d'import

Après chaque import, un résumé détaillé est affiché :
- Nombre de Pokémon insérés/mis à jour
- Nombre d'attaques insérées/mises à jour
- Statistiques PvE et PvP mises à jour
- Liens créés (Pokémon ↔ Types, Attaques ↔ Types, etc.)

### Archivage S3

Le plugin peut automatiquement archiver les fichiers Game Master sur AWS S3 :

- **Bucket** : Nom du bucket S3
- **Préfixe** : Dossier dans le bucket (ex: `gamemaster`)
- **Région** : Région AWS (ex: `eu-west-3`)

Les fichiers sont nommés avec un timestamp : `gamemaster-YYYYMMDD-HHMMSS.json`

### Sources distantes

Le plugin supporte la récupération de données depuis d'autres sites WordPress via des requêtes directes sur la base de données distante.

#### Configuration

Dans **Settings** > **Sources**, configurez les préfixes de tables pour :
- Les événements (posts, postmeta, tables spéciales)
- Les types d'événements (taxonomies)

### Système de traduction Bulbapedia

Le plugin intègre un système complet de gestion des traductions pour Pokémon, Attaques et Types.

#### Fonctionnalités

- **Récupération automatique** : Les noms officiels sont automatiquement récupérés depuis Bulbapedia lors de l'ajout/édition d'items
- **Interface de gestion** : Onglet dédié dans **Settings** > **Translation** pour visualiser et éditer les traductions manquantes
- **Récupération en masse** : Possibilité de récupérer les traductions par lots depuis Bulbapedia
- **Statistiques** : Tableau de bord affichant le nombre de traductions manquantes par langue et par type

#### Langues supportées

- Français (fr) - également stocké dans `name_fr` pour compatibilité
- Allemand (de)
- Italien (it)
- Espagnol (es)
- Japonais (ja)
- Coréen (ko)

#### Utilisation

1. **Visualiser les traductions manquantes** :
   - Allez dans **Poké HUB** > **Settings** > **Translation**
   - Sélectionnez une langue et un type (Pokémon, Attaques, Types)
   - Consultez la liste des items sans traduction

2. **Récupérer depuis Bulbapedia** :
   - Utilisez le formulaire "Bulk Fetch Missing Translations"
   - Sélectionnez le type et limitez le nombre d'items (recommandé : 5-10)
   - Cliquez sur "Fetch Missing Translations"

3. **Éditer manuellement** :
   - Utilisez le formulaire "Edit Missing Translations"
   - Saisissez les traductions dans les champs appropriés
   - Seuls les champs remplis seront sauvegardés

#### Stockage des traductions

Les traductions sont stockées dans le champ `extra` (JSON) avec la structure suivante :
```json
{
  "names": {
    "en": "English Name",
    "fr": "Nom Français",
    "de": "Deutscher Name",
    "it": "Nome Italiano",
    "es": "Nombre Español",
    "ja": "日本語名",
    "ko": "한국어 이름"
  }
}
```

Pour le français, la valeur est également stockée dans la colonne `name_fr` pour la rétrocompatibilité avec le code existant.

#### Constante de débogage

Vous pouvez activer le mode debug des traductions en définissant dans `wp-config.php` :
```php
define('POKE_HUB_TRANSLATIONS_DEBUG', true);
```

Cela activera les logs détaillés dans `error_log` pour diagnostiquer les problèmes de récupération.

---

## Shortcodes

### `[poke_hub_events]`

Affiche une liste d'événements avec filtres.

#### Attributs

- `status` : Statut des événements (`current`, `upcoming`, `past`, `all`) - Défaut: `current`
- `category` : Catégorie d'événement (slug)
- `event_type` : Type(s) d'événement (slug, plusieurs séparés par virgules)
- `event_type_parent` : Type parent (affiche les types enfants en filtre)
- `order` : Ordre d'affichage (`asc`, `desc`) - Défaut: `asc`
- `per_page` : Nombre d'événements par page - Défaut: `15`
- `page_var` : Nom de la variable de pagination dans l'URL - Défaut: `pg`

#### Exemples

```php
// Afficher les événements en cours
[poke_hub_events status="current"]

// Afficher tous les événements passés
[poke_hub_events status="past" order="desc"]

// Filtrer par type d'événement
[poke_hub_events event_type="community-day,raid-day"]

// Avec pagination personnalisée
[poke_hub_events per_page="20" page_var="page"]
```

### `[pokehub-bonus]`

Affiche un ou plusieurs bonus.

#### Attributs

- `bonus` : Liste de bonus avec descriptions (format: `slug:description,slug2:description2`)

#### Exemple

```php
[pokehub-bonus bonus="xp:Double XP pendant l'événement,raids:Raids bonus"]
```

### `[pokehub-event-bonuses]`

Affiche les bonus associés à un événement (post).

#### Attributs

- `post_id` : ID du post/événement (optionnel, utilise le post actuel si non spécifié)

#### Exemple

```php
[pokehub-event-bonuses]

// Ou pour un post spécifique
[pokehub-event-bonuses post_id="123"]
```

---

## Base de données

Le plugin crée plusieurs tables personnalisées pour stocker les données. Les tables sont créées automatiquement lors de l'activation des modules.

### Tables du module Pokémon

- `{prefix}_pokehub_pokemon` : Pokémon principaux
- `{prefix}_pokehub_pokemon_types` : Types (Eau, Feu, etc.)
- `{prefix}_pokehub_regions` : Régions (Kanto, Johto, etc.)
- `{prefix}_pokehub_generations` : Générations
- `{prefix}_pokehub_attacks` : Attaques
- `{prefix}_pokehub_attack_stats` : Statistiques des attaques (PvE/PvP)
- `{prefix}_pokehub_pokemon_type_links` : Liens Pokémon ↔ Types
- `{prefix}_pokehub_attack_type_links` : Liens Attaques ↔ Types
- `{prefix}_pokehub_pokemon_attack_links` : Liens Pokémon ↔ Attaques
- `{prefix}_pokehub_pokemon_weathers` : Météos
- `{prefix}_pokehub_pokemon_type_weather_links` : Liens Types ↔ Météos
- `{prefix}_pokehub_pokemon_form_mappings` : Mappings de formes
- `{prefix}_pokehub_pokemon_form_variants` : Variantes de formes
- `{prefix}_pokehub_pokemon_evolutions` : Évolutions
- `{prefix}_pokehub_items` : Items/Objets
- `{prefix}_pokehub_pokemon_backgrounds` : Fonds spéciaux pour Pokémon
- `{prefix}_pokehub_pokemon_background_pokemon_links` : Liens Backgrounds ↔ Pokémon

### Tables du module Events

- `{prefix}_pokehub_special_events` : Événements spéciaux
- `{prefix}_pokehub_special_event_pokemon` : Liens Événements ↔ Pokémon
- `{prefix}_pokehub_special_event_bonus` : Liens Événements ↔ Bonus
- `{prefix}_pokehub_special_event_pokemon_attacks` : Attaques spéciales par événement

### Fonction helper

Utilisez `pokehub_get_table('table_name')` pour obtenir le nom complet d'une table avec le préfixe :

```php
$pokemon_table = pokehub_get_table('pokemon');
// Retourne: wp_pokehub_pokemon
```

---

## Hooks personnalisés

Le plugin supporte les hooks personnalisés via un fichier externe.

### Fichier de hooks

Le plugin cherche automatiquement un fichier de hooks personnalisés à :
```
wp-content/uploads/poke-hub/custom-hooks.php
```

Ce fichier est créé automatiquement lors de l'activation du plugin s'il n'existe pas.

### Exemple d'utilisation

```php
// wp-content/uploads/poke-hub/custom-hooks.php

// Ajouter un filtre personnalisé
add_filter('pokehub_pokemon_data', function($data, $pokemon_id) {
    // Modifier les données du Pokémon
    return $data;
}, 10, 2);

// Ajouter une action personnalisée
add_action('pokehub_event_created', function($event_id) {
    // Faire quelque chose après la création d'un événement
});
```

---

## Dépendances

### Composer

Le plugin utilise Composer pour gérer ses dépendances :

- **AWS SDK PHP** (`aws/aws-sdk-php`) : Pour l'upload S3 du Game Master

### Plugins WordPress (optionnels)

- **Me5rine LAB** : Requis pour le module Events (si utilisé avec des sources distantes)
- **Amazon S3 and CloudFront** : Optionnel, pour l'intégration avec Offload Media

---

## Développement

### Structure du plugin

```
pokehub/
├── assets/              # CSS et JS
├── includes/            # Fichiers principaux
│   ├── admin-ui.php     # Interface d'administration
│   ├── pokehub-db.php   # Gestion de la base de données
│   └── settings/        # Pages de paramètres
├── modules/              # Modules du plugin
│   ├── bonus/           # Module Bonus
│   ├── events/           # Module Events
│   ├── pokemon/          # Module Pokémon
│   └── pokedex/          # Module Pokédex (à venir)
├── vendor/               # Dépendances Composer
├── poke-hub.php         # Fichier principal
└── uninstall.php        # Script de désinstallation
```

### Constantes disponibles

- `POKE_HUB_PATH` : Chemin absolu du plugin
- `POKE_HUB_URL` : URL du plugin
- `POKE_HUB_VERSION` : Version du plugin
- `POKE_HUB_MODULES_DIR` : Dossier des modules
- `POKE_HUB_INCLUDES_DIR` : Dossier des includes
- `POKE_HUB_HOOKS_DIR` : Dossier des hooks personnalisés
- `POKE_HUB_HOOKS_FILE` : Fichier des hooks personnalisés
- `POKE_HUB_TRANSLATIONS_DEBUG` : Active les logs de débogage pour les traductions (défaut: `false`)

### Fonctions helper

#### Vérifier si un module est actif

```php
if (poke_hub_is_module_active('pokemon')) {
    // Le module Pokémon est actif
}
```

#### Obtenir le registre des modules

```php
$registry = poke_hub_get_modules_registry();
// Retourne: ['events' => 'events/events.php', 'bonus' => 'bonus/bonus.php', ...]
```

#### Obtenir le nom d'une table

```php
$table = pokehub_get_table('pokemon');
// Retourne: wp_pokehub_pokemon
```

#### Fonctions de traduction

```php
// Récupérer les traductions manquantes pour les Pokémon
$missing = poke_hub_pokemon_get_missing_translations(['lang' => 'fr']);

// Récupérer les traductions manquantes pour les attaques
$missing = poke_hub_attacks_get_missing_translations(['lang' => 'de']);

// Récupérer les traductions manquantes pour les types
$missing = poke_hub_types_get_missing_translations(['lang' => 'it']);

// Récupérer toutes les traductions manquantes
$all_missing = poke_hub_get_all_missing_translations();

// Récupérer les noms officiels depuis Bulbapedia pour un Pokémon
$names = poke_hub_pokemon_fetch_pokemon_official_names_from_bulbapedia('Pikachu');

// Récupérer les noms officiels pour une attaque
$names = poke_hub_pokemon_fetch_move_official_names_from_bulbapedia('Thunderbolt');

// Récupérer les noms officiels pour un type
$names = poke_hub_pokemon_fetch_type_official_names_from_bulbapedia('Electric');

// Récupération en masse depuis Bulbapedia
$result = poke_hub_pokemon_fetch_official_names_existing($limit = 5, $force = false);
// Retourne: ['updated' => 5, 'skipped' => 2, 'errors' => 0, 'total' => 7]
```

#### Vérifier si une traduction existe

```php
// Récupérer un élément depuis la base de données
$pokemon = $wpdb->get_row("SELECT * FROM {$table} WHERE id = 123");

// Vérifier si une traduction existe
if (poke_hub_tr_has_translation($pokemon, 'fr')) {
    // La traduction française existe
}

// Récupérer une traduction depuis extra
$name_fr = poke_hub_tr_get_extra_name($pokemon->extra, 'fr');
```

### Ajouter un nouveau module

1. Créez un nouveau dossier dans `modules/`
2. Créez un fichier principal `module-name.php`
3. Ajoutez le module au registre dans `includes/settings/settings-modules.php` :

```php
function poke_hub_get_modules_registry(): array {
    return [
        'events'  => 'events/events.php',
        'bonus'   => 'bonus/bonus.php',
        'pokemon' => 'pokemon/pokemon.php',
        'nouveau-module' => 'nouveau-module/nouveau-module.php', // Ajoutez ici
    ];
}
```

4. Vérifiez que le module est actif avant de charger ses fonctionnalités :

```php
if (!poke_hub_is_module_active('nouveau-module')) {
    return;
}
```

### Hooks disponibles

#### Actions

- `poke_hub_modules_loaded` : Après le chargement de tous les modules
- `poke_hub_event_created` : Après la création d'un événement
- `poke_hub_pokemon_imported` : Après l'import d'un Pokémon

#### Filtres

- `poke_hub_active_modules` : Modifier la liste des modules actifs
- `poke_hub_pokemon_data` : Modifier les données d'un Pokémon
- `poke_hub_event_data` : Modifier les données d'un événement
- `poke_hub_pokemon_import_i18n` : Fournir des traductions personnalisées lors de l'import Game Master
- `poke_hub_pokemon_i18n_names` : Modifier les noms multilingues avant leur stockage (format: `['en' => '...', 'fr' => '...', ...]`)

---

## Support

Pour toute question ou problème, contactez l'auteur via :
- **Site web** : https://me5rine.com
- **Email** : [email à définir]

---

## Changelog

### Version 1.6.5
- **Nouveau système de traduction Bulbapedia** : Récupération automatique des noms officiels depuis Bulbapedia via l'API MediaWiki
- **Onglet Translation** : Nouvel onglet dans les Settings pour gérer les traductions manquantes
- **Support multilingue** : Ajout du support pour 6 langues (fr, de, it, es, ja, ko)
- **Récupération en masse** : Fonctionnalité pour récupérer les traductions par lots depuis Bulbapedia
- **Statistiques de traduction** : Tableau de bord affichant les traductions manquantes par langue et type
- **Stockage JSON** : Les traductions sont stockées dans le champ `extra` avec structure `names[lang]`
- **Gestion spéciale** : Normalisation des cas particuliers (Nidoran♀/Nidoran♂, formes Mega, etc.)

### Version 1.5.6
- Version précédente du plugin

---

## Licence

[À définir]

