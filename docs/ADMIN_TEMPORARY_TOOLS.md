# Outils temporaires (admin)

La page **Poké HUB → Temporary tools** (`admin.php?page=poke-hub-tools`) regroupe des **scripts ponctuels** (imports, migrations) : dates Pokekalos, **import Game Master** (module Pokémon), **traductions manquantes** (module Pokémon), **Images sync** (manifest CSV d’icônes GO à partir de la base), imports Fandom récurrents (heures de raids / vedette), Lundi Max, etc. Le code vit surtout dans `includes/admin-tools.php`, les onglets **Game Master** / **Translation** chargent `includes/settings/tabs/settings-tab-gamemaster.php` et `includes/settings/tabs/settings-tab-translation.php`, et pour les événements dans `modules/events/admin/`.

## Activer ou masquer le sous-menu

- **Réglage** : **Poké HUB → Settings → General** → section **Temporary tools (admin)** — case **Show Temporary tools** (option WordPress `poke_hub_temporary_tools_enabled`, **activée par défaut**).
- Si la case est **décochée** : le sous-menu **Temporary tools** disparaît ; l’accès direct à l’URL de la page renvoie une erreur **403** avec message ; les **POST** et imports **admin-post** liés à cette page (Fandom récurrent, Max Monday) sont **ignorés ou redirigés** vers les réglages pour éviter un traitement « orphelin ».

## Onglets sur la page

L’écran est découpé en **onglets** (paramètre d’URL `tab=…`) pour séparer les outils :

| Onglet (`tab`)   | Contenu principal |
|------------------|-------------------|
| `pokekalos`      | Import des dates de sortie Pokekalos (voir [pokemon/POKEKALOS_RELEASE_DATES.md](./pokemon/POKEKALOS_RELEASE_DATES.md)). |
| `gamemaster`     | Upload / import **Game Master** JSON (Pokémon, attaques, liaisons, formes, GMAX, etc.) — module **pokemon** actif. Comportement **non destructif** : voir [pokemon/DATA_SAFETY.md](./pokemon/DATA_SAFETY.md). Fichiers : `modules/pokemon/functions/pokemon-import-game-master.php`, `pokemon-import-game-master-batch.php`, `modules/pokemon/includes/pokemon-import-game-master-helpers.php`. |
| `translation`    | Outils de traduction côté données Pokémon (écran type *Edit Missing Translations*, exports éventuels selon version) — module **pokemon** actif. Politique i18n des chaînes UI : [TRANSLATION.md](./TRANSLATION.md). |
| `images-sync`    | Manifest **CSV** d’icônes Pokémon GO (chemins Addressable Assets / conventions locales) à partir des **lignes en base** ; options GO / HOME, shinies, sexes si dimorphisme, **Gigamax synthétique** selon les **flags** (`has_gmax_form` / `has_gigantamax_*`, etc.). **Sans** filtrage sur les **dates de sortie** (`extra.release.*`). Voir la section dédiée ci‑dessous ; contraste avec le module **Collections** : [COLLECTIONS_MODULE.md](./COLLECTIONS_MODULE.md) § *Gigamax synthétique (collections vs outil Images)*. |
| `raid-hour`      | Import Fandom — heure de raids (module **events** actif). |
| `spotlight-hour` | Import Fandom — heure vedette (module **events** actif). |
| `max-monday`     | Import Fandom — Lundi Max (module **events** actif). |

Les onglets **events** ne s’affichent que si le module **events** est actif et que les helpers correspondants sont chargés. Les onglets **gamemaster**, **translation** et **images-sync** ne s’affichent que si le module **pokemon** est actif.

## Images sync (`tab=images-sync`)

**Objectif.** Préparer les **chemins de fichiers** attendus pour les sprites / icônes (conventions comparable à PogoMiners ou copie depuis un dossier local), puis éventuellement **télécharger** ou **copier** en masse depuis un CSV.

**Données.** Le manifest est construit depuis la table **`pokemon`** et les métadonnées associées (**formes**, variantes, `extra`). Il inclut selon les cases cochées : versions **GO / HOME**, **chromatiques** (lignes distinctes où le modèle prévoit des variantes séparées), **sexes** lorsque le profil genre le justifie, lignes **Méga**, costumes et autres formes **présentes en base**.

**Pas de dates pour cet écran.** Contrairement au **module Collections**, cet outil **ne** doit **pas** omettre des icônes parce que `extra.release.*` ou la date Gigamax ne sont pas encore renseignées : la couverture suit la **structure des données** (lignes + drapeaux), pas le calendrier de sortie in‑game.

**Gigamax « synthétique » (manifest uniquement).** Quand aucune ligne G‑Max séparée n’existe mais que les **flags** déclarent une capacité Gigamax (`has_gmax_form`, alias `has_gigantamax_form`, etc.), une ligne supplémentaire peut être ajoutée (case *Add synthetic Gigantamax rows*). La sélection SQL associée (`poke_hub_tools_fetch_synthetic_gigantamax_base_rows_relaxed()` dans `includes/admin-tools.php`) est **relaxée** : elle ne filtre **pas** sur `release.gigantamax`. Pour l’usage **collections** (pools affichés au joueur), les helpers utilisent encore **`release.gigantamax`** et « sorti en GO » — voir [COLLECTIONS_MODULE.md](./COLLECTIONS_MODULE.md) § *Gigamax synthétique (collections vs outil Images)*.

