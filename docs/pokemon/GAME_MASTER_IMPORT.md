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

---

## Lignes `*-family` (placeholder de regroupement)

But : une entrée permettant de représenter une **famille formelle** lorsque plusieurs formes métier sont détectées pour l’espèce, **sans** dupliquer le costume pur ou la copie d’événement comme « famille à part » suivant les filtres configurés dans le pré-scan.

Décision portée par le tableau interne **`species_with_explicit_normal_form`** (et la logique associée sur `formSettings` + agrégation `pokemonSettings`) ; la ligne importée avec `form` vide prend le slug **`{slug-espece}-family`**.

**Comportement d’écriture** : pour une ligne famille donnée, le code fait un **`SELECT`** sur `pokehub_*pokemon.slug` ; si existe → **`UPDATE`**, sinon **`INSERT`**. Il n’est **pas garanti au niveau MySQL** l’absence de doublons de slug : la table Pokémon n’a pas de contrainte **UNIQUE** sur `slug` (index seulement). En fonctionnement nominal d’un import unique, un seul slug famille par espèce.

---

## Gigantamax : `extra.has_gmax_form`

- L’existence potentielle Gigamax pour l’espèce est détectée via les entrées GM (famille mappings G‑Max / espèces).
- **`extra['has_gmax_form']`** ne doit refléter un **oui** fictif métier que sur la **fiche par défaut** de l’espèce (`is_default = 1` pour cette entrée là).
- Après fusion **`poke_hub_pokemon_gm_deep_merge_extra()`**, la valeur est **ré-alignée** sur cette règle pour éviter de conserver un ancien `true` présent dans l’historique pour une variante costume / copie / forme dérivée.

---

## Variantes globales (`pokemon_form_variants`)

À l’import, si une ligne existe déjà pour le `form_slug` calculé, l’outil fait un **upsert** (mise à jour y compris de la **`category`**) avec la valeur devinée par **`poke_hub_pokemon_guess_form_type_from_gm()`** (`costume`, `clone`, `visual`, etc.), afin de corriger après coup les classements anciens hors admin.

Référence : `poke_hub_pokemon_upsert_form_variant()` dans `modules/pokemon/includes/pokemon-helpers.php`.

Priorités métier Zarbi / Vivaldaim / Haydaim (visuel saison non « costume » erroné) sont appliquées dans le helper **`poke_hub_pokemon_guess_form_type_from_gm()`** — voir commentaires dans le fichier.

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
