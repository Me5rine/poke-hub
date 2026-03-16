# Import des dates de sortie depuis Pokekalos

Ce document décrit l’import des **dates de sortie** (normal, shiny, shadow, dynamax, gigantamax) depuis les fiches Pokédex Pokémon GO du site [Pokekalos](https://www.pokekalos.fr/pokedex/pokemongo/).

## Vue d’ensemble

- **Source** : bloc « Notes supplémentaires » (div#notes) des pages `https://www.pokekalos.fr/pokedex/pokemongo/{slug}-{dex}.html`.
- **Stockage** : les dates sont enregistrées dans `pokemon.extra['release']` au format **YYYY-MM-DD** (ISO).
- **Cible** : uniquement les Pokémon avec un **nom français** (`name_fr` non vide). Les formes de base et les formes Méga ont des fiches dédiées ; seules ces deux catégories sont importées automatiquement.

## Format des dates en base

- **Format attendu** : `YYYY-MM-DD` (ex. `2016-07-06`).
- Les champs « Release dates » en admin utilisent un **sélecteur de date** (`<input type="date">`) et enregistrent en ISO.
- Une fonction helper **`poke_hub_normalize_release_date($date)`** (dans `includes/functions/pokehub-helpers.php`) convertit les entrées JJ/MM/AAAA ou YYYY-MM-DD vers YYYY-MM-DD.

## Outil d’import (admin)

Menu **Poké HUB > Outils temporaires** :

- **Import dates de sortie Pokekalos** : formulaire avec :
  - **Mode simulation (dry-run)** : affiche les actions sans modifier la base.
  - **Ne pas écraser les dates déjà renseignées** : ne remplit que les champs de date vides (conserve les valeurs existantes).
  - **Limite espèces** : `0` = toutes les espèces concernées ; `N` = traiter uniquement les N premières (par numéro de dex).
  - **Délai (secondes)** : pause entre chaque requête vers Pokekalos (par défaut 1).
- Le résultat (log) s’affiche sous le formulaire après exécution.

Ce menu est **temporaire** : une fois les imports terminés, le fichier `includes/admin-tools.php` et son `require` dans `poke-hub.php` peuvent être retirés.

## Script CLI

En ligne de commande (depuis la racine du plugin ou de WordPress) :

```bash
php scripts/import-pokekalos-release-dates.php
php scripts/import-pokekalos-release-dates.php --dry-run
php scripts/import-pokekalos-release-dates.php --limit=50 --skip-existing --delay=2
```

**Options :**

| Option | Description |
|--------|-------------|
| `--dry-run` | Affiche les actions sans modifier la base. |
| `--limit=N` | Traite au plus N espèces (formes de base) + N formes méga. |
| `--skip-existing` | Ne pas écraser les dates déjà renseignées. |
| `--delay=N` | Délai en secondes entre chaque requête (défaut : 1). |

## Comportement de l’import

### 1. Formes de base (`is_default = 1`)

- **URL** : `{slug}-{dex}.html` (ex. `pikachu-25.html`, `roucarnage-18.html`).
- **Liste** : une entrée par `dex_number` parmi les Pokémon avec nom FR et `is_default = 1`.
- **Mise à jour** : seules les lignes avec ce `dex_number` **et** `is_default = 1` sont modifiées. Les costumes, méga, etc. ne sont pas touchés.

### 2. Formes Méga (`is_default = 0`, catégorie forme = mega)

- **URL** : `{slug}-{dex}m.html` (ex. [mega-roucarnage-18m.html](https://www.pokekalos.fr/pokedex/pokemongo/mega-roucarnage-18m.html)).
- **Liste** : tous les Pokémon avec `is_default = 0`, forme de catégorie `mega` et nom FR.
- **Mise à jour** : uniquement la ligne du Pokémon correspondant (une par forme méga). Les dates de la fiche méga (ex. 18/09/2020) sont appliquées à cette forme, pas au Pokémon de base.

### Formes non gérées automatiquement

- **Costumes** (Pikachu chapeau, etc.) : fiches Pokekalos avec des suffixes variés (`25c`, `25v`, …). Non importées par ce script ; les dates sont à renseigner à la main ou via un outil dédié si besoin.

## Fichiers concernés

| Fichier | Rôle |
|---------|------|
| `includes/functions/pokehub-pokekalos-release-parser.php` | Parse le HTML des notes Pokekalos et extrait les clés normal, shiny, shadow, dynamax, gigantamax. |
| `includes/functions/pokehub-pokekalos-import.php` | Logique d’import : `poke_hub_run_pokekalos_import($options)`. Utilisée par le CLI et par l’admin. |
| `includes/functions/pokehub-helpers.php` | `poke_hub_normalize_release_date($date)` pour le format YYYY-MM-DD. |
| `includes/admin-tools.php` | Menu « Outils temporaires » et formulaire d’import (module temporaire). |
| `scripts/import-pokekalos-release-dates.php` | Script CLI qui appelle `poke_hub_run_pokekalos_import()`. |

## Admin Pokémon : dates de sortie

Dans **Poké HUB > Pokémon** > édition d’un Pokémon :

- Section **Release dates** : un **sélecteur de date** par type (Normal, Shiny, Shadow, Mega, Dynamax, Gigantamax).
- Les valeurs sont lues/écrites en **YYYY-MM-DD** ; les anciennes valeurs JJ/MM/AAAA sont normalisées à l’affichage et à l’enregistrement.

## Résumé des options d’import

| Option | Effet |
|--------|--------|
| **Limite = 0** | Toutes les espèces (formes de base + formes méga). |
| **Limite = N** | Les N premières espèces de chaque catégorie (base puis méga). |
| **Ignorer les existants** | Ne remplit que les champs de date vides ; ne modifie pas les dates déjà renseignées. |
| **Dry-run** | Aucune modification en base ; log uniquement. |
