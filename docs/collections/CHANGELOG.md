# Journal des changements documentés — Collections

Ce fichier recense des **évolutions ponctuelles** du module Collections (correctifs, admin, hooks) lorsqu’elles sont explicitement documentées suite à une implémentation. Pour le fonctionnement général, voir [COLLECTIONS_MODULE.md](../COLLECTIONS_MODULE.md).

---

## 2026-05-08

### Vue collection — menu tiroir barre d’outils (DOM, empilement, scroll)

**Implémentation** : **`[data-toolbar-menu-drawer]`** est sorti de **`.pokehub-collection-toolbar-stack`** et placé comme frère (après la pile, avant **`.pokehub-collection-tiles`**) dans **`collections-shortcode.php`**, pour éviter que **`isolation` + `z-index`** de la pile ne piègent le drawer en `fixed` derrière le header. **`collections-front.js`** : **`document.body.style.overflow = 'hidden'`** pendant l’ouverture du menu (`is-open`), rétabli à la fermeture si le tiroir était ouvert.

**Mise à jour doc** : [COLLECTIONS_MODULE.md](../COLLECTIONS_MODULE.md) (§ *Vue détail : structure DOM*), [COLLECTIONS_HEADERS.md](../../modules/collections/COLLECTIONS_HEADERS.md), [COLLECTIONS_THEME_CSS.md](../../modules/collections/COLLECTIONS_THEME_CSS.md) (empilement / `--pokehub-collection-toolbar-stack-z`), [POKEHUB_CSS_CLASSES.md](../POKEHUB_CSS_CLASSES.md) (variante **`pokehub-collections-drawer--toolbar`**). Côté thème Me5rine Lab : **`css/poke-hub/README.md`** (ligne **`parts/13-collections-front.css`**, rappel menu plein viewport).

---

### Documentation — phrases de recherche GO (préfixes, fonds, contexte)

**Mise à jour** : [COLLECTIONS_MODULE.md](../COLLECTIONS_MODULE.md) — § *Phrases GO : données serveur et langue* (table des préfixes FR/EN, fonds lieu/spécial via `synthetic_go_background_background_type`, préfixes de contexte **chanceux** / **œufs uniquement**, ordre des groupes, passes de dédoublonnage) ; correction de l’ancienne formulation « une seule entrée par dex sur tous les groupes » (comportement réel : regroupements + dédup par groupe et règles parent/sexe). Index [README.md](./README.md), [README.md principal](../README.md) et [COLLECTIONS_AND_FORMS_CATEGORIES.md](../COLLECTIONS_AND_FORMS_CATEGORIES.md) : renvois alignés.

---

### Documentation collections — alignement sur la barre d’outils actuelle

**Contexte** : la doc décrivait encore le mode « compact » (`pokehub-collection--compact`), `.pokehub-collection-sticky-tools` et `--pokehub-sticky-tools-current-height`, absents du comportement actuel.

**Mise à jour** : [COLLECTIONS_MODULE.md](../COLLECTIONS_MODULE.md) (structure DOM, barre **`[data-collection-fixed-toolbar]`**, tuiles **`[data-flow-tiles-host]`** / **`[data-fixed-tiles-host]`**, expand, drawer), [COLLECTIONS_THEME_CSS.md](../../modules/collections/COLLECTIONS_THEME_CSS.md) (variable **`--pokehub-collection-fixed-toolbar-height`**, jump génération ≤1024px), [COLLECTIONS_HEADERS.md](../../modules/collections/COLLECTIONS_HEADERS.md), [POKEHUB_CSS_CLASSES.md](../POKEHUB_CSS_CLASSES.md), index [README.md](./README.md). Côté thème Me5rine Lab : `css/poke-hub/README.md` (tableau des fichiers `parts/`), ligne sur `13-collections-front.css`.

---

### Forme normale Morphéo (slug `castform`) absente du pool « toutes les formes »

**Symptôme** : avec **une entrée par espèce désactivée** (« toutes les formes »), les formes Soleil / Pluie / Blizzard s’affichent, mais pas la forme normale lorsque son slug est **`castform`** et la catégorie de variante en base **`default`** (ou équivalent).

**Cause** : l’espèce CASTFORM est classée côté import GM comme groupe « motifs visuels » (`poke_hub_pokemon_gm_visual_variant_species_protos` inclut `CASTFORM`). Les lignes **`fv.category = 'visual'`** avec slug prolongé (`castform-sunny`, etc.) déclenchent alors la clause SQL qui **supprime du pool** la ligne au **slug de base** jugée être un *stub avant motifs* (comportement voulu pour Zarbi / Prismillon / Spinda, où la carte « motif » prolonge une base commune).

Ce n’est **pas** une régression du regroupement `*-family*` : Morphéo n’est pas traité comme Aegislash / Shaymin dans ce mécanisme.

**Correctif** : exceptions par **№ Dex national** avec :

