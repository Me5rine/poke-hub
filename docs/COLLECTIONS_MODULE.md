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
| `legendary_mythical_ultra` | Liste préparamétrée Légendaire, Fabuleux et Ultra-chimères |
| `custom`                | Liste personnalisée (tous ou filtre) |

Les catégories **spécifiques** (Gigantamax, Dynamax, Costume, Shadow, Purified, Fonds, etc.) affichent **uniquement** ce type : les options « inclure Méga / Gigantamax / Dynamax / costumes » ne sont pas proposées (paramètres adaptatifs). Voir la section *Catégories spécifiques* ci-dessous.

Pour les catégories **non spécifiques** mais dont le **sens de la liste** exclut certaines options (ex. pas de bébés dans une liste L/M/UC), une **deuxième couche** masque des contrôles ciblés : voir *Options masquées par catégorie (UI)*.

### Options de composition

Une collection a des **options** (JSON) ; selon la catégorie, seules les options pertinentes sont affichées :

- **Listes « normales »** (Custom, Shiny, Lucky, Perfect 4*, etc.) : on peut afficher ou masquer des éléments en plus des Pokémon de base :
  - Inclure le Pokédex national, les **formes alternatives** (variantes hors « genre »), les Pokémon avec **fonds GO**, etc.
  - **Genre (deux notions distinctes)** :
    - **`include_gender`** : garder les **lignes séparées** quand le jeu a **deux fiches distinctes** liées à l’attribut de forme *gender* en base (ex. Nidoran, formes mâle/femelle de Mistigrix) — filtre SQL sur `form_category = gender`.
    - **`show_gender_symbols`** : afficher **♂ / ♀** à droite du nom **uniquement** sur ces lignes de variante *gender* (sans effet sur les autres).
    - **`include_both_sexes_collector`** : pour chaque fiche du pool dont le **profil de genre** (`poke_hub_pokemon_get_gender_profile`, champs `available_genders` dans `extra`) déclare **à la fois** `male` et `female`, le pool est **doublé** en deux entrées (même nom d’espèce, symboles ♂/♀) avec des **`pokemon_id` synthétiques** (plage réservée à partir de `2200000000`, voir `poke_hub_collections_sex_synthetic_pokemon_id()` dans `collections-helpers.php`) afin de suivre mâle et femelle **séparément** dans `pokehub_collection_items`. Les espèces **sans genre** ou **un seul sexe** ne sont pas dupliquées. Les lignes déjà de type variante *gender* ne sont pas re-découpées par cette option.
  - Inclure les Pokémon costumés, les Méga, les Gigantamax, les Dynamax.
  - Inclure les attaques spéciales (ex. Dracaufeu avec attaque événement).
- **Listes « spécifiques »** (Gigantamax, Dynamax, Costume, Shadow, Purified, Fonds…) : pas d’options Méga/Giga/Dynamax/costumes — la collection = uniquement ce type.

### Options masquées par catégorie (UI)

Indépendamment du masquage **global** du bloc « filtre de contenu » pour les catégories *spécifiques*, le helper **`poke_hub_collections_settings_hidden_control_keys( string $category )`** (`collections-helpers.php`) retourne une liste de **clés logiques** de contrôles à ne **pas** afficher, car elles n’ont pas de sens pour ce type de liste. Exemples actuels :

| Catégorie (slug) | Clés masquées (résumé) |
|------------------|-------------------------|
| `legendary_mythical_ultra` | `include_baby_pokemon`, `pool_option_baby`, `pool_option_special_all` |
| `gigantamax`, `dynamax` | `include_baby_pokemon`, `pool_option_baby` ; en plus `include_gigantamax` (Gigamax seul) ou `include_dynamax` (Dynamax seul) |
| `costume`, `costume_shiny` | `include_costumes` |
| `background*` (tous les slugs fonds du registre) | `include_backgrounds` |

**Extension** : filtre WordPress **`poke_hub_collections_settings_hidden_control_keys`** (paramètres : liste de clés, slug de catégorie). Pour exposer la même matrice au JavaScript sans requête : **`poke_hub_collections_settings_hidden_control_keys_map_for_ui()`** → passée dans `pokeHubCollections.settingsHiddenByCategory` lors de l’enqueue du script front (`modules/collections/collections.php`).

**Création** : dans le shortcode, les cases et options concernées portent l’attribut **`data-collections-control="<clé>"`**. `collections-front.js` ajoute ou retire la classe **`is-hidden`** sur les `<label>` et l’attribut **`hidden`** sur les `<option>` du select « Inclure seulement » ; à l’envoi, **`getCreateFormData()`** n’utilise pas les cases masquées et applique des **valeurs par défaut** alignées sur la sémantique de la catégorie (même logique que si l’utilisateur ne pouvait pas les cocher).

