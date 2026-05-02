# Import Game Master (référence métier et technique)

Ce document décrit le comportement **attendu** de l’import Pokémon GO (**Game Master** JSON → tables Poké HUB). Code principal :  
`modules/pokemon/functions/pokemon-import-game-master.php` et  
`modules/pokemon/includes/pokemon-import-game-master-helpers.php`.

Voir aussi **[DATA_SAFETY.md](./DATA_SAFETY.md)** (non-écrasement, fusion `extra`, traitement par lots).

---

## Déclenchement

Admin : **Poké HUB → Temporary tools → Game Master** (module **pokemon** actif). Détails : [ADMIN_TEMPORARY_TOOLS.md](../ADMIN_TEMPORARY_TOOLS.md).

Import **non destructif** globalement : insert ou update selon présence des lignes (slug Pokémon, slug attaque).

---

## Parcours d’import (vue d’ensemble)

1. **Pré-scan** du JSON  
   Genre, index G‑Max / moves `VN_BM_*`, mapping sourdough, agrégats de formes par espèce, index des formes costume (`formSettings[].isCostume`), liste des espèces devant avoir une famille explicite, etc.
2. **PASS 1** : attaques (PvE + PvP)
3. **PASS 2** : une entrée `pokemonSettings` par template → mise à jour fiche Pokémon (stats, types, `extra`, variante globale…)
4. **PASS 2C** : liaison attaques G‑Max où applicable
5. **PASS 3** : évolutions (`pokemon_evolutions`)
6. PASS 4 facultatif types Bulbapedia (option d’import)

---

## PASS 3 : table `pokemon_evolutions` et métadonnées `extra`

### Sync et résolution hors ligne `*-family`

L’index **`pokemon_index`** enrichi lors du PASS 2 (par entrée : `pokemon_id`, `form_variant_id`, `slug`, **`is_family_placeholder`**) alimente le PASS 3. Avant chaque **`poke_hub_pokemon_sync_pokemon_evolutions()`**, **`poke_hub_pokemon_gm_resolve_index_row_for_evolution_links()`** (`pokemon-import-game-master-helpers.php`) remappe toute ligne dont le slug est un placeholder **`{espece}-family`** vers une **fiche jouable** du même proto (priorité au slug canon `poke_hub_pokemon_gm_id_to_slug()`, pénalisation des slugs **`mega-*` / `primal-*`** pour le repli automatique).

- **Base** et **cible** d’une arête ne sont plus en pratique les IDs de la ligne `*-family` lorsqu’une autre ligne existe pour l’espèce.
- Si **aucune** ligne non famille n’est trouvée dans le même bucket proto, la résolution peut renvoyer **null** : la branche est ignorée (cible **`0`**), ce qui évite de câbler des évolutions sur le seul placeholder.

Cela évite les **doublons de lignées** et les arêtes incohérentes par rapport aux fiches affichées côté blocs ou sélecteurs (voir aussi **§ Lignes `*-family` — hors import** plus bas).

### Métadonnées `extra` sur chaque ligne d’évolution

Lors du **sync évolutions au fil de l’import Game Master**, la fonction **`poke_hub_pokemon_sync_pokemon_evolutions(..., $options)`** peut enrichir ou mettre à jour le JSON **`extra`** de chaque ligne concernée :

- **`evolution_source`** : chaîne **`game_master`** pour marquer une arête issue du PASS 3.
- **`base_id_proto`** : proto GM du Pokémon **base**, passé depuis l’import via l’option **`base_id_proto`** (alignement avec la fiche source du GM).
- **`target_id_proto`** : proto GM de la **cible** d’évolution.
- **`target_form_proto`** : proto de forme cible lorsqu’il est pertinent pour la discrimination des variantes.

Le code part de **`$row['extra']`** déjà présent puis pose ces clés ; les autres clés métier dans `extra` (hors cet overlay) peuvent ainsi être préservées. Ces marqueurs permettent au front (ex. bloc **New Pokémon – Evolution Lines**) de **rejeter les lignes incohérentes** après un désalignement d’IDs (truncate partiel de `pokemon`, ré-import, etc.) en recoupant protos stockés avec `extra.pokemon_id_proto` sur les fiches **`pokemon`**.

