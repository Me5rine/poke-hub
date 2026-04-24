# Événements : sources local / distant / spéciaux (SQL)

Ce document décrit comment le plugin distingue les **trois familles d’événements** dans la liste admin et le calendrier, et comment l’URL **`/pokemon-go/events/{slug}`** fonctionne pour les **événements spéciaux SQL** uniquement.

## Trois sources côté liste / calendrier

Les objets normalisés exposent un champ logique **`source`** (voir `poke_hub_events_normalize_event()` dans `modules/events/functions/events-queries.php`) :

| Valeur `source`   | Origine des données |
|-------------------|---------------------|
| **`local_event`** | Article ou contenu WordPress **local** (dates, type, etc.). |
| **`remote_event`**| Article **distant** (table `remote_posts` + métas, ex. site JV). |
| **`special_event`**| Ligne dans la table SQL **`special_events`** (préfixe selon les réglages Sources — voir ci‑dessous). |

Les anciennes étiquettes **`local_post`**, **`remote_post`**, **`special_local`**, **`special_remote`**, etc. sont **mappées** vers ces trois valeurs pour compatibilité ; ne pas les utiliser dans du nouveau code.

Il n’existe **pas** de seconde table type `remote_special_events` : une seule table **`special_events`** (nom complet via `pokehub_get_table('special_events')`), dont le préfixe suit la **source Pokémon / contenu** configurée (même principe que les tables `content_*`).

## Routing `/pokemon-go/events/{slug}`

Fichier : `modules/events/public/events-front-routing.php`.

1. Le slug d’URL est lu depuis la query var `pokehub_special_event`.
2. Une requête **`SELECT * FROM {special_events} WHERE slug = %s`** est exécutée sur la table résolue par `pokehub_get_table('special_events')`.
3. Si aucune ligne : **404**.
4. Si une ligne : affichage du template événement spécial ; l’objet reçoit **`_source = 'local'`** pour l’instant (chemins d’images médias WordPress locaux). Il n’y a **pas** de double recherche « d’abord local puis table distante » pour les spéciaux : tout passe par la **même** table `special_events`, au bon préfixe.

Les **articles d’actualité distants** (JV, etc.) ont leurs **propres permaliens** (posts dans `remote_posts`) ; ils ne sont pas servis par cette route `special_events` sauf configuration de réécriture spécifique ailleurs.

## Événements Spotlight (Day Pokémon Hours)

Lorsque la metabox **Day Pokémon Hours** enregistre des créneaux **featured / Spotlight** et que les tables événements existent, le plugin crée des lignes dans **`special_events`** avec notamment :

- **`event_type`** : type enregistré en base (souvent `pokemon-spotlight-hour`, résolu via `pokehub_resolve_spotlight_event_type_slug()` dans `includes/content/content-helpers.php`).
- **`content_source_type`** / **`content_source_id`** : liaison stable au **post WordPress** source (`post` + ID d’article), pour que le bloc **Day Pokémon Hours** retrouve les créneaux même si le **slug** ou les **titres** sont modifiés en admin.
- **`mode`** : **`local`**. Les horaires sont calculés et stockés comme **heure « murale » du site** (`wp_timezone()`), comme pour le mode **local** du formulaire d’édition des **Special events** (`poke_hub_special_event_format_datetime` / `poke_hub_special_event_parse_datetime` dans `modules/events/functions/events-helpers.php`). Le mode **`fixed`** (interprétation **UTC** des champs `datetime-local`) ne doit pas être utilisé pour ces lignes, sinon les heures divergent entre la metabox **Day Pokémon Hours** et l’admin événements spéciaux.
- **Titres** : `title_en` au format **`{Nom EN} Spotlight Hour`** ; `title_fr` au format **`Heure vedette {Nom FR}`** ; `title` aligné sur `title_en`.
  - Lors d’un **réenregistrement** depuis la metabox **Day Pokémon Hours**, les valeurs existantes **`title_en`** et **`title_fr`** sont préservées séparément (si renseignées), pour éviter qu’un titre FR soit écrasé par le titre EN.
  - **Import Fandom** (page **Temporary tools**, onglet *spotlight-hour*) : autre gabarit — **`Pokémon Spotlight Hour – {noms EN}`** / **`Heure vedette Pokémon – {noms FR}`**, noms depuis la table **`pokemon`** ; détail dans [ADMIN_TEMPORARY_TOOLS.md](../ADMIN_TEMPORARY_TOOLS.md) § *Imports Fandom : titres et noms*.