- Fonction **`poke_hub_collections_visual_variant_base_stub_keep_dex_numbers()`** (`collections-helpers.php`) ;
- Liste par défaut : **`351`** (Morphéo / Castform) ;
- Filtre WordPress **`poke_hub_collections_visual_variant_base_stub_keep_dex_numbers`** pour ajouter ou surcharger les № exemptés.

**Référence code** : `modules/collections/functions/collections-helpers.php` — construction du bloc `WHERE` en mode « toutes les formes » (clause NOT sur les variantes `visual` prolongeant le slug), avec désormais :  
`( p.dex_number IN (…exceptions…) OR NOT ( …stub visual… ) )`.

---

### Page d’administration — liste des collections enregistrées

**Besoin** : consulter en back-office les collections **sauvegardées en base** (compte WordPress ou **anonyme** avec `user_id = 0`), avec lien front, type, progression, métadonnées utiles au support.

**Implémentation** :

| Élément | Détail |
|--------|--------|
| **Menu** | **Poké HUB → Collections** (`admin.php?page=poke-hub-collections`) |
| **Droit** | `manage_options` (aligné aux autres sous-menus Poké HUB techniques) |
| **Fichier** | `modules/collections/admin/collections-admin.php` |
| **Chargement** | `modules/collections/collections.php` — `require` conditionnel si `is_admin()` |
| **Liste blanche parent menu** | `poke-hub-collections` ajouté à `poke_hub_admin_pages()` dans `poke-hub.php` |

**Fonctionnalités de l’écran** :

- Colonnes : nom + ID interne ; **propriétaire** (compte : lien profil utilisateur, e-mail ; **anonyme** : mention « Anonyme », IP `anonymous_ip`, préfixe de `anonymous_owner_key`) ; **type** (libellé de catégorie) ; **avancement** (possédés / total, % possédés, détail pour échange — même logique que le front : pool + **`poke_hub_collections_resolved_status_for_row`**) ; visibilité publique / privée ; jeton `share_token` ; date de mise à jour.
- **Lien « Voir »** : **`poke_hub_collections_public_view_url()`** — préfixe = permalien de la page collections (`poke_hub_page_collections`) + `/` + `share_token`.
- **Filtres** : tous propriétaires / compte enregistré uniquement / anonymes uniquement ; recherche par nom.
- **Suppression** : unitaire ou en masse, via **`poke_hub_collections_admin_force_delete()`** (`collections-helpers.php`) — suppression **pour administrateur**, sans être le propriétaire (avec droit `manage_options`).

**Nouvelles fonctions helpers** (`collections-helpers.php`) :

- **`poke_hub_collections_compute_progress_totals( $pool, $items )`** — totaux `owned` / `for_trade` / `missing`, `percent_owned` ;
- **`poke_hub_collections_public_view_url( $collection )`** — URL publique vue collection ;
- **`poke_hub_collections_admin_force_delete( $collection_id )`** — suppression admin.

**Attention** : une collection **privée anonyme** peut rester **inaccessible sans cookie** créateur même si l’URL est ouverte par un administrateur ; le lien sert avant tout au **référencement** et au partage.

---

### Documentation — stockage anonyme, tri pool, Zarbi/Unown

- **[COLLECTIONS_MODULE.md](../COLLECTIONS_MODULE.md)** : § **Stockage** réécrite (collections **`user_id = 0`** en base après création REST ; clé propriétaire / cookie ; distinction localStorage vs source de vérité serveur) ; § **Schéma** — colonnes **`share_token`**, **`anonymous_ip`**, **`anonymous_owner_key`** (migration `poke_hub_collections_maybe_add_anonymous_owner_key_column`), **`is_public`** ; § **Ordre des lignes** — slugs **`unown-*`** attendus, lien vers import GM ; pas de mention obsolète « sticky / compact » dans le pied de page (référence **barre fixe / drawer**).
- **Tri pool** (`poke_hub_collections_display_variant_sort_rank`, filtre **`poke_hub_collections_display_variant_sort_rank`**) : forme normale → **costumes** → puis équivalent **`pokehub_pokemon_select_category_rank()`** avec paliers collections (dont regroupement `switch_*` / `visual` / `gender` avec le groupe régional) pour garder **l’interclassement** méga / gigamax / dynamax comme les sélecteurs ; documenté dans [COLLECTIONS_MODULE.md](../COLLECTIONS_MODULE.md) § *Ordre des lignes*.
- **Slugs Zarbi** : **`poke_hub_pokemon_compose_pokemon_row_slug()`** documenté dans [GAME_MASTER_IMPORT.md](../pokemon/GAME_MASTER_IMPORT.md) § *Slug ligne Pokémon*.
- **[README.md](../README.md)** : entrées **Collections** et fiche COLLECTIONS_MODULE alignées sur ce comportement.

---

*Index dossier collections : [README.md](./README.md)*