Référence : `poke_hub_pokemon_sync_pokemon_evolutions` dans  
`modules/pokemon/admin/sections/pokemon.php`, appel depuis `modules/pokemon/functions/pokemon-import-game-master.php` (PASS 3).

---

## slug Pokémon et champ `is_default`

- **Espèce avec ligne « famille » dédiée** (placeholder `*{base}-family` quand les règles ci‑dessous la créent) : la forme vide côté GM correspond à cette ligne famille ; une forme `*_NORMAL` explicite est une autre fiche avec slug de base canonique lorsque configuré ainsi.
- **Sinon** : forme vide côté GM → souvent fiche « par défaut » (`is_default = 1` pour le slug canonique `{base}`, sans suffixe sauf régles forme explicite).
- Les variantes (suffixes au slug après la forme normalisée) ont en général `is_default = 0`.

Référence code : fonction `poke_hub_pokemon_import_from_pokemon_settings()`.

---

## Normalisation du proto → `form_slug` (`poke_hub_pokemon_normalize_form_proto`)

- Forme vide / `UNSET` → aucun suffixe (forme de base locale à l’entrée GM).
- On retire typiquement le préfixe `ESPÈCE_` dans le proto (ex. `DARMANITAN_GALARIAN_STANDARD` → partie utile après `DARMANITAN_`).
- Les valeurs **`normal`** / **`standard`** *seules* après normalisation représentent la forme canonique équivalente « base » (pas de suffixe slug).
- **Important** : un proto du type **`GALARIAN_STANDARD`** **ne doit pas** être confondu avec un simple **`STANDARD`** (évité la fusion erronée en forme vide — cas **Darmanitan forme standard de Galar**).

Ensuite underscores → tirets dans le suffixe slug.

Ce slug **granulaire** (sortie de cette normalisation) sert tel quel au **suffixe du slug Pokémon** (`{espèce}-{form_slug}`) et au champ **`extra.form_slug`** sur la fiche. Il peut ensuite être **redirigé** vers un autre slug pour **uniquement** la ligne `pokemon_form_variants` (voir § *Variantes globales* ci‑dessous) sans fusionner deux costumes en une seule ligne `pokemon`.

---

## Lignes `*-family` (placeholder de regroupement)

But : une entrée permettant de représenter une **famille formelle** lorsque plusieurs formes métier sont détectées pour l’espèce, **sans** dupliquer le costume pur ou la copie d’événement comme « famille à part » suivant les filtres configurés dans le pré-scan.

Décision portée par le tableau interne **`species_with_explicit_normal_form`** (et la logique associée sur `formSettings` + agrégation `pokemonSettings`) ; la ligne importée avec `form` vide prend le slug **`{slug-espece}-family`**.

**Comportement d’écriture** : pour une ligne famille donnée, le code fait un **`SELECT`** sur `pokehub_*pokemon.slug` ; si existe → **`UPDATE`**, sinon **`INSERT`**. Il n’est **pas garanti au niveau MySQL** l’absence de doublons de slug : la table Pokémon n’a pas de contrainte **UNIQUE** sur `slug` (index seulement). En fonctionnement nominal d’un import unique, un seul slug famille par espèce.

### Hors import — où le slug `*-family` est ignoré métier

Hors module **collections** (qui conserve sa propre logique de pool avec les slugs `*-family` / regroupements), le reste du plugin traite ces lignes comme des **placeholders métier**, pas comme des Pokémon « éditables » dans les flux habituels :

- **Helpers SQL / PHP** : `pokehub_pokemon_slug_is_family_placeholder()`, `pokehub_pokemon_sql_exclude_family_placeholder_slug_expr()` dans **`includes/functions/pokemon-public-helpers.php`**.
- **Sélecteurs / API** : `pokehub_get_pokemon_for_select`, `pokehub_get_pokemon_for_select_filtered`, `pokehub_get_mega_pokemon_for_select`, `pokehub_get_base_pokemon_for_select` ; fichier **`modules/pokemon/includes/pokemon-helpers.php`** (`pokehub_get_all_pokemon_for_select`) ; jeux **`poke_hub_games_get_all_pokemon()`**.
- **Lignées d’évolution affichées** : `pokehub_get_pokemon_evolutions_in` / `pokehub_get_pokemon_evolutions_out` (**`modules/blocks/blocks/new-pokemon-evolutions/render.php`**) — les arêtes dont la base ou la cible jointe porte un slug `*-family` sont exclues de la jointure utilisée pour l’affichage (évite les doublons avec la vraie forme).
- **Agrégations « famille » hors collections** : ex. liste d’IDs pour attaques spéciales / bloc Community Day après parcours des évolutions (`pokehub_get_pokemon_evolution_family_ids`, collecte CD) — filtrés pour ne pas inclure les lignes `*-family` ; même principe pour les **Mega** annexes du rendu Community Day si applicable.
- **Candidats Gigamax synthétiques** (liste dérivée des fiches en base ayant une date Gigamax dans `extra`) : les lignes `*-family` ne servent pas de base synthétique.