- **Slug** : base **`{slug-pokemon}-spotlight-hour`**, unicité via **`pokehub_generate_unique_event_slug()`** (suffixe `-1`, `-2`, … comme pour les autres spéciaux).

La lecture côté bloc combine la liaison **`content_source_*`** et une rétrocompatibilité sur d’anciens préfixes de slug ; détail dans `pokehub_spotlight_sql_parent_scope()`.

**Lecture dans la metabox** : `pokehub_content_get_featured_hours_classic_events_entries_for_parent()` convertit les timestamps Unix vers le fuseau du site pour remplir les champs date / heure — aligné avec **`mode` = `local`**.

**Données anciennes** : une migration ponctuelle (`pokehub_spotlight_special_events_mode_local_v1`, exécutée avec les migrations de table `special_events` dans `includes/pokehub-db.php`) remet en **`local`** les lignes Spotlight encore en **`fixed`**, pour corriger l’affichage / la sauvegarde depuis l’admin **Special events**.

## Images (hooks / template)

- Si **`image_url`** est renseignée : utilisée en priorité.
- Sinon **`image_id`** : URL média locale avec `wp_get_attachment_image_url()` lorsque `_source !== 'remote'` (comportement actuel du routing spécial : `_source` reste `local` pour les lignes SQL servies sur le site courant).
- Pour les événements **distant** au sens **liste** (`remote_event`), les URLs et images passent par les helpers « remote » (`poke_hub_events_get_remote_attachment_url`, etc.) dans les écrans qui consomment la liste normalisée, pas via cette route SQL seule.

Préférez, dans les thèmes / hooks, une URL d’image déjà calculée lorsque le module l’expose (ex. champs dérivés du normalizer / rendu liste).

## Slugs uniques

Les slugs doivent rester **uniques** dans `special_events` **et** ne pas entrer en collision avec un **`post_name`** dans **`remote_posts`** : `pokehub_generate_unique_event_slug()` vérifie les deux.

## Dépannage

| Problème | Piste |
|----------|--------|
| 404 sur `/pokemon-go/events/mon-slug` | Vérifier qu’une ligne existe dans `special_events` avec ce `slug` (même préfixe que la source configurée). |
| Spotlight absent du bloc Day Hours | Vérifier `content_source_type` / `content_source_id` sur les lignes ; re-sauver la metabox sur l’article ; vérifier le type `pokemon-spotlight-hour` (ou équivalent en base). |
| `title_fr` remplacé par l’anglais après sauvegarde d’un article | Vérifier que le code de sauvegarde Spotlight est à jour (préservation distincte `title_en` / `title_fr`) ; corriger les lignes déjà impactées une fois en admin, puis re-sauver l’article pour valider le flux. |
| Heures Spotlight fausses après édition dans **Special events** | Vérifier que **`mode` = `local`** pour ce type d’événement ; recharger une page admin qui déclenche les migrations si besoin (option `pokehub_spotlight_special_events_mode_local_v1`) ; sinon repasser le mode à **local** à la main ou re-sauver depuis la metabox **Day Pokémon Hours**. |
| Liste admin incohérente | Vérifier les filtres `source` (`local_event` / `remote_event` / `special_event`). |

## Voir aussi

- [README-ROUTING.md](./README-ROUTING.md) — règles de réécriture et fichiers concernés.
- [CONTENT_BLOCKS.md](../CONTENT_BLOCKS.md) — bloc **Day Pokémon Hours**.
- [BONUS_SOURCE_AND_BLOCKS.md](../BONUS_SOURCE_AND_BLOCKS.md) — préfixe catalogue bonus (même logique « site principal / distant » que les tables Pokémon).

---

*Index de la documentation : [README du dossier docs](../README.md) · [Charte rédactionnelle](../REDACTION.md)*
