# Sécurité des données Pokémon (non écrasement)

Ce document résume les garde-fous mis en place pour éviter la perte de données manuelles (traductions, dimorphisme, métadonnées custom, etc.) lors des imports et des outils admin.

## Principes généraux

- Les mises à jour doivent être **non destructives** : fusion avec l'existant plutôt que remplacement complet.
- Quand c'est possible, on ne met à jour que les **colonnes réellement modifiées**.
- Si `extra` (JSON) est invalide, on **n'écrase pas** : la ligne est ignorée ou le JSON brut existant est conservé.

## Champ `extra` JSON

- Helpers centraux :
  - `poke_hub_pokemon_decode_extra_json()`
  - `poke_hub_pokemon_encode_extra_json()`
- En cas de JSON invalide :
  - les flux critiques skip l'écriture (ou gardent la valeur brute existante),
  - on évite de repartir de `[]` puis sauvegarder.

## Import Game Master

**Emplacement admin :** **Poké HUB → Temporary tools** — onglet **Game Master** (`tab=gamemaster`, module **pokemon** actif). Voir [ADMIN_TEMPORARY_TOOLS.md](../ADMIN_TEMPORARY_TOOLS.md).

Fichiers principaux : `modules/pokemon/functions/pokemon-import-game-master.php`, `pokemon-import-game-master-batch.php`, `modules/pokemon/includes/pokemon-import-game-master-helpers.php`.

### Robustesse (gros JSON, lots)

- Limite mémoire relevée côté import, `ignore_user_abort(true)` pour limiter les coupures client.
- Import par **lots** : état dans les options WP (`poke_hub_gm_import_status`, `poke_hub_gm_batch_state`) ; **watchdog** AJAX (import bloqué > ~10 min → état erreur + message).
- `register_shutdown_function` sur le batch pour journaliser une **erreur fatale** PHP, marquer l’import en erreur et libérer le verrou.

### Colonne `extra` (JSON)

1. **Dates de sortie** : les valeurs déjà présentes dans `extra['release']` sont conservées lorsque l’import n’apporte pas de date pour la même clé (comportement existant avant fusion globale).
2. **Noms i18n** (`extra['names']`) : les langues déjà renseignées ne sont pas remplacées par une chaîne vide si le Game Master / le filtre i18n ne fournit rien pour cette langue — `poke_hub_pokemon_gm_merge_extra_names_with_existing()`.
3. **Fusion profonde** : `poke_hub_pokemon_gm_deep_merge_extra( $extra_existant, $extra_import )` fusionne récursivement les **objets / tableaux associatifs** ; toute branche ou clé absente de l’import reste en base (extensions, métadonnées métier, etc.).
4. **Listes indexées** (indices `0…n-1`, ex. `quickMoves`, `cinematicMoves`) : traitées comme un **bloc entier** — la liste fournie par le Game Master remplace la précédente pour ce tableau (comportement attendu pour les moves).
5. **Champs booléens / flags Pokémon** : certains indicateurs pouvant être posés **à la main** en admin ne sont pas écrasés par l’import s’ils sont déjà actifs — helper `poke_hub_pokemon_gm_preserve_manual_pokemon_fields()`.
6. **Formes / variantes globales** : à l’import, réutilisation d’une ligne existante si le **slug** de forme est déjà connu (`poke_hub_pokemon_get_form_variant_by_slug` / `poke_hub_pokemon_upsert_form_variant`) ; sinon création. Le **type de forme** (`category` sur `pokemon_form_variants`) est **deviné** depuis le GM (`poke_hub_pokemon_guess_form_type_from_gm`) puis éditable en admin.
7. **Conditions de changement de forme** : extrait du GM dans `extra['form_change_rules']` ; affichage **lecture seule** sur la fiche Pokémon (admin).
8. **Gigantamax** : détection via `sourdoughMoveMappingSettings` et moves `VN_BM_*` ; `extra['has_gmax_form']` ; liaisons d’attaques en rôle **`gmax`** ; case manuelle possible sur la fiche Pokémon.
9. **Espèces à forme NORMAL explicite** (ex. Palkia / Dialga) : logique `species_with_explicit_normal_form` — détection à partir de **formSettings** *et* d’une agrégation sur **pokemonSettings** (au moins une entrée `*_NORMAL` et une autre forme), pour éviter de fusionner la ligne NORMAL avec le slug de base.

### Génération (`generation_id`) depuis le n° de dex

Quand la génération n’est pas déduite autrement du flux Game Master, **`poke_hub_pokemon_guess_generation_by_dex()`** (`modules/pokemon/includes/pokemon-import-game-master-helpers.php`) affecte un **numéro de génération 1…9** à partir du **n° de Pokédex national** (paliers historiques). La **génération 9** couvre la plage **906 à 1026** (Paldea et extensions telles que prises en charge dans le plugin). Hors plages connues, la fonction renvoie **0** (inconnu).

Les fiches **déjà en base** avec une génération erronée ne sont pas recalculées automatiquement au seul déploiement d’une correction : un **nouvel import Game Master** (ou une mise à jour manuelle / SQL) est nécessaire pour réaligner `generation_id` avec la table taxonomique `generations`. Le module **Collections** trie et regroupe les entrées **sans génération jointe** en **dernier** (évite d’afficher ces espèces tout en haut des listes par génération).