**Grille liste Pokémon en admin** (liste des lignes SQL) : **non filtrée** — tu vois encore les placeholders pour maintenance manuelle ou correction métier.

**Contenu / blocs existants** pouvant encore référencer **un ancien `pokemon.id`** correspondant uniquement au placeholder : migration manuelle ou re-sélection du Pokémon dans les metabox après import ; aucune réécriture automatique de tout le contenu WordPress par le plugin.

---

## Gigantamax : `extra.has_gmax_form`

- L’existence potentielle Gigamax pour l’espèce est détectée via les entrées GM (famille mappings G‑Max / espèces).
- **`extra['has_gmax_form']`** ne doit refléter un **oui** fictif métier que sur la **fiche par défaut** de l’espèce (`is_default = 1` pour cette entrée là).
- Après fusion **`poke_hub_pokemon_gm_deep_merge_extra()`**, la valeur est **ré-alignée** sur cette règle pour éviter de conserver un ancien `true` présent dans l’historique pour une variante costume / copie / forme dérivée.

---

## Variantes globales (`pokemon_form_variants`)

### Upsert et type de forme

À partir du **slug granulaire** issu du proto (voir § ci‑dessus), l’import calcule éventuellement un **slug de registre** (alias canonique) utilisé pour **retrouver ou créer** la ligne dans `pokemon_form_variants`, puis rattache **`form_variant_id`** et **`extra.variant_form_slug`** à ce registre.

Si une ligne existe déjà pour ce slug de registre, l’outil fait un **upsert** (mise à jour y compris de la **`category`**) avec la valeur devinée par **`poke_hub_pokemon_guess_form_type_from_gm()`** (`costume`, `clone`, `visual`, etc.), afin de corriger après coup les classements anciens hors admin.

Priorités métier Zarbi / Vivaldaim / Haydaim (visuel saison non « costume » erroné) sont appliquées dans **`poke_hub_pokemon_guess_form_type_from_gm()`** — voir commentaires dans le fichier.

**Forces « nature » et Amovénus** (protos GM **TORNADUS**, **THUNDURUS**, **LANDORUS**, **ENAMORUS** avec forme **INCARNATE** ou **THERIAN**) : dans **`poke_hub_pokemon_guess_form_type_from_gm()`**, ce cas est résolu en **`special`** **avant** la branche **`ibfc`** (changement de forme en combat), qui attribuerait sinon **`switch_battle`** car le GM rattache ces deux protos aux entrées *default/alternate*. C’est cohérent avec le pool **Collections** en mode une entrée par espèce (*exceptions `switch_battle` pour ces № National*, voir **docs/COLLECTIONS_MODULE.md**).

**Fiches squelettes** : une passe GM peut créer ou compléter une ligne sans `pokemonSettings` complet (spawn, réglages de genre, etc.) ; l’import pose alors **`extra.gm_skeleton`** jusqu’à ce qu’une fiche complète ne soit plus considérée comme squelette. Ce drapeau **ne dispense pas** du filtre « sorti en GO » des collections : les dates attendues restent dans **`extra.release`** par contexte (voir **docs/COLLECTIONS_MODULE.md**, *Pool et date de sortie*).

Référence code : **`poke_hub_pokemon_upsert_form_variant()`** dans `modules/pokemon/includes/pokemon-helpers.php` ; résolution registre depuis le granulaire : **`poke_hub_resolve_gm_variant_registry_slug()`** (même fichier) ; **`poke_hub_pokemon_import_from_pokemon_settings()`** dans `modules/pokemon/functions/pokemon-import-game-master.php`.