**Édition** : pour les collections **non** « spécifiques », le drawer n’affiche pas les lignes dont la clé est dans la liste masquée ; les options du select « Inclure seulement » absentes si inapplicables. À la sauvegarde, le même jeu de clés est appliqué côté JS pour ne pas renvoyer d’options contradictoires si un champ n’est pas dans le DOM.

**CSS** : le thème doit masquer `label[data-collections-control].is-hidden` dans le bloc de création (et le drawer d’édition si besoin) — voir **`css/poke-hub/parts/13-collections-front.css`** et **`modules/collections/COLLECTIONS_THEME_CSS.md`**.

Options communes (toutes catégories) : `one_per_species` (une entrée par espèce), `group_by_generation`, `generations_collapsed`, `display_mode` (tiles | select). En mode **select**, une liste avec sélecteur Select2 permet d’ajouter les Pokémon manquants.

### Pool et date de sortie

Le **pool** = Pokémon (table `pokemon` + formes) filtrés par catégorie et options. Seuls les Pokémon ayant une **date de sortie dans Pokémon GO** pour le contexte (normal, shiny, shadow, gigantamax, dynamax) sont inclus. Si aucun Pokémon n’a de date de sortie renseignée, le pool est **vide** (pas de repli sur toute la liste).

### Catégories spécifiques (paramètres adaptatifs)

- **Helpers PHP** : `poke_hub_collections_get_specific_categories()` (liste des slugs), `poke_hub_collections_category_is_specific( $category )`.
- **Comportement** : pour ces catégories, le formulaire de création n’affiche pas les cases « Méga / Gigantamax / Dynamax / costumes » (un bloc d’info les remplace) ; en édition, seul un texte explicatif + « Une entrée par espèce » et les options d’affichage sont proposés. Le calcul du pool ignore les options `include_mega`, `include_gigantamax`, `include_dynamax`, `include_costumes`.
- **Rappel** : les clés de **`poke_hub_collections_settings_hidden_control_keys()`** s’appliquent surtout aux catégories **non** spécifiques (ex. `legendary_mythical_ultra`, ou pour **normaliser** les valeurs envoyées à l’API quand le bloc filtre est masqué mais que des champs restent dans le DOM). Voir *Options masquées par catégorie (UI)*.

## Modèle de données

### Table `pokehub_collections`

- `id`, `user_id` (NULL = non utilisé pour anonymes, on ne stocke pas les collections anonymes en base).
- `name`, `slug` (unique par user), `category` (slug de catégorie).
- `options` (JSON) : `include_national_dex`, `include_gender`, `show_gender_symbols`, `include_both_sexes_collector`, `include_forms`, `include_costumes`, `include_mega`, `include_gigantamax`, `include_dynamax`, `include_backgrounds`, `include_special_attacks`, `one_per_species`, `group_by_generation`, `generations_collapsed`, `display_mode` (tiles | select), `card_background_image_url`, `public`, etc.
- `created_at`, `updated_at`.

### Table `pokehub_collection_items`

