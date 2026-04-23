# Module Collections Pokémon GO

## Vue d'ensemble

Système de suivi des collections Pokémon GO (type [POGO Collection](https://pogo-collection.com/)) : le joueur crée une ou plusieurs collections (100 %, chromatiques, costumés, fonds, chanceux, obscurs, purifiés, Gigamax, Dynamax, etc.), coche ce qu’il possède / a à l’échange, et peut partager sa liste (lien, image).

## Stockage : connecté vs non connecté

- **Connecté** : données en base (tables `pokehub_collections`, `pokehub_collection_items`). Sauvegarde persistante, multi-appareils, partage par lien stable.
- **Non connecté** : **uniquement stockage local** (localStorage + cookie de fallback pour l’ID de collection). Aucune sauvegarde serveur par défaut.

### Pourquoi ne pas stocker par IP pour les anonymes ?

- **Problèmes** : une même personne change d’IP (mobile, autre box), plusieurs joueurs derrière la même IP (famille, bureau) partageraient une seule “liste”. Donc mauvaise identification et risques de conflits.
- **Recommandation** : ne pas utiliser l’IP comme clé de stockage. Garder le mode non connecté = 100 % local (localStorage + cookie optionnel pour persister un identifiant de “session collection”). Si besoin d’une récupération “sans compte”, on peut envisager plus tard un **lien magique** (token dans l’URL) qui charge/sauvegarde une collection éphémère en base, sans lien à une IP.

## Catégories de collection

Chaque collection a une **catégorie** qui définit ce qu’on suit :

| Catégorie (slug)        | Description                          |
|-------------------------|--------------------------------------|
| `perfect_4`              | Pokémon 4* (parfaits)                |
| `shiny`                 | Chromatiques                         |
| `costume`               | Pokémon costumés                     |
| `costume_shiny`         | Costumés chromatiques                |
| `background`            | Pokémon avec fonds (tous)             |
| `background_special`    | Fonds : spéciaux                      |
| `background_places`     | Fonds : lieux                         |
| `background_shiny`      | Fonds chromatiques (tous)             |
| `background_shiny_special` | Fonds chromatiques : spéciaux     |
| `background_shiny_places`  | Fonds chromatiques : lieux        |
| `lucky`                 | Chanceux                             |
| `shadow`                | Obscurs                              |
| `purified`              | Purifiés                             |
| `gigantamax`            | Gigamax                              |
| `dynamax`               | Dynamax (et variantes shiny/100 %)  |
| `custom`                | Liste personnalisée (tous ou filtre) |

Les catégories **spécifiques** (Gigantamax, Dynamax, Costume, Shadow, Purified, Fonds, etc.) affichent **uniquement** ce type : les options « inclure Méga / Gigantamax / Dynamax / costumes » ne sont pas proposées (paramètres adaptatifs). Voir la section *Catégories spécifiques* ci-dessous.

### Options de composition

Une collection a des **options** (JSON) ; selon la catégorie, seules les options pertinentes sont affichées :

- **Listes « normales »** (Custom, Shiny, Lucky, Perfect 4*, etc.) : on peut afficher ou masquer des éléments en plus des Pokémon de base :
  - Inclure le Pokédex national, les différences de genre, les formes alternatives.
  - Inclure les Pokémon costumés, les Méga, les Gigantamax, les Dynamax.
  - Inclure les attaques spéciales (ex. Dracaufeu avec attaque événement).
- **Listes « spécifiques »** (Gigantamax, Dynamax, Costume, Shadow, Purified, Fonds…) : pas d’options Méga/Giga/Dynamax/costumes — la collection = uniquement ce type.

Options communes (toutes catégories) : `one_per_species` (une entrée par espèce), `group_by_generation`, `generations_collapsed`, `display_mode` (tiles | select). En mode **select**, une liste avec sélecteur Select2 permet d’ajouter les Pokémon manquants.

### Pool et date de sortie

Le **pool** = Pokémon (table `pokemon` + formes) filtrés par catégorie et options. Seuls les Pokémon ayant une **date de sortie dans Pokémon GO** pour le contexte (normal, shiny, shadow, gigantamax, dynamax) sont inclus. Si aucun Pokémon n’a de date de sortie renseignée, le pool est **vide** (pas de repli sur toute la liste).

### Catégories spécifiques (paramètres adaptatifs)

- **Helpers PHP** : `poke_hub_collections_get_specific_categories()` (liste des slugs), `poke_hub_collections_category_is_specific( $category )`.
- **Comportement** : pour ces catégories, le formulaire de création n’affiche pas les cases « Méga / Gigantamax / Dynamax / costumes » (un bloc d’info les remplace) ; en édition, seul un texte explicatif + « Une entrée par espèce » et les options d’affichage sont proposés. Le calcul du pool ignore les options `include_mega`, `include_gigantamax`, `include_dynamax`, `include_costumes`.

## Modèle de données

### Table `pokehub_collections`

- `id`, `user_id` (NULL = non utilisé pour anonymes, on ne stocke pas les collections anonymes en base).
- `name`, `slug` (unique par user), `category` (slug de catégorie).
- `options` (JSON) : `include_national_dex`, `include_gender`, `include_forms`, `include_costumes`, `include_mega`, `include_gigantamax`, `include_dynamax`, `include_special_attacks`, `one_per_species`, `group_by_generation`, `generations_collapsed`, `display_mode` (tiles | select), etc.
- `created_at`, `updated_at`.

### Table `pokehub_collection_items`

- `collection_id`, `pokemon_id`, `status` (owned | for_trade | missing).
- Clé primaire `(collection_id, pokemon_id)`.

Pour les collections “locales” (non connecté), pas de ligne en base : le front garde en localStorage un objet du type `{ id: uuid, name, category, options, items: { [pokemon_id]: status } }`.

### Statuts d’une entrée (données ↔ interface)

Chaque Pokémon du pool a un **statut** stocké côté données (`pokehub_collection_items.status`, REST, `localStorage`) et reflété sur la tuile par l’attribut `data-status` :

| Valeur technique | Rôle |
|------------------|------|
| `owned` | Possédé |
| `for_trade` | À l’échange (équivalent du libellé anglais source *For trade*, domaine d’extension `poke-hub`) |
| `missing` | Manquant |

Le bloc **Afficher dans la grille** ne change pas ces valeurs : il **filtre uniquement l’affichage** des tuiles (chaîne source anglaise *Show in grid*, même domaine). En français, l’interface désigne ces états comme **possédé**, **à l’échange**, **manquant** ; le code et l’API gardent `owned`, `for_trade`, `missing`.

## Expérience utilisateur

1. **Création** : drawer (panneau latéral) avec nom, type (catégorie), options selon le type (voir *Catégories spécifiques*), mode d’affichage (tuiles ou liste + sélecteur). Si non connecté : message de stockage local.
2. **Édition** : grille de tuiles (clic = cycle **manquant → possédé → à l’échange → manquant**) **ou** liste + Select2 pour ajouter les Pokémon manquants (mode configurable). **Légende** : possédé (vert), à l’échange (orange), manquant (gris). Bloc **Afficher dans la grille** : une case cochée par statut **affiché** ; tout décocher vide la grille et affiche un rappel ; avec **regroupement par génération**, les blocs sans aucune tuile visible sont masqués (voir *Statuts d’une entrée*).
3. **Partage** : lien (slug ou id), export image (canvas ou serveur).
4. **Mise à jour** : le joueur revient sur la collection et met à jour les statuts ou les paramètres (drawer paramètres pour les options).

## Dépendances

- Module **Pokémon** obligatoire (tables pokemon, pokemon_form_variants, etc. pour construire le pool).

## Fichiers principaux

- `modules/collections/collections.php` — bootstrap du module.
- `modules/collections/functions/collections-helpers.php` — pool, CRUD collections/items.
- `modules/collections/public/collections-shortcode.php` — shortcodes `[poke_hub_collections]` et `[poke_hub_collection_view]`.
- `modules/collections/public/collections-rest.php` — API REST (pool, CRUD, items).
- Styles front : **dans le thème** Me5rine, `css/poke-hub/parts/13-collections-front.css` et `14-collections-theme.css` (importés par `poke-hub-front.css` ; priorité d’ordre : voir [THEME_FRONT_CSS.md](./THEME_FRONT_CSS.md)). En mode plugin pur (`poke_hub_load_default_plugin_front_css` = true), le fichier `assets/css/poke-hub-collections-front.css` s’enfile s’il est présent. `modules/collections/assets/js/collections-front.js` — front.
- `modules/collections/COLLECTIONS_THEME_CSS.md` — référence classes / variables (légende, filtre **Afficher dans la grille**, cartes liste). Chaînes traduisibles : **docs/TRANSLATION.md**. Conventions doc : **docs/REDACTION.md**.

## Mise en place d’une page

Sur la page dédiée aux collections, placer les deux shortcodes :

```
[poke_hub_collections]
[poke_hub_collection_view]
```

Quand l’URL contient `?collection=slug&view=1` (ou `?id=123`), la vue de la collection s’affiche sous la liste ; sinon seule la liste et le bouton « Créer une collection » s’affichent.

---

*Index de la documentation : [README du dossier docs](README.md) · [Charte rédactionnelle](REDACTION.md)*
