# Module Events – Poké HUB (index)

Le module **events** couvre les **événements spéciaux** (admin, listes, imports), le **routing front** (`/pokemon-go/events/`, onglets), le rendu des dates / passes GO, l’intégration **Elementor** et la distinction **local / distant / SQL**.

Ce fichier sert de **table des matières** ; le détail est réparti dans les pages ci-dessous.

## Pages de documentation

| Document | Contenu principal |
|----------|-------------------|
| [README-ROUTING.md](./README-ROUTING.md) | Routing des URLs d’événements, réécritures, comportement front |
| [EVENEMENTS-DISTANTS.md](./EVENEMENTS-DISTANTS.md) | Sources (`local_event`, `remote_event`, `special_event`), table `special_events`, sites distants |
| [INTEGRATION-ELEMENTOR.md](./INTEGRATION-ELEMENTOR.md) | Utilisation avec Elementor |
| [ADMIN_TEMPORARY_TOOLS.md](../ADMIN_TEMPORARY_TOOLS.md) (§ *Imports Fandom*) | Onglets **Temporary tools** : heure de raids, heure vedette, Lundi Max — **titres** (`title_en` / `title_fr`), noms issus de la table **`pokemon`**, wikitext Fandom |

## Événements spéciaux — Pokémon de l’événement

- Formulaire : `modules/events/admin/forms/events-admin-special-events-form.php` ; logique côté client : `assets/js/pokehub-special-events-admin.js` (sérialisation Pokémon / bonus avant envoi du formulaire, **Select2** pour le champ **type d’événement** et pour d’autres selects `event_type` en admin — voir la section suivante).
- **Régional** : la zone d’apparition d’un Pokémon régional est portée par la **fiche Pokémon** (`extra.regional.is_regional` et tables `pokemon_regional_mappings` / régions — voir [pokemon/README-REGIONAL-AUTO-CONFIG.md](../pokemon/README-REGIONAL-AUTO-CONFIG.md)). La liste `pokehub_get_all_pokemon_for_select()` expose `is_regional` sur chaque option (`data-is-regional`).
- **Disponibilité mondiale** : la case `is_worldwide_override` (« exception » : créneau mondial alors que l’espèce est régionale en base) **n’apparaît dans l’admin que si** le Pokémon choisi est régional. Sinon elle est masquée et décochée.
- **`region_note`** : plus de champ texte éditable sur cet écran ; la valeur éventuelle (ex. import Fandom) est conservée via un champ caché à l’enregistrement. L’affichage détaillé des zones pourra s’appuyer sur le Pokédex et les tables de régions.

<a id="event-type-select2-admin"></a>

## Type d’événement (admin, Select2, recherche par nom)

Les types affichés en liste proviennent de la taxonomie distante **`event_type`** (requête dans `poke_hub_events_get_all_event_types()`, fichier `modules/events/functions/events-admin-helpers.php`).

- **Objectif** : éviter de faire défiler une liste très longue dans un `<select>` natif ; **Select2** avec recherche sur le libellé des options.
- **Initialisation** : `assets/js/pokehub-special-events-admin.js` (`initEventTypeSelect2` / `initAllEventTypeSelect2`). Les selects déjà transformés ne sont pas réinitialisés (`select2-hidden-accessible`).
- **Cibles couvertes** :
  - formulaire **Special event** : `#event_type` / `name="event[event_type]"`, classe **`pokehub-event-type-select2`**, placeholder via **`data-placeholder`** ;
  - filtre de la liste unifiée : **`#filter-by-event-type`**, classe **`pokehub-event-type-select`** (largeur fixe **200px** côté Select2 pour s’aligner sur la barre de filtres) ;
  - tout autre `<select>` en admin dont le **`name`** ou l’**`id`** contient la chaîne **`event_type`**, ou qui porte les classes **`pokehub-event-type-select2`** / **`pokehub-event-type-select`** (ex. metabox article / Me5rine LAB hors du seul `#admin_lab_event_box`) ;
  - si la metabox **`#admin_lab_event_box`** est injectée ou modifiée dynamiquement, un **`MutationObserver`** relance l’initialisation pour les nouveaux champs.
- **Enqueue** : `modules/events/events.php` — sur le hook `admin_enqueue_scripts`, enregistrement de **Select2** (CSS + JS, CDN `select2@4.1.0-rc.0`) et du script **`pokehub-special-events-admin`** lorsque la page admin est **Poké HUB → Events** (`poke-hub-events`) **ou** l’éditeur de contenu **`post.php` / `post-new.php`** (pour les metaboxes d’article). **`wp_enqueue_media()`** et le script **`pokehub-media-url`** ne sont chargés que sur la page **Events** (pas sur l’éditeur d’article).
- **Obsolète** : l’ancienne initialisation du seul filtre `#filter-by-event-type` via **`wp_add_inline_script('select2', …)`** dans `modules/events/events.php` a été **retirée** ; le comportement est désormais entièrement porté par **`pokehub-special-events-admin.js`**.
- **Localisation AJAX** : l’objet **`PokeHubSpecialEvents`** (nonce / URL pour attaques Pokémon, etc.) n’est fourni que sur la page **Events** ; sur **`post.php`**, seule la couche **Select2 « type d’événement »** est concernée.

## Rappels code

- Point d’entrée module : `modules/events/events.php`
- Routing public typique : `modules/events/public/events-front-routing.php`
- Réglages globaux des modules : `includes/settings/settings-modules.php` (slug `events`)

## Voir aussi

- [CONTENT_BLOCKS.md](../CONTENT_BLOCKS.md) — Blocs liés aux événements
- [blocks/README.md](../blocks/README.md) — Liste des blocs
- [quests/README.md](../quests/README.md) — Quêtes (menu autonome, association contenu)
- [ORGANISATION.md](../ORGANISATION.md) — Vue d’ensemble du dépôt

---

*Index de la documentation : [README du dossier docs](../README.md) · [Charte rédactionnelle](../REDACTION.md)*
