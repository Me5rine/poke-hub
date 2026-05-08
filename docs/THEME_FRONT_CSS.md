# CSS front : thème vs plugin (migration)

Ce document est la **référence** pour le chargement du CSS public Poké HUB : ce qui vit dans le **thème** (en particulier **Me5rine Lab**), ce qui reste dans le **plugin**, et le filtre WordPress qui bascule d’un mode à l’autre.

## En bref — règle unique (production Me5rine Lab)

| Où ? | Contenu | Quand c’est utilisé |
|------|---------|----------------------|
| **Thème enfant** `me5rine-lab/css/poke-hub/` | Tout le **CSS public** des modules : chaque fichier `parts/*.css` est enqueued **séparément** via `functions.php` (`glob` + tri naturel ; **tout nouveau `*.css` ajouté dans `parts/` est chargé automatiquement**, version = `filemtime` par fichier), puis `poke-hub-late-overrides.css` en **dernière** couche. Le fichier **`poke-hub-front.css`** garde les `@import` des `parts/` comme **index lisible / référence** et pour **`add_editor_style()`** ; en front navigateur ce fichier n’est **plus** enqueue (évite la dépendance cache des `@import`). | Toujours en prod sur Me5rine Lab : c’est la **seule** source de vérité visuelle front pour le « gros lot ». |
| **Plugin** `poke-hub/assets/css/` | **Minimum** : surtout **admin** ; `global-colors.css` (notices, cohérence, besoins Gutenberg) ; **`poke-hub-type-icons.css`** (icônes types en SVG — voir tableau *Ce qui reste* ci‑dessous). | Le plugin **n’enfile plus** le pack `poke-hub-*-front` du dossier `assets/css/` quand le filtre ci‑dessous est à `false` (le thème a déjà tout repris), **sauf** les feuilles **admin** et l’enqueue **explicite** des icônes de types en admin (voir tableau). |

**Filtre WordPress** : `poke_hub_load_default_plugin_front_css`

- Thème Me5rine : `add_filter( 'poke_hub_load_default_plugin_front_css', '__return_false' );` dans `functions.php` du thème enfant.
- Comportement : les appels à `poke_hub_enqueue_bundled_front_style()` **ne chargent plus** les feuilles listées côté plugin ; le hook `poke_hub_maybe_dequeue_plugin_front_styles` **retire** les handles connus (voir `poke_hub_get_plugin_front_style_handles()` dans `includes/functions/pokehub-front-styles-bridge.php`) pour éviter **toute double charge**.

**Sites sans ce thème** (ou en phase de test) : laisser le filtre par défaut à `true` : le plugin charge les `assets/css/poke-hub-*-front.css` **s’ils existent** dans le dépôt du plugin.

```mermaid
flowchart LR
  subgraph theme["Thème me5rine-lab"]
    A[parts/*.css enqueue chaînés ver=filemtime]
    A --> B[poke-hub-late-overrides.css]
    C[poke-hub-front.css ref / éditeur]
  end
  subgraph plugin["Plugin poke-hub"]
    E[admin-unified, metaboxes, …]
    F[global-colors + poke-hub-type-icons admin explicite]
  end
  G["Filtre poke_hub_load_default_plugin_front_css = false"] --> H["Pack front module = thème seulement"]
  G --> I["Déqueue des handles listés côté plugin"]
```

*Point d’entrée côté thème (dépôt me5rine-lab) : `css/poke-hub/README.md`.*

## Objectif

- **Une seule source de vérité** pour l’intégration visuelle (variables `me5rine-lab-*`, composants, modules) : le thème enfant, pas des doublons `assets/css/*.css` côté plugin en production.
- **Ordre de cascade** explicite : d’abord le thème parent (Hello Elementor), puis la base **Me5rine Lab**, puis la couche **Poké HUB** (surcharge plugin / correctifs ciblés).
- Le plugin conserve le **minimum** : styles **admin**, **`global-colors.css`** (Gutenberg, notices, alignement sur la charte), et éventuellement de **petits correctifs** front optionnels (voir plus bas).

## Filtre : `poke_hub_load_default_plugin_front_css`

