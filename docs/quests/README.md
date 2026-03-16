# Module Quêtes – Poké HUB

Le module **Quêtes** gère les quêtes (field research) et les **catégories de quêtes** de manière centralisée et autonome. Il ne dépend pas du module Events pour les menus ou l’interface. Le **bloc** « Quêtes d’événement » (affichage des quêtes sur un article) reste enregistré par le module Blocks et s’appuie sur le module Events pour les données du post courant.

## Activation

1. **Poké HUB → Réglages → General**
2. Cochez **Quêtes**
3. Enregistrez

## Menu et interface

- **Menu admin :** Poké HUB → **Quêtes**
- **Sous-onglets :**
  - **Quêtes** : liste des ensembles de quêtes (`content_quests`) avec leur source (pool global ou contenu local), bouton « Ajouter un ensemble de quêtes », édition des lignes (tâche, récompenses, catégorie).
  - **Catégories de quêtes** : liste des groupes (ex. Captures, Lancers), formulaire d’ajout / édition (titre FR/EN, couleur, ordre).

### Association à un contenu (local ou global)

Lors de l’ajout d’un ensemble de quêtes, vous choisissez :

- **Pool global** : quêtes générales (non liées à un article précis).
- **Un contenu local** : association à un **article** ou un **événement** (post type `post` ou `pokehub_event`). Les quêtes de cet ensemble s’affichent avec le bloc « Quêtes d’événement » sur ce contenu (ou en contenu distant si la source est remote).

L’éditeur de quêtes (tâche, récompenses, catégorie) est partagé avec la metabox quêtes des articles (module Events) via `includes/content/content-quests-editor.php`.

## Tables de contenu

Le module utilise les tables de contenu (scope `content_source`) : **même préfixe** que les tables Pokémon (Réglages > Sources > Pokémon table prefix (remote)).

- **content_quests** : une ligne par source (`source_type`, `source_id`) avec dates optionnelles (`start_ts`, `end_ts`)
- **content_quest_lines** : lignes de quêtes (task, rewards en JSON, `quest_group_id`)
- **quest_groups** : catalogue des catégories (titre FR/EN, couleur, ordre)

Ces tables sont créées lorsque le module **Quêtes**, **Events** ou **Eggs** est actif (`includes/pokehub-db.php`).

## Relation avec le module Events

- **Menus** : le module Events **n’enregistre plus** les sous-menus « Quests » ni « Quest Groups ». Toute la gestion des quêtes et des catégories se fait via **Poké HUB → Quêtes**.
- **Bloc « Quêtes d’événement »** : enregistré par le module Blocks, il **dépend du module Events** (pas du module Quêtes). Il affiche les quêtes du post courant (données dans `content_quests` / `content_quest_lines`).
- **Metabox quêtes** : sur les articles et événements (post / pokehub_event), la metabox est fournie par le module Events ; les données sont lues/écrites dans les mêmes tables. L’éditeur HTML est partagé (`includes/content/content-quests-editor.php`).

## Fichiers partagés (quêtes)

- **`includes/content/content-helpers.php`** : `pokehub_quests_clean_from_request()`, `pokehub_content_get_quests()`, `pokehub_content_save_quests()`, groupes, etc.
- **`includes/content/content-quests-editor.php`** : `pokehub_render_quest_editor_item()` (éditeur d’une ligne de quête), utilisé par la metabox Events et par la page d’édition du module Quêtes.

## Voir aussi

- [CONTENT_BLOCKS.md](../CONTENT_BLOCKS.md) – Blocs et tables de contenu
- [docs/blocks/README.md](../blocks/README.md) – Liste des blocs (dont event-quests)
