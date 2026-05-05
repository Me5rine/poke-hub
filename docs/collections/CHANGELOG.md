# Journal des changements documentés — Collections

Ce fichier recense des **évolutions ponctuelles** du module Collections (correctifs, admin, hooks) lorsqu’elles sont explicitement documentées suite à une implémentation. Pour le fonctionnement général, voir [COLLECTIONS_MODULE.md](../COLLECTIONS_MODULE.md).

---

## 2026-05-05

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

*Index dossier collections : [README.md](./README.md)*
