# Bonus : source de vérité et bloc (module Blocks uniquement)

## Vue d’ensemble

- **Types de bonus** : une seule source de vérité, sur le **site principal** (même principe que les Pokémon).
- **Bloc et metabox bonus** : ils ne dépendent **pas** du module Bonus. Tout est chargé par le **module Blocks** uniquement (helpers + metabox). Le module Bonus reste optionnel (écran d’admin catalogue, shortcodes, filtre `the_content`).

## Source de vérité des types de bonus

- Le **catalogue** des types de bonus (liste pour les selects, affichage) est stocké dans la table **`{prefix}pokehub_bonus_types`** (clé interne `bonus_types` côté code).
- **Site principal** (préfixe Pokémon vide ou identique au préfixe local) : lecture/écriture dans la table **locale** `bonus_types` (préfixe WordPress courant).
- **Sites distants** (préfixe Pokémon configuré dans Réglages > Sources) : lecture dans la table **distante** `remote_bonus_types` (même préfixe que les tables Pokémon du site principal). On ne crée pas les types de bonus sur le distant depuis cet écran ; on les sélectionne depuis le site principal.

Les helpers utilisés pour choisir la table sont dans `includes/functions/pokehub-helpers.php` (toujours chargé) :

- **`pokehub_get_bonus_types_table()`** : retourne la table à utiliser (`bonus_types` ou `remote_bonus_types` selon le préfixe).
- **`pokehub_bonus_use_remote_source()`** : indique si on lit les bonus depuis le site principal (préfixe distant défini).

## Données par article (contenu)

- Les **bonus associés à un article** (post) sont stockés dans les tables **`content_bonus`** et **`content_bonus_entries`** (scope **`content_source`**).
- Ces tables utilisent le **même préfixe** que les tables Pokémon (Réglages > Poké HUB > Sources > **Pokémon table prefix (remote)**). Une seule base pour les Pokémon et tous les contenus (bonus par article, quêtes, habitats, œufs, etc.).
- Chaque entrée contient un **`bonus_id`** (référence vers un type du catalogue) et une **description** éventuelle.
- En résumé : on crée les **types** de bonus sur le site principal (table catalogue) ; les associations article ↔ bonus (IDs + description) sont enregistrées dans les tables de contenu, sur la même base que les Pokémon (site principal si le préfixe est configuré sur les sites distants).

## Module Blocks : tout pour le bloc bonus

Dès que le **module Blocks** est actif :

1. **Helpers bonus** (`modules/bonus/functions/bonus-helpers.php`) sont chargés par `modules/blocks/blocks.php` :  
   `pokehub_get_all_bonuses_for_select()`, `pokehub_get_bonus_data()`, `pokehub_get_bonuses_for_post()`, `pokehub_render_bonuses_visual()`.
2. **Metabox « Bonus de l’événement »** (`modules/bonus/admin/bonus-metabox.php`) est chargée par le module Blocks : sélection des bonus (liste issue du site principal en mode distant) et description par article.
3. **Bloc « Bonus »** (`pokehub/bonus`) est enregistré avec **aucun** module requis (`requires` vide) ; il affiche les bonus selon la table locale ou distante (selon le préfixe).

Aucune activation du **module Bonus** n’est nécessaire pour utiliser le bloc et la metabox.

## Module Bonus (optionnel)

Quand le **module Bonus** est activé (en plus du module Blocks), il apporte :

- **Écran Poké HUB > Bonus** (`poke-hub-bonus-types`) sur le site principal : CRUD sur la table catalogue (titre, slug, image slug optionnel, description, ordre). Aperçu de l’icône via l’URL construite à partir des réglages Sources (bucket + chemin bonus).
- **Menu Bonus** dans l’admin Poké HUB (uniquement sur le site principal ; masqué sur les sites distants).
- **Shortcodes** et filtre **`the_content`** pour afficher les bonus dans le contenu (selon la config du module).

Sur un **site distant**, le menu Bonus et l’édition du catalogue sont masqués ; la liste des bonus dans la metabox et dans le bloc vient du site principal via `remote_bonus_types`.

## Table catalogue (`bonus_types` / `remote_bonus_types`)

- **Création** : la table locale `bonus_types` est créée lorsque le module **Bonus** ou le module **Blocks** est actif (`includes/pokehub-db.php`, `createBonusTypesTable()`). Une migration (`migrateBonusTypesTableSchema`) aligne le schéma existant : `id` AUTO_INCREMENT, `slug` unique.
- **Colonnes** : `id` (PK AUTO_INCREMENT), `title`, `slug` (unique), `description`, `image_slug`, `sort_order`, `created_at`, `updated_at`.
- **Mapping** (dans `pokehub-helpers.php`) :
  - `bonus_types` → table locale (site principal).
  - `remote_bonus_types` → scope `remote_pokemon`, même préfixe que les tables Pokémon (lecture sur le site principal depuis un site distant).

## Résumé

| Élément                         | Dépendance        | Comportement |
|---------------------------------|-------------------|--------------|
| Bloc Bonus                      | **Blocks** seul   | Enregistré et fonctionnel avec uniquement le module Blocks. |
| Metabox Bonus                   | **Blocks** seul   | Chargée par le module Blocks. |
| Liste des types de bonus        | Préfixe Pokémon   | Site principal → table locale ; site distant → table du site principal (`remote_bonus_types`). |
| Création / édition des types    | Module **Bonus**  | Page admin `poke-hub-bonus-types` (table catalogue). |
| Données par article (bonus + description) | Même base que Pokémon | Tables `content_bonus` / `content_bonus_entries` (préfixe Sources = Pokémon et tous les contenus). |

Voir aussi : [blocks/README.md](./blocks/README.md), [CONTENT_BLOCKS.md](./CONTENT_BLOCKS.md).