### Alias « granular → registre » (fusion de variantes sans fusionner les fiches Pokémon)

Pour regrouper **plusieurs** slugs GM sur **une seule** ligne **`pokemon_form_variants`** (ex. plusieurs costumes d’un même événement), sans que les URLs / slugs **`pokemon`** se collisionnent :

- **Réglages** : sous **Temporary tools → Game Master**, champ **« Variant registry slug aliases »** ; une correspondance par ligne ; syntaxes acceptées : `slug-issue-du-gm=>slug-partage-en-registre` ou bien `slug-issue-du-gm slug-partage-en-registre` ; lignes vides ou commençant par `#` ignorées ; persistance dans l’option **`poke_hub_gm_variant_registry_slug_aliases_lines`**.
- **Comportement** : le slug **granulaire** reste utilisé pour le **slug Pokémon** et **`extra.form_slug`** ; le **slug partagé** sert uniquement au **registre** des variantes (`form_slug` de la table + **`extra.variant_form_slug`** au moment de l’import).

Les **méga / primo** créés depuis **`tempEvoOverrides`** ne passent pas par ce mécanisme d’alias (leur branche utilise des slugs propres aux temp evo).

### Filtres WordPress

- **`poke_hub_gm_variant_registry_slug_aliases_map`** : enrichit ou surcharge la carte lue puis parsée depuis l’option (tableau **granulaire sanitizé → canonique**).
- **`poke_hub_gm_variant_registry_slug`** : arguments typiques **`$resolved`**, **`$granular_slug`**, **`$pokemon_id_proto`**, **`$template_id`**, **`$form_proto`**, **`$settings`** — permet une logique métier sans toucher au textarea.

### Libellés admin et blocage de recréation après suppression

- Si **`extra.manual_variant_label`** est vrai sur une variante existante, le **label** colonne n’est pas remplacé par les libellés dérivés de l’import (voir docblock de **`poke_hub_pokemon_upsert_form_variant()`**).

- Une **liste de slugs** optionnelle (option **`poke_hub_gm_suppressed_form_slugs`**, filtre **`poke_hub_gm_suppressed_form_slugs`**) empêche un **INSERT** automatique depuis l’import lorsqu’un **slug granular** absent de la base est marqué comme volontairement supprimé. Lorsqu’un alias pointe vers un **registre** différent du granulaire, le contrôle utilise **explicitement le slug granular** (8ᵉ paramètre de **`poke_hub_pokemon_upsert_form_variant()`**) pour que la garde corresponde à la ligne que vous aviez supprimée en admin.

---

## Collections : costumes et copies (**Copy**)

Pour le **pool** des collections, les lignes assimilées aux **copies événements** (**template_id** contenant `_COPY_`) ou **`variant.category = clone`** sont traitées comme des **costumes** (comme Pokémon GO présente ces déclinaisons sous l’angle collection « déguisement / variante événement »).

Détails des filtres SQL : **`poke_hub_collections_get_pool()`** dans `modules/collections/functions/collections-helpers.php`.  
Vue générale catégories / formes : [../COLLECTIONS_AND_FORMS_CATEGORIES.md](../COLLECTIONS_AND_FORMS_CATEGORIES.md).

---

## Slug et images sprites

Les helpers d’image construisent un fichier **`{slug-global}.png`** (avec suffixes shiny/genre selon les options).

Une ligne **`{espece}-family`** utilise donc en principe la clé **`…/un-family.png`** ou équivalent selon tes réglages de bucket ; si cette ressource n’existe pas, les mécaniques de repli peuvent passer par le dex (voir **`poke_hub_pokemon_get_image_sources()`** et [../POKEMON_IMAGES.md](../POKEMON_IMAGES.md)).

---

## Voir aussi

- [COLLECTIONS_MODULE.md](../COLLECTIONS_MODULE.md) — règles de pool collections
- [POKEMON_IMAGES.md](../POKEMON_IMAGES.md) — assets
- [DATA_SAFETY.md](./DATA_SAFETY.md) — robustesse imports et données manuelles

---

*Documentation : alignée sur la logique courante du code ; après changement métier majeur dans l’import, mettre ce fichier à jour ou pointer vers une PR/issue.*
