# Poké HUB - Documentation

**Version:** 1.7  
**Auteur:** Me5rine  
**Description:** Plugin modulaire WordPress pour le site Poké HUB (Pokémon GO, Pokédex, événements, actualités, outils...)

---

## Table des matières

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Architecture générale](#architecture-générale)
5. [Modules](#modules)
   - [Module Pokémon](#module-pokémon)
   - [Module Events](#module-events)
   - [Module Bonus](#module-bonus)
   - [Module Pokédex](#module-pokédex)
6. [Fonctionnalités](#fonctionnalités)
7. [Shortcodes](#shortcodes)
8. [Routing Front-end](#routing-front-end)
9. [Base de données](#base-de-données)
10. [Hooks personnalisés](#hooks-personnalisés)
11. [API et Helpers](#api-et-helpers)
12. [Dépendances](#dépendances)
13. [Développement](#développement)

---

## Introduction

Poké HUB est un plugin WordPress modulaire conçu pour gérer toutes les données liées à Pokémon GO. Il permet de gérer un Pokédex complet, des événements spéciaux, des bonus, et bien plus encore.

### Caractéristiques principales

- **Architecture modulaire** : Activez uniquement les modules dont vous avez besoin
- **Base de données personnalisée** : Tables optimisées pour les données Pokémon
- **Import Game Master** : Import automatique depuis les fichiers JSON du Game Master
- **Intégration S3** : Archivage automatique des Game Master sur AWS S3
- **Sources distantes** : Support pour récupérer des données depuis d'autres sites WordPress
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

---

## Architecture générale

### Système modulaire

Poké HUB utilise une architecture modulaire qui permet d'activer uniquement les fonctionnalités nécessaires. Chaque module est indépendant et peut être activé/désactivé depuis les paramètres.

### Chargement des modules

Les modules sont chargés dynamiquement lors de l'action `plugins_loaded` (priorité 20). Seuls les modules activés dans les options WordPress sont chargés.

### Structure des modules

Chaque module suit une structure standardisée :
- **Fichier principal** : `modules/{module}/{module}.php` - Point d'entrée du module
- **Admin** : Interface d'administration (`admin/`)
- **Functions** : Fonctions utilitaires (`functions/`)
- **Public** : Fonctionnalités front-end (`public/`)
- **Includes** : Fichiers d'inclusion supplémentaires (`includes/`)

---

## Modules

### Module Pokémon

Le module Pokémon est le cœur du plugin. Il gère toutes les données relatives aux Pokémon, leurs statistiques, attaques, évolutions, et bien plus.

#### Fonctionnalités principales

##### 1. Gestion du Pokédex

- **Pokémon** : Gestion complète des Pokémon avec leurs statistiques de base (ATK, DEF, STA)
- **Numéros Pokédex** : Association automatique des numéros de Pokédex
- **Formes et variantes** : Support complet des différentes formes (Méga, Primo, Costumes, etc.)
- **Formes par défaut** : Identification des formes par défaut pour chaque Pokémon
- **Générations** : Organisation par générations (Gen 1 à Gen 9+)
- **Régions** : Organisation par régions (Kanto, Johto, Hoenn, etc.)

##### 2. Types Pokémon

- **Gestion des types** : 18 types (Normal, Feu, Eau, Plante, etc.)
- **Icônes personnalisées** : Upload d'icônes pour chaque type
- **Couleurs** : Attribution de couleurs pour l'affichage visuel
- **Faiblesses et résistances** : Gestion des relations entre types
- **Boost météorologique** : Association des types avec les conditions météo

##### 3. Attaques (Moves)

- **Attaques rapides et chargées** : Gestion des deux catégories d'attaques
- **Statistiques PvE** : Dégâts, DPS, EPS pour le PvE
- **Statistiques PvP** : Dégâts, DPS, EPS pour le PvP
- **Durée et fenêtre de dégâts** : Gestion des timings précis
- **Énergie** : Gestion de l'énergie générée/consommée
- **Types d'attaques** : Association des attaques avec les types
- **Attaques légacy/événement** : Marquage des attaques spéciales

##### 4. Évolutions

- **Branches d'évolution** : Gestion complète des arbres d'évolution
- **Coûts en bonbons** : Coûts normaux et purifiés
- **Objets requis** : Pierres, objets spéciaux, etc.
- **Leurres** : Évolution via leurres (Glacial, Moussu, etc.)
- **Conditions météo** : Évolutions dépendantes de la météo
- **Évolutions aléatoires** : Gestion des évolutions aléatoires (Evoli, etc.)
- **Évolutions par échange** : Marquage des évolutions nécessitant un échange
- **Conditions temporelles** : Évolutions de jour/nuit
- **Conditions de genre** : Évolutions dépendantes du genre

##### 5. Formes et variantes

- **Formes Méga** : Méga-Évolutions avec stats dédiées
- **Formes Primo** : Primo-Résurgence (Groudon, Kyogre)
- **Costumes** : Pokémon avec costumes spéciaux
- **Formes régionales** : Variantes régionales
- **Mappings de formes** : Correspondance entre IDs Game Master et formes
- **Catégorisation** : Organisation par catégories (normal, mega, costume, etc.)

##### 6. Météos

- **Conditions météorologiques** : Gestion des 8 conditions (Ensoleillé, Pluvieux, etc.)
- **Boost de types** : Association météo ↔ types boostés
- **Effets sur les spawns** : Gestion des effets météo sur les apparitions

##### 7. Items

- **Objets d'évolution** : Pierres, objets spéciaux
- **Leurres** : Leurres modulaires
- **Catégorisation** : Organisation par catégories (evolution_item, lure, ball, etc.)
- **Images** : Gestion des images d'items
- **Descriptions** : Descriptions multilingues

##### 8. Backgrounds

- **Fonds spéciaux** : Fonds personnalisés pour les Pokémon
- **Association événements** : Liens avec les événements spéciaux
- **Association Pokémon** : Plusieurs Pokémon peuvent partager un background

#### Import Game Master

Le module Pokémon permet d'importer les données depuis un fichier Game Master JSON de Pokémon GO.

##### Processus d'import

1. **Upload du fichier** : Via l'interface **Poké HUB** > **Settings** > **Game Master**
2. **Sauvegarde locale** : Le fichier est sauvegardé dans `wp-content/uploads/poke-hub/gamemaster/latest.json`
3. **Archivage S3** (optionnel) : Upload automatique sur S3 avec timestamp
4. **Import des données** : Synchronisation complète dans la base de données

##### Données importées

- **Pokémon** : Tous les Pokémon avec leurs stats (ATK, DEF, STA)
- **Formes** : Toutes les formes (Méga, Primo, Costumes, etc.)
- **Attaques** : Toutes les attaques rapides et chargées
- **Statistiques PvE/PvP** : Stats complètes pour chaque contexte
- **Types** : Liens Pokémon ↔ Types
- **Attaques ↔ Types** : Association des types aux attaques
- **Pokémon ↔ Attaques** : Attaques disponibles par Pokémon
- **Évolutions** : Toutes les branches d'évolution avec conditions
- **Items** : Tous les objets (pierres, leurres, etc.)
- **Métadonnées** : Données supplémentaires (tradable, transferable, shadow, etc.)

##### Options d'import

- **Import depuis Bulbapedia** : Import automatique des types depuis Bulbapedia
- **Détection des changements** : Vérification automatique via `mtime`
- **Force import** : Option pour forcer l'import même sans changement
- **Import par batch** : Traitement par lots pour les gros fichiers

##### Résumé d'import

Après chaque import, un résumé détaillé est affiché :
- Nombre de Pokémon insérés/mis à jour
- Nombre d'attaques insérées/mises à jour
- Statistiques PvE et PvP mises à jour
- Liens créés (Pokémon ↔ Types, Attaques ↔ Types, etc.)
- Formes et variantes créées
- Évolutions importées
- Items importés

#### Interface d'administration

Accédez à **Poké HUB** > **Pokémon** pour gérer :

- **Pokémon** : Liste et édition des Pokémon
- **Types** : Gestion des types avec icônes et couleurs
- **Régions** : Organisation par régions
- **Générations** : Gestion des générations
- **Attaques** : Liste et édition des attaques
- **Items** : Gestion des objets
- **Météos** : Gestion des conditions météorologiques
- **Formes** : Gestion des variantes de formes
- **Mappings de formes** : Correspondance Game Master ↔ Formes
- **Backgrounds** : Gestion des fonds spéciaux

#### Helpers et utilitaires

Le module inclut plusieurs helpers :

- **pokemon-helpers.php** : Fonctions utilitaires générales
- **pokemon-cp-helpers.php** : Calculs de CP
- **pokemon-images-helpers.php** : Gestion des images/sprites
- **pokemon-import-game-master-helpers.php** : Helpers pour l'import GM
- **pokemon-items-helpers.php** : Gestion des items
- **pokemon-weathers-helpers.php** : Gestion des météos
- **pokemon-translation-helpers.php** : Aide à la traduction
- **pokemon-auto-translations.php** : Traductions automatiques
- **pokemon-official-names-fetcher.php** : Récupération des noms officiels
- **pokemon-type-bulbapedia-importer.php** : Import depuis Bulbapedia

#### Routing Front-end

Le module expose des routes personnalisées pour afficher les Pokémon :

- **Route** : `/pokemon-go/pokemon/{slug}/`
- **Query var** : `pokehub_pokemon`
- **Template** : Utilise le système de templates WordPress/Elementor

---

### Module Events

Le module Events gère les événements spéciaux de Pokémon GO, avec support pour les sources locales et distantes.

#### Fonctionnalités principales

##### 1. Événements spéciaux

- **Création et gestion** : Interface complète pour créer/modifier/supprimer des événements
- **Dates** : Dates de début et fin (timestamps)
- **Modes** : Support local et distant (remote)
- **Slugs** : Génération automatique de slugs uniques
- **Images** : Upload d'images pour les événements
- **Descriptions** : Descriptions multilingues (FR/EN)
- **Événements récurrents** : Support des événements récurrents (hebdomadaires, etc.)

##### 2. Types d'événements

- **Taxonomie hiérarchique** : Organisation en parent/enfant
- **Filtrage** : Filtrage par type d'événement
- **Sources distantes** : Récupération depuis d'autres sites WordPress
- **Slugs** : Gestion des slugs pour les URLs

##### 3. Pokémon d'événement

- **Association** : Lier des Pokémon à des événements
- **Multi-sélection** : Sélection multiple via Select2
- **Affichage** : Liste des Pokémon par événement

##### 4. Bonus d'événement

- **Association** : Lier des bonus (CPT) aux événements
- **Descriptions** : Descriptions spécifiques par événement
- **Affichage** : Affichage des bonus sur les pages d'événements

##### 5. Attaques spéciales

- **Attaques exclusives** : Gestion des attaques exclusives aux événements
- **Forçage** : Option pour forcer certaines attaques
- **Association Pokémon** : Lier des attaques à des Pokémon pour un événement

#### Sources distantes

Le module Events peut récupérer des données depuis un autre site WordPress (ex: JV Actu) :

##### Configuration

Dans **Settings** > **Sources** :
- **Préfixe de table Events** : Préfixe des tables d'événements distantes
- **Préfixe de table Event Types** : Préfixe des taxonomies (optionnel)

##### Fonctionnement

- **Requêtes directes** : Accès direct à la base de données distante
- **Tables supportées** : 
  - `{prefix}_posts` et `{prefix}_postmeta` pour les événements
  - `{prefix}_terms`, `{prefix}_term_taxonomy`, etc. pour les types
  - `{prefix}_pokehub_special_events` pour les événements spéciaux
- **Fallback** : Si la source distante échoue, fallback sur les données locales

#### Interface d'administration

Accédez à **Poké HUB** > **Events** pour :

- **Liste des événements** : Table avec filtres et recherche
- **Création/Édition** : Formulaire complet pour gérer les événements
- **Pokémon associés** : Sélection multiple de Pokémon
- **Bonus associés** : Sélection de bonus
- **Attaques spéciales** : Gestion des attaques exclusives
- **Filtres** : Filtrage par type d'événement, dates, etc.
- **Screen Options** : Personnalisation des colonnes affichées

#### Routing Front-end

Le module expose des routes personnalisées :

- **Route** : `/pokemon-go/events/{slug}/`
- **Query var** : `pokehub_special_event`
- **Template** : Utilise le système de templates WordPress/Elementor
- **Support distant** : Recherche automatique dans les sources locales puis distantes

#### Shortcode

Le module fournit le shortcode `[poke_hub_events]` pour afficher les événements (voir section Shortcodes).

---

### Module Bonus

Le module Bonus gère les bonus disponibles dans Pokémon GO via un Custom Post Type WordPress.

#### Fonctionnalités principales

##### 1. Custom Post Type

- **CPT** : `pokehub_bonus`
- **Visibilité** : Privé (pas de pages publiques)
- **Support** : Titre, contenu, image à la une
- **Menu** : Intégré dans le menu Poké HUB

##### 2. Métadonnées

- **Images** : Image à la une pour chaque bonus
- **Descriptions** : Contenu riche via l'éditeur WordPress
- **Slugs** : Génération automatique de slugs

##### 3. Association aux événements

- **Metabox** : Metabox sur les posts/événements pour associer des bonus
- **Module Events** : Intégration avec le module Events
- **Affichage** : Affichage des bonus sur les pages d'événements

#### Interface d'administration

Accédez à **Poké HUB** > **Bonus** pour :

- **Liste des bonus** : Interface WordPress standard pour les CPT
- **Création/Édition** : Éditeur WordPress classique
- **Métadonnées** : Gestion des images et descriptions

#### Shortcodes

Le module fournit plusieurs shortcodes (voir section Shortcodes) :
- `[pokehub-bonus]` : Affiche un ou plusieurs bonus
- `[pokehub-event-bonuses]` : Affiche les bonus d'un événement

---

### Module Pokédex

Le module Pokédex est en développement. Il permettra d'afficher un Pokédex interactif sur le front-end.

#### Statut

- **État** : En développement
- **Fonctionnalités** : À venir

---

## Fonctionnalités

### Import Game Master

Le plugin permet d'importer les données du Game Master de Pokémon GO depuis un fichier JSON.

#### Processus d'import

1. **Upload du fichier** : Uploadez un fichier JSON Game Master via l'interface
2. **Copie locale** : Le fichier est sauvegardé localement dans `wp-content/uploads/poke-hub/gamemaster/latest.json`
3. **Archivage S3** (optionnel) : Si configuré, le fichier est également uploadé sur S3 avec un timestamp
4. **Import des données** : Lancez l'import pour synchroniser les données dans la base
5. **Traitement par batch** : Les gros fichiers sont traités par lots pour éviter les timeouts

#### Détection des changements

L'import vérifie automatiquement si le fichier a changé depuis le dernier import (via `mtime`). Vous pouvez forcer l'import même si le fichier n'a pas changé en cochant **Force import**.

#### Options d'import

- **Import depuis Bulbapedia** : Option pour importer automatiquement les types depuis Bulbapedia
- **Traitement par batch** : Import par lots pour les gros fichiers
- **Résumé détaillé** : Affichage d'un résumé complet après chaque import

#### Résumé d'import

Après chaque import, un résumé détaillé est affiché :
- Nombre de Pokémon insérés/mis à jour
- Nombre d'attaques insérées/mises à jour
- Statistiques PvE et PvP mises à jour
- Liens créés (Pokémon ↔ Types, Attaques ↔ Types, etc.)
- Formes et variantes créées
- Évolutions importées
- Items importés

### Archivage S3

Le plugin peut automatiquement archiver les fichiers Game Master sur AWS S3.

#### Configuration

Dans **Settings** > **Game Master** :
- **Bucket** : Nom du bucket S3
- **Préfixe** : Dossier dans le bucket (ex: `gamemaster`)
- **Région** : Région AWS (ex: `eu-west-3`)

#### Credentials AWS

Ajoutez ces constantes dans `wp-config.php` :

```php
define('POKE_HUB_GM_AWS_KEY', 'votre-clé-aws');
define('POKE_HUB_GM_AWS_SECRET', 'votre-secret-aws');
```

#### Format des fichiers

Les fichiers sont nommés avec un timestamp : `gamemaster-YYYYMMDD-HHMMSS.json`

#### Fonctionnalités

- **Upload automatique** : Upload automatique après chaque import
- **Gestion d'erreurs** : Gestion complète des erreurs avec messages détaillés
- **Historique** : Conservation de l'historique des uploads

### Sources distantes

Le plugin supporte la récupération de données depuis d'autres sites WordPress via des requêtes directes sur la base de données distante.

#### Configuration

Dans **Settings** > **Sources**, configurez les préfixes de tables pour :
- **Events** : Préfixe des tables d'événements (posts, postmeta, special_events)
- **Event Types** : Préfixe des taxonomies (terms, term_taxonomy, etc.)

#### Fonctionnement

- **Requêtes directes** : Accès direct à la base de données distante (même serveur)
- **Fallback** : Si la source distante échoue, fallback sur les données locales
- **Cache** : Mise en cache des résultats pour améliorer les performances

#### Tables supportées

- Tables WordPress standard : `posts`, `postmeta`, `terms`, `term_taxonomy`, etc.
- Tables Poké HUB : `special_events`, `special_event_pokemon`, etc.
- Tables AS3CF : `as3cf_items` (pour les médias offloadés)

### Traductions automatiques

Le module Pokémon inclut un système de traductions automatiques :

- **Noms officiels** : Récupération des noms officiels depuis le Game Master
- **Traductions FR/EN** : Support multilingue pour les noms et descriptions
- **Import Bulbapedia** : Import automatique des types depuis Bulbapedia

### Calculs de CP

Le module Pokémon inclut des helpers pour calculer les CP :

- **CP maximum** : Calcul du CP maximum pour un Pokémon
- **CP par niveau** : Calcul du CP à un niveau donné
- **IV** : Calculs basés sur les IV (Individual Values)

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

## Routing Front-end

Le plugin expose des routes personnalisées pour afficher les Pokémon et événements sur le front-end.

### Routes Pokémon

- **URL** : `/pokemon-go/pokemon/{slug}/`
- **Query var** : `pokehub_pokemon`
- **Fichier** : `modules/pokemon/public/pokemon-front-routing.php`

#### Fonctionnement

1. **Rewrite rule** : Ajoute une règle de réécriture pour capturer les URLs Pokémon
2. **Query var** : Enregistre la variable de requête personnalisée
3. **Interception** : Intercepte la requête et récupère le Pokémon depuis la base
4. **Template** : Crée un faux post WordPress pour compatibilité avec les thèmes/Elementor
5. **404** : Retourne une 404 si le Pokémon n'existe pas

#### Variables globales

- `$pokehub_current_pokemon` : Objet Pokémon actuel (disponible globalement)

### Routes Événements

- **URL** : `/pokemon-go/events/{slug}/`
- **Query var** : `pokehub_special_event`
- **Fichier** : `modules/events/public/events-front-routing.php`

#### Fonctionnement

1. **Recherche locale** : Cherche d'abord dans la table locale
2. **Recherche distante** : Si non trouvé, cherche dans les sources distantes
3. **Template** : Crée un faux post WordPress pour compatibilité
4. **404** : Retourne une 404 si l'événement n'existe pas

#### Variables globales

- `$pokehub_current_special_event` : Objet événement actuel (disponible globalement)
- `$pokehub_current_special_event->_source` : Source de l'événement (`local` ou `remote`)

### Routes Entités Pokémon

Le module Pokémon expose également des routes pour les entités (types, régions, etc.) :

- **Fichier** : `modules/pokemon/public/pokemon-entities-front-routing.php`
- **Fonctionnalités** : Routes pour afficher les types, régions, générations, etc.

---

## Base de données

Le plugin crée plusieurs tables personnalisées pour stocker les données. Les tables sont créées automatiquement lors de l'activation des modules via la classe `Pokehub_DB`.

### Tables du module Pokémon

#### Table principale : `{prefix}_pokehub_pokemon`

Stocke les Pokémon principaux avec leurs statistiques.

**Colonnes principales** :
- `id` : ID unique
- `dex_number` : Numéro Pokédex
- `name_en`, `name_fr` : Noms en anglais et français
- `slug` : Slug unique
- `form_variant_id` : ID de la variante de forme
- `is_default` : Indique si c'est la forme par défaut
- `generation_id` : ID de la génération
- `base_atk`, `base_def`, `base_sta` : Statistiques de base
- `is_tradable`, `is_transferable` : Options d'échange/transfert
- `has_shadow`, `has_purified` : Support Shadow/Purifié
- `shadow_purification_stardust`, `shadow_purification_candy` : Coûts de purification
- `buddy_walked_mega_energy_award` : Énergie Méga gagnée en marchant
- `dodge_probability`, `attack_probability` : Probabilités de combat
- `extra` : Données supplémentaires (JSON)

#### Table : `{prefix}_pokehub_pokemon_types`

Types Pokémon (Eau, Feu, Plante, etc.).

**Colonnes** :
- `id` : ID unique
- `slug` : Slug unique (ex: `water`, `fire`)
- `name_en`, `name_fr` : Noms multilingues
- `color` : Couleur hexadécimale
- `icon` : URL de l'icône
- `sort_order` : Ordre d'affichage
- `extra` : Données supplémentaires

#### Table : `{prefix}_pokehub_regions`

Régions (Kanto, Johto, Hoenn, etc.).

**Colonnes** :
- `id` : ID unique
- `slug` : Slug unique
- `name_en`, `name_fr` : Noms multilingues
- `sort_order` : Ordre d'affichage
- `extra` : Données supplémentaires

#### Table : `{prefix}_pokehub_generations`

Générations (Gen 1, Gen 2, etc.).

**Colonnes** :
- `id` : ID unique
- `slug` : Slug unique
- `name_en`, `name_fr` : Noms multilingues
- `generation_number` : Numéro de génération
- `region_id` : ID de la région associée
- `extra` : Données supplémentaires

#### Table : `{prefix}_pokehub_attacks`

Attaques (moves) rapides et chargées.

**Colonnes** :
- `id` : ID unique
- `slug` : Slug unique
- `name_en`, `name_fr` : Noms multilingues
- `category` : Catégorie (`fast` ou `charged`)
- `duration_ms` : Durée en millisecondes
- `damage_window_start_ms`, `damage_window_end_ms` : Fenêtre de dégâts
- `energy` : Énergie générée/consommée
- `extra` : Données supplémentaires

#### Table : `{prefix}_pokehub_attack_stats`

Statistiques détaillées des attaques (PvE et PvP).

**Colonnes** :
- `id` : ID unique
- `attack_id` : ID de l'attaque
- `game_key` : Clé du jeu (ex: `pokemon_go`)
- `context` : Contexte (`pve` ou `pvp`)
- `damage` : Dégâts
- `dps` : Dégâts par seconde
- `eps` : Énergie par seconde
- `duration_ms`, `damage_window_start_ms`, `damage_window_end_ms` : Timings
- `energy` : Énergie
- `extra` : Données supplémentaires

#### Tables de liens

- **`{prefix}_pokehub_pokemon_type_links`** : Liens Pokémon ↔ Types (slot 1 ou 2)
- **`{prefix}_pokehub_attack_type_links`** : Liens Attaques ↔ Types
- **`{prefix}_pokehub_pokemon_attack_links`** : Liens Pokémon ↔ Attaques
  - Colonnes : `pokemon_id`, `attack_id`, `role` (fast/charged), `is_legacy`, `is_event`, `is_elite_tm`

#### Table : `{prefix}_pokehub_pokemon_weathers`

Conditions météorologiques.

**Colonnes** :
- `id` : ID unique
- `slug` : Slug unique
- `name_en`, `name_fr` : Noms multilingues
- `extra` : Données supplémentaires

#### Tables de liens météo

- **`{prefix}_pokehub_pokemon_type_weather_links`** : Liens Types ↔ Météos (boost)
- **`{prefix}_pokehub_pokemon_type_weakness_links`** : Liens Types ↔ Faiblesses
- **`{prefix}_pokehub_pokemon_type_resistance_links`** : Liens Types ↔ Résistances

#### Table : `{prefix}_pokehub_pokemon_form_mappings`

Mappings entre IDs Game Master et formes.

**Colonnes** :
- `id` : ID unique
- `pokemon_id_proto` : ID protobuf du Pokémon
- `form_proto` : Forme protobuf
- `form_slug` : Slug de la forme
- `label_suffix` : Suffix du label
- `sort_order` : Ordre d'affichage
- `flags` : Flags supplémentaires (JSON)

#### Table : `{prefix}_pokehub_pokemon_form_variants`

Registre global des variantes de formes.

**Colonnes** :
- `id` : ID unique
- `form_slug` : Slug unique de la forme
- `category` : Catégorie (`normal`, `mega`, `costume`, etc.)
- `group` : Groupe de formes
- `label` : Label affiché
- `extra` : Données supplémentaires

#### Table : `{prefix}_pokehub_pokemon_evolutions`

Branches d'évolution.

**Colonnes principales** :
- `id` : ID unique
- `base_pokemon_id` : ID du Pokémon de base
- `target_pokemon_id` : ID du Pokémon cible
- `base_form_variant_id`, `target_form_variant_id` : IDs des formes
- `candy_cost`, `candy_cost_purified` : Coûts en bonbons
- `is_trade_evolution` : Nécessite un échange
- `no_candy_cost_via_trade` : Pas de coût en bonbons via échange
- `is_random_evolution` : Évolution aléatoire
- `method` : Méthode d'évolution
- `item_requirement_slug`, `item_requirement_cost`, `item_id` : Objet requis
- `lure_item_slug`, `lure_item_id` : Leurre requis
- `weather_requirement_slug` : Météo requise
- `gender_requirement` : Genre requis
- `time_of_day` : Moment de la journée requis
- `priority` : Priorité d'affichage
- `quest_template_id` : ID du template de quête
- `extra` : Données supplémentaires

#### Table : `{prefix}_pokehub_items`

Objets/Items (pierres, leurres, etc.).

**Colonnes** :
- `id` : ID unique
- `slug` : Slug unique
- `proto_id` : ID protobuf (ex: `ITEM_KINGS_ROCK`)
- `category` : Catégorie (`evolution_item`, `lure`, `ball`, `mega_item`, `other`)
- `subtype` : Sous-type
- `name_en`, `name_fr` : Noms multilingues
- `description_en`, `description_fr` : Descriptions multilingues
- `image_id` : ID de l'image (média WordPress)
- `game_key` : Clé du jeu (`pokemon_go`)
- `extra` : Données supplémentaires

#### Table : `{prefix}_pokehub_pokemon_backgrounds`

Fonds spéciaux pour les Pokémon.

**Colonnes** :
- `id` : ID unique
- `slug` : Slug unique
- `title` : Titre
- `image_url` : URL de l'image
- `event_id` : ID de l'événement associé (optionnel)
- `event_type` : Type d'événement (`local_post`, `remote_post`, `special_local`, `special_remote`)
- `extra` : Données supplémentaires

#### Table : `{prefix}_pokehub_pokemon_background_pokemon_links`

Liens Background ↔ Pokémon.

**Colonnes** :
- `id` : ID unique
- `background_id` : ID du background
- `pokemon_id` : ID du Pokémon

### Tables du module Events

#### Table : `{prefix}_pokehub_special_events`

Événements spéciaux.

**Colonnes** :
- `id` : ID unique
- `slug` : Slug unique
- `title` : Titre principal
- `title_en`, `title_fr` : Titres multilingues
- `description` : Description
- `event_type` : Type d'événement (slug de taxonomie)
- `start_ts`, `end_ts` : Timestamps de début/fin
- `mode` : Mode (`local` ou `remote`)
- `recurring` : Événement récurrent (booléen)
- `recurring_freq` : Fréquence (`weekly`, `monthly`, etc.)
- `recurring_interval` : Intervalle
- `recurring_window_end_ts` : Fin de la fenêtre de récurrence
- `image_id` : ID de l'image (média WordPress)
- `image_url` : URL de l'image
- `created_at`, `updated_at` : Dates de création/mise à jour

#### Table : `{prefix}_pokehub_special_event_pokemon`

Liens Événements ↔ Pokémon.

**Colonnes** :
- `id` : ID unique
- `event_id` : ID de l'événement
- `pokemon_id` : ID du Pokémon

#### Table : `{prefix}_pokehub_special_event_bonus`

Liens Événements ↔ Bonus.

**Colonnes** :
- `id` : ID unique
- `event_id` : ID de l'événement
- `bonus_id` : ID du bonus (post ID du CPT)
- `description` : Description spécifique

#### Table : `{prefix}_pokehub_special_event_pokemon_attacks`

Attaques spéciales par événement.

**Colonnes** :
- `id` : ID unique
- `event_id` : ID de l'événement
- `pokemon_id` : ID du Pokémon
- `attack_id` : ID de l'attaque
- `is_forced` : Attaque forcée (booléen)

### Fonction helper

Utilisez `pokehub_get_table('table_name')` pour obtenir le nom complet d'une table avec le préfixe :

```php
$pokemon_table = pokehub_get_table('pokemon');
// Retourne: wp_pokehub_pokemon

// Tables distantes
$remote_posts = pokehub_get_table('remote_posts');
// Retourne: {prefix_remote}_posts
```

#### Alias disponibles

La fonction supporte plusieurs alias pour faciliter l'utilisation :

- `regions` = `pokemon_regions`
- `generations` = `pokemon_generations`
- `attacks` = `pokemon_attacks`
- `attack_stats` = `pokemon_attack_stats`
- `weathers` = `pokemon_weathers`
- `items` = `pokemon_items`
- `backgrounds` = `pokemon_backgrounds`
- `form_mappings` = `pokemon_form_mappings`
- `evolutions` = `pokemon_evolutions`

#### Tables distantes

Pour les tables distantes, utilisez le préfixe `remote_` :

- `remote_posts` : Posts WordPress distants
- `remote_postmeta` : Métadonnées de posts distants
- `remote_terms` : Termes de taxonomies distants
- `remote_termmeta` : Métadonnées de termes distants
- `remote_term_taxonomy` : Taxonomies distantes
- `remote_term_relationships` : Relations termes/posts distants
- `remote_special_events` : Événements spéciaux distants
- etc.

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

## API et Helpers

### Fonctions principales

#### Vérifier si un module est actif

```php
if (poke_hub_is_module_active('pokemon')) {
    // Le module Pokémon est actif
}
```

#### Obtenir le registre des modules

```php
$registry = poke_hub_get_modules_registry();
// Retourne: ['events' => 'events/events.php', 'bonus' => 'bonus/bonus.php', 'pokemon' => 'pokemon/pokemon.php']
```

#### Obtenir le nom d'une table

```php
$pokemon_table = pokehub_get_table('pokemon');
// Retourne: wp_pokehub_pokemon

// Tables distantes
$remote_posts = pokehub_get_table('remote_posts');
// Retourne: {prefix_remote}_posts
```

#### Vérifier si une table existe

```php
if (pokehub_table_exists('wp_pokehub_pokemon')) {
    // La table existe
}
```

#### Obtenir le préfixe des tables distantes

```php
$prefix = poke_hub_events_get_table_prefix('events');
// Retourne le préfixe configuré pour les événements

$prefix = poke_hub_events_get_table_prefix('event_types');
// Retourne le préfixe configuré pour les types d'événements
```

### Helpers du module Pokémon

#### Récupérer un Pokémon

```php
// Par ID
$pokemon = poke_hub_get_pokemon($pokemon_id);

// Par slug
$pokemon = poke_hub_get_pokemon_by_slug('pikachu');
```

#### Récupérer les types d'un Pokémon

```php
$types = poke_hub_get_pokemon_types($pokemon_id);
// Retourne un tableau d'objets types
```

#### Récupérer les attaques d'un Pokémon

```php
// Attaques rapides
$fast_attacks = poke_hub_get_pokemon_attacks($pokemon_id, 'fast');

// Attaques chargées
$charged_attacks = poke_hub_get_pokemon_attacks($pokemon_id, 'charged');
```

#### Calculer le CP

```php
// CP maximum
$max_cp = poke_hub_calculate_max_cp($pokemon_id);

// CP à un niveau donné
$cp = poke_hub_calculate_cp($pokemon_id, $level, $iv_atk, $iv_def, $iv_sta);
```

#### Récupérer les évolutions

```php
$evolutions = poke_hub_get_pokemon_evolutions($pokemon_id);
// Retourne un tableau des évolutions possibles
```

### Helpers du module Events

#### Récupérer les événements

```php
// Par statut
$current_events = poke_hub_events_get_all_sources_by_status('current', $args);
$upcoming_events = poke_hub_events_get_all_sources_by_status('upcoming', $args);
$past_events = poke_hub_events_get_all_sources_by_status('past', $args);

// Arguments possibles
$args = [
    'order' => 'asc', // ou 'desc'
    'event_type' => ['community-day', 'raid-day'], // slugs de types
    'event_type_parent' => 'events', // slug du parent
    'category' => 'slug-category',
];
```

#### Récupérer un événement

```php
// Par ID
$event = poke_hub_get_special_event($event_id);

// Par slug
$event = poke_hub_get_special_event_by_slug('slug-evenement');
```

#### Récupérer les Pokémon d'un événement

```php
$pokemon = poke_hub_get_event_pokemon($event_id);
// Retourne un tableau d'IDs de Pokémon
```

#### Récupérer les bonus d'un événement

```php
$bonuses = poke_hub_get_event_bonuses($event_id);
// Retourne un tableau d'objets bonus
```

#### Récupérer les types d'événements enfants

```php
$child_types = poke_hub_events_get_child_event_types('parent-slug');
// Retourne un tableau de termes enfants
```

### Helpers du module Bonus

#### Récupérer un bonus

```php
// Par ID
$bonus = poke_hub_get_bonus($bonus_id);

// Par slug
$bonus = poke_hub_get_bonus_by_slug('xp-double');
```

#### Récupérer les bonus d'un événement

```php
$bonuses = poke_hub_get_event_bonuses($event_id);
// Retourne un tableau d'objets bonus avec descriptions
```

### Hooks disponibles

#### Actions

- `poke_hub_modules_loaded` : Après le chargement de tous les modules
- `poke_hub_event_created` : Après la création d'un événement
- `poke_hub_event_updated` : Après la mise à jour d'un événement
- `poke_hub_pokemon_imported` : Après l'import d'un Pokémon
- `poke_hub_game_master_imported` : Après l'import complet du Game Master

#### Filtres

- `poke_hub_active_modules` : Modifier la liste des modules actifs
- `poke_hub_pokemon_data` : Modifier les données d'un Pokémon
- `poke_hub_event_data` : Modifier les données d'un événement
- `poke_hub_bonus_data` : Modifier les données d'un bonus
- `poke_hub_table_name` : Modifier le nom d'une table
- `poke_hub_events_query_args` : Modifier les arguments de requête des événements

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
poke-hub/
├── assets/                          # Assets CSS et JS
│   ├── css/                         # Feuilles de style
│   │   ├── poke-hub-events-admin.css
│   │   ├── poke-hub-events-front.css
│   │   ├── poke-hub-pokemon-admin.css
│   │   └── poke-hub-special-events-single.css
│   └── js/                          # Scripts JavaScript
│       ├── pokehub-admin-select2.js
│       ├── pokehub-media-url.js
│       ├── pokehub-pokemon-evolutions-admin.js
│       └── pokehub-special-events-admin.js
├── includes/                        # Fichiers principaux
│   ├── admin-ui.php                 # Interface d'administration principale
│   ├── pokehub-db.php               # Classe de gestion de la base de données
│   ├── functions/                   # Fonctions utilitaires
│   │   ├── pokehub-helpers.php      # Helpers généraux
│   │   └── pokehub-encryption.php   # Fonctions de chiffrement
│   └── settings/                    # Pages de paramètres
│       ├── settings.php             # Page principale des paramètres
│       ├── settings-modules.php    # Gestion des modules
│       ├── settings-module-hooks.php # Gestion des hooks
│       └── tabs/                    # Onglets des paramètres
│           ├── settings-tab-general.php
│           ├── settings-tab-gamemaster.php
│           ├── settings-tab-sources.php
│           └── settings-tab-translation.php
├── modules/                         # Modules du plugin
│   ├── bonus/                       # Module Bonus
│   │   ├── bonus.php                # Fichier principal
│   │   ├── admin/                   # Interface admin
│   │   │   └── bonus-metabox.php
│   │   └── functions/               # Fonctions du module
│   │       ├── bonus-cpt.php        # Custom Post Type
│   │       ├── bonus-helpers.php    # Helpers
│   │       └── bonus-shortcodes.php # Shortcodes
│   ├── events/                      # Module Events
│   │   ├── events.php               # Fichier principal
│   │   ├── admin/                   # Interface admin
│   │   │   ├── events-admin-special-events.php
│   │   │   ├── events-class-pokehub-events-list-table.php
│   │   │   ├── events-columns.php
│   │   │   └── forms/
│   │   ├── functions/               # Fonctions du module
│   │   │   ├── events-admin-helpers.php
│   │   │   ├── events-helpers.php
│   │   │   ├── events-queries.php
│   │   │   └── events-render.php
│   │   └── public/                  # Front-end
│   │       ├── events-front-routing.php
│   │       ├── shortcode-events.php
│   │       └── view-events-tabs.php
│   ├── pokemon/                     # Module Pokémon
│   │   ├── pokemon.php              # Fichier principal
│   │   ├── admin/                   # Interface admin
│   │   │   ├── pokemon-admin.php
│   │   │   ├── forms/               # Formulaires
│   │   │   └── sections/            # Sections de l'admin
│   │   │       ├── pokemon.php
│   │   │       ├── types.php
│   │   │       ├── regions.php
│   │   │       ├── generations.php
│   │   │       ├── moves.php
│   │   │       ├── items.php
│   │   │       ├── weathers.php
│   │   │       ├── forms.php
│   │   │       ├── form-mappings.php
│   │   │       └── backgrounds.php
│   │   ├── functions/               # Fonctions d'import
│   │   │   ├── pokemon-import-game-master.php
│   │   │   └── pokemon-import-game-master-batch.php
│   │   ├── includes/                # Helpers et utilitaires
│   │   │   ├── pokemon-helpers.php
│   │   │   ├── pokemon-cp-helpers.php
│   │   │   ├── pokemon-images-helpers.php
│   │   │   ├── pokemon-import-game-master-helpers.php
│   │   │   ├── pokemon-items-helpers.php
│   │   │   ├── pokemon-weathers-helpers.php
│   │   │   ├── pokemon-translation-helpers.php
│   │   │   ├── pokemon-auto-translations.php
│   │   │   ├── pokemon-official-names-fetcher.php
│   │   │   └── pokemon-type-bulbapedia-importer.php
│   │   └── public/                  # Front-end
│   │       ├── pokemon-front-routing.php
│   │       └── pokemon-entities-front-routing.php
│   └── pokedex/                     # Module Pokédex (en développement)
│       └── pokedex.php
├── vendor/                          # Dépendances Composer
│   ├── aws/                         # AWS SDK
│   └── ...
├── poke-hub.php                     # Fichier principal du plugin
└── uninstall.php                    # Script de désinstallation
```

### Constantes disponibles

#### Constantes principales

- `POKE_HUB_PATH` : Chemin absolu du plugin
- `POKE_HUB_URL` : URL du plugin
- `POKE_HUB_VERSION` : Version du plugin (récupérée depuis l'en-tête)
- `POKE_HUB_MODULES_DIR` : Chemin absolu du dossier des modules
- `POKE_HUB_INCLUDES_DIR` : Chemin absolu du dossier des includes
- `POKE_HUB_HOOKS_DIR` : Chemin absolu du dossier des hooks personnalisés (`wp-content/uploads/poke-hub`)
- `POKE_HUB_HOOKS_FILE` : Chemin absolu du fichier des hooks personnalisés

#### Constantes par module

- `POKE_HUB_POKEMON_PATH` : Chemin absolu du module Pokémon
- `poke_hub_POKEMON_URL` : URL du module Pokémon
- `POKE_HUB_EVENTS_PATH` : Chemin absolu du module Events
- `poke_hub_EVENTS_URL` : URL du module Events
- `POKE_HUB_BONUS_PATH` : Chemin absolu du module Bonus
- `poke_hub_BONUS_URL` : URL du module Bonus

### Ajouter un nouveau module

1. **Créer le dossier** : Créez un nouveau dossier dans `modules/` (ex: `modules/mon-module/`)

2. **Créer le fichier principal** : Créez `modules/mon-module/mon-module.php` :

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

// Vérifier que le module est actif
if (!poke_hub_is_module_active('mon-module')) {
    return;
}

// Définir les constantes du module
define('POKE_HUB_MON_MODULE_PATH', __DIR__);
define('poke_hub_MON_MODULE_URL', POKE_HUB_URL . 'modules/mon-module/');

// Charger les fichiers nécessaires
require_once __DIR__ . '/functions/mon-module-helpers.php';
require_once __DIR__ . '/admin/mon-module-admin.php';
```

3. **Ajouter au registre** : Modifiez `includes/settings/settings-modules.php` :

```php
function poke_hub_get_modules_registry(): array {
    return [
        'events'  => 'events/events.php',
        'bonus'   => 'bonus/bonus.php',
        'pokemon' => 'pokemon/pokemon.php',
        'mon-module' => 'mon-module/mon-module.php', // Ajoutez ici
    ];
}
```

4. **Créer les tables** (si nécessaire) : Ajoutez la méthode dans `includes/pokehub-db.php` :

```php
private function createMonModuleTables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    $table = pokehub_get_table('mon_module');
    
    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        // ... vos colonnes
        PRIMARY KEY (id)
    ) {$charset_collate};";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// Dans createTables()
if (in_array('mon-module', $active_modules, true)) {
    $this->createMonModuleTables();
}
```

5. **Ajouter au menu admin** (si nécessaire) : Modifiez `poke-hub.php` :

```php
if (in_array('mon-module', $active_modules, true)) {
    add_submenu_page(
        'poke-hub',
        __('Mon Module', 'poke-hub'),
        __('Mon Module', 'poke-hub'),
        'manage_options',
        'poke-hub-mon-module',
        'poke_hub_mon_module_admin_ui'
    );
}
```

### Bonnes pratiques

#### Vérification des modules

Toujours vérifier qu'un module est actif avant d'utiliser ses fonctionnalités :

```php
if (!poke_hub_is_module_active('pokemon')) {
    return;
}
```

#### Utilisation des helpers de tables

Utilisez toujours `pokehub_get_table()` pour obtenir les noms de tables :

```php
$table = pokehub_get_table('pokemon');
// Au lieu de : $table = $wpdb->prefix . 'pokehub_pokemon';
```

#### Gestion des erreurs

Toujours vérifier les erreurs lors des opérations de base de données :

```php
$result = $wpdb->query($sql);
if ($result === false) {
    // Gérer l'erreur
    error_log('Erreur SQL: ' . $wpdb->last_error);
}
```

#### Sécurité

- Utilisez toujours `$wpdb->prepare()` pour les requêtes SQL
- Sanitizez toutes les entrées utilisateur avec `sanitize_text_field()`, `sanitize_email()`, etc.
- Vérifiez les permissions avec `current_user_can()`
- Utilisez les nonces pour les formulaires

#### Internationalisation

Toujours utiliser les fonctions de traduction WordPress :

```php
__('Texte à traduire', 'poke-hub');
_e('Texte à afficher', 'poke-hub');
esc_html__('Texte HTML', 'poke-hub');
```

### Tests

Pour tester un module :

1. Activez le module dans **Settings** > **General**
2. Vérifiez que les tables sont créées
3. Testez les fonctionnalités admin
4. Testez les fonctionnalités front-end
5. Testez les shortcodes (si applicable)
6. Testez les hooks personnalisés

---

## Support

Pour toute question ou problème, contactez l'auteur via :
- **Site web** : https://me5rine.com
- **Email** : [email à définir]

---

## Changelog

### Version 1.7
- Documentation complète mise à jour
- Support des événements récurrents
- Amélioration du système de routing front-end
- Support étendu des sources distantes
- Amélioration de l'import Game Master
- Ajout des backgrounds pour les Pokémon

---

## Licence

[À définir]