- **Défaut** : `true` — le plugin enfile les feuilles listées dans `includes/functions/pokehub-front-styles-bridge.php` via `poke_hub_enqueue_bundled_front_style()` / handles enregistrés par les modules, **uniquement si** le fichier correspondant existe sous `assets/css/`.
- **Thème Me5rine Lab (production)** : le thème enfant retourne `false` sur ce filtre (`add_filter( 'poke_hub_load_default_plugin_front_css', '__return_false' );` dans `functions.php` du thème). Le hook `poke_hub_maybe_dequeue_plugin_front_styles` **désenfile** alors tous les handles enregistrés via `poke_hub_get_plugin_front_style_handles()` (y compris les styles front des modules) pour éviter toute **double** charge.
- **Intégration manuelle d’un thème tiers** : même principe — fournir l’équivalent du lot front (ou un sous-ensemble) dans le thème, puis `__return_false` sur le filtre. Voir aussi [THEME_INTEGRATION.md](./THEME_INTEGRATION.md) (méthode par copie depuis **FRONT_CSS.md** / **CSS_RULES.md** si vous ne reprenez pas le dépôt du thème Me5rine).

## Ce qui reste dans le plugin (`assets/css/`)

| Fichier / rôle | Rôle |
|----------------|------|
| `global-colors.css` | Variables notices / couleurs partagées (admin + besoins Gutenberg) ; **toujours** pertinent. |
| `poke-hub-type-icons.css` | Icônes de **types** Pokémon (SVG inline, `currentColor`, classes `pokehub-type-icon--admin-list` / `--admin-preview`, cellule liste `.pokehub-type-icon-list-cell`). **Admin** : chargé **systématiquement** sur les écrans du plugin via `poke_hub_enqueue_admin_unified_styles()` dans `poke-hub.php` (chemin explicite), **sans dépendre** du filtre `poke_hub_load_default_plugin_front_css`. **Front** : enregistré sur `init` par `poke_hub_register_bundled_front_style()` dans `includes/functions/pokehub-pokemon-type-icon.php` **uniquement** si le filtre vaut `true` et le fichier est lisible. En production Me5rine (`false`), le thème reprend les mêmes règles dans `css/poke-hub/parts/02-type-icons.css` — **garder les deux fichiers alignés** si vous modifiez les styles. |
| `admin-unified.css`, `pokehub-metaboxes-admin.css` | Administration uniquement. |
| Fichiers `poke-hub-*-front.css` historiques | S’ils sont **absents** du dépôt, les modules n’enquent rien de ce côté ; le thème fournit l’équivalent. |

Code : `includes/functions/pokehub-front-styles-bridge.php` (helpers, liste des handles, déqueue).

## Thème enfant Me5rine Lab (référence)

### Ordre de chargement (front)

1. **Thème parent** (Hello Elementor) — handles tels que `hello-elementor`, `hello-elementor-theme-style`, etc.
2. **Me5rine Lab** — handle `me5rine-child-style` (fichier servi via `style-handler.php` d’après `style.css` : intégrations, formulaires, tableaux, dashboard, profils, menu, **responsive** `css/responsive.css`, **um** `css/um-responsive.css`). **Aucun** import Poké HUB dans ce `style.css`.
3. **Poké HUB** — enqueued **après** `me5rine-child-style` (priorité `100000` dans le `functions.php` du thème) :
   - `me5rine-poke-hub-part-<nom>` → un handle par fichier dans `css/poke-hub/parts/*.css` (**`glob` + tri naturel** : tout nouveau `*.css` dans ce dossier est chargé **sans modifier le PHP** ; `?ver=` = `filemtime` via `me5rine_child_theme_asset_version()`).
   - `me5rine-poke-hub-late` → `css/poke-hub/poke-hub-late-overrides.css` dépend du **dernier** `part` (correctifs de cascade : Collections vs `dashboard.css` / responsive, liste collections, `<details>` avancé, tuiles filtrées JS, bannière reset `[hidden]` vs `forms.css`, etc.) ; même principe de version par fichier.

Détail des helpers de versionnement : voir la section **Cache navigateur (`?ver=`)** plus bas, et le `css/poke-hub/README.md` dans le thème.

Commentaires détaillés : en-tête de `style.css` du thème enfant.

### Éditeur (blocs)

- `add_editor_style( esc_url_raw( me5rine_child_theme_editor_style_url( 'css/poke-hub/poke-hub-front.css' ) ) );`
- `add_editor_style( esc_url_raw( me5rine_child_theme_editor_style_url( 'css/poke-hub/poke-hub-late-overrides.css' ) ) );`