- `collection_id`, `pokemon_id`, `status` (owned | for_trade | missing).
- Clé primaire `(collection_id, pokemon_id)`.
- Les **`pokemon_id`** sont en principe des id de la table `pokemon` ; en **mode collectionneur mâle/femelle** (`include_both_sexes_collector`), des id **synthétiques** (plage `2200000000+`) peuvent apparaître pour les deux lignes d’une même espèce. L’affichage et l’API REST acceptent ces id comme pour les entrées Gigamax synthétiques (`2100000000+`).

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
2. **Édition** : grille de tuiles (clic = cycle **manquant → possédé → à l’échange → manquant**) **ou** liste + Select2 pour ajouter les Pokémon manquants (mode configurable). **Légende** : possédé (vert), à l’échange (orange), manquant (gris). Bloc **Afficher dans la grille** : une case cochée par statut **affiché** ; tout décocher vide la grille et affiche un rappel ; avec **regroupement par génération**, les blocs sans aucune tuile visible sont masqués (voir *Statuts d’une entrée*). Les Pokémon **sans génération résolue** en base (taxonomie `generations`) sont regroupés **en dernier** dans la liste / les sections, pas avant la génération 1 (`poke_hub_collections_sort_pool_display`, `poke_hub_collections_group_pool_by_generation`).
3. **Partage** : lien (slug ou id), export image (canvas ou serveur).
4. **Mise à jour** : le joueur revient sur la collection et met à jour les statuts ou les paramètres (drawer paramètres pour les options).
5. **Phrases de recherche Pokémon GO** : bloc `<details>` rendu par `poke_hub_collections_output_pogo_search_block()` (`collections-shortcode.php`). Le libellé du `<summary>` est une chaîne traduisible (domaine `poke-hub` ; source actuelle du type *Pokémon GO: your hunt strings, ready to paste*). Génération côté client dans `collections-front.js`. Le pool et les statuts sont lus sur `.pokehub-collection-tiles` (`data-pool`, `data-items` ; côté serveur les items sont **résolus** avec `poke_hub_collections_resolved_items_map()` pour hériter du statut de la fiche de base sur les lignes à id synthétique mâle/femelle). Barre d’outils : deux `<select>` (statut de liste, mode noms FR / EN / n° Pokédex) sur **une même ligne** (gauche / droite), avec les classes `pokehub-collection-pogo-search-toolbar` et `pokehub-collection-pogo-search-toolbar-field--status` / `--token`. Les groupes affichés (titres courts côté i18n `pogoGroup*` dans `collections.php`) incluent notamment Classic, formes régionales, méga, dynamax, gigamax, mâle/femelle, costume, fonds simples, **fonds + dynamax**, **fonds + gigantamax**, selon le pool. Chaque groupe non vide : titre `h4.pokehub-pogo-search-group-title`, grille **deux colonnes** de groupes (`.pokehub-pogo-search-groups`), champ + bouton copier. **Une seule entrée par n° de dex** sur l’ensemble des groupes (même espèce dans plusieurs sections → un seul jeton copiable, aligné sur le jeu). **Styles** : uniquement dans le thème, `css/poke-hub/parts/13-collections-front.css` (section *Recherche in-game Pokémon GO*), variables `--me5rine-lab-*` ; le fichier plugin `poke-hub-collections-cascade-late.css` ne contient plus de règles dédiées à ce bloc (filet de cascade pour le reste des collections).

### Phrases GO : données serveur et langue

Le pool renvoyé par `poke_hub_collections_get_pool()` est enrichi dans `poke_hub_collections_pool_rows_add_pogo_tokens()` (`modules/collections/functions/collections-helpers.php`) avant sérialisation JSON (`data-pool`, REST).

| Champ | Rôle |
|-------|------|
| `pogo_token_fr` / `pogo_token_en` | Jeton pour la partie **espèce** après le `&` (comportement type jeu : recherche par **nom d’espèce**, pas par libellé de forme fusion / Necrozma, etc.) : dès qu’un **n° National Dex** est connu et qu’une fiche **de base** existe pour ce dex (`poke_hub_collections_pogo_base_names_by_dex`, requête sur `pokemon` en privilégiant `is_default` / la forme par défaut), le jeton est dérivé de ce **nom d’espèce** pour **toutes** les lignes du pool (avec ou sans `form_variant_id`). Repli si aucune fiche base : noms de la ligne ; puis slug (`poke_hub_collections_pogo_token_from_slug`). Les suffixes régionaux / méga / gigamax résiduels sont encore affinés côté JS (`pogoStripRegionalFormTokenNoise`, `pogoSearchFormTokenNamePart`). |
| `pogo_group_prefix_fr` / `pogo_group_prefix_en` | Préfixe avant le `&` pour les **formes variantes** : lu depuis **`pokemon_form_variants`** (`extra.names.fr` / `extra.names.en`, sinon `label`), puis « collé » comme le reste (accents retirés, alphanum). **Aucun libellé régional en dur** pour Hisui / Alola / etc. : la langue choisie par l’utilisateur (FR vs EN dans le sélecteur du bloc GO) détermine quel champ est utilisé. |

Côté JS (`pogoGroupPrefix`), le préfixe d’un groupe régional utilise d’abord ces champs à partir d’**une ligne exemple** du groupe (première ligne après tri). Les groupes **`male` / `female`** conservent encore des préfixes techniques `male&` / `female&` (pas de table de variant dédiée). Les autres préfixes « vides » dans la table `POGO_PREFIX` laissent la place aux données ci-dessus.

**Phrases copiables** : `pogoBuildPhrasesForWrap` parcourt les groupes dans l’ordre `POGO_GROUP_ORDER` ; pour chaque **n° de dex** national, la **première** occurrence (groupe le plus haut dans cet ordre) fournit le jeton, les lignes suivantes du **même dex** sont ignorées — évite les doublons entre Standard, Autre, etc. À l’intérieur d’un même groupe, les jetons identiques restent dédoublonnés comme auparavant. La fusion mâle/femelle (`pogoMergeMaleFemaleDupesIntoBase`) s’applique après cette étape.

**Limite actuelle** : si un même groupe regroupe plusieurs **variants** différents, une seule ligne sert d’exemple pour le préfixe ; en pratique un groupe = une famille de forme (slug / catégorie) pour les régions.

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
