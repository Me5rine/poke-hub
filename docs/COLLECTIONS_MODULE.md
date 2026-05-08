# Module Collections Pokémon GO

## Vue d'ensemble

Système de suivi des collections Pokémon GO (type [POGO Collection](https://pogo-collection.com/)) : le joueur crée une ou plusieurs collections (100 %, chromatiques, costumés, fonds, chanceux, obscurs, purifiés, Gigamax, Dynamax, etc.), coche ce qu’il possède / a à l’échange, et peut partager sa liste (lien, image).

## Stockage : connecté vs visiteur sans compte

- **Compte WordPress connecté** : collections et items en base (`pokehub_collections`, `pokehub_collection_items`). `user_id` > 0, slug unique **par utilisateur**. Persistance multi-appareils, partage par URL avec **`share_token`** (réécriture `/collections/{token}` ; voir `collections-routing.php`).
- **Visiteur non connecté (mode actuel du module)** : la création via **REST** (`POST /wp-json/poke-hub/v1/collections`) enregistre **aussi** une ligne en base avec **`user_id = 0`** : `share_token`, **`anonymous_owner_key`** (secret côté serveur + renvoyé une fois), cookie propriétaire **`pokehub_col_owner_{token}`**, et **`anonymous_ip`** (support / rattachement ultérieur, **pas** comme clé primaire d’auth). L’UI front peut encore utiliser **localStorage** pour l’état ou le cache, mais la **source de vérité** serveur pour ces collections anonymes est la base.
- **Comportement mixte** : sans compte, le joueur voit surtout un message du type « stocké sur cet appareil » ; techniquement la collection peut être **sauvegardée sur le site** dès qu’une création REST a réussi (même principe qu’un **lien magique** par jeton).

### Pourquoi l’IP n’est pas la clé d’édition ?

- **Limites** : IP change (mobile, box partagée), plusieurs personnes derrière la même IP — mauvaise identification si l’IP seule décidait des droits.
- **En pratique** : l’**édition** d’une collection anonyme repose sur la **clé propriétaire** (header `X-PokeHub-Owner-Key` et/ou cookie) ; l’IP est une **corrélation** secondaire (ex. bannière « rattacher au compte »). Voir `poke_hub_collections_can_edit_anonymous()` dans `collections-helpers.php`.

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

### Gigamax synthétique (collections vs outil Images)

Pour le **pool collections** (affichage joueur, id synthétiques `2100000000+`), la logique Gigamax synthétique vit dans `includes/functions/pokemon-public-helpers.php` : elle tient compte notamment de **`extra.release.gigantamax`** (date renseignée) et du filtre **sorti en GO** pour le mode concerné (`poke_hub_pokemon_is_released_in_go`, etc.). Ce n’est **pas** le comportement de l’onglet admin **Temporary tools → Images sync** (`tab=images-sync`), qui liste des icônes à préparer **sans** dépendre des dates — uniquement selon les **données** et **flags** en base pour le manifest CSV. Détail : [ADMIN_TEMPORARY_TOOLS.md](./ADMIN_TEMPORARY_TOOLS.md) § *Images sync*.

### Options de composition

Une collection a des **options** (JSON) ; selon la catégorie, seules les options pertinentes sont affichées :

- **Listes « normales »** (Custom, Shiny, Lucky, Perfect 4*, etc.) : on peut afficher ou masquer des éléments en plus des Pokémon de base :
  - Inclure le Pokédex national, les **formes alternatives** (variantes hors « genre »), les Pokémon avec **fonds GO**, etc.
  - **Genre (deux notions distinctes)** :
    - **`include_gender`** : garder les **lignes séparées** quand le jeu a **deux fiches distinctes** liées à l’attribut de forme *gender* en base (ex. Nidoran, formes mâle/femelle de Mistigrix) — filtre SQL sur `form_category = gender`.
    - **`show_gender_symbols`** : afficher **♂ / ♀** à droite du nom **uniquement** sur ces lignes de variante *gender* (sans effet sur les autres).
    - **`include_both_sexes_collector`** (et, avec la case **dimorphisme**, **`include_gender`**) : pour chaque fiche du pool dont le **profil de genre** (`poke_hub_pokemon_get_gender_profile`, via `extra` en base) déclare **à la fois** `male` et `female` comme disponibles dans l’UI, le pool peut être **doublé** en deux entrées (symboles ♂/♀) avec des **`pokemon_id` synthétiques** (plage réservée à partir de `2200000000`, `poke_hub_collections_sex_synthetic_pokemon_id()`). **Images** : un drapeau **`synthetic_sex_use_gender_asset`** sur chaque ligne synthétique indique si le sprite doit utiliser les fichiers **`-male` / `-female`** (vrai **uniquement** quand `has_gender_dimorphism` est vrai sur la fiche de référence) ; sinon les deux tuiles réutilisent **le même fichier** (ex. espèce non dimorphique : deux entrées, une image). **Gigamax** : **jamais** de suffixe genre sur l’URL — toujours le sprite de la forme G-Max (`synthetic_sex_use_gender_asset` à false ; le helper d’images évite aussi le « genre par défaut » sur les formes Gigamax). **Dynamax** : le dimorphisme suit **l’espèce de base** ; le profil genre et la résolution des URLs pour les lignes synthétiques utilisent **`dynamax_base_pokemon_id`** lorsque la ligne est un Dynamax synthétique (`synthetic_dynamax`). Les lignes **Dynamax / Gigamax** du pool sont concernées par le dédoublement comme les autres fiches (voir *Pool : formes*, *Images*). Les espèces **sans les deux sexes** en `available_genders` ne sont pas dupliquées. Les lignes déjà de type variante `fv.category = gender` ne sont pas re-découpées par cette option.
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

Le **pool** = Pokémon (table `pokemon` + formes) filtrés par catégorie et options. Après la requête SQL du pool, **`poke_hub_collections_row_passes_pool_release_filter()`** retire toute ligne sans **date de sortie adaptée au contexte**, en ne lisant que **`extra.release`** de la fiche courante (pas de propagation évolution).  
La date doit être **normalisable** (formats acceptés par `poke_hub_normalize_release_date`) et être **inférieure ou égale à aujourd’hui** (fuseau WordPress via `current_time('Y-m-d')`, helper `poke_hub_release_date_is_past_or_today`).  
Résumé des clés utilisées selon la collection : **`normal`** (listes « génériques » comme Custom, Lucky, Perfect 4\*…), **`shiny`**, **`shadow`**, **`gigantamax`**, **`dynamax`** — et en contexte **`normal`**, le filtre peut aussi accepter une entrée si les options du pool incluent Méga / Dynamax / Gigamax et que la clé correspondante est renseignée **avec une date déjà effective**.

**Squelettes import GM** (`extra.gm_skeleton` : fiches pré-remplies type spawn / genre sans `pokemonSettings` complet au premier passage) : même règle que les fiches complètes — **aucun passe-partout** ; la ligne n’apparaît dans une liste **shiny** / **shadow** / etc. que si **`extra.release.<contexte>`** contient une date valide **déjà atteinte** (comme tout autre Pokémon).

Si aucune ligne ne satisfait le filtre dans le contexte courant, la partie du pool après filtrage est **vide**.

**Formes Forces de la nature et Amovénus** (№ National **641**, **642**, **645**, **905**) : cas particuliers sur le filtre **`switch_battle`** SQL (ibfc GM) — voir § *Pool SQL* ci‑dessous ; la liste des № est surchargeable avec le filtre WordPress **`poke_hub_collections_forces_nature_dual_form_dex_numbers`**.

### Catégories spécifiques (paramètres adaptatifs)

- **Helpers PHP** : `poke_hub_collections_get_specific_categories()` (liste des slugs), `poke_hub_collections_category_is_specific( $category )`.
- **Comportement** : pour ces catégories, le formulaire de création n’affiche pas les cases « Méga / Gigantamax / Dynamax / costumes » (un bloc d’info les remplace) ; en édition, seul un texte explicatif + « Une entrée par espèce » et les options d’affichage sont proposés. Le calcul du pool ignore les options `include_mega`, `include_gigantamax`, `include_dynamax`, `include_costumes`.
- **Rappel** : les clés de **`poke_hub_collections_settings_hidden_control_keys()`** s’appliquent surtout aux catégories **non** spécifiques (ex. `legendary_mythical_ultra`, ou pour **normaliser** les valeurs envoyées à l’API quand le bloc filtre est masqué mais que des champs restent dans le DOM). Voir *Options masquées par catégorie (UI)*.

## Modèle de données

### Table `pokehub_collections`

- `id`, **`user_id`** : identifiant WordPress du propriétaire, ou **`0`** pour une collection créée **sans compte** (ligne toujours en base si créée via l’API).
- `name`, **`slug`** (unique **par** `user_id` pour les comptes ; les anonymes ont souvent un slug préfixé type `anon-…`), **`share_token`** (jeton public dans l’URL), **`anonymous_ip`**, **`anonymous_owner_key`** (secrets anonymes — ne pas exposer en REST publique).
- **`category`** (slug de type de liste), **`options`** (JSON) : `include_national_dex`, `include_gender`, `show_gender_symbols`, `include_both_sexes_collector`, `include_forms`, `include_costumes`, `include_mega`, `include_gigantamax`, `include_dynamax`, `include_backgrounds`, `include_special_attacks`, `one_per_species`, `group_by_generation`, `generations_collapsed`, `display_mode` (tiles | select), `card_background_image_url`, etc.
- **`is_public`** : pour les comptes connectés, collection visible sans être le propriétaire (les collections **`user_id = 0`** ne passent pas en « publique » côté métier actuel).
- `created_at`, `updated_at`.

### Table `pokehub_collection_items`

- `collection_id`, `pokemon_id`, `status` (owned | for_trade | missing).
- Clé primaire `(collection_id, pokemon_id)`.
- Les **`pokemon_id`** sont en principe des id de la table `pokemon` ; en **mode collectionneur mâle/femelle** (`include_both_sexes_collector`), des id **synthétiques** (plage `2200000000+`) peuvent apparaître pour les deux lignes d’une même espèce. L’affichage et l’API REST acceptent ces id comme pour les entrées Gigamax synthétiques (`2100000000+`).

Un **brouillon purement local** (sans appel REST réussi) peut encore être représenté côté client en **localStorage** ; dès qu’une collection est **créée ou chargée** via l’API avec un `id` numérique, les items sont lus/écrits en base (`pokehub_collection_items`) selon les droits.

### Statuts d’une entrée (données ↔ interface)

Chaque Pokémon du pool a un **statut** stocké côté données (`pokehub_collection_items.status`, REST, `localStorage`) et reflété sur la tuile par l’attribut `data-status` :

| Valeur technique | Rôle |
|------------------|------|
| `owned` | Possédé |
| `for_trade` | À l’échange (équivalent du libellé anglais source *For trade*, domaine d’extension `poke-hub`) |
| `missing` | Manquant |

Le bloc **Include in grid** (*chaîne source anglaise* dans les fichiers de traduction du domaine `poke-hub`; ancienne formulation documentée « Show in grid ») ne change pas ces valeurs : il **filtre uniquement l’affichage** des tuiles. En français, l’interface peut traduire ce libellé ; les trois statuts sont **possédé**, **à l’échange**, **manquant** côté texte métier ; le code et l’API gardent `owned`, `for_trade`, `missing`.

## Ordre des lignes dans la grille et variantes

Quand plusieurs entrées du pool concernent une même famille (national dex ou même espèce sous plusieurs formes), l’ordre d’affichage est défini dans **`poke_hub_collections_sort_pool_display()`** (`modules/collections/functions/collections-helpers.php`). La catégorie métier utilisée pour le rang est **`poke_hub_collections_row_display_category_for_sort()`**, puis **`poke_hub_collections_display_variant_sort_rank()`** (filtre WordPress **`poke_hub_collections_display_variant_sort_rank`**). Ce rang **collections** diffère du tri générique des sélecteurs (`pokehub_pokemon_select_category_rank` dans **`pokemon-public-helpers.php`**) : même logique « catégorie dérivée », mais avec **costumes avant formes régionales** pour la même lignée Pokédex (ex. Raichu de base puis costumés, puis Raichu d’Alola).

Ordre de tri (résumé) : **génération** ascendante ; **n° de Pokédex** ; **cas spéciaux Zarbi / Unown** (même espèce `#201`) : ordre forcé **A → Z**, puis **`!`**, puis **`?`** (les slugs de fiche attendus après import sont du type **`unown-b`** / `unown-exclamation-point` — **`poke_hub_pokemon_compose_pokemon_row_slug()`** évite le double préfixe `unown-unown-*`, voir **[GAME_MASTER_IMPORT.md](./pokemon/GAME_MASTER_IMPORT.md)** § *Slug ligne Pokémon*) ; **rang collections** (`poke_hub_collections_display_variant_sort_rank`) : normale puis **costumes / clones**, puis **`100 + 20 ×`** le rang **`pokehub_pokemon_select_category_rank()`** pour le reste (donc même **succession relative** régional → méga → gigamax → dynamax que les sélecteurs, avec les catégories « autres » comme `switch_form` / `visual` / `gender` ramenées au groupe **régional/special** côté collections) ; pour les lignes avec **fonds GO** synthétiques, regroupement avec la ligne source ; puis nom / libellé de forme / id pour un ordre stable.

## Pool SQL : lignes « family », tous les Pokémon vs une entrée par espèce

Le pool est construit dans **`poke_hub_collections_get_pool()`**. Comportements utiles pour la maintenance et le support :

- **`one_per_species` désactivé (« toutes les formes »)**  
  - Les slugs se terminant par **`-family`** sont **exclusivement réservés** au mode « une entrée par espèce » (regroupement). En **« toutes les formes », une ligne `*-family` ne doit pas apparaître** ; les entrées utilisables sont la **forme normale** (`slug` sans cette terminaison) et les variantes (méga, costume, fusion, etc.).  
  - D’autres filtres (ex. lignes `-normal` redondantes) ne s’appliquent que lorsqu’une **autre forme réelle** existe pour le même n° Pokédex (évite une base générique alors que des suffixes `-…` sont présents).
  - **Motifs `visual` prolongeant un slug commun** : pour éviter une double carte « Zarbi motif / base », le WHERE **retire** la ligne sans suffixe lorsqu’il existe des lignes **`fv.category = 'visual'`** du même № dont le **`slug`** prolonge celui-ci (`CASTFORM`-like en apparence). **Morphéo / Castform (n° 351)** : la forme **`castform`** est une carte à part entière — exceptions par **`poke_hub_collections_visual_variant_base_stub_keep_dex_numbers()`** (`collections-helpers.php`) ; filtres WordPress **`poke_hub_collections_visual_variant_base_stub_keep_dex_numbers`** (№ Dex). Voir aussi [docs/collections/CHANGELOG.md](./collections/CHANGELOG.md) § *2026-05-05*.

- **`one_per_species` activé**  
  - Logique de **famille par espèce** (slugs `-family`, `is_default`, formes incluses selon options : régional, méga, costume, Dynamax synthétique, etc.). Les variantes **`fusion` / `special`** (ex. plusieurs formes « mécaniquement » distinctes) restent des entrées séparées quand les options les autorisent ; la ligne famille seule peut être masquée si ces formes explicites existent déjà (évite triple affichage type Giratina).

- **Formes catalogue** (`pokemon_form_variants.category`) : en mode **une entrée par espèce**, les variantes **`switch_form`** hors slug **`…-family`** sont en principe retirées du pool (regroupement type Shaymin / Keldeo) ; les **`switch_battle`** hors **`…-family`** aussi, **sauf** les cas explicites du WHERE (**drives Genesect**, **Kyurem** / **Necrozma**, et **Tornadus / Fulguris / Démétéros / Amovénus**, № **641 / 642 / 645 / 905**, pour afficher **deux** cartes **Incarnateur / Totémique** plutôt que seulement **`…-family`**). Pour ces quatre espèces, une variante encore en **`normal`** ou **`default`** avec **slug suffixé** (tiret) est aussi admise par la disjonction SQL dédiée. Filtre PHP sur dates de sortie pour les formes hors famille : **`poke_hub_collections_row_is_forces_nature_dual_variant_row()`** et filtres `poke_hub_collections_forces_dual_*` (`collections-helpers.php`).

- **Synthèses Dynamax / Gigamax** (collections **non** catégories `dynamax` / `gigantamax` dédiées) : si la fiche a une sortie prévue mais pas de ligne variante en table, une tuile peut être dérivée (ids synthétiques `1000000000+` / `2100000000+`). **Il ne doit pas exister** de ligne combinée impossible type « Dynamax **et** Gigantamax » pour la même ligne de pool ; la génération évite aussi qu’une synthèse se réapplique en chaîne depuis une ligne déjà Dynamax ou déjà Gigamax.

- **Cohérence données** : garde SQL optionnel contre un slug improbable **`dynamax` + `gigantamax`** sur une même ligne (données importées corrompues).

Référence code : **`modules/collections/functions/collections-helpers.php`** (filtres `WHERE`, `poke_hub_collections_maybe_mark_*_synthetic_base_row`, `poke_hub_collections_apply_*_synthetic_*`, `poke_hub_collections_sort_pool_display`).

## Administration WordPress (liste des collections enregistrées)

Écran **Poké HUB → Collections** (`page=poke-hub-collections`) : tableau des lignes **`pokehub_collections`** avec propriétaire (compte WordPress ou **anonyme** + IP / préfixe de clé anonyme), type de liste, progression (ratio identique au front), visibilité, jeton de partage, lien **Voir** construit comme sur le front. Filtres (tous / comptes / anonymes), recherche par nom, suppression admin (unitaire ou en masse).

- **Helpers** : `poke_hub_collections_public_view_url()`, `poke_hub_collections_compute_progress_totals()`, `poke_hub_collections_admin_force_delete()` — `collections-helpers.php`.
- **UI** : `modules/collections/admin/collections-admin.php` ; chargé depuis `collections.php` si `is_admin()`.
- Le slug **`poke-hub-collections`** doit figurer dans **`poke_hub_admin_pages()`** (`poke-hub.php`) pour le parent de menu Poké HUB.

Détails et contexte Morphéo : [docs/collections/CHANGELOG.md](./collections/CHANGELOG.md).

**Distinction** : le module **collections** garde une logique métier propre aux slugs `-family` ci-dessus. **Hors collections**, les sélecteurs (blocs, événements, heures vedette, REST, etc.) et les affichages de lignées excluent ces placeholders ; l’import PASS 3 évite d’attacher les évolutions aux seules lignes famille quand une autre fiche existe — synthèse **[pokemon/GAME_MASTER_IMPORT.md](./pokemon/GAME_MASTER_IMPORT.md)** (§ *PASS 3*, *Hors import*).

### Images des tuiles (collections)

Pour lier sprites et pool, **`poke_hub_collections_pool_row_to_pokemon_for_image_target()`** prépare l’objet passé à **`poke_hub_pokemon_get_image_sources()`** :

- lignes **Gigamax** (réelles ou synthétiques) : pas de jeu sur le **genre par défaut** résolu automatiquement (évite les URLs `*-male` non souhaitées sur le sprite G-Max seul fichier) ;
- lignes **synthétiques collectionneur mâle/femelle** : voir **`synthetic_sex_use_gender_asset`** et la section **Options de composition** ci-dessus ; argument optionnel **`skip_gender_resolution`** côté helper d’images : voir **`docs/POKEMON_IMAGES.md`** (**`poke_hub_pokemon_get_image_sources`**).

### POGO recherche

Le dédoublement mâle/femelle et les variantes (chromatique, régional, etc.) sont gérés par **regroupement** puis **dédoublonnage** côté JS (voir § *Phrases GO : données serveur et langue*). Une même espèce peut apparaître dans **plusieurs groupes** si le pool contient des lignes catégorisées différemment ; des passes retirent les doublons **parent sans sexe** vs **ligne sexe** et fusionnent certains cas mâle+femelle.

## Expérience utilisateur

1. **Création** : drawer (panneau latéral) avec nom, type (catégorie), options selon le type (voir *Catégories spécifiques*), mode d’affichage (tuiles ou liste + sélecteur). Si non connecté : message de stockage local.
2. **Édition** : grille de tuiles (clic = cycle **manquant → possédé → à l’échange → manquant**) **ou** liste + Select2 pour ajouter les Pokémon manquants (mode configurable). **Légende** : possédé (vert), à l’échange (orange), manquant (gris). Bloc **Include in grid** : une case cochée par statut **affiché** ; la courte phrase « Click a tile to cycle status. » est rendue dans le **même** bloc sous la légende ; tout décocher vide la grille et affiche un rappel ; avec **regroupement par génération**, les blocs sans aucune tuile visible sont masqués (voir *Statuts d’une entrée*). Les Pokémon **sans génération résolue** en base (taxonomie `generations`) sont regroupés **en dernier** dans la liste / les sections, pas avant la génération 1 (`poke_hub_collections_sort_pool_display`, `poke_hub_collections_group_pool_by_generation`).
3. **Partage** : lien (slug ou id), export image (canvas ou serveur).
4. **Mise à jour** : le joueur revient sur la collection et met à jour les statuts ou les paramètres (drawer paramètres pour les options).
5. **Phrases de recherche Pokémon GO** : bloc `<details>` rendu par `poke_hub_collections_output_pogo_search_block()` (`collections-shortcode.php`). Le libellé du `<summary>` est une chaîne traduisible (domaine `poke-hub` ; source actuelle du type *Pokémon GO: your hunt strings, ready to paste*). Génération côté client dans `collections-front.js`. Le pool et les statuts sont lus sur `.pokehub-collection-tiles` (`data-pool`, `data-items` ; côté serveur les items sont **résolus** avec `poke_hub_collections_resolved_items_map()` pour hériter du statut de la fiche de base sur les lignes à id synthétique mâle/femelle). Barre d’outils : deux `<select>` (statut de liste, mode noms FR / EN / n° Pokédex) sur **une même ligne** (gauche / droite), avec les classes `pokehub-collection-pogo-search-toolbar` et `pokehub-collection-pogo-search-toolbar-field--status` / `--token`. Les groupes affichés (titres côté i18n `pogoGroup*` dans `collections.php`) reflètent le **regroupement** du pool : Classic, chromatique (+ variantes sexe si le pool les distingue), formes régionales, obscur / purifié, méga, gigamax, dynamax, événement (costume), mâle/femelle « globaux », fonds (générique, **lieu**, **spécial**, + variantes **fond + dynamax** / **fond + gigamax**), selon les lignes présentes. Chaque groupe non vide : titre `h4.pokehub-pogo-search-group-title`, grille **deux colonnes** (`.pokehub-pogo-search-groups`), champ + bouton copier. **Dédoublonnage** : à l’intérieur d’un groupe, un même jeton n’apparaît qu’une fois ; après coup, le JS retire les jetons déjà couverts par une ligne **sexe + variante** du groupe parent, fusionne mâle+femelle quand le jeu n’exige pas le filtre sexe, etc. (détail § *Phrases GO : données serveur et langue*). **Styles** : uniquement dans le thème, `css/poke-hub/parts/13-collections-front.css` (section *Recherche in-game Pokémon GO*), variables `--me5rine-lab-*` ; les correctifs de cascade (liste collections, `<details>` avancé, tuiles filtrées, bannière reset `[hidden]`) sont dans `css/poke-hub/poke-hub-late-overrides.css` du thème — plus aucun fichier de cascade côté plugin.
6. **Vue détail : structure DOM, barre d’outils flux / fixe** (scroll long, lorsque `$total > 0` dans `collections-shortcode.php`) :
   - **Barre fixe (hors flux)** : `.pokehub-collection-fixed-toolbar` (`[data-collection-fixed-toolbar]`) — masquée jusqu’à ce que le scroll fasse « décoller » la pile d’outils du site. Elle duplique le hero de page, contient la rangée **`[data-fixed-tiles-host]`** (tuiles générées en JS : filtres, GO, sauts génération, sélecteurs selon les slots présents), puis **`[data-fixed-expand]`** > **`[data-fixed-expand-inner]`** où le panneau choisi est injecté. Le JS **reparente ce bloc sous `document.body`** pour éviter les bugs de `position:fixed` lorsqu’un ancêtre du wrap applique `transform` (ex. colonnes Elementor).
   - **Flux** : `.pokehub-collection-toolbar-stack` — **`.pokehub-collection-toolbar-header`** (titre + progression), puis **`.pokehub-collection-toolbar-tools`** avec **`[data-flow-tiles-host]`** (même jeu de tuiles que la barre fixe, version flux) et les **`[data-collection-toolbar-slot]`**. Chaque panneau porte **`data-collection-fixed-tile`** (`filters`, `pogo`, `generations` si plusieurs groupes, `selectors` selon le mode d’affichage). Le bloc **Jump to generation** (`.pokehub-collection-generation-jump`) est **dans** le slot `generations`, pas séparé des filtres / GO.
   - **Expand unique** : un clic sur une tuile ne doit ouvrir **que** le panneau correspondant ; le DOM du panneau est **déplacé** depuis son slot vers l’inner d’expand actif (fixe, flux ou tiroir). Le JS résout le nœud dans le wrap **ou** dans les conteneurs d’expand (`sectionBodyFor` dans `collections-front.js`) afin de rester correct après reparentage de la barre fixe.
   - **Menu tiroir (petits écrans)** : **`[data-toolbar-menu-drawer]`** (`.pokehub-collections-drawer--toolbar`) avec **`[data-toolbar-menu-body]`** sert de zone d’expand à la place du déplié inline selon le breakpoint (logique dans **`initCollectionFixedToolbar()`**). **Emplacement HTML** : le nœud est rendu **après** la fermeture de **`.pokehub-collection-toolbar-stack`** et **avant** **`.pokehub-collection-tiles`**, toujours sous **`.pokehub-collection-view-wrap`** — il ne doit **pas** rester **à l’intérieur** de la pile sticky : celle-ci applique **`isolation: isolate`** et un **`z-index`** bas (`--pokehub-collection-toolbar-stack-z`, ex. 9 sous le header du site) ; un `position: fixed` du drawer y serait **piégé** dans ce contexte d’empilement et passerait derrière le header ou d’autres couches. **Comportement** : à l’ouverture du menu (`is-open`), le JS pose **`document.body.style.overflow = 'hidden'`** pour empêcher le défilement de la page derrière le tiroir ; à la fermeture, la propriété est réinitialisée **uniquement** si le drawer était ouvert (évite d’écraser un autre verrouillage du `body` dans les cas simples).
   - **État wrap** : classe **`pokehub-collection--fixed-toolbar`** lorsque la barre fixe est visible ; variable CSS **`--pokehub-collection-fixed-toolbar-height`** (et attribut **`data-pokehub-last-fixed-toolbar-h`**) pour la hauteur de la barre — utile au calage du scroll et du saut par ancre.
   - **Décoration des tuiles (barre fixe)** : **`data-toolbar-decoration-url`** sur les panneaux ; défauts côté bucket / options : `filters.png`, `search-strings.png`, `generations.png` (voir réglages Sources et **`poke_hub_collections_get_toolbar_decoration_image_url()`**). L’image sert surtout de **fond des boutons** de la barre fixe ; le flux peut rester sans colonne média latérale si vous ne l’affichez pas dans le HTML du panneau.
   - **Jump génération / région** : en **`max-width: 1024px`** (thème), ruban horizontal masqué + flèches et **`initCollectionGenerationJumpScroll`** ; au-delà, **grille** multi-colonnes — y compris pour le panneau ouvert sous la barre fixe sur grand écran (pas de ruban horizontal réservé au seul mode fixe).
   - **Feuilles de style** : **`css/poke-hub/parts/13-collections-front.css`** (thème) ; offsets **`--pokehub-elementor-header-offset`**, **`--pokehub-adminbar-offset`** — **`modules/collections/COLLECTIONS_THEME_CSS.md`**.
   - **Clic sur un lien de saut** : `initCollectionGenerationJump()` applique la compensation **Elementor + admin bar + hauteur barre d’outils** ; le lien actif est synchronisé au scroll (**`requestAnimationFrame`**).

### Phrases GO : données serveur et langue

Le pool renvoyé par `poke_hub_collections_get_pool()` est enrichi dans `poke_hub_collections_pool_rows_add_pogo_tokens()` (`modules/collections/functions/collections-helpers.php`) avant sérialisation JSON (`data-pool`, REST). Les **tuiles « fond GO »** supplémentaires sont des lignes synthétiques (`synthetic_go_background`) créées dans `poke_hub_collections_apply_synthetic_go_background_pool()` ; le **type de fond** lu en base (`pokemon_backgrounds.background_type`) est normalisé sur la ligne en `synthetic_go_background_background_type` (`''` = fond générique, `location` = fond de lieu, `special` = fond spécial) — voir `poke_hub_collections_get_all_go_backgrounds_for_pokemon_ids()` et `poke_hub_collections_normalize_synthetic_go_background_type()`.

| Champ | Rôle |
|-------|------|
| `pogo_token_fr` / `pogo_token_en` | Jeton pour la partie **espèce** après le `&` (comportement type jeu : recherche par **nom d’espèce**, pas par libellé de forme fusion / Necrozma, etc.) : dès qu’un **n° National Dex** est connu et qu’une fiche **de base** existe pour ce dex (`poke_hub_collections_pogo_base_names_by_dex`, requête sur `pokemon` en privilégiant `is_default` / la forme par défaut), le jeton est dérivé de ce **nom d’espèce** pour **toutes** les lignes du pool (avec ou sans `form_variant_id`). Repli si aucune fiche base : noms de la ligne ; puis slug (`poke_hub_collections_pogo_token_from_slug`). Les suffixes régionaux / méga / gigamax résiduels sont encore affinés côté JS (`pogoStripRegionalFormTokenNoise`, `pogoSearchFormTokenNamePart`). |
| `pogo_group_prefix_fr` / `pogo_group_prefix_en` | Préfixe « variante » issu de **`pokemon_form_variants`** (`extra.names.fr` / `extra.names.en`, sinon `label`), puis collé en jeton de recherche (accents retirés, alphanum). Sert surtout aux **formes régionales** et variantes dont le préfixe dépend des données (pas de libellé régional en dur côté PHP pour Hisui / Alola / etc.). |

**Préfixes « jeu » (FR vs EN)** — assemblés dans `pogoGroupPrefix()` (`collections-front.js`) selon le sélecteur du bloc (**noms FR** / **noms EN** ; le mode **#** utilise les mêmes préfixes **anglais** que l’EN). Les **formes régionales** passent toujours par `pogo_group_prefix_*` sur une **ligne exemple** du groupe (première ligne après tri). Les **composés** « variante + sexe » concatènent le préfixe de la variante + `mâle&` / `femelle&` (FR) ou `male&` / `female&` (EN).

| Sémantique | Préfixe FR | Préfixe EN |
|------------|------------|------------|
| Chromatique | `chromatique&` | `shiny&` |
| Obscur | `obscur&` | `shadow&` |
| Purifié | `purifié&` | `purified&` |
| Événement (costume) | `événement&` | `event&` |
| Méga | `méga&` | `mega&` |
| Gigamax / Gigantamax | `gigamax&` | `gigantamax&` |
| Dynamax | `dynamax&` | `dynamax&` |
| Mâle / femelle (lignes globales) | `mâle&` / `femelle&` | `male&` / `female&` |
| Fond (générique) | `fond&` | `background&` |
| Fond de lieu | `fonddelieu&` | `locationbackground&` |
| Fond spécial | `fonspécial&` | `specialbackground&` |
| Fond + Dynamax | `fond&dynamax&` | `background&dynamax&` |
| Fond + Gigamax | `fond&gigamax&` | `background&gigantamax&` |

**Préfixe de contexte collection** — préfixé **devant** chaque ligne générée (`pogoWrapCollectionScopePrefix()`), en plus des préfixes ci-dessus :

- catégorie de collection **`lucky`** (attribut `data-collection-category` sur le wrap) → `chanceux&` (FR) ou `lucky&` (EN / #) ;
- option **« Inclure seulement : bébés »** (`pool_show_only === 'baby'` dans `data-edit-options`) → `oeufseulement&` (FR) ou `eggsonly&` (EN / #).

**Ordre d’affichage des groupes** — constante `POGO_GROUP_ORDER` dans `collections-front.js` (Classic puis chromatique, régions, obscur/purifié, méga, gigamax, dynamax, événement, mâle/femelle globaux, puis fonds générique / lieu / spécial / +Dynamax / +Gigamax). Le regroupement d’une ligne est calculé par `pogoDetectGroup()` (catégorie de forme, lignes sexe synthétiques, fonds synthétiques + type).

**Phrases copiables** — `pogoBuildPhrasesForWrap` : pour chaque groupe dans `POGO_GROUP_ORDER`, liste des jetons d’espèce (ou n° dex en mode #), dédoublonnage **dans** le groupe, puis passes `pogoDedupeTokensForSexCompoundVariants`, `pogoMergeSexCompoundDupesIntoParent`, `pogoMergeMaleFemaleDupesIntoBase`, `pogoRemoveSexSpecificTokensFromParent` (retire du parent « sans sexe » les espèces déjà listées en **mâle/femelle** ou **variante+sexe** pour le même jeton).

**Limite** : si un même groupe regroupe plusieurs **variants** distincts, une seule ligne sert d’exemple pour `pogo_group_prefix_*` ; en pratique les groupes sont homogènes par type de forme.

## Dépendances

- Module **Pokémon** obligatoire (tables pokemon, pokemon_form_variants, etc. pour construire le pool).

## Fichiers principaux

- `modules/collections/collections.php` — bootstrap du module.
- `modules/collections/functions/collections-helpers.php` — pool, CRUD collections/items, helpers progression / URL publique / suppression admin.
- `modules/collections/admin/collections-admin.php` — liste d’administration des collections (`manage_options`).
- `modules/collections/public/collections-shortcode.php` — shortcodes `[poke_hub_collections]` et `[poke_hub_collection_view]`.
- `modules/collections/public/collections-rest.php` — API REST (pool, CRUD, items).
- Styles front : **dans le thème** Me5rine, `css/poke-hub/parts/13-collections-front.css` et `14-collections-theme.css` (enqueued individuellement par le `functions.php` du thème ; `poke-hub-front.css` ne sert que d’index `@import` et d’`add_editor_style` — voir [THEME_FRONT_CSS.md](./THEME_FRONT_CSS.md)). En mode plugin pur (`poke_hub_load_default_plugin_front_css` = true), le fichier `assets/css/poke-hub-collections-front.css` s’enfile s’il est présent. `modules/collections/assets/js/collections-front.js` — front.
- `modules/collections/COLLECTIONS_THEME_CSS.md` — référence classes / variables (légende, bloc **Include in grid**, carte liste, **barre d’outils fixe**, drawer menu). Chaînes traduisibles : **docs/TRANSLATION.md**. Conventions doc : **docs/REDACTION.md**.

## Mise en place d’une page

Sur la page dédiée aux collections, placer les deux shortcodes :

```
[poke_hub_collections]
[poke_hub_collection_view]
```

Quand l’URL contient `?collection=slug&view=1` (ou `?id=123`), la vue de la collection s’affiche sous la liste ; sinon seule la liste et le bouton « Créer une collection » s’affichent.

---

*Index de la documentation : [README du dossier docs](README.md) · [Charte rédactionnelle](REDACTION.md)*
