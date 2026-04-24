# Module Pokémon GO Collections – Poké HUB (index)

Le module **collections** gère les **listes de collection** (shortcodes, pool d’objets, statuts possédé / échange / manquant), le routage public et l’API REST associée. Options de composition détaillées (genre Nidoran vs symboles vs doublon mâle/femelle collectionneur, **masquage des réglages inutiles selon la catégorie** : voir *Options masquées par catégorie (UI)* dans **[COLLECTIONS_MODULE.md](../COLLECTIONS_MODULE.md)**), phrases GO copiables : **jeton = nom d’espèce**, **une entrée par dex** sur toutes les sections — incluant **Phrases GO : données serveur et langue** (`pogo_token_*`, `pogo_group_prefix_*`, noms variantes en base). Le **CSS front** est surtout dans le **thème** (Me5rine : `css/poke-hub/parts/13-…`, `14-…` ; doc **[THEME_FRONT_CSS.md](../THEME_FRONT_CSS.md)**), y compris la **mise en page du bloc recherche GO** (toolbar, grille de groupes) dans `13-collections-front.css`. `modules/collections/COLLECTIONS_THEME_CSS.md` complète (variables, classes).

## Pages de documentation

| Document | Emplacement | Contenu principal |
|----------|-------------|-------------------|
| Module Collections (shortcodes, grille, filtres) | [COLLECTIONS_MODULE.md](../COLLECTIONS_MODULE.md) | Usage, shortcodes, statuts, option *Afficher dans la grille* |
| Formes vs catégories de collections | [COLLECTIONS_AND_FORMS_CATEGORIES.md](../COLLECTIONS_AND_FORMS_CATEGORIES.md) | Variants Pokémon vs type de liste |
| Thème / classes CSS collections | [../modules/collections/COLLECTIONS_THEME_CSS.md](../../modules/collections/COLLECTIONS_THEME_CSS.md) | Variables, classes ; intégration thème : [THEME_FRONT_CSS.md](../THEME_FRONT_CSS.md) |

## Rappels code

- Point d’entrée : `modules/collections/collections.php`
- Slug module dans `poke_hub_get_modules_config()` : **`collections`** (libellé admin : *Pokémon GO Collections*)

## Voir aussi

- [POKEHUB_CSS_CLASSES.md](../POKEHUB_CSS_CLASSES.md) — Classes `pokehub-*` partagées
- [ORGANISATION.md](../ORGANISATION.md) — Structure `modules/collections/`

---

*Index de la documentation : [README du dossier docs](../README.md) · [Charte rédactionnelle](../REDACTION.md)*