`me5rine_child_theme_editor_style_url()` renvoie une URL absolue + `?ver=filemtime` (le wrapper natif `add_editor_style()` ne versionne pas un chemin relatif). Ordre identique au front.

### Cache navigateur (`?ver=`)

Les paramètres de version sont calculés à partir du `filemtime` du fichier sur le disque : **dès qu’un `.css` ou `.js` est modifié**, l’URL change et le navigateur (et un éventuel CDN qui respecte les query strings) recharge la nouvelle version sans dépendre du numéro de version du plugin.

| Côté | Helper | Usage |
|------|--------|-------|
| Thème enfant | `me5rine_child_theme_asset_version( 'chemin/relatif/au/thème.css' )` | 4ᵉ argument `ver` de `wp_enqueue_style` / `wp_enqueue_script`. Pour les `parts/`, c’est déjà géré automatiquement dans `functions.php`. |
| Thème enfant | `me5rine_child_theme_editor_style_url( 'chemin/relatif.css' )` | À encapsuler dans `add_editor_style()` (cf. ci-dessus). |
| Plugin | `poke_hub_plugin_asset_version( 'chemin/relatif/depuis/POKE_HUB_PATH' )` | 4ᵉ argument `ver` pour tout `wp_enqueue_*` pointant sur un fichier du dépôt plugin. Utilisé par `poke_hub_enqueue_bundled_front_style()`, par `poke-hub.php` (admin unifié, type-icons, fallback raster, etc.) et par les modules pour leurs JS. Retombe sur `POKE_HUB_VERSION` si le fichier est absent. |

**À faire pour tout nouveau JS/CSS plugin** : passer le **chemin relatif** au helper en 4ᵉ argument plutôt que `POKE_HUB_VERSION`. Pour les `parts/` du thème : aucune action — `glob()` les détecte. Pour un nouveau JS du thème : utiliser `me5rine_child_theme_asset_version()`.

Si un cache **ignore** les query strings (Cloudflare avec règle `cache.cacheLevel: simplified`, plugin de cache mal configuré), il faut purger ou activer la prise en compte de `?ver=`.

### Surcharges « collections thème »

Dégradés / variables spécifiques : voir **`parts/14-collections-theme.css`** (listé aussi dans `poke-hub-front.css` pour l’éditeur / lisibilité). **`parts/13-collections-front.css`** : masquage des lignes de filtre (`label[data-collections-control].is-hidden`, etc.) pour les options de collection **selon la catégorie** — détail **docs/COLLECTIONS_MODULE.md** (*Options masquées par catégorie*) ; **bloc phrases de recherche GO** (layout, grille de groupes, toolbar des selects) — détail **modules/collections/COLLECTIONS_THEME_CSS.md** (*Bloc phrases de recherche*). Référence classes : **docs/POKEHUB_CSS_CLASSES.md** ; options et comportement du pool : **docs/COLLECTIONS_MODULE.md**.

## Fichiers de référence (dépôt thème, hors plugin)

- `me5rine-lab/style.css` — plan de l’enchaînement me5rine.
- `me5rine-lab/functions.php` — variables dynamiques, enqueue `me5rine-child-style`, couche Poké HUB, filtre `poke_hub_load_default_plugin_front_css`, `add_editor_style`.
- `me5rine-lab/css/poke-hub/README.md` — point d’entrée rapide du dossier.
- `me5rine-lab/css/poke-hub/poke-hub-front.css` — liste `@import` des `parts/` (référence + éditeur) ; en front, chargement effectif = `parts/*.css` individuels (voir `functions.php`).

## Documentation liée

- [THEME_INTEGRATION.md](./THEME_INTEGRATION.md) — guide plus large (héritage : ce document + thèmes sans bundle).
- [ORGANISATION.md](./ORGANISATION.md) — structure du plugin et dossier `assets/`.
- [POKEHUB_CSS_CLASSES.md](./POKEHUB_CSS_CLASSES.md) — classes `pokehub-*` et liens avec `me5rine-lab-*`.
- [FRONT_CSS.md](./FRONT_CSS.md) — référence historique / variables (peut alimenter un thème minimal si vous copiez le contenu).
- [CSS_SYSTEM.md](./CSS_SYSTEM.md) — système de classes.

---

*Index de la documentation : [README du dossier docs](README.md) · [Charte rédactionnelle](REDACTION.md)*