**Code utile.** `poke_hub_tools_build_manifest_csv_from_db()`, `poke_hub_tools_manifest_fput_lines_for_pokemon()`, téléchargements / copie : `poke_hub_tools_download_images_from_manifest()`, `poke_hub_tools_copy_images_from_local_manifest()` — le tout dans `includes/admin-tools.php`. Conventions raster et buckets : [POKEMON_IMAGES.md](./POKEMON_IMAGES.md).

## Imports Fandom : titres et noms (raid hour, vedette, Lundi Max)

Les trois outils **lisent le wikitext** (API Fandom ou collage) et créent des lignes dans **`special_events`** (+ liaisons Pokémon). Les **titres** enregistrés (`title`, `title_en`, `title_fr`) et le **slug** (dérivé du titre EN) suivent les règles ci‑dessous.

### Noms Pokémon dans les titres

Les libellés **visibles dans les titres** proviennent de la table **`pokemon`** : colonnes **`name_en`** et **`name_fr`** (avec repli raisonnable sur le nom wiki résolu si une valeur manque en base). Le texte **wiki** (`{{I|…}}`, liens `[[…]]`, etc.) reste utilisé pour le **parsing** (résolution d’ID, attaques événement, notes régionales pour les heures de raids) — il n’est pas le libellé principal des titres une fois l’import effectué.

### Heure de raids (`tab=raid-hour`, type `raid-hour`)

- **Anglais** : liste des noms EN **puis** `Raid Hour`, avec jointure **« , … & »** (deux Pokémon : `A & B Raid Hour` ; trois ou plus : `A, B & C Raid Hour`).
- **Français** : `Heure de raids` + espace + **même logique** sur les noms FR (`Heure de raids A & B`, `Heure de raids A, B & C`).

Fichier : `modules/events/admin/events-admin-fandom-recurring-imports.php` (`pokehub_fandom_recurring_insert_one`, helper `pokehub_fandom_join_names_ampersand_list`).

### Heure vedette Pokémon (`tab=spotlight-hour`, type `pokemon-spotlight-hour`)

- **Anglais** : `Pokémon Spotlight Hour – ` + noms EN séparés par des **virgules** (plusieurs Pokémon sur une même ligne wiki).
- **Français** : `Heure vedette Pokémon – ` + noms FR séparés par des **virgules**.

Même fichier que les heures de raids ; gabarit distinct de celui produit par la **metabox Day Pokémon Hours** (voir [events/EVENEMENTS-DISTANTS.md](./events/EVENEMENTS-DISTANTS.md) § Événements Spotlight).

### Lundi Max (`tab=max-monday`, type `max-monday`)

- **Anglais** : `Max Monday ` + noms EN séparés par des **virgules** (ex. `Max Monday Rayquaza, Groudon`).
- **Français** : `Lundi Max ` + noms FR séparés par des **virgules**.

Fichier : `modules/events/admin/events-admin-max-monday-import.php` (`pokehub_max_monday_insert_one`).

### Données déjà en base

Les changements de **gabarit** ou de **source des noms** ne **réécrivent pas** les événements déjà importés. Pour aligner d’anciennes lignes, édition manuelle sous **Special events** ou script ponctuel côté site.

## Fichiers et helpers utiles

| Élément | Rôle |
|---------|------|
| `includes/admin-tools.php` | Sous-menu, page, onglets (dont `gamemaster`, `translation`, `images-sync`), import Pokekalos depuis l’admin ; génération manifest CSV / téléchargement d’icônes ; `poke_hub_temporary_tools_enabled()`, `poke_hub_admin_tools_url($tab)`. |
| `includes/settings/settings.php` | Enregistrement de l’option `poke_hub_temporary_tools_enabled` ; page **Settings** sans onglets Game Master / Translation (déplacés ici). |
| `includes/settings/tabs/settings-tab-gamemaster.php` | UI import Game Master (chargée uniquement sous **Temporary tools** quand le module Pokémon est actif). |
| `includes/settings/tabs/settings-tab-translation.php` | UI traductions données Pokémon (idem ; URLs d’action adaptées au contexte *tools*). |
| `includes/settings/tabs/settings-tab-general.php` | Case à cocher dans l’onglet **General**. |
| `poke-hub.php` | Fonction `poke_hub_admin_pages()` : retire `poke-hub-tools` de la liste utilisée pour le surlignage du menu parent lorsque l’option est désactivée. |
| `modules/events/admin/events-admin-fandom-recurring-imports.php` | Formulaires / redirects vers la page outils (avec `tab` cohérent). |
| `modules/events/admin/events-admin-max-monday-import.php` | Idem pour Lundi Max. |

## Évolution / retrait du code

Les imports Pokekalos restent aussi disponibles en **CLI** (`scripts/import-pokekalos-release-dates.php`) sans passer par l’admin. Retirer complètement la fonctionnalité « outils temporaires » du plugin impliquerait de supprimer ou de conditionner davantage de code (menu, handlers, modules events) — ce n’est plus documenté comme un simple retrait de `admin-tools.php` seul.

---

*Index de la documentation : [README du dossier docs](./README.md) · [Charte rédactionnelle](./REDACTION.md)*