### Colonnes SQL (Pokémon, Méga, attaques)

- **`poke_hub_pokemon_gm_wpdb_data_only_changed_columns()`** : sur **UPDATE**, seules les colonnes dont la valeur a réellement changé sont envoyées à `$wpdb->update()`. La colonne `extra` est comparée via un JSON **normalisé** (tri récursif des clés) pour limiter les faux écarts dus à l’ordre des clés. Si aucune colonne ne change, aucun `UPDATE` n’est exécuté.
- **`name_fr`** : si l’import n’a pas de traduction FR, la clé `name_fr` peut être omise du jeu de données pour **ne pas écraser** un `name_fr` déjà saisi en admin.

### Méga / Primo (`tempEvoOverrides`)

- **Formats `$wpdb`** : les lignes Méga utilisent **`poke_hub_pokemon_gm_wpdb_format_for_pokemon_row( $mega_data )`** calculé sur **le même** `$mega_data` que l’insert/update — on ne réutilise pas le format de la forme de base (sinon décalage `%d` / `%s` si `name_fr` est absent sur la ligne de base, ce qui pouvait corrompre le `slug`, ex. `0`).
- **Recherche de ligne existante** : d’abord par `slug` attendu (`mega-tortank`, etc.) ; si aucune ligne ne correspond, repli par **`dex_number` + `form_variant_id` + `is_default = 0`** pour retrouver une ligne déjà en base avec un slug corrompu et la mettre à jour (slug + `extra` corrects au re-import).

### Sprites / URLs d’images (lien)

- Si un objet Pokémon a un `slug` invalide (`0`, chaîne vide) ou des données partielles, les helpers d’image peuvent retomber sur le Dex ou recharger la ligne en base — voir `poke_hub_pokemon_get_image_sources()` dans `includes/functions/pokemon-public-helpers.php` et [../POKEMON_IMAGES.md](../POKEMON_IMAGES.md).

## Traductions (admin : écran *Edit Missing Translations*)

**Emplacement :** **Poké HUB → Temporary tools** — onglet **Translation** (`tab=translation`, module **pokemon** actif), et non plus sous **Settings**. Même règles de non-écrasement ci-dessous.

### Edit Missing Translations

- Modifie seulement :
  - `extra['names'][lang]`
  - et `name_fr` si langue = `fr`.
- Le reste de `extra` est conservé.
- Pas d'update si la valeur n'a pas changé.
- Si `extra` est invalide : ligne ignorée (pas d'écrasement).

### Bulk Fetch Bulbapedia

- Cible les lignes ayant au moins une langue manquante parmi `fr,de,it,es,ja,ko` (hors mode force).
- Support de reprise par curseur : `start_after_id` / `next_start_after_id`.
- Si `extra` est invalide : ligne en erreur/skip, pas d'écrasement.

## Import Pokekalos (dates de sortie)

- Met à jour uniquement `extra['release']`.
- Conserve les autres clés de `extra`.
- Si `skip_existing` est activé, les dates déjà renseignées ne sont pas remplacées.
- Si `extra` est invalide ou qu'aucune différence n'est détectée : pas d'update.
- L’écran admin correspond à l’onglet **Dates (Pokekalos)** de **Temporary tools** ; ce sous-menu peut être désactivé dans **Réglages → General** sans changer le comportement du CLI (voir [ADMIN_TEMPORARY_TOOLS.md](../ADMIN_TEMPORARY_TOOLS.md)).

## Tables de liaison (types, attaques)

Les tables **`pokemon_type_links`**, **`attack_type_links`**, **`pokemon_attack_links`** ont des **clés primaires composites** (pas de colonne `id`). En import **non destructif** :

- **Pokémon ↔ types** : pas de vidage global du Pokémon ; **upsert** par `(pokemon_id, type_id)` — nouveaux slots uniquement si le couple n’existe pas (`poke_hub_pokemon_sync_pokemon_types_links( …, false )`).
- **Attaque ↔ types** : ajout des couples manquants sans supprimer les liens existants (`poke_hub_pokemon_import_sync_attack_types_links_non_destructive`).
- **Pokémon ↔ attaques** : en mode import non destructif, pas de `DELETE` massif sur `fast`/`charged` ; pour chaque lien, **mise à jour** de la ligne existante (fusion de `extra`, **max** des flags `is_legacy` / `is_event` / `is_elite_tm`) ou **insert** ; les rôles **`special`** (et autres hors GM) restent intacts. Rôle **`gmax`** géré comme les autres pour les mappings GMAX.

Les synchronisations « plein remplacement » (suppression ciblée `fast`/`charged` uniquement) restent le comportement par défaut **hors** import GM lorsque le code appelle explicitement le mode remplacement.

## Points d'attention

- Un `DELETE` puis réinsertion sur une table de liaison doit être limité au périmètre fonctionnel (rôles ou game_key ciblés).
- Toute nouvelle écriture de `extra` doit passer par les helpers safe decode/encode.
- Les formulaires admin qui réécrivent `extra` doivent partir de l'existant et fusionner.

---

*Index de la documentation : [README du dossier docs](../README.md) · [Charte rédactionnelle](../REDACTION.md)*
