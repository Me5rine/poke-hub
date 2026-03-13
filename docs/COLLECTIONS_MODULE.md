# Module Collections Pokémon GO

## Vue d'ensemble

Système de suivi des collections Pokémon GO (type [POGO Collection](https://pogo-collection.com/)) : le joueur crée une ou plusieurs collections (100 %, chromatiques, costumés, fonds, chanceux, obscurs, purifiés, Gigamax, Dynamax, etc.), coche ce qu'il possède / a à l'échange, et peut partager sa liste (lien, image).

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
| `background`            | Pokémon avec fonds                   |
| `background_shiny`      | Fonds chromatiques                   |
| `lucky`                 | Chanceux                             |
| `shadow`                | Obscurs                              |
| `purified`              | Purifiés                             |
| `gigantamax`            | Gigamax                              |
| `dynamax`               | Dynamax (et variantes shiny/100 %)  |
| `custom`                | Liste personnalisée (tous ou filtre) |

Une collection a aussi des **options de composition** (case à cocher) :

- Inclure le Pokédex national (sinon seulement une génération ou un filtre).
- Inclure les différences de genre (Nidoran M/F, etc.).
- Inclure les formes alternatives (Alola, Galar, etc.).
- Inclure les Pokémon costumés (pour les catégories qui le permettent).
- Inclure les attaques spéciales (ex. Dracaufeu avec attaque événement).

Le **pool** de Pokémon d’une collection = tous les `pokemon.id` (formes déjà dans la table `pokemon`) filtrés selon la catégorie et ces options.

## Modèle de données

### Table `pokehub_collections`

- `id`, `user_id` (NULL = non utilisé pour anonymes, on ne stocke pas les collections anonymes en base).
- `name`, `slug` (unique par user), `category` (slug de catégorie).
- `options` (JSON) : `include_national_dex`, `include_gender`, `include_forms`, `include_costumes`, `include_special_attacks`, `display_mode` (tiles | select), `public`.
- `created_at`, `updated_at`.

### Table `pokehub_collection_items`

- `collection_id`, `pokemon_id`, `status` (owned | for_trade | missing).
- Clé primaire `(collection_id, pokemon_id)`.

Pour les collections “locales” (non connecté), pas de ligne en base : le front garde en localStorage un objet du type `{ id: uuid, name, category, options, items: { [pokemon_id]: status } }`.

## Expérience utilisateur

1. **Création** : formulaire “Informations de la collection” (nom, catégorie, options, publique, mode d’affichage). Si non connecté : message “Cette collection sera stockée localement sur cet appareil. Créez un compte pour sauvegarder.”
2. **Édition** : grille de tuiles (1 clic = possédé, 2e clic = à l’échange / non possédé selon le cycle) **ou** liste vide + select des Pokémon manquants (mode configurable).
3. **Partage** : lien (slug ou id), export image (canvas ou serveur).
4. **Mise à jour** : le joueur revient sur la collection (connecté ou même appareil en local) et met à jour les cases.

## Dépendances

- Module **Pokémon** obligatoire (tables pokemon, pokemon_form_variants, etc. pour construire le pool).

## Fichiers principaux

- `modules/collections/collections.php` — bootstrap du module.
- `modules/collections/functions/collections-helpers.php` — pool, CRUD collections/items.
- `modules/collections/public/collections-shortcode.php` — shortcodes `[poke_hub_collections]` et `[poke_hub_collection_view]`.
- `modules/collections/public/collections-rest.php` — API REST (pool, CRUD, items).
- `modules/collections/assets/css/collections-front.css` et `assets/js/collections-front.js` — front.

## Mise en place d’une page

Sur la page dédiée aux collections, placer les deux shortcodes :

```
[poke_hub_collections]
[poke_hub_collection_view]
```

Quand l’URL contient `?collection=slug&view=1` (ou `?id=123`), la vue de la collection s’affiche sous la liste ; sinon seule la liste et le bouton « Créer une collection » s’affichent.
