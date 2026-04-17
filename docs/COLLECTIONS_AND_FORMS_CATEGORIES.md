# Catégories : formes vs collections

Le plugin utilise le mot **« catégorie »** à deux endroits différents. Ce n’est pas le même concept.

---

## 1. Catégorie des **formes** (form variants)

**Où :** Poké HUB → Pokémon → **Formes**  
**Table :** `pokemon_form_variants`  
**Champ :** `category` — sélectionné via un **menu déroulant** en admin (plus de champ texte libre).

Les **formes** sont des variantes globales (un registre partagé). Chaque forme a :
- un **form_slug** (ex. `armored`, `fall-2019`, `halloween_2020`),
- un **label** (nom affiché),
- une **category** (type logique de la forme), choisie dans la liste ci‑dessous.

### Valeurs de catégorie (menu déroulant)

| Valeur (slug) | Libellé affiché      |
|---------------|----------------------|
| `normal`      | Normal               |
| `costume`     | Costume / Event      |
| `clone`       | Clone                |
| `regional`    | Regional             |
| `shadow`      | Shadow               |
| `purified`    | Purified             |
| `mega`        | Mega                 |
| `alola`       | Alola                |
| `galar`       | Galar                |
| `hisui`       | Hisui                |
| `paldea`      | Paldea               |

Si une forme en base a une catégorie qui n’est pas dans cette liste (données anciennes), le formulaire affiche une option **« Other: &lt;valeur&gt; »**. En choisissant une catégorie standard (ex. **Costume / Event**) et en enregistrant, la valeur est migrée.

**Costume / Event :** si vous choisissez cette catégorie pour une forme, tout Pokémon utilisant cette forme est considéré comme costumé/événement *via la forme* (sans avoir à cocher la case sur chaque fiche Pokémon).

En édition d’un **Pokémon** (fiche individuelle), vous assignez une **Forme / variant** (form_variant_id). Si cette forme a `category = 'costume'`, le Pokémon est considéré comme costumé *via la forme*. La case **« Event or costumed »** peut en plus être cochée manuellement sur la fiche ; le plugin considère qu’un Pokémon est costumé si au moins l’un des deux est vrai (forme costume ou case cochée).

**En résumé :** la catégorie des formes sert à **typer la forme** (costume, normal, méga…). Les Pokémon qui utilisent une forme avec `category = 'costume'` sont donc des Pokémon costumés.

### Association Pokémon ↔ événements

Dès qu’un Pokémon est reconnu comme **événement ou costumé** (forme costume ou case « Event or costumed » cochée), la fiche Pokémon affiche une section **« Event Association »**. Elle permet d’associer ce Pokémon à **un ou plusieurs événements** (posts, événements spéciaux locaux ou distants), via le même sélecteur d’événements que pour les **fonds** (backgrounds) et les **formes/variants** (form-form).

- **Table :** `pokemon_pokemon_events` (`pokemon_id`, `event_type`, `event_id`).
- **Helper :** `poke_hub_get_pokemon_events($pokemon_id)` — retourne la liste des événements associés (tableau `['event_type' => ..., 'event_id' => ...]`).
- **Fichiers :** formulaire Pokémon `modules/pokemon/admin/forms/pokemon-form.php` (section Event Association), sauvegarde dans `modules/pokemon/admin/sections/pokemon.php` ; sélecteur réutilisable dans `includes/admin/event-picker.php` (`poke_hub_get_events_for_picker()`, `poke_hub_render_event_picker_row()`).

---

## 2. Catégorie des **collections**

**Où :** module **Collections** (shortcode, création / édition d’une collection)  
**Fonction :** `poke_hub_collections_get_categories()`

Quand vous créez une collection, vous choisissez une **catégorie de collection** : « Chromatiques », « Pokémon costumés », « Pokémon avec fonds », etc. Cette catégorie indique **quel type de Pokémon** la collection doit afficher :

| Slug catégorie  | Signification                    | Pool = quels Pokémon ? |
|-----------------|----------------------------------|-------------------------|
| `shiny`         | Chromatiques                     | Tous (ou selon options) |
| `costume`       | Pokémon costumés                 | Ceux avec forme `costume` **ou** flag « Event/costumed » |
| `costume_shiny` | Costumés chromatiques            | Idem, contexte shiny    |
| `background`    | Pokémon avec fonds               | Ceux liés à au moins un fond |
| `shadow`        | Obscurs                          | Ceux avec has_shadow    |
| …               | …                                | …                       |

**En résumé :** la catégorie de **collection** sert à choisir **le type de collection** (ce qu’on collectionne). Le module s’en sert pour construire le **pool** (liste des Pokémon affichés) avec les bons filtres (formes, fonds, etc.).

**Catégories « spécifiques »** : pour certaines catégories (Gigantamax, Dynamax, Costume, Shadow, Purified, Fonds…), la collection n’affiche **que** ce type. Les options « inclure Méga / Gigantamax / Dynamax / costumes » ne sont pas proposées (paramètres adaptatifs). Détails dans **docs/COLLECTIONS_MODULE.md** (sections *Catégories spécifiques* et *Statuts d’une entrée* ; vue grille, filtre **Afficher dans la grille**, légende des tuiles).

---

## Lien entre les deux pour les costumes

Pour qu’un Pokémon apparaisse dans une collection **« Pokémon costumés »** ou **« Costumés chromatiques »**, il doit être reconnu comme costumé. Le plugin le fait de **deux façons** (au moins une doit être vraie) :

1. **Par la forme :** le Pokémon a un `form_variant_id` qui pointe vers une forme dont la **category** (dans Formes) est `costume`.
2. **Par le flag :** sur la fiche Pokémon (édition), la case **« Event or costumed »** est cochée → `extra.is_event_costumed = true`.

Donc :
- **Catégorie des formes** = typage des formes (costume, normal, méga…) dans le registre des formes.
- **Catégorie des collections** = type de collection (costumés, shiny, fonds…) qui détermine quel pool de Pokémon est affiché.

Les deux sont indépendants ; le mot « catégorie » désigne simplement un type dans chaque contexte (forme vs collection).

---

*Index de la documentation : [README du dossier docs](README.md) · [Charte rédactionnelle](REDACTION.md)*
